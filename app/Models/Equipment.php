<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Equipment extends Model
{
    protected $table = 'equipment';

    protected $fillable = ['code', 'name', 'category', 'location', 'is_active', 'effective_from', 'effective_until'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
        ];
    }

    public function statuses(): HasMany
    {
        return $this->hasMany(EquipmentStatus::class);
    }

    public function parameterRules(): HasMany
    {
        return $this->hasMany(ParameterRule::class);
    }

    public function scopeEffectiveAt(Builder $query, mixed $moment): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', $moment))
            ->where(fn (Builder $query) => $query->whereNull('effective_until')->orWhere('effective_until', '>', $moment));
    }
}
