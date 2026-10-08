import test from 'node:test';
import assert from 'node:assert/strict';
import http from 'node:http';
import net from 'node:net';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import {once} from 'node:events';
import {spawnSync} from 'node:child_process';
import {setTimeout as delay} from 'node:timers/promises';
import {startRecordings} from '../src/recordings.js';
import {encodeAudioPacket} from '../src/audio-codec.js';

for(const bytes of [6,10])test(`SD replay receiver preserves video, trailing audio and completed jobs (${bytes} byte identity)`,{skip:!fs.existsSync('/usr/bin/ffmpeg'),timeout:20000},async t=>{
    const terminal=bytes===6?'456789012345':'00000123456789012345',secret='recording-receiver-test-'.repeat(3);
    let stopResolve,stopPending=false;
    const backend=http.createServer(async(req,res)=>{
        for await(const _ of req){}
        assert.equal(req.headers.authorization,`Bearer ${secret}`);
        res.setHeader('Content-Type','application/json');
        if(req.url==='/resolve')return res.end(JSON.stringify({id:1,video_terminal_id:terminal,channels:2,frame_rate:10,gps_timezone_minutes:60}));
        if(req.url==='/recordings/stop'){
            stopPending=true;stopResolve=()=>{stopPending=false;if(!res.writableEnded)res.end('{}');};return;
        }
        res.end('{}');
    });backend.listen(0,'127.0.0.1');await once(backend,'listening');
    const root=fs.mkdtempSync(path.join(os.tmpdir(),'exad-recording-test-')),base=`http://127.0.0.1:${backend.address().port}`;
    Object.assign(process.env,{LISTENER_API_TOKEN:secret,LARAVEL_LISTENER_URL:base,GPS_API_URL:base,RECORDING_PORT:'0',RECORDING_API_PORT:'0',LISTENER_BIND:'127.0.0.1',RECORDING_STORAGE:root});
    let service=startRecordings();await once(service.server,'listening');if(!service.api.listening)await once(service.api,'listening');
    let api=`http://127.0.0.1:${service.api.address().port}`;
    const control=(route,data={})=>fetch(api+route,{method:'POST',headers:{Authorization:`Bearer ${secret}`,'Content-Type':'application/json'},body:JSON.stringify({device_id:1,...data})});
    const clients=[];
    t.after(()=>{stopResolve?.();for(const s of clients)s.destroy();service.close();backend.closeAllConnections();backend.close();fs.rmSync(root,{recursive:true,force:true});});
    const record={channel:1,start:'2026-09-28T10:00:00Z',end:'2026-09-28T10:00:01Z',media_type:0,stream_type:0,storage_type:0};
    assert.equal((await fetch(api+'/jobs',{method:'POST',body:'{}'})).status,403);
    const starts=await Promise.all([control('/jobs',{record}),control('/jobs',{record})]);
    assert.deepEqual(starts.map(r=>r.status).sort(),[201,409]);
    const job=(await starts.find(r=>r.status===201).json()).job_id;
    const socket=net.connect(service.server.address().port,'127.0.0.1');clients.push(socket);socket.on('error',()=>{});socket.resume();await once(socket,'connect');
    const generated=spawnSync('/usr/bin/ffmpeg',['-v','error','-f','lavfi','-i','testsrc=size=96x64:rate=10','-t','1.2','-pix_fmt','yuv420p','-c:v','libx264','-preset','ultrafast','-tune','zerolatency','-x264-params','repeat-headers=1:aud=1','-f','h264','pipe:1']);
    assert.equal(generated.status,0,generated.stderr.toString());
    const raw=generated.stdout,edges=[];
    for(let i=0;i<raw.length-5;i++)if(raw[i]===0&&raw[i+1]===0&&raw[i+2]===0&&raw[i+3]===1&&(raw[i+4]&31)===9)edges.push(i);
    edges.push(raw.length);assert.ok(edges.length>10);
    for(let i=0;i<edges.length-1;i++){
        const payload=raw.subarray(edges[i],edges[i+1]),off=bytes-6,h=Buffer.alloc(30+off);
        h.writeUInt32BE(0x30316364);h[4]=0x81;h[5]=98;h.writeUInt16BE(i,6);Buffer.from(terminal,'hex').copy(h,8);h[14+off]=1;h[15+off]=i?0x10:0;
        h.writeBigUInt64BE(BigInt(i*100),16+off);h.writeUInt16BE(payload.length,28+off);socket.write(Buffer.concat([h,payload]));
    }
    // Full audio arrives after the final video frame, as on actual card replays.
    for(let i=0;i<11;i++)socket.write(encodeAudioPacket({terminal,channel:1,codec:6,sequence:100+i,timestamp:i*100,payload:Buffer.alloc(800,0xd5)}));
    socket.end();
    let result;
    for(let i=0;i<80;i++){result=await (await control('/jobs/'+job)).json();if(['ready','failed'].includes(result.status))break;await delay(50);}
    assert.equal(result.status,'ready',JSON.stringify(result));assert.equal(result.audio,true);
    assert.ok(stopPending);assert.equal((await control('/jobs',{record})).status,409,'late stop must not race with a new replay');
    const probe=spawnSync('/usr/bin/ffprobe',['-v','error','-show_entries','stream=codec_name,duration','-of','json',path.join(root,job,'recording.mp4')]);
    assert.equal(probe.status,0);const streams=JSON.parse(probe.stdout).streams;
    assert.equal(streams[0].codec_name,'h264');assert.equal(streams[1].codec_name,'aac');assert.ok(Number(streams[1].duration)>=1);
    stopResolve();await delay(50);service.close();await delay(50);
    service=startRecordings();await once(service.server,'listening');if(!service.api.listening)await once(service.api,'listening');api=`http://127.0.0.1:${service.api.address().port}`;
    assert.equal((await (await control('/jobs/'+job)).json()).status,'ready','completed downloads survive restart');
    const next=await (await control('/jobs',{record})).json();assert.ok(next.job_id);
    assert.equal((await (await control('/jobs/'+next.job_id+'/cancel')).json()).status,'cancelled');
    await delay(50);stopResolve?.();
});
