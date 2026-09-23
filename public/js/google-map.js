(async () => {
    'use strict';
    const configNode = document.getElementById('google-map-data');
    if (!configNode) return;
    const config = JSON.parse(configNode.textContent), labels = config.labels;
    if (!config.allowed) return;
    const { movementPath, pointAlong, bearing, trailThroughPosition, preferredTrackingVehicle } = await import('./map-motion.mjs?v=map-anchor-1');
    const { MapVideoChannel } = await import('./map-video.mjs?v=live-reconnect-1');
    const { audioControls } = await import('./live-audio.mjs?v=audio-3');
    const {attachLivePlayer, resetLivePlayer} = await import('./live-player.mjs?v=live-reconnect-1');
    const element = id => document.getElementById(id);
    const vehicleAudio = audioControls(element('tracking-video-audio'));
    const workspace = element('tracking-workspace'), canvas = element('google-fleet-map');
    const fleetFilter = element('tracking-fleet'), departmentFilter = element('tracking-department'), stateFilter = element('tracking-state');
    const list = element('tracking-vehicle-list'), mapMessage = element('tracking-map-message'), feedMessage = element('tracking-feed-message');
    const auto = element('tracking-auto'), follow = element('tracking-follow'), showTrails = element('tracking-trails');
    const markers = new Map(), rows = new Map();
    const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
    let vehicles = [], selectedId = null, map, Marker, loading, timer, controller, requestSequence = 0, generation = null;
    let infoWindow, popupVehicleId = null, popupFields = null, popupSubtitle = null, popupDot = null, videoVehicle = null, videoGeneration = 0;
    let initialFit = false, fitOnOpen = false, failures = 0, refreshDelay = 10000, searchTimer;
    const normalize = value => String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase().trim();
    const active = () => !document.hidden && ['overview', 'map'].includes(document.body.dataset.view);
    const date = value => value ? new Intl.DateTimeFormat(config.locale, {dateStyle:'short',timeStyle:'medium'}).format(new Date(value)) : '—';
    function icon(name) {
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('class','icon'); svg.setAttribute('viewBox','0 0 24 24'); svg.setAttribute('fill','none');
        svg.setAttribute('stroke','currentColor'); svg.setAttribute('stroke-width','1.8'); svg.setAttribute('aria-hidden','true');
        const use = document.createElementNS('http://www.w3.org/2000/svg','use'); use.setAttribute('href',`${config.iconsUrl}#${name}`); svg.append(use); return svg;
    }
    function filtered() {
        const query = normalize(element('tracking-search').value);
        return vehicles.filter(vehicle => (!fleetFilter.value || String(vehicle.fleet.id) === fleetFilter.value)
            && (!departmentFilter.value || (departmentFilter.value === 'none' ? !vehicle.department : String(vehicle.department?.id) === departmentFilter.value))
            && (!stateFilter.value || vehicle.state === stateFilter.value)
            && normalize([vehicle.name,vehicle.registration,vehicle.fleet.name,vehicle.department?.name,vehicle.camera_name,vehicle.equipment?.imei,vehicle.equipment?.model].filter(Boolean).join(' ')).includes(query));
    }
    function displayed() {
        const matches = filtered();
        return document.body.dataset.view === 'overview' || element('tracking-show-all').checked ? matches : matches.filter(v => v.id === selectedId);
    }
    function replaceOptions(select, options) {
        const signature = JSON.stringify(options);
        if (select.dataset.optionsSignature === signature) return;
        const value = select.value;
        select.replaceChildren(...options.map(([id,name]) => new Option(name,id)));
        select.value = options.some(([id]) => String(id) === value) ? value : '';
        select.dataset.optionsSignature = signature;
        select.dispatchEvent(new Event('searchable-select:refresh'));
    }
    function updateFilters() {
        const fleets = new Map(vehicles.map(vehicle => [String(vehicle.fleet.id),vehicle.fleet.name]));
        replaceOptions(fleetFilter, [['',labels.all_fleets],...fleets.entries()].sort((a,b) => a[0] && b[0] ? a[1].localeCompare(b[1],config.locale) : 0));
        const departments = new Map(vehicles.filter(vehicle => vehicle.department && (!fleetFilter.value || String(vehicle.fleet.id) === fleetFilter.value)).map(vehicle => [String(vehicle.department.id), vehicle.department.name]));
        element('tracking-department-filter').hidden = departments.size === 0;
        replaceOptions(departmentFilter, [['',labels.all_departments],['none',labels.none_department],...departments.entries()]);
    }
    function stopAnimations() {
        markers.forEach(item => {
            if (item.animation) cancelAnimationFrame(item.animation);
            item.animation = null; item.path = [];
            if (item.vehicle?.position) {
                item.displayed = item.vehicle.position; item.marker.position = item.displayed;
                drawTrail(item);
                const route = item.vehicle.trail || [];
                if (item.vehicle.state === 'moving' && route.length > 1) item.symbol.style.setProperty('--heading',bearing(route.at(-2),route.at(-1))+'deg');
                if (popupVehicleId === item.vehicle.id) infoWindow.setPosition(item.displayed);
            }
        });
    }
    function drawTrail(item) {
        item.trail.setPath(showTrails.checked ? trailThroughPosition(item.vehicle,item.displayed,item.path) : []);
    }
    function choose(id, center = true, popup = false) {
        if (selectedId !== id) void closeVideo();
        selectedId = id;
        if (id !== null) { element('tracking-show-all').checked = false; follow.checked = true; }
        if (!popup) { infoWindow?.close(); popupVehicleId = null; }
        render(false);
        const vehicle = vehicles.find(item => item.id === id);
        if (center && map && vehicle?.position) { fitFleet(); }
        if (popup && vehicle?.position) openPopup(vehicle);
    }
    function glyph(vehicle) {
        const symbol = document.createElement('span'); symbol.className = 'tracking-glyph';
        symbol.dataset.state = vehicle.state; symbol.textContent = vehicle.state === 'parking' ? 'P' : '';
        return symbol;
    }
    function rowFor(vehicle) {
        let row = rows.get(vehicle.id);
        if (!row) {
            const button = document.createElement('button'), symbol = document.createElement('span'), copy = document.createElement('span');
            const name = document.createElement('strong'), subtitle = document.createElement('small'), state = document.createElement('span');
            const dot = document.createElement('i'), status = document.createElement('span');
            button.type = 'button'; button.className = 'tracking-vehicle'; symbol.className = 'vehicle-symbol'; copy.className = 'vehicle-copy';
            symbol.append(glyph(vehicle)); state.className = 'vehicle-state'; state.append(dot,status); copy.append(name,subtitle,state); button.append(symbol,copy);
            button.addEventListener('click', () => choose(vehicle.id));
            row = {button,name,subtitle,dot,status,symbol}; rows.set(vehicle.id,row);
        }
        row.symbol.replaceChildren(glyph(vehicle));
        row.button.setAttribute('aria-pressed', String(selectedId === vehicle.id)); row.name.textContent = vehicle.name;
        row.subtitle.textContent = [vehicle.registration,vehicle.equipment?.imei,vehicle.fleet.name,vehicle.department?.name].filter(Boolean).join(' · ');
        row.dot.className = `tracking-dot ${vehicle.state}`;
        row.status.textContent = labels[vehicle.state] + (vehicle.position ? ` · ${vehicle.position.speed.toLocaleString(config.locale)} ${labels.kmh}` : '');
        return row.button;
    }
    function fillFields(container, fields) {
        container.replaceChildren(...fields.flatMap(([title,value]) => {
            const term = document.createElement('dt'), description = document.createElement('dd');
            term.textContent = title; description.textContent = value ?? '—'; return [term,description];
        }));
    }
    function relativeDate(value) {
        if (!value || !Number.isFinite(Date.parse(value))) return '—';
        const seconds = Math.min(0, Math.round((Date.parse(value) - Date.now()) / 1000));
        const [amount, unit] = Math.abs(seconds) < 60 ? [seconds, 'second'] : Math.abs(seconds) < 3600 ? [Math.round(seconds / 60), 'minute'] : Math.abs(seconds) < 86400 ? [Math.round(seconds / 3600), 'hour'] : [Math.round(seconds / 86400), 'day'];
        return new Intl.RelativeTimeFormat(config.locale, {numeric:'auto'}).format(amount, unit);
    }
    function connectionDot(online) {
        const dot = document.createElement('span'); dot.className = 'tracking-connection-dot';
        dot.dataset.online = String(Boolean(online)); dot.setAttribute('aria-hidden','true'); return dot;
    }
    function updatePopup(vehicle) {
        if (!vehicle || popupVehicleId !== vehicle.id || !popupFields) return;
        popupSubtitle.textContent = [vehicle.online ? labels.online : labels.offline,vehicle.equipment?.imei].filter(Boolean).join(' · ');
        popupDot.dataset.online = String(Boolean(vehicle.online));
        const fields = [[labels.registration,vehicle.registration], [labels.camera,vehicle.equipment?.model || vehicle.camera_name],
            [labels.fleet,vehicle.fleet.name], [labels.speed,vehicle.position.speed+' '+labels.kmh], [labels.contact_date,relativeDate(vehicle.last_seen_at)]];
        fillFields(popupFields, fields);
        popupFields.lastElementChild.title = date(vehicle.last_seen_at);
    }
    function openPopup(vehicle) {
        const content = document.createElement('div'); content.className = 'tracking-popup';
        const header = document.createElement('header'), identity = document.createElement('div');
        const title = document.createElement('strong'); title.textContent = vehicle.name;
        popupDot = connectionDot(vehicle.online); popupSubtitle = document.createElement('p'); popupFields = document.createElement('dl');
        identity.append(title,popupSubtitle);
        const close = document.createElement('button'); close.type = 'button'; close.className = 'tracking-popup-close'; close.setAttribute('aria-label',labels.close_details); close.append(icon('close'));
        close.addEventListener('click',() => { infoWindow.close(); popupVehicleId = null; });
        header.append(popupDot,identity,close);
        const actions = document.createElement('div'); actions.className = 'tracking-popup-actions';
        const details = document.createElement('button'); details.type = 'button'; details.append(icon('clock'),document.createTextNode(labels.history_details));
        details.addEventListener('click',() => openHistory(vehicles.find(v => v.id === vehicle.id) || vehicle));
        const video = document.createElement('button'); video.type = 'button'; video.append(icon('camera'),document.createTextNode(labels.video));
        video.disabled = !config.canVideo || !vehicle.equipment; video.title = video.disabled ? labels.video_forbidden : labels.video;
        video.addEventListener('click',() => { const current = vehicles.find(v => v.id === vehicle.id); if (current) void openVideo(current); });
        actions.append(details,video); content.append(header,popupFields,actions);
        popupVehicleId = vehicle.id; updatePopup(vehicle);
        infoWindow.setContent(content); infoWindow.setPosition(markers.get(vehicle.id)?.displayed || vehicle.position); infoWindow.open({map,shouldFocus:false});
    }

    function markerFor(vehicle, animate) {
        let item = markers.get(vehicle.id);
        if (!item) {
            const content = document.createElement('div'), symbol = document.createElement('span'), label = document.createElement('span');
            content.className = 'tracking-marker'; symbol.className = 'tracking-glyph'; label.className = 'tracking-marker-label';
            content.append(symbol,label);
            // The glyph rotates around its centre: anchor that same point to GPS.
            const marker = new Marker({ map, position: vehicle.position, title: vehicle.name, anchorLeft:'-50%', anchorTop:'-50%', gmpClickable:document.body.dataset.view === 'map' }); marker.append(content);
            marker.addEventListener('gmp-click', () => { if (document.body.dataset.view === 'map') choose(vehicle.id,false,true); });
            const trail = new google.maps.Polyline({ map, geodesic:true, strokeColor:'#487eae', strokeOpacity:0.75, strokeWeight:4, clickable:false });
            item = {marker,content,label,symbol,trail,vehicle:null,animation:null,path:[],displayed:vehicle.position}; markers.set(vehicle.id,item);
        }
        item.marker.gmpClickable = document.body.dataset.view === 'map';
        item.content.dataset.state = vehicle.state; item.content.classList.toggle('is-selected',selectedId === vehicle.id);
        item.symbol.dataset.state = vehicle.state; item.symbol.textContent = vehicle.state === 'parking' ? 'P' : '';
        item.label.textContent = [vehicle.name,vehicle.registration].filter(Boolean).join(' '); item.marker.title = `${vehicle.name} · ${labels[vehicle.state]}`; item.marker.zIndex = selectedId === vehicle.id ? 100 : 1;
        item.trail.setOptions({strokeColor:'#229bd8',strokeOpacity:0.65,strokeWeight:5});
        const route = vehicle.trail || [];
        const changed = !item.vehicle || item.vehicle.state !== vehicle.state || item.vehicle.source_id !== vehicle.source_id || item.vehicle.position?.at !== vehicle.position.at || item.vehicle.position?.lat !== vehicle.position.lat || item.vehicle.position?.lng !== vehicle.position.lng;
        if (changed) {
            if (item.animation) cancelAnimationFrame(item.animation);
            const path = animate && !reducedMotion.matches ? movementPath(item.vehicle,vehicle,item.displayed) : [];
            item.path = path;
            if (path.length > 1) {
                const started = performance.now(); item.previousFrame = path[0];
                const frame = time => {
                    const progress = Math.min(1,(time-started)/8000);
                    item.displayed = pointAlong(path,progress); item.marker.position = item.displayed;
                    if (popupVehicleId === vehicle.id) infoWindow.setPosition(item.displayed);
                    drawTrail(item);
                    if (item.previousFrame.lat !== item.displayed.lat || item.previousFrame.lng !== item.displayed.lng) item.symbol.style.setProperty('--heading',bearing(item.previousFrame,item.displayed)+'deg'); item.previousFrame = item.displayed;
                    if (follow.checked && !element('tracking-show-all').checked && selectedId === vehicle.id) map.panTo(item.displayed);
                    item.animation = progress < 1 && active() ? requestAnimationFrame(frame) : null;
                };
                item.animation = requestAnimationFrame(frame);
            } else { item.displayed = vehicle.position; item.marker.position = vehicle.position; item.animation = null; if (popupVehicleId === vehicle.id) infoWindow.setPosition(item.displayed); if (follow.checked && !element('tracking-show-all').checked && selectedId === vehicle.id) map.panTo(item.displayed); }
        }
        item.vehicle = vehicle;
        if (!item.animation && vehicle.state === 'moving' && route.length > 1) item.symbol.style.setProperty('--heading',bearing(route.at(-2),route.at(-1))+'deg');
        drawTrail(item);
    }
    function render(animate = false) {
        const matches = filtered(), ids = new Set(matches.map(vehicle => vehicle.id));
        element('tracking-results').hidden = !element('tracking-search').value.trim();
        if (selectedId !== null && !ids.has(selectedId)) { selectedId = null; void closeVideo(); }
        const automaticFocus = selectedId === null && follow.checked && !element('tracking-show-all').checked
            ? preferredTrackingVehicle(matches) : null;
        if (automaticFocus) selectedId = automaticFocus.id;
        const visible = displayed();
        for (const [id,row] of rows) if (!ids.has(id)) { row.button.remove(); rows.delete(id); }
        matches.slice(0,50).forEach((vehicle,index) => { const button = rowFor(vehicle); if (list.children[index] !== button) list.insertBefore(button,list.children[index] || null); });
        element('tracking-result-count').textContent = matches.length;
        element('tracking-empty').hidden = matches.length > 0;
        element('tracking-empty').textContent = vehicles.length ? labels.empty : labels.no_vehicles;
        const counts = {total:matches.length,positioned:matches.filter(v => v.position).length,online:matches.filter(v => v.online).length,offline:matches.filter(v => !v.online).length};
        document.querySelectorAll('[data-tracking-count]').forEach(node => { node.textContent = counts[node.dataset.trackingCount]; });
        const currentPopup = visible.find(vehicle => vehicle.id === popupVehicleId);
        if (popupVehicleId && !currentPopup) { infoWindow?.close(); popupVehicleId = null; } else updatePopup(currentPopup);
        if (videoVehicle && !matches.some(v => v.id === videoVehicle.id && v.source_id === videoVehicle.source_id)) void closeVideo();
        if (!map) return;
        for (const [id,item] of markers) if (!ids.has(id) || !visible.find(v => v.id === id)?.position) {
            if (item.animation) cancelAnimationFrame(item.animation); item.marker.map = null; item.trail.setMap(null); markers.delete(id);
        }
        visible.filter(vehicle => vehicle.position).forEach(vehicle => markerFor(vehicle,animate));
        if ((!initialFit || automaticFocus) && visible.some(v => v.position)) { fitFleet(); initialFit = true; }
    }
    function fitFleet() {
        if (!map) return;
        const displayedVehicles = displayed().filter(vehicle => vehicle.position);
        const focused = !element('tracking-show-all').checked ? preferredTrackingVehicle(displayedVehicles, selectedId) : null;
        const visible = focused ? [focused] : displayedVehicles;
        if (!visible.length) return;
        const bounds = new google.maps.LatLngBounds(); visible.forEach(vehicle => bounds.extend(vehicle.position));
        const panelWidth = document.body.dataset.view === 'map' && !workspace.classList.contains('panel-collapsed') ? element('tracking-panel').offsetWidth + 36 : 30;
        map.fitBounds(bounds,{top:65,right:65,bottom:65,left:panelWidth});
        google.maps.event.addListenerOnce(map,'idle',() => { if (map.getZoom() > 18) map.setZoom(18); });
    }
    function mapFailed() { mapMessage.hidden = false; mapMessage.textContent = labels.unavailable; }
    async function createMap() {
        await new Promise((resolve,reject) => {
            if (window.google?.maps?.importLibrary) { resolve(); return; }
            const timeout = setTimeout(() => reject(Error('Maps timeout')),20000);
            window.initExadcamGoogleMaps = () => { clearTimeout(timeout); resolve(); };
            window.gm_authFailure = mapFailed;
            const script = document.createElement('script');
            script.src = `https://maps.googleapis.com/maps/api/js?${new URLSearchParams({key:config.apiKey,callback:'initExadcamGoogleMaps',loading:'async',v:'quarterly',libraries:'marker',language:config.locale,region:'CD'})}`;
            script.async = true; script.onerror = () => { clearTimeout(timeout); reject(Error('Maps unavailable')); }; document.head.append(script);
        });
        const [{Map:GoogleMap},{AdvancedMarkerElement}] = await Promise.all([google.maps.importLibrary('maps'),google.maps.importLibrary('marker')]);
        Marker = AdvancedMarkerElement;
        map = new GoogleMap(canvas,{center:config.center,zoom:12,minZoom:3,maxZoom:20,mapId:config.mapId,renderingType:google.maps.RenderingType.RASTER,disableDefaultUI:true,cameraControl:false,streetViewControl:false,mapTypeControl:false,fullscreenControl:false,zoomControl:false,rotateControl:false,scaleControl:document.body.dataset.view === 'map',clickableIcons:false,gestureHandling:document.body.dataset.view === 'map' ? 'cooperative' : 'none',keyboardShortcuts:document.body.dataset.view === 'map',disableDoubleClickZoom:document.body.dataset.view !== 'map'});
        map.addListener('dragstart',() => { follow.checked = false; });
        map.addListener('zoom_changed',() => { element('tracking-zoom-in').disabled = map.getZoom() >= 20; element('tracking-zoom-out').disabled = map.getZoom() <= 3; });
        infoWindow = new google.maps.InfoWindow({maxWidth:350,headerDisabled:true});
        infoWindow.addListener('close',() => { popupVehicleId = null; });
        mapMessage.hidden = true; render();
    }
    function schedule() {
        clearTimeout(timer);
        if (active() && auto.checked) timer = setTimeout(() => refresh(), refreshDelay);
    }
    async function refresh() {
        if (!active() || controller) return;
        const sequence = ++requestSequence;
        controller = new AbortController(); const currentController = controller;
        const timeout = setTimeout(() => currentController.abort(),15000);
        element('tracking-refresh').disabled = true;
        try {
            const response = await fetch(config.positionsUrl,{headers:{Accept:'application/json'},credentials:'same-origin',cache:'no-store',signal:currentController.signal});
            if ([401,403,419].includes(response.status)) { vehicles = []; selectedId = null; render(); throw Error(labels.forbidden); }
            if (!response.ok) throw Error(labels.failed);
            const result = await response.json();
            if (sequence !== requestSequence || !active()) return;
            vehicles = result.vehicles; generation = result.generated_at; failures = 0; refreshDelay = 10000;
            feedMessage.hidden = true; updateFilters(); render(true);
            element('tracking-updated').dateTime = generation; element('tracking-updated').textContent = date(generation);
        } catch (error) {
            if (sequence !== requestSequence || !active()) return;
            failures++; refreshDelay = Math.min(30000,10000*failures); stopAnimations();
            feedMessage.textContent = error.message === labels.forbidden ? labels.forbidden : labels.failed; feedMessage.hidden = false;
        } finally {
            clearTimeout(timeout);
            if (controller === currentController) { controller = null; element('tracking-refresh').disabled = false; schedule(); }
        }
    }
    function syncView() {
        clearTimeout(timer);
        if (!active()) { void closeVideo(); requestSequence++; controller?.abort(); controller = null; stopAnimations(); return; }
        if (config.apiKey && !loading) loading = createMap().catch(mapFailed);
        if (map) {
            const interactive = document.body.dataset.view === 'map';
            map.setOptions({gestureHandling:interactive ? 'cooperative' : 'none',keyboardShortcuts:interactive,disableDoubleClickZoom:!interactive,scaleControl:interactive});
            markers.forEach(item => { item.marker.gmpClickable = interactive; });
            if (!interactive) { infoWindow?.close(); popupVehicleId = null; void closeVideo(); }
        }
        if (map) requestAnimationFrame(() => { google.maps.event.trigger(map,'resize'); if (fitOnOpen) { fitFleet(); fitOnOpen = false; } });
        if (!generation || auto.checked) void refresh();
    }
    function togglePanel(collapsed) {
        workspace.classList.toggle('panel-collapsed',collapsed);
        element('tracking-open-panel').setAttribute('aria-expanded',String(!collapsed));
        element('tracking-close-panel').setAttribute('aria-expanded',String(!collapsed));
        if (!collapsed) element('tracking-search').focus(); else element('tracking-open-panel').focus();
    }
    if (matchMedia('(max-width: 767px)').matches) workspace.classList.add('panel-collapsed');
    element('tracking-open-panel').addEventListener('click',() => togglePanel(false));
    element('tracking-close-panel').addEventListener('click',() => togglePanel(true));
    element('tracking-refresh').addEventListener('click',refresh);
    element('tracking-fit').addEventListener('click',() => { if (!element('tracking-show-all').checked) follow.checked = true; render(); fitFleet(); });
    element('tracking-show-all').addEventListener('change',() => { if (!element('tracking-show-all').checked) selectedId = null; infoWindow?.close(); popupVehicleId = null; render(); fitFleet(); });
    fleetFilter.addEventListener('change',() => { updateFilters(); render(); fitFleet(); });
    departmentFilter.addEventListener('change',() => { render(); fitFleet(); });
    stateFilter.addEventListener('change',() => { render(); fitFleet(); });
    element('tracking-search').addEventListener('input',() => { clearTimeout(searchTimer); searchTimer = setTimeout(() => render(),150); });
    auto.addEventListener('change',() => { element('tracking-sync-label').textContent = auto.checked ? labels.last_update : labels.paused; if (auto.checked) void refresh(); else { clearTimeout(timer); stopAnimations(); } });
    showTrails.addEventListener('change',() => render());
    follow.addEventListener('change',() => { if (follow.checked) { render(); fitFleet(); } });
    element('tracking-zoom-in').addEventListener('click',() => { if (map) map.setZoom(Math.min(20,map.getZoom()+1)); });
    element('tracking-zoom-out').addEventListener('click',() => { if (map) map.setZoom(Math.max(3,map.getZoom()-1)); });
    element('tracking-map-type').addEventListener('click',event => { if (!map) return; const satellite = map.getMapTypeId() !== 'hybrid'; map.setMapTypeId(satellite ? 'hybrid' : 'roadmap'); event.currentTarget.setAttribute('aria-pressed',String(satellite)); });
    element('tracking-fullscreen').addEventListener('click',async () => { try { if (document.fullscreenElement) await document.exitFullscreen(); else await workspace.requestFullscreen(); } catch { feedMessage.hidden = false; feedMessage.textContent = labels.fullscreen_failed; } });
    document.addEventListener('fullscreenchange',() => { if (map) google.maps.event.trigger(map,'resize'); });
    document.addEventListener('visibilitychange',syncView);
    document.addEventListener('exadcam:view-changed',event => { fitOnOpen = event.detail.view === 'map'; syncView(); });
    window.addEventListener('pagehide',() => { clearTimeout(timer); controller?.abort(); stopAnimations(); });
    function detailCard(title, symbol, fields) {
        const card = document.createElement('section'); card.className = 'tracking-detail-card';
        const header = document.createElement('header'), heading = document.createElement('h3'); heading.textContent = title;
        header.append(heading,icon(symbol));
        const list = document.createElement('dl');
        for (const [glyph,label,value] of fields) {
            const row = document.createElement('div'), term = document.createElement('dt'), description = document.createElement('dd');
            const badge = document.createElement('span'); badge.className = 'tracking-detail-icon'; badge.append(icon(glyph));
            term.textContent = label; description.textContent = value ?? '—'; row.append(badge,term,description); list.append(row);
        }
        card.append(header,list); return card;
    }
    function renderEquipment(result, snapshot) {
        const {vehicle,equipment:eq} = result, point = snapshot.position;
        const hero = document.createElement('section'); hero.className = 'tracking-details-summary';
        const identity = document.createElement('div'); identity.className = 'tracking-summary-identity';
        const badge = document.createElement('span'); badge.className = 'tracking-summary-icon'; badge.append(icon('truck'));
        const copy = document.createElement('div'), eyebrow = document.createElement('span'), title = document.createElement('h3'), subtitle = document.createElement('p');
        eyebrow.className = 'tracking-summary-eyebrow'; eyebrow.textContent = labels.operational_summary;
        title.textContent = vehicle.name; subtitle.textContent = [vehicle.registration,vehicle.fleet].filter(Boolean).join(' · ');
        copy.append(eyebrow,title,subtitle); identity.append(badge,copy);
        const status = document.createElement('div'); status.className = 'tracking-summary-status';
        const online = result.last_seen_at && Date.now() - Date.parse(result.last_seen_at) <= 180000;
        const connection = document.createElement('span'); connection.className = 'tracking-summary-chip'; connection.append(connectionDot(online),document.createTextNode(online ? labels.online : labels.offline));
        const state = document.createElement('span'); state.className = 'tracking-summary-chip'; state.append(icon(snapshot.state === 'moving' ? 'route' : 'truck'),document.createTextNode(labels[snapshot.state]));
        const updated = document.createElement('p'); updated.append(icon('clock'),document.createTextNode(relativeDate(result.last_seen_at))); updated.title = date(result.last_seen_at);
        status.append(connection,state,updated); hero.append(identity,status);
        const identityFields = [['truck',labels.registration,vehicle.registration],['fleets',labels.fleet,vehicle.fleet],['departments',labels.department,vehicle.department]];
        if (eq) {
            if (eq.name !== eq.model) identityFields.push(['camera',labels.camera,eq.name]);
            identityFields.push(['dashcam',labels.model,eq.model],['info',labels.imei,eq.imei],['camera',labels.video,`${eq.channels} ${labels.channels.toLocaleLowerCase()} · ${eq.frame_rate} ${labels.frame_rate.toLocaleLowerCase()}`]);
        }
        const locationFields = [['pin',labels.coordinates,point ? `${point.lat.toFixed(6)}, ${point.lng.toFixed(6)}` : null],
            ['route',labels.speed,point ? `${point.speed} ${labels.kmh}` : null],
            ['settings',labels.ignition,point ? (point.ignition ? labels.ignition_on : labels.ignition_off) : null],
            ['clock',labels.position_date,date(point?.at)],['signal',labels.contact_date,date(result.last_seen_at)]];
        const cards = document.createElement('div'); cards.className = 'tracking-details-grid';
        cards.append(detailCard(eq ? labels.vehicle_equipment : labels.vehicle,'truck',identityFields),detailCard(labels.location,'pin',locationFields));
        const fragment = document.createDocumentFragment(); fragment.append(hero,cards);
        if (eq) {
            const technical = document.createElement('div'); technical.className = 'tracking-details-technical';
            const values = [[labels.protocol,[eq.transport,'JT808',eq.protocol_version].filter(Boolean).join(' · ')],
                [labels.observed_protocol,eq.last_protocol ? 'JT808 · '+eq.last_protocol : '—'],[labels.created,date(eq.created_at)]];
            for (const [label,value] of values) { const item = document.createElement('div'), term = document.createElement('span'), description = document.createElement('strong'); term.textContent = label; description.textContent = value; item.append(term,description); technical.append(item); }
            fragment.append(technical);
        }
        element('tracking-equipment-fields').replaceChildren(fragment);
    }

    const historyModal = element('tracking-history-modal');
    document.body.append(historyModal);
    let historyVehicle = null, historyPage = 1, historyRequest = null;
    const localDay = () => { const d = new Date(); return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`; };
    element('tracking-history-date').max = localDay();
    async function openHistory(vehicle) {
        historyVehicle = vehicle; historyPage = 1; element('tracking-history-date').value = localDay();
        element('tracking-history-title').textContent = labels.equipment_details;
        bootstrap.Tab.getOrCreateInstance(element('tracking-summary-tab')).show();
        element('tracking-equipment-fields').replaceChildren();
        if (document.fullscreenElement) await document.exitFullscreen().catch(() => {});
        bootstrap.Modal.getOrCreateInstance(historyModal).show();
        void loadHistory();
    }
    function renderHistoryPagination(result) {
        historyPage = result.page;
        const format = value => new Intl.NumberFormat(config.locale).format(value);
        element('tracking-history-summary').textContent = labels.history_range
            .replace(':from',format(result.from ?? 0)).replace(':to',format(result.to ?? 0)).replace(':total',format(result.total));
        const pages = element('tracking-history-pages'); pages.replaceChildren();
        const last = result.last_page;
        const visible = new Set([1,last]);
        const start = Math.max(1,Math.min(result.page - 1,last - 4));
        const end = Math.min(last,Math.max(result.page + 1,5));
        for (let number = start; number <= end; number++) visible.add(number);
        let previous = 0;
        for (const number of [...visible].sort((a,b) => a-b)) {
            if (previous && number - previous > 1) {
                const gap = document.createElement('span'); gap.className = 'tracking-page-gap'; gap.textContent = '…'; gap.setAttribute('aria-hidden','true'); pages.append(gap);
            }
            const button = document.createElement('button'); button.type = 'button'; button.className = 'tracking-page-button';
            button.textContent = format(number); button.setAttribute('aria-label',`${labels.page} ${format(number)}`);
            if (number === result.page) button.setAttribute('aria-current','page');
            button.addEventListener('click',() => { if (number !== historyPage) { historyPage = number; void loadHistory(); } });
            pages.append(button); previous = number;
        }
        element('tracking-history-prev').disabled = result.page <= 1;
        element('tracking-history-next').disabled = !result.has_more;
    }

    async function loadHistory() {
        historyRequest?.abort(); historyRequest = new AbortController();
        const current = historyRequest, selected = historyVehicle;
        const dateInput = element('tracking-history-date'), message = element('tracking-history-message');
        const rows = element('tracking-history-rows'); rows.replaceChildren();
        element('tracking-history-prev').disabled = true; element('tracking-history-next').disabled = true;
        element('tracking-history-pages').replaceChildren();
        element('tracking-history-summary').textContent = '';
        if (!dateInput.value || !dateInput.validity.valid) { message.textContent = labels.date_invalid; return; }
        message.textContent = labels.history_loading;
        const timeout = setTimeout(() => current.abort(),15000);
        try {
            const params = new URLSearchParams({source_id:selected.source_id,date:dateInput.value,timezone:Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC',page:historyPage});
            const response = await fetch(`${selected.details_url}?${params}`,{headers:{Accept:'application/json'},credentials:'same-origin',cache:'no-store',signal:current.signal});
            if (!response.ok) throw Error('unavailable');
            const result = await response.json(); if (current !== historyRequest) return;
            renderEquipment(result,selected);
            rows.replaceChildren(...result.history.map(point => {
                const row = document.createElement('tr');
                [date(point.at),labels[point.state],point.speed+' '+labels.kmh,point.ignition ? labels.ignition_on : labels.ignition_off,`${point.lat.toFixed(6)}, ${point.lng.toFixed(6)}`].forEach(value => {
                    const cell = document.createElement('td'); cell.textContent = value; row.append(cell);
                }); return row;
            }));
            message.textContent = '';
            if (!result.history.length) { const row = document.createElement('tr'), cell = document.createElement('td'); cell.colSpan = 5; cell.textContent = labels.history_empty; row.append(cell); rows.append(row); }
            renderHistoryPagination(result);
        } catch { if (current === historyRequest) { message.textContent = labels.history_failed; element('tracking-equipment-fields').replaceChildren(); } }
        finally { clearTimeout(timeout); }
    }
    historyModal.addEventListener('hidden.bs.modal',() => { historyRequest?.abort(); historyRequest = null; });
    element('tracking-history-date').addEventListener('change',() => { historyPage = 1; void loadHistory(); });
    element('tracking-history-prev').addEventListener('click',() => { historyPage = Math.max(1,historyPage-1); void loadHistory(); });
    element('tracking-history-next').addEventListener('click',() => { historyPage++; void loadHistory(); });

    async function videoRequest(url, data, keepalive = false) {
        const controller = new AbortController(), timeout = setTimeout(() => controller.abort(), 15000);
        try {
            const response = await fetch(url,{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify(data),credentials:'same-origin',keepalive,signal:controller.signal});
            if (response.redirected || !response.ok) throw Object.assign(Error('Video unavailable'),{status:response.redirected ? 401 : response.status});
            return await response.json();
        } finally { clearTimeout(timeout); }
    }
    const videoPlayers = [...document.querySelectorAll('[data-map-channel]')].map(section => {
        const player = section.querySelector('video'), startButton = section.querySelector('[data-video-start]'), stopButton = section.querySelector('[data-video-stop]');
        const placeholder = section.querySelector('.tracking-video-placeholder'), status = section.querySelector('[data-video-status]');
        let playback = null, manualPaused = false;
        const channel = Number(section.dataset.mapChannel);
        const session = new MapVideoChannel({request:videoRequest,
            reset({preserveFrame = false} = {}) {
                const still = resetLivePlayer(player, playback, {preserveFrame}); playback = null;
                if (!preserveFrame) manualPaused = false;
                placeholder.hidden = still; startButton.hidden = preserveFrame;
                startButton.disabled = !videoVehicle || channel > videoVehicle.equipment.channels;
                stopButton.hidden = !preserveFrame;
            },
            notify(state) { status.textContent = labels['video_'+state]; startButton.disabled = state !== 'failed'; stopButton.hidden = state === 'failed'; startButton.hidden = state !== 'failed'; },
            attach(url,onError) {
                playback = attachLivePlayer(player, url, {
                    shouldPlay: () => !manualPaused,
                    onState(state) {
                        if (state === 'paused') manualPaused = true;
                        if (state === 'ready') { manualPaused = false; session.markPlaying(); }
                        if (['preview','ready','paused','play_required'].includes(state)) placeholder.hidden = true;
                        if (state !== 'buffering' && state !== 'preview') status.textContent = labels['video_'+state];
                    }, onError,
                });
            }
        });
        startButton.addEventListener('click',() => {
            if (!videoVehicle?.equipment || !config.canVideo || channel > videoVehicle.equipment.channels) return;
            void session.start(`${config.videoUrl}/${videoVehicle.equipment.id}/live`,channel);
        });
        stopButton.addEventListener('click',() => { void session.stop(); status.textContent = labels.video_idle; });
        return {session,startButton,status,channel};
    });
    let panelBeforeVideo = false;
    function resizeMap() { if (map) requestAnimationFrame(() => { google.maps.event.trigger(map,'resize'); fitFleet(); }); }
    async function closeVideo(keepalive = false) {
        videoGeneration++;
        const wasOpen = !!videoVehicle; videoVehicle = null;
        element('tracking-video-panel').hidden = true; workspace.classList.remove('has-video');
        const stops = [...videoPlayers.map(({session}) => session.stop(keepalive)),vehicleAudio.select(null,keepalive)];
        if (wasOpen) { workspace.classList.toggle('panel-collapsed',panelBeforeVideo); resizeMap(); }
        await Promise.allSettled(stops);
    }
    async function openVideo(vehicle) {
        if (!config.canVideo || !vehicle.equipment) return;
        const stopping = closeVideo(), opening = videoGeneration;
        await stopping;
        if (opening !== videoGeneration || !active() || selectedId !== vehicle.id) return;
        // The opening itself does not start either camera channel.
        videoVehicle = vehicle; panelBeforeVideo = workspace.classList.contains('panel-collapsed');
        void vehicleAudio.select(vehicle.equipment.id);
        workspace.classList.add('panel-collapsed','has-video');
        if (document.body.dataset.view !== 'map') location.hash = 'map';
        element('tracking-video-title').textContent = vehicle.name;
        element('tracking-video-subtitle').textContent = [vehicle.equipment.model,vehicle.equipment.imei].filter(Boolean).join(' · ');
        element('tracking-video-panel').dataset.cameraModel = vehicle.equipment.model || '';
        element('tracking-video-panel').hidden = false;
        videoPlayers.forEach(({startButton,status,channel}) => { startButton.disabled = channel > vehicle.equipment.channels; status.textContent = labels[channel > vehicle.equipment.channels ? 'video_missing' : 'video_idle']; });
        infoWindow?.close(); popupVehicleId = null; resizeMap();
        element('tracking-video-close').focus();
    }
    element('tracking-video-close').addEventListener('click',() => { void closeVideo(); });
    window.addEventListener('pagehide',() => { void closeVideo(true); });

    syncView();
})();
