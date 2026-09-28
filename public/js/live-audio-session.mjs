export class LiveAudioSession {
    constructor({request,createGraph,microphone,openSocket,notify=()=>{},schedule=(fn,ms)=>setTimeout(fn,ms),cancel=id=>clearTimeout(id)}) {
        Object.assign(this,{request,createGraph,microphone,openSocket,notify,schedule,cancel});
        this.run=null;this.closing=Promise.resolve();this.retry=null;this.epoch=0;
    }
    async start(url,mode,waitFor=Promise.resolve()) {
        const stopping=Promise.all([this.stop(),waitFor]);
        const run={url,mode,cancelled:false};this.run=run;
        this.notify(mode==='talk'?'permission':'connecting');
        // Start browser permission/resume in the click's user activation, before HTTP.
        const graph=Promise.resolve().then(()=>this.createGraph()).then(value=>{
            run.graph=value;if(run.cancelled)value.close();return value;
        });
        const microphone=mode==='talk'?Promise.resolve().then(()=>this.microphone()).then(stream=>{
            run.microphone=stream;
            if(run.cancelled)stream.getTracks().forEach(track=>track.stop());
            else stream.getTracks().forEach(track=>track.addEventListener('ended',()=>this.fail(run,Object.assign(Error('Microphone ended'),{name:'NotFoundError'}))));
            return stream;
        }):Promise.resolve(null);
        try{
            await Promise.all([stopping,graph,microphone]);
            if(this.run!==run)return;
            this.notify('connecting');
            run.creating=this.request(url,{mode}).then(result=>{run.lease=result.lease_id;return result;});
            const result=await run.creating;
            if(this.run!==run){await this.release(run);return;}
            run.socket=this.openSocket(result);
            run.socket.binaryType='arraybuffer';
            run.socket.onmessage=event=>{
                if(this.run!==run)return;
                if(event.data instanceof ArrayBuffer){if(mode==='listen')run.graph.play(event.data);return;}
                try{
                    const message=JSON.parse(event.data);
                    if(message.state==='ready'&&!run.ready){
                        run.ready=true;
                        if(mode==='listen')this.cancel(run.timeout);
                        if(mode==='talk')run.graph.capture(run.microphone,data=>{
                            if(this.run!==run||run.socket.readyState!==1)return;
                            if(run.socket.bufferedAmount>16000){this.fail(run,Error('Audio congested'));return;}
                            run.socket.send(data);
                        });
                        this.notify(mode==='talk'?'microphone':'listening');
                    }
                    // The incoming camera audio alone does not confirm a microphone return.
                    if(message.state==='transmitting'&&mode==='talk'&&run.ready&&!run.transmitting){
                        this.cancel(run.timeout);run.transmitting=true;this.notify('talking');
                    }
                    if(message.state==='closed')this.fail(run,Error('Audio disconnected'));
                }catch(error){this.fail(run,error);}
            };
            run.socket.onerror=()=>this.fail(run,Error('Audio unavailable'));
            run.socket.onclose=()=>this.fail(run,Error('Audio disconnected'));
            run.timeout=this.schedule(()=>this.fail(run,Error('Audio timeout')),45000);
            const heartbeat=async()=>{
                if(this.run!==run)return;
                try{await this.request(`${url}/${run.lease}/keepalive`,{});if(this.run===run)run.heartbeat=this.schedule(heartbeat,6000);}
                catch(error){this.fail(run,error);}
            };
            run.heartbeat=this.schedule(heartbeat,3000);
        }catch(error){if(this.run===run)this.fail(run,error);}
    }
    fail(run,error) {
        if(this.run!==run)return;
        void this.stop();
        if(run.mode==='listen'&&![401,403,404,422,429].includes(error?.status)&&!['NotAllowedError','NotSupportedError'].includes(error?.name)){
            const epoch=this.epoch;this.notify('reconnecting');
            this.retry=this.schedule(()=>{this.retry=null;if(this.epoch===epoch)void this.start(run.url,'listen');},5000);
        }else this.notify('failed',error);
    }
    release(run,keepalive=false) {
        if(!run.lease)return Promise.resolve();
        const lease=run.lease;run.lease=null;
        return this.request(`${run.url}/${lease}/stop`,{},keepalive).catch(()=>{});
    }
    stop(keepalive=false) {
        const retrying=this.retry!==null;this.cancel(this.retry);this.retry=null;this.epoch++;
        const run=this.run;this.run=null;
        if(!run){if(retrying)this.notify('idle');return this.closing;}
        run.cancelled=true;this.cancel(run.timeout);this.cancel(run.heartbeat);
        run.microphone?.getTracks().forEach(track=>track.stop());
        run.graph?.close();
        if(run.socket){run.socket.onmessage=null;run.socket.onclose=null;run.socket.onerror=null;run.socket.close();}
        const release=run.creating?run.creating.then(()=>this.release(run,keepalive)).catch(()=>{}):this.release(run,keepalive);
        this.closing=Promise.allSettled([this.closing,release]);
        this.notify('idle');return this.closing;
    }
    setVolume(value){this.run?.graph?.volume(value);}
}
