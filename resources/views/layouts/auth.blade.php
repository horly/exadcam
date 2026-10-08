<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', __('Connexion')) · {{ $branding['settings']['app_name'] }}</title>
    @include('partials.favicons')
    @include('partials.fonts')
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/auth-login.css') }}?v=manrope-1">
    <link rel="stylesheet" href="{{ asset('css/auth-panel.css') }}?v=dynamic-login-1">
    @include('partials.branding-theme')
</head>
<body class="auth-body">
    <a class="visually-hidden-focusable auth-skip" href="#auth-content">{{ __('Aller au formulaire') }}</a>
    <div class="auth-shell">
        <aside class="auth-story" aria-label="{{ __('EXADCAM, supervision de flotte') }}">
            <div class="auth-photography" aria-hidden="true">
                <img src="{{ asset('images/brand/exadcam-dashcam-focus.webp') }}" alt="" width="1024" height="1536" fetchpriority="high">
            </div>
            <div class="story-shade" aria-hidden="true"></div>
            <div class="story-header">
                <div class="auth-brand company-brand"><img class="company-logo hero-company-logo" src="{{ $branding['internal_logo'] }}" alt="{{ $branding['settings']['app_name'] }}" width="931" height="441"><div class="brand-product"><strong>{{ $branding['settings']['short_name'] }}</strong><span>{{ __('DASHCAMS CONNECTÉES') }}</span></div></div>
            </div>
            <div class="story-bottom">
                <div class="story-content">
                    <div class="story-eyebrow"><span></span>{{ __('LA VIDÉO EMBARQUÉE AU PREMIER PLAN') }}</div>
                    <h2>{{ __('Chaque trajet.') }}<br> {{ __('Une vision complète.') }}</h2>
                    <p>{{ __('La géolocalisation et la vidéo embarquée, réunies au service de votre flotte.') }}</p>
                </div>
                <div class="story-features" aria-label="{{ __('Fonctionnalités de la plateforme') }}">
                    <span><x-icon name="pin" />{{ __('Suivi GPS') }}</span>
                    <span><x-icon name="camera" />{{ __('Vidéo embarquée') }}</span>
                    <span><x-icon name="shield" />{{ __('Sécurité de la flotte') }}</span>
                </div>
            </div>
        </aside>
        <main class="auth-main" id="auth-content">
            <header class="auth-main-header">
                <span class="workspace-caption"><x-icon name="lock" />{{ __('PORTAIL ENTREPRISE') }}</span>
                <x-language-switcher />
            </header>
            <div class="auth-form-container">@yield('content')</div>
            <footer class="auth-footer"><span>© {{ now()->year }} {{ $branding['settings']['short_name'] }}</span><span>{{ __('Tous droits réservés.') }}</span></footer>
        </main>
    </div>
    @stack('modals')
    <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}" defer></script>
    <script src="{{ asset('js/auth-login.js') }}?v=dynamic-login-1" defer></script>
</body>
</html>
