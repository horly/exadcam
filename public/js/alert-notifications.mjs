import {playAlertTone} from './alert-sound.mjs?v=cam-alerts-map-20261008';

const configNode = document.getElementById('cam-notification-config');
if (configNode) startNotifications(JSON.parse(configNode.textContent));

export function startNotifications(config) {
    const labels = config.labels, stack = document.getElementById('cam-alert-toasts');
    const toggle = document.getElementById('cam-alert-sound'), preview = document.getElementById('cam-alert-sound-test');
    const status = document.getElementById('cam-alert-sound-status');
    const key = 'exadcam:notifications:v1:'+config.user, soundKey = key+':sound';
    const read = name => { try { return JSON.parse(localStorage.getItem(name)); } catch { return null; } };
    const write = (name,value) => { try { localStorage.setItem(name,JSON.stringify(value)); } catch { /* In-memory operation still works. */ } };
    let soundEnabled = read(soundKey) === true, audio, busy = false, stopped = false, timer, cursor = null;
    let pending = [], active = 0, lastSound = -Infinity, more = false;
    const formatDate = value => new Intl.DateTimeFormat(config.locale,{dateStyle:'short',timeStyle:'short'}).format(new Date(value));
    function updateSound() {
        toggle.checked = soundEnabled;
        status.textContent = soundEnabled && audio?.state !== 'running' ? labels.unlock : labels.browser;
    }
    async function unlock() {
        if (!soundEnabled) return false;
        try {
            const Context = window.AudioContext || window.webkitAudioContext;
            if (!Context) throw Error('Audio unavailable');
            audio ??= new Context();
            if (audio.state === 'suspended') await audio.resume();
            updateSound(); return audio.state === 'running';
        } catch { status.textContent = labels.unavailable; return false; }
    }
    toggle.addEventListener('change',async () => {
        soundEnabled = toggle.checked; write(soundKey,soundEnabled); updateSound();
        if (soundEnabled && await unlock()) playAlertTone(audio);
    });
    preview.addEventListener('click',async () => {
        // Preview is also an explicit user gesture; it need not enable future alerts.
        try {
            const Context = window.AudioContext || window.webkitAudioContext;
            if (!Context) throw Error('Audio unavailable');
            audio ??= new Context(); await audio.resume();
            playAlertTone(audio); updateSound();
        } catch { status.textContent = labels.unavailable; }
    });
    document.addEventListener('pointerdown',() => { if (soundEnabled && audio?.state !== 'running') void unlock(); },{passive:true});
    document.addEventListener('keydown',() => { if (soundEnabled && audio?.state !== 'running') void unlock(); });
    window.addEventListener('storage',event => { if (event.key === soundKey) { soundEnabled = read(soundKey) === true; updateSound(); } });
    function make(tag,text,className) {
        const node = document.createElement(tag);
        if (text !== undefined) node.textContent = text;
        if (className) node.className = className;
        return node;
    }
    function drain() {
        while (active < 3 && pending.length && !document.hidden) {
            const event = pending.shift(); active++;
            const node = make('article',undefined,'toast cam-alert-toast');
            node.setAttribute('role','status'); node.setAttribute('aria-live','polite'); node.setAttribute('aria-atomic','true');
            const header = make('div',undefined,'toast-header');
            header.append(make('span','!','cam-alert-toast-icon'),make('strong',event.title));
            const close = make('button',undefined,'btn-close'); close.type = 'button'; close.setAttribute('aria-label',labels.close);
            header.append(close);
            const body = make('div',undefined,'toast-body'), link = make('a',labels.open,'cam-alert-toast-link'); link.href='#alerts';
            body.append(make('strong',[event.vehicle,event.registration].filter(Boolean).join(' · ')),make('p',event.description));
            const date = make('time',formatDate(event.at)); date.dateTime=event.at;
            body.append(date,link); node.append(header,body); stack.append(node);
            const toast = new window.bootstrap.Toast(node,{autohide:true,delay:8000});
            close.addEventListener('click',() => toast.hide());
            link.addEventListener('click',() => toast.hide());
            node.addEventListener('hidden.bs.toast',() => { toast.dispose(); node.remove(); active--; drain(); },{once:true});
            // Keep an actionable toast available while the user reads or navigates it.
            toast.show();
        }
    }
    async function poll() {
        const saved = read(key);
        if (saved?.cursor && Date.now()-saved.at < 120000) cursor = saved.cursor;
        const controller = new AbortController(), timeout = setTimeout(() => controller.abort(),12000);
        try {
            const url = config.url + (cursor ? '?'+new URLSearchParams(cursor) : '');
            const response = await fetch(url,{headers:{Accept:'application/json'},credentials:'same-origin',cache:'no-store',signal:controller.signal});
            if (response.redirected || [401,403].includes(response.status)) { stopped = true; pending=[]; stack.replaceChildren(); return; }
            if (response.status === 422) { cursor=null; write(key,null); return; }
            if (!response.ok) return;
            const result = await response.json();
            cursor=result.cursor; write(key,{cursor,at:Date.now()}); more=result.has_more;
            document.querySelectorAll('[data-alert-count]').forEach(node => { node.textContent=result.total; node.hidden=result.total===0; });
            pending.push(...result.data); drain();
            if (result.data.length && soundEnabled && Date.now()-lastSound > 1500 && playAlertTone(audio)) lastSound=Date.now();
            if (result.data.length) document.dispatchEvent(new CustomEvent('exadcam:alerts-received'));
        } finally { clearTimeout(timeout); }
    }
    async function refresh() {
        clearTimeout(timer);
        if (busy || stopped || document.hidden) return;
        busy=true; more=false;
        try {
            if (navigator.locks?.request) await navigator.locks.request(key,{ifAvailable:true},lock => lock ? poll() : undefined);
            else await poll();
        } catch { /* Retry without advancing the last received cursor. */ }
        finally { busy=false; if (!stopped && !document.hidden) timer=setTimeout(refresh,more ? 2500 : 15000); }
    }
    document.addEventListener('visibilitychange',() => { if (document.hidden) clearTimeout(timer); else { drain(); void refresh(); } });
    window.addEventListener('pagehide',() => { stopped=true; clearTimeout(timer); });
    window.addEventListener('pageshow',event => { if (event.persisted) { stopped=false; void refresh(); } });
    updateSound(); void refresh();
}
