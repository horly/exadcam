<?php

namespace App\Models;

use App\Enums\UserRole;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    public const PERMISSION_MAP_VIEW = 'map.view';

    public const PERMISSION_REPORTS_GENERATE = 'reports.generate';

    public const PERMISSION_ENGINE_CONTROL = 'engine.control';

    public const PERMISSION_GARAGES_MANAGE = 'garages.manage';

    public const PERMISSION_MAINTENANCE_MANAGE = 'maintenance.manage';

    public const PERMISSION_VEHICLES_MANAGE = 'vehicles.manage';

    public const PERMISSION_DEPARTMENTS_MANAGE = 'departments.manage';

    public const PERMISSION_DASHCAMS_MANAGE = 'dashcams.manage';

    public const PERMISSION_VIDEO_VIEW = 'video.view';

    public const PERMISSION_AUDIO_TALK = 'audio.talk';

    public const CLIENT_PERMISSIONS = [
        self::PERMISSION_VEHICLES_MANAGE,
        self::PERMISSION_DEPARTMENTS_MANAGE,
        self::PERMISSION_DASHCAMS_MANAGE,
        self::PERMISSION_VIDEO_VIEW,
        self::PERMISSION_AUDIO_TALK,
        self::PERMISSION_MAP_VIEW,
        self::PERMISSION_REPORTS_GENERATE,
        self::PERMISSION_ENGINE_CONTROL,
        self::PERMISSION_GARAGES_MANAGE,
        self::PERMISSION_MAINTENANCE_MANAGE,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'subscription_id',
        'fleet_id',
        'created_by',
        'name',
        'email',
        'password',
        'role',
        'status',
        'disabled_at',
        'permissions',
        'phone',
        'address',
        'profile_photo_path',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'disabled_at' => 'datetime',
            'permissions' => 'array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function fleet(): BelongsTo
    {
        return $this->belongsTo(Fleet::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(self::class, 'created_by');
    }

    public function loginHistories(): HasMany
    {
        return $this->hasMany(UserLoginHistory::class)->latest('logged_in_at');
    }

    public function fleets(): BelongsToMany
    {
        return $this->belongsToMany(Fleet::class)
            ->withPivot('permission')
            ->withTimestamps();
    }

    public function isSuperadmin(): bool
    {
        return $this->role === UserRole::Superadmin;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isSimpleUser(): bool
    {
        return $this->role === UserRole::User;
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->disabled_at === null;
    }

    public function hasClientPermission(string $permission): bool
    {
        return $this->isActive()
            && in_array($permission, self::CLIENT_PERMISSIONS, true)
            && ($this->isSuperadmin()
                || $this->isAdmin()
                || in_array($permission, $this->permissions ?? [], true));
    }

    public function profilePhotoUrl(): ?string
    {
        return $this->profile_photo_path
            ? Storage::disk('public')->url($this->profile_photo_path)
            : null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')->whereNull('disabled_at');
    }

    public function scopeSuperadmins(Builder $query): Builder
    {
        return $query->where('role', UserRole::Superadmin->value);
    }

    public function scopeAdmins(Builder $query): Builder
    {
        return $query->where('role', UserRole::Admin->value);
    }

    public function scopeSimpleUsers(Builder $query): Builder
    {
        return $query->where('role', UserRole::User->value);
    }
}
