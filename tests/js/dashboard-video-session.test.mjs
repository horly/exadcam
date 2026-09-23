import test from 'node:test';
import assert from 'node:assert/strict';
import {DashboardVideoSelection} from '../../public/js/dashboard-video-session.mjs';

const deferred = () => { let resolve; const promise = new Promise(r=>resolve=r); return {promise,resolve}; };
function setup(stop = async()=>{}) {
    const calls=[];
    const channels=[1,2].map(channel=>({channel,session:{
        stop: async keepalive=>{calls.push(['stop',channel,keepalive]);await stop(channel);},
        start: async(base,ch)=>{calls.push(['start',base,ch]);},
    }}));
    return {selection:new DashboardVideoSelection(channels,'/dashcams'),calls};
}
test('selecting a vehicle opens both configured channels and clears old leases first',async()=>{
    const {selection,calls}=setup();await selection.choose({device_id:42,channels:2});
    assert.deepEqual(calls,[['stop',1,false],['stop',2,false],['start','/dashcams/42/live',1],['start','/dashcams/42/live',2]]);
});
test('a one-channel device never opens an unsupported second channel',async()=>{
    const {selection,calls}=setup();await selection.choose({device_id:42,channels:1});
    assert.deepEqual(calls.filter(c=>c[0]==='start'),[['start','/dashcams/42/live',1]]);
});
test('rapid selections cannot open streams belonging to a superseded vehicle',async()=>{
    const pending=deferred();const {selection,calls}=setup(()=>pending.promise);
    const first=selection.choose({device_id:41,channels:2});const last=selection.choose({device_id:42,channels:2});
    pending.resolve();await Promise.all([first,last]);
    assert.deepEqual(calls.filter(c=>c[0]==='start'),[['start','/dashcams/42/live',1],['start','/dashcams/42/live',2]]);
});
test('leaving the dashboard while a vehicle switch is pending prevents a late start',async()=>{
    const pending=deferred();const {selection,calls}=setup(()=>pending.promise);
    const opening=selection.choose({device_id:42,channels:2});const closing=selection.clear(true);
    pending.resolve();await Promise.all([opening,closing]);
    assert.deepEqual(calls.filter(c=>c[0]==='start'),[]);assert.equal(selection.device,null);
    assert.deepEqual(calls.slice(-2),[['stop',1,true],['stop',2,true]]);
});
