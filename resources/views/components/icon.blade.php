@props(['name'])
<svg {{ $attributes->merge(['class' => 'icon']) }} width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><use href="{{ asset('images/icons.svg') }}#{{ $name }}"></use></svg>
