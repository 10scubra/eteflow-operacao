<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParameterRule extends Model
{
    use HasFactory;

    protected $fillable = ['reading_section_definition_id', 'equipment_id', 'section_key', 'field_key', 'label', 'data_type', 'description', 'unit', 'decimal_places', 'minimum_value', 'maximum_value', 'sort_order', 'condition_operator', 'reference_value', 'options', 'frequency_type', 'frequency_config', 'operational_rule', 'visibility_config', 'is_required', 'is_active', 'version', 'effective_from', 'effective_until'];

    protected function casts(): array
    {
        return ['minimum_value' => 'decimal:4', 'maximum_value' => 'decimal:4', 'reference_value' => 'decimal:6', 'decimal_places' => 'integer', 'sort_order' => 'integer', 'options' => 'array', 'frequency_config' => 'array', 'visibility_config' => 'array', 'is_required' => 'boolean', 'is_active' => 'boolean', 'effective_from' => 'datetime', 'effective_until' => 'datetime'];
    }

    public function sectionDefinition(): BelongsTo
    {
        return $this->belongsTo(ReadingSectionDefinition::class, 'reading_section_definition_id');
    }

    public function points(): HasMany
    {
        return $this->hasMany(ParameterRulePoint::class)->orderBy('sort_order');
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function scopeEffectiveAt(Builder $query, mixed $moment): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('effective_from', '<=', $moment)
            ->where(fn (Builder $query) => $query->whereNull('effective_until')->orWhere('effective_until', '>', $moment));
    }
}
