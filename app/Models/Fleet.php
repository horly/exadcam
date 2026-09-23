<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fleet extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'subscription_id',
        'name',
        'code',
        'description',
        'status',
    ];

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('permission')
            ->withTimestamps();
    }

    public function managers(): BelongsToMany
    {
        return $this->users()->where('fleet_user.permission', 'manager');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->isActive()) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSuperadmin()) {
            return $query;
        }

        return $query->whereKey($user->fleet_id ?? 0);
    }
}
