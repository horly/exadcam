import test from 'node:test';
import assert from 'node:assert/strict';
import {createMapVideoPlayer} from '../../public/js/map-video-player.mjs';

const flush = () => new Promise(resolve => setImmediate(resolve));
const deferred = () => {let resolve; const promise=new Promise(r=>resolve=r); return {promise,resolve};};
class Element extends EventTarget {
    constructor() {super(); this.hidden=false; this.disabled=false; this.dataset={}; this.textContent=''; this.attrs={};}
    setAttribute(key,value) {this.attrs[key]=value;}
    click() {this.dispatchEvent(new Event('click'));}
}
const labels=Object.fromEntries(['idle','waiting','buffering','reconnecting','ready','paused','play_required','failed','missing'].map(state=>['video_'+state,state]));
Object.assign(labels,{play:'Lecture',video_retry:'Réessayer',video_start:'Démarrer'});
function harness({channel=1,channels=2,canVideo=true,request:handler}={}) {
    const nodes=new Map(),jobs=new Map(),calls=[],attached=[]; let counter=0, resumes=0;
    const node=selector=>{if(!nodes.has(selector))nodes.set(selector,new Element());return nodes.get(selector);};
    const section=new Element(); section.dataset.mapChannel=String(channel); section.querySelector=node;
    const document={hidden:false};
    const controller=createMapVideoPlayer({section,labels,document,canVideo,baseUrl:'/camera',getVehicle:()=>({equipment:{id:7,channels}}),
        request:async(url,data)=>{calls.push({url,data});return handler ? handler(url,data) : url.endsWith('/live')?{lease_id:'lease-'+channel,url:'/media-'+channel}:url.endsWith('/keepalive')?{status:'ready'}:{};},
        reset:(_player,playback)=>playback?.destroy(),
        attach:(_player,url,options)=>{const attachment={url,options,destroyed:false};attached.push(attachment);options.onState('buffering');return {destroy(){attachment.destroyed=true;},resume(){resumes++;if(options.shouldPlay())options.onState('ready');}};},
        sessionOptions:{schedule:(fn,delay)=>{jobs.set(++counter,{fn,delay});return counter;},cancel:id=>jobs.delete(id)},
    });
    return {controller,section,node,calls,jobs,attached,document,resumes:()=>resumes,
        state:()=>section.dataset.playbackState, event:state=>attached.at(-1).options.onState(state),
        run:async()=>{const [id,job]=jobs.entries().next().value;jobs.delete(id);await job.fn();},
    };
}

