<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DosingCycle extends Model
{
    protected $fillable = ['dosing_rule_id', 'dosing_alert_id', 'equipment_id', 'active_lock_key', 'trigger_reading_value_id', 'product_name', 'process_name', 'started_at', 'started_by', 'initial_value', 'initial_percentage', 'status', 'ended_at', 'ended_by', 'final_value', 'end_reason', 'notes'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime', 'initial_value' => 'decimal:4', 'initial_percentage' => 'decimal:2', 'final_value' => 'decimal:4'];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(DosingRule::class, 'dosing_rule_id');
    }

    public function alert(): BelongsTo
    {
        return $this->belongsTo(DosingAlert::class, 'dosing_alert_id');
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function endedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ended_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(DosingCycleEvent::class);
    }
}
