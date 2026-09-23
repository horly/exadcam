<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    protected $fillable = ['fleet_id', 'department_id', 'created_by', 'name', 'registration_number', 'brand', 'model'];

    protected function casts(): array
    {
        return ['fleet_assigned_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $vehicle): void {
            if ($vehicle->isDirty('fleet_id')) {
                $vehicle->fleet_assigned_at = now();
            }
        });
    }

    public function fleet(): BelongsTo
    {
        return $this->belongsTo(Fleet::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function dashcams(): HasMany
    {
        return $this->hasMany(Dashcam::class);
    }
}
