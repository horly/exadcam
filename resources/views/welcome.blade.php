@extends('layouts.app')
@section('title', 'Tableau de bord · EXADCAM')
@section('content')
<div class="page-heading"><div><p class="eyebrow">{{ __('VOTRE ESPACE DE SUPERVISION') }}</p><h1 data-view-title>{{ __('Tableau de bord') }}</h1><p class="page-description" data-view-description>{{ __('Gardez un œil sur votre flotte. Et sur ce qui compte.') }}</p></div>
    @if($dashboard['can_map'])<button type="button" class="dashboard-map-action" data-switch-view="map"><x-icon name="pin" />{{ __('Voir la carte') }}</button>@endif
</div>
<p id="dashboard-sync" class="dashboard-sync overview-only" role="status">{{ __('Actualisation automatique toutes les 15 secondes') }}</p>
<section class="metrics-grid overview-only" aria-label="{{ __('Indicateurs de la flotte') }}">
    <article class="metric-card"><div class="metric-heading"><span>{{ __('Véhicules équipés') }}</span><span class="metric-icon indigo"><x-icon name="truck" /></span></div><div class="metric-number"><strong data-metric="vehicles">{{ $dashboard['metrics']['vehicles'] }}</strong><span>{{ __('véhicules') }}</span></div><div class="metric-bottom"><span data-metric="fleets">{{ $dashboard['metrics']['fleets'] }}</span> {{ __('flotte(s) représentée(s)') }}</div></article>
    <article class="metric-card"><div class="metric-heading"><span>{{ __('Dashcams en ligne') }}</span><span class="metric-icon green"><x-icon name="signal" /></span></div><div class="metric-number"><strong data-metric="online">{{ $dashboard['metrics']['online'] }}</strong><span>/ <span data-metric="dashcams">{{ $dashboard['metrics']['dashcams'] }}</span></span></div><div class="metric-bottom"><span data-metric="offline">{{ $dashboard['metrics']['offline'] }}</span> {{ __('sans contact récent') }}</div></article>
    <article class="metric-card"><div class="metric-heading"><span>{{ __('Dashcams hors ligne') }}</span><span class="metric-icon amber"><x-icon name="signal" /></span></div><div class="metric-number"><strong data-metric="offline">{{ $dashboard['metrics']['offline'] }}</strong><span>{{ __('équipements') }}</span></div><div class="metric-bottom">{{ __('Sur les dashcams activées') }}</div></article>
    @if($dashboard['can_map'])<article class="metric-card"><div class="metric-heading"><span>{{ __('Alertes à consulter') }}</span><span class="metric-icon amber"><x-icon name="bell" /></span></div><div class="metric-number"><strong data-metric="alerts">{{ $dashboard['metrics']['alerts'] }}</strong><span>{{ __('événements') }}</span></div><div class="metric-bottom">{{ __('7 jours · pertes de contact en cours') }}</div></article>@endif
</section>
@if($googleMap['allowed'] || $googleMap['canVideo'])
<div class="monitoring-grid overview-only dashboard-live-grid {{ !$googleMap['allowed'] || !$googleMap['canVideo'] ? 'dashboard-live-grid-single' : '' }}">
    @if($googleMap['allowed']) @include('partials.dashboard-map', ['googleMap' => $googleMap]) @endif
    @include('partials.dashboard-video')
