// Local searchable selects, adapted from EXAD Tracking for EXADCAM's icons and modals.
(() => {
    'use strict';
    const normalize = value => String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase().trim();
    document.querySelectorAll('select[data-searchable-database]').forEach(select => {
        const root = document.createElement('div'), toggle = document.createElement('button');
        const label = document.createElement('span'), chevron = document.createElement('span');
        const panel = document.createElement('div'), search = document.createElement('input');
        const choices = document.createElement('div'), empty = document.createElement('p');
        const fieldLabel = document.querySelector(`label[for="${CSS.escape(select.id)}"]`);
        root.className = 'searchable-select';
        select.before(root); root.append(select);
        select.classList.add('searchable-select-native'); select.tabIndex = -1; select.setAttribute('aria-hidden', 'true');
        toggle.type = 'button'; toggle.id = `${select.id}-toggle`; toggle.className = 'searchable-select-toggle';
        toggle.setAttribute('aria-expanded', 'false');
        label.id = `${select.id}-value`; chevron.textContent = '⌄'; chevron.setAttribute('aria-hidden', 'true');
        if (fieldLabel) {
            fieldLabel.id ||= `${select.id}-label`;
            fieldLabel.htmlFor = toggle.id;
            toggle.setAttribute('aria-labelledby', `${fieldLabel.id} ${label.id}`);
        }
        toggle.append(label, chevron);
        panel.id = `${select.id}-panel`; panel.className = 'searchable-select-panel'; panel.hidden = true;
        toggle.setAttribute('aria-controls', panel.id);
        search.type = 'search'; search.className = 'searchable-select-search'; search.autocomplete = 'off';
        search.placeholder = select.dataset.searchPlaceholder; search.setAttribute('aria-label', select.dataset.searchPlaceholder);
        choices.className = 'searchable-select-options';
        empty.className = 'searchable-select-empty'; empty.textContent = select.dataset.noResults;
        empty.setAttribute('role', 'status');
        panel.append(search, choices, empty); root.append(toggle, panel);

        const mapPanel = root.closest('.tracking-panel');
        // Keep map dropdowns out of document flow and within the visible panel.
        function positionMapDropdown() {
            if (!mapPanel || panel.hidden) return;
            const bounds = mapPanel.getBoundingClientRect(), anchor = root.getBoundingClientRect();
            const above = Math.max(0,anchor.top - Math.max(8,bounds.top + 8) - 6);
            const below = Math.max(0,Math.min(window.innerHeight - 8,bounds.bottom - 8) - anchor.bottom - 6);
            const opensUp = below < Math.min(240,panel.scrollHeight) && above > below;
            panel.style.top = opensUp ? 'auto' : 'calc(100% + 6px)';
            panel.style.bottom = opensUp ? 'calc(100% + 6px)' : 'auto';
            panel.style.maxHeight = Math.floor(opensUp ? above : below)+'px';
        }
        function close() { panel.hidden = true; root.classList.remove('is-open'); toggle.setAttribute('aria-expanded', 'false'); }
        function sync() {
            label.textContent = select.selectedOptions[0]?.textContent || select.options[0]?.textContent || '';
            toggle.disabled = select.matches(':disabled');
            toggle.classList.toggle('has-value', Boolean(select.value));
            toggle.setAttribute('aria-invalid', select.getAttribute('aria-invalid') || 'false');
            if (select.getAttribute('aria-describedby')) toggle.setAttribute('aria-describedby', select.getAttribute('aria-describedby'));
            if (toggle.disabled) close();
            if (!panel.hidden) render();
        }
        function render() {
            const query = normalize(search.value);
            const matches = [...select.options].filter(option => (option.value || !select.required) && !option.disabled && !option.hidden && normalize(`${option.textContent} ${option.dataset.search || ''} ${option.dataset.status || ''}`).includes(query));
            choices.replaceChildren();
            matches.forEach(option => {
                const button = document.createElement('button'), identity = document.createElement('span');
                const name = document.createElement('strong'), detail = document.createElement('small');
                const [title, ...subtitle] = option.textContent.split(' · ');
                button.type = 'button'; button.className = 'searchable-select-option';
                button.classList.toggle('is-selected', option.value === select.value);
                button.setAttribute('aria-pressed', String(option.value === select.value));
                name.textContent = title; detail.textContent = subtitle.join(' · ');
                identity.append(name, detail);
                if (option.dataset.status) {
                    const status = document.createElement('small'); status.className = 'select-connection';
                    status.dataset.tone = option.dataset.statusTone; status.textContent = option.dataset.status; identity.append(status);
                }
                button.append(identity);
                if (option.value === select.value) { const check = document.createElement('span'); check.textContent = '✓'; check.setAttribute('aria-hidden', 'true'); button.append(check); }
                button.addEventListener('click', () => {
                    select.value = option.value; close(); select.dispatchEvent(new Event('change', { bubbles: true })); sync(); toggle.focus();
                });
                choices.append(button);
            });
            empty.hidden = matches.length > 0;
            positionMapDropdown();
        }
        function open() {
            if (select.matches(':disabled')) return;
            document.querySelectorAll('.searchable-select.is-open').forEach(other => other.dispatchEvent(new Event('searchable-select:close')));
            panel.hidden = false; root.classList.add('is-open'); toggle.setAttribute('aria-expanded', 'true');
            search.value = ''; render();
            if (mapPanel) search.focus({ preventScroll: true });
            else {
                search.focus();
                panel.scrollIntoView({ block: 'nearest', behavior: 'instant' });
            }
        }
        mapPanel?.addEventListener('scroll', close);
        if (mapPanel) window.addEventListener('resize', positionMapDropdown);
        toggle.addEventListener('click', () => panel.hidden ? open() : close());
        search.addEventListener('input', render);
        select.addEventListener('change', sync);
        select.addEventListener('searchable-select:refresh', sync);
        select.addEventListener('focus', () => toggle.focus());
        select.addEventListener('invalid', event => { event.preventDefault(); open(); });
        root.addEventListener('searchable-select:close', close);
        root.addEventListener('keydown', event => {
            if (event.key === 'Escape' && !panel.hidden) { event.preventDefault(); event.stopPropagation(); close(); toggle.focus(); }
            if (event.key === 'Enter' && event.target === search) { event.preventDefault(); choices.querySelector('button')?.click(); }
            if (['ArrowDown','ArrowUp'].includes(event.key)) {
                event.preventDefault();
                if (panel.hidden) { open(); return; }
                const buttons = [...choices.querySelectorAll('button')], index = buttons.indexOf(document.activeElement);
                const next = event.key === 'ArrowDown' ? Math.min(index + 1, buttons.length - 1) : index <= 0 ? -1 : index - 1;
                if (next < 0) search.focus(); else buttons[next]?.focus();
            }
        });
        document.addEventListener('focusin', event => { if (!root.contains(event.target)) close(); });
        document.addEventListener('click', event => { if (!root.contains(event.target)) close(); });
        select.form?.addEventListener('reset', () => { close(); queueMicrotask(sync); });
        select.closest('.modal')?.addEventListener('hidden.bs.modal', close);
        new MutationObserver(sync).observe(select, { childList: true, attributes: true, attributeFilter: ['disabled','class','aria-invalid','aria-describedby'] });
        if (select.closest('fieldset')) new MutationObserver(sync).observe(select.closest('fieldset'), { attributes: true, attributeFilter: ['disabled'] });
        sync();
    });
})();
