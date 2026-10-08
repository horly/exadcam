import {MapVideoChannel} from './map-video.mjs?v=live-pipeline-20260924';
import {attachLivePlayer, resetLivePlayer} from './live-player.mjs?v=live-pipeline-20260924';

// The map opens both configured channels; each keeps its own stop/retry lifecycle.
export function createMapVideoPlayer({section, labels, request, getVehicle, canVideo, baseUrl,
    document = globalThis.document, attach = attachLivePlayer, reset = resetLivePlayer,
    sessionOptions = {}}) {
    const player = section.querySelector('video');
    const startButton = section.querySelector('[data-video-start]');
    const startLabel = section.querySelector('[data-video-start-label]');
    const stopButton = section.querySelector('[data-video-stop]');
    const placeholder = section.querySelector('.tracking-video-placeholder');
    const loader = section.querySelector('[data-video-loading]');
    const loadingLabel = section.querySelector('[data-video-loading-label]');
    const screen = section.querySelector('.tracking-video-screen');
    const status = section.querySelector('[data-video-status]');
    const channel = Number(section.dataset.mapChannel);
    let playback = null, manualPaused = false, wanted = false, prepared = false;
    let state = 'idle', mediaGeneration = 0;
    const available = () => canVideo && getVehicle()?.equipment && channel <= getVehicle().equipment.channels;

    function show(next) {
        state = next;
        const busy = ['waiting','buffering','reconnecting'].includes(state);
        section.dataset.playbackState = state;
        screen.setAttribute('aria-busy', String(busy));
        loader.hidden = !busy;
        placeholder.hidden = busy || state === 'ready';
        startButton.hidden = !['idle','paused','play_required','failed'].includes(state);
        startButton.disabled = !available();
        startLabel.textContent = state === 'failed' ? labels.video_retry : state === 'play_required' ? labels.video_start : labels.play;
        stopButton.hidden = !wanted && !prepared;
        status.textContent = labels['video_'+state] || labels.video_failed;
        loadingLabel.textContent = status.textContent;
    }

    const session = new MapVideoChannel({...sessionOptions, request,
        reset({preserveFrame = false} = {}) {
            mediaGeneration++;
            reset(player, playback, {preserveFrame}); playback = null;
            if (!preserveFrame) manualPaused = false;
        },
        notify(next) {
            if (!wanted) return;
            if (next === 'failed') wanted = false;
            show(next);
        },
        attach(url, onError, options) {
            const generation = ++mediaGeneration;
            playback = attach(player, url, {...options,
                shouldPlay: () => wanted && !manualPaused,
                onState(next) {
                    if (!wanted || generation !== mediaGeneration) return;
                    if (next === 'paused') {
                        if (document.hidden) return;
                        manualPaused = true;
                    }
                    if (next === 'ready') { manualPaused = false; session.markPlaying(); }
                    // A decoded preview is not yet playing: keep the loading feedback.
                    show(next === 'preview' ? 'buffering' : next);
                },
                onError(error) { if (wanted && generation === mediaGeneration) onError(error); },
            });
        },
    });

    function prepare() {
        prepared = Boolean(available());
        show(prepared ? 'waiting' : 'missing');
    }
    async function start() {
        if (!available() || wanted) return;
        prepared = false; wanted = true; manualPaused = false;
        show('waiting');
        const equipmentId = getVehicle().equipment.id;
        await session.start(`${baseUrl}/${equipmentId}/live`, channel);
    }
    async function stop(keepalive = false) {
        prepared = false; wanted = false;
        show(available() ? 'idle' : 'missing');
        await session.stop(keepalive);
    }
    startButton.addEventListener('click', () => {
        if (!available()) return;
        if (session.active && playback && ['paused','play_required'].includes(state)) {
            manualPaused = false; show('buffering'); playback.resume();
        } else void start();
    });
    stopButton.addEventListener('click', () => { void stop(); });
    return {channel, prepare, start, stop,
        startPrepared() { if (prepared) return start(); },
        resume() { if (wanted && session.active) { void session.resume(); playback?.resume(); } },
    };
}
