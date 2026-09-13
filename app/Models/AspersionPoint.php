<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AspersionPoint extends Model
{
    protected $fillable = ['name', 'location', 'public_token', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function aspersions(): HasMany
    {
        return $this->hasMany(Aspersion::class);
    }
}
