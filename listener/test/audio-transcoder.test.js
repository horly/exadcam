import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import {audioTranscoder,AdtsFrames} from '../src/audio-codec.js';

for(const codec of [6,7,19])test(`real FFmpeg microphone encoding and listening decoding round-trip codec ${codec}`,{skip:!fs.existsSync(process.env.FFMPEG_PATH||'/usr/bin/ffmpeg')},async()=>{
    let encoder,decoder,timer,deadline,samples=0,energy=0,frames=0;
    try{
        await new Promise((resolve,reject)=>{
            const adts=new AdtsFrames();
            decoder=audioTranscoder({codec,rate:8000,onError:reject,onData:pcm=>{
                for(let i=0;i+1<pcm.length;i+=2){samples++;energy+=Math.abs(pcm.readInt16LE(i));}
                if(samples>=4000)resolve();
            }});
            encoder=audioTranscoder({codec,rate:8000,encode:true,onError:reject,onData:data=>{
                try{for(const frame of codec===19?adts.push(data):[data]){frames++;decoder.write(frame);}}catch(error){reject(error);}
            }});
            let time=0;
            timer=setInterval(()=>{
                const pcm=Buffer.alloc(1280);
                for(let i=0;i<640;i++)pcm.writeInt16LE(Math.round(Math.sin(2*Math.PI*440*time++/16000)*10000),i*2);
                try{encoder.write(pcm);}catch(error){reject(error);}
            },40);
            deadline=setTimeout(()=>reject(Error('Audio round-trip timeout')),5000);
        });
        assert.ok(frames>0);assert.ok(energy/samples>1000,'Decoded PCM must contain the test tone, not just silence');
    }finally{clearInterval(timer);clearTimeout(deadline);encoder?.close();decoder?.close();}
});
