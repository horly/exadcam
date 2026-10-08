@extends('layouts.app')
@section('title', __('Tableau de bord').' · '.$branding['settings']['app_name'])
@section('content')
<div class="page-heading"><div><p class="eyebrow">{{ __('VOTRE ESPACE DE SUPERVISION') }}</p><h1 data-view-title>{{ __('Tableau de bord') }}</h1><p class="page-description" data-view-description>{{ __('Gardez un œil sur votre flotte. Et sur ce qui compte.') }}</p></div>
    @if($dashboard['can_map'])<button type="button" class="dashboard-map-action" data-switch-view="map"><x-icon name="pin" />{{ __('Voir la carte') }}</button>@endif
</div>
<p id="dashboard-sync" class="dashboard-sync overview-only" role="status">{{ __('Actualisation automatique toutes les 15 secondes') }}</p>
@include('partials.dashboard-metrics')
@if($googleMap['allowed'] || $googleMap['canVideo'])
<div class="monitoring-grid overview-only dashboard-live-grid {{ !$googleMap['allowed'] || !$googleMap['canVideo'] ? 'dashboard-live-grid-single' : '' }}">
    @if($googleMap['allowed']) @include('partials.dashboard-map', ['googleMap' => $googleMap]) @endif
    @include('partials.dashboard-video')
</div>
@endif
@if($dashboardCharts) @include('partials.dashboard-charts') @endif
<div class="detail-grid {{ !$dashboard['can_map'] ? 'dashboard-detail-single' : '' }}">
    <section class="panel fleet-section" aria-labelledby="fleet-title">
        <header class="panel-heading"><div class="panel-title"><x-icon name="truck" /><h2 id="fleet-title">{{ __(auth()->user()->isSuperadmin() ? 'Véhicules & dashcams' : 'Véhicules') }}</h2><span class="count-tag" id="dashboard-vehicle-count">{{ count($dashboard['vehicles']) }}</span></div></header>
        <div class="fleet-toolbar"><div class="status-filter" role="group" aria-label="{{ __('Filtrer les véhicules') }}">@foreach(['all' => 'Tous', 'online' => 'En ligne', 'offline' => 'Sans contact récent'] as $key => $label)<button type="button" data-fleet-status="{{ $key }}" @class(['active' => $key === 'all']) aria-pressed="{{ $key === 'all' ? 'true' : 'false' }}">{{ __($label) }}</button>@endforeach</div><label class="table-search"><x-icon name="search" /><input id="fleet-search" type="search" maxlength="100" placeholder="{{ __('Rechercher…') }}" aria-label="{{ __('Filtrer la liste des véhicules') }}"></label></div>
        <div class="table-responsive"><table class="table fleet-table align-middle mb-0"><thead><tr><th>{{ __('Véhicule') }}</th><th>{{ __('Flotte') }}</th><th>{{ __('Statut') }}</th><th>{{ $dashboard['can_map'] ? __('Vitesse') : __('Dernier contact') }}</th>@if($dashboard['can_video'])<th>{{ __('Caméras') }}</th>@endif</tr></thead><tbody id="dashboard-vehicles"></tbody></table></div>
        <p id="dashboard-fleet-empty" class="dashboard-empty" hidden>{{ __('Aucun véhicule ne correspond à votre recherche.') }}</p>
        <footer class="dashboard-pagination"><span id="dashboard-fleet-summary"></span><nav aria-label="{{ __('Pages des véhicules') }}" id="dashboard-fleet-pages"></nav></footer>
    </section>
    @if($dashboard['can_map'])
    <section class="panel alerts-section" aria-labelledby="alerts-title">
        <header class="panel-heading"><div class="panel-title"><x-icon name="bell" /><h2 id="alerts-title">{{ __('Dernières alertes') }}</h2><span class="count-tag" data-alert-count>{{ $dashboard['metrics']['alerts'] }}</span></div></header>
        <p class="dashboard-alert-caption">{{ __('Alarmes reçues sur 7 jours et pertes de contact en cours.') }}</p>
        @include('partials.alert-sound-controls')
        <div id="dashboard-alerts" class="dashboard-alert-list"></div>
        <p id="dashboard-alert-error" class="dashboard-empty" role="status" hidden></p>
        <footer class="dashboard-pagination alerts-pagination"><span id="dashboard-alert-summary"></span><nav aria-label="{{ __('Pages des alertes') }}" id="dashboard-alert-pages"></nav></footer>
        <button class="chart-card-link overview-only" data-switch-view="alerts" type="button">{{ __('Consulter les alertes') }}<x-icon name="arrow" /></button>
    </section>
    @endif
</div>
@include('reports.module')
@include('users.module')
@include('dashcams.module')
@include('recordings.module')
@include('server-logs.module')
@include('server-monitoring.module')
@include('customization.module')
@include('registry.module')
<noscript><p class="dashboard-empty">{{ __('Activez JavaScript pour afficher les listes et actualiser les données.') }}</p></noscript>
@include('partials.alert-notifications')
@endsection
@push('styles')<link rel="stylesheet" href="{{ asset('css/dashboard-real.css') }}?v=dashboard-widgets-20261006">@endpush
@push('scripts')
<script type="application/json" id="dashboard-real-config">{!! \Illuminate\Support\Js::encode(['data' => $dashboard, 'url' => route('dashboard.data'), 'alertsUrl' => route('dashboard.alerts'), 'locale' => app()->getLocale(), 'labels' => ['empty' => __('Aucune alerte reçue sur cette période.'), 'refresh' => __('Actualisé à'), 'failed' => __('Actualisation impossible. Les dernières données reçues restent affichées.'), 'alerts_failed' => __('Impossible de charger les alertes. Réessayez.'), 'from' => __('Affichage'), 'of' => __('sur'), 'video' => __('Vidéos'), 'previous' => __('Précédent'), 'next' => __('Suivant'), 'contact' => __('Dernier contact')]]) !!}</script>
<script src="{{ asset('js/dashboard-real.js') }}?v=cam-alerts-map-20261008" defer></script>
@endpush
