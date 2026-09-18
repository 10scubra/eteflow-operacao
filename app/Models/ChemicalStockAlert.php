<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChemicalStockAlert extends Model
{
    protected $fillable = ['chemical_product_id', 'chemical_storage_location_id', 'chemical_stock_balance_snapshot_id', 'severity', 'status', 'balance_snapshot', 'threshold_snapshot', 'opened_at', 'resolved_at', 'resolved_by'];

    protected function casts(): array
    {
        return ['balance_snapshot' => 'decimal:4', 'threshold_snapshot' => 'array', 'opened_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ChemicalProduct::class, 'chemical_product_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(ChemicalStorageLocation::class, 'chemical_storage_location_id');
    }
}
