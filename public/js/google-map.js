(async () => {
    'use strict';
    const configNode = document.getElementById('google-map-data');
    if (!configNode) return;
    const config = JSON.parse(configNode.textContent), labels = config.labels;
    if (!config.allowed) return;
    const { createMarkerTrail } = await import('./map-marker-trail.mjs?v=map-trail-2');
    const { movementPath, pointAlong, bearing, trailThroughPosition, preferredTrackingVehicle } = await import('./map-motion.mjs?v=map-anchor-1');
    const { createMapVideoPlayer } = await import('./map-video-player.mjs?v=cam-map-live-20261008');
    const { viewportPadding, projectedCenter, observeMapView } = await import('./map-view.mjs?v=map-responsive-1');
    const { audioControls } = await import('./live-audio.mjs?v=talk-direction-20260924');
    const {createVideoFullscreen,observeMapLayout} = await import('./map-video-fullscreen.mjs?v=map-layout-20260925');
    const { createTripHistory } = await import('./map-trip-history.mjs?v=history-design-20261005');
    const {dashboardConnection,matchesMapState,applyDashboardConnection} = await import('./map-dashboard-filter.mjs?v=map-panel-20261007');
    const element = id => document.getElementById(id);
    const vehicleAudio = audioControls(element('tracking-video-audio'));
    const workspace = element('tracking-workspace'), canvas = element('google-fleet-map');
    const fleetFilter = element('tracking-fleet'), departmentFilter = element('tracking-department'), stateFilter = element('tracking-state');
    const list = element('tracking-vehicle-list'), mapMessage = element('tracking-map-message'), feedMessage = element('tracking-feed-message');
    const auto = element('tracking-auto'), follow = element('tracking-follow'), showTrails = element('tracking-trails');
    const markers = new Map(), rows = new Map();
    const tripHistory = createTripHistory({host:element('tracking-map-area'),getMap:()=>map,getMarker:()=>Marker,labels,icon,
        onOpen:()=>{follow.checked=false;infoWindow?.close();element('tracking-panel').classList.add('history-covered');},
        onClose:()=>element('tracking-panel').classList.remove('history-covered')});
    const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
    let vehicles = [], selectedId = null, map, Marker, loading, timer, controller, requestSequence = 0, generation = null;
    let infoWindow, popupVehicleId = null, popupFields = null, popupSubtitle = null, popupDot = null, videoVehicle = null, videoGeneration = 0;
    let initialFit = false, fitOnOpen = false, failures = 0, refreshDelay = 10000, searchTimer;
    const normalize = value => String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase().trim();
    let mapPadding = {top:24,right:24,bottom:24,left:24}, resizeFrame = null;
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
        return vehicles.filter(vehicle => (!fleetFilter?.value || String(vehicle.fleet.id) === fleetFilter.value)
            && (!departmentFilter.value || (departmentFilter.value === 'none' ? !vehicle.department : String(vehicle.department?.id) === departmentFilter.value))
            && matchesMapState(vehicle,stateFilter.value)
            && normalize([vehicle.name,vehicle.registration,vehicle.fleet.name,vehicle.department?.name,vehicle.camera_name,vehicle.equipment?.imei,vehicle.equipment?.model].filter(Boolean).join(' ')).includes(query));
    }
    function displayed() {
        const matches = filtered();
        return document.body.dataset.view === 'overview' || element('tracking-show-all').checked ? matches : matches.filter(v => v.id === selectedId);
    }
    function replaceOptions(select, options) {
        if (!select) return;
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
        const departments = new Map(vehicles.filter(vehicle => vehicle.department && (!fleetFilter?.value || String(vehicle.fleet.id) === fleetFilter.value)).map(vehicle => [String(vehicle.department.id), vehicle.department.name]));
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
        item.trail.setPath(showTrails.checked ? trailThroughPosition(item.vehicle,item.displayed,item.path) : [], item.displayed);
    }
    let keepResultsVisible = false, pendingPopupId = null;
    function choose(id, center = true, popup = false) {
        keepResultsVisible = id !== null; pendingPopupId = null;
        if (selectedId !== id) void closeVideo();
        selectedId = id;
        if (id !== null) { element('tracking-show-all').checked = false; follow.checked = true; }
        if (!popup) { infoWindow?.close(); popupVehicleId = null; }
        render(false);
        const vehicle = vehicles.find(item => item.id === id);
        if (center && map && vehicle?.position) {
            fitFleet();
        }
        if (popup && vehicle?.position) {
            if (map && infoWindow) openPopup(vehicle);
            else pendingPopupId = id;
        }
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
            button.addEventListener('click', () => choose(vehicle.id,true,true));
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
        const fields = [[labels.registration,vehicle.registration], vehicle.equipment?.model ? [labels.camera,vehicle.equipment.model] : [labels.ignition,typeof vehicle.position?.ignition === 'boolean' ? (vehicle.position.ignition ? labels.ignition_on : labels.ignition_off) : '—'],
            [labels.fleet,vehicle.fleet.name], [labels.speed,vehicle.position.speed+' '+labels.kmh], [labels.contact_date,relativeDate(vehicle.last_seen_at)]];
        fillFields(popupFields, fields);
        popupFields.lastElementChild.title = date(vehicle.last_seen_at);
    }
    function openPopup(vehicle) {
        if (!map || !infoWindow || !vehicle?.position) return;
        pendingPopupId = null;
        const content = document.createElement('div'); content.className = 'tracking-popup';
        const header = document.createElement('header'), identity = document.createElement('div');
        const title = document.createElement('strong'); title.textContent = vehicle.name;
        popupDot = connectionDot(vehicle.online); popupSubtitle = document.createElement('p'); popupFields = document.createElement('dl');
        identity.append(title,popupSubtitle);
        const close = document.createElement('button'); close.type = 'button'; close.className = 'tracking-popup-close'; close.setAttribute('aria-label',labels.close_details); close.append(icon('close'));
        close.addEventListener('click',() => { infoWindow.close(); popupVehicleId = null; });
        header.append(popupDot,identity,close);
        const actions = document.createElement('div'); actions.className = 'tracking-popup-actions';
        const details = document.createElement('button'); details.type = 'button'; details.append(icon('info'),document.createTextNode(labels.history_details));
        details.addEventListener('click',() => openHistory(vehicles.find(v => v.id === vehicle.id) || vehicle));
        const video = document.createElement('button'); video.type = 'button'; video.append(icon('camera'),document.createTextNode(labels.video));
        video.disabled = !config.canVideo || !vehicle.equipment; video.title = video.disabled ? labels.video_forbidden : labels.video;
        video.addEventListener('click',() => { const current = vehicles.find(v => v.id === vehicle.id); if (current) void openVideo(current); });
        const trips = document.createElement('button'); trips.type='button'; trips.append(icon('route'),document.createTextNode(labels.trips_history));
        trips.addEventListener('click',()=>tripHistory.open(vehicles.find(v=>v.id===vehicle.id)||vehicle));
        actions.append(trips,details,video); content.append(header,popupFields,actions);
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
            const trail = createMarkerTrail(content, map, google.maps);
            item = {marker,content,label,symbol,trail,vehicle:null,animation:null,path:[],displayed:vehicle.position}; markers.set(vehicle.id,item);
        }
        item.marker.gmpClickable = document.body.dataset.view === 'map';
        item.content.dataset.state = vehicle.state; item.content.classList.toggle('is-selected',selectedId === vehicle.id);
        item.symbol.dataset.state = vehicle.state; item.symbol.textContent = vehicle.state === 'parking' ? 'P' : '';
        item.label.textContent = [vehicle.name,vehicle.registration].filter(Boolean).join(' '); item.marker.title = `${vehicle.name} · ${labels[vehicle.state]}`; item.marker.zIndex = selectedId === vehicle.id ? 100 : 1;
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
                    if (!tripHistory.active && follow.checked && !element('tracking-show-all').checked && selectedId === vehicle.id) centerPosition(item.displayed);
                    item.animation = progress < 1 && active() ? requestAnimationFrame(frame) : null;
                };
                item.animation = requestAnimationFrame(frame);
            } else { item.displayed = vehicle.position; item.marker.position = vehicle.position; item.animation = null; if (popupVehicleId === vehicle.id) infoWindow.setPosition(item.displayed); if (!tripHistory.active && follow.checked && !element('tracking-show-all').checked && selectedId === vehicle.id) centerPosition(item.displayed); }
        }
        item.vehicle = vehicle;
        if (!item.animation && vehicle.state === 'moving' && route.length > 1) item.symbol.style.setProperty('--heading',bearing(route.at(-2),route.at(-1))+'deg');
        drawTrail(item);
    }
    function render(animate = false) {
        const matches = filtered(), ids = new Set(matches.map(vehicle => vehicle.id));
        document.querySelectorAll('[data-tracking-status]').forEach(button => button.setAttribute('aria-pressed',String(button.dataset.trackingStatus === stateFilter.value)));
        element('tracking-results').hidden = !keepResultsVisible && !element('tracking-show-all').checked && !element('tracking-search').value.trim() && !['online','offline'].includes(stateFilter.value);
        if (selectedId !== null && !ids.has(selectedId)) { selectedId = null; void closeVideo(); }
        const automaticFocus = selectedId === null && follow.checked && !element('tracking-show-all').checked
            ? preferredTrackingVehicle(matches) : null;
        if (automaticFocus) selectedId = automaticFocus.id;
        const visible = displayed();
        for (const [id,row] of rows) if (!ids.has(id)) { row.button.remove(); rows.delete(id); }
        matches.forEach((vehicle,index) => { const button = rowFor(vehicle); if (list.children[index] !== button) list.insertBefore(button,list.children[index] || null); });
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
            if (item.animation) cancelAnimationFrame(item.animation); item.marker.map = null; item.trail.remove(); markers.delete(id);
        }
        visible.filter(vehicle => vehicle.position).forEach(vehicle => markerFor(vehicle,animate));
        if ((!initialFit || automaticFocus) && visible.some(v => v.position)) { fitFleet(); initialFit = true; }
    }
    function measureViewport() {
        const area = canvas.getBoundingClientRect();
        const panel = document.body.dataset.view === 'map' && !workspace.classList.contains('panel-collapsed')
            ? element('tracking-panel').getBoundingClientRect() : null;
        mapPadding = viewportPadding(area.width, area.height, panel ? panel.right - area.left : 0);
    }
    function centerPosition(position) {
        const projection = map?.getProjection();
        if (!projection || !position || !active()) return;
        const point = projection.fromLatLngToPoint(new google.maps.LatLng(position.lat,position.lng));
        const center = projectedCenter(point,map.getZoom(),mapPadding);
        // Updating the camera with the animated marker avoids a second, lagging pan animation.
        map.setCenter(projection.fromPointToLatLng(new google.maps.Point(center.x,center.y)));
    }
    function recenterSelection() {
        if (tripHistory.active || !follow.checked || element('tracking-show-all').checked) return;
        const selected = vehicles.find(vehicle => vehicle.id === selectedId);
        centerPosition(markers.get(selectedId)?.displayed || selected?.position);
    }
    function fitFleet() {
        if (tripHistory.active) return;
        if (!map) return;
        const displayedVehicles = displayed().filter(vehicle => vehicle.position);
        const focused = !element('tracking-show-all').checked ? preferredTrackingVehicle(displayedVehicles, selectedId) : null;
        const visible = focused ? [focused] : displayedVehicles;
        if (!visible.length) return;
        measureViewport();
        if (focused) { map.setZoom(18); centerPosition(markers.get(focused.id)?.displayed || focused.position); return; }
        const bounds = new google.maps.LatLngBounds(); visible.forEach(vehicle => bounds.extend(vehicle.position));
        map.fitBounds(bounds,mapPadding);
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
        map = new GoogleMap(canvas,{center:config.center,zoom:12,minZoom:3,maxZoom:20,mapId:config.mapId,mapTypeId:config.mapType || 'roadmap',renderingType:google.maps.RenderingType.RASTER,disableDefaultUI:true,cameraControl:false,streetViewControl:false,mapTypeControl:false,fullscreenControl:false,zoomControl:false,rotateControl:false,scaleControl:document.body.dataset.view === 'map',clickableIcons:false,gestureHandling:document.body.dataset.view === 'map' ? 'cooperative' : 'none',keyboardShortcuts:document.body.dataset.view === 'map',disableDoubleClickZoom:document.body.dataset.view !== 'map'});
        map.addListener('projection_changed',() => { measureViewport(); recenterSelection(); });
        map.addListener('zoom_changed',() => { markers.forEach(item => item.trail.redraw()); if (!popupVehicleId) recenterSelection(); });
        map.addListener('dragstart',() => { follow.checked = false; });
        map.addListener('zoom_changed',() => { element('tracking-zoom-in').disabled = map.getZoom() >= 20; element('tracking-zoom-out').disabled = map.getZoom() <= 3; });
        infoWindow = new google.maps.InfoWindow({maxWidth:350,headerDisabled:true});
        infoWindow.addListener('close',() => { popupVehicleId = null; });
        mapMessage.hidden = true; render();
        if (pendingPopupId === selectedId) {
            const requested = vehicles.find(vehicle => vehicle.id === pendingPopupId);
            if (requested?.position) openPopup(requested);
        }
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
    document.addEventListener('exadcam:report-map-load',()=>{
        if(config.apiKey&&!loading) loading=createMap().catch(mapFailed);
    });
    let appliedDashboardHash = null;
    function syncDashboardFilter() {
        const status = dashboardConnection(location.hash);
        if (!status) { appliedDashboardHash = null; return; }
        if (appliedDashboardHash === location.hash) return;
        appliedDashboardHash = location.hash;
        applyDashboardConnection(document,status);
        selectedId = null; initialFit = false; fitOnOpen = true;
        tripHistory.close(); infoWindow?.close(); popupVehicleId = null; void closeVideo();
        updateFilters(); render(); fitFleet();
    }
    function syncView() {
        syncDashboardFilter();
        clearTimeout(timer);
        if (!active()) { requestSequence++; controller?.abort(); controller = null; stopAnimations(); return; }
        if (config.apiKey && !loading) loading = createMap().catch(mapFailed);
        if (map) {
            const interactive = document.body.dataset.view === 'map';
            map.setOptions({gestureHandling:interactive ? 'cooperative' : 'none',keyboardShortcuts:interactive,disableDoubleClickZoom:!interactive,scaleControl:interactive});
            markers.forEach(item => { item.marker.gmpClickable = interactive; });
            if (!interactive) { infoWindow?.close(); popupVehicleId = null; void closeVideo(); }
        }
        resizeMap();
        if (!generation || auto.checked) void refresh();
    }
    function togglePanel(collapsed, moveFocus = true) {
        workspace.classList.toggle('panel-collapsed',collapsed);
        element('tracking-open-panel').setAttribute('aria-expanded',String(!collapsed));
        element('tracking-close-panel').setAttribute('aria-expanded',String(!collapsed));
        if (moveFocus) {
            if (!collapsed) element('tracking-search').focus({preventScroll:true}); else element('tracking-open-panel').focus({preventScroll:true});
        }
        resizeMap();
    }
    if (matchMedia('(max-width: 767px)').matches) workspace.classList.add('panel-collapsed');
    element('tracking-open-panel').addEventListener('click',() => togglePanel(false));
    element('tracking-close-panel').addEventListener('click',() => togglePanel(true));
    element('tracking-refresh').addEventListener('click',refresh);
    element('tracking-fit').addEventListener('click',() => { if (!element('tracking-show-all').checked) follow.checked = true; render(); fitFleet(); });
    element('tracking-show-all').addEventListener('change',() => { keepResultsVisible = false; if (!element('tracking-show-all').checked) selectedId = null; infoWindow?.close(); popupVehicleId = null; render(); fitFleet(); });
    fleetFilter?.addEventListener('change',() => { updateFilters(); render(); fitFleet(); });
    departmentFilter.addEventListener('change',() => { render(); fitFleet(); });
    stateFilter.addEventListener('change',() => { render(); fitFleet(); });
    document.querySelectorAll('[data-tracking-status]').forEach(button => button.addEventListener('click',() => {
        stateFilter.value = button.dataset.trackingStatus;
        element('tracking-show-all').checked = true;
        follow.checked = false;
        selectedId = null;
        tripHistory.close();
        if (document.body.dataset.view === 'map') history.replaceState(null,'',stateFilter.value ? '#map?connection='+stateFilter.value : '#map');
        stateFilter.dispatchEvent(new Event('change'));
    }));
    element('tracking-search').addEventListener('input',() => { clearTimeout(searchTimer); searchTimer = setTimeout(() => render(),150); });
    auto.addEventListener('change',() => { element('tracking-sync-label').textContent = auto.checked ? labels.last_update : labels.paused; if (auto.checked) void refresh(); else { clearTimeout(timer); stopAnimations(); } });
    showTrails.addEventListener('change',() => render());
    follow.addEventListener('change',() => { if (follow.checked) { render(); fitFleet(); } });
    element('tracking-zoom-in').addEventListener('click',() => { if (map) map.setZoom(Math.min(20,map.getZoom()+1)); });
    element('tracking-zoom-out').addEventListener('click',() => { if (map) map.setZoom(Math.max(3,map.getZoom()-1)); });
    element('tracking-map-type').setAttribute('aria-pressed',String(['hybrid','satellite'].includes(config.mapType)));
    element('tracking-map-type').addEventListener('click',event => { if (!map) return; const satellite = !['hybrid','satellite'].includes(map.getMapTypeId()); const satelliteType = ['hybrid','satellite'].includes(config.mapType) ? config.mapType : 'hybrid'; const planType = ['roadmap','terrain'].includes(config.mapType) ? config.mapType : 'roadmap'; map.setMapTypeId(satellite ? satelliteType : planType); event.currentTarget.setAttribute('aria-pressed',String(satellite)); });
    element('tracking-fullscreen').addEventListener('click',async () => { try { if (document.fullscreenElement) await document.exitFullscreen(); else await workspace.requestFullscreen(); } catch { feedMessage.hidden = false; feedMessage.textContent = labels.fullscreen_failed; } });
    document.addEventListener('fullscreenchange',resizeMap);
    window.addEventListener('resize',resizeMap);
    window.visualViewport?.addEventListener('resize',resizeMap);
    const mapResizeObserver = new ResizeObserver(resizeMap);
    mapResizeObserver.observe(element('tracking-map-area'));
    mapResizeObserver.observe(element('tracking-panel'));
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
    let historyRequest = null;
    async function openHistory(vehicle) {
        historyRequest?.abort();
        const current = historyRequest = new AbortController();
        element('tracking-history-title').textContent = labels.equipment_details;
        element('tracking-equipment-fields').replaceChildren();
        const message = element('tracking-history-message');
        message.textContent = labels.history_loading;
        if (document.fullscreenElement) await document.exitFullscreen().catch(() => {});
        if (current !== historyRequest) return;
        bootstrap.Modal.getOrCreateInstance(historyModal).show();
        const timeout = setTimeout(() => current.abort(),15000);
        try {
            const params = new URLSearchParams({source_id:vehicle.source_id,summary_only:'1'});
            const response = await fetch(vehicle.details_url+'?'+params,{headers:{Accept:'application/json'},credentials:'same-origin',cache:'no-store',signal:current.signal});
            if (!response.ok) throw Error('unavailable');
            const result = await response.json();
            if (current !== historyRequest) return;
            renderEquipment(result,vehicle);
            message.textContent = '';
        } catch { if (current === historyRequest) { message.textContent = labels.history_failed; element('tracking-equipment-fields').replaceChildren(); } }
        finally { clearTimeout(timeout); }
    }
    historyModal.addEventListener('hidden.bs.modal',() => { historyRequest?.abort(); historyRequest = null; });

    async function videoRequest(url, data, keepalive = false) {
        const controller = new AbortController(), timeout = setTimeout(() => controller.abort(), 15000);
        try {
            const response = await fetch(url,{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify(data),credentials:'same-origin',keepalive,signal:controller.signal});
            if (response.redirected || !response.ok) {
                const detail = response.status === 422 ? await response.json().catch(() => ({})) : {};
                throw Object.assign(Error(detail.message || labels.video_failed),{status:response.redirected ? 401 : response.status});
            }
            return await response.json();
        } finally { clearTimeout(timeout); }
    }
    const videoPlayers = [...document.querySelectorAll('[data-map-channel]')].map(section => createMapVideoPlayer({
        section, labels, request:videoRequest, getVehicle:() => videoVehicle,
        canVideo:config.canVideo, baseUrl:config.videoUrl,
    }));
    const videoFullscreen=createVideoFullscreen({panel:element('tracking-video-panel'),button:element('tracking-video-fullscreen'),onChange:resizeMap});
    const stopLayoutObserver=observeMapLayout(workspace);
    window.addEventListener('pagehide',stopLayoutObserver,{once:true});
    function resizeMap() {
        if (!map || !active() || resizeFrame !== null) return;
        resizeFrame = requestAnimationFrame(() => {
            resizeFrame = null;
            if (!active() || !canvas.clientWidth || !canvas.clientHeight) return;
            google.maps.event.trigger(map,'resize'); measureViewport();
            if (fitOnOpen) { fitOnOpen = false; fitFleet(); }
            else recenterSelection();
        });
    }
    async function closeVideo(keepalive = false) {
        videoGeneration++;
        const leavingFullscreen=videoFullscreen.close({restoreFocus:false});
        const wasOpen = !!videoVehicle; videoVehicle = null;
        element('tracking-video-panel').hidden = true; workspace.classList.remove('has-video');
        const stops = [leavingFullscreen,...videoPlayers.map(player => player.stop(keepalive)),vehicleAudio.select(null,keepalive)];
        if (wasOpen) resizeMap();
        await Promise.allSettled(stops);
    }
    async function openVideo(vehicle) {
        if (!config.canVideo || !vehicle.equipment) return;
        const stopping = closeVideo(), opening = videoGeneration;
        // Show loading immediately, including while the previous leases are released.
        videoVehicle = vehicle;
        workspace.classList.add('has-video');
        togglePanel(false,false);
        if (document.body.dataset.view !== 'map') location.hash = 'map';
        element('tracking-video-title').textContent = vehicle.name;
        element('tracking-video-subtitle').textContent = [vehicle.equipment.model,vehicle.equipment.imei].filter(Boolean).join(' · ');
        element('tracking-video-panel').dataset.videoFit = vehicle.equipment.video_fit || 'contain';
        element('tracking-video-panel').dataset.channelCount = String(Math.min(2,vehicle.equipment.channels));
        document.querySelectorAll('[data-map-channel]').forEach(section=>{section.dataset.available=String(Number(section.dataset.mapChannel)<=vehicle.equipment.channels);});
        element('tracking-video-panel').hidden = false;
        videoPlayers.forEach(player => player.prepare());
        infoWindow?.close(); popupVehicleId = null; resizeMap();
        element('tracking-video-close').focus({preventScroll:true});
        await stopping;
        if (opening !== videoGeneration || document.body.dataset.view !== 'map' || selectedId !== vehicle.id) return;
        void vehicleAudio.select(vehicle.equipment.id);
        videoPlayers.forEach(player => { void player.startPrepared(); });
    }
    element('tracking-video-close').addEventListener('click',() => { void closeVideo(); });
    window.addEventListener('pagehide',() => { void closeVideo(true); });

    observeMapView({document,
        closeVideo:() => { void closeVideo(); },
        sync:syncView,
        enter:() => { fitOnOpen = document.body.dataset.view === 'map'; },
        resume:() => { videoPlayers.forEach(player => player.resume()); },
    });
})();
