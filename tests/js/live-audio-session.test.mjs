import test from 'node:test';
import assert from 'node:assert/strict';
import {LiveAudioSession} from '../../public/js/live-audio-session.mjs';

const deferred=()=>{let resolve,reject;const promise=new Promise((a,b)=>{resolve=a;reject=b;});return {promise,resolve,reject};};
const tick=()=>new Promise(resolve=>setImmediate(resolve));
test('default browser timers are called without binding them to the session',async()=>{
    const originalSet=globalThis.setTimeout,originalClear=globalThis.clearTimeout;
    const pending=new Map();let id=0;
    globalThis.setTimeout=function(fn){assert.equal(this,undefined);pending.set(++id,fn);return id;};
    globalThis.clearTimeout=function(key){assert.equal(this,undefined);pending.delete(key);};
    try{
        const f=setup({schedule:undefined,cancel:undefined});
        await f.session.start('/dashcams/1/audio','listen');
        assert.equal(pending.size,2);await f.session.stop();assert.equal(pending.size,0);
    }finally{globalThis.setTimeout=originalSet;globalThis.clearTimeout=originalClear;}
});
function setup(extra={}){
    const requests=[],states=[],tracks=[{stopped:0,stop(){this.stopped++;},addEventListener(name,handler){this.ended=handler;}}];
    const graph={closed:0,received:0,close(){this.closed++;},play(){this.received++;},capture(stream,send){this.stream=stream;this.send=send;},volume(){}};
    const socket={readyState:1,bufferedAmount:0,sent:[],close(){this.closed=true;},send(data){this.sent.push(data);}};
    const timers=new Map();let timer=0;
    const session=new LiveAudioSession({request:async(url,data)=>{requests.push({url,data});return url.endsWith('/audio')?{lease_id:'lease',socket_path:'/audio-live/lease',token:'test'}:{};},
        createGraph:async()=>graph,microphone:async()=>({getTracks:()=>tracks}),openSocket:()=>socket,notify:(state,error)=>states.push([state,error]),
        schedule:fn=>{timers.set(++timer,fn);return timer;},cancel:id=>timers.delete(id),...extra});
    return {session,requests,states,tracks,graph,socket,timers};
}
test('listening opens no microphone and closes the owned lease with its graph',async()=>{
    const f=setup({microphone:()=>{throw Error('Microphone must not be requested');}});
    await f.session.start('/dashcams/1/audio','listen');
    f.socket.onmessage({data:JSON.stringify({state:'ready'})});
    f.socket.onmessage({data:new ArrayBuffer(640)});
    assert.equal(f.graph.received,1);assert.equal(f.states.at(-1)[0],'listening');
    await f.session.stop();assert.equal(f.graph.closed,1);assert.equal(f.socket.closed,true);assert.equal(f.timers.size,0);
    assert.equal(f.requests.at(-1).url,'/dashcams/1/audio/lease/stop');
});
test('talk sends PCM only after the device is ready and stops all microphone tracks',async()=>{
    const f=setup();await f.session.start('/dashcams/1/audio','talk');assert.equal(f.graph.send,undefined);
    f.socket.onmessage({data:JSON.stringify({state:'ready'})});const sample=new ArrayBuffer(1280);f.graph.send(sample);
    assert.deepEqual(f.socket.sent,[sample]);assert.equal(f.states.at(-1)[0],'microphone');
    f.socket.onmessage({data:new ArrayBuffer(640)});assert.equal(f.graph.received,0,'Talk must never play camera audio');
    f.socket.onmessage({data:JSON.stringify({state:'transmitting'})});assert.equal(f.states.at(-1)[0],'talking');
    await f.session.stop();assert.equal(f.tracks[0].stopped,1);f.graph.send(sample);assert.equal(f.socket.sent.length,1);
});
test('closing during browser permission stops the late microphone without opening a lease',async()=>{
    const permission=deferred(),f=setup({microphone:()=>permission.promise});
    const starting=f.session.start('/dashcams/1/audio','talk');await tick();await f.session.stop();
    permission.resolve({getTracks:()=>f.tracks});await starting;
    assert.equal(f.tracks[0].stopped,1);assert.equal(f.requests.length,0);assert.equal(f.graph.closed,1);
});
test('permission denial releases the audio graph and never contacts a vehicle',async()=>{
    const f=setup({microphone:async()=>{throw Object.assign(Error('Denied'),{name:'NotAllowedError'});}});
    await f.session.start('/dashcams/1/audio','talk');assert.equal(f.requests.length,0);assert.equal(f.graph.closed,1);
    assert.equal(f.states.at(-1)[1].name,'NotAllowedError');
});
test('a late session creation response is released after navigating away',async()=>{
    const response=deferred(),calls=[];
    const f=setup({request:async(url)=>{calls.push(url);return url.endsWith('/audio')?response.promise:{};}});
    const starting=f.session.start('/dashcams/1/audio','talk');await tick();const stopping=f.session.stop();
    response.resolve({lease_id:'late'});await Promise.all([starting,stopping]);
    assert.deepEqual(calls,['/dashcams/1/audio','/dashcams/1/audio/late/stop']);assert.equal(f.tracks[0].stopped,1);
});
test('revocation during heartbeat closes microphone and socket with no automatic microphone restart',async()=>{
    const f=setup({request:async url=>{if(url.endsWith('keepalive'))throw Object.assign(Error('Revoked'),{status:403});return {lease_id:'lease'};}});
    await f.session.start('/dashcams/1/audio','talk');f.socket.onmessage({data:JSON.stringify({state:'ready'})});
    f.socket.onmessage({data:JSON.stringify({state:'transmitting'})});
    await [...f.timers.values()][0]();await tick();
    assert.equal(f.tracks[0].stopped,1);assert.equal(f.socket.closed,true);assert.equal(f.timers.size,0);assert.equal(f.states.at(-1)[0],'failed');
});
test('unplugging a microphone or congestion stops transmission immediately',async()=>{
    for(const cause of ['unplug','congestion']){
        const f=setup();await f.session.start('/dashcams/1/audio','talk');f.socket.onmessage({data:JSON.stringify({state:'ready'})});
        if(cause==='unplug')f.tracks[0].ended();else{f.socket.bufferedAmount=20000;f.graph.send(new ArrayBuffer(1280));}
        await tick();assert.equal(f.tracks[0].stopped,1);assert.equal(f.socket.closed,true);assert.equal(f.socket.sent.length,0);
    }
});

