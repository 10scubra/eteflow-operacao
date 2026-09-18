<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DosingRule extends Model
{
    protected $fillable = ['stable_key', 'version', 'name', 'description', 'is_active', 'parameter_rule_id', 'parameter_rule_point_id', 'product_name', 'equipment_id', 'start_operator', 'start_value', 'stop_operator', 'stop_value', 'consistency_max_spread', 'recurrence_window', 'recurrence_count', 'reminder_after_minutes', 'reminder_interval_minutes', 'recheck_after_minutes', 'effective_from', 'effective_until', 'created_by'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'start_value' => 'decimal:4', 'stop_value' => 'decimal:4', 'consistency_max_spread' => 'decimal:4', 'effective_from' => 'datetime', 'effective_until' => 'datetime'];
    }

    public function parameterRule(): BelongsTo
    {
        return $this->belongsTo(ParameterRule::class);
    }

    public function point(): BelongsTo
    {
        return $this->belongsTo(ParameterRulePoint::class, 'parameter_rule_point_id');
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(DosingAlert::class);
    }

    public function cycles(): HasMany
    {
        return $this->hasMany(DosingCycle::class);
    }

    public function consistencyChecks(): HasMany
    {
        return $this->hasMany(DosingConsistencyCheck::class);
    }
}
