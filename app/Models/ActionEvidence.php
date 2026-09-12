<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActionEvidence extends Model
{
    protected $table = 'action_evidences';

    protected $fillable = ['operational_action_id', 'type', 'original_name', 'path', 'mime_type', 'size', 'caption', 'user_id', 'taken_at'];

    protected function casts(): array
    {
        return ['taken_at' => 'datetime'];
    }

    public function action()
    {
        return $this->belongsTo(OperationalAction::class, 'operational_action_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
