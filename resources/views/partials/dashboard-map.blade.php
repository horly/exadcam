<section id="tracking-module" class="panel live-map-panel" data-module-view="map" data-module-title="{{ __('map.title') }}" data-module-description="{{ __('map.description') }}" lang="{{ app()->getLocale() }}">
    <header class="panel-heading tracking-overview-heading"><div class="panel-title"><x-icon name="pin" /><h2>{{ __('map.fleet_view') }}</h2></div><a href="#map" data-nav="map" class="text-action">{{ __('map.expand') }}<x-icon name="arrow" /></a></header>
    @if($googleMap['allowed'])
    <div class="tracking-workspace" id="tracking-workspace">
        <div class="tracking-map-area" id="tracking-map-area">
            <div id="google-fleet-map" class="tracking-canvas" aria-label="{{ __('map.title') }}"></div>
            <div class="tracking-map-message" id="tracking-map-message" role="status">{{ $googleMap['apiKey'] ? __('map.loading') : __('map.not_configured') }}</div>
            <button class="users-icon-button tracking-open-panel" id="tracking-open-panel" type="button" aria-label="{{ __('map.show_panel') }}" aria-controls="tracking-panel" aria-expanded="false"><x-icon name="settings" /></button>
            <aside class="tracking-panel" id="tracking-panel" aria-label="{{ __('map.fleet_view') }}">
                <div class="tracking-panel-heading"><div><span>{{ __('map.filters') }}</span><h2><x-icon name="pin" />{{ __('map.title') }}</h2></div><div><button type="button" id="tracking-refresh" class="users-icon-button" aria-label="{{ __('map.refresh') }}"><x-icon name="refresh" /></button><button type="button" id="tracking-close-panel" class="users-icon-button" aria-label="{{ __('map.hide_panel') }}" aria-controls="tracking-panel" aria-expanded="true"><x-icon name="chevron" /></button></div></div>
                <div class="tracking-stats">
                    @foreach(['total' => 'camera', 'positioned' => 'pin', 'online' => 'signal', 'offline' => 'close'] as $stat => $icon)
                    <div class="tracking-stat stat-{{ $stat }}"><span class="tracking-stat-icon"><x-icon :name="$icon" /></span><span class="tracking-stat-label">{{ __('map.'.$stat) }}</span><strong data-tracking-count="{{ $stat }}">—</strong></div>
                    @endforeach
                </div>
                <div id="tracking-feed-message" class="tracking-feed-message" role="alert" hidden></div>
                <label class="tracking-show-all"><input class="form-check-input" type="checkbox" id="tracking-show-all"><x-icon name="truck" /><span>{{ __('map.show_all') }}</span></label>
                <div class="tracking-filters">
                    <div class="row g-2">
                        <div class="col-6"><label for="tracking-state"><x-icon name="signal" />{{ __('map.state') }}</label><select id="tracking-state" class="form-select"><option value="">{{ __('map.all_states') }}</option>@foreach(['moving','stopped','parking','offline','stale','no_position','no_camera'] as $state)<option value="{{ $state }}">{{ __('map.'.$state) }}</option>@endforeach</select></div>
                        <div class="col-6"><label for="tracking-fleet"><x-icon name="fleets" />{{ __('map.fleet') }}</label><select id="tracking-fleet" class="form-select" data-searchable-database data-search-placeholder="{{ __('dashcams.search_fleet') }}" data-no-results="{{ __('dashcams.no_option_match') }}"><option value="">{{ __('map.all_fleets') }}</option></select></div>
                    </div>
                    <div class="tracking-department-filter" id="tracking-department-filter" hidden><label for="tracking-department"><x-icon name="departments" />{{ __('map.department') }}</label><select id="tracking-department" class="form-select" data-searchable-database data-search-placeholder="{{ __('dashcams.search_department') }}" data-no-results="{{ __('dashcams.no_option_match') }}"><option value="">{{ __('map.all_departments') }}</option><option value="none">{{ __('dashcams.no_department') }}</option></select></div>
                    <label class="tracking-search-caption" for="tracking-search"><x-icon name="search" />{{ __('map.search_label') }}</label>
                    <div class="tracking-search"><x-icon name="search" /><input id="tracking-search" type="search" maxlength="100" placeholder="{{ __(auth()->user()->isSuperadmin() ? 'map.search' : 'map.client_search') }}" aria-label="{{ __(auth()->user()->isSuperadmin() ? 'map.search' : 'map.client_search') }}"></div>
                </div>
                <div class="tracking-results" id="tracking-results" hidden>
                    <div class="tracking-list-header"><strong>{{ __('map.results') }}</strong><span id="tracking-result-count">0</span></div>
                    <div class="tracking-vehicle-list" id="tracking-vehicle-list" aria-label="{{ __('map.vehicles') }}"></div>
                    <p class="tracking-empty" id="tracking-empty" hidden>{{ __('map.empty') }}</p>
                </div>
                <div class="tracking-panel-footer">
                    <div class="tracking-panel-actions"><button type="button" id="tracking-fit" class="btn btn-primary users-primary"><x-icon name="target" />{{ __('map.fit') }}</button><label><input id="tracking-auto" class="form-check-input" type="checkbox" checked><x-icon name="refresh" /> {{ __('map.auto') }}</label></div>
                    <div class="tracking-options"><label><input id="tracking-trails" class="form-check-input" type="checkbox" checked> {{ __('map.trails') }}</label><label><input id="tracking-follow" class="form-check-input" type="checkbox" checked> {{ __('map.follow') }}</label></div>
                    <p><x-icon name="clock" /><span id="tracking-sync-label">{{ __('map.last_update') }}</span> <time id="tracking-updated">—</time></p>
                </div>
            </aside>
            <div class="tracking-tools"><button type="button" class="users-icon-button" id="tracking-zoom-in" aria-label="{{ __('map.zoom_in') }}"><x-icon name="plus" /></button><button type="button" class="users-icon-button" id="tracking-zoom-out" aria-label="{{ __('map.zoom_out') }}"><x-icon name="minus" /></button><button type="button" class="users-icon-button" id="tracking-map-type" aria-label="{{ __('map.satellite') }}" aria-pressed="false"><x-icon name="globe" /></button><button type="button" class="users-icon-button" id="tracking-fullscreen" aria-label="{{ __('map.fullscreen') }}"><x-icon name="expand" /></button></div>
            <div class="tracking-legend"><span class="tracking-glyph" data-state="moving"></span>{{ __('map.moving') }}<span class="tracking-glyph" data-state="stopped"></span>{{ __('map.stopped') }}<span class="tracking-glyph" data-state="parking">P</span>{{ __('map.parking') }}</div>
        </div>
        <aside class="tracking-video-panel" id="tracking-video-panel" aria-label="{{ __('map.video') }}" hidden>
            <header><div><span>{{ __('map.live_video') }}</span><h2 id="tracking-video-title"></h2><p id="tracking-video-subtitle"></p></div><button type="button" id="tracking-video-close" class="users-icon-button" aria-label="{{ __('map.close_video') }}"><x-icon name="close" /></button></header>
            @include('partials.audio-controls', ['audioId' => 'tracking-video-audio'])
            <div class="tracking-video-channels">
                @foreach([1,2] as $channel)
                <section class="tracking-channel" data-map-channel="{{ $channel }}">
                    <div class="tracking-channel-heading"><x-icon name="camera" /><h3>{{ __('map.channel') }} {{ $channel }}</h3><span>{{ __('map.live') }}</span></div>
                    <div class="tracking-video-screen"><video playsinline muted controls preload="none" aria-label="{{ __('map.channel') }} {{ $channel }}"></video><div class="tracking-video-placeholder"><x-icon name="camera" /><button type="button" class="btn tracking-play" data-video-start><x-icon name="play" />{{ __('map.play') }}</button></div></div>
                    <footer><p role="status" data-video-status>{{ __('map.video_idle') }}</p><button type="button" class="btn tracking-video-stop" data-video-stop hidden>{{ __('map.stop') }}</button></footer>
                </section>
                @endforeach
            </div>
        </aside>
    </div>
    @else
        <div class="p-4">{{ __('map.forbidden') }}</div>
    @endif
