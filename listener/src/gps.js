import net from 'node:net';
import {recordingQuery,recordingResources,recordingPlayback,recordingStop,RecordingFragments} from './recording-protocol.js';
import { gpsKeepAliveOptions } from './transport-policy.js';
import { backendCall, isTemporaryBackendError } from './backend-recovery.js';
import { pathToFileURL } from 'node:url';
import { setTimeout as delay } from 'node:timers/promises';
import { Frames808, encode808, position808, liveRequest, audioCapabilities } from './protocol.js';
import { event, registry, log, internalServer, reply, safeEqual } from './common.js';

export function startGps() {
    const sessions = new Map(), sockets = new Map();
    const port = Number(process.env.GPS_PORT || 7808);
    const server = net.createServer(socket => {
        if (sockets.size >= 512) return socket.destroy();
        // Cellular setup can deliver the first JT808 frame after 15–20 seconds.
        // Keep a bounded minute for admission, independently of later traffic.
        const handshakeTimeout = 60000;
        socket.setNoDelay(true); socket.setKeepAlive(true, 30000); socket.setTimeout(handshakeTimeout);
        let device = null, identity = null, authenticated = false, serial = 0, queued = 0, checked = 0, lastEvent = 0;
        let rateAt = performance.now(), messages = 0, chain = Promise.resolve(), authorizationPending = false;
        let closing = false, closeReason = null, received = 0, lastMessage = null, pacedWindows = 0, lastPacingLog = -Infinity;
        let messageTypes = {};
        const connectedAt = performance.now(), stopped = new AbortController();
        const disconnect = reason => {
            closeReason ??= reason; closing = true; stopped.abort(); socket.destroy();
        };
        const finish = reason => {
            closeReason ??= reason; closing = true; stopped.abort(); socket.end();
        };
        sockets.set(socket, disconnect);
        const frames = new Frames808(), commands = new Map(), recordingFragments = new RecordingFragments();
        let archiveQuery = null;
        const send = (id, body) => { const n = serial++ & 65535; socket.write(encode808({ ...identity, id, serial: n, body })); return n; };
        const ack = (message, result = 0) => {
            const body = Buffer.alloc(5); body.writeUInt16BE(message.serial); body.writeUInt16BE(message.id, 2); body[4] = result; send(0x8001, body);
        };
        let authorization = null;
        const authorize = () => {
            // Timer, telemetry and media requests share a single in-flight check.
            // Never let an older concurrent response restore a revoked identity.
            if (!authorization) authorization = (async () => {
                stopped.signal.throwIfAborted();
                const current = await backendCall(() => registry(identity.version, identity.terminal));
                stopped.signal.throwIfAborted();
                if (device && (current.id !== device.id || !safeEqual(current.auth_token, device.auth_token))) throw new Error('Device authorization changed');
                device = current; checked = Date.now();
            })().finally(() => { authorization = null; });
            return authorization;
        };
        const ingest = async position => {
            await backendCall(() => event({ device_id: device.id, protocol: identity.version, ip: socket.remoteAddress.replace(/^::ffff:/, ''), position }));
            lastEvent = Date.now();
        };
        let lastBackendLog = -Infinity;
        const retainOnBackendOutage = error => {
            if (!authenticated || !isTemporaryBackendError(error)) return false;
            if (performance.now() - lastBackendLog >= 30000) {
                log('gps_backend_unavailable', { device_id: device.id, reason: error.message });
                lastBackendLog = performance.now();
            }
            return true;
        };
        let presencePending = false;
        const touchPresence = () => {
            if (presencePending || closing || socket.destroyed || Date.now() - lastEvent <= 10000) return;
            // Presence is a consequence of an authenticated frame, not part of
            // the protocol handshake. Do not hold authentication/heartbeat ACKs
            // behind a database write (the camera may time out before it ends).
            // Coalesce bursts; never queue retries or invent traffic on a timer.
            presencePending = true;
            ingest(null).catch(error => {
                if (closing || socket.destroyed) return;
                if (!retainOnBackendOutage(error)) disconnect(error.message);
            }).finally(() => { presencePending = false; });
        };
        let capabilityRequest = null, capabilities = null, capabilitiesAt = 0;
        const session = {
            socket, disconnect,
            async capabilities() {
                if (capabilities && Date.now() - capabilitiesAt < 60000) return capabilities;
                if (!capabilityRequest) capabilityRequest = session.command(0x9003,Buffer.alloc(0),0x1003).finally(() => { capabilityRequest = null; });
                return capabilityRequest;
            },
            async recordings(data) {
                if (archiveQuery) throw Object.assign(Error('Recording query already pending'),{status:409});
                recordingFragments.clear();
                const offset=Number(device.gps_timezone_minutes ?? process.env.GPS_TIMEZONE_MINUTES ?? 60);
                archiveQuery=session.command(0x9205,recordingQuery(data,offset),0x1205,30000)
                    .finally(()=>{archiveQuery=null;recordingFragments.clear();});
                return archiveQuery;
            },
            async command(id, body, replyId = 0x0001, timeout = 8000) {
                await authorize();
                if (!authenticated || socket.destroyed) throw Object.assign(new Error('Device offline'), { status: 409 });
                if (commands.size >= 8) throw new Error('Command queue full');
                return new Promise((resolve, reject) => {
                    const sequence = send(id, body);
                    const timer = setTimeout(() => { commands.delete(sequence); reject(new Error('Device command timeout')); }, timeout);
                    commands.set(sequence, { id, replyId, resolve, reject, timer });
                });
            },
        };
        const handle = async message => {
            // A TCP read can contain many valid frames after a cellular delay. Keep
            // the processing bound, but pace authenticated devices instead of
            // severing their GPS session (which also interrupts their live video).
            if (performance.now() - rateAt >= 1000) { rateAt = performance.now(); messages = 0; messageTypes = {}; }
            if (messages >= 50) {
                if (!authenticated) throw new Error('Unauthenticated message rate limit');
                pacedWindows++;
                if (performance.now() - lastPacingLog >= 30000) {
                    log('gps_paced', { device_id: device.id, message_types: messageTypes, queued_bytes: queued });
                    lastPacingLog = performance.now();
                }
                await delay(Math.max(1, 1000 - (performance.now() - rateAt)), undefined, { signal: stopped.signal });
                rateAt = performance.now(); messages = 0; messageTypes = {};
            }
            stopped.signal.throwIfAborted();
            messages++; received++; lastMessage = '0x' + message.id.toString(16).padStart(4, '0');
            messageTypes[lastMessage] = (messageTypes[lastMessage] || 0) + 1;
            if (identity && (identity.terminal !== message.terminal || identity.version !== message.version)) throw new Error('Identity changed on connection');
            identity = { terminal: message.terminal, version: message.version };
            // A heartbeat only acknowledges this already authenticated transport.
            // Periodic revalidation still closes revoked devices; telemetry and
            // outbound commands retain their authorization/persistence checks.
            if (authenticated && message.id === 0x0002 && !message.fragmented) {
                ack(message); touchPresence(); return;
            }
            if (!device || Date.now() - checked > 5000) {
                try { await authorize(); } catch (error) {
                    if (message.id === 0x0100 && error.status === 404) {
                        const body = Buffer.alloc(3); body.writeUInt16BE(message.serial); body[2] = 4;
                        send(0x8100, body);
                    }
                    throw error;
                }
            }
            if (authenticated && message.id === 0x1205) {
                const pending=[...commands.entries()].find(([,c])=>c.replyId===0x1205);
                if(!pending){ack(message);return;}
                try {
                    const body=recordingFragments.push(message);
                    if(!body){ack(message);return;}
                    const result=recordingResources(body,Number(device.gps_timezone_minutes ?? process.env.GPS_TIMEZONE_MINUTES ?? 60));
                    if(result.serial!==pending[0]){ack(message,1);return;}
                    clearTimeout(pending[1].timer);commands.delete(pending[0]);
                    pending[1].resolve({records:result.records});ack(message);touchPresence();
                }catch(error){
                    recordingFragments.clear();clearTimeout(pending[1].timer);commands.delete(pending[0]);
                    pending[1].reject(error);ack(message,1);
                }
                return;
            }
            if (message.fragmented) { ack(message, 3); return; }
            if (message.id === 0x0100) {
                if (authenticated) throw new Error('Registration on authenticated session');
                if (message.body.length < 4) throw new Error('Invalid registration');
                const body = Buffer.alloc(3); body.writeUInt16BE(message.serial);
                send(0x8100, Buffer.concat([body, Buffer.from(device.auth_token)]));
                return;
            }
            if (message.id === 0x0102) {
                let supplied = message.body.toString();
                if (message.version === '2019') {
                    const n = message.body[0];
                    if (!n || message.body.length < n + 36) throw new Error('Invalid modern authentication');
                    supplied = message.body.subarray(1, 1 + n).toString();
                    if (message.body.subarray(n + 1, n + 16).toString() !== device.imei) throw new Error('Authentication IMEI mismatch');
                }
                if (!safeEqual(supplied, device.auth_token)) { ack(message, 1); return finish('authentication_failed'); }
                const previous = sessions.get(device.id); if (previous && previous !== session) previous.disconnect('replaced_by_new_connection');
                authenticated = true; sessions.set(device.id, session);
                // The legacy ES500-603 profile includes SmartVision/CarAssist
                // cameras. Their quiet intervals can exceed three minutes. Retain
                // authenticated transport; TCP keepalive, peer closure and the
                // periodic registry check still detect loss or revoked access.
                // Silence never generates telemetry or refreshes last_seen_at.
                socket.setTimeout(device.model === 'ES500-603' ? 0 : 180000);
                // Probe quiet cellular links early, but allow widely spaced
                // retries during a network interruption. This is TCP-only;
                // it does not send camera commands or fabricate presence.
                socket.setKeepAlive(...gpsKeepAliveOptions(device.model));
                ack(message); touchPresence(); log('device_authenticated', { device_id: device.id, protocol: message.version }); return;
            }
            if (!authenticated) { ack(message, 1); return finish('authentication_required'); }
            if (message.id === 0x0001) {
                if (message.body.length !== 5) throw new Error('Invalid command acknowledgement');
                const key = message.body.readUInt16BE(0), command = commands.get(key);
                if (command && command.id === message.body.readUInt16BE(2)
                    && !(message.body[4] === 0 && command.replyId !== 0x0001)) {
                    clearTimeout(command.timer); commands.delete(key);
                    if (message.body[4] === 0) command.resolve({ acknowledged: true });
                    else {
                        const result = message.body[4];
                        log('command_rejected', {device_id:device.id, command:'0x'+command.id.toString(16), sequence:key, result});
                        command.reject(Object.assign(new Error('Device rejected command'), {status:422,code:'device_rejected',result}));
                    }
                }
                // The camera already answered: a slow presence write must not
                // turn an accepted live/intercom command into a command timeout.
                touchPresence();
                return;
            }
            if (message.id === 0x1003) {
                const pending = [...commands.entries()].find(([,command]) => command.replyId === 0x1003);
                try {
                    capabilities = audioCapabilities(message.body); capabilitiesAt = Date.now();
                    if (pending) { clearTimeout(pending[1].timer); commands.delete(pending[0]); pending[1].resolve(capabilities); }
                    ack(message);
                    log('audio_capabilities', {device_id:device.id,...capabilities});
                    touchPresence();
                } catch (error) {
                    if (error.backendFailure) throw error;
                    if (pending) { clearTimeout(pending[1].timer); commands.delete(pending[0]); pending[1].reject(error); }
                    ack(message,1);
                }
                return;
            }
            if (message.id === 0x0003) { ack(message); finish('device_logout'); return; }
            if (message.id === 0x0200) {
                await ingest(position808(message.body, Number(device.gps_timezone_minutes ?? process.env.GPS_TIMEZONE_MINUTES ?? 480)));
                ack(message); return;
            }
            if (message.id === 0x0704) {
                if (message.body.length < 3) throw new Error('Invalid location batch');
                const count = message.body.readUInt16BE(0); let offset = 3;
                if (count > 32) throw new Error('Location batch too large');
                for (let i = 0; i < count; i++) {
                    if (offset + 2 > message.body.length) throw new Error('Truncated batch');
                    const length = message.body.readUInt16BE(offset); offset += 2;
                    if (length < 28 || offset + length > message.body.length) throw new Error('Invalid batch length');
                    await ingest(position808(message.body.subarray(offset, offset + length), Number(device.gps_timezone_minutes ?? process.env.GPS_TIMEZONE_MINUTES ?? 480)));
                    offset += length;
                }
                if (offset !== message.body.length) throw new Error('Unexpected batch suffix');
                ack(message); return;
            }
            ack(message, 3); // Never pretend that an unsupported command was processed.
        };
        socket.on('data', chunk => {
            if (closing) return;
            // Apply TCP backpressure until this read is drained, including pacing.
            // The application queue remains bounded even for a continuous sender.
            socket.pause();
            queued += chunk.length;
            if (queued > 65536) return disconnect('queue_limit');
            chain = chain.then(async () => {
                for (const message of frames.push(chunk)) {
                    if (socket.destroyed || closing) break;
                    try { await handle(message); }
                    catch (error) {
                        if (!retainOnBackendOutage(error)) throw error;
                        // Do not confirm persistence of telemetry that the backend
                        // rejected. Keep this authenticated transport for recovery;
                        // continue processing other frames from the same TCP read.
                        ack(message, 1);
                    }
                }
            })
                .catch(error => {
                    if (stopped.signal.aborted) return;
                    log('gps_rejected', { terminal: identity?.terminal, reason: error.message });
                    disconnect(error.message);
                })
                .finally(() => { queued -= chunk.length; if (!queued && !closing && !socket.destroyed) socket.resume(); });
        });
        const authorizationTimer = setInterval(() => {
            if (!authenticated || authorizationPending || closing || socket.destroyed) return;
            authorizationPending = true;
            authorize().catch(error => {
                // 403/404, identity changes and protocol errors remain fatal.
                // A brief PHP/Apache outage is not a device revocation.
                if (!retainOnBackendOutage(error)) disconnect(error.message);
            }).finally(() => { authorizationPending = false; });
        }, 5000);
        const handshakeTimer = setTimeout(() => { if (!authenticated) disconnect('handshake_timeout'); }, handshakeTimeout);
        socket.on('timeout', () => disconnect('idle_timeout'));
        socket.on('error', error => disconnect(error.code || 'socket_error'));
        socket.on('end', () => { closeReason ??= 'peer_closed'; });
        socket.on('close', () => {
            closing = true; stopped.abort();
            clearInterval(authorizationTimer); clearTimeout(handshakeTimer); sockets.delete(socket);
            if (device && sessions.get(device.id) === session) sessions.delete(device.id);
            for (const command of commands.values()) { clearTimeout(command.timer); command.reject(new Error('Device disconnected')); }
            if (authenticated) log('device_disconnected', { device_id: device.id, reason: closeReason || 'connection_closed',
                received_messages: received, paced_windows: pacedWindows, last_message: lastMessage,
                duration_seconds: Math.round((performance.now() - connectedAt) / 1000) });
        });
    });
    server.listen(port, process.env.LISTENER_BIND || '0.0.0.0');
    const api = internalServer(Number(process.env.GPS_API_PORT || 3001), async (_req, res, path, data) => {
        const id = Number(data.device_id), session = sessions.get(id);
        if (path === '/disconnect') { session?.disconnect('platform_disconnect'); return reply(res, 200, { disconnected: true }); }
        if (!session || session.socket.destroyed) return reply(res, 409, { error: 'Device offline' });
        if (path === '/status') { await registry('id', id); return reply(res, 200, { online: true }); }
        if (path === '/recordings/query') {
            const device=await registry('id',id),channel=Number(data.channel);
            if(!Number.isInteger(channel)||channel<0||channel>device.channels)return reply(res,422,{error:'Invalid channel'});
            return reply(res,200,await session.recordings({...data,channel}));
        }
        if (path === '/audio/capabilities') { await registry('id',id); return reply(res,200,await session.capabilities()); }
        const device = await registry('id', id), channel = Number(data.channel);
        if (!Number.isInteger(channel) || channel < 1 || channel > device.channels) return reply(res, 400, { error: 'Invalid channel' });
        if (path === '/recordings/start') return reply(res,200,await session.command(0x9201,recordingPlayback(process.env.VIDEO_PUBLIC_HOST,Number(process.env.RECORDING_PORT||1081),{...data,channel},Number(device.gps_timezone_minutes??60))));
        if (path === '/recordings/stop') return reply(res,200,await session.command(0x9202,recordingStop(channel)));
        if (path === '/live/start') return reply(res, 200, await session.command(0x9101, liveRequest(process.env.VIDEO_PUBLIC_HOST, Number(process.env.VIDEO_PORT || 1078), channel,channel===1?0:1)));
        if (path === '/live/stop') return reply(res, 200, await session.command(0x9102, Buffer.from([channel, 0, 2, 1])));
        if (['/audio/start','/audio/stop'].includes(path)) {
            if (!['listen','talk'].includes(data.mode)) return reply(res,400,{error:'Invalid audio mode'});
            if (path === '/audio/start') return reply(res,200,await session.command(0x9101,liveRequest(process.env.VIDEO_PUBLIC_HOST,Number(process.env.AUDIO_PORT || 1080),channel,data.mode === 'talk' ? 2 : 3,0)));
            return reply(res,200,await session.command(0x9102,Buffer.from([channel,data.mode === 'talk' ? 4 : 0,1,0])));
        }
        return reply(res, 404, { error: 'Not found' });
    });
    const close = () => { for (const disconnect of sockets.values()) disconnect('service_shutdown'); api.close(); server.close(); };
    log('gps_listening', { port });
    return { server, api, close };
}
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
    const service = startGps();
    for (const signal of ['SIGTERM', 'SIGINT']) process.on(signal, () => service.close());
}
