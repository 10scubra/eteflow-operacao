<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReadingSectionDefinition extends Model
{
    protected $fillable = [
        'reading_template_version_id',
        'section_key',
        'label',
        'description',
        'sort_order',
        'version',
        'is_active',
        'effective_from',
        'effective_until',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'version' => 'integer',
            'is_active' => 'boolean',
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
        ];
    }

    public function sections(): HasMany
    {
        return $this->hasMany(ReadingSection::class);
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(ReadingTemplateVersion::class, 'reading_template_version_id');
    }

    public function parameterRules(): HasMany
    {
        return $this->hasMany(ParameterRule::class);
    }

    public function scopeEffectiveAt(Builder $query, mixed $moment): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', $moment))
            ->where(fn (Builder $query) => $query->whereNull('effective_until')->orWhere('effective_until', '>', $moment));
    }
}
