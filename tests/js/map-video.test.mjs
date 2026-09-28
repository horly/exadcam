import test from 'node:test';
import assert from 'node:assert/strict';
import {MapVideoChannel} from '../../public/js/map-video.mjs';
const deferred = () => { let resolve; const promise = new Promise(r => {resolve=r;}); return {promise,resolve}; };
function channel(request) {
    const state = {attached:[],events:[],resets:0};
    const session = new MapVideoChannel({request,attach:url => state.attached.push(url),reset:() => state.resets++,notify:status => state.events.push(status),schedule:() => 1,cancel:() => {}});
    return {session,state};
}
test('closing while start is pending releases the late lease without playback',async () => {
    const pending=deferred(),entered=deferred(),calls=[];
    const {session,state}=channel(async (url) => { calls.push(url); if(url==='/live'){entered.resolve();return pending.promise;}return {}; });
    const started=session.start('/live',1); await entered.promise;
    await session.stop(); pending.resolve({lease_id:'late',url:'/stream.m3u8'}); await started;
    assert.deepEqual(calls,['/live','/live/late/stop']); assert.deepEqual(state.attached,[]);
});
test('channel one and two retain separate leases and closing one preserves the other',async () => {
    const calls=[];
    const request=async (url,data) => {calls.push(url);return url==='/live'?{lease_id:'ch'+data.channel,url:'/stream'+data.channel}:url.endsWith('keepalive')?{status:'ready'}:{};};
    const one=channel(request),two=channel(request);
    await Promise.all([one.session.start('/live',1),two.session.start('/live',2)]);
    await one.session.stop();
    assert.equal(two.session.lease.lease_id,'ch2'); assert.deepEqual(two.state.attached,['/stream2']);
    assert.equal(calls.includes('/live/ch2/stop'),false); await two.session.stop();
});
test('failed heartbeat releases only its owned lease and schedules automatic recovery',async () => {
    const calls=[];
    const {session,state}=channel(async url => {calls.push(url);if(url.endsWith('keepalive'))throw Error('offline');return {lease_id:'failed',url:'/stream'};});
    await session.start('/live',1);
    assert.equal(session.lease,null); assert.ok(state.events.includes('reconnecting')); assert.ok(calls.includes('/live/failed/stop')); assert.equal(session.active,true);
});

function harness(handler) {
    let next=0;
    const jobs=new Map(), events=[],attached=[],resets=[],calls=[];
    const session=new MapVideoChannel({
        request:async(url,data)=>{calls.push(url);return handler(url,data);},
        attach:(url,fail,options)=>attached.push({url,fail,options}),reset:options=>resets.push(options),notify:s=>events.push(s),
        schedule:(fn,ms)=>{jobs.set(++next,{fn,ms});return next;},cancel:id=>jobs.delete(id),
    });
    return {session,jobs,events,attached,resets,calls,run:async()=>{
        const [id,job]=jobs.entries().next().value; jobs.delete(id); await job.fn();
    }};
}
const statusError=status=>Object.assign(Error('HTTP failure'),{status});

test('the server profile selects the startup reserve without shortening legacy streams',async()=>{
    for(const [value,expected] of [[4,4],[8,8],[undefined,8],[1,8]]){
        const h=harness(async url=>url==='/live'?{lease_id:'one',url:'/media',startup_buffer_seconds:value}:url.endsWith('keepalive')?{status:'ready'}:{});
        await h.session.start('/live',1);assert.deepEqual(h.attached[0].options,{startBufferSeconds:expected});await h.session.stop();
    }
});

test('startup checks each second, attaches once, then renews every five seconds',async()=>{
    let ready=false;
    const h=harness(async url=>url==='/live'?{lease_id:'one',url:'/media/one'}:url.endsWith('keepalive')?{status:ready?'ready':'waiting'}:{});
    await h.session.start('/live',1);
    assert.equal(h.attached.length,0);assert.equal([...h.jobs.values()][0].ms,1000);
    await h.run();assert.equal(h.attached.length,0);assert.equal([...h.jobs.values()][0].ms,1000);
    ready=true;await h.run();
    assert.equal(h.attached.length,1);assert.equal([...h.jobs.values()][0].ms,5000);
    await h.run();assert.equal(h.attached.length,1);assert.equal(h.calls.filter(x=>x==='/live').length,1);
    await h.session.stop();assert.equal(h.jobs.size,0);
});

test('an expired media session is replaced and attached again without another click',async()=>{
    let count=0,broken=false;
    const h=harness(async url=>{
        if(url==='/live')return {lease_id:String(++count),url:'/media/'+count};
        if(url.endsWith('/keepalive')){if(broken){broken=false;throw statusError(503);}return {status:'ready'};}
        return {};
    });
    await h.session.start('/live',2); broken=true; await h.run();
    assert.deepEqual(h.resets.at(-1),{preserveFrame:true});assert.equal(h.events.at(-1),'reconnecting');
    assert.equal([...h.jobs.values()][0].ms,3000);await h.run();
    assert.deepEqual(h.attached.map(x=>x.url),['/media/1','/media/2']);
    assert.equal(h.calls.filter(x=>x==='/live/1/stop').length,1); await h.session.stop();
});

