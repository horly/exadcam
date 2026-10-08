export function dashboardConnection(hash) {
    const [view, query = ''] = String(hash).replace(/^#/, '').split('?');
    if (view !== 'map') return '';
    const status = new URLSearchParams(query).get('connection');
    return status === 'online' || status === 'offline' ? status : '';
}

export function matchesMapState(vehicle, state) {
    if (!state) return true;
    if (state === 'online') return vehicle.online === true;
    // A vehicle without an active camera is a separate state, not an offline device.
    if (state === 'offline') return vehicle.source_id != null && vehicle.online === false;
    return vehicle.state === state;
}

export function applyDashboardConnection(root, status) {
    if (!['online', 'offline'].includes(status)) return false;
    for (const id of ['tracking-fleet', 'tracking-department', 'tracking-search']) {
        const input = root.getElementById(id);
        if (!input) continue;
        input.value = '';
        input.dispatchEvent(new Event('searchable-select:refresh'));
    }
    root.getElementById('tracking-state').value = status;
    root.getElementById('tracking-show-all').checked = true;
    root.getElementById('tracking-follow').checked = false;
    return true;
}
