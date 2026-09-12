<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    protected $fillable = ['shift_date', 'starts_at', 'ends_at', 'status', 'notes', 'closed_by', 'closed_at'];

    protected function casts(): array
    {
        return ['shift_date' => 'date', 'closed_at' => 'datetime'];
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'shift_members')
            ->wherePivotNull('left_at')
            ->withPivot(['joined_at', 'left_at'])
            ->withTimestamps();
    }

    public function allMembers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'shift_members')
            ->withPivot(['joined_at', 'left_at'])
            ->withTimestamps();
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(ReadingRound::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
