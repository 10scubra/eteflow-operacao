<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DosingAlert extends Model
{
    protected $fillable = ['dosing_rule_id', 'trigger_reading_value_id', 'resolved_reading_value_id', 'status', 'trigger_value', 'rule_snapshot', 'detected_at', 'last_reminded_at', 'actioned_at', 'actioned_by', 'impediment_code', 'justification', 'resolved_at'];

    protected function casts(): array
    {
        return ['trigger_value' => 'decimal:4', 'rule_snapshot' => 'array', 'detected_at' => 'datetime', 'last_reminded_at' => 'datetime', 'actioned_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(DosingRule::class, 'dosing_rule_id');
    }

    public function triggerValue(): BelongsTo
    {
        return $this->belongsTo(ReadingValue::class, 'trigger_reading_value_id');
    }

    public function resolvedValue(): BelongsTo
    {
        return $this->belongsTo(ReadingValue::class, 'resolved_reading_value_id');
    }

    public function actionedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actioned_by');
    }

    public function cycles(): HasMany
    {
        return $this->hasMany(DosingCycle::class);
    }
}
