@php($cameraAccess = \App\Support\FleetAccess::dashcams(auth()->user()))
<section id="dashcams-module" data-module-view="dashcams" data-module-title="{{ __('dashcams.title') }}" data-module-description="{{ __(auth()->user()->isSuperadmin() ? 'dashcams.description' : 'dashcams.client_description') }}" lang="{{ app()->getLocale() }}" hidden>
@if($cameraAccess)
    <div class="users-overview">
        @foreach(['total' => ['camera', 'total'], 'online' => ['shield', 'online_stat'], 'unassigned' => ['truck', 'unassigned_stat']] as $key => [$icon, $label])
        <div class="users-stat"><span class="users-stat-icon {{ $key === 'online' ? 'green' : '' }}"><x-icon :name="$icon" /></span><div><strong data-dashcam-stat="{{ $key }}">—</strong><span>{{ __('dashcams.'.$label) }}</span></div></div>
        @endforeach
    </div>
    <div id="dashcam-notice" class="alert" role="status" aria-live="polite" hidden></div>
    <div class="panel users-list-panel">
        <div class="users-toolbar"><div><h2>{{ __('dashcams.registry') }}</h2><p>{{ __('dashcams.registry_hint') }}</p></div>@if(auth()->user()->isSuperadmin())<button type="button" id="dashcam-create" class="btn btn-primary users-primary"><x-icon name="plus" />{{ __('dashcams.new') }}</button>@endif</div>
        <div class="users-filters dashcam-filters">
            <label class="users-search" for="dashcam-search"><x-icon name="search" /><input type="search" id="dashcam-search" maxlength="100" placeholder="{{ __(auth()->user()->isSuperadmin() ? 'dashcams.search' : 'dashcams.client_search') }}" aria-label="{{ __(auth()->user()->isSuperadmin() ? 'dashcams.search' : 'dashcams.client_search') }}"></label>
            @if(auth()->user()->isSuperadmin())<select id="dashcam-model-filter" class="form-select" aria-label="{{ __('dashcams.model') }}"><option value="">{{ __('dashcams.all_models') }}</option>@foreach(\App\Support\DashcamProfile::MODELS as $model)<option>{{ $model }}</option>@endforeach</select>@endif
            <div class="users-page-size"><label for="dashcam-page-size">{{ __('dashcams.show') }}</label><select id="dashcam-page-size" class="form-select form-select-sm">@foreach([5,10,25,50] as $size)<option @selected($size === 10)>{{ $size }}</option>@endforeach</select></div>
            <button type="button" id="dashcam-refresh" class="users-icon-button" aria-label="{{ __('dashcams.refresh') }}" title="{{ __('dashcams.refresh') }}"><x-icon name="refresh" /></button>
        </div>
        <div id="dashcam-table" aria-busy="true"><div class="users-loading" role="status"><span class="spinner-border spinner-border-sm"></span>{{ __('dashcams.loading') }}</div></div>
    </div>
@else
    <div class="panel p-4">{{ __('dashcams.no_access') }}</div>
