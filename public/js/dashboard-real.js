(() => {
    'use strict';
    const configNode = document.getElementById('dashboard-real-config');
    if (!configNode) return;
    const config = JSON.parse(configNode.textContent), labels = config.labels;
    let data = config.data, fleetPage = 1, fleetStatus = 'all', alertPage = 1, timer, loading = false, alertRequest = 0;
    const el = id => document.getElementById(id);
    const make = (tag, text, cls) => { const n = document.createElement(tag); if (text != null) n.textContent = text; if (cls) n.className = cls; return n; };
    const normalize = s => String(s || '').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase();
    const date = value => value ? new Intl.DateTimeFormat(config.locale, {dateStyle:'short',timeStyle:'medium',timeZone:'Africa/Kinshasa'}).format(new Date(value)) : '—';
    const active = () => !document.hidden && ['overview','fleet','alerts'].includes(document.body.dataset.view);
    function pages(target, current, last, change) {
        const signature = `${current}/${last}`;
        if (target.dataset.signature === signature) return;
        target.dataset.signature = signature; target.replaceChildren();
        const button = (caption, page, disabled = false) => {
            const b = make('button',caption); b.type = 'button'; b.disabled = disabled;
            if (Number(caption) === current) b.setAttribute('aria-current','page');
            b.addEventListener('click',()=>change(page)); target.append(b);
        };
        button('‹',current-1,current === 1); target.lastChild.setAttribute('aria-label',labels.previous);
        const numbers = [...new Set([1, current-1, current, current+1, last])].filter(n=>n>=1 && n<=last).sort((a,b)=>a-b);
        numbers.forEach((n,i)=>{ if(i && n-numbers[i-1]>1) target.append(make('span','…')); button(String(n),n); });
        button('›',current+1,current === last); target.lastChild.setAttribute('aria-label',labels.next);
    }
    function summary(total, page, size) { return `${labels.from} ${total ? (page-1)*size+1 : 0}–${Math.min(page*size,total)} ${labels.of} ${total}`; }
    function renderFleet() {
        const query = normalize(el('fleet-search').value);
        const rows = data.vehicles.filter(v => (fleetStatus === 'all' || (fleetStatus === 'online' ? v.online : !v.online)) && normalize([v.name,v.registration,v.fleet,v.status,v.model].join(' ')).includes(query));
        const last = Math.max(1,Math.ceil(rows.length/10)); fleetPage = Math.min(fleetPage,last);
        const shown = rows.slice((fleetPage-1)*10,fleetPage*10), body = el('dashboard-vehicles');
        const signature = JSON.stringify(shown);
        if (body.dataset.signature !== signature) {
            body.dataset.signature = signature;
            body.replaceChildren(...shown.map(v => {
                const row = make('tr'), identity = make('td'); identity.append(make('strong',v.name),make('small',[v.registration,v.model].filter(Boolean).join(' · ')));
                const state = make('td'), badge = make('span',v.status,'dashboard-connection'); badge.dataset.tone = v.connection; state.append(badge);
                if(v.motion && v.motion !== v.status) state.append(make('small',v.motion));
                row.append(identity,make('td',v.fleet),state,make('td', data.can_map ? (v.speed == null ? '—' : `${v.speed.toLocaleString(config.locale)} km/h`) : date(v.last_seen_at)));
                if(data.can_video) {
                    const cell = make('td');
                    if(v.device_id) { const button=make('button',labels.video,'dashboard-video-link'); button.type='button'; button.addEventListener('click',()=>{
                        location.hash='overview'; requestAnimationFrame(()=> { document.dispatchEvent(new CustomEvent('exadcam:select-dashboard-video',{detail:{id:v.id}})); el('dashboard-camera-panel')?.scrollIntoView({block:'center',behavior:'smooth'}); });
                    }); cell.append(button); } else cell.textContent='—';
                    row.append(cell);
                }
                return row;
            }));
        }
        el('dashboard-fleet-empty').hidden = rows.length !== 0;
        el('dashboard-fleet-summary').textContent = summary(rows.length,fleetPage,10);
        el('dashboard-vehicle-count').textContent = data.vehicles.length;
        pages(el('dashboard-fleet-pages'),fleetPage,last,p=>{fleetPage=p;renderFleet();});
    }
    function renderAlerts(result, overview = false) {
        const list = el('dashboard-alerts'); if(!list) return;
        const signature = JSON.stringify(result.data);
        if(list.dataset.signature !== signature) {
            list.dataset.signature = signature;
            list.replaceChildren(...result.data.map(event => {
                const article = make('article',null,'dashboard-alert'), symbol = make('span',event.kind === 'connection' ? '○' : '!','dashboard-alert-symbol'); symbol.dataset.kind=event.kind; symbol.setAttribute('aria-hidden','true');
                const copy = make('div'), time = make('time',`${event.kind === 'connection' ? labels.contact+' · ' : ''}${date(event.at)}`); time.dateTime = event.at;
                copy.append(make('strong',event.title),make('p',[event.vehicle,event.registration,event.fleet].filter(Boolean).join(' · ')),time);
                const details=make('details'); details.append(make('summary',event.model),make('p',event.description)); copy.append(details); article.append(symbol,copy); return article;
            }));
            if(!result.data.length) list.append(make('p',labels.empty,'dashboard-empty'));
        }
        if(!overview) { alertPage=result.page; el('dashboard-alert-summary').textContent=summary(result.total,result.page,10); pages(el('dashboard-alert-pages'),result.page,result.last_page,p=>void loadAlerts(p)); }
    }
    async function get(url) {
        const control=new AbortController(), timeout=setTimeout(()=>control.abort(),12000);
        try {
            const response=await fetch(url,{headers:{Accept:'application/json'},credentials:'same-origin',cache:'no-store',signal:control.signal});
            if(response.redirected || response.status===401 || response.status===403) { location.reload(); throw Error('Access changed'); }
            if(!response.ok) throw Error('Unavailable');
            return await response.json();
        } finally { clearTimeout(timeout); }
    }
    async function loadAlerts(page=alertPage) {
        if(!data.can_map || document.body.dataset.view!=='alerts') return;
        const sequence=++alertRequest;
        el('dashboard-alert-error').hidden=true;
        el('dashboard-alerts').setAttribute('aria-busy','true');
        try { const result=await get(`${config.alertsUrl}?page=${page}`); if(sequence===alertRequest && document.body.dataset.view==='alerts') renderAlerts(result); }
        catch { if(sequence===alertRequest) { el('dashboard-alert-error').textContent=labels.alerts_failed; el('dashboard-alert-error').hidden=false; } }
        finally { if(sequence===alertRequest) el('dashboard-alerts').removeAttribute('aria-busy'); }
    }
    function render() {
        document.querySelectorAll('[data-metric]').forEach(n=>{n.textContent=data.metrics[n.dataset.metric];});
        document.querySelectorAll('[data-alert-count]').forEach(n=>{n.textContent=data.metrics.alerts; n.hidden=data.metrics.alerts===0;});
        el('dashboard-sync').textContent=`${labels.refresh} ${date(data.generated_at)} · Kinshasa`;
        renderFleet();
        if(document.body.dataset.view==='overview') renderAlerts(data.alerts,true);
    }
    async function refresh() {
        clearTimeout(timer); if(!active() || loading) return; loading=true;
        try {
            const next=await get(config.url);
            if(next.can_map!==data.can_map || next.can_video!==data.can_video) { location.reload(); return; }
            data=next; render(); document.dispatchEvent(new CustomEvent('exadcam:dashboard-data',{detail:data}));
            if(document.body.dataset.view==='alerts') await loadAlerts();
        } catch { el('dashboard-sync').textContent=labels.failed; }
        finally { loading=false; if(active()) timer=setTimeout(refresh,15000); }
    }
    el('fleet-search').addEventListener('input',()=>{fleetPage=1;renderFleet();});
    document.querySelectorAll('[data-fleet-status]').forEach(button=>button.addEventListener('click',()=>{
        fleetStatus=button.dataset.fleetStatus; fleetPage=1;
        document.querySelectorAll('[data-fleet-status]').forEach(n=>{ const chosen=n===button; n.classList.toggle('active',chosen); n.setAttribute('aria-pressed',String(chosen)); }); renderFleet();
    }));
    document.addEventListener('exadcam:alerts-received',() => { if(active()) void refresh(); });
    document.addEventListener('exadcam:view-changed',()=>{ alertRequest++; render(); void refresh(); });
    document.addEventListener('visibilitychange',()=>{if(active()) void refresh();else clearTimeout(timer);});
    window.addEventListener('pagehide',()=>clearTimeout(timer));
    render(); if(document.body.dataset.view==='alerts') void loadAlerts(); timer=setTimeout(refresh,15000);
})();
