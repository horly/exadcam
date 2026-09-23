<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'EXADCAM')</title>
    @include('partials.favicons')
    @include('partials.fonts')
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=manrope-1">
    <link rel="stylesheet" href="{{ asset('css/topbar.css') }}?v=navbar-compact-1">
    <link rel="stylesheet" href="{{ asset('css/sidebar.css') }}?v=sidebar-tree-1">
    <link rel="stylesheet" href="{{ asset('css/navigation.css') }}?v=nav-tree-1">
    <link rel="stylesheet" href="{{ asset('css/users.css') }}?v=users-1">
    @stack('styles')
</head>
<body class="app-body" data-view="overview">
    <a class="skip-link visually-hidden-focusable" href="#main-content">{{ __('Aller au contenu') }}</a>
    @include('partials.sidebar')
    <div class="app-workspace">
        @include('partials.topbar')
        <main id="main-content" class="workspace-content" tabindex="-1">
            @yield('content')
        </main>
        <footer class="workspace-footer"><span>EXADCAM <span class="footer-dot">·</span> {{ __('Supervision de flotte') }}</span><span>{{ __('Supervision en direct') }} <span class="version-tag">v0.1</span></span></footer>
    </div>
    @include('partials.preview-modals')
    <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}" defer></script>
    <script src="{{ asset('js/app.js') }}?v=dashboard-real-1" defer></script>
    <script src="{{ asset('js/topbar.js') }}?v=navbar-1" defer></script>
    @stack('scripts')
</body>
</html>
