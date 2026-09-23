(() => {
    const languageSwitcher = document.querySelector('.language-switcher');
    if (languageSwitcher) {
        document.addEventListener('click', (event) => {
            if (!languageSwitcher.contains(event.target)) languageSwitcher.open = false;
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && languageSwitcher.open) {
                languageSwitcher.open = false;
                languageSwitcher.querySelector('summary').focus();
            }
        });
    }

    const form = document.getElementById('login-form');
    if (!form) return;

    const email = form.querySelector('#email');
    const password = form.querySelector('#password');
    const fields = [email, password];
    const remember = form.querySelector('#remember');
    const toggle = form.querySelector('[data-password-toggle]');
    const submit = form.querySelector('[data-login-submit]');
    const spinner = form.querySelector('[data-submit-spinner]');
    const arrow = form.querySelector('[data-submit-arrow]');
    const status = form.querySelector('[data-login-status]');
    const alert = document.getElementById('login-error');
    const alertMessage = alert.querySelector('[data-login-error-message]');
    const refresh = alert.querySelector('[data-login-refresh]');
    const touched = new Set(fields.filter((field) => field.classList.contains('is-invalid')));
    const serverErrors = new Set(touched);
    let busy = false;
    let expired = false;

    // Preserve native validation and the standard POST when JavaScript is unavailable.
    form.noValidate = true;
    toggle.hidden = false;

    function setPasswordVisibility(show) {
        password.type = show ? 'text' : 'password';
        toggle.setAttribute('aria-pressed', String(show));
        toggle.setAttribute('aria-label', show ? toggle.dataset.hideLabel : toggle.dataset.showLabel);
        toggle.querySelector('[data-eye-open]').classList.toggle('d-none', show);
        toggle.querySelector('[data-eye-closed]').classList.toggle('d-none', !show);
    }

    toggle.addEventListener('click', () => setPasswordVisibility(password.type === 'password'));

    function setFieldError(field, message = '') {
        const feedback = document.getElementById(`${field.id}-error`);
        field.classList.toggle('is-invalid', Boolean(message));
        field.setAttribute('aria-invalid', String(Boolean(message)));
        feedback.textContent = message;
        feedback.hidden = !message;
    }

    function validate(field) {
        let message = '';
        if (!field.value.trim()) {
            message = field.dataset.requiredMessage;
        } else if ([...field.value].length > field.maxLength) {
            message = field.dataset.maxMessage;
        } else if (field === email && field.validity.typeMismatch) {
            message = field.dataset.emailMessage;
        }
        setFieldError(field, message);
        return !message;
    }

    function clearAlert() {
        alert.hidden = true;
        alertMessage.textContent = '';
        refresh.hidden = true;
    }

    function showAlert(message, refreshRequired = false) {
        alertMessage.textContent = message;
        refresh.hidden = !refreshRequired;
        alert.hidden = false;
        alert.focus();
    }

    function setBusy(value) {
        busy = value;
        submit.disabled = value || expired;
        form.setAttribute('aria-busy', String(value));
        spinner.classList.toggle('d-none', !value);
        arrow.classList.toggle('d-none', value);
        status.textContent = value ? status.dataset.loadingLabel : '';
        fields.forEach((field) => { field.readOnly = value; });
        remember.disabled = value;
    }

    for (const field of fields) {
        field.addEventListener('blur', () => {
            if (busy) return;
            touched.add(field);
            if (field === email) email.value = email.value.trim();
            if (!serverErrors.has(field)) validate(field);
        });
        field.addEventListener('input', () => {
            if (busy) return;
            // Fortify associates bad credentials with email, even when password changes.
            for (const invalid of serverErrors) validate(invalid);
            serverErrors.clear();
            if (!expired) clearAlert();
            if (touched.has(field)) validate(field);
        });
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (busy || expired) return;

        clearAlert();
        serverErrors.clear();
        email.value = email.value.trim();
        const valid = fields.map((field) => {
            touched.add(field);
            return validate(field);
        }).every(Boolean);

        if (!valid) {
            form.querySelector('[aria-invalid="true"]')?.focus();
            return;
        }

        const body = new FormData(form);
        const controller = new AbortController();
        const timeout = window.setTimeout(() => controller.abort(), 20000);
        let navigating = false;
        setBusy(true);

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body,
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                mode: 'same-origin',
                signal: controller.signal,
            });

            if (response.status === 419) {
                expired = true;
                showAlert(form.dataset.sessionError, true);
                return;
            }

            const data = response.headers.get('content-type')?.includes('application/json')
                ? await response.json() : null;

            if (response.ok && data?.two_factor === false && typeof data.redirect === 'string') {
                const destination = new URL(data.redirect, window.location.origin);
                navigating = true;
                window.location.assign(destination.origin === window.location.origin
                    ? destination.href : form.dataset.redirect);
                return;
            }

            if (response.status === 422 && data?.errors) {
                for (const field of fields) {
                    const message = data.errors[field.name]?.[0];
                    if (typeof message === 'string') {
                        setFieldError(field, message);
                        serverErrors.add(field);
                    }
                }
                if (serverErrors.size) {
                    form.querySelector('[aria-invalid="true"]')?.focus();
                } else {
                    showAlert(form.dataset.serverError);
                }
            } else if (response.status === 429) {
                const message = data?.errors?.email?.[0];
                showAlert(typeof message === 'string' ? message : form.dataset.throttleError);
            } else {
                showAlert(form.dataset.serverError);
            }
        } catch {
            showAlert(form.dataset.networkError);
        } finally {
            window.clearTimeout(timeout);
            if (!navigating) setBusy(false);
        }
    });

    window.addEventListener('pageshow', () => {
        setBusy(false);
        setPasswordVisibility(false);
    });

    form.querySelector('[aria-invalid="true"]')?.focus();
})();
