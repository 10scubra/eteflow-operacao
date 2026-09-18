<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChemicalStockBalanceSnapshot extends Model
{
    protected $fillable = ['chemical_product_id', 'chemical_storage_location_id', 'reference_count_item_id', 'trigger_type', 'trigger_id', 'balance', 'percentage', 'status', 'configuration_snapshot', 'calculated_at'];

    protected function casts(): array
    {
        return ['balance' => 'decimal:4', 'percentage' => 'decimal:4', 'configuration_snapshot' => 'array', 'calculated_at' => 'datetime'];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(ChemicalStorageLocation::class, 'chemical_storage_location_id');
    }
}
