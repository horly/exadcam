@if($branding['settings']['support_email'] || $branding['settings']['support_phone'] || $branding['settings']['website_url'])
<div class="brand-support">
    <strong>{{ __('customization.contact') }}</strong>
    @if($branding['settings']['support_email'])<a href="mailto:{{ $branding['settings']['support_email'] }}">{{ $branding['settings']['support_email'] }}</a>@endif
    @if($branding['settings']['support_phone'])<a href="tel:{{ preg_replace('/[^+0-9]/', '', $branding['settings']['support_phone']) }}">{{ $branding['settings']['support_phone'] }}</a>@endif
    @if($branding['settings']['website_url'])<a href="{{ $branding['settings']['website_url'] }}" target="_blank" rel="noopener noreferrer">{{ $branding['settings']['website_url'] }}</a>@endif
</div>
@endif
