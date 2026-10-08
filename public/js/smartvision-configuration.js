(() => {
    'use strict';
    const configNode = document.getElementById('smartvision-config');
    const root = document.getElementById('smartvision-modal');
    if (!configNode || !root) return;
    const config = JSON.parse(configNode.textContent), text = config.strings;
    const form = document.getElementById('smartvision-form');
    const fields = document.getElementById('sv-fields');
    const save = document.getElementById('sv-save'), reset = document.getElementById('sv-reset');
    const reload = document.getElementById('sv-reload'), notice = document.getElementById('sv-message');
    const modal = bootstrap.Modal.getOrCreateInstance(root);
    const servers = {backup: ['ipbak', 'portbak'], secondary: ['ip2', 'port2'], secondary_backup: ['ipbak2', 'portbak2']};
    const controls = [...fields.querySelectorAll('[name]')];
    let cameraId = null, snapshot = null, baseline = '', busy = false, dirty = false, requestNumber = 0, blocked = false;
    const control = key => form.elements.namedItem(key);
    const message = (value, type = 'info') => {
        notice.textContent = value || '';
        notice.className = `alert alert-${type}`;
        notice.hidden = !value;
    };
    function settings() {
        const values = Object.fromEntries(controls.map(el => [el.name, el.value.trim() || null]));
        Object.entries(servers).forEach(([key, [ip, port]]) => {
            if (values[`${key}_action`] !== 'set') values[ip] = values[port] = null;
        });
        return values;
    }
    function buttons() {
        fields.disabled = busy || !snapshot || blocked;
        save.disabled = busy || !snapshot || blocked;
        reset.disabled = busy || !snapshot || !dirty;
        reload.disabled = busy;
        save.textContent = busy ? text.saving : text.save;
        root.setAttribute('aria-busy', String(busy));
        root.querySelector('.btn-close').disabled = busy;
    }
    function updateDirty() {
        dirty = !!snapshot && JSON.stringify(settings()) !== baseline;
        document.getElementById('sv-dirty').hidden = !dirty;
        buttons();
    }
    function syncServers() {
        Object.entries(servers).forEach(([key, [ip, port]]) => {
            const mode = control(`${key}_action`).value;
            control(ip).disabled = control(port).disabled = mode !== 'set';
            document.getElementById(`sv-hint-${key}`).textContent = text[`${mode}_hint`];
        });
    }
    function clearErrors() {
        controls.forEach(el => { el.classList.remove('is-invalid'); el.removeAttribute('aria-invalid'); });
        root.querySelectorAll('.invalid-feedback').forEach(el => { el.textContent = ''; });
    }
    function render(data) {
        snapshot = data; blocked = false;
        const camera = data.camera;
        document.getElementById('sv-camera').textContent = [camera.vehicle || camera.name, camera.registration, camera.imei].filter(Boolean).join(' · ');
        document.getElementById('sv-state').textContent = text[data.state];
        document.getElementById('sv-stale').hidden = !data.stale;
        let version = '';
        if (data.revision) {
            const when = new Intl.DateTimeFormat(config.locale, {dateStyle: 'short', timeStyle: 'medium', timeZone: 'Africa/Kinshasa'}).format(new Date(data.updated_at));
            version = `${text.revision} ${data.revision} · ${when} · Kinshasa${data.updated_by ? ` · ${text.by} ${data.updated_by}` : ''}`;
        }
        document.getElementById('sv-version').textContent = version;
        controls.forEach(el => { el.value = data.settings?.[el.name] ?? (el.name.endsWith('_action') ? 'keep' : ''); });
        syncServers(); clearErrors();
        baseline = JSON.stringify(settings());
        updateDirty();
    }
    async function request(method, body) {
        const response = await fetch(`${config.url}/${cameraId}/configuration`, {
            signal: AbortSignal.timeout(15000),
            method, credentials: 'same-origin', headers: {
                Accept: 'application/json', 'Content-Type': 'application/json',
                'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value,
            }, ...(body ? {body: JSON.stringify(body)} : {}),
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw {status: response.status, data};
        return data;
    }
    async function load() {
        if (busy) return;
        const number = ++requestNumber;
        busy = true; snapshot = null; dirty = false; blocked = false;
        document.getElementById('sv-dirty').hidden = true;
        document.getElementById('sv-camera').textContent = '';
        document.getElementById('sv-version').textContent = '';
        document.getElementById('sv-state').textContent = text.loading;
        document.getElementById('sv-stale').hidden = true;
        controls.forEach(el => { el.value = el.name.endsWith('_action') ? 'keep' : ''; });
        clearErrors(); syncServers(); buttons(); message(text.loading);
        try {
            const data = await request('GET');
            if (number !== requestNumber) return;
            render(data); message(null);
        } catch (error) {
            if (number === requestNumber) message(error.data?.message || text.error, 'danger');
        } finally {
            if (number === requestNumber) { busy = false; buttons(); }
        }
    }
    document.addEventListener('click', event => {
        const trigger = event.target.closest('[data-smartvision-configuration]');
        if (!trigger || busy) return;
        cameraId = trigger.dataset.smartvisionConfiguration;
        if (!/^\d+$/.test(cameraId)) return;
        modal.show(); load();
    });
    reload.addEventListener('click', () => {
        if (!dirty || window.confirm(text.reload_confirm)) load();
    });
    reset.addEventListener('click', () => { if (snapshot && !busy) { render(snapshot); message(null); } });
    document.getElementById('sv-exadcam').addEventListener('click', () => {
        if (!snapshot || busy || blocked) return;
        control('ip').value = snapshot.defaults.ip;
        control('port').value = snapshot.defaults.port;
        clearErrors(); updateDirty(); message(text.exadcam_selected);
    });
    fields.addEventListener('input', updateDirty);
    fields.addEventListener('change', () => { syncServers(); updateDirty(); });
    root.addEventListener('hide.bs.modal', event => {
        if (busy || (dirty && !window.confirm(text.discard_confirm))) event.preventDefault();
    });
    root.addEventListener('hidden.bs.modal', () => { requestNumber++; snapshot = null; dirty = false; cameraId = null; });
    window.addEventListener('beforeunload', event => { if (dirty || busy) { event.preventDefault(); event.returnValue = ''; } });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (!snapshot || busy || blocked) return;
        const body = {revision: snapshot.revision, context: snapshot.context, settings: settings()};
        busy = true; buttons(); clearErrors(); message(null);
        try {
            const data = await request('PUT', body);
            render(data); message(data.message || text.saved, 'success');
        } catch (error) {
            if (error.status === 409) blocked = true;
            message(error.status === 422 ? text.review_errors : (error.data?.message || text.error), 'danger');
            for (const [path, errors] of Object.entries(error.data?.errors || {})) {
                const key = path.replace(/^settings\./, ''), el = control(key);
                if (!el || !controls.includes(el)) continue;
                el.classList.add('is-invalid'); el.setAttribute('aria-invalid', 'true');
                const feedback = document.getElementById(`sv-error-${key}`);
                if (feedback) feedback.textContent = errors.join(' ');
            }
        } finally { busy = false; buttons(); notice.scrollIntoView({block: 'nearest'}); }
    });
})();
