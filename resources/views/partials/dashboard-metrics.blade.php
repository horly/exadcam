@php
    $superadmin = auth()->user()->isSuperadmin();
    $canVehicles = \App\Support\FleetAccess::registry(auth()->user(), 'vehicles');
    $cards = [
        ['key' => 'vehicles', 'title' => __('Véhicules équipés'), 'icon' => 'truck', 'tone' => 'indigo',
            'unit' => __('véhicules'), 'footer_key' => 'fleets', 'footer' => __('flotte(s) représentée(s)'),
            'href' => $canVehicles ? '#vehicles' : (\App\Support\FleetAccess::browse(auth()->user()) ? '#fleet' : null)],
        ['key' => $superadmin ? 'online' : 'online_vehicles', 'title' => __('dashboard.'.($superadmin ? 'online_dashcams' : 'online_vehicles')), 'icon' => 'signal', 'tone' => 'green',
            'total_key' => $superadmin ? 'dashcams' : 'vehicles', 'footer_key' => $superadmin ? 'offline' : 'offline_vehicles', 'footer' => __('sans contact récent'),
            'href' => $dashboard['can_map'] ? '#map?connection=online' : null],
        ['key' => $superadmin ? 'offline' : 'offline_vehicles', 'title' => __('dashboard.'.($superadmin ? 'offline_dashcams' : 'offline_vehicles')), 'icon' => 'signal', 'tone' => 'amber',
            'unit' => $superadmin ? __('équipements') : __('véhicules'), 'footer' => __('dashboard.'.($superadmin ? 'active_dashcams' : 'equipped_vehicles')),
            'href' => $dashboard['can_map'] ? '#map?connection=offline' : null],
    ];
    if ($dashboard['can_map']) $cards[] = ['key' => 'alerts', 'title' => __('Alertes à consulter'), 'icon' => 'bell', 'tone' => 'amber',
        'unit' => __('événements'), 'footer' => __('7 jours · pertes de contact en cours'), 'href' => '#alerts'];
@endphp
<section class="metrics-grid overview-only" aria-label="{{ __('Indicateurs de la flotte') }}">
    @foreach($cards as $card)
        @if($card['href'])<a class="metric-card metric-card-link" href="{{ $card['href'] }}" aria-label="{{ $card['title'] }}">@else<article class="metric-card">@endif
            <div class="metric-heading"><span>{{ $card['title'] }}</span><span class="metric-icon {{ $card['tone'] }}"><x-icon :name="$card['icon']" /></span></div>
            <div class="metric-number"><strong data-metric="{{ $card['key'] }}">{{ $dashboard['metrics'][$card['key']] }}</strong><span>@if(isset($card['total_key']))/ <span data-metric="{{ $card['total_key'] }}">{{ $dashboard['metrics'][$card['total_key']] }}</span>@else{{ $card['unit'] }}@endif</span></div>
            <div class="metric-bottom">@if(isset($card['footer_key']))<span data-metric="{{ $card['footer_key'] }}">{{ $dashboard['metrics'][$card['footer_key']] }}</span> @endif{{ $card['footer'] }}</div>
        @if($card['href'])</a>@else</article>@endif
    @endforeach
</section>
