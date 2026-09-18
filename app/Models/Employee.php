<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends Model
{
    public const STATUSES = ['active', 'away', 'vacation', 'terminated', 'inactive'];

    protected $fillable = [
        'full_name', 'display_name', 'registration_number', 'job_title', 'operational_function',
        'department', 'unit', 'corporate_email', 'personal_email', 'phone', 'hired_at', 'status',
        'photo_path', 'administrative_notes', 'terminated_at', 'termination_reason',
    ];

    protected $hidden = ['personal_email', 'phone', 'administrative_notes', 'termination_reason'];

    protected function casts(): array
    {
        return ['hired_at' => 'date', 'terminated_at' => 'date'];
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function shiftMemberships(): HasMany
    {
        return $this->hasMany(ShiftMember::class);
    }
}
