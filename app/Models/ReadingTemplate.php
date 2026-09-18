<?php

namespace App\Models;

use Database\Factories\ReadingTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReadingTemplate extends Model
{
    /** @use HasFactory<ReadingTemplateFactory> */
    use HasFactory;

    protected $fillable = ['stable_key', 'name', 'description', 'is_active', 'is_default'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_default' => 'boolean'];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ReadingTemplateVersion::class);
    }
}
