<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPermissionOverride extends Model
{
    protected $fillable = ['user_id', 'permission_id', 'effect', 'effective_from', 'effective_until', 'created_by'];

    protected function casts(): array
    {
        return ['effective_from' => 'datetime', 'effective_until' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeEffectiveAt(Builder $query, mixed $moment): Builder
    {
        return $query
            ->where(fn (Builder $query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', $moment))
            ->where(fn (Builder $query) => $query->whereNull('effective_until')->orWhere('effective_until', '>', $moment));
    }
}
