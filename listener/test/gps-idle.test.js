import { test } from 'node:test';
import assert from 'node:assert/strict';
import http from 'node:http';
import net from 'node:net';
import { once } from 'node:events';
import { setTimeout as delay } from 'node:timers/promises';
import { startGps } from '../src/gps.js';
import { encode808, Frames808 } from '../src/protocol.js';

test('ES500 retains silent authenticated transport, without false presence or bypassing revocation', { timeout: 210000 }, async t => {
    const secret = 'isolated-sleep-test-secret-'.repeat(3), auth = 'synthetic-device-token-1234567890';
    const devices = [
        { id: 1, model: 'ES500-603', terminal: '456789012345', imei: '123456789012345' },
        { id: 2, model: 'JK114', terminal: '456789012346', imei: '123456789012346' },
        { id: 3, model: 'ES500-603', terminal: '456789012347', imei: '123456789012347' },
    ];
    let enabled = true; const events = [], clients = [];
    const backend = http.createServer(async (req, res) => {
        assert.equal(req.headers.authorization, `Bearer ${secret}`);
        let body = ''; for await (const chunk of req) body += chunk;
        const data = JSON.parse(body); res.setHeader('Content-Type', 'application/json');
        if (req.url === '/resolve') {
            const device = devices.find(d => data.kind === 'id' ? String(d.id) === data.terminal : d.terminal === data.terminal);
            if (!enabled || !device) { res.writeHead(404); return res.end('{}'); }
            return res.end(JSON.stringify({ ...device, auth_token: auth, channels: 2 }));
        }
        events.push(data); res.end('{"accepted":true}');
    });
    backend.listen(0, '127.0.0.1'); await once(backend, 'listening');
    Object.assign(process.env, { LISTENER_API_TOKEN: secret, LARAVEL_LISTENER_URL: `http://127.0.0.1:${backend.address().port}`, GPS_PORT: '0', GPS_API_PORT: '0', LISTENER_BIND: '127.0.0.1', VIDEO_PUBLIC_HOST: '127.0.0.1' });
    const service = startGps(); await once(service.server, 'listening');
    t.after(() => { clients.forEach(c => c.socket.destroy()); service.close(); backend.closeAllConnections(); backend.close(); });
    const until = async (predicate, ms = 4000) => {
        const end = Date.now() + ms;
        while (!predicate()) { if (Date.now() > end) throw Error('GPS idle test timed out'); await delay(20); }
    };
    for (const device of devices) {
        const socket = net.connect(service.server.address().port, '127.0.0.1'), parser = new Frames808(), replies = [];
        const frame = (id, serial, body = Buffer.alloc(0)) => encode808({ id, serial, terminal: device.terminal, body });
        socket.on('error', () => {});
        socket.on('data', chunk => replies.push(...parser.push(chunk)));
        await once(socket, 'connect'); clients.push({ socket, frame, replies });
        // Resolve the third ES500, but do not authenticate: knowing a registered
        // identity must not remove its unauthenticated timeout.
        socket.write(device.id === 3 ? frame(0x0100, 1, Buffer.alloc(37)) : frame(0x0102, 1, Buffer.from(auth)));
        await until(() => replies.length === 1);
    }
    await until(() => events.length === 2);
    assert.equal(events.length, 2);
    const [es, jk, untrusted] = clients;
    // Real time and real sockets: cross the production three-minute boundary.
    await delay(185000);
    assert.equal(es.socket.destroyed, false, 'a silent authenticated ES500 must retain its transport');
    assert.equal(jk.socket.destroyed, true, 'JK114 retains the existing inactivity limit');
    assert.equal(untrusted.socket.destroyed, true, 'registration alone cannot retain the socket');
    assert.equal(events.length, 2, 'registry revalidation must not fabricate presence or GPS');
    es.socket.write(es.frame(2, 2)); await until(() => es.replies.length === 2);
    assert.equal(es.replies.at(-1).body[4], 0);
    await until(() => events.length === 3);
    assert.equal(events.length, 3, 'only the actual heartbeat refreshes presence');
    assert.equal(events.at(-1).position, null);
    const command = fetch(`http://127.0.0.1:${service.api.address().port}/live/start`, {
        method: 'POST', headers: { Authorization: `Bearer ${secret}`, 'Content-Type': 'application/json' },
        body: JSON.stringify({ device_id: 1, channel: 1 }),
    });
    await until(() => es.replies.some(r => r.id === 0x9101));
    const request = es.replies.find(r => r.id === 0x9101), ack = Buffer.alloc(5);
    ack.writeUInt16BE(request.serial); ack.writeUInt16BE(request.id, 2);
    es.socket.write(es.frame(0x0001, 3, ack)); assert.equal((await command).status, 200);
    enabled = false; await until(() => es.socket.destroyed, 6500);
    assert.equal(events.length, 3, 'revocation closes an idle ES500 without fabricated telemetry');
});
