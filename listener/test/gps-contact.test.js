import test from 'node:test';
import assert from 'node:assert/strict';
import http from 'node:http';
import net from 'node:net';
import { once } from 'node:events';
import { setTimeout as delay } from 'node:timers/promises';
import { startGps } from '../src/gps.js';
import { Frames808, encode808 } from '../src/protocol.js';

for (const failure of [403,503]) test(`authentication and heartbeats remain responsive during a presence stall (${failure})`, {timeout:15000}, async t => {
    const secret = 'contact-test-only-'.repeat(4), auth = 'synthetic-camera-token';
    let release, writes = 0, completed = 0, status = 200;
    const gate = new Promise(resolve => { release = resolve; });
    const backend = http.createServer(async (req, res) => {
        assert.equal(req.headers.authorization, `Bearer ${secret}`);
        for await (const chunk of req) { /* drain synthetic request */ }
        res.setHeader('Content-Type','application/json');
        if (req.url === '/resolve') return res.end(JSON.stringify({id:2,model:'ES500-603',auth_token:auth,channels:2}));
        writes++;
        await gate;
        res.writeHead(status); res.end('{}'); completed++;
    });
    backend.listen(0,'127.0.0.1'); await once(backend,'listening');
    Object.assign(process.env,{LISTENER_API_TOKEN:secret,LARAVEL_LISTENER_URL:`http://127.0.0.1:${backend.address().port}`,GPS_PORT:'0',GPS_API_PORT:'0',LISTENER_BIND:'127.0.0.1'});
    const service = startGps(); await once(service.server,'listening');
    const socket = net.connect(service.server.address().port,'127.0.0.1'), received = [], parser = new Frames808();
    socket.on('error',()=>{}); socket.on('data',b=>received.push(...parser.push(b)));
    t.after(()=>{ release(); socket.destroy();service.close();backend.closeAllConnections();backend.close(); });
    await once(socket,'connect');
    const frame = (id,serial,body=Buffer.alloc(0))=>encode808({id,serial,body,terminal:'456789012345'});
    const ack = serial=>received.find(m=>m.id===0x8001&&m.body.readUInt16BE(0)===serial);
    const until = async (predicate, label) => {
        const end = Date.now()+1500;
        while(!predicate()) { assert.ok(Date.now()<end,label); await delay(10); }
    };
    socket.write(frame(0x0102,1,Buffer.from(auth)));
    await until(()=>ack(1),'Authentication ACK is blocked by the presence write');
    assert.equal(ack(1).body[4],0);
    socket.write(Buffer.concat(Array.from({length:20},(_,i)=>frame(0x0002,i+2))));
    await until(()=>ack(21),'Heartbeat ACKs are blocked by the presence write');
    assert.equal(received.filter(m=>m.id===0x8001).length,21);
    assert.equal(writes,1,'at most one background presence write per connection');
    assert.equal(completed,0,'protocol acceptance does not claim presence persistence');
    assert.equal(socket.destroyed,false);
    status=failure; release();
    if (failure===403) {
        await until(()=>socket.destroyed,'revocation from the backend must still close the connection');
    } else {
        await until(()=>completed===1,'the failed write must complete'); await delay(50);
        assert.equal(socket.destroyed,false,'a temporary outage must not close an authenticated socket');
        assert.equal(writes,1,'no retry without a new frame from the camera');
        status=200;
        socket.write(frame(0x0002,22));
        await until(()=>ack(22)&&completed===2,'the next actual heartbeat must refresh presence after recovery');
        assert.equal(ack(22).body[4],0); assert.equal(socket.destroyed,false);
    }
});
