<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LaboratoryResult extends Model
{
    protected $fillable = ['laboratory_collection_id', 'laboratory_parameter_id', 'result_value', 'unit', 'parameter_snapshot', 'created_by'];

    protected function casts(): array
    {
        return ['result_value' => 'decimal:6', 'parameter_snapshot' => 'array'];
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(LaboratoryCollection::class, 'laboratory_collection_id');
    }

    public function parameter(): BelongsTo
    {
        return $this->belongsTo(LaboratoryParameter::class, 'laboratory_parameter_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(LaboratoryResultCorrection::class);
    }

    public function effectiveValue(): float
    {
        return (float) ($this->corrections()->latest('corrected_at')->value('corrected_value') ?? $this->result_value);
    }
}
