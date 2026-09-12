<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadingValueRevision extends Model
{
    protected $fillable = ['reading_value_id', 'previous_value', 'new_value', 'changed_by', 'changed_at', 'reason'];

    protected function casts(): array
    {
        return ['previous_value' => 'array', 'new_value' => 'array', 'changed_at' => 'datetime'];
    }

    public function value(): BelongsTo
    {
        return $this->belongsTo(ReadingValue::class, 'reading_value_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
