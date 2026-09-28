import test from 'node:test';
import assert from 'node:assert/strict';
import http from 'node:http';
import net from 'node:net';
import { once } from 'node:events';
import { setTimeout as delay } from 'node:timers/promises';
import { startGps } from '../src/gps.js';
import { Frames808, encode808 } from '../src/protocol.js';

test('delayed cellular registration succeeds while repeated registration cannot retain an unauthenticated socket', {timeout:70000}, async t => {
    const secret='admission-test-only-'.repeat(4),auth='synthetic-admission-auth',events=[];
    const backend=http.createServer(async(req,res)=>{
        assert.equal(req.headers.authorization,`Bearer ${secret}`);
        let body='';for await(const chunk of req)body+=chunk;
        const data=JSON.parse(body);res.setHeader('Content-Type','application/json');
        if(req.url==='/resolve')return res.end(JSON.stringify({id:data.terminal==='456789012345'?1:2,model:'ES500-603',auth_token:auth,channels:2}));
        events.push(data);res.end('{}');
    });
    backend.listen(0,'127.0.0.1');await once(backend,'listening');
    Object.assign(process.env,{LISTENER_API_TOKEN:secret,LARAVEL_LISTENER_URL:`http://127.0.0.1:${backend.address().port}`,GPS_PORT:'0',GPS_API_PORT:'0',LISTENER_BIND:'127.0.0.1'});
    const service=startGps();await once(service.server,'listening');
    const clients=[];let repeated;
    t.after(()=>{clearInterval(repeated);clients.forEach(c=>c.socket.destroy());service.close();backend.closeAllConnections();backend.close();});
    const connect=async terminal=>{
        const socket=net.connect(service.server.address().port,'127.0.0.1'),parser=new Frames808(),replies=[];
        socket.on('error',()=>{});socket.on('data',b=>replies.push(...parser.push(b)));
        const client={socket,replies,frame:(id,serial,body=Buffer.alloc(0))=>encode808({id,serial,terminal,body})};
        clients.push(client);await once(socket,'connect');return client;
    };
    const delayed=await connect('456789012345'),untrusted=await connect('456789012346');
    const at=performance.now();let closedAt=null,serial=1;
    untrusted.socket.once('close',()=>{closedAt=performance.now();clearInterval(repeated);});
    const register=()=>{if(!untrusted.socket.destroyed)untrusted.socket.write(untrusted.frame(0x0100,serial++,Buffer.alloc(37)));};
    register();repeated=setInterval(register,8000);
    await delay(22000);
    assert.equal(delayed.socket.destroyed,false,'first JT808 frame must still be accepted after the old 15/20-second deadlines');
    const until=async(predicate,ms=1500)=>{const end=Date.now()+ms;while(!predicate()){assert.ok(Date.now()<end,'expected protocol response or bounded admission closure');await delay(10);}};
    delayed.socket.write(delayed.frame(0x0100,1,Buffer.alloc(37)));
    await until(()=>delayed.replies.some(m=>m.id===0x8100));
    delayed.socket.write(delayed.frame(0x0102,2,Buffer.from(auth)));
    await until(()=>delayed.replies.some(m=>m.id===0x8001&&m.body.readUInt16BE(0)===2&&m.body[4]===0));
    delayed.socket.write(delayed.frame(0x0002,3));
    await until(()=>delayed.replies.some(m=>m.id===0x8001&&m.body.readUInt16BE(0)===3&&m.body[4]===0));
    await until(()=>closedAt!==null,42000);
    assert.ok(closedAt-at>=59000&&closedAt-at<65000,'periodic registration must not extend the absolute admission deadline');
    assert.equal(delayed.socket.destroyed,false,'successful authentication removes the admission deadline');
    assert.ok(events.length>0&&events.every(e=>e.device_id===1),'unauthenticated registration never fabricates presence');
});