test('opening a configured channel shows loading immediately and hides Play until the user stops',async()=>{
    const pending=deferred();const h=harness({request:async url=>url.endsWith('/live')?pending.promise:{status:'ready'}});
    h.controller.prepare();assert.equal(h.state(),'waiting');assert.equal(h.calls.length,0);
    assert.equal(h.node('[data-video-loading]').hidden,false);assert.equal(h.node('[data-video-start]').hidden,true);
    const started=h.controller.startPrepared();await flush();assert.equal(h.calls.length,1);
    pending.resolve({lease_id:'one',url:'/media'});await started;
    assert.equal(h.state(),'buffering');h.event('preview');assert.equal(h.node('[data-video-loading]').hidden,false);
    h.event('ready');assert.equal(h.node('[data-video-loading]').hidden,true);assert.equal(h.node('.tracking-video-screen').attrs['aria-busy'],'false');
    assert.equal(h.node('[data-video-start]').hidden,true);assert.equal(h.node('[data-video-stop]').hidden,false);
    await h.controller.stop();assert.equal(h.state(),'idle');assert.equal(h.node('[data-video-start]').hidden,false);
    assert.equal(h.node('[data-video-start-label]').textContent,'Lecture');assert.equal(h.jobs.size,0);
});
test('both configured channels start independently and stopping one leaves the other running',async()=>{
    const one=harness(),two=harness({channel:2});
    one.controller.prepare();two.controller.prepare();await Promise.all([one.controller.startPrepared(),two.controller.startPrepared()]);
    assert.equal(one.calls[0].data.channel,1);assert.equal(two.calls[0].data.channel,2);
    await one.controller.stop();assert.equal(two.calls.some(call=>call.url.endsWith('/stop')),false);await two.controller.stop();
});
test('missing channels and unauthorized viewers never issue a start request',async()=>{
    for(const options of [{channel:2,channels:1},{canVideo:false}]){
        const h=harness(options);h.controller.prepare();await h.controller.startPrepared();await h.controller.start();
        assert.equal(h.calls.length,0);assert.equal(h.node('[data-video-loading]').hidden,true);assert.equal(h.node('[data-video-start]').hidden,true);
    }
});
test('stop during preparation prevents the deferred automatic start',async()=>{
    const h=harness();h.controller.prepare();await h.controller.stop();await h.controller.startPrepared();assert.equal(h.calls.length,0);
    assert.equal(h.state(),'idle');
});
test('closing a pending opening releases the late lease and never attaches a stream',async()=>{
    const pending=deferred();const h=harness({request:url=>url.endsWith('/live')?pending.promise:{}});
    const start=h.controller.start();await flush();await h.controller.stop();pending.resolve({lease_id:'late',url:'/old'});await start;
    assert.equal(h.attached.length,0);assert.ok(h.calls.some(call=>call.url.endsWith('/late/stop')));assert.equal(h.state(),'idle');
});
test('manual pause and browser autoplay refusal resume the same lease through the central button',async()=>{
    for(const state of ['paused','play_required']){
        const h=harness();await h.controller.start();h.event('ready');h.event(state);
        assert.equal(h.node('[data-video-start]').hidden,false);assert.equal(h.node('[data-video-loading]').hidden,true);
        assert.equal(h.node('[data-video-start-label]').textContent,state==='paused'?'Lecture':'Démarrer');
        h.node('[data-video-start]').click();await flush();assert.equal(h.state(),'ready');assert.equal(h.resumes(),1);
        assert.equal(h.calls.filter(call=>call.url.endsWith('/live')).length,1);await h.controller.stop();
    }
});
test('rebuffering and reconnection show a loader and never a Play button or false live state',async()=>{
    const h=harness();await h.controller.start();h.event('ready');h.event('buffering');
    assert.equal(h.node('[data-video-loading]').hidden,false);assert.equal(h.node('[data-video-start]').hidden,true);
    h.attached[0].options.onError(Error('disconnected'));await flush();assert.equal(h.state(),'reconnecting');
    assert.equal(h.node('[data-video-start]').hidden,true);await h.run();assert.equal(h.attached.length,2);assert.equal(h.state(),'buffering');
    h.event('ready');assert.equal(h.node('[data-video-loading]').hidden,true);await h.controller.stop();
});
test('an authorization failure keeps a useful retry state after session cleanup',async()=>{
    const h=harness({request:async()=>{throw Object.assign(Error('denied'),{status:403});}});await h.controller.start();
    assert.equal(h.state(),'failed');assert.equal(h.node('[data-video-loading]').hidden,true);assert.equal(h.node('[data-video-start-label]').textContent,'Réessayer');
    assert.equal(h.node('[data-video-stop]').hidden,true);assert.equal(h.jobs.size,0);
});
test('late playback events cannot revive a stopped channel or hide its restart action',async()=>{
    const h=harness();await h.controller.start();const old=h.attached[0];await h.controller.stop();old.options.onState('ready');old.options.onError(Error('late'));
    assert.equal(h.state(),'idle');assert.equal(h.jobs.size,0);assert.equal(h.node('[data-video-start]').hidden,false);
});
test('double start does not duplicate a lease and a background pause is not treated as a manual pause',async()=>{
    const h=harness();await Promise.all([h.controller.start(),h.controller.start()]);assert.equal(h.calls.filter(call=>call.url.endsWith('/live')).length,1);
    h.event('ready');h.document.hidden=true;h.event('paused');h.document.hidden=false;h.controller.resume();await flush();
    assert.equal(h.state(),'ready');assert.equal(h.attached[0].options.shouldPlay(),true);await h.controller.stop();
});
