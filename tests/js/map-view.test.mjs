import test from 'node:test';
import assert from 'node:assert/strict';
import {viewportPadding, projectedCenter, observeMapView} from '../../public/js/map-view.mjs';

test('the selected point stays at the centre of the uncovered tablet map at every zoom',()=>{
    for(const [width,height,panel] of [[768,950,340],[1024,640,340],[540,692,0],[390,718,0],[390,273,0],[844,144,0]]) {
        const padding=viewportPadding(width,height,panel), target={x:134,y:130};
        for(const zoom of [12,18,20]) {
            const center=projectedCenter(target,zoom,padding),scale=2**zoom;
            const screen={x:width/2+(target.x-center.x)*scale,y:height/2+(target.y-center.y)*scale};
            assert.ok(Math.abs(screen.x-(padding.left+width-padding.right)/2)<1e-6);
            assert.ok(Math.abs(screen.y-height/2)<1e-6);
        }
    }
});
test('narrow maps never receive fit padding wider or taller than their canvas',()=>{
    for(const width of [100,280,360,390,540])for(const height of [80,144,250]) {
        const padding=viewportPadding(width,height,340);
        assert.ok(padding.left+padding.right<width);
        assert.ok(padding.top+padding.bottom<height);
    }
});
test('background and foreground preserve the video panel; navigation away closes it',()=>{
    const document=new EventTarget();document.hidden=false;document.body={dataset:{view:'map'}};
    let opened=true, syncing=0,resumed=0,entering=0;
    const cleanup=observeMapView({document,closeVideo:()=>{opened=false;},sync:()=>syncing++,enter:()=>entering++,resume:()=>resumed++});
    document.hidden=true;document.dispatchEvent(new Event('visibilitychange'));
    assert.equal(opened,true);assert.equal(resumed,0);
    document.hidden=false;document.dispatchEvent(new Event('visibilitychange'));
    assert.equal(opened,true);assert.equal(resumed,1);assert.equal(syncing,3);
    document.body.dataset.view='overview';document.dispatchEvent(new Event('exadcam:view-changed'));
    assert.equal(opened,false);assert.equal(entering,1);
    document.dispatchEvent(new Event('visibilitychange'));assert.equal(resumed,1);
    cleanup();document.dispatchEvent(new Event('visibilitychange'));assert.equal(syncing,5);
});
test('leaving the map while the browser tab is hidden still releases video',()=>{
    const document=new EventTarget();document.hidden=true;document.body={dataset:{view:'map'}};let closed=0;
    const cleanup=observeMapView({document,closeVideo:()=>closed++,sync:()=>{},enter:()=>{},resume:()=>{throw Error('hidden resume');}});
    document.body.dataset.view='users';document.dispatchEvent(new Event('exadcam:view-changed'));
    assert.equal(closed,1);cleanup();
});
