@if(\App\Support\FleetAccess::allows(auth()->user(), \App\Models\User::PERMISSION_REPORTS_GENERATE))
<section id="reports-module" class="reports-module" data-module-view="reports" data-module-title="{{ __('reports.title') }}" data-module-description="{{ __('reports.description') }}" hidden>
    <div class="panel report-filter-panel">
        <div class="report-tabs" role="group" aria-label="{{ __('reports.title') }}">@foreach(['summary','trips','usage','safety'] as $type)<button type="button" data-report-type="{{ $type }}" aria-pressed="{{ $type === 'summary' ? 'true' : 'false' }}">{{ __('reports.'.$type) }}</button>@endforeach</div>
        <form id="report-form">
            <fieldset id="report-fields" disabled>
                <div class="report-filter-grid">
                    <div><label for="report-period">{{ __('reports.period') }}</label><select id="report-period" class="form-select">@foreach(['today','yesterday','week','month','custom'] as $period)<option value="{{ $period }}" @selected($period === 'week')>{{ __('reports.'.$period) }}</option>@endforeach</select></div>
                    <div><label for="report-from">{{ __('reports.from') }}</label><input id="report-from" type="date" class="form-control" required></div>
                    <div><label for="report-to">{{ __('reports.to') }}</label><input id="report-to" type="date" class="form-control" required></div>
                    <div><label for="report-fleet">{{ __('reports.fleet') }}</label><select id="report-fleet" class="form-select"></select></div>
                    <div><label for="report-department">{{ __('reports.department') }}</label><select id="report-department" class="form-select"></select></div>
                    <div class="report-vehicle-picker"><label for="report-vehicle-toggle">{{ __('reports.vehicles') }}</label><button id="report-vehicle-toggle" type="button" class="form-select" aria-expanded="false" aria-controls="report-vehicle-menu">{{ __('reports.all_vehicles') }}</button>
                        <div id="report-vehicle-menu" class="report-vehicle-menu" hidden><input id="report-vehicle-search" type="search" class="form-control" maxlength="100" placeholder="{{ __('reports.find_vehicle') }}" aria-label="{{ __('reports.find_vehicle') }}"><button id="report-all-vehicles" type="button" class="btn btn-link">{{ __('reports.all_vehicles') }}</button><div id="report-vehicle-options"></div></div>
                    </div>
                </div>
                <div class="report-form-actions"><button id="report-generate" type="submit" class="btn btn-primary">{{ __('reports.generate') }}</button><button id="report-restore" type="button" class="btn btn-outline-secondary">{{ __('reports.restore') }}</button><span class="text-secondary small">{{ __('reports.limit_days') }}</span></div>
                <details class="report-presets"><summary>{{ __('reports.preset') }}</summary><div class="report-preset-grid"><div><label for="report-preset">{{ __('reports.preset') }}</label><select id="report-preset" class="form-select"></select></div><div><label for="report-preset-name">{{ __('reports.preset_name') }}</label><input id="report-preset-name" type="text" class="form-control" maxlength="80"></div><button type="button" id="report-save-preset" class="btn btn-outline-secondary">{{ __('reports.save_preset') }}</button><button type="button" id="report-delete-preset" class="btn btn-outline-secondary" disabled>{{ __('reports.delete_preset') }}</button></div><p class="text-secondary small">{{ __('reports.preset_hint') }}</p></details>
            </fieldset>
        </form>
    </div>
    <p id="report-message" class="report-message" role="status" aria-live="polite">{{ __('reports.initial') }}</p>
    <div id="report-results" hidden>
        <div class="report-results-heading"><div><h2 id="report-result-title"></h2><p id="report-result-period" class="text-secondary small"></p></div><div class="report-export-actions"><button id="report-pdf" type="button" class="btn btn-outline-secondary">{{ __('reports.pdf') }}</button><button id="report-excel" type="button" class="btn btn-outline-secondary">{{ __('reports.excel') }}</button></div></div>
        <div id="report-metrics" class="report-metrics"></div>
        <p class="text-secondary small">{{ __('reports.comparison_hint') }}</p>
        <div class="panel report-chart-panel"><h3>{{ __('reports.daily') }}</h3><p class="text-secondary small">{{ __('reports.daily_hint') }}</p><div id="report-chart" role="img" aria-label="{{ __('reports.daily') }}"></div></div>
        <details class="report-quality" open><summary>{{ __('reports.quality') }}</summary><p id="report-quality-summary"></p><p>{{ __('reports.method') }}</p><p id="report-safety-hint" hidden>{{ __('reports.safety_hint') }}</p></details>
        <div class="panel report-data-panel"><div class="users-filters report-table-toolbar"><label class="users-search" for="report-search"><x-icon name="search" /><input id="report-search" type="search" maxlength="100" placeholder="{{ __('reports.search') }}" aria-label="{{ __('reports.search') }}"></label><div class="users-page-size"><label for="report-size">{{ __('reports.show') }}</label><select id="report-size" class="form-select">@foreach([5,10,25,50] as $size)<option value="{{ $size }}">{{ $size }}</option>@endforeach</select></div></div>
            <div id="report-table-wrap" class="table-responsive users-table-scroll" tabindex="0" aria-label="{{ __('reports.results') }}"><table class="table users-table align-middle"><thead id="report-head"></thead><tbody id="report-rows"></tbody></table></div>
            <div class="users-pagination"><p id="report-pagination" aria-live="polite"></p><nav aria-label="{{ __('reports.results') }}"><button id="report-previous" type="button" class="users-page-button" aria-label="{{ __('reports.previous') }}">‹</button><span id="report-pages"></span><button id="report-next" type="button" class="users-page-button" aria-label="{{ __('reports.next') }}">›</button></nav></div>
        </div>
        <p class="text-secondary small report-retention">{{ __('reports.expires_hint') }}</p>
    </div>
</section>
<div class="modal fade" id="report-detail" tabindex="-1" aria-labelledby="report-detail-title" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h2 class="modal-title fs-5" id="report-detail-title">{{ __('reports.detail') }}</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('reports.close') }}"></button></div><div class="modal-body"><p id="report-detail-period"></p><div id="report-detail-map"></div><p id="report-detail-message" role="status"></p></div></div></div></div>
@push('styles')<link rel="stylesheet" href="{{ asset('css/reports.css') }}?v=reports-20260928-3">@endpush
@pushOnce('scripts', 'apexcharts-library')<script src="{{ asset('vendor/apexcharts/apexcharts.js') }}?v=3.35.1" defer></script>@endPushOnce
@push('scripts')
<script type="application/json" id="report-config">{!! \Illuminate\Support\Js::encode(['base' => url('/reports'), 'locale' => app()->getLocale(), 'labels' => trans('reports'), 'vendor' => asset('vendor/reports')]) !!}</script>
<script type="module" src="{{ asset('js/reports.mjs') }}?v=reports-20260928-3"></script>
@endpush
@endif
