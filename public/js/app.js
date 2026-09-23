(() => {
    'use strict';

    const views = {
        overview: { title: 'Vue d’ensemble', description: 'Gardez un œil sur votre flotte. Et sur ce qui compte.' },
        fleet: { title: 'Véhicules & dashcams', description: 'Retrouvez vos véhicules, leurs équipements et leur état de connexion.' },
        map: { title: 'Carte', description: 'Suivez les positions et les déplacements de vos véhicules.' },
        alerts: { title: 'Alertes', description: 'Consultez les alertes et retrouvez le contexte de chaque trajet.' },
    };
    const modulePanels = [...document.querySelectorAll('[data-module-view]')];
    modulePanels.forEach(panel => {
        views[panel.dataset.moduleView] = { title: panel.dataset.moduleTitle, description: panel.dataset.moduleDescription };
    });
    const overviewPanels = document.querySelectorAll('.overview-only');
    const fleetPanel = document.querySelector('.fleet-section');
    const alertsPanel = document.querySelector('.alerts-section');
    const videoPanel = document.querySelector('.video-section');
    const detailGrid = document.querySelector('.detail-grid');
    const sidebar = document.getElementById('app-sidebar');

    function showView(requested, moveFocus = false) {
        const view = Object.hasOwn(views, requested) ? requested : 'overview';
        document.body.dataset.view = view;
        const pending = modulePanels.some(panel => panel.dataset.moduleView === view);
        document.body.dataset.modulePending = String(pending);
        const label = document.querySelector('.corporate-topbar').dataset[`${view}Title`] || views[view].title;
        document.querySelector('[data-view-title]').textContent = label;
        document.querySelectorAll('[data-view-label]').forEach(element => { element.textContent = label; });
        document.querySelector('[data-view-description]').textContent = views[view].description;
        document.title = `${label} · EXADCAM`;
        overviewPanels.forEach(panel => {
            panel.hidden = view !== 'overview' && !(view === 'map' && panel.classList.contains('monitoring-grid'));
        });
        modulePanels.forEach(panel => { panel.hidden = panel.dataset.moduleView === 'map' ? !['overview', 'map'].includes(view) : panel.dataset.moduleView !== view; });
        const demo = document.querySelector('.demo-notice'); if (demo) demo.hidden = pending;
        if (fleetPanel) fleetPanel.hidden = !['overview', 'fleet'].includes(view);
        if (alertsPanel) alertsPanel.hidden = !['overview', 'alerts'].includes(view);
        if (videoPanel) videoPanel.hidden = view !== 'video';
        if (detailGrid) detailGrid.hidden = !['overview', 'fleet', 'alerts'].includes(view);
        document.querySelectorAll('.sidebar-nav [data-nav]').forEach(link => {
            const active = link.dataset.nav === view;
            link.classList.toggle('active', active);
            if (active) link.setAttribute('aria-current', 'page');
            else link.removeAttribute('aria-current');
        });
        sidebar.querySelectorAll('.sidebar-group').forEach(group => {
            const active = Boolean(group.querySelector('[aria-current="page"]'));
            group.classList.toggle('has-active', active);
            if (active && moveFocus) group.open = true;
        });
        window.bootstrap?.Offcanvas.getInstance(sidebar)?.hide();
        document.dispatchEvent(new CustomEvent('exadcam:view-changed', { detail: { view } }));
        if (moveFocus) {
            document.getElementById('main-content').focus({ preventScroll: true });
            window.scrollTo({ top: 0, behavior: 'instant' });
        }
    }

    function navigate(view) {
        if (location.hash === `#${view}`) showView(view, true);
        else location.hash = view;
    }

    document.querySelectorAll('[data-nav]').forEach(link => {
        link.addEventListener('click', event => {
            event.preventDefault();
            navigate(link.dataset.nav);
        });
    });
    document.querySelectorAll('[data-switch-view]').forEach(button => {
        button.addEventListener('click', () => navigate(button.dataset.switchView));
    });
    sidebar.querySelectorAll('[data-bs-toggle="modal"]').forEach(button => {
        button.addEventListener('click', () => window.bootstrap?.Offcanvas.getInstance(sidebar)?.hide());
    });
    window.addEventListener('hashchange', () => showView(location.hash.slice(1), true));
    showView(location.hash.slice(1));

    document.getElementById('compact-display').addEventListener('change', event => {
        document.body.classList.toggle('compact-tables', event.target.checked);
    });
})();