test('media and heartbeat failures cannot create duplicate reconnect loops',async()=>{
    const pending=deferred();let calls=0;
    const h=harness(async url=>url==='/live'?{lease_id:'one',url:'/one'}:url.endsWith('/keepalive')?(++calls===1?{status:'ready'}:pending.promise):{});
    await h.session.start('/live',1); const poll=h.run();await Promise.resolve();
    h.attached[0].fail(Error('media'));await Promise.resolve();await Promise.resolve();
    pending.resolve(Promise.reject(statusError(503)));await poll;await Promise.resolve();
    assert.equal(h.jobs.size,1);assert.equal(h.calls.filter(x=>x==='/live/one/stop').length,1);await h.session.stop();
});

test('closing during the recovery delay cancels the next attempt',async()=>{
    const h=harness(async()=>{throw statusError(409);});await h.session.start('/live',1);
    assert.equal(h.jobs.size,1);await h.session.stop();assert.equal(h.jobs.size,0);assert.equal(h.session.active,false);
});

test('a late recovery response is released if the user closed meanwhile',async()=>{
    const pending=deferred();let count=0;
    const h=harness(async url=>url==='/live'?(++count===1?Promise.reject(statusError(409)):pending.promise):{});
    await h.session.start('/live',1);const opening=h.run();await Promise.resolve();await h.session.stop();
    pending.resolve({lease_id:'late',url:'/late'});await opening;
    assert.ok(h.calls.includes('/live/late/stop'));assert.equal(h.attached.length,0);assert.equal(h.jobs.size,0);
});

test('authentication, permission and invalid device errors do not retry',async()=>{
    for(const status of [400,401,403,404,419,422]) {
        const h=harness(async()=>{throw statusError(status);});await h.session.start('/live',1);
        assert.equal(h.jobs.size,0);assert.equal(h.session.active,false);assert.ok(h.events.includes('failed'));
    }
});

test('revoked permission on heartbeat stops without opening a replacement',async()=>{
    const h=harness(async url=>{if(url==='/live')return {lease_id:'owned',url:'/media'};if(url.endsWith('keepalive'))throw statusError(403);return {};});
    await h.session.start('/live',1);assert.equal(h.jobs.size,0);assert.equal(h.session.active,false);
    assert.deepEqual(h.calls,['/live','/live/owned/keepalive','/live/owned/stop']);
});

test('repeated offline errors back off to thirty seconds and reset after playback',async()=>{
    const h=harness(async()=>{throw statusError(409);});await h.session.start('/live',1);
    const delays=[];for(let i=0;i<6;i++){delays.push([...h.jobs.values()][0].ms);await h.run();}
    assert.deepEqual(delays,[3000,5000,10000,20000,30000,30000]);
    h.session.markPlaying();await h.run();assert.equal([...h.jobs.values()][0].ms,3000);await h.session.stop();
});

test('switching channels ignores stale recovery callbacks and releases the old lease',async()=>{
    const h=harness(async(url,data)=>url==='/live'?{lease_id:String(data.channel),url:'/media/'+data.channel}:url.endsWith('keepalive')?{status:'ready'}:{});
    await h.session.start('/live',1);const fail=h.attached[0].fail;
    await h.session.start('/live',2);fail(Error('stale'));await Promise.resolve();
    assert.equal(h.session.lease.lease_id,'2');assert.equal(h.jobs.size,1);
    assert.equal(h.calls.includes('/live/2/stop'),false);await h.session.stop();
});

test('foreground renewal retains an active lease and cannot duplicate an in-flight heartbeat',async()=>{
    const pending=deferred();let keepalives=0;
    const h=harness(async url=>url==='/live'?{lease_id:'one',url:'/one'}:url.endsWith('keepalive')?(++keepalives===1?{status:'ready'}:pending.promise):{});
    await h.session.start('/live',1);const waking=h.session.resume();await h.session.resume();
    assert.equal(keepalives,2);assert.equal(h.jobs.size,0);
    pending.resolve({status:'ready'});await waking;
    assert.equal(h.attached.length,1);assert.equal(h.jobs.size,1);
    assert.equal(h.calls.filter(x=>x.endsWith('/stop')).length,0);await h.session.stop();
});
test('returning after the browser suspended lease renewal reconnects without another click',async()=>{
    let count=0,expired=false;
    const h=harness(async url=>{
        if(url==='/live')return {lease_id:String(++count),url:'/media/'+count};
        if(url.endsWith('/keepalive')){if(expired){expired=false;throw statusError(404);}return {status:'ready'};}
        return {};
    });
    await h.session.start('/live',2);expired=true;await h.session.resume();await h.run();
    assert.equal(h.session.active,true);assert.equal(h.session.lease.lease_id,'2');assert.equal(h.attached.length,2);
    await h.session.stop();await h.session.resume();assert.equal(count,2);
});
