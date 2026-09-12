<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActionChecklistItem extends Model
{
    protected $fillable = ['operational_action_id', 'label', 'is_completed', 'completed_by', 'completed_at'];

    protected function casts(): array
    {
        return ['is_completed' => 'boolean', 'completed_at' => 'datetime'];
    }

    public function action()
    {
        return $this->belongsTo(OperationalAction::class, 'operational_action_id');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
