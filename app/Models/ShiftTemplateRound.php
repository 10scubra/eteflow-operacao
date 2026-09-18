<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftTemplateRound extends Model
{
    protected $fillable = ['shift_template_id', 'offset_minutes', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['offset_minutes' => 'integer', 'sort_order' => 'integer', 'is_active' => 'boolean'];
    }

    public function shiftTemplate(): BelongsTo
    {
        return $this->belongsTo(ShiftTemplate::class);
    }
}
