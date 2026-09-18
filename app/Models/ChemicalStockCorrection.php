<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChemicalStockCorrection extends Model
{
    protected $fillable = ['record_type', 'record_id', 'corrected_quantity', 'reason', 'corrected_by', 'corrected_at', 'idempotency_key', 'original_snapshot', 'correction_snapshot'];

    protected function casts(): array
    {
        return ['corrected_quantity' => 'decimal:4', 'corrected_at' => 'datetime', 'original_snapshot' => 'array', 'correction_snapshot' => 'array'];
    }
}
