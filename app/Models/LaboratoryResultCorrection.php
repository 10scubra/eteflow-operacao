<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaboratoryResultCorrection extends Model
{
    protected $fillable = ['laboratory_result_id', 'original_value', 'corrected_value', 'reason', 'corrected_by', 'corrected_at'];

    protected function casts(): array
    {
        return ['original_value' => 'decimal:6', 'corrected_value' => 'decimal:6', 'corrected_at' => 'datetime'];
    }

    public function result(): BelongsTo
    {
        return $this->belongsTo(LaboratoryResult::class, 'laboratory_result_id');
    }

    public function corrector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }
}
