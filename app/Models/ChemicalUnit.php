<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChemicalUnit extends Model
{
    protected $fillable = ['code', 'name', 'symbol', 'decimal_places', 'is_closed_package', 'equivalent_quantity', 'equivalent_unit_id', 'is_active'];

    protected function casts(): array
    {
        return ['decimal_places' => 'integer', 'is_closed_package' => 'boolean', 'equivalent_quantity' => 'decimal:4', 'is_active' => 'boolean'];
    }

    public function equivalentUnit(): BelongsTo
    {
        return $this->belongsTo(self::class, 'equivalent_unit_id');
    }
}
