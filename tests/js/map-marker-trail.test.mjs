import test from 'node:test';
import assert from 'node:assert/strict';
import {relativeTrail, createMarkerTrail} from '../../public/js/map-marker-trail.mjs';
const project = p => ({x:p.lng,y:p.lat});

test('moving endpoint stays at the arrow origin at every zoom, including bends', () => {
    for (const zoom of [3,12,18,20]) for (const lat of [1,1.2,1.9,2]) {
        const position = {lat,lng:2};
        const path = relativeTrail([{lat:0,lng:0},{lat:0,lng:2},position],position,zoom,project);
        assert.ok(path.endsWith('L0.000 0.000'));
        assert.equal((path.match(/L/g)||[]).length,2);
    }
    assert.equal(relativeTrail([], {lat:1,lng:2},18,project),'');
});
test('relative projection takes the short path across the date line', () => {
    assert.equal(relativeTrail([{lat:0,lng:255},{lat:0,lng:1}],{lat:0,lng:1},3,project),'M-16.000 0.000 L0.000 0.000');
});
test('line and arrow share a DOM parent; zoom refresh and removal update that same line', t => {
    const previous = globalThis.document;
    const node = () => ({attributes:{},children:[],setAttribute(k,v){this.attributes[k]=v;},append(n){this.children.push(n);},remove(){this.removed=true;}});
    globalThis.document = {createElementNS:node};
    t.after(() => {globalThis.document=previous;});
    const content = {children:[{glyph:true}],prepend(n){this.children.unshift(n);}};
    let zoom = 3;
    const trail = createMarkerTrail(content,{getProjection:()=>({fromLatLngToPoint:project}),getZoom:()=>zoom},{LatLng:class{constructor(lat,lng){Object.assign(this,{lat,lng});}}});
    const svg=content.children[0], path=svg.children[0];
    trail.setPath([{lat:0,lng:0},{lat:1,lng:1}],{lat:1,lng:1});
    assert.equal(path.attributes.d,'M-8.000 -8.000 L0.000 0.000');
    zoom=4;trail.redraw();assert.equal(path.attributes.d,'M-16.000 -16.000 L0.000 0.000');
    trail.setPath([],{lat:1,lng:1});assert.equal(path.attributes.d,'');
    trail.remove();assert.equal(svg.removed,true);
});
