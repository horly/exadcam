@if(\App\Support\FleetAccess::allows(auth()->user(), \App\Models\User::PERMISSION_VIDEO_VIEW))
<div class="live-audio-controls" id="{{ $audioId }}" role="group" aria-label="{{ __('audio.title') }}" data-state="idle">
    <div class="live-audio-actions">
        <button type="button" class="btn" data-audio-listen aria-pressed="false" disabled><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M11 5 6 9H3v6h3l5 4V5Z M15 8a6 6 0 0 1 0 8 M18 5a10 10 0 0 1 0 14"/></svg>{{ __('audio.listen') }}</button>
        @if(\App\Support\FleetAccess::allows(auth()->user(), \App\Models\User::PERMISSION_AUDIO_TALK))
        <button type="button" class="btn" data-audio-talk aria-pressed="false" disabled><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 10v2a7 7 0 0 0 14 0v-2 M12 19v3 M8 22h8"/></svg>{{ __('audio.talk') }}</button>
        @endif
        <button type="button" class="btn live-audio-stop" data-audio-stop hidden>{{ __('audio.stop') }}</button>
        <input type="range" min="0" max="1" step="0.05" value="1" data-audio-volume aria-label="{{ __('audio.volume') }}" hidden>
    </div>
    <label class="live-audio-level" data-audio-level hidden>{{ __('audio.microphone_level') }} <meter min="0" max="1" value="0" aria-label="{{ __('audio.microphone_level') }}"></meter></label>
    <p role="status" aria-live="polite" data-audio-status>{{ __('audio.idle') }}</p>
    <small class="live-audio-timing">{{ __('audio.realtime') }}</small>
    <small class="live-audio-intercom-note">{{ __('audio.video_pause') }}</small>
</div>
@once
@push('styles')<link rel="stylesheet" href="{{ asset('css/live-audio.css') }}?v=talk-direction-20260924">@endpush
@push('scripts')<script id="live-audio-config" type="application/json">{!! \Illuminate\Support\Js::encode(['baseUrl' => url('/dashcams'), 'labels' => trans('audio')]) !!}</script>@endpush
@endonce
@endif
