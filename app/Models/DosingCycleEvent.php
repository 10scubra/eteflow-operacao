<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DosingCycleEvent extends Model
{
    protected $fillable = ['dosing_cycle_id', 'equipment_id', 'type', 'percentage', 'ph_value', 'justification', 'metadata', 'event_occurred_at', 'created_by'];

    protected function casts(): array
    {
        return ['percentage' => 'decimal:2', 'ph_value' => 'decimal:4', 'metadata' => 'array', 'event_occurred_at' => 'datetime'];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(DosingCycle::class, 'dosing_cycle_id');
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
