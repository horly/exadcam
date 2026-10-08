import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';
import {playAlertTone} from '../../public/js/alert-sound.mjs';

const flush=async()=>{for(let i=0;i<6;i++)await new Promise(resolve=>setImmediate(resolve));};
class Element extends EventTarget {
    constructor(){super();this.children=[];this.attrs={};this.checked=false;this.textContent='';}
    append(...nodes){this.children.push(...nodes);nodes.forEach(n=>n.parent=this);} setAttribute(k,v){this.attrs[k]=v;}
    remove(){this.parent.children=this.parent.children.filter(n=>n!==this);} replaceChildren(...nodes){this.children=[];this.append(...nodes);}
    click(){this.dispatchEvent(new Event('click'));}
}
async function page({storage=new Map(),first,lock=true}={}){
    const nodes=new Map();const node=id=>{if(!nodes.has(id))nodes.set(id,new Element());return nodes.get(id);};
    const document=new Element(),window=new Element(),timers=new Map(),requests=[],toasts=[],sounds=[];
    document.hidden=false;document.getElementById=id=>id==='cam-notification-config'?null:node(id);document.createElement=()=>new Element();document.querySelectorAll=()=>[node('badge')];
    class Context {constructor(){this.state='suspended';}async resume(){this.state='running';}}
    window.AudioContext=Context;
    window.bootstrap={Toast:class {constructor(el){this.el=el;toasts.push(el);}show(){this.el.shown=true;}hide(){this.el.dispatchEvent(new Event('hidden.bs.toast'));}dispose(){}}};
    let result=first??{data:[],cursor:{after_alarm:10,after_connection_at:'2026-10-08 11:00:00',after_connection_id:0},total:2};
    const context=vm.createContext({document,window,navigator:{locks:{request:async(k,o,fn)=>fn(lock?{}:null)}},localStorage:{getItem:k=>storage.get(k)??null,setItem:(k,v)=>storage.set(k,v)},
        fetch:async(url)=>{requests.push(url);if(result instanceof Error)throw result;return {ok:!(result.status>=400),status:result.status??200,json:async()=>result};},
        setTimeout:(fn,ms)=>{const id=Symbol();timers.set(id,{fn,ms});return id;},clearTimeout:id=>timers.delete(id),AbortController,Date,Intl,URLSearchParams,
        CustomEvent:class extends Event{constructor(type,options){super(type);this.detail=options?.detail;}},playAlertTone:audio=>{if(audio?.state!=='running')return false;sounds.push(1);return true;}});
    const source=readFileSync(new URL('../../public/js/alert-notifications.mjs',import.meta.url),'utf8').replace(/^import[^\n]+\n/,'').replace('export function','function');
    vm.runInContext(source,context);
    context.config={user:1,url:'/recent',locale:'fr',labels:{sound:'Sound',browser:'Saved',unlock:'Click',unavailable:'Unavailable',close:'Close',open:'Alerts'}};
    vm.runInContext('startNotifications(config)',context);await flush();
    return {nodes,node,document,window,toasts,sounds,requests,storage,setResult:r=>{result=r;},async poll(){const timer=[...timers].find(([,t])=>t.ms===15000||t.ms===2500);assert.ok(timer);timers.delete(timer[0]);timer[1].fn();await flush();}};
}
const alert={id:'alarm-11',title:'SOS',vehicle:'Vehicle <script>',registration:'TEST',description:'Alert',at:'2026-10-08T11:00:00Z'};
test('starts silently, shows a safe clickable toast for a new alert, and advances cursors',async()=>{
    const p=await page();assert.equal(p.toasts.length,0);assert.equal(p.node('badge').textContent,2);
    p.setResult({data:[alert],cursor:{after_alarm:11},total:3});await p.poll();
    assert.ok(p.requests[1].includes('after_alarm=10'));assert.equal(p.toasts.length,1);assert.equal(p.sounds.length,0);
    const toast=p.toasts[0],body=toast.children[1];assert.equal(body.children[0].textContent,'Vehicle <script> · TEST');assert.equal(body.children[3].href,'#alerts');
    p.setResult({data:[],cursor:{after_alarm:11},total:3});await p.poll();assert.ok(p.requests[2].includes('after_alarm=11'));assert.equal(p.toasts.length,1);
    toast.children[0].children[2].click();assert.equal(p.node('cam-alert-toasts').children.length,0);
});
test('sound is opt in, can be disabled, and preview does not change the preference',async()=>{
    const p=await page();const toggle=p.node('cam-alert-sound');assert.equal(toggle.checked,false);
    p.node('cam-alert-sound-test').click();await flush();assert.equal(p.sounds.length,1);assert.equal(toggle.checked,false);
    toggle.checked=true;toggle.dispatchEvent(new Event('change'));await flush();assert.equal(p.sounds.length,2);
    p.setResult({data:[alert],cursor:{after_alarm:11},total:3});await p.poll();assert.equal(p.sounds.length,3);
    toggle.checked=false;toggle.dispatchEvent(new Event('change'));await flush();
    p.setResult({data:[{...alert,id:'alarm-12'}],cursor:{after_alarm:12},total:4});await p.poll();assert.equal(p.sounds.length,3);
    assert.equal(p.storage.get('exadcam:notifications:v1:1:sound'),'false');
});
test('failed requests retain the cursor and expired authorization stops the feed',async()=>{
    const p=await page();p.setResult(new Error('offline'));await p.poll();p.setResult({data:[alert],cursor:{after_alarm:11},total:3});await p.poll();
    assert.equal(p.requests[1],p.requests[2]);assert.equal(p.toasts.length,1);
    p.setResult({status:403});await p.poll();const count=p.requests.length;
    p.document.dispatchEvent(new Event('visibilitychange'));await flush();assert.equal(p.requests.length,count);
});
test('shared cursor prevents duplicate toast delivery across tabs and a held lock skips polling',async()=>{
    const storage=new Map();const one=await page({storage});
    one.setResult({data:[alert],cursor:{after_alarm:11},total:3});await one.poll();
    const two=await page({storage,first:{data:[],cursor:{after_alarm:11},total:3}});assert.ok(two.requests[0].includes('after_alarm=11'));assert.equal(two.toasts.length,0);
    const locked=await page({lock:false});assert.equal(locked.requests.length,0);
});
test('sound does not start in a suspended audio context and releases audio nodes on completion',()=>{
    assert.equal(playAlertTone({state:'suspended'}),false);
    const nodes=[],frequencies=[];const context={state:'running',currentTime:0,destination:{},
        createGain(){const n={gain:{value:0,setValueAtTime(){},exponentialRampToValueAtTime(){}},connect(){},disconnect(){this.disconnected=true;}};nodes.push(n);return n;},
        createOscillator(){const n={frequency:{setValueAtTime:v=>frequencies.push(v)},connect(){},start(){},stop(){},disconnect(){this.disconnected=true;}};nodes.push(n);return n;}};
    assert.equal(playAlertTone(context),true);assert.ok(frequencies[0]>frequencies[3]);
    nodes.filter(n=>n.onended).forEach(n=>n.onended());assert.ok(nodes.every(n=>n.disconnected));
});
