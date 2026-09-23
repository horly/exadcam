export function distance(a, b) {
    const r = Math.PI / 180;
    const h = Math.sin((b.lat - a.lat) * r / 2) ** 2 + Math.cos(a.lat * r) * Math.cos(b.lat * r) * Math.sin((b.lng - a.lng) * r / 2) ** 2;
    return 6371000 * 2 * Math.asin(Math.min(1, Math.sqrt(h)));
}

// Animate only between confirmed samples; never predict a device's next position.
export function movementPath(previous, next, displayed) {
    if (!previous?.position || !next.position || previous.source_id !== next.source_id) return [];
    const seconds = (Date.parse(next.position.at) - Date.parse(previous.position.at)) / 1000;
    if (seconds <= 0 || seconds > 120 || next.state !== 'moving') return [];
    if (distance(previous.position, next.position) < 3) return [];
    const start = displayed || previous.position;
    // A new report can interrupt an animation before its previous GPS endpoint.
    // Retain the remaining confirmed corners, starting at the displayed time.
    const samples = [...(next.trail || []), previous.position, next.position]
        .filter(p => Date.parse(p.at) > Date.parse(start.at) && Date.parse(p.at) <= Date.parse(next.position.at));
    const path = [start, ...new Map(samples.sort((a,b) => Date.parse(a.at) - Date.parse(b.at)).map(p => [p.at,p])).values()];
    let total = 0;
    for (let i = 1; i < path.length; i++) {
        const segment = distance(path[i - 1], path[i]);
        if (segment > 600) return [];
        total += segment;
    }
    return total / seconds > 70 ? [] : path;
}

export function pointAlong(path, progress) {
    const lengths = path.slice(1).map((p, i) => distance(path[i], p));
    const total = lengths.reduce((sum, value) => sum + value, 0);
    let remaining = Math.min(1, Math.max(0, progress)) * total;
    for (let i = 0; i < lengths.length; i++) {
        if (remaining <= lengths[i] && lengths[i] > 0) {
            const ratio = remaining / lengths[i];
            const at = Date.parse(path[i].at) + (Date.parse(path[i + 1].at) - Date.parse(path[i].at)) * ratio;
            return { lat: path[i].lat + (path[i + 1].lat - path[i].lat) * ratio, lng: path[i].lng + (path[i + 1].lng - path[i].lng) * ratio,
                ...(Number.isFinite(at) ? {at:new Date(at).toISOString()} : {}) };
        }
        remaining -= lengths[i];
    }
    return path.at(-1);
}

export function bearing(a,b) {
    const r = Math.PI/180, p1=a.lat*r, p2=b.lat*r, delta=(b.lng-a.lng)*r;
    return (Math.atan2(Math.sin(delta)*Math.cos(p2), Math.cos(p1)*Math.sin(p2)-Math.sin(p1)*Math.cos(p2)*Math.cos(delta))/r+360)%360;
}

export function pathThrough(path, progress) {
    const total = path.slice(1).reduce((sum,p,i) => sum + distance(path[i],p),0);
    let remaining = Math.min(1,Math.max(0,progress))*total;
    const traversed = [path[0]];
    for (let i=1;i<path.length;i++) {
        const length = distance(path[i-1],path[i]);
        if (length > remaining) break;
        traversed.push(path[i]); remaining -= length;
    }
    traversed.push(pointAlong(path,progress));
    return traversed;
}

// One endpoint for the GPS line and the marker, including redraws between frames.
// Animation timestamps are presentation metadata, never saved as GPS reports.
export function trailThroughPosition(vehicle, position, path = []) {
    if (vehicle.state !== 'moving' || !position) return [];
    const samples = [...(vehicle.trail || []), ...path]
        .filter(p => Date.parse(p.at) < Date.parse(position.at));
    return [...new Map(samples.sort((a,b) => Date.parse(a.at) - Date.parse(b.at)).map(p => [p.at,p])).values(), position];
}

// Keep the chosen vehicle; otherwise prefer the freshest moving GPS fix.
export function preferredTrackingVehicle(vehicles, selectedId = null) {
    const positioned = vehicles.filter(vehicle => Number.isFinite(vehicle.position?.lat) && Number.isFinite(vehicle.position?.lng));
    return positioned.find(vehicle => vehicle.id === selectedId)
        || positioned.filter(vehicle => vehicle.online && vehicle.state === 'moving')
            .sort((a,b) => (Date.parse(b.position.at) || 0) - (Date.parse(a.position.at) || 0))[0]
        || null;
}
