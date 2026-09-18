<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChemicalOpenPackage extends Model
{
    protected $fillable = ['chemical_product_id', 'source_location_id', 'content_unit_id', 'usage_location', 'initial_content', 'remaining_content', 'status', 'opened_by', 'opened_at', 'last_weighed_by', 'last_weighed_at', 'observation', 'issue_movement_id', 'configuration_snapshot'];

    protected function casts(): array
    {
        return ['initial_content' => 'decimal:4', 'remaining_content' => 'decimal:4', 'opened_at' => 'datetime', 'last_weighed_at' => 'datetime', 'configuration_snapshot' => 'array'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ChemicalProduct::class, 'chemical_product_id');
    }

    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(ChemicalStorageLocation::class, 'source_location_id');
    }

    public function contentUnit(): BelongsTo
    {
        return $this->belongsTo(ChemicalUnit::class, 'content_unit_id');
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function lastWeigher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_weighed_by');
    }

    public function issueMovement(): BelongsTo
    {
        return $this->belongsTo(ChemicalStockMovement::class, 'issue_movement_id');
    }

    public function weighings(): HasMany
    {
        return $this->hasMany(ChemicalOpenPackageWeighing::class);
    }
}
