<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SamplingPoint extends Model
{
    protected $fillable = ['operational_unit_id', 'stable_key', 'name', 'description', 'equipment_id', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function operationalUnit(): BelongsTo
    {
        return $this->belongsTo(OperationalUnit::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }
}
