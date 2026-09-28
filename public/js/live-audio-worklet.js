class VehicleAudioProcessor extends AudioWorkletProcessor {
    constructor(options) {
        super();this.capture=options.processorOptions?.capture===true;
        this.samples=[];this.offset=0;this.queued=0;this.playing=false;
        this.input=new Int16Array(640);this.inputAt=0;
        this.port.onmessage=event=>{
            if(this.capture)return;
            const pcm=new Int16Array(event.data),copy=new Float32Array(pcm.length);
            for(let i=0;i<pcm.length;i++)copy[i]=pcm[i]/32768;
            this.samples.push(copy);this.queued+=copy.length;
            // Bound latency after a network burst instead of accumulating old speech.
            while(this.queued>16000&&this.samples.length>1){this.queued-=this.samples[0].length-this.offset;this.samples.shift();this.offset=0;}
        };
    }
    process(inputs,outputs) {
        if(this.capture){
            for(const output of outputs)for(const channel of output)channel.fill(0);
            const input=inputs[0]?.[0];
            if(input)for(const value of input){
                this.input[this.inputAt++]=Math.round(Math.max(-1,Math.min(1,value))*(value<0?32768:32767));
                if(this.inputAt===this.input.length){this.port.postMessage(this.input.buffer,[this.input.buffer]);this.input=new Int16Array(640);this.inputAt=0;}
            }
        }else{
            const output=outputs[0]?.[0];if(!output)return true;
            if(!this.playing&&this.queued>=3200)this.playing=true;
            for(let i=0;i<output.length;i++){
                if(!this.playing||!this.queued){output[i]=0;this.playing=false;continue;}
                output[i]=this.samples[0][this.offset++];this.queued--;
                if(this.offset===this.samples[0].length){this.samples.shift();this.offset=0;}
            }
        }
        return true;
    }
}
registerProcessor('vehicle-audio',VehicleAudioProcessor);
