<?php

namespace App\Models;

use Database\Factories\ParameterRulePointFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParameterRulePoint extends Model
{
    /** @use HasFactory<ParameterRulePointFactory> */
    use HasFactory;

    protected $fillable = ['parameter_rule_id', 'stable_key', 'label', 'sort_order', 'is_active', 'instruction'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_active' => 'boolean'];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(ParameterRule::class, 'parameter_rule_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(ReadingValue::class, 'parameter_rule_point_id');
    }
}
