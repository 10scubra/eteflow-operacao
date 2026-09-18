<?php

namespace App\Models;

use Database\Factories\ReadingTemplateVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReadingTemplateVersion extends Model
{
    /** @use HasFactory<ReadingTemplateVersionFactory> */
    use HasFactory;

    public const Draft = 'DRAFT';

    public const Published = 'PUBLISHED';

    public const Archived = 'ARCHIVED';

    protected $fillable = ['reading_template_id', 'version', 'status', 'notes', 'created_by', 'published_by', 'published_at'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'published_at' => 'datetime'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ReadingTemplate::class, 'reading_template_id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(ReadingSectionDefinition::class)->orderBy('sort_order');
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(ReadingRound::class);
    }

    public function isEditable(): bool
    {
        return $this->status === self::Draft;
    }
}
