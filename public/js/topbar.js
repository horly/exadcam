(() => {
    const topbar = document.querySelector('.corporate-topbar');
    if (!topbar) return;

    const sidebarToggle = topbar.querySelector('[data-sidebar-toggle]');
    const desktop = window.matchMedia('(min-width: 992px)');
    function syncSidebar() {
        const collapsed = desktop.matches && document.body.classList.contains('sidebar-collapsed');
        sidebarToggle.setAttribute('aria-expanded', String(!collapsed));
        sidebarToggle.setAttribute('aria-label', collapsed ? sidebarToggle.dataset.showLabel : sidebarToggle.dataset.hideLabel);
    }
    sidebarToggle.addEventListener('click', () => {
        document.body.classList.toggle('sidebar-collapsed');
        syncSidebar();
    });
    desktop.addEventListener('change', syncSidebar);
    syncSidebar();

    const fullscreen = topbar.querySelector('[data-fullscreen-toggle]');
    const feedback = topbar.querySelector('[data-navbar-feedback]');
    let feedbackTimeout;
    function syncFullscreen() {
        const active = Boolean(document.fullscreenElement);
        const label = active ? fullscreen.dataset.exitLabel : fullscreen.dataset.enterLabel;
        fullscreen.setAttribute('aria-pressed', String(active));
        fullscreen.setAttribute('aria-label', label);
        fullscreen.title = label;
        fullscreen.querySelector('[data-fullscreen-enter]').classList.toggle('d-none', active);
        fullscreen.querySelector('[data-fullscreen-exit]').classList.toggle('d-none', !active);
    }
    fullscreen.addEventListener('click', async () => {
        try {
            if (document.fullscreenElement) await document.exitFullscreen();
            else await document.documentElement.requestFullscreen();
            feedback.hidden = true;
        } catch {
            feedback.textContent = fullscreen.dataset.unavailableLabel;
            feedback.hidden = false;
            window.clearTimeout(feedbackTimeout);
            feedbackTimeout = window.setTimeout(() => { feedback.hidden = true; }, 6000);
        }
    });
    document.addEventListener('fullscreenchange', syncFullscreen);
    syncFullscreen();

    const language = topbar.querySelector('.language-switcher');
    document.addEventListener('click', (event) => {
        if (!language.contains(event.target)) language.open = false;
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && language.open) {
            language.open = false;
            language.querySelector('summary').focus();
        }
    });
})();
