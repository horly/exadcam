@if($branding['favicon'] ?? null)
<link rel="icon" href="{{ $branding['favicon'] }}" type="image/png">
<link rel="apple-touch-icon" href="{{ $branding['favicon'] }}">
@else
<link rel="icon" href="{{ asset('favicon.ico') }}?v=exadcam-1" type="image/x-icon" sizes="16x16 32x32 48x48">
<link rel="icon" href="{{ asset('images/favicon.svg') }}?v=exadcam-1" type="image/svg+xml" sizes="any">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}?v=exadcam-1" sizes="180x180">

@endif
