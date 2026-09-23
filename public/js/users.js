(() => {
    'use strict';
    const configElement = document.getElementById('users-config');
    if (!configElement) return;
    const config = JSON.parse(configElement.textContent);
    const text = (key, replacements = {}) => Object.entries(replacements).reduce(
        (value, [name, replacement]) => value.replaceAll(`:${name}`, String(replacement)), config.strings[key] ?? key);
    const byId = id => document.getElementById(id);
    const table = byId('users-table');
    const form = byId('user-form');
    const formElement = byId('user-form-modal');
    const historyElement = byId('user-history-modal');
    const deleteElement = byId('user-delete-modal');
    const formModal = bootstrap.Modal.getOrCreateInstance(formElement);
    const historyModal = bootstrap.Modal.getOrCreateInstance(historyElement);
    const deleteModal = bootstrap.Modal.getOrCreateInstance(deleteElement);
    const fields = Object.fromEntries(['name', 'email', 'role', 'fleet_id', 'phone', 'address', 'password', 'password_confirmation']
        .map(name => [name, form.elements.namedItem(name)]));
    const permissionInputs = [...form.querySelectorAll('[name="permissions[]"]')];
    const state = { search: '', sort: 'id', direction: 'desc', page: 1, per_page: 5 };
    const historyState = { search: '', sort: 'logged_in_at', direction: 'desc', page: 1 };
    let records = new Map();
    let fleets = [];
    let loaded = false;
    let listRequest;
    let historyRequest;
    let formRequest;
    let editingId = null;
    let historyId = null;
    let deletingId = null;
    let saving = false;
    let deleting = false;
    let formReady = false;
    let attempted = false;
    const touched = new Set();

    function showMessage(element, message) {
        element.textContent = message;
        element.hidden = !message;
    }

    async function request(url, options = {}) {
        const response = await fetch(url, {
            credentials: 'same-origin', ...options,
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        });
        if (response.redirected || response.status === 401 || response.status === 419) throw { message: text('session_expired') };
        const json = response.headers.get('content-type')?.includes('application/json') ? await response.json() : {};
        if (!response.ok) {
            const message = response.status === 403 ? text('forbidden') : response.status === 404 ? text('not_found')
                : response.status === 422 ? text('invalid_form') : response.status === 429 ? text('too_many') : text('request_failed');
            throw { message, errors: response.status === 422 ? json.errors : null };
        }
        return json;
    }

    function errorMessage(error) { return error instanceof Error ? text('network_error') : error.message || text('request_failed'); }

    async function loadUsers(focusSelector = null) {
        listRequest?.abort();
        const controller = new AbortController();
        listRequest = controller;
        table.setAttribute('aria-busy', 'true');
        table.inert = true;
        showMessage(byId('users-list-error'), '');
        try {
            const payload = await request(`${config.url}?${new URLSearchParams(state)}`, { signal: controller.signal });
            if (controller.signal.aborted) return;
            if (state.page > payload.meta.last_page) {
                state.page = payload.meta.last_page;
                return loadUsers(focusSelector);
            }
            // Ce fragment provient de Blade : toutes les valeurs utilisateur y sont échappées.
            table.innerHTML = payload.html;
            records = new Map(payload.records.map(record => [String(record.id), record]));
            Object.entries(payload.stats).forEach(([name, value]) => {
                const target = document.querySelector(`[data-users-stat="${name}"]`);
                if (target) target.textContent = value;
            });
            loaded = true;
            table.inert = false;
            if (focusSelector) table.querySelector(focusSelector)?.focus({ preventScroll: true });
        } catch (error) {
            if (error.name !== 'AbortError') {
                showMessage(byId('users-list-error'), errorMessage(error));
                table.replaceChildren();
                records.clear();
            }
        } finally {
            if (listRequest === controller) {
                table.setAttribute('aria-busy', 'false');
                table.inert = false;
            }
        }
    }

    function activate() {
        if (document.body.dataset.view === 'users' && !loaded) loadUsers();
    }
    document.addEventListener('exadcam:view-changed', activate);
    activate();

    const debounce = (callback, delay = 280) => {
        let timer;
        return (...args) => { clearTimeout(timer); timer = setTimeout(() => callback(...args), delay); };
    };
    byId('users-search').addEventListener('input', debounce(() => {
        state.search = byId('users-search').value.trim(); state.page = 1; loadUsers();
    }));
    byId('users-page-size').addEventListener('change', event => {
        state.per_page = Number(event.target.value); state.page = 1; loadUsers();
    });
    byId('users-refresh').addEventListener('click', () => loadUsers());

    function updatePaging(event, targetState, reload) {
        const sort = event.target.closest('[data-sort]');
        const page = event.target.closest('[data-page]');
        if (sort) {
            targetState.direction = targetState.sort === sort.dataset.sort && targetState.direction === 'asc' ? 'desc' : 'asc';
            targetState.sort = sort.dataset.sort; targetState.page = 1;
            reload(`[data-sort="${sort.dataset.sort}"]`);
        } else if (page && !page.disabled) {
            targetState.page = Number(page.dataset.page); reload();
        }
    }
    table.addEventListener('click', event => {
        updatePaging(event, state, loadUsers);
        const edit = event.target.closest('[data-user-edit]');
        const history = event.target.closest('[data-user-history]');
        const remove = event.target.closest('[data-user-delete]');
        if (edit) openForm(records.get(edit.dataset.userEdit));
        if (history) openHistory(records.get(history.dataset.userHistory));
        if (remove) openDelete(records.get(remove.dataset.userDelete));
    });

    function fieldError(name, message) {
        const key = name.startsWith('permissions') ? 'permissions' : name;
        const output = form.querySelector(`[data-field-error="${key}"]`);
        if (output) { output.textContent = message; output.classList.toggle('d-block', Boolean(message)); }
        const field = fields[key];
        if (field) { field.classList.toggle('is-invalid', Boolean(message)); field.setAttribute('aria-invalid', String(Boolean(message))); }
    }
    function passwordRules(value) {
        return { min: [...value].length >= 12, mixed: /\p{Ll}/u.test(value) && /\p{Lu}/u.test(value),
            number: /\p{N}/u.test(value), symbol: /[^\p{L}\p{M}\p{N}\s]/u.test(value) };
    }
    function validate(name) {
        const field = fields[name];
        if (!field) return true;
        const value = field.value;
        let error = '';
        const required = field.required || (name === 'password_confirmation' && fields.password.value !== '');
        if (required && !value.trim()) error = text('required_field');
        else if (name === 'email' && value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) error = text('email_invalid');
        else if (name === 'password' && value && new TextEncoder().encode(value).length > 72) error = text('password_max');
        else if (name === 'password' && value && !Object.values(passwordRules(value)).every(Boolean)) error = text('password_invalid');
        else if (name === 'password_confirmation' && value !== fields.password.value) error = text('password_match');
        else if (field.maxLength > 0 && [...value].length > field.maxLength) error = text('field_max', { max: field.maxLength });
        fieldError(name, error);
        return !error;
    }
    function passwordFeedback() {
        Object.entries(passwordRules(fields.password.value)).forEach(([rule, valid]) => {
            const element = form.querySelector(`[data-user-password-rule="${rule}"]`);
            element.classList.toggle('is-valid', Boolean(fields.password.value) && valid);
        });
    }
    Object.entries(fields).forEach(([name, field]) => {
        field.addEventListener('blur', () => { touched.add(name); validate(name); });
        field.addEventListener('input', () => {
            if (touched.has(name) || attempted) validate(name);
            if (name === 'password') {
                passwordFeedback();
                if (touched.has('password_confirmation') || attempted) validate('password_confirmation');
            }
        });
        field.addEventListener('change', () => { if (touched.has(name) || attempted) validate(name); });
    });
    form.querySelectorAll('[data-user-password]').forEach(button => button.addEventListener('click', () => {
        const input = byId(button.dataset.userPassword);
        const showing = input.type === 'password';
        input.type = showing ? 'text' : 'password';
        button.setAttribute('aria-pressed', String(showing));
        button.setAttribute('aria-label', text(showing ? 'hide_password' : 'show_password'));
    }));
    function updatePermissions() {
        const isAdmin = fields.role.value === 'admin';
        byId('user-permissions-panel').hidden = isAdmin;
        byId('user-admin-permissions').hidden = !isAdmin;
        permissionInputs.forEach(input => { input.disabled = isAdmin; });
    }
    fields.role.addEventListener('change', updatePermissions);

    function fillFleets(selected = fields.fleet_id.value) {
        const query = byId('user-fleet-search').value.trim().toLocaleLowerCase();
        const visible = fleets.filter(fleet => String(fleet.id) === String(selected) || `${fleet.name} ${fleet.code}`.toLocaleLowerCase().includes(query));
        fields.fleet_id.replaceChildren(new Option(visible.length ? text('choose_fleet') : text('no_fleet_match'), ''));
        visible.forEach(fleet => fields.fleet_id.add(new Option(`${fleet.name} · ${fleet.code}`, fleet.id)));
        fields.fleet_id.value = String(selected ?? '');
    }
    byId('user-fleet-search').addEventListener('input', () => fillFleets());

    async function openForm(record = null) {
        if (saving) return;
        formRequest?.abort();
        const controller = new AbortController();
        formRequest = controller;
        editingId = record?.id ?? null;
        attempted = false; touched.clear(); formReady = false;
        form.reset();
        form.querySelectorAll('[data-field-error]').forEach(element => { element.textContent = ''; element.classList.remove('d-block'); });
        Object.values(fields).forEach(field => { field.disabled = false; field.classList.remove('is-invalid'); field.removeAttribute('aria-invalid'); });
        fields.password.type = fields.password_confirmation.type = 'password';
        form.querySelectorAll('[data-user-password]').forEach(button => { button.setAttribute('aria-pressed', 'false'); button.setAttribute('aria-label', text('show_password')); });
        if (record) Object.entries(fields).forEach(([name, field]) => { if (!name.startsWith('password') && name !== 'fleet_id') field.value = record[name] ?? ''; });
        permissionInputs.forEach(input => { input.checked = record?.permissions.includes(input.value) ?? false; });
        fields.password.required = fields.password_confirmation.required = !record;
        byId('user-form-title').textContent = text(record ? 'edit_title' : 'create_title');
        byId('user-password-hint').textContent = text(record ? 'password_keep' : 'password_hint');
        byId('user-save').textContent = text(record ? 'update_button' : 'create_button');
        byId('user-save').disabled = true;
        byId('user-fleet-search').value = '';
        byId('user-fleet-notice').hidden = true;
        fields.fleet_id.replaceChildren(new Option(text('loading'), ''));
        showMessage(byId('user-form-alert'), '');
        passwordFeedback(); updatePermissions(); formModal.show();
        try {
            const payload = await request(config.optionsUrl, { signal: controller.signal });
            if (controller.signal.aborted) return;
            fleets = payload.fleets;
            fillFleets(record?.fleet_id ?? (!config.isPlatform && fleets.length === 1 ? fleets[0].id : ''));
            byId('user-fleet-notice').hidden = fleets.length !== 0;
            byId('user-fleet-search').hidden = !config.isPlatform;
            fields.fleet_id.disabled = !config.isPlatform;
            formReady = fleets.length > 0;
            byId('user-save').disabled = !formReady;
            if (record?.fleet_id && !fleets.some(fleet => fleet.id === record.fleet_id)) fieldError('fleet_id', text('fleet_required'));
        } catch (error) {
            if (error.name !== 'AbortError') showMessage(byId('user-form-alert'), errorMessage(error));
        }
    }
    byId('user-create').addEventListener('click', () => openForm());
    formElement.addEventListener('shown.bs.modal', () => fields.name.focus());
    formElement.addEventListener('hide.bs.modal', event => { if (saving) event.preventDefault(); });
    formElement.addEventListener('hidden.bs.modal', () => {
        formRequest?.abort(); fields.password.value = fields.password_confirmation.value = '';
        (editingId ? table.querySelector(`[data-user-edit="${editingId}"]`) : byId('user-create'))?.focus({ preventScroll: true });
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (saving || !formReady) return;
        attempted = true;
        const valid = Object.keys(fields).map(validate).every(Boolean);
        if (!valid) { form.querySelector('.is-invalid')?.focus(); return; }
        const payload = Object.fromEntries(Object.entries(fields).map(([name, field]) => [name, field.value]));
        payload.permissions = fields.role.value === 'user' ? permissionInputs.filter(input => input.checked).map(input => input.value) : [];
        saving = true;
        byId('user-save').disabled = true; byId('user-save').textContent = text('processing');
        showMessage(byId('user-form-alert'), '');
        try {
            const result = await request(editingId ? `${config.url}/${editingId}` : config.url,
                { method: editingId ? 'PUT' : 'POST', body: JSON.stringify(payload) });
            saving = false; formModal.hide();
            showMessage(byId('users-notice'), result.message);
            if (!editingId) { state.search = ''; state.page = 1; byId('users-search').value = ''; }
            await loadUsers();
        } catch (error) {
            showMessage(byId('user-form-alert'), errorMessage(error));
            Object.entries(error.errors ?? {}).forEach(([name, messages]) => fieldError(name, messages[0]));
            form.querySelector('.is-invalid')?.focus();
        } finally {
            saving = false; byId('user-save').disabled = !formReady;
            byId('user-save').textContent = text(editingId ? 'update_button' : 'create_button');
        }
    });

    function openDelete(record) {
        if (!record || deleting) return;
        deletingId = record.id;
        showMessage(byId('user-delete-error'), '');
        byId('user-delete-description').textContent = text('delete_confirm_message', { name: record.name });
        deleteModal.show();
    }
    deleteElement.addEventListener('hide.bs.modal', event => { if (deleting) event.preventDefault(); });
    byId('user-delete-confirm').addEventListener('click', async () => {
        if (deleting || deletingId === null) return;
        deleting = true;
        byId('user-delete-confirm').disabled = true;
        byId('user-delete-confirm').textContent = text('processing');
        try {
            const result = await request(`${config.url}/${deletingId}`, { method: 'DELETE' });
            deleting = false; deleteModal.hide();
            showMessage(byId('users-notice'), result.message);
            await loadUsers();
        } catch (error) { showMessage(byId('user-delete-error'), errorMessage(error)); }
        finally {
            deleting = false; byId('user-delete-confirm').disabled = false;
            byId('user-delete-confirm').textContent = text('delete_confirm_submit');
        }
    });

    function openHistory(record) {
        if (!record) return;
        historyId = record.id;
        Object.assign(historyState, { search: '', sort: 'logged_in_at', direction: 'desc', page: 1 });
        byId('user-history-search').value = '';
        byId('user-history-title').textContent = text('login_history_title', { name: record.name });
        byId('user-history-table').replaceChildren();
        historyModal.show(); loadHistory();
    }
    async function loadHistory() {
        if (historyId === null) return;
        historyRequest?.abort();
        const controller = new AbortController();
        historyRequest = controller;
        const target = byId('user-history-table');
        target.setAttribute('aria-busy', 'true'); target.inert = true;
        showMessage(byId('user-history-error'), '');
        try {
            const result = await request(`${config.url}/${historyId}/login-history?${new URLSearchParams(historyState)}`, { signal: controller.signal });
            if (!controller.signal.aborted) target.innerHTML = result.html;
        } catch (error) {
            if (error.name !== 'AbortError') { target.replaceChildren(); showMessage(byId('user-history-error'), errorMessage(error)); }
        } finally {
            if (historyRequest === controller) { target.setAttribute('aria-busy', 'false'); target.inert = false; }
        }
    }
    byId('user-history-search').addEventListener('input', debounce(() => {
        historyState.search = byId('user-history-search').value.trim(); historyState.page = 1; loadHistory();
    }));
    byId('user-history-table').addEventListener('click', event => updatePaging(event, historyState, loadHistory));
    historyElement.addEventListener('hidden.bs.modal', () => {
        historyRequest?.abort();
        table.querySelector(`[data-user-history="${historyId}"]`)?.focus({ preventScroll: true });
        historyId = null;
    });
})();
