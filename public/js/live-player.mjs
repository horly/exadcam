// Keep a modest startup reserve for cellular jitter; refill more after starvation.
export const LIVE_START_BUFFER_SECONDS = 8;
export const LIVE_BUFFER_SECONDS = 15;
export const LIVE_HLS_CONFIG = Object.freeze({
    lowLatencyMode: false,
    liveSyncDuration: 18,
    liveMaxLatencyDuration: 40,
    initialLiveManifestSize: 1,
    maxBufferLength: 30,
    maxMaxBufferLength: 60,
    backBufferLength: 10,
    maxLiveSyncPlaybackRate: 1,
});

// Count only the continuous playable range, never add ranges across a gap.
export function bufferedAhead(player) {
    for (let i = 0; i < player.buffered.length; i++) {
        if (player.currentTime >= player.buffered.start(i) - 0.05 && player.currentTime < player.buffered.end(i)) {
            return Math.max(0, player.buffered.end(i) - player.currentTime);
        }
    }
    return 0;
}

// Keep only one in-memory still per player. It is cleared when the user closes
// the channel, and never uploaded or persisted.
export function resetLivePlayer(player, playback, {preserveFrame = false} = {}) {
    if (preserveFrame && player.videoWidth && player.videoHeight && player.readyState >= 2) {
        try {
            const canvas = document.createElement('canvas');
            const scale = Math.min(1, 960 / player.videoWidth);
            canvas.width = Math.round(player.videoWidth * scale);
            canvas.height = Math.round(player.videoHeight * scale);
            canvas.getContext('2d').drawImage(player, 0, 0, canvas.width, canvas.height);
            player.poster = canvas.toDataURL('image/jpeg', 0.8);
        } catch { /* A cross-origin or unavailable frame falls back to the placeholder. */ }
    }
    if (!preserveFrame) player.removeAttribute('poster');
    playback?.destroy(); player.pause(); player.removeAttribute('src'); player.load();
    player.controls = false;
    return preserveFrame && Boolean(player.getAttribute('poster'));
}

export function attachLivePlayer(player, url, {
    Hls = globalThis.Hls, onState = () => {}, onError = () => {}, shouldPlay = () => true,
    startBufferSeconds = LIVE_START_BUFFER_SECONDS,
    schedule = (fn, ms) => setInterval(fn, ms), cancel = id => clearInterval(id), now = () => Date.now(),
} = {}) {
    let hls = null, disposed = false, buffering = true, started = false, nativePositioned = false, previewShown = false;
    let timer = null, bufferingSince = now();
    const listeners = [];
    const initialBuffer = startBufferSeconds === 4 ? 4 : LIVE_START_BUFFER_SECONDS;
    const requiredBuffer = () => started ? LIVE_BUFFER_SECONDS : initialBuffer;
    const listen = (event, callback) => { player.addEventListener(event, callback); listeners.push([event, callback]); };
    function destroy() {
        if (disposed) return;
        disposed = true; cancel(timer);
        for (const [event, callback] of listeners) player.removeEventListener(event, callback);
        hls?.destroy(); hls = null;
    }
    function fail(error) { if (!disposed) { try { onError(error); } finally { destroy(); } } }
    function check() {
        if (disposed || !buffering) return;
        // MPEG-TS timelines can begin after zero. Start at the first decoded
        // range instead of waiting forever for images before that range.
        if (!started && player.buffered.length && player.currentTime < player.buffered.start(0)) {
            player.currentTime = player.buffered.start(0);
        }
        if (!previewShown && player.readyState >= 2 && bufferedAhead(player) > 0) {
            previewShown = true;
            onState('preview');
        }
        if (!hls && !nativePositioned && player.seekable.length) {
            const start = player.seekable.start(0), end = player.seekable.end(player.seekable.length - 1);
            if (end - start >= requiredBuffer()) {
                player.currentTime = Math.max(start, end - LIVE_HLS_CONFIG.liveSyncDuration);
                nativePositioned = true;
            }
        }
        if (bufferedAhead(player) >= requiredBuffer() - 0.1 && player.readyState >= 2) {
            buffering = false; player.controls = true;
            if (!shouldPlay()) { onState('paused'); return; }
            Promise.resolve(player.play()).catch(error => {
                if (disposed) return;
                if (error.name === 'NotAllowedError') onState('play_required');
                else if (error.name !== 'AbortError') fail(error);
            });
        } else if (now() - bufferingSince > 90000) fail(new Error('Video buffer timeout'));
    }
    player.autoplay = false; player.preload = 'auto'; player.controls = false;
    listen('playing', () => { if (!buffering) { started = true; onState('ready'); } });
    listen('pause', () => { if (started && !buffering && !disposed) onState('paused'); });
    listen('ended', () => fail(new Error('Live stream ended')));
    listen('play', () => { if (buffering && bufferedAhead(player) < requiredBuffer() - 0.1) player.pause(); });
    listen('waiting', () => {
        if (started && !buffering && !player.paused && bufferedAhead(player) < 1) {
            buffering = true; bufferingSince = now(); player.pause(); player.controls = false; onState('buffering');
        }
    });
    for (const event of ['progress', 'canplay', 'loadedmetadata', 'loadeddata', 'seeked']) listen(event, check);
    listen('error', () => fail(player.error || new Error('Video playback failed')));
    onState('buffering');
    try {
        if (Hls?.isSupported()) {
            hls = new Hls({...LIVE_HLS_CONFIG});
            hls.on(Hls.Events.FRAG_BUFFERED, check);
            hls.on(Hls.Events.ERROR, (_event, data) => { if (data.fatal) fail(new Error(data.details || 'Video unavailable')); });
            hls.loadSource(url); hls.attachMedia(player);
        } else if (player.canPlayType('application/vnd.apple.mpegurl')) {
            player.src = url; player.load();
        } else throw Object.assign(new Error('HLS playback unsupported'), {status:422});
        if (!disposed) timer = schedule(check, 250);
    } catch (error) { destroy(); throw error; }
    function resume() {
        if (disposed || !shouldPlay()) return;
        if (buffering) { check(); return; }
        if (player.paused) Promise.resolve(player.play()).catch(error => {
            if (disposed) return;
            if (error.name === 'NotAllowedError') onState('play_required');
            else if (error.name !== 'AbortError') fail(error);
        });
    }
    return {destroy,resume};
}
