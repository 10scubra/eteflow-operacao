<?php

namespace App\Services;

use App\Models\OperationalUnit;
use App\Models\PublicDashboardShare;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PublicDashboardService
{
    public function __construct(private AuditService $audit) {}

    public function create(OperationalUnit $unit, User $user, array $data): array
    {
        return DB::transaction(function () use ($unit, $user, $data) {
            $token = Str::random(64);
            $share = PublicDashboardShare::create(['operational_unit_id' => $unit->id, 'name' => $data['name'], 'token_hash' => hash('sha256', $token), 'token_last_four' => substr($token, -4), 'expires_at' => $data['expires_at'] ?? null, 'is_active' => true, 'allow_export' => (bool) ($data['allow_export'] ?? false), 'created_by' => $user->id]);
            $pageIds = $unit->pages()->where('is_active', true)->where('is_shareable', true)->whereIn('id', $data['page_ids'])->pluck('id');
            $share->pages()->sync($pageIds);
            $this->audit->record($share, 'public_share.created', $user, [], ['unit_id' => $unit->id, 'name' => $share->name, 'page_ids' => $pageIds->all(), 'expires_at' => $share->expires_at?->toISOString(), 'allow_export' => $share->allow_export, 'token_last_four' => $share->token_last_four]);

            return ['share' => $share, 'token' => $token];
        });
    }

    public function resolve(string $token): PublicDashboardShare
    {
        $share = PublicDashboardShare::where('token_hash', hash('sha256', $token))->with(['operationalUnit', 'pages.indicators'])->firstOrFail();
        abort_unless($share->is_active && ! $share->revoked_at && (! $share->expires_at || $share->expires_at->isFuture()), 404);

        return $share;
    }

    public function revoke(PublicDashboardShare $share, User $user): void
    {
        $share->update(['is_active' => false, 'revoked_at' => now(), 'revoked_by' => $user->id]);
        $this->audit->record($share, 'public_share.revoked', $user, [], ['revoked_at' => $share->revoked_at->toISOString(), 'token_last_four' => $share->token_last_four]);
    }
}
