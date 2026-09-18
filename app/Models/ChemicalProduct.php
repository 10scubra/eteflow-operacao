<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChemicalProduct extends Model
{
    public const HANDLING_BULK = 'BULK';

    public const HANDLING_WEIGHABLE_PACKAGE = 'WEIGHABLE_PACKAGE';

    public const HANDLING_SEALED_PACKAGE = 'SEALED_PACKAGE';

    protected $fillable = ['stable_key', 'name', 'description', 'unit_id', 'decimal_places', 'handling_mode', 'package_content_quantity', 'package_content_unit_id', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['decimal_places' => 'integer', 'package_content_quantity' => 'decimal:4', 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ChemicalUnit::class, 'unit_id');
    }

    public function packageContentUnit(): BelongsTo
    {
        return $this->belongsTo(ChemicalUnit::class, 'package_content_unit_id');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(ChemicalStorageLocation::class)->orderBy('sort_order');
    }

    public function openPackages(): HasMany
    {
        return $this->hasMany(ChemicalOpenPackage::class);
    }
}
