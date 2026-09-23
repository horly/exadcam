(async () => {
    'use strict';
    const {attachLivePlayer, resetLivePlayer} = await import('./live-player.mjs?v=live-reconnect-1');
    const {MapVideoChannel} = await import('./map-video.mjs?v=live-reconnect-1');
    const {audioControls} = await import('./live-audio.mjs?v=audio-3');
    const configNode = document.getElementById('dashcam-config');
    if (!configNode) return;
    const config = JSON.parse(configNode.textContent), strings = config.strings;
    const module = document.getElementById('dashcams-module'), form = document.getElementById('dashcam-form');
    const notice = document.getElementById('dashcam-notice'), table = document.getElementById('dashcam-table');
    const modal = document.getElementById('dashcam-live-modal'), player = document.getElementById('dashcam-live-player');
    const vehicleAudio = audioControls(document.getElementById('dashcam-live-audio'));
    const status = document.getElementById('dashcam-live-status'), formModal = document.getElementById('dashcam-form-modal');
    if (!form || !table) return;
    const formAlert = document.getElementById('dashcam-form-alert'), fields = form.elements;
    let playback = null, manualPaused = false;
    let records = [], editing = null, options = { fleets: [], vehicles: [] }, listRequest = 0, openRequest = 0;
    const state = { page: 1, per_page: 10, search: '', model: '', sort: 'id', direction: 'desc' };
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    async function request(url, data, method = 'POST', keepalive = false) {
        const controller = new AbortController(), timeout = setTimeout(() => controller.abort(), 15000);
        try {
        const response = await fetch(url, { method, credentials: 'same-origin', keepalive, signal: controller.signal,
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            ...(method === 'GET' ? {} : { body: JSON.stringify(data || {}) }) });
        const result = await response.json().catch(() => ({}));
        if (response.redirected || !response.ok) throw Object.assign(new Error(result.message || strings.unavailable), { status: response.redirected ? 401 : response.status, errors: result.errors || {} });
        return result;
        } finally { clearTimeout(timeout); }
    }
    function showNotice(message, error = false) { notice.textContent = message; notice.className = `alert ${error ? 'alert-danger' : 'alert-success'}`; notice.hidden = false; }
    async function refresh() {
        const current = ++listRequest;
        table.setAttribute('aria-busy', 'true');
        try {
            const result = await request(`${config.url}?${new URLSearchParams(state)}`, null, 'GET');
            if (current !== listRequest) return;
            const channels = new Map([...table.querySelectorAll('[data-camera-channel]')].map(el => [el.dataset.cameraChannel, el.value]));
            records = result.dashcams.data;
            table.innerHTML = result.html;
            table.querySelectorAll('[data-camera-channel]').forEach(el => { if ([...el.options].some(option => option.value === channels.get(el.dataset.cameraChannel))) el.value = channels.get(el.dataset.cameraChannel); });
            Object.entries(result.stats).forEach(([key,value]) => { document.querySelector(`[data-dashcam-stat="${key}"]`).textContent = value; });
        } catch (error) { if (current === listRequest) showNotice(error.message, true); }
        finally { if (current === listRequest) table.setAttribute('aria-busy', 'false'); }
    }
    function fieldError(name, message = '') {
        const field = fields.namedItem(name), area = form.querySelector(`[data-dashcam-error="${name}"]`);
        field?.classList.toggle('is-invalid', Boolean(message)); field?.setAttribute('aria-invalid', String(Boolean(message)));
        if (area) area.textContent = message;
    }
    function validate(field) {
        if (!field.name || field.name === '_token' || field.matches(':disabled')) return true;
        let message = '';
        if (field.required && !String(field.value).trim()) message = strings.required;
        else if (field.name === 'imei' && !/^\d{15}$/.test(field.value)) message = strings.invalid_imei;
        else if (field.name === 'communication_id' && !/^\d{12}$/.test(field.value)) message = strings.invalid_identifier;
        else if (!field.validity.valid) message = strings.invalid_number;
        fieldError(field.name, message); return !message;
    }
    function profile(year = null) {
        const model = fields.namedItem('model').value, es = model === 'ES500-603';
        document.getElementById('dashcam-fields').disabled = !model;
        document.getElementById('dashcam-identifier-group').hidden = !es;
        fields.namedItem('communication_id').disabled = !es;
        fields.namedItem('communication_id').required = es;
        fieldError('communication_id');
        const protocol = fields.namedItem('protocol_version');
        protocol.replaceChildren(...(es ? ['2013'] : ['2013','2019']).map(year => new Option(`JT808 · ${year}`, year)));
        protocol.value = es ? '2013' : (year || '2019');
        document.getElementById('dashcam-protocol-hint').textContent = strings[es ? 'es_protocol_hint' : 'protocol_hint'];
    }
    function vehicleOptions(selected = '') {
        const select = fields.namedItem('vehicle_id');
        select.replaceChildren(new Option(strings.choose_vehicle, ''), ...options.vehicles.map(vehicle => {
            const option = new Option([vehicle.name, vehicle.registration_number, vehicle.fleet?.name].filter(Boolean).join(' · '), vehicle.id);
            option.dataset.search = vehicle.fleet?.code || '';
            return option;
        }));
        select.value = selected;
        select.dispatchEvent(new Event('searchable-select:refresh'));
        document.getElementById('dashcam-assignment-notice').hidden = options.vehicles.length > 0;
        document.getElementById('dashcam-assignment-message').textContent = strings.no_vehicle;
    }
    async function openForm(record = null) {
        const current = ++openRequest;
        editing = record?.id || null;
        form.reset(); formAlert.hidden = true;
        form.querySelectorAll('[data-dashcam-error]').forEach(el => fieldError(el.dataset.dashcamError));
        document.getElementById('dashcam-form-title').textContent = strings[record ? 'edit' : 'new'];
        (config.isPlatform ? ['model','name','imei','channels','frame_rate'] : ['name']).forEach(key => { if (record) fields.namedItem(key).value = record[key] ?? ''; });
        if (config.isPlatform) { fields.namedItem('communication_id').value = record?.model === 'ES500-603' ? record.terminal_id_2013 : '';
        profile(record?.protocol_version); }
        const submit = form.querySelector('[type="submit"]'); submit.disabled = true;
        bootstrap.Modal.getOrCreateInstance(formModal).show();
        try {
            const result = await request(config.optionsUrl, null, 'GET');
            if (current !== openRequest) return;
            options = result;
            vehicleOptions(record?.vehicle_id || ''); submit.disabled = false;
        } catch (error) { if (current === openRequest) { formAlert.textContent = error.message; formAlert.hidden = false; } }
    }
    formModal.addEventListener('hidden.bs.modal', () => { openRequest++; });
    form.addEventListener('input', event => validate(event.target));
    form.addEventListener('focusout', event => validate(event.target));
    form.addEventListener('change', event => {
        if (event.target.name === 'model') profile();
        validate(event.target);
    });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (form.querySelector('[type="submit"]').disabled) return;
        const valid = [...fields].filter(field => field.matches('input:not([type="hidden"]),select')).map(validate).every(Boolean);
        if (!valid) return form.querySelector('.is-invalid')?.focus();
        const button = form.querySelector('[type="submit"]'); button.disabled = true; formAlert.hidden = true;
        try {
            const result = await request(editing ? `${config.url}/${editing}` : config.url, Object.fromEntries(new FormData(form)), editing ? 'PATCH' : 'POST');
            bootstrap.Modal.getOrCreateInstance(formModal).hide(); showNotice(result.message); if (!editing) state.page = 1;
            await refresh();
        } catch (error) {
            Object.entries(error.errors).forEach(([field, messages]) => fieldError(field, messages[0]));
            formAlert.textContent = error.message; formAlert.hidden = false; form.querySelector('.is-invalid')?.focus();
        } finally { button.disabled = false; }
    });
    const liveSession = new MapVideoChannel({
        request: (url, data, keepalive) => request(url, data, 'POST', keepalive),
        reset({preserveFrame = false} = {}) {
            const still = resetLivePlayer(player, playback, {preserveFrame}); playback = null;
            if (still) modal.dataset.videoReady = '1'; else delete modal.dataset.videoReady;
            if (!preserveFrame) manualPaused = false;
        },
        notify(state, error) { status.textContent = state === 'failed' ? (error?.message || strings.unavailable) : strings[state]; },
        attach(url, onError) {
            playback = attachLivePlayer(player, url, {
                shouldPlay: () => !manualPaused,
                onState(state) {
                    if (state === 'paused') manualPaused = true;
                    if (state === 'ready') { manualPaused = false; liveSession.markPlaying(); }
                    if (['preview','ready','paused','play_required'].includes(state)) modal.dataset.videoReady = '1';
                    if (state !== 'buffering' && state !== 'preview') status.textContent = strings[state];
                }, onError,
            });
        },
    });
    const stop = keepalive => Promise.allSettled([liveSession.stop(keepalive),vehicleAudio.select(null,keepalive)]);
    async function start(device, channel) {
        await vehicleAudio.select(device);
        modal.dataset.cameraModel = records.find(record => String(record.id) === String(device))?.model || '';
        bootstrap.Modal.getOrCreateInstance(modal).show();
        await liveSession.start(`${config.url}/${device}/live`, channel);
    }
    table.addEventListener('click', async event => {
        const page = event.target.closest('[data-page]'), sort = event.target.closest('[data-sort]');
        if (page) { state.page = Number(page.dataset.page); return refresh(); }
        if (sort) { state.direction = state.sort === sort.dataset.sort && state.direction === 'asc' ? 'desc' : 'asc'; state.sort = sort.dataset.sort; state.page = 1; return refresh(); }
        const edit = event.target.closest('[data-dashcam-edit]');
        if (edit) return openForm(records.find(record => String(record.id) === edit.dataset.dashcamEdit));
        const toggle = event.target.closest('[data-dashcam-toggle]'), live = event.target.closest('[data-dashcam-live]');
        if (toggle) {
            const enabled = toggle.dataset.enabled !== '1';
            if (!enabled && !window.confirm(strings.confirm_disable)) return;
            toggle.disabled = true;
            try { const result = await request(`${config.url}/${toggle.dataset.dashcamToggle}`, { enabled }, 'PATCH'); showNotice(result.message); await refresh(); }
            catch (error) { showNotice(error.message, true); toggle.disabled = false; }
        }
        if (live) { live.disabled = true; try { await start(live.dataset.dashcamLive, Number(table.querySelector(`[data-camera-channel="${live.dataset.dashcamLive}"]`).value)); } finally { live.disabled = false; } }
    });
    let searchTimer;
    document.getElementById('dashcam-search').addEventListener('input', event => { clearTimeout(searchTimer); searchTimer = setTimeout(() => { state.search = event.target.value.trim(); state.page = 1; void refresh(); }, 250); });
    document.getElementById('dashcam-page-size').addEventListener('change', event => { state.per_page = Number(event.target.value); state.page = 1; void refresh(); });
    document.getElementById('dashcam-model-filter').addEventListener('change', event => { state.model = event.target.value; state.page = 1; void refresh(); });
    modal.addEventListener('hidden.bs.modal', () => { void stop(); });
    window.addEventListener('pagehide', () => { void stop(true); });
    document.getElementById('dashcam-refresh').addEventListener('click', () => refresh());
    document.getElementById('dashcam-create')?.addEventListener('click', () => openForm());
    document.addEventListener('exadcam:view-changed', event => { if (event.detail.view === 'dashcams') void refresh(); });
    if (location.hash === '#dashcams') void refresh();
    setInterval(() => { if (!module.hidden && !liveSession.active && !formModal.classList.contains('show')) void refresh(); }, 30000);
})();
