import test from 'node:test';
import assert from 'node:assert/strict';
import { movementPath, pointAlong, bearing, pathThrough, trailThroughPosition, preferredTrackingVehicle } from '../../public/js/map-motion.mjs';

const previous = {source_id:1, position:{lat:-4.33,lng:15.22,at:'2026-09-22T12:00:00Z'}};
const next = {source_id:1,state:'moving',position:{lat:-4.329,lng:15.221,at:'2026-09-22T12:00:20Z'},trail:[{lat:-4.329,lng:15.22,at:'2026-09-22T12:00:10Z'}]};
test('movement follows received corner samples rather than cutting across the route', () => {
    const path = movementPath(previous,next);
    assert.equal(path.length,3);
    const halfway = pointAlong(path,0.49);
    assert.equal(halfway.lng,15.22);
    assert.ok(halfway.lat > previous.position.lat);
    assert.deepEqual(pointAlong(path,1),{lat:next.position.lat,lng:next.position.lng,at:new Date(next.position.at).toISOString()});
});
test('stationary and repeated samples do not create animation', () => {
    assert.deepEqual(movementPath(previous,{...next,position:previous.position}),[]);
    assert.deepEqual(movementPath(previous,{...next,position:{...previous.position,at:next.position.at}}),[]);
});
test('offline gaps changed cameras future ordering and teleports are never interpolated', () => {
    for (const changed of [{...next,state:'offline'}, {...next,source_id:2}, {...next,position:{...next.position,at:'2026-09-22T12:10:00Z'}}, {...next,position:{...next.position,at:'2026-09-22T11:59:00Z'}}, {...next,position:{...next.position,lat:-5}}]) assert.deepEqual(movementPath(previous,changed),[]);
});

test('parking and ignition-on stops do not animate even with drifting GPS', () => {
    for (const state of ['parking','stopped']) assert.deepEqual(movementPath(previous,{...next,state}),[]);
});
test('arrow bearing follows north east and west and line ends at the animated marker', () => {
    assert.equal(bearing({lat:0,lng:0},{lat:1,lng:0}),0);
    assert.equal(bearing({lat:0,lng:0},{lat:0,lng:1}),90);
    assert.equal(bearing({lat:0,lng:0},{lat:0,lng:-1}),270);
    const path = movementPath(previous,next), partial = pathThrough(path,0.2);
    assert.equal(partial.length,2);
    assert.deepEqual(partial.at(-1),pointAlong(path,0.2));
    assert.equal(partial.at(-1).lng,previous.position.lng);
});

test('a redraw never reveals a future trail beyond the displayed arrow', () => {
    const path = movementPath(previous,next), position = pointAlong(path,0.2);
    const line = trailThroughPosition(next,position,path);
    assert.deepEqual(line.at(-1),position);
    assert.ok(line.every(p => Date.parse(p.at) <= Date.parse(position.at)));
    assert.equal(line.length,2);
    assert.deepEqual(trailThroughPosition(next,position,path),line);
});
test('a report interrupting an animation keeps the remaining GPS corners', () => {
    const path = movementPath(previous,next), position = pointAlong(path,0.2);
    const later = {...next,position:{lat:-4.3288,lng:15.221,at:'2026-09-22T12:00:30Z'},trail:[previous.position,...next.trail,next.position]};
    const resumed = movementPath(next,later,position);
    assert.deepEqual(resumed,[position,...next.trail,next.position,later.position]);
    const advanced = pointAlong(resumed,0.1);
    const line = trailThroughPosition(later,advanced,resumed);
    assert.deepEqual(line.at(-1),advanced);
    assert.deepEqual(line[0],previous.position);
    assert.equal(advanced.lng,previous.position.lng);
});
test('stopping animation commits the trail and arrow to the same latest position', () => {
    assert.deepEqual(trailThroughPosition(next,next.position).at(-1),next.position);
    for (const state of ['stopped','parking','offline','stale']) {
        assert.deepEqual(trailThroughPosition({...next,state},next.position),[]);
    }
});

const parked = {id:1,online:true,state:'parking',position:previous.position};
const moving = {id:2,online:true,state:'moving',position:next.position};
test('initial map focus prefers a moving vehicle over a parked or offline one', () => {
    assert.equal(preferredTrackingVehicle([parked,{...moving,id:3,online:false},moving]).id,2);
});
test('map focus respects the chosen vehicle even when another vehicle is moving', () => {
    assert.equal(preferredTrackingVehicle([parked,moving],1).id,1);
    assert.equal(preferredTrackingVehicle([moving,{...moving,id:3,position:{...next.position,at:'2026-09-22T12:01:00Z'}}],2).id,2);
});
test('moving focus waits for real coordinates and prefers the freshest eligible report', () => {
    assert.equal(preferredTrackingVehicle([parked,{...moving,position:null}]),null);
    assert.equal(preferredTrackingVehicle([moving,{...moving,id:3,position:{...next.position,at:'2026-09-22T12:01:00Z'}}]).id,3);
    assert.equal(preferredTrackingVehicle([parked,{...moving,position:{lat:NaN,lng:15}}]),null);
});
