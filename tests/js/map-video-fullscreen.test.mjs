import test from 'node:test';
import assert from 'node:assert/strict';
import {createVideoFullscreen,observeMapLayout} from '../../public/js/map-video-fullscreen.mjs';

class Element extends EventTarget {
    constructor(document){super();this.document=document;this.children=[];this.attrs=new Map();this.dataset={};this.inert=false;this.isConnected=true;this.hidden=false;this.scrollTop=42;const names=new Set();this.classList={toggle(name,on){on?names.add(name):names.delete(name);},contains:name=>names.has(name)};}
    append(...children){for(const node of children){node.parentElement=this;this.children.push(node);}}
    setAttribute(name,value){this.attrs.set(name,String(value));}
    getAttribute(name){return this.attrs.get(name)??null;}
    removeAttribute(name){this.attrs.delete(name);}
    toggleAttribute(name,on){on?this.attrs.set(name,''):this.attrs.delete(name);}
    focus(){this.document.activeElement=this;}
    contains(node){return node===this||this.children.some(c=>c.contains(node));}
    querySelector(selector){return this.parts[selector];}
    querySelectorAll(){return this.focusable||[];}
    getClientRects(){return [{}];}
    closest(){return null;}
}
function setup(mode='fallback'){
    const document=new EventTarget();document.body=new Element(document);
    const topbar=new Element(document),main=new Element(document),map=new Element(document),panel=new Element(document),button=new Element(document),last=new Element(document),video=new Element(document);
    document.body.append(topbar,main);main.append(map,panel);panel.append(button,video,last);panel.focusable=[button,video,last];
    button.parts=Object.fromEntries(['[data-fullscreen-label]','[data-video-expand-icon]','[data-video-reduce-icon]'].map(s=>[s,new Element(document)]));
    Object.assign(button.dataset,{enterLabel:'Expand both channels',exitLabel:'Exit',enterShort:'2 screens',exitShort:'Reduce'});
    video.src='owned-live-stream';video.currentTime=17;video.pause=video.load=video.play=()=>{throw Error('Fullscreen must not touch playback');};
    button.focus();let exits=0,resolve;
    document.exitFullscreen=async()=>{exits++;document.fullscreenElement=null;document.dispatchEvent(new Event('fullscreenchange'));};
    if(mode==='native')panel.requestFullscreen=async()=>{document.fullscreenElement=panel;document.dispatchEvent(new Event('fullscreenchange'));};
    if(mode==='refused')panel.requestFullscreen=async()=>{throw Error('Unsupported');};
    if(mode==='pending')panel.requestFullscreen=()=>new Promise(r=>{resolve=()=>{document.fullscreenElement=panel;document.dispatchEvent(new Event('fullscreenchange'));r();};});
    const controller=createVideoFullscreen({panel,button,document});
    return {document,topbar,main,map,panel,button,last,video,controller,finish:()=>resolve(),exits:()=>exits};
}
for(const mode of ['native','fallback','refused'])test(`${mode} fullscreen preserves video, restores scrolling, focus and background`,async()=>{
    const f=setup(mode);f.map.inert=true;
    await f.controller.open();assert.equal(f.controller.active,true);assert.equal(f.panel.getAttribute('role'),'dialog');
    assert.equal(f.topbar.inert,true);assert.equal(f.main.inert,false);assert.equal(f.panel.inert,false);
    assert.equal(f.video.src,'owned-live-stream');assert.equal(f.video.currentTime,17);assert.equal(f.video.parentElement,f.panel);
    f.panel.scrollTop=0;await f.controller.close();
    assert.equal(f.controller.active,false);assert.equal(f.panel.getAttribute('role'),null);assert.equal(f.panel.scrollTop,42);
    assert.equal(f.topbar.inert,false);assert.equal(f.map.inert,true);assert.equal(f.document.activeElement,f.button);
    assert.equal(f.exits(),mode==='native'?1:0);
});
test('native Escape exits the split view without closing either player',async()=>{
    const f=setup('native');await f.controller.open();
    f.document.fullscreenElement=null;f.document.dispatchEvent(new Event('fullscreenchange'));
    assert.equal(f.controller.active,false);assert.equal(f.video.currentTime,17);assert.equal(f.topbar.inert,false);
});
test('late fullscreen permission cannot reopen a panel closed by navigation',async()=>{
    const f=setup('pending');const opening=f.controller.open();await f.controller.close({restoreFocus:false});
    f.panel.hidden=true;f.finish();await opening;
    assert.equal(f.controller.active,false);assert.equal(f.document.fullscreenElement,null);assert.equal(f.exits(),1);
});
test('fallback traps tab focus and Escape returns to the original button',async()=>{
    const f=setup();await f.controller.open();
    const key=(name,shift=false)=>{const e=new Event('keydown',{cancelable:true});e.key=name;e.shiftKey=shift;f.document.dispatchEvent(e);return e;};
    assert.equal(key('Tab',true).defaultPrevented,true);assert.equal(f.document.activeElement,f.last);
    assert.equal(key('Tab').defaultPrevented,true);assert.equal(f.document.activeElement,f.button);
    key('Escape');assert.equal(f.controller.active,false);assert.equal(f.document.activeElement,f.button);
});
test('single-video native fullscreen nested in split view preserves the parent layout',async()=>{
    const f=setup('native');await f.controller.open();f.document.fullscreenElement=f.video;f.document.dispatchEvent(new Event('fullscreenchange'));
    assert.equal(f.controller.active,true);f.document.fullscreenElement=f.panel;f.document.dispatchEvent(new Event('fullscreenchange'));
    assert.equal(f.controller.active,true);await f.controller.close();
});
test('responsive layout follows the workspace width, height and orientation, not screen width',()=>{
    let update,disconnected=false;const workspace=new Element({});workspace.style={setProperty(){}};let size={width:768,height:930};workspace.getBoundingClientRect=()=>size;
    const close=observeMapLayout(workspace,{ResizeObserver:class{constructor(callback){update=callback;}observe(){}disconnect(){disconnected=true;}}});
    assert.equal(workspace.classList.contains('is-stacked'),true);
    size={width:390,height:690};update();assert.equal(workspace.classList.contains('is-narrow'),true);
    size={width:320,height:449};update();assert.equal(workspace.classList.contains('is-stacked'),true);
    size={width:844,height:300};update();assert.equal(workspace.classList.contains('is-stacked'),false);
    size={width:1200,height:700};update();assert.equal(workspace.classList.contains('is-stacked'),false);assert.equal(workspace.classList.contains('is-narrow'),false);
    close();assert.equal(disconnected,true);
});
