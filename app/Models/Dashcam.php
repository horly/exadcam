<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Dashcam extends Model
{
    protected $fillable = ['vehicle_id', 'model', 'transport', 'protocol_version', 'name', 'imei', 'terminal_id_2013', 'terminal_id_2019', 'video_terminal_id', 'enabled', 'channels', 'frame_rate', 'gps_timezone_minutes', 'normalize_video_timestamps'];

    protected $hidden = ['auth_token'];

    protected function casts(): array
    {
        return ['auth_token' => 'encrypted', 'enabled' => 'boolean', 'normalize_video_timestamps' => 'boolean', 'gps_timezone_minutes' => 'integer', 'last_seen_at' => 'datetime', 'position_at' => 'datetime', 'vehicle_assigned_at' => 'datetime'];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    protected static function booted(): void
    {
        static::saving(function (self $dashcam): void {
            if ($dashcam->isDirty('vehicle_id')) {
                $dashcam->vehicle_assigned_at = $dashcam->vehicle_id ? now() : null;
            }
        });
        static::creating(function (self $dashcam): void {
            $dashcam->auth_token = Str::random(32);
            $dashcam->terminal_id_2013 ??= substr($dashcam->imei, -12);
            $dashcam->terminal_id_2019 ??= str_pad($dashcam->imei, 20, '0', STR_PAD_LEFT);
            $dashcam->video_terminal_id ??= substr($dashcam->imei, -12);
        });
    }
}
