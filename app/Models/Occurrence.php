<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Occurrence extends Model
{
    protected $fillable = ['shift_id', 'code', 'title', 'description', 'location', 'priority', 'status', 'photo_path', 'reported_by', 'reported_at', 'operational_action_id'];

    protected function casts(): array
    {
        return ['reported_at' => 'datetime'];
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function action()
    {
        return $this->belongsTo(OperationalAction::class, 'operational_action_id');
    }
}
