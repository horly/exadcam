(() => {
    'use strict';
    const configNode = document.getElementById('dashcam-config');
    if (!configNode) return;
    const config = JSON.parse(configNode.textContent), strings = config.strings;
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    async function request(url, method = 'GET', data) {
        const response = await fetch(url, { method, credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf }, ...(data ? { body: JSON.stringify(data) } : {}) });
        const result = await response.json().catch(() => ({}));
        if (!response.ok) throw Object.assign(new Error(result.message || strings.unavailable), { errors: result.errors || {} });
        return result;
    }
    document.querySelectorAll('[data-registry-module]').forEach(module => {
        const kind = module.dataset.registryModule, singular = { fleets: 'fleet', vehicles: 'vehicle', departments: 'department' }[kind];
        const table = module.querySelector('[data-registry-table]'), notice = module.querySelector('[data-registry-notice]');
        const modal = document.getElementById(`registry-${kind}-modal`);
        if (!table) return;
        const form = modal?.querySelector('form'), alert = modal?.querySelector('[data-registry-error]');
        const fleetSelect = form?.elements.namedItem('fleet_id'), departmentSelect = form?.elements.namedItem('department_id');
        let departments = [];
        function syncDepartments(selected = '') {
            if (!departmentSelect) return;
            const fleetId = fleetSelect.value;
            const available = departments.filter(item => String(item.fleet_id) === fleetId);
            departmentSelect.replaceChildren(new Option(strings.no_department, ''), ...available.map(item => new Option([item.name, item.code].filter(Boolean).join(' · '), item.id)));
            departmentSelect.value = available.some(item => String(item.id) === String(selected)) ? String(selected) : '';
            departmentSelect.disabled = !fleetId;
            departmentSelect.dispatchEvent(new Event('searchable-select:refresh'));
            modal.querySelector('[data-department-hint]').textContent = !fleetId ? strings.department_choose_fleet : !available.length ? strings.no_departments_in_fleet : strings.department_hint;
            fieldError('department_id');
        }
        fleetSelect?.addEventListener('change', () => syncDepartments(departmentSelect?.value));
        const state = { search: '', page: 1, per_page: 10, sort: 'id', direction: 'desc' };
        let records = [], editing = null, sequence = 0, opening = 0, searchTimer;
        function message(text, error = false) { notice.textContent = text; notice.className = `alert ${error ? 'alert-danger' : 'alert-success'}`; notice.hidden = false; }
        async function refresh() {
            const current = ++sequence; table.setAttribute('aria-busy', 'true');
            try {
                const result = await request(`${config.registryUrl}/${kind}?${new URLSearchParams(state)}`);
                if (current !== sequence) return;
                records = result.records.data; table.innerHTML = result.html;
            } catch (error) { if (current === sequence) message(error.message, true); }
            finally { if (current === sequence) table.setAttribute('aria-busy', 'false'); }
        }
        function fieldError(name, text = '') {
            const field = form?.elements.namedItem(name), target = form.querySelector(`[data-registry-field="${name}"]`);
            field?.classList.toggle('is-invalid', Boolean(text)); field?.setAttribute('aria-invalid', String(Boolean(text)));
            if (target) target.textContent = text;
        }
        function validate(field) {
            if (field.disabled || !field.name || field.name === '_token') return true;
            const error = field.required && !field.value.trim() ? strings.required : !field.validity.valid ? strings.invalid_number : '';
            fieldError(field.name, error); return !error;
        }
        async function open(record = null) {
            if (!form) return;
            const current = ++opening;
            editing = record?.id || null; form.reset(); alert.hidden = true;
            modal.querySelector('[data-registry-title]').textContent = strings[`${record ? 'edit' : 'new'}_${singular}`];
            form.querySelectorAll('[data-registry-field]').forEach(target => {
                const key = target.dataset.registryField; fieldError(key);
                if (record) form.elements.namedItem(key).value = record[key] ?? '';
            });
            const submit = form.querySelector('[type="submit"]'); submit.disabled = kind !== 'fleets';
            if (fleetSelect) { fleetSelect.disabled = true; departments = []; syncDepartments(); }
            bootstrap.Modal.getOrCreateInstance(modal).show();
            if (kind !== 'fleets') {
                try {
                    const options = await request(config.optionsUrl);
                    if (current !== opening) return;
                    const fleet = form?.elements.namedItem('fleet_id');
                    fleet.replaceChildren(new Option(strings.choose_fleet, ''), ...options.fleets.map(item => new Option(`${item.name} · ${item.code}`, item.id)));
                    fleet.value = record?.fleet_id || (config.isPlatform ? '' : config.fleetId);
                    if (!config.isPlatform) fleet.disabled = true;
                    fleet.disabled = !config.isPlatform;
                    departments = options.departments || [];
                    syncDepartments(record?.department_id || '');
                    fleet.dispatchEvent(new Event('searchable-select:refresh'));
                    if (!options.fleets.length) { alert.textContent = strings.no_fleet; alert.hidden = false; }
                    submit.disabled = false;
                } catch (error) { if (current === opening) { alert.textContent = error.message; alert.hidden = false; } }
            }
        }
        modal?.addEventListener('hidden.bs.modal', () => { opening++; });
        form?.addEventListener('input', event => validate(event.target));
        form?.addEventListener('change', event => validate(event.target));
        form?.addEventListener('focusout', event => validate(event.target));
        form?.addEventListener('submit', async event => {
            event.preventDefault();
            const submit = form.querySelector('[type="submit"]'); if (submit.disabled) return;
            if (![...form.elements].filter(field => field.matches('input,select')).map(validate).every(Boolean)) return form.querySelector('.is-invalid')?.focus();
            submit.disabled = true; alert.hidden = true;
            try {
                const result = await request(`${config.registryUrl}/${kind}${editing ? `/${editing}` : ''}`, editing ? 'PATCH' : 'POST', Object.fromEntries(new FormData(form)));
                bootstrap.Modal.getOrCreateInstance(modal).hide(); message(result.message); state.page = 1; await refresh();
            } catch (error) { Object.entries(error.errors).forEach(([name,messages]) => fieldError(name,messages[0])); alert.textContent = error.message; alert.hidden = false; form.querySelector('.is-invalid')?.focus(); }
            finally { submit.disabled = false; }
        });
        table.addEventListener('click', event => {
            const page = event.target.closest('[data-page]'), sort = event.target.closest('[data-sort]'), edit = event.target.closest('[data-registry-edit]');
            if (page) { state.page = Number(page.dataset.page); void refresh(); }
            if (sort) { state.direction = state.sort === sort.dataset.sort && state.direction === 'asc' ? 'desc' : 'asc'; state.sort = sort.dataset.sort; state.page = 1; void refresh(); }
            if (edit) void open(records.find(record => String(record.id) === edit.dataset.registryEdit));
        });
        module.querySelector('[data-registry-create]')?.addEventListener('click', () => open());
        module.querySelector('[data-registry-refresh]').addEventListener('click', refresh);
        module.querySelector('[data-registry-search]').addEventListener('input', event => { clearTimeout(searchTimer); searchTimer = setTimeout(() => { state.search = event.target.value.trim(); state.page = 1; void refresh(); }, 250); });
        module.querySelector('[data-registry-size]').addEventListener('change', event => { state.per_page = Number(event.target.value); state.page = 1; void refresh(); });
        document.addEventListener('exadcam:view-changed', event => { if (event.detail.view === kind) void refresh(); });
        if (location.hash === `#${kind}`) void refresh();
    });
})();
