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

test('ES500 microphone boost quadruples quiet speech and limits loud peaks through real G711', {skip:!fs.existsSync(process.env.FFMPEG_PATH||'/usr/bin/ffmpeg')}, async()=>{
    async function measure(amplitude,gain){
        let encoder,decoder,timer,deadline,count=0,sum=0,peak=0,time=0;
        try{
            await new Promise((resolve,reject)=>{
                decoder=audioTranscoder({codec:6,rate:8000,onError:reject,onData:pcm=>{
                    for(let i=0;i+1<pcm.length;i+=2){const value=Math.abs(pcm.readInt16LE(i));count++;sum+=value;peak=Math.max(peak,value);}
                    if(count>=8000)resolve();
                }});
                encoder=audioTranscoder({codec:6,rate:8000,encode:true,gain,onError:reject,onData:data=>decoder.write(data)});
                timer=setInterval(()=>{const pcm=Buffer.alloc(1280);for(let i=0;i<640;i++)pcm.writeInt16LE(Math.round(Math.sin(2*Math.PI*440*time++/16000)*amplitude),i*2);try{encoder.write(pcm);}catch(e){reject(e);}},40);
                deadline=setTimeout(()=>reject(Error('Boost timeout')),5000);
            });
            return {mean:sum/count,peak};
        }finally{clearInterval(timer);clearTimeout(deadline);encoder?.close();decoder?.close();}
    }
    const normal=await measure(3000,1),boosted=await measure(3000,4),loud=await measure(26000,4);
    assert.ok(boosted.mean/normal.mean>3.6&&boosted.mean/normal.mean<4.4);
    assert.ok(loud.peak<32700,'Limiter keeps amplified speech away from full-scale clipping');
    assert.ok(loud.mean>normal.mean*4);
});

test('JK114 outbound microphone gain doubles quiet speech through real AAC', {skip:!fs.existsSync(process.env.FFMPEG_PATH||'/usr/bin/ffmpeg')}, async()=>{
 async function measure(gain){
  let encoder,decoder,timer,deadline,count=0,energy=0,time=0;
  try{
   await new Promise((resolve,reject)=>{
    const adts=new AdtsFrames();
    decoder=audioTranscoder({codec:19,rate:8000,onError:reject,onData:pcm=>{
     for(let i=0;i+1<pcm.length;i+=2){count++;energy+=Math.abs(pcm.readInt16LE(i));}
     if(count>=12000)resolve();
    }});
    encoder=audioTranscoder({codec:19,rate:8000,encode:true,gain,onError:reject,onData:data=>{
     try{for(const frame of adts.push(data))decoder.write(frame);}catch(e){reject(e);}
    }});
    timer=setInterval(()=>{const pcm=Buffer.alloc(1280);for(let i=0;i<640;i++)pcm.writeInt16LE(Math.round(Math.sin(2*Math.PI*440*time++/16000)*3000),i*2);try{encoder.write(pcm);}catch(e){reject(e);}},40);
    deadline=setTimeout(()=>reject(Error('AAC boost timeout')),5000);
   });
   return energy/count;
  }finally{clearInterval(timer);clearTimeout(deadline);encoder?.close();decoder?.close();}
 }
 const base=await measure(1),boost=await measure(2);
 assert.ok(base>1000);assert.ok(boost/base>1.8&&boost/base<2.2,'Vehicle-bound AAC carries amplified microphone samples');
});
