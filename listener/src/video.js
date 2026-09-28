import net from 'node:net';
import fs from 'node:fs';
import path from 'node:path';
import { randomUUID } from 'node:crypto';
import { spawn } from 'node:child_process';
import { pathToFileURL } from 'node:url';
import { Frames1078, MediaFrames, decodeBcd } from './protocol.js';
import { registry, post, log, internalServer, reply } from './common.js';
import {AudioFrames} from './audio-codec.js';
import {videoMuxerArgs, videoStartupBuffer} from './video-profile.js';
import { removeMediaDirectory } from './media-cleanup.js';

export function startVideo() {
    const streams = new Map(), leases = new Map(), sockets = new Set(), starting = new Map();
    const conversations=new Map();
    const intercomBlocks = (id,channel) => {
        const owner=conversations.get(id);
        return owner?.expires>Date.now() && (owner.allChannels || channel===1);
    };
    const root = path.resolve(process.env.VIDEO_STORAGE || '/var/lib/exadcam-media');
    fs.mkdirSync(root, { recursive: true, mode: 0o750 });
    // Reclaim only our own UUID directories after a service restart.
    for (const entry of fs.readdirSync(root, { withFileTypes: true })) {
        if (entry.isDirectory() && /^[a-f0-9-]{36}$/.test(entry.name)) void removeMediaDirectory(root, entry.name);
    }
    const gps = (route, data) => post(`${process.env.GPS_API_URL || 'http://127.0.0.1:3001'}${route}`, data);
    const stop = async (stream, reason = 'closed') => {
        if (stream.closed) return;
        stream.closed = true;
        streams.delete(stream.key);
        for (const [key, lease] of leases) if (lease.stream === stream) leases.delete(key);
        stream.socket?.destroy();
        if (stream.process) {
            stream.process.kill('SIGTERM');
            const timer = setTimeout(() => { if (stream.process.exitCode === null) stream.process.kill('SIGKILL'); }, 2000);
            timer.unref();
        }
        // ES500 replaces AV with intercom itself. A preceding AV Stop can also
        // disable its audio path; reserve/drain locally and let 0x9101 switch it.
        if (!stream.startRejected && !(reason === 'intercom' && stream.device.model === 'ES500-603')) await gps('/live/stop', { device_id: stream.device.id, channel: stream.channel }).catch(() => {});
        await removeMediaDirectory(root, stream.id);
        log('video_stopped', { device_id: stream.device.id, channel: stream.channel, reason });
    };
    const spawnMuxer = stream => {
        const directory = path.join(root, stream.id);
        fs.mkdirSync(directory, { recursive: true, mode: 0o750 });
        const child = spawn(process.env.FFMPEG_PATH || '/usr/bin/ffmpeg', videoMuxerArgs(stream.device, directory), { stdio: ['pipe', 'ignore', 'pipe'] });
        stream.process = child;
        let diagnostic = '';
        child.stderr.on('data', chunk => { diagnostic = (diagnostic + chunk.toString()).slice(-1500); });
        child.stdin.on('error', () => { void stop(stream, 'encoder_input_failed'); });
        child.on('error', () => { void stop(stream, 'encoder_start_failed'); });
        child.on('close', code => {
            if (!stream.closed) { log('encoder_closed', { device_id: stream.device.id, code, diagnostic }); void stop(stream, 'encoder_closed'); }
        });
        return child;
    };
    const create = async (id, channel) => {
        if(intercomBlocks(id,channel))throw Object.assign(Error('Intercom active'),{status:409});
        const device = await registry('id', id);
        if (!Number.isInteger(channel) || channel < 1 || channel > device.channels) throw new Error('Invalid channel');
        await gps('/status', { device_id: id });
        if(intercomBlocks(id,channel))throw Object.assign(Error('Intercom active'),{status:409});
        const key = `${device.video_terminal_id}:${channel}`;
        if (starting.has(key)) return starting.get(key);
        if (streams.has(key)) return streams.get(key);
        const pending = (async () => {
            if (streams.size >= Number(process.env.MAX_VIDEO_STREAMS || 8)) throw new Error('Concurrent video limit reached');
            const stream = { id: randomUUID(), key, device, channel, created: Date.now(), checked: Date.now(), lastPacket: Date.now(), closed: false, frames: new MediaFrames(), audioFrames:new AudioFrames(),audioQueued:0, audioChain:Promise.resolve(), bytes: 0 };
            streams.set(key, stream);
            try { await gps('/live/start', { device_id: id, channel }); }
            catch (error) {
                // Some devices send media without acknowledging 0x9101 promptly.
                // Keep the already-authorized request during the existing 30 s
                // media deadline instead of sending Stop and starting over.
                if (error.status === 503 || ['TimeoutError','AbortError'].includes(error.name)) {
                    log('video_start_ack_delayed', { device_id: id, channel });
                } else {
                // A refused command did not start our stream. Sending 0x9102 here
                // can stop a channel already owned by another platform.
                stream.startRejected = error.code === 'device_rejected';
                await stop(stream, 'start_failed'); throw error;
                }
            }
            log('video_requested', { device_id: id, channel });
            return stream;
        })();
        starting.set(key, pending);
        try { return await pending; } finally { starting.delete(key); }
    };
    const server = net.createServer(socket => {
        if (sockets.size >= 64) return socket.destroy();
        sockets.add(socket);
        socket.setTimeout(15000); socket.setKeepAlive(true, 30000);
        const parser = new Frames1078(header => {
            // Choose only among exact identities of requested streams. Leading zeroes
            // alone cannot distinguish a short SIM identifier from a long IMEI.
            const matches = [];
            for (const bytes of [6, 10]) {
                try {
                    const terminal = decodeBcd(header.subarray(8, 8 + bytes));
                    const candidate = streams.get(`${terminal}:${header[8 + bytes]}`);
                    if (candidate && !candidate.closed) matches.push(bytes);
                } catch { /* Not a BCD identity of this length. */ }
            }
            if (matches.length !== 1) throw new Error('No unambiguous authorized video session');
            return matches[0];
        });
        let stream = null, queued = 0, chain = Promise.resolve();
        socket.on('data', chunk => {
            queued += chunk.length;
            if (queued > 2 * 1024 * 1024) return socket.destroy();
            chain = chain.then(async () => {
                for (const packet of parser.push(chunk)) {
                    if (socket.destroyed) break;
                    const key = `${packet.terminal}:${packet.channel}`;
                    if (!stream) {
                        stream = streams.get(key);
                        if (!stream || stream.closed || stream.socket) throw new Error('No authorized video session');
                        const device = await registry('video', packet.terminal);
                        if (device.id !== stream.device.id) throw new Error('Video identity mismatch');
                        await gps('/status', { device_id: device.id });
                        if (stream.closed || stream.socket) throw new Error('Video session already claimed or closed');
                        stream.socket = socket; stream.checked = Date.now(); socket.setTimeout(30000);
                    }
                    if (stream.closed || stream.key !== key) throw new Error('Video channel changed');
                    stream.lastPacket = Date.now(); stream.bytes += packet.payload.length;
                    if(packet.type===3){
                        // Audio shares the camera's channel-one socket. A second listen command
                        // replaces this video connection on JK114 firmware.
                        const feed=stream.audioFeed;
                        if(feed&&feed.expires>Date.now()){
                            try{
                                const frame=stream.audioFrames.push(packet);
                                if(frame&&frame.length<=4096&&stream.audioQueued<16){
                                    stream.audioQueued++;
                                    stream.audioChain=stream.audioChain.then(()=>{
                                        if(stream.closed||stream.audioFeed!==feed)return;
                                        return post(`${process.env.AUDIO_API_URL||'http://127.0.0.1:3003'}/ingest`,{device_id:stream.device.id,lease_id:feed.id,codec:packet.payloadType,payload:frame.toString('base64')});
                                    }).catch(()=>{}).finally(()=>stream.audioQueued--);
                                }
                            }catch{stream.audioFrames=new AudioFrames();}
                        }
                        continue;
                    }
                    if (stream.device.normalize_video_timestamps && packet.type === 2) throw new Error('Timestamp normalization requires I/P video without B-frames');
                    const frame = stream.frames.push(packet);
                    if (!frame) continue;
                    // Wait for the first keyframe so SPS/PPS and a decodable start are present.
                    if (!stream.process && packet.type !== 0) continue;
                    const encoder = stream.process || spawnMuxer(stream);
                    if (!encoder.stdin.write(frame)) {
                        socket.pause();
                        await new Promise((resolve, reject) => {
                            const timer = setTimeout(() => { clean(); reject(new Error('Video backpressure timeout')); }, 5000);
                            const drain = () => { clean(); resolve(); };
                            const fail = () => { clean(); reject(new Error('Video writer closed')); };
                            const clean = () => { clearTimeout(timer); encoder.stdin.off('drain', drain); encoder.stdin.off('error', fail); encoder.stdin.off('close', fail); };
                            encoder.stdin.once('drain', drain); encoder.stdin.once('error', fail); encoder.stdin.once('close', fail);
                        });
                        socket.resume();
                    }
                }
            }).catch(error => {
                log('video_rejected', { device_id: stream?.device.id, reason: error.message });
                socket.destroy();
                if (stream?.socket === socket) void stop(stream, error.message);
            }).finally(() => { queued -= chunk.length; });
        });
        socket.on('timeout', () => socket.destroy()); socket.on('error', () => {});
        socket.on('close', () => { sockets.delete(socket); if (stream?.socket === socket) void stop(stream, 'source_disconnected'); });
    });
    server.listen(Number(process.env.VIDEO_PORT || 1078), process.env.LISTENER_BIND || '0.0.0.0');
    const api = internalServer(Number(process.env.VIDEO_API_PORT || 3002), async (_req, res, url, data) => {
        if(url.startsWith('/audio/')){
            const id=Number(data.device_id),lease=data.lease_id;
            if(!Number.isInteger(id)||!/^[a-f0-9-]{36}$/.test(lease||''))return reply(res,400,{error:'Invalid audio owner'});
            const current=[...streams.values()].find(s=>s.device.id===id&&s.channel===1);
            if(url==='/audio/attach'){
                const stream=await create(id,1);
                if(stream.audioFeed&&stream.audioFeed.id!==lease&&stream.audioFeed.expires>Date.now())return reply(res,409,{error:'Audio busy'});
                stream.audioFeed={id:lease,expires:Date.now()+20000};
                return reply(res,200,{attached:true});
            }
            if(url==='/audio/talk-start'){
                const previous=conversations.get(id);
                if(previous&&previous.id!==lease&&previous.expires>Date.now())return reply(res,409,{error:'Intercom busy'});
                const device=await registry('id',id);
                const allChannels=device.model==='ES500-603';
                conversations.set(id,{id:lease,expires:Date.now()+20000,allChannels});
                const affected=[...streams.values()].filter(s=>s.device.id===id&&(allChannels||s.channel===1));
                // Reserve first, then drain every command already in flight.
                await Promise.all(affected.map(s=>starting.get(s.key)?.catch(()=>{})));
                await Promise.all(affected.map(s=>stop(s,'intercom')));
                return reply(res,200,{reserved:true});
            }
            if(url==='/audio/keepalive'){
                const owner=data.mode==='talk'?conversations.get(id):current?.audioFeed;
                if(owner?.id!==lease)return reply(res,404,{error:'Audio expired'});
                owner.expires=Date.now()+20000;return reply(res,200,{renewed:true});
            }
            if(url==='/audio/detach'){
                if(conversations.get(id)?.id===lease)conversations.delete(id);
                if(current?.audioFeed?.id===lease){delete current.audioFeed;if(![...leases.values()].some(l=>l.stream===current))await stop(current,'audio_closed');}
                return reply(res,200,{detached:true});
            }
            return reply(res,404,{error:'Not found'});
        }
        if (url === '/revoke') {
            await Promise.all([...streams.values()].filter(s => s.device.id === Number(data.device_id)).map(s => stop(s, 'revoked')));
            return reply(res, 200, { revoked: true });
        }
        if (url === '/sessions') {
            const stream = await create(Number(data.device_id), Number(data.channel));
            const leaseId = randomUUID();
            leases.set(leaseId, { stream, expires: Date.now() + 60000 });
            return reply(res, 201, { lease_id: leaseId, url: `/live-media/${leaseId}/index.m3u8`, status: 'waiting', startup_buffer_seconds: videoStartupBuffer(stream.device) });
        }
        const match = url.match(/^\/sessions\/([a-f0-9-]{36})\/(keepalive|stop)$/);
        const lease = match && leases.get(match[1]);
        if (!lease || lease.expires <= Date.now() || lease.stream.closed) return reply(res, 404, { error: 'Session expired' });
        if (match[2] === 'stop') {
            leases.delete(match[1]);
            if (![...leases.values()].some(l => l.stream === lease.stream)) await stop(lease.stream);
            return reply(res, 200, { stopped: true });
        }
        await registry('id', lease.stream.device.id);
        lease.expires = Date.now() + 60000;
        const ready = fs.existsSync(path.join(root, lease.stream.id, 'index.m3u8'));
        return reply(res, 200, { status: ready ? 'ready' : 'waiting', bytes_received: lease.stream.bytes });
    }, async (req, res, url) => {
        if (req.method !== 'GET' && req.method !== 'HEAD') return reply(res, 405, { error: 'Method not allowed' });
        const match = url.pathname.match(/^\/media\/([a-f0-9-]{36})\/(index\.m3u8|segment-\d{6,12}\.ts)$/);
        const lease = match && leases.get(match[1]);
        if (!lease || lease.expires <= Date.now() || lease.stream.closed) return reply(res, 404, { error: 'Session expired' });
        const file = path.join(root, lease.stream.id, match[2]);
        let stat;
        try { stat = await fs.promises.stat(file); } catch { return reply(res, 404, { error: 'Stream not ready' }); }
        res.writeHead(200, { 'Content-Type': file.endsWith('.m3u8') ? 'application/vnd.apple.mpegurl' : 'video/mp2t', 'Content-Length': stat.size,
            'Cache-Control': 'private, no-store', 'X-Content-Type-Options': 'nosniff', 'Referrer-Policy': 'no-referrer' });
        if (req.method === 'HEAD') return res.end();
        const input = fs.createReadStream(file);
        input.on('error', () => res.destroy()); res.on('close', () => input.destroy()); input.pipe(res);
    });
    let checking = false;
    const timer = setInterval(async () => {
        if (checking) return; checking = true;
        try {
            for(const [id,conversation] of conversations)if(conversation.expires<=Date.now())conversations.delete(id);
            for (const [key, lease] of leases) if (lease.expires <= Date.now()) leases.delete(key);
            await Promise.all([...streams.values()].map(async stream => {
                const viewers = [...leases.values()].some(l => l.stream === stream)||(stream.audioFeed?.expires>Date.now());
                if ((!viewers && Date.now() - stream.created > 20000) || Date.now() - stream.lastPacket > 30000) return stop(stream, 'idle_timeout');
                try {
                    const current = await registry('id', stream.device.id);
                    if (current.video_terminal_id !== stream.device.video_terminal_id || stream.channel > current.channels || Boolean(current.normalize_video_timestamps) !== Boolean(stream.device.normalize_video_timestamps) || current.frame_rate !== stream.device.frame_rate) throw new Error('Registry changed');
                    // The established JT1078 socket is a separate live connection.
                    // Losing JT808 must not discard media that is still arriving.
                    // Pending streams still require GPS authentication; registry,
                    // revocation and the media idle timeout apply to all streams.
                    if (!stream.socket || stream.socket.destroyed) await gps('/status', { device_id: stream.device.id });
                    stream.checked = Date.now();
                    const directory = path.join(root, stream.id);
                    if (fs.existsSync(directory)) {
                        const bytes = fs.readdirSync(directory).reduce((total, file) => { try { return total + fs.statSync(path.join(directory, file)).size; } catch { return total; } }, 0);
                        if (bytes > 128 * 1024 * 1024) throw new Error('Live buffer storage limit');
                    }
                } catch { await stop(stream, 'authorization_or_source_lost'); }
            }));
        } finally { checking = false; }
    }, 5000);
    const close = async () => {
        clearInterval(timer);
        for (const socket of sockets) socket.destroy();
        await Promise.all([...streams.values()].map(s => stop(s, 'service_shutdown')));
        api.close(); server.close();
    };
    log('video_listening', { port: Number(process.env.VIDEO_PORT || 1078) });
    return { server, api, close };
}
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
    const service = startVideo();
    for (const signal of ['SIGTERM', 'SIGINT']) process.on(signal, () => { void service.close(); });
}
