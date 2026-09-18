<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OperationalUnit extends Model
{
    protected $fillable = ['stable_key', 'name', 'timezone', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('is_default')->withTimestamps();
    }

    public function pages(): HasMany
    {
        return $this->hasMany(DashboardPage::class);
    }

    public function indicators(): HasMany
    {
        return $this->hasMany(Indicator::class);
    }
}
