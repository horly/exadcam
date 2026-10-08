@if($branding['can_manage'])
@php($globalBranding = $branding['global'])
@php($settings = $branding['settings'])
<section id="customization-module" class="customization-module" data-module-view="customization" data-module-title="{{ __('customization.title') }}" data-module-description="{{ __('customization.'.($globalBranding ? 'global_description' : 'fleet_description')) }}" hidden>
    <div class="customization-intro"><span class="customization-scope"><x-icon name="shield" />{{ __('customization.'.($globalBranding ? 'global_scope' : 'fleet_settings')) }}</span>@unless($globalBranding)<strong>{{ $branding['fleet_name'] }}</strong>@endunless</div>
    @if(session('customization_status'))<p class="customization-success" role="status">{{ session('customization_status') }}</p>@endif
    <form id="customization-form" action="{{ route($globalBranding ? 'customization.global' : 'customization.fleet') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @if($globalBranding)
        <section class="customization-card"><header><span class="customization-section-icon"><x-icon name="grid" /></span><div><h2>{{ __('customization.identity') }}</h2><p>{{ __('customization.identity_help') }}</p></div></header><div class="customization-fields">
            @foreach(['app_name' => ['text',80], 'short_name' => ['text',24], 'website_url' => ['url',255]] as $key => [$type,$max])
            <div class="customization-field"><label for="custom-{{ $key }}">{{ __('customization.'.$key) }}@if($key !== 'website_url') <span aria-hidden="true">*</span>@endif</label><input class="form-control" id="custom-{{ $key }}" name="{{ $key }}" type="{{ $type }}" maxlength="{{ $max }}" value="{{ $settings[$key] }}" @required($key !== 'website_url') aria-describedby="custom-error-{{ $key }}"><p class="customization-field-error" id="custom-error-{{ $key }}" data-field-error="{{ $key }}" hidden></p></div>
            @endforeach
        </div></section>
        <section class="customization-card"><header><span class="customization-section-icon"><x-icon name="pin" /></span><div><h2>{{ __('customization.map') }}</h2><p>{{ __('customization.map_help') }}</p></div></header><div class="customization-fields">
            <div class="customization-field"><label>{{ __('customization.map_provider') }}</label><p class="customization-static">Google Maps</p></div>
            <div class="customization-field"><label for="custom-map_type">{{ __('customization.map_type') }}</label><select id="custom-map_type" name="map_type" class="form-select">@foreach(['roadmap','hybrid','satellite','terrain'] as $type)<option value="{{ $type }}" @selected($settings['map_type'] === $type)>{{ __('customization.'.$type) }}</option>@endforeach</select><p class="customization-field-error" data-field-error="map_type" hidden></p></div>
        </div></section>
        @endif
        @unless($globalBranding)
        <section class="customization-card"><header><span class="customization-section-icon"><x-icon name="fleets" /></span><div><h2>{{ __('customization.fleet_identity') }}</h2><p>{{ __('customization.fleet_name_help') }}</p></div></header><div class="customization-fields"><div class="customization-field"><label for="custom-fleet_name">{{ __('customization.fleet_name') }} <span aria-hidden="true">*</span></label><input id="custom-fleet_name" name="fleet_name" type="text" class="form-control" value="{{ $branding['fleet_name'] }}" required maxlength="255" aria-describedby="custom-error-fleet_name"><p id="custom-error-fleet_name" class="customization-field-error" data-field-error="fleet_name" hidden></p></div></div></section>
        @endunless
        <section class="customization-card"><header><span class="customization-section-icon"><x-icon name="camera" /></span><div><h2>{{ __('customization.'.($globalBranding ? 'visual' : 'fleet_scope')) }}</h2><p>{{ __('customization.'.($globalBranding ? 'visual_help' : 'fleet_help')) }}</p></div></header>
            <div class="customization-assets {{ !$globalBranding ? 'customization-assets-fleet' : '' }}">
            @foreach($globalBranding ? ['logo','internal_logo','favicon'] : ['logo'] as $kind)
                @php($assetUrl = $globalBranding ? ($branding[$kind] ?? asset('apple-touch-icon.png')) : $branding['sidebar_logo'])
                @php($hasCustom = $globalBranding ? $branding['custom_images'][$kind] : (bool) $branding['fleet_logo'])
                <div class="customization-asset"><div class="customization-image {{ $kind === 'internal_logo' || !$globalBranding ? 'is-dark' : '' }}"><img src="{{ $assetUrl }}" alt="{{ __('customization.'.$kind) }}" data-custom-image="{{ $kind }}"></div><div class="customization-asset-controls"><label class="customization-asset-label" for="custom-{{ $kind }}">{{ __('customization.'.($globalBranding ? $kind : 'fleet_scope')) }}</label><input id="custom-{{ $kind }}" name="{{ $kind }}" type="file" accept="image/png,image/jpeg,image/webp" class="form-control" data-image-input="{{ $kind }}" data-max-size="{{ $kind === 'favicon' ? 1048576 : 2097152 }}"><small>{{ __('customization.'.($kind === 'favicon' ? 'favicon_help' : 'logo_help')) }}</small><p class="customization-field-error" data-field-error="{{ $kind }}" hidden></p>
                <label class="customization-reset-image" @if(!$hasCustom) hidden @endif><input type="checkbox" name="remove_{{ $kind }}" value="1" data-remove-image="{{ $kind }}">{{ __('customization.'.(!$globalBranding ? 'fleet_remove' : ($kind === 'favicon' ? 'remove_favicon' : 'remove'))) }}</label></div></div>
            @endforeach
            </div>
            <div class="customization-preview-heading"><span>{{ __('customization.preview') }}</span><small>{{ __('customization.preview_only') }}</small></div>
            <div class="customization-sidebar-preview"><img id="customization-sidebar-logo" src="{{ $branding['sidebar_logo'] }}" alt="{{ __('customization.preview') }}"><div><strong id="customization-sidebar-name">{{ $globalBranding ? $settings['short_name'] : 'EXADCAM' }}</strong><small>{{ __('VIDÉO & GPS') }}</small></div></div>
        </section>
        @if($globalBranding)
        <section class="customization-card"><header><span class="customization-section-icon"><x-icon name="settings" /></span><div><h2>{{ __('customization.colors') }}</h2><p>{{ __('customization.colors_help') }}</p></div><button type="button" id="customization-restore-colors" class="customization-button"><x-icon name="refresh" />{{ __('customization.restore_colors') }}</button></header><div class="customization-colors">
            @foreach($branding['colors'] as $key => $color)
            <div class="customization-field"><label for="custom-{{ $key }}">{{ __('customization.'.$key) }}</label><div class="customization-color"><input type="color" id="custom-{{ $key }}" name="{{ $key }}" value="{{ $color }}" data-color="{{ $key }}"><input type="text" maxlength="7" pattern="#[0-9A-Fa-f]{6}" aria-label="{{ __('customization.'.$key) }} · HEX" value="{{ strtoupper($color) }}" data-color-hex="{{ $key }}"></div><p class="customization-field-error" data-field-error="{{ $key }}" hidden></p></div>
            @endforeach
        </div></section>
        <section class="customization-card"><header><span class="customization-section-icon"><x-icon name="help" /></span><div><h2>{{ __('customization.support') }}</h2><p>{{ __('customization.support_help') }}</p></div></header><div class="customization-fields">
            @foreach(['support_email' => ['email',255], 'support_phone' => ['tel',40]] as $key => [$type,$max])
            <div class="customization-field"><label for="custom-{{ $key }}">{{ __('customization.'.$key) }}</label><input class="form-control" id="custom-{{ $key }}" name="{{ $key }}" type="{{ $type }}" maxlength="{{ $max }}" value="{{ $settings[$key] }}"><p class="customization-field-error" data-field-error="{{ $key }}" hidden></p></div>
            @endforeach
        </div></section>
        @endif
        <div id="customization-error" class="customization-error" role="alert" tabindex="-1" hidden></div>
        <p id="customization-restore-status" class="customization-restore-notice" role="status" hidden>{{ __('customization.'.($globalBranding ? 'restore_global_help' : 'restore_fleet_help')) }}</p>
        <footer class="customization-actions"><button type="button" id="customization-restore" class="customization-button customization-restore"><x-icon name="refresh" />{{ __('customization.restore_defaults') }}</button><button type="button" id="customization-cancel" class="customization-button">{{ __('customization.cancel') }}</button><button type="submit" id="customization-save" class="customization-button is-primary"><x-icon name="check" /><span>{{ __('customization.save') }}</span></button></footer>
    </form>
</section>
@push('styles')<link rel="stylesheet" href="{{ asset('css/customization.css') }}?v=customization-2">@endpush
@push('scripts')
<script id="customization-config" type="application/json">{!! \Illuminate\Support\Js::encode(['global' => $globalBranding, 'labels' => trans('customization'), 'colors' => $branding['colors'], 'hasImages' => $branding['custom_images'], 'defaults' => \App\Services\BrandingService::COLORS, 'defaultValues' => $globalBranding ? array_diff_key(\App\Services\BrandingService::DEFAULTS, array_flip(['logo_path', 'internal_logo_path', 'favicon_path'])) : [], 'images' => ['logo' => $branding['logo'], 'internal_logo' => $branding['internal_logo'], 'favicon' => $branding['favicon'] ?? asset('apple-touch-icon.png')], 'fallback' => ['logo' => app(\App\Services\BrandingService::class)->defaultLogo(), 'internal_logo' => app(\App\Services\BrandingService::class)->defaultLogo(true), 'favicon' => asset('apple-touch-icon.png')], 'sidebarLogo' => $branding['sidebar_logo']]) !!}</script>
<script src="{{ asset('js/customization.js') }}?v=customization-2" defer></script>
@endpush
@endif
