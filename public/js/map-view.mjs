// Pixel padding is shared by the initial fit and every subsequent GPS update.
export function viewportPadding(width, height, panelRight = 0) {
    const horizontal = Math.min(24, Math.max(0, width / 8));
    const vertical = Math.min(24, Math.max(0, height / 8));
    return {top:vertical, bottom:vertical, right:horizontal,
        left:Math.min(Math.max(horizontal, panelRight ? panelRight + 16 : 0), Math.max(horizontal, width - horizontal - 96))};
}

export function projectedCenter(point, zoom, padding) {
    const scale = 2 ** zoom;
    return {x:point.x - (padding.left - padding.right) / (2 * scale),
        y:point.y - (padding.top - padding.bottom) / (2 * scale)};
}

// Switching browser tabs suspends GPS work, not the user's video selection.
// Leaving the map inside EXADCAM still releases its video sessions.
export function observeMapView({document, closeVideo, sync, enter, resume}) {
    const visibility = () => { sync(); if (!document.hidden && document.body.dataset.view === 'map') resume(); };
    const navigation = () => { if (document.body.dataset.view !== 'map') closeVideo(); enter(); sync(); };
    document.addEventListener('visibilitychange',visibility);
    document.addEventListener('exadcam:view-changed',navigation);
    sync();
    return () => {
        document.removeEventListener('visibilitychange',visibility);
        document.removeEventListener('exadcam:view-changed',navigation);
    };
}
