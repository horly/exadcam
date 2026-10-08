(() => {
    'use strict';
    const node = document.getElementById('customization-config');
    if (!node) return;
    const config = JSON.parse(node.textContent), labels = config.labels;
    const form = document.getElementById('customization-form'), errorBox = document.getElementById('customization-error');
    const save = document.getElementById('customization-save'), cancel = document.getElementById('customization-cancel');
    const restore = document.getElementById('customization-restore'), restoreStatus = document.getElementById('customization-restore-status');
    const selected = new Map(), root = document.documentElement, initialName = document.getElementById('customization-sidebar-name').textContent;
    let saving = false;
    const field = name => form.elements.namedItem(name);
    const validColor = value => /^#[0-9a-f]{6}$/i.test(value);
    function foreground(hex) {
        const rgb = hex.slice(1).match(/../g).map(v => parseInt(v,16)/255).map(v => v <= .04045 ? v/12.92 : ((v+.055)/1.055)**2.4);
        return rgb[0]*.2126+rgb[1]*.7152+rgb[2]*.0722 > .179 ? '#102033' : '#ffffff';
    }
    function applyColors(colors) {
        for (const [key,color] of Object.entries(colors)) if (validColor(color)) root.style.setProperty('--brand-'+key.replace('_color','').replaceAll('_','-'),color);
        for (const [key,token] of [['button_color','button'],['avatar_color','avatar'],['sidebar_end_color','sidebar']]) {
            if (validColor(colors[key])) root.style.setProperty('--brand-'+token+'-text',foreground(colors[key]));
        }
    }
    function previewColors() {
        if (!config.global) return;
        const values = Object.fromEntries([...form.querySelectorAll('[data-color]')].map(input => [input.name,input.value]));
        applyColors(values);
    }
    function removed(kind) { return Boolean(field('remove_'+kind)?.checked); }
    function globalImage(kind) {
        if (selected.has(kind)) return selected.get(kind);
        if (kind === 'internal_logo') {
            if (!removed(kind) && config.hasImages[kind]) return config.images[kind];
            return selected.has('logo') || (!removed('logo') && config.hasImages.logo) ? globalImage('logo') : config.fallback.internal_logo;
        }
        return removed(kind) ? config.fallback[kind] : config.images[kind];
    }
    function previewImages() {
        for (const img of form.querySelectorAll('[data-custom-image]')) {
            img.src = config.global ? globalImage(img.dataset.customImage) : selected.get('logo') || (removed('logo') ? config.images.internal_logo : config.sidebarLogo);
        }
        document.getElementById('customization-sidebar-logo').src = config.global ? globalImage('internal_logo') : selected.get('logo') || (removed('logo') ? config.images.internal_logo : config.sidebarLogo);
        document.getElementById('customization-sidebar-name').textContent = config.global ? field('short_name').value || initialName : 'EXADCAM';
    }
    function clearErrors() {
        errorBox.hidden = true; errorBox.textContent = '';
        for (const error of form.querySelectorAll('[data-field-error]')) { error.hidden = true; error.textContent = ''; }
        for (const input of form.querySelectorAll('[aria-invalid]')) input.removeAttribute('aria-invalid');
    }
    function fieldError(name,message) {
        const error = [...form.querySelectorAll('[data-field-error]')].find(item=>item.dataset.fieldError===name);
        if (error) { error.textContent = message; error.hidden = false; }
        field(name)?.setAttribute('aria-invalid','true');
    }
    for (const input of form.querySelectorAll('[data-image-input]')) input.addEventListener('change', () => {
        clearErrors(); const kind = input.dataset.imageInput, file = input.files[0];
        if (selected.has(kind)) { URL.revokeObjectURL(selected.get(kind)); selected.delete(kind); }
        if (file) {
            if (!['image/png','image/jpeg','image/webp'].includes(file.type) || file.size > Number(input.dataset.maxSize)) {
                fieldError(kind,file.size > Number(input.dataset.maxSize) ? labels.image_size : labels.invalid_image); input.value = ''; previewImages(); return;
            }
            selected.set(kind,URL.createObjectURL(file));
            if (field('remove_'+kind)) field('remove_'+kind).checked = false;
        }
        previewImages();
    });
    for (const input of form.querySelectorAll('[data-remove-image]')) input.addEventListener('change', () => {
        const kind = input.dataset.removeImage;
        if (input.checked) { field(kind).value = ''; if (selected.has(kind)) URL.revokeObjectURL(selected.get(kind)); selected.delete(kind); }
        previewImages();
    });
    for (const input of form.querySelectorAll('[data-color]')) input.addEventListener('input', () => {
        form.querySelector(`[data-color-hex="${input.name}"]`).value = input.value.toUpperCase(); previewColors();
    });
    for (const input of form.querySelectorAll('[data-color-hex]')) input.addEventListener('input', () => {
        if (validColor(input.value)) { field(input.dataset.colorHex).value = input.value; previewColors(); }
    });
    field('short_name')?.addEventListener('input',previewImages);
    document.getElementById('customization-restore-colors')?.addEventListener('click', () => {
        for (const [key,value] of Object.entries(config.defaults)) { field(key).value = value; form.querySelector(`[data-color-hex="${key}"]`).value = value.toUpperCase(); }
        previewColors();
    });
    restore.addEventListener('click', () => {
        if (saving) return;
        clearErrors();
        for (const url of selected.values()) URL.revokeObjectURL(url);
        selected.clear();
        for (const input of form.querySelectorAll('[data-image-input]')) input.value = '';
        for (const input of form.querySelectorAll('[data-remove-image]')) input.checked = true;
        if (config.global) {
            for (const [key,value] of Object.entries(config.defaultValues)) field(key).value = value ?? '';
            for (const input of form.querySelectorAll('[data-color-hex]')) input.value = field(input.dataset.colorHex).value.toUpperCase();
            previewColors();
        } else {
            field('fleet_name').value = field('fleet_name').defaultValue;
        }
        previewImages(); restoreStatus.hidden = false;
    });
    cancel.addEventListener('click', () => {
        form.reset(); clearErrors(); restoreStatus.hidden = true;
        for (const url of selected.values()) URL.revokeObjectURL(url);
        selected.clear(); applyColors(config.colors); previewImages();
    });
    document.addEventListener('exadcam:view-changed', event => {
        if (config.global) { if (event.detail.view === 'customization') previewColors(); else applyColors(config.colors); }
    });
    form.addEventListener('submit', async event => {
        event.preventDefault(); if (saving || !form.reportValidity()) return;
        clearErrors(); saving = true; const payload = new FormData(form);
        save.disabled = cancel.disabled = restore.disabled = true; save.querySelector('span').textContent = labels.saving;
        try {
            const response = await fetch(form.action,{method:'POST',body:payload,headers:{Accept:'application/json','X-CSRF-TOKEN':field('_token').value},credentials:'same-origin'});
            if (response.redirected || [401,403,419].includes(response.status)) throw new Error(labels.session);
            const data = await response.json();
            if (!response.ok) {
                for (const [name,messages] of Object.entries(data.errors || {})) fieldError(name,messages.join(' '));
                throw new Error(labels.failed);
            }
            // Reload the saved branding, including favicon and shared layout text.
            window.location.reload();
        } catch (error) { errorBox.textContent = error.message || labels.failed; errorBox.hidden = false; errorBox.focus(); }
        finally { saving = false; save.disabled = cancel.disabled = restore.disabled = false; save.querySelector('span').textContent = labels.save; }
    });
})();
