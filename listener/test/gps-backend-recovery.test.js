import test from 'node:test';
import assert from 'node:assert/strict';
import http from 'node:http';
import net from 'node:net';
import { once } from 'node:events';
import { setTimeout as delay } from 'node:timers/promises';
import { startGps } from '../src/gps.js';
import { Frames808, encode808 } from '../src/protocol.js';
import { backendCall, isTemporaryBackendError } from '../src/backend-recovery.js';

async function fixture(t) {
    const token = 'backend-test-only-'.repeat(4), auth = 'synthetic-device-authentication';
    const state = { resolveStatus: 200, eventStatus: 200, events: [], resolutions: 0, received: [] };
    const backend = http.createServer(async (req, res) => {
        assert.equal(req.headers.authorization, `Bearer ${token}`);
        let raw = ''; for await (const part of req) raw += part;
        const data = JSON.parse(raw); res.setHeader('Content-Type', 'application/json');
        if (req.url === '/resolve') {
            state.resolutions++;
            if (state.resolveGate) await state.resolveGate;
            res.writeHead(state.resolveStatus);
            return res.end(state.resolveStatus === 200 ? JSON.stringify({ id: 2, model: 'ES500-603', imei: '123456789012345', auth_token: auth, channels: 2, gps_timezone_minutes: 0 }) : '{}');
        }
        if (state.eventGate) await state.eventGate;
        res.writeHead(state.eventStatus);
        if (state.eventStatus === 200) state.events.push(data);
        res.end('{}');
    });
    backend.listen(0, '127.0.0.1'); await once(backend, 'listening');
    Object.assign(process.env, { LISTENER_API_TOKEN: token, LARAVEL_LISTENER_URL: `http://127.0.0.1:${backend.address().port}`, GPS_PORT: '0', GPS_API_PORT: '0', LISTENER_BIND: '127.0.0.1', VIDEO_PUBLIC_HOST: '127.0.0.1' });
    const service = startGps(); await once(service.server, 'listening');
    const clients = [], parser = new Frames808();
    const socket = net.connect(service.server.address().port, '127.0.0.1'); clients.push(socket);
    socket.on('error', () => {}); socket.on('data', b => state.received.push(...parser.push(b)));
    await once(socket, 'connect');
    t.after(() => { clients.forEach(s => s.destroy()); service.close(); backend.closeAllConnections(); backend.close(); });
    const frame = (id, serial, body = Buffer.alloc(0)) => encode808({ id, serial, body, terminal: '456789012345' });
    const until = async predicate => {
        const deadline = Date.now() + 7000;
        while (!predicate()) { if (Date.now() > deadline) throw Error('Recovery test timed out'); await delay(10); }
    };
    const ackFor = serial => state.received.find(m => m.id === 0x8001 && m.body.readUInt16BE(0) === serial);
    const send = async (id, serial, body) => { socket.write(frame(id, serial, body)); await until(() => ackFor(serial)); return ackFor(serial); };
    await send(0x0102, 1, Buffer.from(auth));
    await until(() => state.events.length === 1);
    assert.equal(state.events.length, 1);
    return { state, backend, service, socket, clients, frame, until, ackFor, send, token };
}

function position() {
    const body = Buffer.alloc(28); body.writeUInt32BE(6, 4);
    body.writeUInt32BE(4300000, 8); body.writeUInt32BE(15300000, 12);
    Buffer.from('260925100000', 'hex').copy(body, 22); return body;
}

test('temporary backend errors are recognized only at the backend boundary', async () => {
    for (const status of [408, 429, 500, 502, 503, 504]) {
        const error = Object.assign(Error('Service unavailable'), { status });
        assert.equal(isTemporaryBackendError(error), false);
        await assert.rejects(backendCall(() => Promise.reject(error)));
        assert.equal(isTemporaryBackendError(error), true);
    }
    for (const error of [Object.assign(Error('Not allowed'), { status: 403 }), Object.assign(Error('Revoked'), { status: 404 }), SyntaxError('Invalid JSON'), Error('Identity changed on connection')]) {
        await assert.rejects(backendCall(() => Promise.reject(error)));
        assert.equal(isTemporaryBackendError(error), false);
    }
    for (const error of [Object.assign(Error('timeout'), { name: 'TimeoutError' }), Object.assign(TypeError('fetch failed'), { cause: { code: 'ECONNREFUSED' } })]) {
        await assert.rejects(backendCall(() => Promise.reject(error)));
        assert.equal(isTemporaryBackendError(error), true);
    }
});

test('503 during periodic authorization preserves the same socket, blocks commands and still enforces revocation', { timeout: 18000 }, async t => {
    const f = await fixture(t); f.state.resolveStatus = 503;
    const checked = f.state.resolutions;
    await f.until(() => f.state.resolutions > checked); await delay(50);
    assert.equal(f.socket.destroyed, false);
    assert.equal(f.state.events.length, 1, 'an open socket does not fabricate presence');
    assert.equal((await f.send(0x0002, 20)).body[4], 0, 'temporary authorization outage must not reject the heartbeat of an authenticated camera');
    const denied = await f.send(0x0200, 2, position());
    assert.equal(denied.body[4], 1); assert.equal(f.state.events.length, 1);
    const response = await fetch(`http://127.0.0.1:${f.service.api.address().port}/live/start`, { method: 'POST', headers: { Authorization: `Bearer ${f.token}`, 'Content-Type': 'application/json' }, body: JSON.stringify({device_id:2,channel:1}) });
    assert.equal(response.status, 503);
    assert.equal(f.state.received.some(m => m.id === 0x9101), false, 'no media command when authorization cannot be revalidated');
    f.state.resolveStatus = 200;
    const accepted = await f.send(0x0200, 3, position()); assert.equal(accepted.body[4], 0);
    assert.equal(f.socket.destroyed, false); assert.equal(f.state.events.length, 2);
    f.state.resolveStatus = 404;
    await f.until(() => f.socket.destroyed);
});

