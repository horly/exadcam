// The line lives inside the marker, so Google cannot render its endpoint in a
// different frame or pane from the arrow. Both share the GPS anchor at (0, 0).
export function relativeTrail(points, position, zoom, project) {
    if (!position || points.length < 2) return '';
    const origin = project(position), scale = 2 ** zoom;
    return points.map((point, index) => {
        const world = project(point);
        let dx = world.x - origin.x;
        // Google's Mercator world is 256 px wide, including across the date line.
        dx -= Math.round(dx / 256) * 256;
        return `${index ? 'L' : 'M'}${(dx * scale).toFixed(3)} ${((world.y - origin.y) * scale).toFixed(3)}`;
    }).join(' ');
}

export function createMarkerTrail(content, map, maps) {
    const ns = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(ns, 'svg'), path = document.createElementNS(ns, 'path');
    svg.setAttribute('class', 'tracking-marker-trail'); svg.setAttribute('aria-hidden', 'true');
    path.setAttribute('fill', 'none'); path.setAttribute('stroke', '#229bd8');
    path.setAttribute('stroke-opacity', '.65'); path.setAttribute('stroke-width', '5');
    path.setAttribute('stroke-linecap', 'round'); path.setAttribute('stroke-linejoin', 'round');
    svg.append(path); content.prepend(svg);
    let points = [], position = null;
    const redraw = () => {
        const projection = map.getProjection();
        path.setAttribute('d', projection ? relativeTrail(points, position, map.getZoom(),
            p => projection.fromLatLngToPoint(new maps.LatLng(p.lat, p.lng))) : '');
    };
    return {setPath(next, anchor) { points = next; position = anchor; redraw(); }, redraw, remove() { svg.remove(); }};
}
