import { test } from 'node:test';
import assert from 'node:assert/strict';
import http from 'node:http';
import net from 'node:net';
import { once } from 'node:events';
import { startGps } from '../src/gps.js';
import { encode808, Frames808 } from '../src/protocol.js';

async function fixture(t) {
    const terminal = '456789012345', auth = 'synthetic-device-token-1234567890';
    const secret = 'isolated-gps-burst-secret-'.repeat(3), events = [];
    let enabled = true;
    const backend = http.createServer(async (req, res) => {
        assert.equal(req.headers.authorization, `Bearer ${secret}`);
        let body = ''; for await (const chunk of req) body += chunk;
        const data = JSON.parse(body);
        res.setHeader('Content-Type', 'application/json');
        if (req.url === '/resolve') {
            if (!enabled || data.terminal !== terminal) { res.writeHead(404); return res.end('{}'); }
            return res.end(JSON.stringify({ id: 1, imei: '123456789012345', auth_token: auth, channels: 2, gps_timezone_minutes: 60 }));
        }
        events.push(data); res.end('{"accepted":true}');
    });
    backend.listen(0, '127.0.0.1'); await once(backend, 'listening');
    Object.assign(process.env, { LISTENER_API_TOKEN: secret, LARAVEL_LISTENER_URL: `http://127.0.0.1:${backend.address().port}`, GPS_PORT: '0', GPS_API_PORT: '0', LISTENER_BIND: '127.0.0.1' });
    const service = startGps(); await once(service.server, 'listening');
    const socket = net.connect(service.server.address().port, '127.0.0.1');
    const replies = [], parser = new Frames808();
    socket.on('error', () => {});
    socket.on('data', chunk => replies.push(...parser.push(chunk)));
    await once(socket, 'connect');
    t.after(() => { socket.destroy(); service.close(); backend.closeAllConnections(); backend.close(); });
    const frame = (id, serial, body = Buffer.alloc(0)) => encode808({ id, serial, terminal, body });
    const until = async (predicate, timeout = 4500) => {
        const end = Date.now() + timeout;
        while (!predicate()) {
            if (Date.now() > end) throw new Error('Timed out waiting for GPS test condition');
            await new Promise(resolve => setTimeout(resolve, 10));
        }
    };
    const authenticate = async () => {
        socket.write(frame(0x0102, 1, Buffer.from(auth)));
        await until(() => replies.length === 1);
        assert.equal(replies[0].body[4], 0); replies.length = 0;
    };
    return { socket, replies, events, frame, until, authenticate, service, revoke: () => { enabled = false; } };
}

test('authenticated burst is paced without losing acknowledgements, GPS or the connection', { timeout: 8000 }, async t => {
    const f = await fixture(t); await f.authenticate();
    const location = Buffer.alloc(28);
    location.writeUInt32BE(6, 4); location.writeUInt32BE(4300000, 8); location.writeUInt32BE(15300000, 12);
    Buffer.from('260923120000', 'hex').copy(location, 22);
    const frames = Array.from({ length: 120 }, (_, i) => f.frame(2, i + 2));
    frames.push(f.frame(0x0200, 122, location));
    const started = performance.now(); f.socket.write(Buffer.concat(frames));
    await f.until(() => f.replies.length === 121 || f.socket.destroyed);
    assert.equal(f.socket.destroyed, false, 'an authenticated burst must not disconnect the device');
    assert.equal(f.replies.length, 121);
    assert.ok(performance.now() - started >= 1500, 'the burst still obeys the processing rate');
    assert.deepEqual(f.replies.map(reply => reply.body.readUInt16BE(0)), Array.from({ length: 121 }, (_, i) => i + 2));
    assert.ok(f.replies.every(reply => reply.id === 0x8001 && reply.body[4] === 0));
    assert.equal(f.events.at(-1).position.recorded_at, '2026-09-23T11:00:00.000Z');
    f.socket.write(f.frame(2, 123)); await f.until(() => f.replies.length === 122);
});

test('registration floods still close unauthenticated connections without accepting events', { timeout: 5000 }, async t => {
    const f = await fixture(t);
    f.socket.write(Buffer.concat(Array.from({ length: 65 }, (_, i) => f.frame(0x0100, i, Buffer.alloc(37)))));
    await f.until(() => f.socket.destroyed);
    assert.ok(f.replies.length <= 50);
    assert.equal(f.events.length, 0);
});

test('large multimedia reads keep the authenticated session available for subsequent GPS', { timeout: 8000 }, async t => {
    const f = await fixture(t); await f.authenticate();
    const location = Buffer.alloc(28);
    location.writeUInt32BE(6, 4); location.writeUInt32BE(4300000, 8); location.writeUInt32BE(15300000, 12);
    Buffer.from('260923120000', 'hex').copy(location, 22);
    const burst = Buffer.concat([
        ...Array.from({ length: 120 }, (_, i) => f.frame(0x0801, i + 2, Buffer.alloc(1023, 0x7e))),
        f.frame(0x0200, 122, location),
    ]);
    f.socket.write(burst.subarray(0, 37));
    await new Promise(resolve => setTimeout(resolve, 30));
    f.socket.write(burst.subarray(37));
    await f.until(() => f.replies.length === 121 || f.socket.destroyed);
    assert.equal(f.socket.destroyed, false, 'TCP coalescing must not disconnect a valid device');
    assert.equal(f.replies.length, 121);
    // Archiving multimedia is still unsupported; do not claim these uploads were saved.
    assert.ok(f.replies.slice(0, 120).every(reply => reply.body[4] === 3));
    assert.equal(f.replies.at(-1).body[4], 0);
    assert.equal(f.events.at(-1).position.recorded_at, '2026-09-23T11:00:00.000Z');
    f.socket.write(f.frame(2, 123)); await f.until(() => f.replies.length === 122);
});

test('revocation is enforced while an authenticated burst is still queued', { timeout: 10000 }, async t => {
    const f = await fixture(t); await f.authenticate();
    f.socket.write(Buffer.concat(Array.from({ length: 600 }, (_, i) => f.frame(2, i + 2))));
    await f.until(() => f.replies.length >= 49 || f.socket.destroyed);
    assert.equal(f.socket.destroyed, false);
    f.revoke(); await f.until(() => f.socket.destroyed, 6500);
    assert.ok(f.replies.length < 600, 'remaining frames cannot bypass revocation');
    assert.equal(f.events.length, 1);
});

test('shutdown cancels pacing promptly and does not process queued positions', { timeout: 5000 }, async t => {
    const f = await fixture(t); await f.authenticate();
    f.socket.write(Buffer.concat(Array.from({ length: 500 }, (_, i) => f.frame(2, i + 2))));
    await f.until(() => f.replies.length >= 49 || f.socket.destroyed);
    assert.equal(f.socket.destroyed, false);
    f.service.close(); await f.until(() => f.socket.destroyed, 500);
    assert.ok(f.replies.length < 500); assert.equal(f.events.length, 1);
});
