<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DashboardPage extends Model
{
    protected $fillable = ['operational_unit_id', 'stable_key', 'name', 'slug', 'description', 'sort_order', 'is_active', 'is_shareable'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_active' => 'boolean', 'is_shareable' => 'boolean'];
    }

    public function operationalUnit(): BelongsTo
    {
        return $this->belongsTo(OperationalUnit::class);
    }

    public function indicators(): HasMany
    {
        return $this->hasMany(Indicator::class)->orderBy('sort_order');
    }

    public function shares(): BelongsToMany
    {
        return $this->belongsToMany(PublicDashboardShare::class, 'dashboard_page_public_share');
    }
}
