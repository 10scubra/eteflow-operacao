<?php

namespace App\Actions;

use App\Models\Shift;
use App\Models\ShiftMember;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UpdateShiftTeam
{
    public function __construct(private AuditService $audit) {}

    /** @param Collection<int, User> $operators */
    public function handle(Shift $shift, Collection $operators, User $changedBy): Shift
    {
        return DB::transaction(function () use ($shift, $operators, $changedBy) {
            $lockedShift = Shift::query()->lockForUpdate()->findOrFail($shift->id);
            $selectedIds = $operators->modelKeys();
            $memberships = ShiftMember::query()->where('shift_id', $lockedShift->id)->lockForUpdate()->get();
            $openMemberships = $memberships->whereNull('left_at');
            $previousIds = $openMemberships->pluck('user_id')->filter()->values()->all();

            foreach ($openMemberships as $membership) {
                if (! in_array($membership->user_id, $selectedIds, true)) {
                    $membership->update(['left_at' => now()]);
                    $this->audit->record($membership, 'shift_membership.left', $changedBy, ['left_at' => null], ['left_at' => now()->toISOString()]);
                }
            }

            foreach ($selectedIds as $operatorId) {
                if ($openMemberships->contains('user_id', $operatorId)) {
                    continue;
                }

                $operator = $operators->firstWhere('id', $operatorId);
                $membership = ShiftMember::query()->create([
                    'shift_id' => $lockedShift->id,
                    'user_id' => $operatorId,
                    'employee_id' => $operator?->employee_id,
                    'joined_at' => now(),
                ]);
                $action = $memberships->contains('user_id', $operatorId) ? 'shift_membership.reentered' : 'shift_membership.joined';
                $this->audit->record($membership, $action, $changedBy, [], [
                    'shift_id' => $lockedShift->id,
                    'user_id' => $operatorId,
                    'employee_id' => $operator?->employee_id,
                    'joined_at' => now()->toISOString(),
                ]);
            }

            $this->audit->record($lockedShift, 'shift.team_updated', $changedBy, ['operator_ids' => $previousIds], ['operator_ids' => $selectedIds]);

            return $lockedShift->fresh(['members', 'allMembers']);
        }, 3);
    }
}