</section>
@if($googleMap['allowed'])
<div class="modal fade tracking-history-modal" id="tracking-history-modal" tabindex="-1" aria-labelledby="tracking-history-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header">
            <div class="tracking-details-heading"><span class="tracking-details-heading-icon"><x-icon name="dashcam" /></span><div><span class="tracking-modal-eyebrow">{{ __('map.supervision') }}</span><h2 class="modal-title" id="tracking-history-title">{{ __('map.equipment_details') }}</h2></div></div>
            <button type="button" class="tracking-details-close" data-bs-dismiss="modal" aria-label="{{ __('map.close_details') }}"><x-icon name="close" /></button>
        </div>
        <div class="modal-body">
            <div id="tracking-history-message" role="status"></div>
            <nav class="tracking-details-tabs nav" role="tablist" aria-label="{{ __('map.history_details') }}">
                <button class="active" id="tracking-summary-tab" data-bs-toggle="tab" data-bs-target="#tracking-summary-pane" type="button" role="tab" aria-controls="tracking-summary-pane" aria-selected="true"><x-icon name="grid" />{{ __('map.summary') }}</button>
                <button id="tracking-history-tab" data-bs-toggle="tab" data-bs-target="#tracking-history-pane" type="button" role="tab" aria-controls="tracking-history-pane" aria-selected="false"><x-icon name="clock" />{{ __('map.history_tab') }}</button>
            </nav>
            <div class="tab-content">
                <div class="tab-pane show active" id="tracking-summary-pane" role="tabpanel" aria-labelledby="tracking-summary-tab" tabindex="0"><div id="tracking-equipment-fields"></div></div>
                <div class="tab-pane" id="tracking-history-pane" role="tabpanel" aria-labelledby="tracking-history-tab" tabindex="0">
                    <div class="tracking-history-toolbar"><div><h3>{{ __('map.gps_history') }}</h3><p>{{ __('map.history_hint') }}</p></div><label>{{ __('map.date') }}<input type="date" id="tracking-history-date" class="form-control" required min="2020-01-01"></label></div>
                    <div class="table-responsive"><table class="table tracking-history-table"><thead><tr><th>{{ __('map.position_date') }}</th><th>{{ __('map.state') }}</th><th>{{ __('map.speed') }}</th><th>{{ __('map.ignition') }}</th><th>{{ __('map.coordinates') }}</th></tr></thead><tbody id="tracking-history-rows"></tbody></table></div>
                    <div class="tracking-history-pagination">
                        <p class="tracking-history-summary" id="tracking-history-summary" role="status"></p>
                        <nav aria-label="{{ __('map.history_pagination') }}">
                            <button type="button" id="tracking-history-prev" class="tracking-page-button" aria-label="{{ __('map.previous') }}" disabled><x-icon name="chevron" /></button>
                            <div id="tracking-history-pages" class="tracking-history-pages"></div>
                            <button type="button" id="tracking-history-next" class="tracking-page-button" aria-label="{{ __('map.next') }}" disabled><x-icon name="chevron" /></button>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer"><p><x-icon name="shield" />{{ __('map.latest_signal_note') }}</p><button type="button" class="btn btn-primary" data-bs-dismiss="modal">{{ __('map.close') }}</button></div>
    </div></div>
</div>
@endif
@push('styles')<link rel="stylesheet" href="{{ asset('css/google-map.css') }}?v=map-follow-1">@endpush
@push('scripts')
<script id="google-map-data" type="application/json">{!! \Illuminate\Support\Js::encode($googleMap) !!}</script>
<script src="{{ asset('js/google-map.js') }}?v=audio-3" defer></script>
@endpush
