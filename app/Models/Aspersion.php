<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Aspersion extends Model
{
    protected $fillable = ['shift_id', 'area', 'line', 'status', 'initial_reading', 'final_reading', 'total_consumption', 'notes', 'started_by', 'ended_by', 'started_at', 'ended_at'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime', 'initial_reading' => 'decimal:3', 'final_reading' => 'decimal:3', 'total_consumption' => 'decimal:3'];
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function startedBy()
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function endedBy()
    {
        return $this->belongsTo(User::class, 'ended_by');
    }
}
