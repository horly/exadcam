@if($googleMap['canVideo'])
<section class="panel camera-panel dashboard-camera-panel" id="dashboard-camera-panel" aria-labelledby="dashboard-camera-title">
    <header class="panel-heading">
        <div class="panel-title"><span class="heading-icon"><x-icon name="camera" /></span><h2 id="dashboard-camera-title">{{ __('dashboard-video.title') }}</h2></div>
        <span class="neutral-tag">{{ __('dashboard-video.live') }}</span>
    </header>
    <div class="dashboard-camera-body">
        <label for="dashboard-camera-vehicle" class="dashboard-camera-label">{{ __('dashboard-video.vehicle') }}</label>
        <select id="dashboard-camera-vehicle" class="form-select" data-searchable-database data-search-placeholder="{{ __('dashboard-video.search') }}" data-no-results="{{ __('dashboard-video.no_results') }}" @disabled($dashboardVideo->isEmpty())>
            <option value="">{{ __($dashboardVideo->isEmpty() ? 'dashboard-video.no_vehicles' : 'dashboard-video.choose') }}</option>
            @foreach($dashboardVideo as $vehicle)<option value="{{ $vehicle['id'] }}" data-status="{{ $vehicle['status'] }}" data-status-tone="{{ $vehicle['connection'] }}">{{ $vehicle['label'] }}</option>@endforeach
        </select>
        @include('partials.audio-controls', ['audioId' => 'dashboard-camera-audio'])
        <div class="dashboard-camera-channels">
            @foreach([1,2] as $channel)
            <section class="dashboard-camera-channel" data-dashboard-channel="{{ $channel }}" aria-labelledby="dashboard-channel-{{ $channel }}">
                <h3 id="dashboard-channel-{{ $channel }}"><x-icon name="camera" />{{ __('dashboard-video.channel') }} {{ $channel }}</h3>
                <div class="dashboard-camera-screen">
                    <video playsinline muted preload="none" aria-label="{{ __('dashboard-video.channel') }} {{ $channel }}"></video>
                    <div class="dashboard-camera-placeholder"><x-icon name="camera" /><span data-idle>{{ __('dashboard-video.select_first') }}</span><button type="button" class="btn" data-start hidden><x-icon name="play" />{{ __('dashboard-video.play') }}</button></div>
                </div>
                <footer><p role="status" aria-live="polite" data-status></p><button type="button" class="btn" data-stop hidden>{{ __('dashboard-video.stop') }}</button></footer>
            </section>
            @endforeach
        </div>
    </div>
</section>
@push('styles')<link rel="stylesheet" href="{{ asset('css/dashboard-video.css') }}?v=talk-direction-20260924">@endpush
@push('scripts')
<script id="dashboard-video-config" type="application/json">{!! \Illuminate\Support\Js::encode(['vehicles' => $dashboardVideo, 'baseUrl' => url('/dashcams'), 'labels' => trans('dashboard-video')]) !!}</script>
<script type="module" src="{{ asset('js/dashboard-video.mjs') }}?v=talk-direction-20260924"></script>
@endpush
@endif
