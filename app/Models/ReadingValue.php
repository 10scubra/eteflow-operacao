<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReadingValue extends Model
{
    protected $fillable = ['reading_section_id', 'parameter_rule_id', 'field_key', 'value_text', 'value_numeric', 'unit', 'minimum_at_time', 'maximum_at_time', 'is_out_of_range', 'recorded_by'];

    protected function casts(): array
    {
        return ['value_numeric' => 'decimal:4', 'minimum_at_time' => 'decimal:4', 'maximum_at_time' => 'decimal:4', 'is_out_of_range' => 'boolean'];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(ReadingSection::class, 'reading_section_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(ParameterRule::class, 'parameter_rule_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ReadingValueRevision::class);
    }
}
