<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShiftHandover extends Model
{
    protected $fillable = ['shift_id', 'summary', 'note', 'confirmed', 'confirmed_by', 'closed_at'];

    protected function casts(): array
    {
        return ['summary' => 'array', 'confirmed' => 'boolean', 'closed_at' => 'datetime'];
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function confirmedBy()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
