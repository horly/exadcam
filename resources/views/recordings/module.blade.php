@if(\App\Support\FleetAccess::allows(auth()->user(), \App\Models\User::PERMISSION_VIDEO_VIEW))
<section class="panel recordings-panel" data-module-view="recordings" data-module-title="{{ __('recordings.title') }}" data-module-description="{{ __('recordings.description') }}" id="recordings-module" hidden>
    <header class="panel-heading"><div class="panel-title"><x-icon name="camera" /><h2>{{ __('recordings.title') }}</h2></div></header>
    <div class="recordings-body">
        <form id="recordings-search" class="recordings-filters">
            <div class="recordings-vehicle"><label class="form-label" for="recordings-vehicle">{{ __('recordings.vehicle') }}</label>
                <select id="recordings-vehicle" class="form-select" required data-searchable-database data-search-placeholder="{{ __('recordings.search_vehicle') }}">
                    <option value="">{{ __('recordings.choose') }}</option>
                    @foreach($dashboardVideo as $vehicle)<option value="{{ $vehicle['device_id'] }}" data-channels="{{ $vehicle['channels'] }}" data-status="{{ $vehicle['status'] }}" data-status-tone="{{ $vehicle['connection'] }}">{{ $vehicle['label'] }}</option>@endforeach
                </select>
            </div>
            <div><label class="form-label" for="recordings-date">{{ __('recordings.date') }}</label><input type="date" class="form-control" id="recordings-date" value="{{ now('Africa/Kinshasa')->format('Y-m-d') }}" max="{{ now('Africa/Kinshasa')->format('Y-m-d') }}" required></div>
            <div><label class="form-label" for="recordings-channel">{{ __('recordings.channel') }}</label><select id="recordings-channel" class="form-select"><option value="0">{{ __('recordings.all_channels') }}</option></select></div>
            <button class="btn btn-primary" type="submit">{{ __('recordings.search') }}</button>
        </form>
        <p class="text-secondary small">{{ __('recordings.hint') }}</p>
        <p id="recordings-message" role="status" aria-live="polite">{{ __('recordings.initial') }}</p>
        <div class="users-filters recordings-table-toolbar">
            <label class="users-search" for="recordings-filter"><x-icon name="search" /><input id="recordings-filter" type="search" maxlength="100" placeholder="{{ __('recordings.search_results') }}" aria-label="{{ __('recordings.search_results') }}" disabled></label>
            <div class="users-page-size"><label for="recordings-page-size">{{ __('users.show') }}</label><select id="recordings-page-size" class="form-select form-select-sm" disabled>@foreach([5,10,25,50] as $size)<option value="{{ $size }}" @selected($size === 5)>{{ $size }}</option>@endforeach</select></div>
        </div>
        <div id="recordings-table" aria-busy="false">
            <div class="table-responsive users-table-scroll" tabindex="0" aria-label="{{ __('recordings.title') }}"><table class="table align-middle users-table recordings-table"><thead><tr>
                <th scope="col">#</th>
                @foreach(['channel','start','end','duration','size'] as $column)<th scope="col" aria-sort="{{ $column === 'start' ? 'descending' : 'none' }}"><button type="button" data-recording-sort="{{ $column }}" disabled>{{ __('recordings.'.$column) }}<span aria-hidden="true">{{ $column === 'start' ? '↓' : '↕' }}</span></button></th>@endforeach<th scope="col" class="text-end">{{ __('dashcams.actions') }}</th>
            </tr></thead><tbody id="recordings-rows"><tr><td colspan="7" class="users-empty">{{ __('recordings.initial') }}</td></tr></tbody></table></div>
            <div class="users-pagination recordings-pages">
                <p id="recordings-summary" aria-live="polite">{{ __('users.pagination_summary', ['first' => 0, 'last' => 0, 'total' => 0]) }}</p>
                <nav aria-label="{{ __('recordings.title') }}"><button id="recordings-previous" class="users-page-button" type="button" disabled aria-label="{{ __('recordings.previous') }}"><x-icon name="chevron" class="rotate-180" /></button><span id="recordings-page-numbers"></span><button id="recordings-next" class="users-page-button" type="button" disabled aria-label="{{ __('recordings.next') }}"><x-icon name="chevron" /></button></nav>
            </div>
        </div>
        <section class="recordings-preview" id="recordings-preview" hidden>
            <h3 id="recordings-clip-title"></h3>
            <form id="recordings-prepare" class="recordings-filters">
                <div><label class="form-label" for="recordings-from">{{ __('recordings.clip_start') }}</label><input type="datetime-local" step="1" class="form-control" id="recordings-from" required></div>
                <div><label class="form-label" for="recordings-to">{{ __('recordings.clip_end') }}</label><input type="datetime-local" step="1" class="form-control" id="recordings-to" required></div>
                <button class="btn btn-primary" type="submit">{{ __('recordings.prepare') }}</button>
            </form>
            <p class="text-secondary small">{{ __('recordings.clip_hint') }}</p>
            <p id="recordings-transfer-status" role="status" aria-live="polite"></p>
            <progress id="recordings-progress" max="100" value="0" hidden></progress>
            <video id="recordings-player" controls playsinline preload="metadata" hidden></video>
            <div class="recordings-actions"><button type="button" id="recordings-cancel" class="btn btn-outline-secondary" hidden>{{ __('recordings.cancel') }}</button><a id="recordings-download" class="btn btn-primary" hidden>{{ __('recordings.download') }}</a></div>
        </section>
    </div>
</section>
@push('styles')<link rel="stylesheet" href="{{ asset('css/recordings.css') }}?v=recordings-datatable-20260928">@endpush
@push('scripts')
<script type="application/json" id="recordings-config">{!! \Illuminate\Support\Js::encode(['baseUrl' => url('/dashcams'), 'locale' => app()->getLocale(), 'labels' => trans('recordings')]) !!}</script>
<script type="module" src="{{ asset('js/recordings.mjs') }}?v=recordings-report-context-20260928"></script>
@endpush
@endif
