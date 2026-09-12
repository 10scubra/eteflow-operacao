<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Shift;
use App\Models\ShiftMember;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UpdateShiftTeam
{
    /** @param Collection<int, User> $operators */
    public function handle(Shift $shift, Collection $operators, User $changedBy): Shift
    {
        return DB::transaction(function () use ($shift, $operators, $changedBy) {
            $lockedShift = Shift::query()->lockForUpdate()->findOrFail($shift->id);
            $selectedIds = $operators->modelKeys();
            $memberships = ShiftMember::query()
                ->where('shift_id', $lockedShift->id)
                ->lockForUpdate()
                ->get();
            $previousIds = $memberships->whereNull('left_at')->pluck('user_id')->values()->all();

            foreach ($memberships->whereNull('left_at') as $membership) {
                if (! in_array($membership->user_id, $selectedIds, true)) {
                    $membership->update(['left_at' => now()]);
                }
            }

            foreach ($selectedIds as $operatorId) {
                $membership = $memberships->firstWhere('user_id', $operatorId);

                if ($membership) {
                    if ($membership->left_at !== null) {
                        $membership->update(['joined_at' => now(), 'left_at' => null]);
                    }

                    continue;
                }

                ShiftMember::create([
                    'shift_id' => $lockedShift->id,
                    'user_id' => $operatorId,
                    'joined_at' => now(),
                ]);
            }

            AuditLog::create([
                'auditable_type' => Shift::class,
                'auditable_id' => $lockedShift->id,
                'action' => 'shift.team_updated',
                'old_values' => ['operator_ids' => $previousIds],
                'new_values' => ['operator_ids' => $selectedIds],
                'user_id' => $changedBy->id,
                'created_at' => now(),
            ]);

            return $lockedShift->fresh(['members', 'allMembers']);
        }, 3);
    }
}
