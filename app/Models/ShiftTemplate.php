<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShiftTemplate extends Model
{
    protected $fillable = [
        'code',
        'name',
        'starts_at',
        'ends_at',
        'version',
        'is_active',
        'effective_from',
        'effective_until',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'is_active' => 'boolean',
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
        ];
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(ShiftTemplateRound::class)->orderBy('sort_order');
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function scopeEffectiveAt(Builder $query, mixed $moment): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', $moment))
            ->where(fn (Builder $query) => $query->whereNull('effective_until')->orWhere('effective_until', '>', $moment));
    }
}
