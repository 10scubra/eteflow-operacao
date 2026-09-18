<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChemicalStockMovement extends Model
{
    protected $fillable = ['chemical_product_id', 'source_location_id', 'destination_location_id', 'unit_id', 'shift_id', 'type', 'direction', 'quantity', 'supplier', 'document', 'reason', 'observation', 'status', 'idempotency_key', 'occurred_at', 'created_by', 'configuration_snapshot'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'occurred_at' => 'datetime', 'configuration_snapshot' => 'array'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ChemicalProduct::class, 'chemical_product_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(ChemicalStorageLocation::class, 'source_location_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(ChemicalStorageLocation::class, 'destination_location_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
