<aside class="app-sidebar corporate-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="app-sidebar" aria-label="{{ __('Navigation principale') }}" lang="{{ app()->getLocale() }}">
    <div class="sidebar-brand">
        <a href="#overview" data-nav="overview" class="brand-link brand-link-official" aria-label="{{ __('EXADCAM, vue d’ensemble') }}">
            <x-company-logo tone="light" class="sidebar-company-logo" />
            <span class="sidebar-product-name">EXADCAM<small>{{ __('VIDÉO & GPS') }}</small></span>
        </a>
        <button type="button" class="icon-button sidebar-dismiss d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#app-sidebar" aria-label="{{ __('Fermer le menu') }}"><x-icon name="close" /></button>
    </div>

    <div class="sidebar-scroll">
        <p class="nav-section-label" id="supervision-label">{{ __('SUPERVISION') }}</p>
        <nav class="sidebar-nav nav flex-column" aria-labelledby="supervision-label">
            <a class="nav-link active" href="#overview" data-nav="overview" aria-current="page"><span class="nav-icon"><x-icon name="grid" /></span><span>{{ __('Tableau de bord') }}</span></a>
            @if(\App\Support\FleetAccess::allows(auth()->user(), \App\Models\User::PERMISSION_MAP_VIEW))<a class="nav-link" href="#map" data-nav="map"><span class="nav-icon"><x-icon name="pin" /></span><span>{{ __('Carte') }}</span></a>@endif
            @if(\App\Support\FleetAccess::browse(auth()->user()))
            @if(auth()->user()->isSuperadmin())
            <details class="sidebar-group">
                <summary class="nav-link"><span class="nav-icon"><x-icon name="truck" /></span><span>{{ __('Flottes') }}</span><x-icon name="down" class="nav-group-chevron" /></summary>
                <ul class="sidebar-submenu">
                    @if(\App\Support\FleetAccess::registry(auth()->user(), 'fleets'))<li><a class="nav-link" href="#fleets" data-nav="fleets"><span class="nav-icon"><x-icon name="fleets" /></span><span>{{ __('Flottes') }}</span></a></li>@endif
                    @if(\App\Support\FleetAccess::registry(auth()->user(), 'vehicles'))<li><a class="nav-link" href="#vehicles" data-nav="vehicles"><span class="nav-icon"><x-icon name="truck" /></span><span>{{ __('Véhicules') }}</span></a></li>@endif
                    @if(\App\Support\FleetAccess::dashcams(auth()->user()))<li><a class="nav-link" href="#dashcams" data-nav="dashcams"><span class="nav-icon"><x-icon name="dashcam" /></span><span>{{ __('Dashcams') }}</span></a></li>@endif
                    @if(\App\Support\FleetAccess::registry(auth()->user(), 'departments'))<li><a class="nav-link" href="#departments" data-nav="departments"><span class="nav-icon"><x-icon name="departments" /></span><span>{{ __('Départements') }}</span></a></li>@endif
                </ul>
            </details>
            @else
                @if(\App\Support\FleetAccess::registry(auth()->user(), 'vehicles'))<a class="nav-link" href="#vehicles" data-nav="vehicles"><span class="nav-icon"><x-icon name="truck" /></span><span>{{ __('Véhicules') }}</span></a>@endif
                @if(\App\Support\FleetAccess::dashcams(auth()->user()))<a class="nav-link" href="#dashcams" data-nav="dashcams"><span class="nav-icon"><x-icon name="dashcam" /></span><span>{{ __('Dashcams') }}</span></a>@endif
                @if(\App\Support\FleetAccess::registry(auth()->user(), 'departments'))<a class="nav-link" href="#departments" data-nav="departments"><span class="nav-icon"><x-icon name="departments" /></span><span>{{ __('Départements') }}</span></a>@endif
            @endif
            @endif
            @if($dashboard['can_map'])<a class="nav-link" href="#alerts" data-nav="alerts"><span class="nav-icon"><x-icon name="bell" /></span><span>{{ __('Alertes') }}</span><span class="nav-count alert-count" data-alert-count @if(!$dashboard['metrics']['alerts']) hidden @endif>{{ $dashboard['metrics']['alerts'] }}</span></a>@endif
            @if(\App\Support\FleetAccess::allows(auth()->user(), \App\Models\User::PERMISSION_REPORTS_GENERATE))<a class="nav-link" href="#reports" data-nav="reports"><span class="nav-icon"><x-icon name="report" /></span><span>{{ __('Rapports') }}</span></a>@endif
            @can('viewAny', \App\Models\User::class)
                <a class="nav-link" href="#users" data-nav="users"><span class="nav-icon"><x-icon name="users" /></span><span>{{ __('Utilisateurs') }}</span></a>
            @endcan
        </nav>

        <p class="nav-section-label nav-secondary-label" id="preferences-label">{{ __('PRÉFÉRENCES') }}</p>
        <nav class="sidebar-nav nav flex-column" aria-labelledby="preferences-label">
            <button type="button" class="nav-link" data-bs-toggle="modal" data-bs-target="#display-modal"><span class="nav-icon"><x-icon name="settings" /></span><span>{{ __('Affichage') }}</span></button>
        </nav>
    </div>

    <div class="sidebar-footer">
        <button type="button" class="sidebar-help" data-bs-toggle="modal" data-bs-target="#help-modal">
            <span class="sidebar-help-icon"><x-icon name="help" /></span>
            <span><strong>{{ __('Besoin d’aide ?') }}</strong><small>{{ __('Guide de la plateforme') }}</small></span>
            <x-icon name="chevron" class="sidebar-help-arrow" />
        </button>
        <div class="sidebar-footer-brand"><span>{{ __('UNE SOLUTION EXAD') }}</span><span>v0.1</span></div>
    </div>
</aside>
