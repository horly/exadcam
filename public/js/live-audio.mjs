import {LiveAudioSession} from './live-audio-session.mjs?v=audio-3';

let activeControls=null;
const config=()=>JSON.parse(document.getElementById('live-audio-config').textContent);
async function createGraph() {
    const AudioContext=window.AudioContext||window.webkitAudioContext;
    if(!AudioContext||!window.AudioWorkletNode)throw Object.assign(Error('Unsupported browser'),{name:'NotSupportedError'});
    const context=new AudioContext({sampleRate:16000,latencyHint:'interactive'});
    let timeout;
    try{
        if(context.sampleRate!==16000)throw Error('Unsupported sample rate');
        const resumed=context.resume();
        await Promise.race([
            Promise.all([context.audioWorklet.addModule(new URL('./live-audio-worklet.js?v=audio-3',import.meta.url)),resumed]),
            new Promise((_,reject)=>{timeout=setTimeout(()=>reject(Object.assign(Error('Audio output unavailable'),{name:'NotSupportedError'})),8000);}),
        ]);
        clearTimeout(timeout);
        const output=new AudioWorkletNode(context,'vehicle-audio',{numberOfInputs:0,numberOfOutputs:1,outputChannelCount:[1]});
        const volume=context.createGain();output.connect(volume).connect(context.destination);
        let source,input,closed=false;
        return {
            play(buffer){if(!closed)output.port.postMessage(buffer,[buffer]);},
            volume(value){volume.gain.value=Math.max(0,Math.min(1,value));},
            capture(stream,send){
                if(closed||input)return;
                input=new AudioWorkletNode(context,'vehicle-audio',{numberOfInputs:1,numberOfOutputs:1,outputChannelCount:[1],processorOptions:{capture:true}});
                source=context.createMediaStreamSource(stream);source.connect(input).connect(context.destination);
                input.port.onmessage=event=>{if(!closed)send(event.data);};
            },
            close(){if(closed)return;closed=true;source?.disconnect();input?.disconnect();output.disconnect();volume.disconnect();void context.close().catch(()=>{});},
        };
    }catch(error){clearTimeout(timeout);void context.close().catch(()=>{});throw error;}
}

export function audioControls(element) {
    if(!element)return {select(){},stop(){return Promise.resolve();}};
    const settings=config(),labels=settings.labels;
    const listen=element.querySelector('[data-audio-listen]'),talk=element.querySelector('[data-audio-talk]'),stop=element.querySelector('[data-audio-stop]');
    const status=element.querySelector('[data-audio-status]'),volume=element.querySelector('[data-audio-volume]');
    let device=null;
    const request=async(url,data,keepalive=false)=>{
        const controller=new AbortController(),timeout=setTimeout(()=>controller.abort(),15000);
        try{
            const response=await fetch(url,{method:'POST',credentials:'same-origin',keepalive,signal:controller.signal,headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify(data)});
            const json=await response.json().catch(()=>({}));
            if(response.redirected||!response.ok)throw Object.assign(Error(json.message||labels.unavailable),{status:response.redirected?401:response.status});
            return json;
        }finally{clearTimeout(timeout);}
    };
    const session=new LiveAudioSession({request,createGraph,
        microphone:()=>{
            if(!navigator.mediaDevices?.getUserMedia)throw Object.assign(Error('Unavailable'),{name:'NotSupportedError'});
            return navigator.mediaDevices.getUserMedia({audio:{channelCount:1,echoCancellation:true,noiseSuppression:true,autoGainControl:true},video:false});
        },
        openSocket:result=>{
            const url=new URL(result.socket_path,location.origin);url.protocol=location.protocol==='https:'?'wss:':'ws:';
            if(url.origin!==location.origin.replace(/^http/,'ws'))throw Error('Invalid audio destination');
            return new WebSocket(url.href,['exadcam-audio',`token.${result.token}`]);
        },
        notify(state,error){
            element.dataset.state=state;
            const busy=['permission','connecting','reconnecting','listening','talking'].includes(state);
            const pending=['permission','connecting','reconnecting'].includes(state);
            listen.disabled=!device||pending||state==='listening';if(talk)talk.disabled=!device||pending||state==='talking';
            listen.setAttribute('aria-pressed',String(state==='listening'));
            talk?.setAttribute('aria-pressed',String(state==='talking'));
            stop.hidden=!busy;volume.hidden=!['listening','talking'].includes(state);
            if(state==='failed')status.textContent=error?.name==='NotAllowedError'?labels.denied:error?.name==='NotFoundError'?labels.no_microphone:error?.name==='NotSupportedError'?labels.unsupported_browser:error?.status?error.message:labels.disconnected;
            else status.textContent=labels[state]||labels.idle;
            if(state==='listening'||state==='talking')session.setVolume(Number(volume.value));
        },
    });
    const controls={
        select(id,keepalive=false){if(String(id||'')===String(device||''))return Promise.resolve();device=id||null;const stopped=session.stop(keepalive);listen.disabled=!device;if(talk)talk.disabled=!device;status.textContent=labels[device?'idle':'select'];return stopped;},
        stop(keepalive=false){return session.stop(keepalive);},
    };
    const start=mode=>{
        if(!device)return;
        const waiting=activeControls&&activeControls!==controls?activeControls.stop():Promise.resolve();activeControls=controls;
        void session.start(`${settings.baseUrl}/${device}/audio`,mode,waiting);
    };
    listen.addEventListener('click',()=>start('listen'));talk?.addEventListener('click',()=>start('talk'));
    stop.addEventListener('click',()=>{void session.stop();});volume.addEventListener('input',()=>session.setVolume(Number(volume.value)));
    document.addEventListener('visibilitychange',()=>{if(document.hidden)void controls.stop(true);});
    window.addEventListener('pagehide',()=>{void controls.stop(true);});
    return controls;
}
