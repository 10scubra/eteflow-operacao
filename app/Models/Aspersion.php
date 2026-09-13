<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Aspersion extends Model
{
    protected $fillable = [
        'shift_id',
        'aspersion_point_id',
        'area',
        'line',
        'status',
        'source',
        'initial_reading',
        'initial_flow_rate',
        'initial_active_cannons',
        'final_reading',
        'final_flow_rate',
        'final_active_cannons',
        'total_consumption',
        'notes',
        'started_by',
        'ended_by',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'initial_reading' => 'decimal:3',
            'initial_flow_rate' => 'decimal:3',
            'final_reading' => 'decimal:3',
            'final_flow_rate' => 'decimal:3',
            'total_consumption' => 'decimal:3',
            'initial_active_cannons' => 'integer',
            'final_active_cannons' => 'integer',
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function point(): BelongsTo
    {
        return $this->belongsTo(AspersionPoint::class, 'aspersion_point_id');
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function endedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ended_by');
    }
}