test('an ingest outage rejects unpersisted telemetry without losing the next frame or transport', { timeout: 9000 }, async t => {
    const f = await fixture(t); f.state.eventStatus = 503;
    f.socket.write(Buffer.concat([f.frame(0x0200, 2, position()), f.frame(0x0002, 3)]));
    await f.until(() => f.ackFor(2) && f.ackFor(3));
    assert.equal(f.ackFor(2).body[4], 1, 'never ACK failed persistence as success');
    assert.equal(f.ackFor(3).body[4], 0, 'remaining frames in the TCP read are handled');
    assert.equal(f.state.events.length, 1); assert.equal(f.socket.destroyed, false);
    f.state.eventStatus = 200;
    assert.equal((await f.send(0x0200, 4, position())).body[4], 0);
    assert.equal(f.state.events.length, 2); assert.equal(f.socket.destroyed, false);
    f.state.eventStatus = 403;
    f.socket.write(f.frame(0x0200, 5, position())); await f.until(() => f.socket.destroyed);
});

test('valid command replies refresh actual presence; malformed replies and rejected identities do not', { timeout: 30000 }, async t => {
    const f = await fixture(t);
    await delay(10100);
    const response = Buffer.alloc(5); response.writeUInt16BE(0x9101, 2);
    f.socket.write(f.frame(0x0001, 2, response)); await f.until(() => f.state.events.length === 2);
    assert.equal(f.state.events.at(-1).position, null);
    await delay(10100);
    await f.send(0x1003, 3, Buffer.from('06010001014001620102', 'hex'));
    await f.until(() => f.state.events.length === 3);
    assert.equal(f.state.events.length, 3); assert.equal(f.state.events.at(-1).position, null);
    f.socket.write(f.frame(0x0001, 4, Buffer.alloc(2))); await f.until(() => f.socket.destroyed);
    assert.equal(f.state.events.length, 3);
    const socket = net.connect(f.service.server.address().port, '127.0.0.1'); f.clients.push(socket); socket.on('error',()=>{});socket.resume();
    await once(socket,'connect');socket.write(f.frame(0x0001,5,response));await f.until(()=>socket.destroyed);
    assert.equal(f.state.events.length,3,'an unauthenticated reply is never presence');
});

test('an accepted media command is returned before a slow presence write completes', { timeout: 18000 }, async t => {
    const f = await fixture(t); await delay(10100);
    let release;
    f.state.eventGate = new Promise(resolve => { release = resolve; });
    try {
        const command = fetch(`http://127.0.0.1:${f.service.api.address().port}/live/start`, {
            method: 'POST', headers: { Authorization: `Bearer ${f.token}`, 'Content-Type': 'application/json' },
            body: JSON.stringify({device_id:2,channel:1}),
        });
        await f.until(() => f.state.received.some(m => m.id === 0x9101));
        const request = f.state.received.find(m => m.id === 0x9101), ack = Buffer.alloc(5);
        ack.writeUInt16BE(request.serial); ack.writeUInt16BE(request.id, 2);
        f.socket.write(f.frame(0x0001, 2, ack));
        const response = await Promise.race([command, delay(1500).then(() => { throw Error('Media command blocked by presence write'); })]);
        assert.equal(response.status,200);
        assert.equal(f.state.events.length,1,'presence backend is still blocked');
    } finally { release(); }
    await f.until(() => f.state.events.length===2);
});

test('slow periodic authorization does not block heartbeats and still gates media commands', { timeout: 16000 }, async t => {
    const f = await fixture(t);
    let release;
    f.state.resolveGate = new Promise(resolve => { release = resolve; });
    const checked = f.state.resolutions;
    try {
        await f.until(() => f.state.resolutions > checked);
        f.socket.write(f.frame(0x0002, 30));
        const limit = Date.now()+1500;
        while (!f.ackFor(30) && Date.now()<limit) await delay(10);
        assert.equal(f.ackFor(30)?.body[4], 0, 'heartbeat must not wait behind registry I/O');
        const response = fetch(`http://127.0.0.1:${f.service.api.address().port}/live/start`, {
            method:'POST', headers:{Authorization:`Bearer ${f.token}`,'Content-Type':'application/json'},
            body:JSON.stringify({device_id:2,channel:1}),
        });
        await delay(50);
        assert.equal(f.state.received.some(m=>m.id===0x9101),false);
        f.state.resolveStatus=503; release();
        assert.equal((await response).status,503);
        assert.equal(f.socket.destroyed,false);
        f.state.resolveStatus=404;
        await f.until(()=>f.socket.destroyed);
    } finally { release(); }
});
