<?php

namespace App\Services;

use App\Models\Dashcam;
use App\Models\User;
use App\Support\FleetAccess;
use Illuminate\Support\Facades\Cache;

class AudioAccess
{
    public static function snapshot(User $user, Dashcam $camera, string $mode): array
    {
        $camera->load('vehicle');

        return [
            'user_id' => $user->id, 'device_id' => $camera->id, 'mode' => $mode,
            'vehicle_id' => $camera->vehicle_id, 'vehicle_assigned_at' => $camera->vehicle_assigned_at?->toISOString(),
            'fleet_id' => $camera->vehicle?->fleet_id, 'fleet_assigned_at' => $camera->vehicle?->fleet_assigned_at?->toISOString(),
        ];
    }

    public static function permitted(User $user, Dashcam $camera, string $mode): bool
    {
        return $camera->enabled && FleetAccess::camera($user, $camera, User::PERMISSION_VIDEO_VIEW)
            && ($mode !== 'talk' || FleetAccess::allows($user, User::PERMISSION_AUDIO_TALK));
    }

    public static function valid(string $grant, int $deviceId): bool
    {
        $record = Cache::get('audio-grant:'.$grant);
        if (! is_array($record) || $record['device_id'] !== $deviceId) {
            return false;
        }
        $user = User::find($record['user_id']);
        $camera = Dashcam::find($deviceId);

        return $user && $camera && self::permitted($user, $camera, $record['mode'])
            && self::snapshot($user, $camera, $record['mode']) === $record;
    }
}
