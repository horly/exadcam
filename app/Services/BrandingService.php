<?php

namespace App\Services;

use App\Models\ApplicationSetting;
use App\Models\Fleet;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class BrandingService
{
    public const COLORS = [
        'primary_color' => '#203d65', 'secondary_color' => '#7799bd', 'button_color' => '#203d65',
        'avatar_color' => '#315b9e', 'accent_color' => '#5686bd',
        'sidebar_start_color' => '#182e49', 'sidebar_end_color' => '#12243b',
    ];

    public const DEFAULTS = [
        'app_name' => 'EXADCAM', 'short_name' => 'EXADCAM', 'website_url' => null,
        'support_email' => null, 'support_phone' => null, 'map_type' => 'roadmap',
        'logo_path' => null, 'internal_logo_path' => null, 'favicon_path' => null,
        ...self::COLORS,
    ];

    public function settings(): array
    {
        $stored = Schema::hasTable('application_settings') ? ApplicationSetting::find(1)?->values ?? [] : [];
        $settings = array_replace(self::DEFAULTS, array_intersect_key($stored, self::DEFAULTS));
        foreach (self::COLORS as $name => $fallback) {
            if (! is_string($settings[$name]) || ! preg_match('/^#[0-9a-f]{6}$/iD', $settings[$name])) {
                $settings[$name] = $fallback;
            }
        }

        return $settings;
    }

    public function canManage(?User $user): bool
    {
        return $user?->isActive() && ($user->isSuperadmin() || ($user->isAdmin() && Fleet::whereKey($user->fleet_id)->where('status', 'active')->exists()));
    }

    public function defaultLogo(bool $dark = false): string
    {
        return asset('images/brand/exad-logo-'.($dark ? 'light' : 'navy').'.svg');
    }

    public function version(string $path): string
    {
        return substr(hash('sha256', $path), 0, 20);
    }

    public function validPath(?string $path, string $directory): bool
    {
        return is_string($path) && preg_match('#^'.preg_quote($directory, '#').'/[0-9a-f-]{36}\.png$#D', $path) === 1;
    }

    public function context(?User $user = null): array
    {
        $settings = $this->settings();
        $images = [];
        foreach (['logo', 'internal_logo', 'favicon'] as $kind) {
            $path = $settings[$kind.'_path'];
            $images[$kind] = $this->validPath($path, 'branding/global') && Storage::disk('local')->exists($path)
                ? route('branding.global-image', ['kind' => $kind, 'version' => $this->version($path)]) : null;
        }
        $logo = $images['logo'] ?? $this->defaultLogo();
        $internal = $images['internal_logo'] ?? $images['logo'] ?? $this->defaultLogo(true);
        $fleet = $user?->isActive() && ! $user->isSuperadmin() ? Fleet::whereKey($user->fleet_id)->where('status', 'active')->first() : null;
        $fleetLogo = $fleet && $this->validPath($fleet->logo_path, 'branding/fleets/'.$fleet->id)
            && Storage::disk('local')->exists($fleet->logo_path)
            ? route('branding.fleet-image', ['version' => $this->version($fleet->logo_path)]) : null;

        return [
            'settings' => array_diff_key($settings, array_flip(['logo_path', 'internal_logo_path', 'favicon_path'])),
            'logo' => $logo, 'internal_logo' => $internal, 'sidebar_logo' => $fleetLogo ?? $internal,
            'favicon' => $images['favicon'], 'custom_images' => array_map(fn ($url) => $url !== null, $images),
            'fleet_logo' => $fleetLogo, 'fleet_name' => $fleet?->name,
            'can_manage' => $this->canManage($user), 'global' => (bool) $user?->isSuperadmin(),
            'colors' => array_intersect_key($settings, self::COLORS),
        ];
    }

    public static function foreground(string $hex): string
    {
        $rgb = array_map(fn ($v) => hexdec($v) / 255, str_split(substr($hex, 1), 2));
        $rgb = array_map(fn ($v) => $v <= 0.04045 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4, $rgb);
        $luminance = 0.2126 * $rgb[0] + 0.7152 * $rgb[1] + 0.0722 * $rgb[2];

        return $luminance > 0.179 ? '#102033' : '#ffffff';
    }
}
