export const coordinate = pair => Array.isArray(pair) && pair.length >= 2 && pair.every(Number.isFinite)
    && Math.abs(pair[0]) <= 180 && Math.abs(pair[1]) <= 90 ? {lng:pair[0],lat:pair[1]} : null;
export const duration = seconds => {
    const n = Math.max(0, Math.floor(Number(seconds) || 0));
    return [n >= 3600 ? `${Math.floor(n / 3600)} h` : '', n >= 60 ? `${Math.floor(n / 60) % 60} min` : '', `${n % 60} s`].filter(Boolean).join(' ');
};
// Progress is expressed on a 0..1000 range; keep fractions for long trips.
export const playbackAdvance = (progress, elapsedMs, seconds, speed) => Math.min(1000,
    Math.max(0, Number(progress) || 0) + Math.max(0, elapsedMs) * speed / Math.max(1, Number(seconds) || 1));
export function createTripHistory({host, getMap, getMarker, labels: l, icon, onOpen, onClose}) {
    const node = (tag, text, cls) => { const n = document.createElement(tag); if (text != null) n.textContent = text; if (cls) n.className = cls; return n; };
    const button = (text, action, title = text) => {const b = node('button', text); b.type = 'button'; b.title = title; b.setAttribute('aria-label', title); b.addEventListener('click', action); return b;};
    const setIcon = (button, name, fallback) => { if(icon) button.replaceChildren(icon(name)); else button.textContent=fallback; };
    const panel = node('aside',null,'cam-trip-panel'); panel.hidden = true; panel.setAttribute('aria-label',l.trips_history);
    const head = node('header'), title = node('strong', l.trips_history), body = node('div',null,'cam-trip-body');
    const collapse = button('−',()=>{body.hidden = !body.hidden; setIcon(collapse,body.hidden?'plus':'minus',body.hidden?'+':'−'); collapse.setAttribute('aria-expanded',String(!body.hidden));},l.trips_collapse);
    collapse.setAttribute('aria-expanded','true');
    const closeButton=button('×',close,l.close);setIcon(collapse,'minus','−');setIcon(closeButton,'close','×');
    if(icon)head.append(icon('route'));head.append(title,collapse,closeButton);
    const form = node('form',null,'cam-trip-dates'), from = node('input'), to = node('input');
    for (const [input,label] of [[from,l.trips_from],[to,l.trips_to]]) {input.type='date';input.required=true;input.setAttribute('aria-label',label); const field=node('label',label);field.append(input);form.append(field);}
    const submit=node('button',l.trips_show);submit.type='submit';form.append(submit);
    const presets=node('div',null,'cam-trip-presets');
    const day = d => `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
    [[l.trips_today,0],[l.trips_yesterday,1],[l.trips_week,6]].forEach(([text,days])=>presets.append(button(text,()=>{const a=new Date(),b=new Date();a.setDate(a.getDate()-days);if(days===1)b.setDate(b.getDate()-1);from.value=day(a);to.value=day(b);void load();})));
    const tools=node('nav',null,'cam-trip-tools'), content=node('div',null,'cam-trip-content'), message=node('p',null,'cam-trip-message');message.setAttribute('role','status');
    const play=button('▶',togglePlay,l.trips_play), reset=button('↺',()=>{stop();progress.value=0;replayAt(0);},l.trips_reset), overview=button('☷',()=>{detailed=false;selectAll();render();},l.trips_global);
    const all=node('input');all.type='checkbox';all.setAttribute('aria-label',l.trips_all);all.addEventListener('change',()=>{selected=new Set(all.checked?data.trips.map(t=>t.id):[]);parking=null;draw();render();});
    const fitButton=button('⌖',()=>draw(),l.trips_fit);
    setIcon(play,'play','▶');setIcon(reset,'refresh','↺');setIcon(overview,'menu','☷');setIcon(fitButton,'target','⌖');
    const speed=node('select');speed.title=l.trips_speed;speed.setAttribute('aria-label',l.trips_speed);
    [1,2,4,8,16,32,64].forEach(value=>{const option=node('option','×'+value);option.value=String(value);speed.append(option);});speed.value='1';
    tools.append(play,reset,speed,overview,fitButton,all,node('span',l.trips_all,'cam-trip-all-label'));
    const progress=node('input');progress.type='range';progress.step='any';progress.min=0;progress.max=1000;progress.value=0;progress.setAttribute('aria-label',l.trips_progress);progress.addEventListener('input',()=>{stop();replayAt(Number(progress.value)/1000);});
    body.append(form,presets,tools,progress,message,content);panel.append(head,body);host.append(panel);
    let vehicle=null,request=null,data={trips:[],history:{items:[]}},selected=new Set(),parking=null,detailed=false,lines=[],pins=[],replay=null,timer=null;
    const visibleTrips=()=>data.trips.filter(t=>selected.has(t.id));
    function stop(){clearInterval(timer);timer=null;setIcon(play,'play','▶');}
    function clear(){stop();lines.forEach(p=>p.setMap(null));pins.forEach(p=>p.map=null);lines=[];pins=[];if(replay)replay.map=null;replay=null;}
    function pin(point,text,color,titleText){if(!point)return null;const el=node('span',text,'cam-trip-pin');el.style.setProperty('--trip-color',color);const p=new (getMarker())({map:getMap(),position:point,title:titleText,anchorLeft:'-50%',anchorTop:'-100%'});p.append(el);pins.push(p);return p;}
    function draw(){
        clear();progress.value=0;const map=getMap();if(!map)return;
        const trips=visibleTrips(),bounds=new google.maps.LatLngBounds();
        for(const trip of trips){const path=trip.coordinates.map(coordinate).filter(Boolean);if(!path.length)continue;path.forEach(p=>bounds.extend(p));const line=new google.maps.Polyline({map,path,strokeColor:trip.color,strokeWeight:4,icons:[{icon:{path:google.maps.SymbolPath.FORWARD_OPEN_ARROW,strokeColor:'#fff',strokeWeight:2,scale:2},offset:'35px',repeat:'100px'}]});line.addListener('click',()=>selectTrip(trip));lines.push(line);}
        if(parking){const p=coordinate(parking.coordinates);if(p){pin(p,'P','#8a8a8a',parking.address);bounds.extend(p);}}
        else if(trips.length){pin(coordinate(trips[0].start_coordinates),'▷',trips[0].color,l.trips_start);pin(coordinate(trips.at(-1).end_coordinates),'🏁',trips.at(-1).color,l.trips_finish);}
        if(!bounds.isEmpty()){const width=host.clientWidth;map.fitBounds(bounds,{left:width>700?480:30,right:30,top:30,bottom:width>700?40:host.clientHeight*.7});google.maps.event.addListenerOnce(map,'idle',()=>{if(map.getZoom()>18)map.setZoom(18);});}
        const enabled=trips.length===1&&!parking;play.disabled=reset.disabled=progress.disabled=speed.disabled=!enabled;
    }
    function replayAt(ratio){const trip=visibleTrips()[0];if(!trip||parking)return;const path=trip.coordinates.map(coordinate).filter(Boolean);if(!path.length)return;const at=ratio*(path.length-1),i=Math.floor(at),a=path[i],b=path[Math.min(i+1,path.length-1)],t=at-i;const p={lat:a.lat+(b.lat-a.lat)*t,lng:a.lng+(b.lng-a.lng)*t};if(!replay)replay=pin(p,'▶',trip.color,l.trips_play);else replay.position=p;}
    function togglePlay(){
        if(timer){stop();return;}
        const trip=visibleTrips()[0];if(!trip||parking)return;
        if(Number(progress.value)>=1000)progress.value=0;
        play.replaceChildren();play.textContent='Ⅱ';replayAt(Number(progress.value)/1000);
        let last=performance.now();
        timer=setInterval(()=>{const now=performance.now();progress.value=playbackAdvance(progress.value,now-last,trip.duration_seconds,Number(speed.value));last=now;replayAt(Number(progress.value)/1000);if(Number(progress.value)>=1000)stop();},100);
    }
    function selectTrip(trip){selected=new Set([trip.id]);parking=null;detailed=true;draw();render();}
    function selectAll(){selected=new Set(data.trips.map(t=>t.id));parking=null;draw();}
    function endpoint(time,address,date){const row=node('div',null,'cam-trip-endpoint');const clock=node('span',time);clock.append(node('small',date));row.append(clock,node('i'),node('span',address));return row;}
    function render(){
        content.replaceChildren();all.checked=data.trips.length>0&&selected.size===data.trips.length;all.indeterminate=selected.size>0&&selected.size<data.trips.length;
        if(!data.history.items.length){content.append(node('p',l.history_empty));return;}
        if(!detailed){const box=button('',()=>{detailed=true;render();});box.className='cam-trip-overview';const first=data.history.first,last=data.history.last;box.append(node('strong',vehicle.name),endpoint(first.start_time,first.start_address||first.address,first.date),node('p',`${data.summary.distance_km} km · ${duration(data.history.elapsed_seconds)}`),endpoint(last.end_time,last.end_address||last.address,last.end_date),node('p',`${data.summary.count} ${l.trips_trips} · ${data.history.parking_count} ${l.trips_parking} ›`));content.append(box);return;}
        let date='';
        for(const item of data.history.items){
            if(item.date!==date){date=item.date;content.append(node('div',date,'cam-trip-day'));}
            const row=node('div',null,'cam-trip-row');row.style.setProperty('--trip-color',item.color||'#b7b7b7');row.classList.toggle('is-selected',item.type==='trip'?selected.has(item.id):parking?.id===item.id);
            const symbol=node('span',item.type==='trip'?'↝':item.type==='parking'?'P':'Ⅱ','cam-trip-symbol');row.append(symbol);
            if(item.type==='trip'){
                const trip=data.trips.find(t=>t.id===item.id),check=node('input');check.type='checkbox';check.checked=selected.has(item.id);check.setAttribute('aria-label',`${l.trips_trip} ${item.index}`);check.addEventListener('change',()=>{if(check.checked)selected.add(item.id);else selected.delete(item.id);parking=null;draw();render();});row.append(check);
                const select=button('',()=>selectTrip(trip));select.append(endpoint(item.start_time,item.start_address,item.date),node('p',`${item.distance_km} km · ${duration(item.duration_seconds)}`),endpoint(item.end_time,item.end_address,item.end_date));row.append(select);
            }else{const select=button('',()=>{selected.clear();parking=item;draw();render();});select.append(node('strong',item.type==='parking'?l.trips_parking:l.stopped),node('p',item.address),node('small',`${item.start_time} – ${item.end_time} · ${duration(item.duration_seconds)}`));row.append(select);}
            content.append(row);
        }
    }
    async function load(){
        if(!vehicle||!form.reportValidity())return;request?.abort();request=new AbortController();const current=request;clear();data={trips:[],history:{items:[]}};content.replaceChildren();message.textContent=l.history_loading;tools.hidden=progress.hidden=true;submit.disabled=true;
        const timeout=setTimeout(()=>current.abort(),25000);
        try{const params=new URLSearchParams({source_id:vehicle.source_id,from:from.value,to:to.value,timezone:Intl.DateTimeFormat().resolvedOptions().timeZone||'UTC'});const response=await fetch(`${vehicle.trips_url}?${params}`,{headers:{Accept:'application/json'},credentials:'same-origin',cache:'no-store',signal:current.signal});if(!response.ok)throw Error(response.status===422?l.trips_period_limit:l.history_failed);const result=await response.json();if(request!==current)return;data=result;detailed=false;message.textContent='';tools.hidden=progress.hidden=false;selectAll();render();}catch(e){if(request===current)message.textContent=e.name==='AbortError'?l.history_failed:e.message;}finally{clearTimeout(timeout);if(request===current)submit.disabled=false;}
    }
    function close(){request?.abort();request=null;clear();panel.hidden=true;onClose?.();}
    form.addEventListener('submit',e=>{e.preventDefault();void load();});window.addEventListener('pagehide',close);
    return {get active(){return !panel.hidden;},close,open(v){vehicle=v;title.textContent=`${l.trips_history} · ${v.name}`;panel.hidden=false;body.hidden=false;setIcon(collapse,'minus','−');collapse.setAttribute('aria-expanded','true');from.max=to.max=from.value=to.value=day(new Date());onOpen?.();void load();}};
}
