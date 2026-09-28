import test from 'node:test';
import assert from 'node:assert/strict';
import http from 'node:http';
import net from 'node:net';
import {once} from 'node:events';
import {WebSocket} from 'ws';
import {setTimeout as delay} from 'node:timers/promises';
import {startAudio} from '../src/audio.js';
import {Frames1078} from '../src/protocol.js';
import {encodeAudioPacket,AudioFrames,AdtsFrames,adtsFormat,g711FrameSize} from '../src/audio-codec.js';

test('audio framing round-trips short and extended identities without leaking video frames',()=>{
    for(const terminal of ['053810725721','00000867934087966430']){
        const wire=encodeAudioPacket({terminal,channel:1,codec:19,sequence:65535,timestamp:123456,payload:Buffer.from([1,2,3])});
        const parser=new Frames1078(terminal.length/2);
        assert.deepEqual(parser.push(wire.subarray(0,12)),[]);
        const [p]=parser.push(wire.subarray(12));assert.equal(p.terminal,terminal);assert.equal(p.type,3);assert.equal(p.timestamp,123456n);assert.equal(p.sequence,65535);
        assert.deepEqual(new AudioFrames().push(p),Buffer.from([1,2,3]));
        assert.throws(()=>new AudioFrames().push({...p,type:0}),/Unsupported/);
    }
});
test('fragmented audio enforces order, size and codec',()=>{
    const frames=new AudioFrames(),base={type:3,payloadType:6,timestamp:1n,sequence:65535,fragment:1,payload:Buffer.from([1,2])};
    assert.equal(frames.push(base),null);assert.deepEqual(frames.push({...base,sequence:0,fragment:2,payload:Buffer.from([3])}),Buffer.from([1,2,3]));
    assert.throws(()=>frames.push({...base,sequence:2,fragment:2}),/Incomplete/);
    assert.throws(()=>frames.push({...base,fragment:0,payload:Buffer.alloc(8193)}),/large/);
});
test('AAC parser extracts complete ADTS frames across transport boundaries',()=>{
    const payload=Buffer.from('fff16c40013ffc0000','hex'); // mono 8 kHz, nine bytes.
    const parser=new AdtsFrames();assert.deepEqual(parser.push(payload.subarray(0,5)),[]);
    assert.deepEqual(parser.push(Buffer.concat([payload.subarray(5),payload])),[payload,payload]);
    assert.deepEqual(adtsFormat(payload),{rate:8000,channels:1});
    assert.throws(()=>new AdtsFrames().push(Buffer.alloc(7)),/Invalid/);
});

