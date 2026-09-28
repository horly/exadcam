import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { randomUUID } from 'node:crypto';
import { removeMediaDirectory } from '../src/media-cleanup.js';

test('cleanup removes only the closed UUID directory and preserves other active streams', async t => {
    const root=await fs.mkdtemp(path.join(os.tmpdir(),'exadcam-cleanup-'));
    t.after(()=>fs.rm(root,{recursive:true,force:true}));
    const closed=randomUUID(),active=randomUUID();
    for(const id of [closed,active]){await fs.mkdir(path.join(root,id));await fs.writeFile(path.join(root,id,'segment.ts'),'synthetic media');}
    assert.equal(await removeMediaDirectory(root,closed),true);
    await assert.rejects(fs.stat(path.join(root,closed)),{code:'ENOENT'});
    assert.equal(await fs.readFile(path.join(root,active,'segment.ts'),'utf8'),'synthetic media');
});

test('an encoder cleanup failure is contained instead of rejecting stop and crashing all streams', async () => {
    const events=[];
    for(const code of ['ENOTEMPTY','EBUSY','EACCES']) {
        const result=await removeMediaDirectory(os.tmpdir(),randomUUID(),{
            remove:async()=>{throw Object.assign(Error('synthetic file still being written'),{code});},
            report:(event,data)=>events.push({event,...data}),
        });
        assert.equal(result,false);
    }
    assert.deepEqual(events.map(e=>e.reason),['ENOTEMPTY','EBUSY','EACCES']);
    assert.ok(events.every(e=>e.event==='video_cleanup_deferred'));
});

test('cleanup is asynchronous and rejects paths outside generated stream directories', async () => {
    let release,called=0,finished=false;
    const gate=new Promise(resolve=>release=resolve);
    const remove=async()=>{called++;await gate;};
    const pending=removeMediaDirectory(os.tmpdir(),randomUUID(),{remove}).then(()=>finished=true);
    await Promise.resolve();assert.equal(finished,false);
    for(const id of ['../outside','.',os.tmpdir()])assert.equal(await removeMediaDirectory(os.tmpdir(),id,{remove}),false);
    assert.equal(called,1);
    release();await pending;assert.equal(finished,true);
});
