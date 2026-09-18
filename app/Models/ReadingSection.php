<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReadingSection extends Model
{
    protected $fillable = ['reading_round_id', 'reading_section_definition_id', 'section_key', 'label', 'status', 'editing_by', 'editing_started_at', 'started_by', 'completed_by', 'last_edited_by', 'started_at', 'completed_at', 'lock_version'];

    protected function casts(): array
    {
        return ['editing_started_at' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(ReadingRound::class, 'reading_round_id');
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(ReadingSectionDefinition::class, 'reading_section_definition_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(ReadingValue::class);
    }

    public function editingBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'editing_by');
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function lastEditedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_edited_by');
    }
}
