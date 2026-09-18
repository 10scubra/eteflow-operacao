<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LaboratoryCollection extends Model
{
    protected $fillable = ['operational_unit_id', 'sampling_point_id', 'origin_type', 'collected_at', 'result_received_at', 'external_laboratory', 'document_reference', 'observation', 'created_by'];

    protected function casts(): array
    {
        return ['collected_at' => 'datetime', 'result_received_at' => 'datetime'];
    }

    public function operationalUnit(): BelongsTo
    {
        return $this->belongsTo(OperationalUnit::class);
    }

    public function point(): BelongsTo
    {
        return $this->belongsTo(SamplingPoint::class, 'sampling_point_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function results(): HasMany
    {
        return $this->hasMany(LaboratoryResult::class);
    }
}
