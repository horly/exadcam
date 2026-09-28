// Run ONLY inside a fresh Linux network namespace; never changes host networking.
import assert from 'node:assert/strict';
import fs from 'node:fs';
import http from 'node:http';
import net from 'node:net';
import { once } from 'node:events';
import { execFileSync } from 'node:child_process';
import { setTimeout as delay } from 'node:timers/promises';
import { startGps } from '../src/gps.js';
import { Frames808, encode808 } from '../src/protocol.js';

assert.notEqual(fs.readlinkSync('/proc/self/ns/net'), fs.readlinkSync('/proc/1/ns/net'), 'Must run in a separate network namespace');
execFileSync('ip',['link','set','lo','up']);
const secret='isolated-network-diagnostic-'.repeat(3), auth='synthetic-es500-auth-only';
let enabled=true, received=0;
const backend=http.createServer(async(req,res)=>{
  assert.equal(req.headers.authorization,`Bearer ${secret}`);
  for await(const _ of req) {};
  res.setHeader('Content-Type','application/json');
  if(req.url==='/resolve'){
    if(!enabled){res.writeHead(404);return res.end('{}');}
    return res.end(JSON.stringify({id:1,model:'ES500-603',auth_token:auth,channels:2}));
  }
  received++;res.end('{"accepted":true}');
});
backend.listen(0,'127.0.0.1');await once(backend,'listening');
Object.assign(process.env,{LISTENER_API_TOKEN:secret,LARAVEL_LISTENER_URL:`http://127.0.0.1:${backend.address().port}`,
  GPS_PORT:'0',GPS_API_PORT:'0',LISTENER_BIND:'127.0.0.1',VIDEO_PUBLIC_HOST:'127.0.0.1'});
const service=startGps();await once(service.server,'listening');
const socket=net.connect(service.server.address().port,'127.0.0.1'),replies=[],parser=new Frames808();
socket.on('error',()=>{});socket.on('data',chunk=>replies.push(...parser.push(chunk)));
await once(socket,'connect');
const frame=(id,serial,body=Buffer.alloc(0))=>encode808({id,serial,terminal:'456789012345',body});
const until=async(fn,ms=3000)=>{const end=Date.now()+ms;while(!fn()){if(Date.now()>end)throw Error('Timed out');await delay(20);}};
socket.write(frame(0x0102,1,Buffer.from(auth)));await until(()=>replies.length===1);
await until(()=>received===1);
assert.equal(received,1);
const rule=['OUTPUT','-o','lo','-p','tcp','--sport',String(socket.localPort),'--dport',String(service.server.address().port),'-j','DROP'];
const status=async()=>{
  const r=await fetch(`http://127.0.0.1:${service.api.address().port}/status`,{method:'POST',headers:{Authorization:`Bearer ${secret}`,'Content-Type':'application/json'},body:JSON.stringify({device_id:1})});
  await r.arrayBuffer();return r.status;
};
const started=Date.now();
try{
  execFileSync('iptables',['-A',...rule]);
  console.log(JSON.stringify({event:'synthetic_network_loss_started',at:new Date().toISOString(),duration_seconds:65}));
  await delay(65000);
  const during=await status();
  execFileSync('iptables',['-D',...rule]);
  console.log(JSON.stringify({event:'network_restored',elapsed_seconds:Math.round((Date.now()-started)/1000),gps_status:during,events:received}));
  {
    assert.equal(during,200,'Transport must survive a 65-second cellular interruption');
    assert.equal(received,1,'No invented heartbeats or positions');
    socket.write(frame(0x0002,2));await until(()=>replies.length===2);
    await until(()=>received===2);
    assert.equal(replies.at(-1).body[4],0);assert.equal(received,2);
    enabled=false;await until(()=>socket.destroyed,6500);
    console.log(JSON.stringify({result:'recovery_and_revocation_passed',events:received}));
  }
}finally{
  socket.destroy();service.close();backend.closeAllConnections();backend.close();
}
