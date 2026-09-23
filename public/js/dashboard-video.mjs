import {MapVideoChannel} from './map-video.mjs?v=live-reconnect-1';
import {attachLivePlayer, resetLivePlayer} from './live-player.mjs?v=live-reconnect-1';
import {DashboardVideoSelection} from './dashboard-video-session.mjs?v=dashboard-live-1';
import {audioControls} from './live-audio.mjs?v=audio-3';

const panel = document.getElementById('dashboard-camera-panel');
if (panel) {
    const config = JSON.parse(document.getElementById('dashboard-video-config').textContent);
    const select = document.getElementById('dashboard-camera-vehicle'), labels = config.labels;
    const vehicleAudio = audioControls(document.getElementById('dashboard-camera-audio'));
    const vehicles = new Map(config.vehicles.map(vehicle => [String(vehicle.id),vehicle]));
    const selected = () => vehicles.get(select.value);
    const active = () => document.body.dataset.view === 'overview';
    const request = async (url, data, keepalive = false) => {
        const controller = new AbortController(), timeout = setTimeout(() => controller.abort(),15000);
        try {
            const response = await fetch(url,{method:'POST',credentials:'same-origin',keepalive,signal:controller.signal,
                headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify(data)});
            if (response.redirected || !response.ok) throw Object.assign(Error('Video unavailable'),{status:response.redirected ? 401 : response.status});
            return await response.json();
        } finally { clearTimeout(timeout); }
    };
    const channels = [...panel.querySelectorAll('[data-dashboard-channel]')].map(section => {
        const channel = Number(section.dataset.dashboardChannel), player = section.querySelector('video');
        const placeholder = section.querySelector('.dashboard-camera-placeholder'), idle = section.querySelector('[data-idle]');
        const start = section.querySelector('[data-start]'), stop = section.querySelector('[data-stop]'), status = section.querySelector('[data-status]');
        let playback = null, manualPaused = false;
        const eligible = () => selected() && channel <= selected().channels && active();
        const session = new MapVideoChannel({request,
            reset({preserveFrame = false} = {}) {
                const still = resetLivePlayer(player,playback,{preserveFrame}); playback = null;
                if (!preserveFrame) manualPaused = false;
                placeholder.hidden = still; start.hidden = preserveFrame || !eligible(); stop.hidden = !preserveFrame;
                idle.hidden = Boolean(eligible());
                idle.textContent = selected() ? labels.missing : labels.select_first;
            },
            notify(state) {
                status.textContent = labels[state]; start.hidden = state !== 'failed'; stop.hidden = state === 'failed';
            },
            attach(url,onError) {
                playback = attachLivePlayer(player,url,{
                    shouldPlay:()=>!manualPaused,
                    onState(state) {
                        if (state === 'paused') manualPaused = true;
                        if (state === 'ready') { manualPaused = false; session.markPlaying(); }
                        if (['preview','ready','paused','play_required'].includes(state)) placeholder.hidden = true;
                        if (!['preview','buffering'].includes(state)) status.textContent = labels[state];
                    },onError,
                });
            },
        });
        start.addEventListener('click',()=>{if(eligible()) void session.start(`${config.baseUrl}/${selected().device_id}/live`,channel);});
        const showIdle = () => { status.textContent = !selected() ? '' : eligible() ? labels.idle : labels.missing; };
        stop.addEventListener('click',()=>{void session.stop(); showIdle();});
        return {channel,session,showIdle};
    });
    const selection = new DashboardVideoSelection(channels,config.baseUrl);
    const info = document.createElement('p'); info.className = 'dashboard-camera-selection';
    select.closest('.dashboard-camera-body').append(info);
    const updateInfo = () => {
        info.replaceChildren(); const vehicle = selected(); if(!vehicle) return;
        const status = document.createElement('span'); status.className='dashboard-connection'; status.dataset.tone=vehicle.connection; status.textContent=vehicle.status;
        const details = document.createElement('span'); details.textContent=vehicle.model;
        info.append(status,details);
    };
    document.addEventListener('exadcam:dashboard-data',event=>{
        const previous=selected(), chosen=select.value, options=event.detail.video;
        vehicles.clear(); options.forEach(v=>vehicles.set(String(v.id),v));
        const first=select.options[0].cloneNode(true);
        select.replaceChildren(first,...options.map(v=>{const option=new Option(v.label,String(v.id));option.dataset.status=v.status;option.dataset.statusTone=v.connection;return option;}));
        select.disabled=!options.length; select.value=vehicles.has(chosen)?chosen:'';
        select.dispatchEvent(new Event('searchable-select:refresh')); updateInfo();
        if(previous && (!selected() || previous.device_id!==selected().device_id || previous.channels!==selected().channels)) select.dispatchEvent(new Event('change'));
    });
    document.addEventListener('exadcam:select-dashboard-video',event=>{
        if(!active() || !vehicles.has(String(event.detail.id))) return;
        select.value=String(event.detail.id); select.dispatchEvent(new Event('change')); select.dispatchEvent(new Event('searchable-select:refresh'));
    });
    select.addEventListener('change',()=>{
        updateInfo();
        const vehicle = active() ? selected() : null;
        void vehicleAudio.select(vehicle?.device_id);
        panel.dataset.cameraModel = vehicle?.model || '';
        channels.forEach(({showIdle})=>showIdle());
        void selection.choose(vehicle);
    });
    const clear = (keepalive = false) => {
        void vehicleAudio.select(null,keepalive);
        select.value = ''; select.dispatchEvent(new Event('searchable-select:refresh'));
        delete panel.dataset.cameraModel; info.replaceChildren();
        void selection.clear(keepalive);
        channels.forEach(({showIdle})=>showIdle());
    };
    document.addEventListener('exadcam:view-changed',event=>{if(event.detail.view !== 'overview') clear();});
    window.addEventListener('pagehide',()=>clear(true));
}
