<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipmentStatus extends Model
{
    protected $fillable = ['shift_id', 'name', 'location', 'status', 'reading', 'unit', 'notes', 'reported_by', 'reported_at'];

    protected function casts(): array
    {
        return ['reported_at' => 'datetime', 'reading' => 'decimal:3'];
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