</div>
@endif
@if($dashboardCharts) @include('partials.dashboard-charts') @endif
<div class="detail-grid {{ !$dashboard['can_map'] ? 'dashboard-detail-single' : '' }}">
    <section class="panel fleet-section" aria-labelledby="fleet-title">
        <header class="panel-heading"><div class="panel-title"><x-icon name="truck" /><h2 id="fleet-title">{{ __('Véhicules & dashcams') }}</h2><span class="count-tag" id="dashboard-vehicle-count">{{ count($dashboard['vehicles']) }}</span></div></header>
        <div class="fleet-toolbar"><div class="status-filter" role="group" aria-label="{{ __('Filtrer les véhicules') }}">@foreach(['all' => 'Tous', 'online' => 'En ligne', 'offline' => 'Sans contact récent'] as $key => $label)<button type="button" data-fleet-status="{{ $key }}" @class(['active' => $key === 'all']) aria-pressed="{{ $key === 'all' ? 'true' : 'false' }}">{{ __($label) }}</button>@endforeach</div><label class="table-search"><x-icon name="search" /><input id="fleet-search" type="search" maxlength="100" placeholder="{{ __('Rechercher…') }}" aria-label="{{ __('Filtrer la liste des véhicules') }}"></label></div>
        <div class="table-responsive"><table class="table fleet-table align-middle mb-0"><thead><tr><th>{{ __('Véhicule') }}</th><th>{{ __('Flotte') }}</th><th>{{ __('Statut') }}</th><th>{{ $dashboard['can_map'] ? __('Vitesse') : __('Dernier contact') }}</th>@if($dashboard['can_video'])<th>{{ __('Caméras') }}</th>@endif</tr></thead><tbody id="dashboard-vehicles"></tbody></table></div>
        <p id="dashboard-fleet-empty" class="dashboard-empty" hidden>{{ __('Aucun véhicule ne correspond à votre recherche.') }}</p>
        <footer class="dashboard-pagination"><span id="dashboard-fleet-summary"></span><nav aria-label="{{ __('Pages des véhicules') }}" id="dashboard-fleet-pages"></nav></footer>
    </section>
    @if($dashboard['can_map'])
    <section class="panel alerts-section" aria-labelledby="alerts-title">
        <header class="panel-heading"><div class="panel-title"><x-icon name="bell" /><h2 id="alerts-title">{{ __('Dernières alertes') }}</h2><span class="count-tag" data-alert-count>{{ $dashboard['metrics']['alerts'] }}</span></div></header>
        <p class="dashboard-alert-caption">{{ __('Alarmes reçues sur 7 jours et pertes de contact en cours.') }}</p>
        <div id="dashboard-alerts" class="dashboard-alert-list"></div>
        <p id="dashboard-alert-error" class="dashboard-empty" role="status" hidden></p>
        <footer class="dashboard-pagination alerts-pagination"><span id="dashboard-alert-summary"></span><nav aria-label="{{ __('Pages des alertes') }}" id="dashboard-alert-pages"></nav></footer>
        <button class="chart-card-link overview-only" data-switch-view="alerts" type="button">{{ __('Consulter les alertes') }}<x-icon name="arrow" /></button>
    </section>
    @endif
</div>
@include('partials.navigation-modules')
@include('users.module')
@include('dashcams.module')
@include('registry.module')
<noscript><p class="dashboard-empty">{{ __('Activez JavaScript pour afficher les listes et actualiser les données.') }}</p></noscript>
@endsection
@push('styles')<link rel="stylesheet" href="{{ asset('css/dashboard-real.css') }}?v=dashboard-real-2">@endpush
@push('scripts')
<script type="application/json" id="dashboard-real-config">{!! \Illuminate\Support\Js::encode(['data' => $dashboard, 'url' => route('dashboard.data'), 'alertsUrl' => route('dashboard.alerts'), 'locale' => app()->getLocale(), 'labels' => ['empty' => __('Aucune alerte reçue sur cette période.'), 'refresh' => __('Actualisé à'), 'failed' => __('Actualisation impossible. Les dernières données reçues restent affichées.'), 'alerts_failed' => __('Impossible de charger les alertes. Réessayez.'), 'from' => __('Affichage'), 'of' => __('sur'), 'video' => __('Vidéos'), 'previous' => __('Précédent'), 'next' => __('Suivant'), 'contact' => __('Dernier contact')]]) !!}</script>
<script src="{{ asset('js/dashboard-real.js') }}?v=dashboard-real-1" defer></script>
@endpush
