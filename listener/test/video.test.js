import { test } from 'node:test';
import assert from 'node:assert/strict';
import http from 'node:http';
import net from 'node:net';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { once } from 'node:events';
import { spawnSync } from 'node:child_process';
import { startVideo } from '../src/video.js';
import {encodeAudioPacket} from '../src/audio-codec.js';

for (const [identityBytes, normalize] of [[6, false], [10, false], [6, true]]) test('video TCP with '+identityBytes+' identity bytes, normalization='+normalize+' produces HLS and expires revoked access', { skip: !fs.existsSync('/usr/bin/ffmpeg'), timeout: 25000 }, async t => {
    const videoTerminal = identityBytes === 6 ? '456789012345' : '00000123456789012345';
    const secret = 'isolated-media-test-token-'.repeat(3);
    let enabled = true;const receivedAudio=[];
    const backend = http.createServer(async (req, res) => {
        assert.equal(req.headers.authorization, `Bearer ${secret}`);
        let body='';for await (const chunk of req) body+=chunk;
        res.setHeader('Content-Type', 'application/json');
        if (!enabled && req.url === '/resolve') { res.writeHead(404); return res.end('{}'); }
        if (req.url === '/resolve') return res.end(JSON.stringify({ id: 1, imei: '123456789012345', video_terminal_id: videoTerminal, channels: 2, frame_rate: 15, normalize_video_timestamps: normalize }));
        if(req.url==='/ingest')receivedAudio.push(JSON.parse(body));
        res.end('{"acknowledged":true,"online":true}');
    });
    backend.listen(0, '127.0.0.1'); await once(backend, 'listening');
    const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'exadcam-media-test-'));
    const base = `http://127.0.0.1:${backend.address().port}`;
    Object.assign(process.env, { LISTENER_API_TOKEN: secret, LARAVEL_LISTENER_URL: base, GPS_API_URL: base, AUDIO_API_URL:base,
        VIDEO_PORT: '0', VIDEO_API_PORT: '0', LISTENER_BIND: '127.0.0.1', VIDEO_STORAGE: directory });
    const service = startVideo(); await once(service.server, 'listening');
    if (!service.api.listening) await once(service.api, 'listening');
    const api = `http://127.0.0.1:${service.api.address().port}`;
    const clients = [];
    t.after(async () => {
        for (const client of clients) client.destroy();
        await service.close(); backend.closeAllConnections(); backend.close();
        if (fs.readdirSync(directory).length === 0) fs.rmdirSync(directory);
    });
    const connect = async () => {
        const socket = net.connect(service.server.address().port, '127.0.0.1'); clients.push(socket);
        socket.on('error', () => {}); socket.resume(); await once(socket, 'connect'); return socket;
    };
    const packet = (payload, index = 0) => {
        const offset = identityBytes - 6, h = Buffer.alloc(30 + offset); h.writeUInt32BE(0x30316364); h[4] = 0x81; h[5] = 98;
        h.writeUInt16BE(index, 6); Buffer.from(videoTerminal, 'hex').copy(h, 8); h[14 + offset] = 1;
        h.writeBigUInt64BE(BigInt(index * 67), 16 + offset); h.writeUInt16BE(payload.length, 28 + offset);
        return Buffer.concat([h, payload]);
    };
    assert.equal((await fetch(api+'/sessions', { method: 'POST', body: '{}' })).status, 403);
    const denied = await connect(), deniedClosed = once(denied, 'close'); denied.write(packet(Buffer.from([0,0,0,1,0x65]))); await deniedClosed;
    const response = await fetch(api+'/sessions', { method: 'POST', headers: { Authorization: `Bearer ${secret}`, 'Content-Type': 'application/json' }, body: JSON.stringify({ device_id: 1, channel: 1 }) });
    assert.equal(response.status, 201); const session = await response.json();
    const owner='00000000-0000-4000-8000-000000000001';
    const control=(route,extra={})=>fetch(api+route,{method:'POST',headers:{Authorization:`Bearer ${secret}`,'Content-Type':'application/json'},body:JSON.stringify({device_id:1,lease_id:owner,...extra})});
    assert.equal((await control('/audio/attach')).status,200);
    assert.equal((await fetch(api+'/media/00000000-0000-0000-0000-000000000000/index.m3u8')).status, 404);
    const generated = spawnSync('/usr/bin/ffmpeg', ['-hide_banner','-loglevel','error','-f','lavfi','-i','testsrc=size=320x180:rate=15','-t','48','-pix_fmt','yuv420p','-c:v','libx264','-preset','ultrafast','-tune','zerolatency','-g','30','-x264-params','repeat-headers=1:aud=1','-f','h264','pipe:1'], { maxBuffer: 8*1024*1024 });
    assert.equal(generated.status, 0, generated.stderr.toString());
    const raw = generated.stdout, boundaries = [];
    for (let i=0; i<raw.length-5; i++) if (raw[i]===0 && raw[i+1]===0 && raw[i+2]===0 && raw[i+3]===1 && (raw[i+4]&31)===9) boundaries.push(i);
    assert.ok(boundaries.length > 100); boundaries.push(raw.length);
    const socket = await connect();
    socket.write(encodeAudioPacket({terminal:videoTerminal,channel:1,codec:6,sequence:0,timestamp:0,payload:Buffer.alloc(160,0xd5)}));
    for (let i=0;i<boundaries.length-1;i++) socket.write(packet(raw.subarray(boundaries[i], boundaries[i+1]), i));
    let playlist = '';
    for (let i=0;i<80;i++) {
        const r = await fetch(`${api}/media/${session.lease_id}/index.m3u8`);
        if (r.status===200) { playlist=await r.text(); if (playlist.split('#EXTINF:').length - 1 >= 20) break; }
        await new Promise(resolve => setTimeout(resolve, 100));
    }
    assert.equal(playlist.split('#EXTINF:').length - 1, 20, 'A forty-second window remains available for delayed playback');
    const durations = [...playlist.matchAll(/#EXTINF:([0-9.]+)/g)].map(match => Number(match[1]));
    assert.ok(durations.reduce((sum, duration) => sum + duration, 0) >= 39);
    assert.match(playlist, /#EXTM3U/); const segment = playlist.split('\n').find(line => /^segment-.*\.ts$/.test(line));
    assert.ok(segment); const r = await fetch(`${api}/media/${session.lease_id}/${segment}`);
    assert.equal(r.status, 200);
    const segmentBytes = Buffer.from(await r.arrayBuffer()); assert.ok(segmentBytes.length > 0);
    const probe = spawnSync('/usr/bin/ffprobe', ['-v','error','-select_streams','v:0','-show_entries','packet=pts_time','-of','json','pipe:0'], { input: segmentBytes });
    assert.equal(probe.status, 0, probe.stderr.toString());
    const timestamps = JSON.parse(probe.stdout).packets.map(packet => Number(packet.pts_time));
    assert.ok(timestamps.length > 10 && timestamps.every(Number.isFinite));
    for (let i=1; i<timestamps.length; i++) assert.ok(Math.abs(timestamps[i]-timestamps[i-1]-1/15) < 0.002);
    const decoded = spawnSync('/usr/bin/ffmpeg', ['-nostdin','-v','error','-i','pipe:0','-frames:v','1','-f','null','-'], { input: segmentBytes });
    assert.equal(decoded.status, 0, decoded.stderr.toString());
    assert.equal(receivedAudio.length,1);assert.equal(receivedAudio[0].lease_id,owner);
    assert.equal(receivedAudio[0].codec,6);assert.equal(Buffer.from(receivedAudio[0].payload,'base64').length,160);
    assert.equal((await control('/audio/detach',{lease_id:'00000000-0000-4000-8000-000000000002'})).status,200);
    assert.equal((await control('/audio/keepalive')).status,200,'A different owner cannot detach this audio feed');
    assert.equal((await control('/audio/detach')).status,200);
    assert.equal((await fetch(`${api}/media/${session.lease_id}/index.m3u8`)).status,200,'Closing audio preserves existing video viewers');
    if (normalize) {
        // Never silently assign presentation order to a stream with reordered B-frames.
        const sourceClosed = once(socket, 'close');
        const bFrame = packet(raw.subarray(boundaries[0], boundaries[1]), boundaries.length);
        bFrame[15] = 0x20;
        socket.write(bFrame);
        await sourceClosed;
        assert.equal((await fetch(`${api}/media/${session.lease_id}/index.m3u8`)).status, 404);
        return;
    }
    enabled = false;
    for (let i=0;i<90;i++) {
        const r = await fetch(`${api}/media/${session.lease_id}/index.m3u8`);
        if (r.status===404) break;
        await new Promise(resolve => setTimeout(resolve, 100));
    }
    assert.equal((await fetch(`${api}/media/${session.lease_id}/index.m3u8`)).status, 404);
    if(identityBytes===6){
        enabled=true;
        assert.equal((await control('/audio/talk-start')).status,200);
        assert.equal((await control('/sessions',{channel:1})).status,409,'Video cannot replace a live conversation');
        assert.equal((await control('/audio/detach')).status,200);
        assert.equal((await control('/sessions',{channel:1})).status,201,'Video resumes after the microphone releases the channel');
    }
});
