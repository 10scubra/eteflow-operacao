<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationalAction extends Model
{
    protected $fillable = ['shift_id', 'title', 'description', 'priority', 'equipment', 'due_at', 'status', 'requires_before_photo', 'requires_after_photo', 'created_by', 'started_by', 'completed_by', 'started_at', 'completed_at', 'observation', 'measured_value', 'measured_unit'];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime', 'requires_before_photo' => 'boolean', 'requires_after_photo' => 'boolean', 'measured_value' => 'decimal:4'];
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function checklistItems()
    {
        return $this->hasMany(ActionChecklistItem::class);
    }

    public function evidences()
    {
        return $this->hasMany(ActionEvidence::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function startedBy()
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
