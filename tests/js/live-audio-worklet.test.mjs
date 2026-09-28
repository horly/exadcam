import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
test('microphone captures distinct PCM for transport and never monitors it in the browser',()=>{
 let Processor;const sent=[];
 const context={AudioWorkletProcessor:class{constructor(){this.port={postMessage:data=>sent.push(data)};}},registerProcessor:(_name,value)=>{Processor=value;}};
 vm.runInNewContext(readFileSync(new URL('../../public/js/live-audio-worklet.js',import.meta.url),'utf8'),context);
 const processor=new Processor({processorOptions:{capture:true}});
 const input=new Float32Array(128).fill(0.25),output=new Float32Array(128).fill(1);
 for(let i=0;i<5;i++){output.fill(1);processor.process([[input]],[[output]]);assert.ok(output.every(value=>value===0));}
 assert.equal(sent.length,1);assert.ok(new Int16Array(sent[0]).every(value=>value===8192));
});
