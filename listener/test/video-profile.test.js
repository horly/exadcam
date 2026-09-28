import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import {spawn,spawnSync} from 'node:child_process';
import {once} from 'node:events';
import {videoMuxerArgs,videoStartupBuffer} from '../src/video-profile.js';

test('unknown cameras and the copy override retain the compatible remux profile',()=>{
    for(const [model,profile] of [['other','fast'],['ES500-603','copy']]){
        assert.equal(videoStartupBuffer({model},profile),8);
        const args=videoMuxerArgs({model,frame_rate:12,normalize_video_timestamps:true},'/tmp/media',{profile});
        assert.equal(args[args.indexOf('-c:v')+1],'copy');assert.equal(args[args.indexOf('-hls_time')+1],'2');
        assert.ok(args.includes('setts=ts=N/(12*TB)'));
    }
    for(const rate of [0,-1,Infinity,61])assert.throws(()=>videoMuxerArgs({frame_rate:rate},'/tmp/media'));
});

test('real-time input with long GOPs yields independent one-second segments promptly',{
    skip:!fs.existsSync('/usr/bin/ffmpeg'),timeout:16000,
},async t=>{
    const directory=fs.mkdtempSync(path.join(os.tmpdir(),'exadcam-startup-test-'));
    const fixture=spawnSync('/usr/bin/ffmpeg',['-v','error','-f','lavfi','-i','testsrc=size=960x540:rate=12','-t','7','-pix_fmt','yuv420p',
        '-c:v','libx264','-threads','1','-preset','ultrafast','-tune','zerolatency','-g','60','-x264-params','repeat-headers=1:aud=1','-f','h264','pipe:1'],{maxBuffer:8*1024*1024});
    assert.equal(fixture.status,0,fixture.stderr.toString());
    const raw=fixture.stdout,starts=[];
    for(let i=0;i<raw.length-5;i++)if(raw[i]===0&&raw[i+1]===0&&raw[i+2]===0&&raw[i+3]===1&&(raw[i+4]&31)===9)starts.push(i);
    starts.push(raw.length);assert.ok(starts.length>70);
    const encoder=spawn('/usr/bin/ffmpeg',videoMuxerArgs({model:'ES500-603',frame_rate:12,normalize_video_timestamps:true},directory));
    let stderr='';encoder.stderr.on('data',b=>stderr+=b);encoder.stdin.on('error',()=>{});
    t.after(()=>{if(encoder.exitCode===null)encoder.kill('SIGKILL');fs.rmSync(directory,{recursive:true,force:true});});
    const ended=once(encoder,'close'),began=performance.now();let first=null;
    for(let i=0;i<starts.length-1;i++){
        encoder.stdin.write(raw.subarray(starts[i],starts[i+1]));
        await new Promise(r=>setTimeout(r,1000/12));
        if(first===null&&fs.existsSync(path.join(directory,'index.m3u8')))first=performance.now()-began;
    }
    encoder.stdin.end();const [code]=await ended;assert.equal(code,0,stderr);
    assert.ok(first!==null&&first<2500,'First manifest must appear before a five-second camera GOP ends: '+first);
    const playlist=fs.readFileSync(path.join(directory,'index.m3u8'),'utf8');
    assert.match(playlist,/#EXT-X-INDEPENDENT-SEGMENTS/);
    const segments=playlist.split('\n').filter(line=>line.endsWith('.ts'));assert.ok(segments.length>=6);
    for(const segment of [segments[0],segments[1],segments.at(-2)]){
        const result=spawnSync('/usr/bin/ffprobe',['-v','error','-select_streams','v:0','-show_entries','stream=width,height:packet=flags','-of','json',path.join(directory,segment)]);
        assert.equal(result.status,0,result.stderr.toString());const data=JSON.parse(result.stdout);
        assert.equal(data.streams[0].width,960);assert.equal(data.streams[0].height,540);assert.ok(data.packets[0].flags.includes('K'));
        const decoded=spawnSync('/usr/bin/ffmpeg',['-v','error','-i',path.join(directory,segment),'-f','null','-']);
        assert.equal(decoded.status,0,decoded.stderr.toString());
    }
});
