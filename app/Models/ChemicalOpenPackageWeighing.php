<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChemicalOpenPackageWeighing extends Model
{
    protected $fillable = ['chemical_open_package_id', 'previous_remaining', 'remaining', 'consumed', 'weighed_by', 'weighed_at', 'observation'];

    protected function casts(): array
    {
        return ['previous_remaining' => 'decimal:4', 'remaining' => 'decimal:4', 'consumed' => 'decimal:4', 'weighed_at' => 'datetime'];
    }

    public function openPackage(): BelongsTo
    {
        return $this->belongsTo(ChemicalOpenPackage::class);
    }

    public function weigher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'weighed_by');
    }
}