for (const [frameSize,firstDelay] of [[80,0],[320,0],[80,17000]]) test('TCP/WebSocket intercom G711 length '+frameSize+', delay '+firstDelay+' and revocation',async t=>{
    const secret='audio-test-internal-secret-'.repeat(3),terminal=frameSize===80?'053810725721':'00000867934087966430';
    const grant='a'.repeat(64);let allowed=true,service,deviceSocket,deviceTimer,stopCount=0,transmitted=[],initialTimer;
    const backend=http.createServer(async(req,res)=>{
        assert.equal(req.headers.authorization,`Bearer ${secret}`);let input='';for await(const chunk of req)input+=chunk;const data=JSON.parse(input||'{}');
        res.setHeader('Content-Type','application/json');
        const respond=(code,value)=>{res.writeHead(code);res.end(JSON.stringify(value));};
        if(req.url==='/resolve')return respond(200,{id:1,model:frameSize===80?'ES500-603':'JK114',video_terminal_id:terminal,channels:2});
        if(req.url==='/audio-access')return respond(allowed&&data.grant===grant&&data.device_id===1?200:403,{});
        if(req.url==='/audio/capabilities')return respond(200,{codec:6,channels:1,sample_rate:8000,frame_length:frameSize,output:true});
        if(req.url==='/audio/attach'){
            deviceTimer=setInterval(()=>{
                fetch(`http://127.0.0.1:${service.api.address().port}/ingest`,{method:'POST',headers:{Authorization:`Bearer ${secret}`,'Content-Type':'application/json'},body:JSON.stringify({device_id:1,lease_id:data.lease_id,codec:6,payload:Buffer.alloc(160,0xd5).toString('base64')})}).catch(()=>{});
            },20);
            return respond(200,{attached:true});
        }
        if(req.url==='/audio/detach'){clearInterval(deviceTimer);stopCount++;return respond(200,{});}
        if(req.url==='/audio/talk-start'||req.url==='/audio/keepalive')return respond(200,{});
        if(req.url==='/audio/start'){
            assert.equal(data.mode,'talk','Listening must share the video source without a competing camera command');
            initialTimer=setTimeout(()=>{
            deviceSocket=net.connect(service.server.address().port,'127.0.0.1');deviceSocket.on('error',()=>{});
            const parser=new Frames1078(terminal.length/2);deviceSocket.on('data',chunk=>transmitted.push(...parser.push(chunk)));
            let sequence=0;
            deviceSocket.on('connect',()=>{deviceTimer=setInterval(()=>deviceSocket.write(encodeAudioPacket({terminal,channel:1,codec:6,sequence:sequence++,timestamp:sequence*20,payload:Buffer.alloc(frameSize,0xd5)})),20);});
            deviceSocket.on('close',()=>clearInterval(deviceTimer));
            },firstDelay);return respond(frameSize===80?503:200,{acknowledged:true});
        }
        if(req.url==='/audio/stop'){stopCount++;deviceSocket?.destroy();return respond(200,{acknowledged:true});}
        respond(404,{});
    });
    backend.listen(0,'127.0.0.1');await once(backend,'listening');
    const base=`http://127.0.0.1:${backend.address().port}`;
    Object.assign(process.env,{LISTENER_API_TOKEN:secret,LARAVEL_LISTENER_URL:base,GPS_API_URL:base,VIDEO_API_URL:base,AUDIO_PORT:'0',AUDIO_API_PORT:'0',LISTENER_BIND:'127.0.0.1',AUDIO_ALLOWED_ORIGINS:'https://example.test'});
    service=startAudio({transcoder:({encode,gain,onData})=>{if(encode)assert.equal(gain,frameSize===80?4:2);return {write:data=>onData(encode?Buffer.from(data.subarray(0,640)):Buffer.alloc(640)),close(){}};}});
    await once(service.server,'listening');const clients=[];
    t.after(()=>{clearTimeout(initialTimer);clearInterval(deviceTimer);for(const ws of clients)ws.terminate();deviceSocket?.destroy();service.close();backend.closeAllConnections();backend.close();});
    const api=async(route,data={})=>fetch(`http://127.0.0.1:${service.api.address().port}${route}`,{method:'POST',headers:{Authorization:`Bearer ${secret}`,'Content-Type':'application/json'},body:JSON.stringify(data)});
    const open=async(mode='listen')=>{const res=await api('/sessions',{device_id:1,grant,mode});assert.equal(res.status,201);return res.json();};
    const connect=(lease,token=lease.token,origin='https://example.test')=>{
        const ws=new WebSocket(`ws://127.0.0.1:${service.api.address().port}${lease.socket_path}`,['exadcam-audio',`token.${token}`],{origin});clients.push(ws);ws.on('error',()=>{});return ws;
    };
    const denied=await api('/sessions',{device_id:1,mode:'listen',grant:'b'.repeat(64)});assert.equal(denied.status,403);
    const first=await open();const bad=connect(first,'0'.repeat(64));await once(bad,'error');
    const badOrigin=connect(first,first.token,'https://evil.test');await once(badOrigin,'error');
    const ws=connect(first);let audioBytes=0,ready=false;
    ws.on('message',(data,binary)=>{if(binary)audioBytes+=data.length;else if(JSON.parse(data).state==='ready')ready=true;});
    await once(ws,'open');
    const deadline=Date.now()+3000;while(!ready&&Date.now()<deadline)await delay(20);
    assert.ok(ready);assert.ok(audioBytes>0);
    const busy=await api('/sessions',{device_id:1,mode:'talk',grant});assert.equal(busy.status,409);
    const reused=connect(first);await once(reused,'error');
    ws.send(Buffer.alloc(1280));await once(ws,'close'); // listening can never transmit.
    await delay(30);assert.equal(transmitted.length,0);
    const second=await open('talk'),talk=connect(second);let talkReady=false,talkBytes=0,sending=false;
    talk.on('message',(data,binary)=>{if(binary)talkBytes+=data.length;else{const state=JSON.parse(data).state;if(state==='ready')talkReady=true;if(state==='transmitting')sending=true;}});await once(talk,'open');
    const talkDeadline=Date.now()+3000+firstDelay;while(!talkReady&&Date.now()<talkDeadline)await delay(20);
    assert.ok(talkReady);assert.equal(sending,false);talk.send(Buffer.alloc(1280,0x34));await delay(100);
    assert.equal(sending,true);assert.equal(talkBytes,0,'Talk does not return camera audio to the browser');
    assert.ok(transmitted.length>0);assert.equal(transmitted[0].payloadType,6);assert.equal(transmitted[0].terminal,terminal);
    assert.equal(transmitted.length,640/frameSize);
    for(let i=0;i<transmitted.length;i++){
        assert.equal(transmitted[i].payload.length,frameSize);
        assert.deepEqual(transmitted[i].payload,Buffer.alloc(frameSize,0x34),'Camera receives browser microphone data, never its own microphone');
        assert.equal(transmitted[i].timestamp,BigInt(i*frameSize/8));
        assert.equal(transmitted[i].sequence,i);
    }
    allowed=false;await Promise.race([once(talk,'close'),delay(5000).then(()=>{throw Error('Revocation timeout');})]);
    assert.ok(stopCount>=2);
});

test('invalid G711 capability lengths use a bounded 20 ms fallback',()=>{
    for(const frame_length of [undefined,0,-1,8192,1.5,'80']) assert.equal(g711FrameSize({frame_length},8000),160);
    assert.equal(g711FrameSize({frame_length:80},8000),80);
});
