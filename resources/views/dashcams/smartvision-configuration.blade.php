<div class="modal fade sv-modal" id="smartvision-modal" tabindex="-1" aria-labelledby="smartvision-title" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header">
            <div><p class="sv-eyebrow">{{ __('smartvision.subtitle') }}</p><h2 id="smartvision-title" class="modal-title">{{ __('smartvision.title') }}</h2><p id="sv-camera" class="sv-camera"></p></div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('smartvision.close') }}"></button>
        </div>
        <form id="smartvision-form" autocomplete="off" novalidate>
            @csrf
            <div class="modal-body">
                <div class="sv-notice"><strong id="sv-state">{{ __('smartvision.empty') }}</strong><p>{{ __('smartvision.notice') }}</p></div>
                <p class="sv-help">{{ __('smartvision.current_unknown') }}</p>
                <div id="sv-message" class="alert" role="status" aria-live="polite" hidden></div>
                <div id="sv-stale" class="alert alert-warning" hidden>{{ __('smartvision.stale') }}</div>
                <div class="sv-meta"><span id="sv-version"></span><button type="button" class="btn btn-sm btn-light" id="sv-reload"><x-icon name="refresh" />{{ __('smartvision.reload') }}</button></div>
                <fieldset id="sv-fields" disabled>
                    <section class="sv-section" aria-labelledby="sv-servers-title">
                        <div class="sv-section-heading"><h3 id="sv-servers-title">{{ __('smartvision.servers') }}</h3><button type="button" id="sv-exadcam" class="btn btn-sm btn-outline-primary">{{ __('smartvision.use_exadcam') }}</button></div>
                        <div class="sv-servers">
                            @foreach(['main' => ['ip', 'port'], ...\App\Http\Controllers\SmartvisionConfigurationController::SERVERS] as $key => [$ip, $port])
                            <div class="sv-server">
                                <div class="sv-server-heading"><h4>{{ __('smartvision.'.$key) }}</h4>
                                    @if($key !== 'main')<label class="visually-hidden" for="sv-{{ $key }}_action">{{ __('smartvision.fields.'.$key.'_action') }}</label><select class="form-select form-select-sm" id="sv-{{ $key }}_action" name="{{ $key }}_action" data-sv-server="{{ $key }}">@foreach(['keep', 'set', 'remove'] as $action)<option value="{{ $action }}">{{ __('smartvision.'.$action) }}</option>@endforeach</select>@endif
                                </div>
                                <div class="sv-server-fields">
                                    <div><label class="form-label" for="sv-{{ $ip }}">{{ __('smartvision.fields.'.$ip) }}@if($key === 'main') * @endif</label><input class="form-control" id="sv-{{ $ip }}" name="{{ $ip }}" type="text" maxlength="253" spellcheck="false" autocapitalize="off" aria-describedby="sv-error-{{ $ip }}"><div class="invalid-feedback" id="sv-error-{{ $ip }}"></div></div>
                                    <div><label class="form-label" for="sv-{{ $port }}">{{ __('smartvision.fields.'.$port) }}@if($key === 'main') * @endif</label><input class="form-control" id="sv-{{ $port }}" name="{{ $port }}" type="number" inputmode="numeric" min="1" max="65535" step="1" aria-describedby="sv-error-{{ $port }}"><div class="invalid-feedback" id="sv-error-{{ $port }}"></div></div>
                                </div>
                                @if($key !== 'main')<p class="sv-help" id="sv-hint-{{ $key }}"></p><div class="invalid-feedback d-block" id="sv-error-{{ $key }}_action"></div>@endif
                            </div>
                            @endforeach
                        </div>
                    </section>
                    <section class="sv-section" aria-labelledby="sv-identity-title">
                        <h3 id="sv-identity-title">{{ __('smartvision.identity') }}</h3><p class="sv-help">{{ __('smartvision.identity_hint') }}</p>
                        <div class="sv-grid">
                            @foreach(['telnum' => 12, 'tid' => 7, 'manuf' => 5, 'module' => 20] as $key => $max)
                            <div><label class="form-label" for="sv-{{ $key }}">{{ __('smartvision.fields.'.$key) }}</label><input class="form-control" id="sv-{{ $key }}" name="{{ $key }}" type="text" maxlength="{{ $max }}" placeholder="{{ __('smartvision.optional') }}" @if($key === 'telnum') inputmode="numeric" @endif aria-describedby="sv-error-{{ $key }}@if($key === 'telnum') sv-sim-hint @endif"><div class="invalid-feedback" id="sv-error-{{ $key }}"></div>@if($key === 'telnum')<p class="sv-help" id="sv-sim-hint">{{ __('smartvision.sim_hint') }}</p>@endif</div>
                            @endforeach
                        </div>
                    </section>
                    <section class="sv-section" aria-labelledby="sv-vehicle-title">
                        <h3 id="sv-vehicle-title">{{ __('smartvision.vehicle') }}</h3>
                        <div class="sv-grid">
                            @foreach(['provinceid' => 65535, 'cityid' => 65535, 'carid' => null, 'platecolor' => 255] as $key => $max)
                            <div><label class="form-label" for="sv-{{ $key }}">{{ __('smartvision.fields.'.$key) }}</label><input class="form-control" id="sv-{{ $key }}" name="{{ $key }}" type="{{ $max === null ? 'text' : 'number' }}" @if($max === null) maxlength="32" @else inputmode="numeric" min="0" max="{{ $max }}" step="1" @endif placeholder="{{ __('smartvision.optional') }}" aria-describedby="sv-error-{{ $key }}"><div class="invalid-feedback" id="sv-error-{{ $key }}"></div></div>
                            @endforeach
                        </div>
                    </section>
                </fieldset>
            </div>
            <div class="modal-footer"><span id="sv-dirty" class="sv-help" hidden>{{ __('smartvision.unsaved') }}</span><button type="button" class="btn btn-light btn-sm" id="sv-reset" disabled>{{ __('smartvision.reset') }}</button><button type="submit" id="sv-save" class="btn btn-primary btn-sm" disabled>{{ __('smartvision.save') }}</button><button type="button" class="btn btn-outline-secondary btn-sm" disabled title="{{ __('smartvision.send_unavailable') }}">{{ __('smartvision.send') }}</button></div>
        </form>
    </div></div>
</div>
@push('styles')<link rel="stylesheet" href="{{ asset('css/smartvision-configuration.css') }}?v=20260929-1">@endpush
@push('scripts')
<script type="application/json" id="smartvision-config">{!! \Illuminate\Support\Js::encode(['url' => url('/dashcams'), 'locale' => app()->getLocale(), 'strings' => trans('smartvision')]) !!}</script>
<script src="{{ asset('js/smartvision-configuration.js') }}?v=20260929-1" defer></script>
@endpush
