<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChemicalStockCount extends Model
{
    protected $fillable = ['shift_id', 'counted_by', 'origin_context', 'observation', 'counted_at'];

    protected function casts(): array
    {
        return ['counted_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ChemicalStockCountItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
