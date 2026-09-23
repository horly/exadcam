import { test } from 'node:test';
import assert from 'node:assert/strict';
import http from 'node:http';
import net from 'node:net';
import { once } from 'node:events';
import { startGps } from '../src/gps.js';
import { encode808, Frames808 } from '../src/protocol.js';

test('real TCP listener rejects unknown/unauthenticated devices and authenticates both protocol versions', async t => {
    const secret = 'test-only-internal-token-'.repeat(3);
    const auth = 'testDeviceAuthenticationToken1234';
    let enabled = true; const events = [];
    const terminalIds = ['456789012345', '00000123456789012345'];
    const backend = http.createServer(async (req, res) => {
        assert.equal(req.headers.authorization, `Bearer ${secret}`);
        let text = ''; for await (const part of req) text += part;
        const input = JSON.parse(text);
        res.setHeader('Content-Type', 'application/json');
        if (req.url === '/resolve') {
            if (!enabled || !(terminalIds.includes(input.terminal) || (input.kind === 'id' && input.terminal === '1'))) { res.writeHead(404); return res.end('{}'); }
            return res.end(JSON.stringify({ id: 1, imei: '123456789012345', auth_token: auth, channels: 2, gps_timezone_minutes: 60 }));
        }
        events.push(input); res.end('{"accepted":true}');
    });
    backend.listen(0, '127.0.0.1'); await once(backend, 'listening');
    Object.assign(process.env, { LISTENER_API_TOKEN: secret, LARAVEL_LISTENER_URL: `http://127.0.0.1:${backend.address().port}`, GPS_PORT: '0', GPS_API_PORT: '0', LISTENER_BIND: '127.0.0.1' });
    const service = startGps(); await once(service.server, 'listening');
    const clients = [];
    t.after(() => { for (const client of clients) client.destroy(); service.close(); backend.closeAllConnections(); backend.close(); });
    const connect = async () => {
        const socket = net.connect(service.server.address().port, '127.0.0.1'); clients.push(socket);
        socket.on('error', () => {}); await once(socket, 'connect'); socket.resume(); return socket;
    };
    const request = (socket, data) => new Promise((resolve, reject) => {
        const parser = new Frames808();
        const timer = setTimeout(() => { clean(); reject(new Error('Test reply timeout')); }, 3000);
        const listener = chunk => { const result = parser.push(chunk); if (result.length) { clean(); resolve(result[0]); } };
        const clean = () => { clearTimeout(timer); socket.off('data', listener); };
        socket.on('data', listener); socket.write(data);
    });
    const unknown = await connect();
    const closed = once(unknown, 'close'); unknown.write(encode808({ id: 0x0100, terminal: '000000000009', body: Buffer.alloc(37) }));
    await closed; assert.equal(events.length, 0);
    const unauthenticated = await connect();
    const response = await request(unauthenticated, encode808({ id: 0x0002, terminal: terminalIds[0] }));
    assert.equal(response.body[4], 1); assert.equal(events.length, 0);
    for (const version of ['2013', '2019']) {
        const socket = await connect(), terminal = terminalIds[version === '2013' ? 0 : 1];
        const reg = await request(socket, encode808({ id: 0x0100, version, terminal, body: Buffer.alloc(76) }));
        assert.equal(reg.id, 0x8100); assert.equal(reg.body.subarray(3).toString(), auth);
        const credentials = version === '2013' ? Buffer.from(auth) : Buffer.concat([Buffer.from([auth.length]), Buffer.from(auth), Buffer.from('123456789012345'), Buffer.alloc(20)]);
        const result = await request(socket, encode808({ id: 0x0102, version, terminal, body: credentials }));
        assert.equal(result.body[4], 0);
        const position = Buffer.alloc(28);
        position.writeUInt32BE(6, 4); position.writeUInt32BE(4300000, 8); position.writeUInt32BE(15300000, 12);
        Buffer.from('250901120000', 'hex').copy(position, 22);
        const telemetry = await request(socket, encode808({ id: 0x0200, version, terminal, body: position }));
        assert.equal(telemetry.body[4], 0);
        assert.equal(events.at(-1).position.recorded_at, '2025-09-01T11:00:00.000Z');
        const heartbeat = await request(socket, encode808({ id: 2, version, terminal })); assert.equal(heartbeat.body[4], 0);
        const commandParser = new Frames808(), commands = [];
        const respond = chunk => {
            for (const command of commandParser.push(chunk)) {
                if (![0x9003,0x9101,0x9102].includes(command.id)) continue;
                commands.push(command);
                const body = Buffer.alloc(5); body.writeUInt16BE(command.serial); body.writeUInt16BE(command.id,2);
                socket.write(encode808({id:0x0001,version,terminal,body}));
                if (command.id === 0x9003) socket.write(encode808({id:0x1003,version,terminal,body:Buffer.from('06010001014001620102','hex')}));
            }
        };
        socket.on('data',respond);
        const api = async (route,data) => {
            const response = await fetch(`http://127.0.0.1:${service.api.address().port}${route}`, {method:'POST',headers:{Authorization:`Bearer ${secret}`,'Content-Type':'application/json'},body:JSON.stringify({device_id:1,...data})});
            assert.equal(response.status,200); return response.json();
        };
        const capabilities = await api('/audio/capabilities');
        assert.equal(capabilities.codec,6); assert.equal(capabilities.sample_rate,8000);
        assert.equal(capabilities.frame_length,320); assert.equal(capabilities.output,true);
        await api('/audio/capabilities'); assert.equal(commands.length,1);
        process.env.VIDEO_PUBLIC_HOST='127.0.0.1'; process.env.AUDIO_PORT='1080';
        for (const mode of ['listen','talk']) {
            await api('/audio/start',{channel:1,mode});
            assert.equal(commands.at(-1).body.at(-2),mode === 'talk' ? 2 : 3);
            await api('/audio/stop',{channel:1,mode});
            assert.deepEqual(commands.at(-1).body,Buffer.from([1,mode === 'talk' ? 4 : 0,1,0]));
        }
        await api('/live/stop',{channel:1});
        assert.deepEqual(commands.at(-1).body,Buffer.from([1,0,2,1]));
        await api('/live/start',{channel:1});assert.equal(commands.at(-1).body.at(-2),0);
        await api('/live/start',{channel:2});assert.equal(commands.at(-1).body.at(-2),1);
        socket.off('data',respond);
        if (version === '2019') {
            enabled = false;
            const revoked = once(socket, 'close');
            await Promise.race([revoked, new Promise((_, reject) => setTimeout(() => reject(new Error('Revocation timeout')), 8000).unref())]);
        } else socket.destroy();
    }
    assert.equal(events.length, 4);
});