@endif
</section>
@if(auth()->user()->isSuperadmin())
<div class="modal fade users-modal" id="dashcam-form-modal" tabindex="-1" aria-labelledby="dashcam-form-title" aria-hidden="true" data-bs-backdrop="static"><div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><span class="users-modal-icon"><x-icon name="camera" /></span><div><p>{{ __('dashcams.settings') }}</p><h2 id="dashcam-form-title" class="modal-title">{{ __('dashcams.new') }}</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('dashcams.close') }}"></button></div>
    <form id="dashcam-form" novalidate autocomplete="off">
        @csrf
        <div class="modal-body">
            <div id="dashcam-form-alert" class="alert alert-danger" role="alert" hidden></div>
            <p class="users-required-note">{{ __('dashcams.required_note') }}</p>
            <div class="dashcam-profile"><label class="form-label" for="dashcam-model">{{ __('dashcams.model') }} *</label><select id="dashcam-model" name="model" class="form-select" required aria-describedby="dashcam-model-error"><option value="">{{ __('dashcams.choose_model') }}</option>@foreach(\App\Support\DashcamProfile::MODELS as $model)<option>{{ $model }}</option>@endforeach</select><div id="dashcam-model-error" class="invalid-feedback" data-dashcam-error="model"></div></div>
            <fieldset id="dashcam-fields" disabled>
                <div class="row g-3">
                    @foreach(['name' => ['text',100], 'imei' => ['text',15]] as $field => [$type,$max])
                    <div class="col-md-6"><label class="form-label" for="dashcam-{{ $field }}">{{ $field === 'imei' ? 'IMEI' : __('dashcams.name') }} *</label><input id="dashcam-{{ $field }}" name="{{ $field }}" type="{{ $type }}" class="form-control" maxlength="{{ $max }}" @if($field === 'imei') inputmode="numeric" pattern="[0-9]{15}" @endif required aria-describedby="dashcam-{{ $field }}-error"><div id="dashcam-{{ $field }}-error" class="invalid-feedback" data-dashcam-error="{{ $field }}"></div></div>
                    @endforeach
                    <div id="dashcam-identifier-group" class="col-12" hidden><div class="dashcam-id-group"><label class="form-label" for="dashcam-communication-id">{{ __('dashcams.communication_id') }} *</label><input id="dashcam-communication-id" name="communication_id" class="form-control" inputmode="numeric" maxlength="12" pattern="[0-9]{12}" aria-describedby="dashcam-communication_id-error dashcam-identifier-hint" disabled><div id="dashcam-communication_id-error" class="invalid-feedback" data-dashcam-error="communication_id"></div><p id="dashcam-identifier-hint" class="users-field-hint">{{ __('dashcams.communication_hint') }}</p></div></div>
                    <div class="col-md-6"><label class="form-label" for="dashcam-transport">{{ __('dashcams.transport') }}</label><input id="dashcam-transport" name="transport" value="TCP" class="form-control" readonly><div class="invalid-feedback" data-dashcam-error="transport"></div></div>
                    <div class="col-md-6"><label class="form-label" for="dashcam-protocol">{{ __('dashcams.protocol') }} *</label><select id="dashcam-protocol" name="protocol_version" class="form-select" required aria-describedby="dashcam-protocol_version-error"><option value="2013">JT808 · 2013</option><option value="2019">JT808 · 2019</option></select><div id="dashcam-protocol_version-error" class="invalid-feedback" data-dashcam-error="protocol_version"></div></div>
                    <div class="col-12"><p id="dashcam-protocol-hint" class="users-field-hint"></p></div>
                    <div class="col-12"><div class="users-section-title dashcam-form-divider"><x-icon name="truck" />{{ __('dashcams.assignment') }}</div><p class="users-field-hint">{{ __('dashcams.assignment_hint') }}</p></div>
                    <div class="col-12"><label class="form-label" for="dashcam-vehicle_id">{{ __('dashcams.vehicle') }} *</label><select id="dashcam-vehicle_id" name="vehicle_id" class="form-select" required data-searchable-database data-search-placeholder="{{ __('dashcams.search_vehicle') }}" data-no-results="{{ __('dashcams.no_option_match') }}" aria-describedby="dashcam-vehicle_id-error"><option value="">{{ __('dashcams.choose_vehicle') }}</option></select><div id="dashcam-vehicle_id-error" class="invalid-feedback" data-dashcam-error="vehicle_id"></div></div>
                    <div class="col-12" id="dashcam-assignment-notice" hidden><p class="dashcam-form-hint mb-0" id="dashcam-assignment-message"></p></div>
                    <div class="col-12"><div class="users-section-title dashcam-form-divider"><x-icon name="camera" />{{ __('dashcams.video_settings') }}</div></div>
                    <div class="col-md-6"><label class="form-label" for="dashcam-channels">{{ __('dashcams.channels') }} *</label><select id="dashcam-channels" name="channels" class="form-select" required>@foreach(range(1,8) as $count)<option @selected($count === 2)>{{ $count }}</option>@endforeach</select><div class="invalid-feedback" data-dashcam-error="channels"></div></div>
                    <div class="col-md-6"><label class="form-label" for="dashcam-frame-rate">{{ __('dashcams.frame_rate') }} *</label><input type="number" id="dashcam-frame-rate" name="frame_rate" class="form-control" value="15" min="1" max="30" step="1" required><div class="invalid-feedback" data-dashcam-error="frame_rate"></div></div>
                </div>
            </fieldset>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('dashcams.cancel') }}</button><button type="submit" class="btn btn-primary users-primary">{{ __('dashcams.save') }}</button></div>
    </form>
</div></div></div>
@elseif($cameraAccess)
@include('dashcams.fleet-form')
@endif
@if($cameraAccess)
<div class="modal fade" id="dashcam-live-modal" tabindex="-1" aria-labelledby="dashcam-live-title" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h2 class="modal-title h5" id="dashcam-live-title">{{ __('dashcams.live') }}</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('dashcams.close') }}"></button></div>
    <div class="modal-body">@include('partials.audio-controls', ['audioId' => 'dashcam-live-audio'])<div class="dashcam-live-screen"><div class="dashcam-live-cover" aria-hidden="true"><x-icon name="camera" /></div><video id="dashcam-live-player" class="w-100 bg-dark rounded" style="max-height:65vh" controls muted playsinline preload="auto"></video></div><p id="dashcam-live-status" class="mt-3 mb-0" role="status" aria-live="polite"></p><small class="text-secondary">{{ __('dashcams.live_hint') }}</small></div>
</div></div></div>
@endif
@if(\App\Support\FleetAccess::browse(auth()->user()))
@push('styles')<link rel="stylesheet" href="{{ asset('css/searchable-select.css') }}?v=dashboard-real-1"><link rel="stylesheet" href="{{ asset('css/dashcams.css') }}?v=models-20260928">@endpush
@push('scripts')
    <script id="dashcam-config" type="application/json">{!! \Illuminate\Support\Js::encode(['isPlatform' => auth()->user()->isSuperadmin(), 'fleetId' => auth()->user()->fleet_id, 'url' => route('dashcams.index'), 'optionsUrl' => route('dashcams.options'), 'registryUrl' => url('/registry'), 'strings' => trans('dashcams')]) !!}</script>
    <script src="{{ asset('js/searchable-select.js') }}?v=map-overlay-20261007" defer></script>
    <script src="{{ asset('vendor/hls/hls.min.js') }}" defer></script>
    <script src="{{ asset('js/dashcams.js') }}?v=models-20260928" defer></script>
@endpush
@endif
