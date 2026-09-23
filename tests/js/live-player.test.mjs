import test from 'node:test';
import assert from 'node:assert/strict';
import {attachLivePlayer, bufferedAhead} from '../../public/js/live-player.mjs';

class Video extends EventTarget {
    currentTime = 0; readyState = 4; paused = true; plays = 0; ranges = []; seekingRanges = [];
    get buffered() { return {length:this.ranges.length,start:i=>this.ranges[i][0],end:i=>this.ranges[i][1]}; }
    get seekable() { return {length:this.seekingRanges.length,start:i=>this.seekingRanges[i][0],end:i=>this.seekingRanges[i][1]}; }
    canPlayType() {return 'probably';}
    load() {}
    play() {this.paused=false;this.plays++;this.dispatchEvent(new Event('play'));if(!this.paused)this.dispatchEvent(new Event('playing'));return Promise.resolve();}
    pause() {this.paused=true;}
}
class FakeHls {
    static isSupported() {return true;}
    static Events = {FRAG_BUFFERED:'buffer',ERROR:'error'};
    constructor(config) {this.config=config;this.events={};FakeHls.last=this;}
    on(event,fn) {this.events[event]=fn;}
    loadSource() {}
    attachMedia() {}
    destroy() {this.destroyed=true;}
}
function setup({native=false,shouldPlay=()=>true}={}) {
    const player=new Video(), states=[],errors=[];let tick,clock=0,cancelled=false;
    const playback=attachLivePlayer(player,'/test.m3u8',{Hls:native?null:FakeHls,shouldPlay,onState:s=>states.push(s),onError:e=>errors.push(e),schedule:fn=>(tick=fn,1),cancel:()=>{cancelled=true;},now:()=>clock});
    return {player,states,errors,playback,tick:()=>tick(),advance:ms=>{clock+=ms;tick();},cancelled:()=>cancelled};
}
test('startup waits for fifteen seconds of contiguous playable video',()=>{
    const s=setup();s.player.ranges=[[0,4]];s.tick();assert.equal(s.player.plays,0);
    s.player.ranges=[[0,8],[10,30]];s.tick();assert.equal(s.player.plays,0);assert.equal(bufferedAhead(s.player),8);
    s.player.ranges=[[0,16]];s.tick();assert.equal(s.player.plays,1);assert.deepEqual(s.states,['buffering','preview','ready']);s.playback.destroy();
});
test('the first real frame is revealed while the reserve fills, without starting playback',()=>{
    const s=setup();assert.equal(FakeHls.last.config.initialLiveManifestSize,1);
    s.player.ranges=[[0,2]];s.player.dispatchEvent(new Event('loadeddata'));
    assert.equal(s.player.plays,0);assert.equal(s.player.controls,false);assert.deepEqual(s.states,['buffering','preview']);
    s.tick();assert.equal(s.states.filter(x=>x==='preview').length,1);s.playback.destroy();
});
test('an initial media timeline that starts after zero can build its reserve and play',()=>{
    const s=setup();s.player.currentTime=0;s.player.ranges=[[1.4,18]];s.tick();
    assert.equal(s.player.currentTime,1.4);assert.equal(s.player.plays,1);s.playback.destroy();
});
test('a drained buffer refills once instead of repeatedly restarting with a short segment',()=>{
    const s=setup();s.player.ranges=[[0,18]];s.tick();s.player.currentTime=17.8;s.player.dispatchEvent(new Event('waiting'));
    assert.equal(s.player.paused,true);assert.equal(s.states.at(-1),'buffering');
    s.player.ranges=[[0,22]];s.tick();assert.equal(s.player.plays,1);
    s.player.ranges=[[0,34]];s.tick();assert.equal(s.player.plays,2);assert.equal(s.states.at(-1),'ready');s.playback.destroy();
});
test('manual pause is preserved when more fragments arrive',()=>{
    const s=setup();s.player.ranges=[[0,18]];s.tick();s.player.pause();s.player.ranges=[[0,30]];
    s.player.dispatchEvent(new Event('waiting'));s.tick();assert.equal(s.player.plays,1);assert.equal(s.player.paused,true);s.playback.destroy();
});
test('destroy detaches late events and prevents playback after closing',()=>{
    const s=setup();const hls=FakeHls.last;s.playback.destroy();s.player.ranges=[[0,25]];s.tick();s.player.dispatchEvent(new Event('progress'));hls.events.buffer();
    assert.equal(s.player.plays,0);assert.equal(hls.destroyed,true);assert.equal(s.cancelled(),true);
});
test('fatal errors end only this player; nonfatal retries remain with HLS',()=>{
    const one=setup(), first=FakeHls.last, two=setup(),second=FakeHls.last;
    first.events.error(null,{fatal:false});assert.equal(one.errors.length,0);
    first.events.error(null,{fatal:true,details:'network'});assert.equal(one.errors.length,1);assert.equal(first.destroyed,true);assert.equal(second.destroyed,undefined);
    two.player.ranges=[[0,20]];two.tick();assert.equal(two.player.plays,1);two.playback.destroy();
});
test('native HLS starts behind the edge and waits for a playable reserve',()=>{
    const s=setup({native:true});s.player.seekingRanges=[[100,140]];s.player.ranges=[[135,140]];s.tick();
    assert.equal(s.player.currentTime,122);assert.equal(s.player.plays,0);
    s.player.ranges=[[122,140]];s.tick();assert.equal(s.player.plays,1);s.playback.destroy();
});
test('a source that never fills the buffer fails after a bounded wait',()=>{
    const s=setup();s.player.ranges=[[0,4]];s.advance(91000);assert.equal(s.errors.length,1);assert.equal(s.cancelled(),true);
});
test('blocked autoplay shows a manual play action without destroying the stream',async()=>{
    const s=setup();s.player.play=()=>Promise.reject(Object.assign(new Error('gesture'),{name:'NotAllowedError'}));s.player.ranges=[[0,18]];s.tick();
    await Promise.resolve();assert.equal(s.states.at(-1),'play_required');assert.equal(s.errors.length,0);s.playback.destroy();
});
test('a manually paused channel remains paused after a replacement player is ready',()=>{
    const s=setup({shouldPlay:()=>false});s.player.ranges=[[0,18]];s.tick();
    assert.equal(s.player.plays,0);assert.equal(s.player.controls,true);assert.equal(s.states.at(-1),'paused');
    s.player.play();assert.equal(s.states.at(-1),'ready');s.playback.destroy();
});
test('unexpected end of a live stream reports a recoverable failure',()=>{
    const s=setup();s.player.ranges=[[0,18]];s.tick();s.player.dispatchEvent(new Event('ended'));
    assert.equal(s.errors.length,1);assert.equal(s.cancelled(),true);
});
test('the recovery callback can capture the frame before HLS detaches it',()=>{
    const player=new Video();let captured=false;
    const playback=attachLivePlayer(player,'/live',{Hls:FakeHls,schedule:()=>1,cancel:()=>{},onError:()=>{captured=!FakeHls.last.destroyed;}});
    FakeHls.last.events.error(null,{fatal:true});assert.equal(captured,true);assert.equal(FakeHls.last.destroyed,true);playback.destroy();
});
