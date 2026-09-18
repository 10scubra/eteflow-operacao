<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditService
{
    public function record(
        Model $auditable,
        string $action,
        ?User $user = null,
        array $oldValues = [],
        array $newValues = [],
    ): AuditLog {
        return AuditLog::query()->create([
            'auditable_type' => $auditable->getMorphClass(),
            'auditable_id' => $auditable->getKey(),
            'action' => $action,
            'old_values' => $this->sanitize($oldValues) ?: null,
            'new_values' => $this->sanitize($newValues) ?: null,
            'user_id' => $user?->getKey(),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'created_at' => now(),
        ]);
    }

    private function sanitize(array $values): array
    {
        return collect($values)->reject(function (mixed $value, string $key): bool {
            return Str::contains(Str::lower($key), ['password', 'token', 'secret']);
        })->map(function (mixed $value): mixed {
            return is_array($value) ? $this->sanitize($value) : $value;
        })->all();
    }
}
