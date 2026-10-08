@props(['tone' => 'navy'])

<img {{ $attributes->merge(['class' => 'company-logo', 'alt' => 'EXAD — Solution & Services', 'width' => 931, 'height' => 441]) }} src="{{ ($branding ?? app(\App\Services\BrandingService::class)->context(auth()->user()))[$tone === 'light' ? 'internal_logo' : 'logo'] }}">
