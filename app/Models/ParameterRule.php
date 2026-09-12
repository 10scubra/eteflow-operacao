<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParameterRule extends Model
{
    protected $fillable = ['section_key', 'field_key', 'label', 'unit', 'minimum_value', 'maximum_value', 'is_required', 'is_active', 'version', 'effective_from', 'effective_until'];

    protected function casts(): array
    {
        return ['minimum_value' => 'decimal:4', 'maximum_value' => 'decimal:4', 'is_required' => 'boolean', 'is_active' => 'boolean', 'effective_from' => 'datetime', 'effective_until' => 'datetime'];
    }
}
