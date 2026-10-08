import test from 'node:test';
import assert from 'node:assert/strict';
import http from 'node:http';
import net from 'node:net';
import {once} from 'node:events';
import {setTimeout as delay} from 'node:timers/promises';
import {startGps} from '../src/gps.js';
import {Frames808,encode808} from '../src/protocol.js';
import {recordingQuery,recordingPlayback,recordingResources,RecordingFragments} from '../src/recording-protocol.js';

const request={channel:1,start:'2026-09-27T10:00:00.000Z',end:'2026-09-27T10:10:00.000Z'};
function resources(serial){
    const b=Buffer.alloc(34);b.writeUInt16BE(serial);b.writeUInt32BE(1,2);b[6]=1;
    Buffer.from('260927110000260927110100','hex').copy(b,7);b[28]=1;b[29]=1;b.writeUInt32BE(12345,30);return b;
}
test('recording requests and replies preserve camera clock, type, storage and file size',()=>{
    const b=recordingQuery(request,60);assert.equal(b.length,24);assert.equal(b.subarray(1,7).toString('hex'),'260927110000');assert.equal(b[21],3);
    const result=recordingResources(resources(22),60);assert.equal(result.serial,22);assert.equal(result.records[0].start,request.start);assert.equal(result.records[0].size,12345);
    const playback=recordingPlayback('127.0.0.1',1081,result.records[0],60),n=playback[0];
    assert.equal(playback.length,n+23);assert.equal(playback.readUInt16BE(n+1),1081);assert.equal(playback[n+5],1);assert.equal(playback.subarray(n+11,n+17).toString('hex'),'260927110000');
    assert.throws(()=>recordingQuery({...request,end:'2026-09-30T12:00:00Z'}));
    assert.throws(()=>recordingResources(Buffer.alloc(7)));
    const bad=resources(1);bad[8]=0x19;assert.throws(()=>recordingResources(bad));
});
test('recording reassembly tolerates duplicates and out of order fragments but rejects conflicts and excess',()=>{
    const f=new RecordingFragments(),m={fragmented:true,serial:8,packetTotal:2};
    assert.equal(f.push({...m,packetIndex:2,body:Buffer.from('two')}),null);
    assert.equal(f.push({...m,packetIndex:2,body:Buffer.from('two')}),null);
    assert.equal(f.push({...m,packetIndex:1,body:Buffer.from('one')}).toString(),'onetwo');
    assert.throws(()=>f.push({...m,packetIndex:0,body:Buffer.alloc(1)}));
    f.push({...m,packetIndex:1,body:Buffer.from('a')});
    assert.throws(()=>f.push({...m,packetIndex:1,body:Buffer.from('b')}));
});
test('real firmware fragment serials may increment with packet index including wraparound',()=>{
    const f=new RecordingFragments();
    assert.equal(f.push({fragmented:true,serial:0,packetTotal:3,packetIndex:2,body:Buffer.from('b')}),null);
    assert.equal(f.push({fragmented:true,serial:65535,packetTotal:3,packetIndex:1,body:Buffer.from('a')}),null);
    assert.equal(f.push({fragmented:true,serial:1,packetTotal:3,packetIndex:3,body:Buffer.from('c')}).toString(),'abc');
    f.push({fragmented:true,serial:10,packetTotal:2,packetIndex:1,body:Buffer.from('a')});
    assert.throws(()=>f.push({fragmented:true,serial:50,packetTotal:2,packetIndex:2,body:Buffer.from('b')}));
});
function fragment({terminal,version,serial,body,index,total}){
    const modern=version==='2019',h=Buffer.alloc((modern?17:12)+4);
    h.writeUInt16BE(0x1205);h.writeUInt16BE(0x2000|body.length|(modern?0x4000:0),2);
    if(modern)h[4]=1;Buffer.from(terminal,'hex').copy(h,modern?5:4);h.writeUInt16BE(serial,modern?15:10);
    h.writeUInt16BE(total,h.length-4);h.writeUInt16BE(index,h.length-2);
    const raw=Buffer.concat([h,body]),out=[126];let sum=0;
    for(const x of raw)sum^=x;
    for(const x of [...raw,sum]){if(x===126)out.push(125,2);else if(x===125)out.push(125,1);else out.push(x);}out.push(126);return Buffer.from(out);
}
for(const version of ['2013','2019'])test('recording list over real TCP keeps GPS alive, correlated and authenticated '+version,{timeout:15000},async t=>{
    const secret='recording-isolated-test-'.repeat(3),auth='recording-terminal-test',terminal=version==='2013'?'456789012345':'00000123456789012345';
    const backend=http.createServer(async(req,res)=>{for await(const _ of req){}res.setHeader('Content-Type','application/json');res.end(JSON.stringify(req.url==='/resolve'?{id:2,imei:'123456789012345',model:'ES500-603',auth_token:auth,channels:2,gps_timezone_minutes:60}:{}));});
    backend.listen(0,'127.0.0.1');await once(backend,'listening');
    Object.assign(process.env,{LISTENER_API_TOKEN:secret,LARAVEL_LISTENER_URL:`http://127.0.0.1:${backend.address().port}`,GPS_PORT:'0',GPS_API_PORT:'0',LISTENER_BIND:'127.0.0.1'});
    const service=startGps();await once(service.server,'listening');
    const socket=net.connect(service.server.address().port,'127.0.0.1'),parser=new Frames808();
    let sequence=1,mode='list',acks=0;
    const frame=(id,body)=>encode808({id,terminal,version,serial:sequence++,body});
    socket.on('error',()=>{});
    socket.on('data',data=>{for(const m of parser.push(data)){
        if(m.id===0x8001){acks++;continue;}if(m.id!==0x9205)continue;
        const ack=Buffer.alloc(5);ack.writeUInt16BE(m.serial);ack.writeUInt16BE(m.id,2);ack[4]=mode==='reject'?3:0;
        socket.write(frame(0x0001,ack));if(mode==='reject')continue;
        const b=mode==='bad'?Buffer.alloc(7):resources(m.serial);
        const serial=sequence++;
        socket.write(Buffer.concat([fragment({terminal,version,serial:version==='2019'?serial+1:serial,body:b.subarray(10),index:2,total:2}),fragment({terminal,version,serial,body:b.subarray(0,10),index:1,total:2})]));
    }});
    t.after(()=>{socket.destroy();service.close();backend.closeAllConnections();backend.close();});await once(socket,'connect');
    socket.write(frame(0x0102,version==='2013'?Buffer.from(auth):Buffer.concat([Buffer.from([auth.length]),Buffer.from(auth),Buffer.from('123456789012345'),Buffer.alloc(20)])));
    for(let i=0;!acks&&i<100;i++)await delay(10);assert.ok(acks);
    const api=(path,data)=>fetch(`http://127.0.0.1:${service.api.address().port}${path}`,{method:'POST',headers:{Authorization:`Bearer ${secret}`,'Content-Type':'application/json'},body:JSON.stringify({device_id:2,...data})});
    let r=await api('/recordings/query',request);assert.equal(r.status,200);assert.equal((await r.json()).records[0].size,12345);
    mode='reject';r=await api('/recordings/query',request);assert.equal(r.status,422);assert.equal((await r.json()).code,'device_rejected');
    mode='bad';r=await api('/recordings/query',request);assert.equal(r.status,422);
    assert.equal((await api('/status',{})).status,200);assert.equal(socket.destroyed,false);
});
