<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PublicDashboardShare extends Model
{
    protected $fillable = ['operational_unit_id', 'name', 'token_hash', 'token_last_four', 'expires_at', 'is_active', 'allow_export', 'created_by', 'revoked_at', 'revoked_by'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'is_active' => 'boolean', 'allow_export' => 'boolean', 'revoked_at' => 'datetime'];
    }

    public function operationalUnit(): BelongsTo
    {
        return $this->belongsTo(OperationalUnit::class);
    }

    public function pages(): BelongsToMany
    {
        return $this->belongsToMany(DashboardPage::class, 'dashboard_page_public_share');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
