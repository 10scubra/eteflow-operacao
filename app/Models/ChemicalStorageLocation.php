<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChemicalStorageLocation extends Model
{
    protected $fillable = ['chemical_product_id', 'unit_id', 'stable_key', 'name', 'description', 'capacity', 'low_threshold_type', 'low_threshold_value', 'critical_threshold_type', 'critical_threshold_value', 'participates_in_shift_count', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['capacity' => 'decimal:4', 'low_threshold_value' => 'decimal:4', 'critical_threshold_value' => 'decimal:4', 'participates_in_shift_count' => 'boolean', 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ChemicalProduct::class, 'chemical_product_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ChemicalUnit::class, 'unit_id');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(ChemicalStockBalanceSnapshot::class);
    }
}
