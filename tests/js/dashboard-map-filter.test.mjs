import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
import {dashboardConnection,matchesMapState,applyDashboardConnection} from '../../public/js/map-dashboard-filter.mjs';

test('connection shortcuts survive reload and reject unrelated or invalid hashes',()=>{
    assert.equal(dashboardConnection('#map?connection=online'),'online');
    assert.equal(dashboardConnection('#map?connection=offline'),'offline');
    for(const hash of ['#map','#alerts?connection=online','#map?connection=all']) assert.equal(dashboardConnection(hash),'');
});
test('online includes parked and missing GPS vehicles, offline includes never connected equipment but not unequipped vehicles',()=>{
    const vehicles=[
        {id:1,source_id:1,online:true,state:'moving'},
        {id:2,source_id:2,online:true,state:'parking'},
        {id:3,source_id:3,online:true,state:'no_position'},
        {id:4,source_id:4,online:false,state:'offline'},
        {id:5,source_id:5,online:false,state:'no_position'},
        {id:6,source_id:null,online:false,state:'no_camera'},
    ];
    assert.deepEqual(vehicles.filter(v=>matchesMapState(v,'online')).map(v=>v.id),[1,2,3]);
    assert.deepEqual(vehicles.filter(v=>matchesMapState(v,'offline')).map(v=>v.id),[4,5]);
    assert.deepEqual(vehicles.filter(v=>matchesMapState(v,'parking')).map(v=>v.id),[2]);
    assert.equal(vehicles.filter(v=>matchesMapState(v,'')).length,6);
});
test('a dashboard shortcut clears narrowing filters and displays every matching vehicle',()=>{
    const fields=Object.fromEntries(['fleet','department','search','state','show-all','follow'].map(id=>['tracking-'+id,{value:'previous',checked:id==='follow',dispatchEvent(){}}]));
    assert.equal(applyDashboardConnection({getElementById:id=>fields[id]},'offline'),true);
    for(const id of ['fleet','department','search'])assert.equal(fields['tracking-'+id].value,'');
    assert.equal(fields['tracking-state'].value,'offline');
    assert.equal(fields['tracking-show-all'].checked,true);
    assert.equal(fields['tracking-follow'].checked,false);
    // Client maps have no fleet selector; dashboard shortcuts must still work.
    delete fields['tracking-fleet'];
    assert.equal(applyDashboardConnection({getElementById:id=>fields[id]},'online'),true);
    assert.equal(fields['tracking-state'].value,'online');
    assert.equal(applyDashboardConnection({getElementById(){throw Error('must not touch');}},'unknown'),false);
});

function navigation(hash,dashcamsView){
    const listeners={},events=[];
    const element=()=>({dataset:{},classList:{contains:()=>false,toggle(){}},querySelectorAll:()=>[],focus(){},addEventListener(){},textContent:''});
    const sidebar=element(),main=element(),body={dataset:{dashcamsView}},topbar=element();
    const panels=['map','vehicles','dashcams'].map(view=>({...element(),dataset:{moduleView:view,moduleTitle:view,moduleDescription:view}}));
    const document={body,
        querySelectorAll:s=>s==='[data-module-view]'?panels:[],
        querySelector:s=>s==='.corporate-topbar'?topbar:s.startsWith('[data-view-')?element():null,
        getElementById:id=>id==='app-sidebar'?sidebar:main,
        dispatchEvent:e=>events.push(e),
    };
    const location={hash},history={replaceState:(_,__,hash)=>location.hash=hash};
    vm.runInNewContext(readFileSync(new URL('../../public/js/app.js',import.meta.url),'utf8'),{document,location,history,
        window:{addEventListener:(name,fn)=>listeners[name]=fn,scrollTo(){}},
        CustomEvent:class{constructor(name,args){this.type=name;Object.assign(this,args);}},
    });
    return {body,location,events,go(hash){location.hash=hash;listeners.hashchange();}};
}
test('hash navigation opens filtered map and supports browser back to overview',()=>{
    const n=navigation('#map?connection=online','dashcams');
    assert.equal(n.body.dataset.view,'map');assert.equal(n.events.at(-1).detail.view,'map');
    n.go('#overview');assert.equal(n.body.dataset.view,'overview');
    n.go('#map?connection=offline');assert.equal(n.body.dataset.view,'map');
});
test('legacy Dashcams links route clients to vehicles and preserve superadmin access',()=>{
    const client=navigation('#dashcams','vehicles');assert.equal(client.body.dataset.view,'vehicles');assert.equal(client.location.hash,'#vehicles');
    const readonly=navigation('#dashcams','fleet');assert.equal(readonly.body.dataset.view,'fleet');
    const admin=navigation('#dashcams','dashcams');assert.equal(admin.body.dataset.view,'dashcams');assert.equal(admin.location.hash,'#dashcams');
});
