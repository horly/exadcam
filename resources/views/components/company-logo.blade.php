@props(['tone' => 'navy'])

<img {{ $attributes->merge(['class' => 'company-logo', 'alt' => 'EXAD — Solution & Services', 'width' => 931, 'height' => 441]) }} src="{{ asset('images/brand/exad-logo-'.($tone === 'light' ? 'light' : 'navy').'.svg') }}">
