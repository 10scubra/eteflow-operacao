<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChemicalStockCountItem extends Model
{
    protected $fillable = ['chemical_stock_count_id', 'chemical_product_id', 'chemical_storage_location_id', 'unit_id', 'quantity', 'expected_quantity_snapshot', 'difference_snapshot', 'configuration_snapshot'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'expected_quantity_snapshot' => 'decimal:4', 'difference_snapshot' => 'decimal:4', 'configuration_snapshot' => 'array'];
    }

    public function count(): BelongsTo
    {
        return $this->belongsTo(ChemicalStockCount::class, 'chemical_stock_count_id');
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
