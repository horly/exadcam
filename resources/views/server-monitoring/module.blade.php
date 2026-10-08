@if(auth()->user()->isSuperadmin())
<section id="server-monitoring-module" class="server-monitoring" data-module-view="server-monitoring" data-module-title="{{ __('server_monitoring.title') }}" data-module-description="{{ __('server_monitoring.description') }}" hidden>
    <div class="sm-toolbar">
        <div class="sm-status" role="status"><span id="sm-state" class="sm-state">{{ __('server_monitoring.loading') }}</span><span id="sm-updated"></span></div>
    </div>
    <div class="sm-metrics">
        @foreach(['cpu' => 'settings', 'ram' => 'grid', 'disk' => 'report', 'load' => 'signal'] as $key => $icon)
        <article class="sm-card sm-metric sm-tone-{{ $key }}">
            <div class="sm-metric-heading"><h2>{{ __('server_monitoring.'.$key) }}</h2><span class="sm-icon"><x-icon :name="$icon" /></span></div>
            <strong class="sm-value" id="sm-{{ $key }}">—</strong><p class="sm-detail" id="sm-{{ $key }}-detail">{{ __('server_monitoring.unknown') }}</p>
            @if($key !== 'load')<div class="sm-meter" aria-hidden="true"><span id="sm-{{ $key }}-bar"></span></div>@endif
        </article>
        @endforeach
    </div>
    <p id="sm-history-note" class="sm-note">{{ __('server_monitoring.history_note') }}</p>
    <div class="sm-grid">
        <article class="sm-card sm-chart-card"><header><span class="sm-kicker">{{ __('server_monitoring.performance') }}</span><h2>{{ __('server_monitoring.cpu_memory') }}</h2></header><div id="sm-performance-chart" class="sm-chart" aria-hidden="true"></div><p class="sm-chart-empty" data-chart-message="performance">{{ __('server_monitoring.history_empty') }}</p></article>
        <article class="sm-card sm-disk-card"><header><span class="sm-kicker">{{ __('server_monitoring.capacity') }}</span><h2>{{ __('server_monitoring.disk_usage') }}</h2></header><div id="sm-disk-ring" class="sm-disk-ring" role="img" aria-label="{{ __('server_monitoring.unknown') }}"><div><span>{{ __('server_monitoring.disk') }}</span><strong id="sm-disk-ring-value">—</strong></div></div><p id="sm-disk-free" class="sm-note"></p><p class="sm-storage-note">{{ __('server_monitoring.disk_scope') }}</p></article>
        <article class="sm-card sm-chart-card"><header><span class="sm-kicker">{{ __('server_monitoring.network') }}</span><h2>{{ __('server_monitoring.network_traffic') }}</h2></header><div id="sm-network-chart" class="sm-chart" aria-hidden="true"></div><p class="sm-chart-empty" data-chart-message="network">{{ __('server_monitoring.history_empty') }}</p></article>
        <article class="sm-card sm-chart-card"><header><span class="sm-kicker">{{ __('server_monitoring.performance') }}</span><h2>{{ __('server_monitoring.system_load') }}</h2></header><div id="sm-load-chart" class="sm-chart" aria-hidden="true"></div><p class="sm-chart-empty" data-chart-message="load">{{ __('server_monitoring.history_empty') }}</p></article>
        <article class="sm-card sm-network-card"><header><h2>{{ __('server_monitoring.network') }}</h2></header><div class="sm-network-totals"><div><span>{{ __('server_monitoring.incoming') }}</span><strong id="sm-rx">—</strong></div><div><span>{{ __('server_monitoring.outgoing') }}</span><strong id="sm-tx">—</strong></div></div>
            <div class="sm-table-wrap"><table class="sm-table"><caption class="visually-hidden">{{ __('server_monitoring.network') }}</caption><thead><tr>@foreach(['interface', 'received', 'sent', 'incoming', 'outgoing'] as $label)<th scope="col">{{ __('server_monitoring.'.$label) }}</th>@endforeach</tr></thead><tbody id="sm-interfaces"><tr><td colspan="5">{{ __('server_monitoring.no_interfaces') }}</td></tr></tbody></table></div><p class="sm-note">{{ __('server_monitoring.network_note') }}</p>
        </article>
        <article class="sm-card"><header><h2>{{ __('server_monitoring.system_info') }}</h2></header><dl class="sm-system">@foreach(['hostname', 'os', 'uptime', 'php', 'laravel', 'environment', 'swap'] as $key)<div><dt>{{ in_array($key, ['php', 'laravel']) ? ($key === 'php' ? 'PHP' : 'Laravel') : __('server_monitoring.'.$key) }}</dt><dd id="sm-system-{{ $key }}">—</dd></div>@endforeach</dl></article>
    </div>
    <details class="sm-card sm-history"><summary>{{ __('server_monitoring.history_data') }}</summary><div class="sm-table-wrap"><table class="sm-table"><caption class="visually-hidden">{{ __('server_monitoring.history_data') }}</caption><thead><tr>@foreach(['time', 'cpu', 'ram', 'incoming', 'outgoing', 'load'] as $key)<th scope="col">{{ __('server_monitoring.'.$key) }}</th>@endforeach</tr></thead><tbody id="sm-history-rows"></tbody></table></div></details>
</section>
@push('styles')<link rel="stylesheet" href="{{ asset('css/server-monitoring.css') }}?v=monitoring-4">@endpush
@pushOnce('scripts', 'apexcharts-library')<script src="{{ asset('vendor/apexcharts/apexcharts.js') }}?v=3.35.1" defer></script>@endPushOnce
@push('scripts')
<script id="server-monitoring-config" type="application/json">{!! \Illuminate\Support\Js::encode(['url' => route('server-monitoring.metrics'), 'locale' => app()->getLocale(), 'labels' => trans('server_monitoring')]) !!}</script>
<script src="{{ asset('js/server-monitoring.js') }}?v=monitoring-4" defer></script>
@endpush
@endif
