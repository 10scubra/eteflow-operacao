<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReadingRound extends Model
{
    protected $fillable = ['shift_id', 'reading_template_version_id', 'scheduled_at', 'status'];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime'];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(ReadingSection::class);
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(ReadingTemplateVersion::class, 'reading_template_version_id');
    }
}
