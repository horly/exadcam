import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';
import * as view from '../../public/js/map-view.mjs';
import * as motion from '../../public/js/map-motion.mjs';
import * as dashboardFilter from '../../public/js/map-dashboard-filter.mjs';

const flush=()=>new Promise(resolve=>setImmediate(resolve));
class Element extends EventTarget {
    constructor(){super();this.children=[];this.dataset={};this.value='';this.checked=false;this.hidden=false;this.attrs={};this.textContent='';this.style={setProperty(){}};this.validity={valid:true};this.clientWidth=600;this.clientHeight=700;
        const classes=new Set();this.classList={add:(...names)=>names.forEach(n=>classes.add(n)),remove:(...names)=>names.forEach(n=>classes.delete(n)),contains:n=>classes.has(n),toggle(n,on){if(on??!classes.has(n))classes.add(n);else classes.delete(n);}};}
    append(...children){for(const child of children){child.remove?.();child.parent=this;this.children.push(child);}}
    replaceChildren(...children){this.children.forEach(c=>c.parent=null);this.children=[];this.append(...children);}
    insertBefore(child,before){child.remove?.();const index=this.children.indexOf(before);child.parent=this;this.children.splice(index<0?this.children.length:index,0,child);}
    remove(){if(this.parent){this.parent.children=this.parent.children.filter(c=>c!==this);this.parent=null;}}
    setAttribute(k,v){this.attrs[k]=String(v);} getAttribute(k){return this.attrs[k];}
    get lastElementChild(){return this.children.at(-1);}
    getBoundingClientRect(){return {left:0,top:0,right:this.right??600,bottom:700,width:600,height:700};}
    click(){this.dispatchEvent(new Event('click'));} focus(){} querySelector(){return new Element();}
}
async function page({canVideo=true,channels=2,deferMap=false}={}){
    const nodes=new Map(),players=[],popups=[],frames=[];
    const node=id=>{if(!nodes.has(id))nodes.set(id,new Element());return nodes.get(id);};
    const document=new Element();document.body=new Element();document.body.dataset.view='map';document.hidden=false;
    document.getElementById=node;document.createElement=()=>new Element();document.createElementNS=()=>new Element();document.createTextNode=text=>Object.assign(new Element(),{textContent:text});document.createDocumentFragment=()=>new Element();
    const sections=[1,2].map(n=>{const s=new Element();s.dataset.mapChannel=String(n);return s;});
    document.querySelectorAll=selector=>selector==='[data-map-channel]'?sections:[];
    document.querySelector=()=>new Element();
    node('tracking-show-all').checked=true;node('tracking-panel').right=500;
    const labels={all_fleets:'All fleets',all_departments:'All departments',none_department:'None',moving:'En déplacement',kmh:'km/h',speed:'Vitesse',online:'En ligne',offline:'Hors ligne'};
    const config={labels,allowed:true,canVideo,apiKey:'test',locale:'fr',positionsUrl:'/positions',videoUrl:'/cameras',iconsUrl:'/icons',center:{lat:-4.3,lng:15.3}};
    node('google-map-data').textContent=JSON.stringify(config);
    let vehicles=[1,2].map(id=>({id,source_id:id,name:'Vehicle '+id,registration:'TEST'+id,fleet:{id:1,name:'Fleet'},state:'moving',online:true,last_seen_at:new Date().toISOString(),trail:[],position:{lat:-4.3,lng:15.3+id/100,speed:25,at:new Date().toISOString()},equipment:{id,channels,model:'Camera'}}));
    class MapView{addListener(){}getZoom(){return 18;}setZoom(){}setCenter(){}setOptions(){}fitBounds(){}getProjection(){return {fromLatLngToPoint:p=>({x:p.lng,y:p.lat}),fromPointToLatLng:p=>p};}}
    class Marker extends Element{constructor(options){super();Object.assign(this,options);}}
    class Popup{constructor(){popups.push(this);}addListener(){}setPosition(position){this.position=position;}setContent(content){this.content=content;}open(){this.opened=true;}close(){this.opened=false;}}
    let resolveMap; const pendingMap=new Promise(resolve=>resolveMap=resolve);
    const google={maps:{importLibrary:async name=>{if(deferMap)await pendingMap;return name==='maps'?{Map:MapView}:{AdvancedMarkerElement:Marker};},InfoWindow:Popup,RenderingType:{RASTER:'raster'},LatLng:class{constructor(lat,lng){Object.assign(this,{lat,lng});}},Point:class{constructor(x,y){Object.assign(this,{x,y});}},LatLngBounds:class{extend(){return this;}},event:{trigger(){},addListenerOnce(){}}}};
    const window=new Element();window.google=google;
    let releaseStops=null;
    const moduleMap={
        'map-marker-trail.mjs':{createMarkerTrail:()=>({setPath(){},remove(){},redraw(){}})},
        'map-motion.mjs':motion,'map-view.mjs':view,'map-dashboard-filter.mjs':dashboardFilter,
        'live-audio.mjs':{audioControls:()=>({select:async()=>{}})},
        'map-trip-history.mjs':{createTripHistory:()=>({active:false,close(){}})},
        'map-video-fullscreen.mjs':{createVideoFullscreen:()=>({close:async()=>{}}),observeMapLayout:()=>()=>{}},
        'map-video-player.mjs':{createMapVideoPlayer:({section,getVehicle})=>{
            const player={starts:[],prepared:false,prepare(){this.prepared=Number(section.dataset.mapChannel)<=getVehicle().equipment.channels;section.dataset.playbackState=this.prepared?'waiting':'missing';},startPrepared(){if(this.prepared){this.prepared=false;this.starts.push(getVehicle().id);}},stop(){this.prepared=false;return releaseStops?.promise??Promise.resolve();},resume(){}};
            players.push(player);return player;
        }},
    };
    const context={document,window,google,Intl,Date,Map,Set,Number,Math,URLSearchParams,AbortController,Event,console,
        ResizeObserver:class{observe(){}disconnect(){}},
        modules:async spec=>moduleMap[spec.replace('./','').split('?')[0]],
        location:{hash:'#map'},history:{replaceState(){}},matchMedia:()=>({matches:false}),
        Option:class extends Element{constructor(text,value){super();this.textContent=text;this.value=value;}},
        setTimeout:()=>1,clearTimeout(){},requestAnimationFrame:fn=>{frames.push(fn);return frames.length;},cancelAnimationFrame(){},
        fetch:async()=>({ok:true,status:200,json:async()=>({vehicles,generated_at:new Date().toISOString()})}),
    };
    const source=readFileSync(new URL('../../public/js/google-map.js',import.meta.url),'utf8').replace(/await import\('([^']+)'\)/g,'await modules("$1")');
    await vm.runInNewContext(source,context);await flush();
    return {node,players,sections,popups,frames,ready:async()=>{resolveMap();await flush();},refresh:async speed=>{vehicles=vehicles.map(v=>({...v,position:{...v.position,speed}}));node('tracking-refresh').click();await flush();},holdStops(){let resolve;const promise=new Promise(r=>resolve=r);releaseStops={promise};return ()=>{releaseStops=null;resolve();};}};
}

