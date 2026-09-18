<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChemicalProductPrice extends Model
{
    protected $fillable = ['operational_unit_id', 'chemical_product_id', 'price', 'unit_id', 'currency', 'effective_from', 'effective_until', 'created_by', 'configuration_snapshot'];

    protected function casts(): array
    {
        return ['price' => 'decimal:6', 'effective_from' => 'datetime', 'effective_until' => 'datetime', 'configuration_snapshot' => 'array'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ChemicalProduct::class, 'chemical_product_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ChemicalUnit::class, 'unit_id');
    }

    public function operationalUnit(): BelongsTo
    {
        return $this->belongsTo(OperationalUnit::class);
    }
}
