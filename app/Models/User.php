<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'employee_id', 'role_id', 'name', 'username', 'email', 'password', 'role', 'is_active',
        'blocked_at', 'last_login_at', 'must_change_password',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime', 'password' => 'hashed', 'is_active' => 'boolean',
            'blocked_at' => 'datetime', 'last_login_at' => 'datetime', 'must_change_password' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function roleProfile(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function permissionOverrides(): HasMany
    {
        return $this->hasMany(UserPermissionOverride::class);
    }

    public function shiftMemberships(): HasMany
    {
        return $this->hasMany(ShiftMember::class);
    }

    public function operationalUnits(): BelongsToMany
    {
        return $this->belongsToMany(OperationalUnit::class)->withPivot('is_default')->withTimestamps();
    }

    public function shifts(): BelongsToMany
    {
        return $this->belongsToMany(Shift::class, 'shift_members')
            ->wherePivotNull('left_at')
            ->withPivot(['joined_at', 'left_at'])
            ->withTimestamps();
    }
}
