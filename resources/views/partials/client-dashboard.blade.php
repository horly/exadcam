<div class="page-heading"><div><p class="eyebrow">{{ __('dashcams.fleet_scope') }} · {{ auth()->user()->fleet?->name }}</p><h1 data-view-title>{{ __('Tableau de bord') }}</h1><p class="page-description" data-view-description>{{ __('dashcams.fleet_scope_hint') }}</p></div></div>
<div class="users-overview overview-only">@foreach($clientOverview as $key => $count)<div class="users-stat"><span class="users-stat-icon"><x-icon name="{{ $key === 'vehicles' ? 'truck' : 'camera' }}" /></span><div><strong>{{ $count }}</strong><span>{{ __('dashcams.'.$key) }}</span></div></div>@endforeach</div>
@if($googleMap['allowed'] || $googleMap['canVideo'])
<div class="monitoring-grid overview-only {{ $googleMap['allowed'] && $googleMap['canVideo'] ? 'dashboard-live-grid' : 'dashboard-single-grid' }}" @if(!$googleMap['allowed'] || !$googleMap['canVideo']) style="grid-template-columns:minmax(0,1fr)" @endif>
    @if($googleMap['allowed']) @include('partials.dashboard-map', ['googleMap' => $googleMap]) @endif
    @include('partials.dashboard-video')
</div>
@else
<div class="panel p-4 overview-only">{{ __('dashcams.fleet_scope_hint') }}</div>
@endif