test('selecting a result keeps the entire list and panel visible, opens its popup and refreshes speeds',async()=>{
    const p=await page();assert.equal(p.node('tracking-vehicle-list').children.length,2);
    p.node('tracking-vehicle-list').children[0].click();await flush();
    assert.equal(p.node('tracking-results').hidden,false);assert.equal(p.node('tracking-vehicle-list').children.length,2);
    assert.equal(p.node('tracking-workspace').classList.contains('panel-collapsed'),false);
    assert.equal(p.popups[0].opened,true);assert.equal(p.popups[0].position.lng,15.31);
    await p.refresh(47);
    assert.equal(p.node('tracking-vehicle-list').children[0].children[1].children[2].children[1].textContent,'En déplacement · 47 km/h');
    assert.equal(p.popups[0].content.children[1].children[7].textContent,'47 km/h');
});
test('Videos opens a loading panel immediately and automatically starts all configured channels',async()=>{
    for(const channels of [1,2]){
        const p=await page({channels});p.node('tracking-vehicle-list').children[0].click();await flush();
        // Video also opens the filters if the user had previously collapsed them.
        if(channels===1)p.node('tracking-close-panel').click();
        const release=p.holdStops();p.popups[0].content.children[2].children[2].click();
        assert.equal(p.node('tracking-workspace').classList.contains('panel-collapsed'),false);
        assert.equal(p.node('tracking-close-panel').getAttribute('aria-expanded'),'true');
        assert.equal(p.node('tracking-results').hidden,false);
        assert.equal(p.node('tracking-video-panel').hidden,false);assert.equal(p.sections[0].dataset.playbackState,'waiting');
        assert.deepEqual(p.players[0].starts,[]);release();await flush();
        assert.deepEqual(p.players[0].starts,[1]);assert.deepEqual(p.players[1].starts,channels===2?[1]:[]);
        await p.refresh(36);
        assert.equal(p.node('tracking-workspace').classList.contains('panel-collapsed'),false);
        assert.equal(p.node('tracking-vehicle-list').children[0].children[1].children[2].children[1].textContent,'En déplacement · 36 km/h');
        p.node('tracking-video-close').click();await flush();
        assert.equal(p.node('tracking-workspace').classList.contains('panel-collapsed'),false);
    }
});
test('closing or selecting another vehicle during opening prevents delayed video starts',async()=>{
    for(const close of [true,false]){
        const p=await page();p.node('tracking-vehicle-list').children[0].click();await flush();
        const release=p.holdStops();p.popups[0].content.children[2].children[2].click();
        if(close)p.node('tracking-video-close').click();else p.node('tracking-vehicle-list').children[1].click();
        release();await flush();assert.ok(p.players.every(player=>player.starts.length===0));assert.equal(p.node('tracking-video-panel').hidden,true);
    }
});
test('unauthorized map viewers keep the video action disabled and never start a channel',async()=>{
    const p=await page({canVideo:false});p.node('tracking-vehicle-list').children[0].click();await flush();
    const button=p.popups[0].content.children[2].children[2];assert.equal(button.disabled,true);button.click();await flush();
    assert.ok(p.players.every(player=>player.starts.length===0));
});
test('a row selected while Google Maps loads opens its popup when the map is ready',async()=>{
    const p=await page({deferMap:true});p.node('tracking-vehicle-list').children[1].click();
    assert.equal(p.node('tracking-results').hidden,false);assert.equal(p.popups.length,0);
    await p.ready();assert.equal(p.popups[0].opened,true);assert.equal(p.popups[0].content.children[0].children[1].children[0].textContent,'Vehicle 2');
});
