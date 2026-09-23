<header class="app-topbar corporate-topbar" lang="{{ app()->getLocale() }}"
    data-overview-title="{{ __('Tableau de bord') }}"
    data-fleet-title="{{ __('Véhicules') }}"
    data-alerts-title="{{ __('Alertes') }}"
    data-map-title="{{ __('Carte') }}">
    <div class="topbar-leading">
        <button class="navbar-control sidebar-toggle d-none d-lg-inline-grid" type="button"
            data-sidebar-toggle aria-controls="app-sidebar" aria-expanded="true"
            aria-label="{{ __('Masquer le menu') }}"
            data-hide-label="{{ __('Masquer le menu') }}" data-show-label="{{ __('Afficher le menu') }}">
            <x-icon name="chevron" />
        </button>
        <button class="navbar-control sidebar-toggle d-lg-none" type="button" data-bs-toggle="offcanvas"
            data-bs-target="#app-sidebar" aria-controls="app-sidebar" aria-label="{{ __('Ouvrir le menu') }}">
            <x-icon name="menu" />
        </button>
        <div class="navbar-heading">
            <p class="navbar-title" data-view-label>{{ __('Tableau de bord') }}</p>
            <nav aria-label="{{ __('Fil d’Ariane') }}">
                <ol class="navbar-breadcrumb">
                    <li>{{ auth()->user()->isSuperadmin() || auth()->user()->isAdmin() ? __('Admin') : __('Espace de travail') }}</li>
                    <li aria-hidden="true">/</li>
                    <li aria-current="page" data-view-label>{{ __('Tableau de bord') }}</li>
                </ol>
            </nav>
        </div>
    </div>
    <div class="topbar-actions">
        <button class="navbar-control" type="button" data-fullscreen-toggle aria-pressed="false"
            aria-label="{{ __('Passer en plein écran') }}" title="{{ __('Passer en plein écran') }}"
            data-enter-label="{{ __('Passer en plein écran') }}" data-exit-label="{{ __('Quitter le plein écran') }}"
            data-unavailable-label="{{ __('Le plein écran n’est pas disponible dans ce navigateur.') }}">
            <x-icon name="expand" data-fullscreen-enter /><x-icon name="minimize" data-fullscreen-exit class="d-none" />
        </button>
        <button class="navbar-control" type="button" data-bs-toggle="modal" data-bs-target="#display-modal"
            aria-label="{{ __('Préférences d’affichage') }}" title="{{ __('Préférences d’affichage') }}">
            <x-icon name="settings" />
        </button>
        @if($dashboard['can_map'])
        <button class="navbar-control notification-button" type="button" data-switch-view="alerts"
            aria-label="{{ __('Consulter les alertes') }}" title="{{ __('Consulter les alertes') }}">
            <x-icon name="bell" /><span class="notification-badge" data-alert-count aria-hidden="true" @if(!$dashboard['metrics']['alerts']) hidden @endif>{{ $dashboard['metrics']['alerts'] }}</span>
        </button>
        @endif
        <x-language-switcher compact class="navbar-language" />
        <div class="dropdown navbar-account">
            <button class="account-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ __('Menu du compte') }}">
                <span class="topbar-avatar">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(auth()->user()->name, 0, 1)) }}</span>
                <span class="topbar-user-name">{{ auth()->user()->name }}</span>
                <x-icon name="down" class="account-chevron" />
            </button>
            <div class="dropdown-menu dropdown-menu-end account-menu">
                <div class="account-identity"><strong>{{ auth()->user()->name }}</strong><span>{{ auth()->user()->email }}</span></div>
                <div class="dropdown-divider"></div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="dropdown-item account-logout" type="submit"><x-icon name="logout" />{{ __('Se déconnecter') }}</button>
                </form>
            </div>
        </div>
    </div>
    <div class="navbar-feedback" role="status" data-navbar-feedback hidden></div>
</header>
