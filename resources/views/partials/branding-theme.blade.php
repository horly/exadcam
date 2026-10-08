@php($branding ??= app(\App\Services\BrandingService::class)->context(auth()->user()))
<style id="branding-theme">
    :root {
        @foreach($branding['colors'] as $key => $color)
        --brand-{{ str_replace('_', '-', str_replace('_color', '', $key)) }}: {{ $color }};
        @endforeach
        --brand-button-text: {{ \App\Services\BrandingService::foreground($branding['colors']['button_color']) }};
        --brand-avatar-text: {{ \App\Services\BrandingService::foreground($branding['colors']['avatar_color']) }};
        --brand-sidebar-text: {{ \App\Services\BrandingService::foreground($branding['colors']['sidebar_end_color']) }};
    }
</style>
<link rel="stylesheet" href="{{ asset('css/branding.css') }}?v=branding-1">
