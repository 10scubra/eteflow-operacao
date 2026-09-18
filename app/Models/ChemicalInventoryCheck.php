<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChemicalInventoryCheck extends Model
{
    protected $fillable = ['chemical_product_id', 'chemical_storage_location_id', 'unit_id', 'shift_id', 'expected_quantity', 'physical_quantity', 'difference', 'reason', 'observation', 'checked_by', 'checked_at', 'idempotency_key', 'adjustment_movement_id', 'configuration_snapshot'];

    protected function casts(): array
    {
        return ['expected_quantity' => 'decimal:4', 'physical_quantity' => 'decimal:4', 'difference' => 'decimal:4', 'checked_at' => 'datetime', 'configuration_snapshot' => 'array'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ChemicalProduct::class, 'chemical_product_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(ChemicalStorageLocation::class, 'chemical_storage_location_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ChemicalUnit::class, 'unit_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