test('listening recovers after a source cut without ever requesting a microphone',async()=>{
    const f=setup({microphone:()=>{throw Error('No microphone during listening recovery');}});
    await f.session.start('/dashcams/1/audio','listen');
    f.socket.onmessage({data:JSON.stringify({state:'ready'})});
    f.socket.onclose();assert.equal(f.states.at(-1)[0],'reconnecting');assert.equal(f.timers.size,1);
    const [id,retry]=[...f.timers.entries()][0];f.timers.delete(id);retry();await tick();await tick();
    assert.equal(f.requests.filter(r=>r.url==='/dashcams/1/audio').length,2);
    f.socket.onmessage({data:JSON.stringify({state:'ready'})});assert.equal(f.states.at(-1)[0],'listening');
    await f.session.stop();
});

test('closing cancels listening recovery and revoked listening never retries',async()=>{
    const f=setup();await f.session.start('/dashcams/1/audio','listen');f.socket.onclose();
    const retry=[...f.timers.values()][0];await f.session.stop();retry();await tick();
    assert.equal(f.requests.filter(r=>r.url==='/dashcams/1/audio').length,1);assert.equal(f.timers.size,0);
    const denied=setup({request:async()=>{throw Object.assign(Error('Revoked'),{status:403});}});
    await denied.session.start('/dashcams/1/audio','listen');assert.equal(denied.states.at(-1)[0],'failed');assert.equal(denied.timers.size,0);
});

test('a ready camera without a microphone return never reports talking',async()=>{
    const f=setup();await f.session.start('/dashcams/2/audio','talk');
    f.socket.onmessage({data:JSON.stringify({state:'transmitting'})});
    assert.equal(f.states.at(-1)[0],'connecting');
    f.socket.onmessage({data:JSON.stringify({state:'ready'})});
    assert.equal(f.states.at(-1)[0],'microphone');
    const timeout=[...f.timers.values()][0];timeout();await tick();
    assert.equal(f.states.at(-1)[0],'failed');assert.equal(f.tracks[0].stopped,1);
    assert.equal(f.states.some(([state])=>state==='talking'),false);
});
