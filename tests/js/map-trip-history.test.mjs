import test from 'node:test';
import assert from 'node:assert/strict';
import {coordinate,duration,playbackAdvance,createTripHistory} from '../../public/js/map-trip-history.mjs';

class Node {
    constructor(tag){this.tag=tag;this.children=[];this.events={};this.style={setProperty(){}};this.classList={toggle(){}};this.value='';this.clientWidth=1000;this.clientHeight=650;}
    append(...items){this.children.push(...items);}
    replaceChildren(...items){this.children=items;}
    setAttribute(){}
    addEventListener(name,fn){this.events[name]=fn;}
    reportValidity(){return true;}
    all(){return [this,...this.children.flatMap(n=>n?.all?.()||[])];}
}
function setup(fetcher){
    const pins=[],lines=[];
    globalThis.document={createElement:tag=>new Node(tag)};
    globalThis.window={addEventListener(){}};
    globalThis.fetch=fetcher;
    globalThis.google={maps:{LatLngBounds:class{constructor(){this.points=[];}extend(p){this.points.push(p);}isEmpty(){return !this.points.length;}},Polyline:class{constructor(options){this.options=options;lines.push(this);}setMap(map){this.options.map=map;}addListener(){}},SymbolPath:{FORWARD_OPEN_ARROW:'arrow'},event:{addListenerOnce(){}}}};
    const host=new Node('host'),map={fitBounds(){},getZoom(){return 15;}},Marker=class{constructor(options){Object.assign(this,options);pins.push(this);}append(el){this.el=el;}};
    const labels=new Proxy({}, {get:(_,key)=>key});
    return {host,pins,lines,console:createTripHistory({host,getMap:()=>map,getMarker:()=>Marker,labels})};
}
const vehicle={name:'Test vehicle',source_id:7,trips_url:'/map/vehicles/1/trips'};
const trip={id:'t1',type:'trip',index:1,start_coordinates:[15.2,-4.3],end_coordinates:[15.3,-4.3],coordinates:[[15.2,-4.3],[15.3,-4.3]],color:'#795548',date:'05.10.2026',end_date:'05.10.2026',start_time:'08:00',end_time:'08:10',start_address:'Start',end_address:'End',duration_seconds:600,distance_km:1};
const parking={id:'p1',type:'parking',coordinates:[15.3,-4.3],date:'05.10.2026',end_date:'05.10.2026',start_time:'08:10',end_time:'08:15',address:'End',duration_seconds:300};
const payload={trips:[trip],summary:{count:1,distance_km:1},history:{items:[trip,parking],first:trip,last:parking,parking_count:1,elapsed_seconds:900}};
const tick=()=>new Promise(resolve=>setImmediate(resolve));

test('coordinates reject invalid samples and preserve longitude/latitude order',()=>{assert.deepEqual(coordinate([15,-4]),{lng:15,lat:-4});assert.equal(coordinate([Infinity,2]),null);assert.equal(coordinate([1,91]),null);assert.equal(duration(3661),'1 h 1 min 1 s');});
test('overview draws exact start and finish then parking clears route and adds a P pin',async()=>{
    const state=setup(async()=>({ok:true,json:async()=>payload}));state.console.open(vehicle);await tick();
    assert.equal(state.lines.length,1);assert.deepEqual(state.pins[0].position,{lng:15.2,lat:-4.3});assert.equal(state.pins[1].el.textContent,'🏁');
    state.host.all().find(n=>n.className==='cam-trip-overview').events.click();
    const rows=state.host.all().filter(n=>n.className==='cam-trip-row');assert.equal(rows.length,2);
    rows[1].children.find(n=>n.tag==='button').events.click();
    assert.equal(state.lines[0].options.map,null);assert.equal(state.pins.at(-1).el.textContent,'P');
    state.console.close();assert.equal(state.pins.at(-1).map,null);assert.equal(state.console.active,false);
});
test('a response that arrives after close cannot restore the route',async()=>{
    let resolve;const state=setup(()=>new Promise(r=>resolve=r));state.console.open(vehicle);state.console.close();
    resolve({ok:true,json:async()=>payload});await tick();assert.equal(state.lines.length,0);assert.equal(state.pins.length,0);
});

test('playback speed respects trip duration, fractional progress and completion',()=>{
    assert.equal(playbackAdvance(0,1000,600,1),1000/600);
    assert.equal(playbackAdvance(0,1000,600,16),16000/600);
    assert.ok(playbackAdvance(0,100,86400,1)>0);
    assert.equal(playbackAdvance(999,1000,600,64),1000);
});
test('speed control offers all rates and is disabled on a parking selection',async()=>{
    const state=setup(async()=>({ok:true,json:async()=>payload}));state.console.open(vehicle);await tick();
    const speed=state.host.all().find(n=>n.tag==='select');assert.equal(speed.value,'1');assert.equal(speed.disabled,false);
    assert.deepEqual(speed.children.map(n=>n.value),['1','2','4','8','16','32','64']);
    assert.equal(state.host.all().find(n=>n.type==='range').step,'any');
    state.host.all().find(n=>n.className==='cam-trip-overview').events.click();
    state.host.all().filter(n=>n.className==='cam-trip-row')[1].children.find(n=>n.tag==='button').events.click();
    assert.equal(speed.disabled,true);state.console.close();
});
