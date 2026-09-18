<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaboratoryParameter extends Model
{
    protected $fillable = ['operational_unit_id', 'stable_key', 'name', 'default_unit', 'description', 'decimal_places', 'is_active', 'sort_order', 'configuration'];

    protected function casts(): array
    {
        return ['decimal_places' => 'integer', 'is_active' => 'boolean', 'sort_order' => 'integer', 'configuration' => 'array'];
    }

    public function operationalUnit(): BelongsTo
    {
        return $this->belongsTo(OperationalUnit::class);
    }
}
