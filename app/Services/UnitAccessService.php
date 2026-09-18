<?php

namespace App\Services;

use App\Models\OperationalUnit;
use App\Models\User;
use Illuminate\Support\Collection;

class UnitAccessService
{
    public function accessible(User $user): Collection
    {
        return $user->operationalUnits()->where('is_active', true)->orderBy('name')->get();
    }

    public function resolve(User $user, ?int $requested = null): OperationalUnit
    {
        $query = $user->operationalUnits()->where('is_active', true);
        if ($requested) {
            $query->whereKey($requested);
        }
        $unit = $query->orderByDesc('operational_unit_user.is_default')->first();
        abort_unless($unit, 403, 'Usuário sem acesso à unidade solicitada.');

        return $unit;
    }

    public function canAccess(User $user, int $unitId): bool
    {
        return $user->operationalUnits()->whereKey($unitId)->exists();
    }
}
