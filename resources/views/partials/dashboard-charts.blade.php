<div class="dashboard-charts overview-only" lang="{{ app()->getLocale() }}">
    <section class="panel activity-panel" aria-labelledby="activity-chart-title">
        <header class="chart-heading">
            <div><h2 id="activity-chart-title">{{ __('Activité de la flotte') }}</h2><p>{{ __('Véhicules ayant transmis des positions GPS, par période') }}</p></div>
            <div class="chart-period" role="group" aria-label="{{ __('Période du graphique') }}">
                <button type="button" data-chart-period="day" class="active" aria-pressed="true" aria-controls="fleet-activity-chart">{{ __('24 h') }}</button>
                <button type="button" data-chart-period="week" aria-pressed="false" aria-controls="fleet-activity-chart">{{ __('7 jours') }}</button>
            </div>
        </header>
        <div class="chart-legend" aria-hidden="true"><span><i class="chart-dot chart-dot-primary"></i>{{ __('Avec relevé GPS') }}</span><span><i class="chart-dot chart-dot-secondary"></i>{{ __('En déplacement') }}</span></div>
        <div class="activity-chart" id="fleet-activity-chart" aria-hidden="true"></div>
        <p class="chart-error" data-chart-error hidden>{{ __('Le graphique est indisponible. Les valeurs restent accessibles ci-dessous.') }}</p>
        <div class="chart-footnote"><span><x-icon name="info" />{{ __('Relevés enregistrés') }}</span><span data-chart-caption aria-live="polite">{{ $dashboardCharts['periods']['day']['caption'] }}</span></div>
        <details class="chart-data-details" id="activity-chart-table">
            <summary>{{ __('Voir les données du graphique') }}</summary>
            <div class="table-responsive"><table class="table table-sm mb-0">
                <caption class="visually-hidden" data-chart-table-caption>{{ $dashboardCharts['periods']['day']['caption'] }}</caption>
                <thead><tr><th scope="col">{{ __('Période') }}</th><th scope="col">{{ __('Avec relevé GPS') }}</th><th scope="col">{{ __('En déplacement') }}</th></tr></thead>
                <tbody data-chart-table-body>
                    @foreach ($dashboardCharts['periods']['day']['categories'] as $index => $label)
                        <tr><th scope="row">{{ $label }}</th><td>{{ $dashboardCharts['periods']['day']['online'][$index] }}</td><td>{{ $dashboardCharts['periods']['day']['moving'][$index] }}</td></tr>
                    @endforeach
                </tbody>
            </table></div>
        </details>
    </section>
    <section class="panel availability-panel" aria-labelledby="status-chart-title">
        <header class="chart-heading"><div><h2 id="status-chart-title">{{ __('État des dashcams') }}</h2><p>{{ __('Équipements activés · contact reçu depuis moins de 3 min') }}</p></div><span class="chart-heading-icon"><x-icon name="dashcam" /></span></header>
        <div class="status-chart" id="dashcam-status-chart" aria-hidden="true"></div>
        <dl class="status-chart-legend">
            @foreach ($dashboardCharts['status']['labels'] as $index => $label)
                <div><dt><i class="chart-dot chart-dot-status-{{ $index }}" aria-hidden="true"></i>{{ $label }}</dt><dd data-dashcam-series="{{ $index }}">{{ $dashboardCharts['status']['series'][$index] }}</dd></div>
            @endforeach
        </dl>
        <button type="button" class="chart-card-link" data-switch-view="fleet">{{ __('Consulter les véhicules') }}<x-icon name="arrow" /></button>
    </section>
</div>

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}?v=dashboard-real-1">
@endpush
@push('scripts')
    <script type="application/json" id="dashboard-chart-data">{!! \Illuminate\Support\Js::encode($dashboardCharts) !!}</script>
    <script src="{{ asset('vendor/apexcharts/apexcharts.js') }}?v=3.35.1" defer></script>
    <script src="{{ asset('js/dashboard-charts.js') }}?v=dashboard-real-1" defer></script>
@endpush
