// Node 24.19 introduced explicit TCP_KEEPINTVL/TCP_KEEPCNT parameters.
// Older Node versions ignore extra arguments, so retain their previous long
// idle delay rather than accidentally falling back to a 70-second drop window.
export function gpsKeepAliveOptions(model, nodeVersion = process.versions.node) {
    if (model !== 'ES500-603') return [true, 30000];
    const [major, minor] = nodeVersion.split('.').map(Number);
    const tunable = major > 24 || (major === 24 && minor >= 19);
    return tunable ? [true, 60000, 30000, 10] : [true, 300000];
}
