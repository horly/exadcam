<?php

namespace App\Support;

use App\Models\Dashcam;
use App\Models\Fleet;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class FleetAccess
{
    public static function allows(User $user, string $permission): bool
    {
        return $user->hasClientPermission($permission)
            && ($user->isSuperadmin() || $user->fleet?->status === 'active');
    }

    public static function registry(User $user, string $kind, bool $write = false): bool
    {
        if ($kind === 'fleets') {
            return $write ? $user->isActive() && $user->isSuperadmin() : self::browse($user);
        }

        return self::allows($user, match ($kind) {
            'vehicles' => User::PERMISSION_VEHICLES_MANAGE,
            'departments' => User::PERMISSION_DEPARTMENTS_MANAGE,
        });
    }

    public static function createRegistry(User $user, string $kind): bool
    {
        return self::registry($user, $kind, true)
            && ($kind !== 'vehicles' || $user->isSuperadmin());
    }

    public static function browse(User $user): bool
    {
        foreach ([User::PERMISSION_VEHICLES_MANAGE, User::PERMISSION_DEPARTMENTS_MANAGE,
            User::PERMISSION_DASHCAMS_MANAGE, User::PERMISSION_VIDEO_VIEW, User::PERMISSION_MAP_VIEW] as $permission) {
            if (self::allows($user, $permission)) {
                return true;
            }
        }

        return false;
    }

    public static function dashcams(User $user): bool
    {
        return self::allows($user, User::PERMISSION_DASHCAMS_MANAGE)
            || self::allows($user, User::PERMISSION_VIDEO_VIEW);
    }

    public static function fleets(User $user): Builder
    {
        return Fleet::visibleTo($user)->when(! $user->isSuperadmin(), fn ($query) => $query->where('status', 'active'));
    }

    public static function scopeRegistry(Builder $query, User $user, string $kind): Builder
    {
        return $query->whereIn($kind === 'fleets' ? 'id' : 'fleet_id', self::fleets($user)->select('id'));
    }

    public static function scopeDashcams(Builder $query, User $user): Builder
    {
        if ($user->isActive() && $user->isSuperadmin()) {
            return $query;
        }

        return $query->whereHas('vehicle', fn ($vehicle) => $vehicle->whereIn('fleet_id', self::fleets($user)->select('id')));
    }

    public static function camera(User $user, Dashcam $camera, string $permission): bool
    {
        return self::allows($user, $permission)
            && self::scopeDashcams(Dashcam::query(), $user)->whereKey($camera->id)->exists();
    }
}
