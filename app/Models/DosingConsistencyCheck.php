<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DosingConsistencyCheck extends Model
{
    protected $fillable = ['dosing_rule_id', 'reading_section_id', 'control_point_id', 'suspected_point_id', 'minimum_value', 'maximum_value', 'average_value', 'spread_value', 'configured_max_spread', 'is_divergent', 'values_snapshot', 'values_hash', 'evaluated_at', 'evaluated_by', 'confirmed_at', 'confirmed_by'];

    protected function casts(): array
    {
        return ['minimum_value' => 'decimal:4', 'maximum_value' => 'decimal:4', 'average_value' => 'decimal:4', 'spread_value' => 'decimal:4', 'configured_max_spread' => 'decimal:4', 'is_divergent' => 'boolean', 'values_snapshot' => 'array', 'evaluated_at' => 'datetime', 'confirmed_at' => 'datetime'];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(DosingRule::class, 'dosing_rule_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(ReadingSection::class, 'reading_section_id');
    }

    public function controlPoint(): BelongsTo
    {
        return $this->belongsTo(ParameterRulePoint::class, 'control_point_id');
    }

    public function suspectedPoint(): BelongsTo
    {
        return $this->belongsTo(ParameterRulePoint::class, 'suspected_point_id');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }
}
