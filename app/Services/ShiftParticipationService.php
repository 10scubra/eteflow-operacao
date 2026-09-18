<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftMember;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShiftParticipationService
{
    public function __construct(private AuditService $audit) {}

    public function join(Shift $shift, ?Employee $employee, ?User $user, CarbonInterface $joinedAt, User $actor, ?CarbonInterface $leftAt = null): ShiftMember
    {
        return DB::transaction(fn () => $this->joinLocked($shift, $employee, $user, $joinedAt, $actor, $leftAt), 3);
    }

    public function leave(ShiftMember $membership, CarbonInterface $leftAt, User $actor): ShiftMember
    {
        return DB::transaction(function () use ($membership, $leftAt, $actor) {
            $locked = ShiftMember::query()->lockForUpdate()->findOrFail($membership->id);
            $time = Carbon::instance($leftAt);
            if ($locked->left_at !== null) {
                throw ValidationException::withMessages(['membership' => 'Esta participação já foi encerrada.']);
            }
            if ($locked->joined_at && $time->lessThanOrEqualTo($locked->joined_at)) {
                throw ValidationException::withMessages(['left_at' => 'A saída deve ocorrer depois da entrada.']);
            }

            $locked->update(['left_at' => $time]);
            $this->audit->record($locked, 'shift_membership.left', $actor, ['left_at' => null], ['left_at' => $time->toISOString()]);

            return $locked->fresh();
        }, 3);
    }

    public function substitute(ShiftMember $membership, ?Employee $replacementEmployee, ?User $replacementUser, CarbonInterface $at, User $actor): ShiftMember
    {
        return DB::transaction(function () use ($membership, $replacementEmployee, $replacementUser, $at, $actor) {
            $locked = ShiftMember::query()->lockForUpdate()->findOrFail($membership->id);
            $time = Carbon::instance($at);
            if ($locked->left_at !== null || ($locked->joined_at && $time->lessThanOrEqualTo($locked->joined_at))) {
                throw ValidationException::withMessages(['membership' => 'A participação anterior não pode ser substituída nesse horário.']);
            }

            $locked->update(['left_at' => $time]);
            $this->audit->record($locked, 'shift_membership.left', $actor, ['left_at' => null], ['left_at' => $time->toISOString(), 'reason' => 'substitution']);
            $replacement = $this->joinLocked($locked->shift, $replacementEmployee, $replacementUser, $time, $actor, null);
            $this->audit->record($replacement, 'shift_membership.substituted', $actor, ['replaced_membership_id' => $locked->id], ['replacement_membership_id' => $replacement->id]);

            return $replacement;
        }, 3);
    }

    private function joinLocked(Shift $shift, ?Employee $employee, ?User $user, CarbonInterface $joinedAt, User $actor, ?CarbonInterface $leftAt): ShiftMember
    {
        $lockedShift = Shift::query()->lockForUpdate()->findOrFail($shift->id);
        $this->validateIdentity($employee, $user);
        $start = Carbon::instance($joinedAt);
        $end = $leftAt ? Carbon::instance($leftAt) : null;

        if ($end && $end->lessThanOrEqualTo($start)) {
            throw ValidationException::withMessages(['left_at' => 'A saída deve ocorrer depois da entrada.']);
        }

        $memberships = ShiftMember::query()
            ->where('shift_id', $lockedShift->id)
            ->where(function ($query) use ($employee, $user) {
                if ($employee) {
                    $query->where('employee_id', $employee->id);
                }
                if ($user) {
                    $employee ? $query->orWhere('user_id', $user->id) : $query->where('user_id', $user->id);
                }
            })
            ->lockForUpdate()
            ->get();
        $openLegacyMembership = $employee && $user
            ? $memberships->first(fn (ShiftMember $existing): bool => $existing->left_at === null
                && $existing->employee_id === null
                && (int) $existing->user_id === (int) $user->id
            )
            : null;

        if ($openLegacyMembership) {
            $openLegacyMembership->update(['employee_id' => $employee->id]);
            $this->audit->record(
                $openLegacyMembership,
                'shift_membership.identity_linked',
                $actor,
                ['employee_id' => null],
                ['employee_id' => $employee->id],
            );

            return $openLegacyMembership->fresh();
        }

        $overlap = $memberships->contains(function (ShiftMember $existing) use ($start, $end): bool {
            $existingStart = $existing->joined_at ?? $existing->created_at;

            return ($end === null || $existingStart->lt($end))
                && ($existing->left_at === null || $existing->left_at->gt($start));
        });

        if ($overlap) {
            throw ValidationException::withMessages(['joined_at' => 'Já existe participação aberta ou sobreposta para esta pessoa no turno.']);
        }

        $membership = ShiftMember::query()->create([
            'shift_id' => $lockedShift->id,
            'employee_id' => $employee?->id,
            'user_id' => $user?->id,
            'joined_at' => $start,
            'left_at' => $end,
        ]);
        $action = $memberships->isEmpty() ? 'shift_membership.joined' : 'shift_membership.reentered';
        $this->audit->record($membership, $action, $actor, [], [
            'shift_id' => $lockedShift->id,
            'employee_id' => $employee?->id,
            'user_id' => $user?->id,
            'joined_at' => $start->toISOString(),
            'left_at' => $end?->toISOString(),
        ]);

        return $membership;
    }

    private function validateIdentity(?Employee $employee, ?User $user): void
    {
        if (! $employee && ! $user) {
            throw ValidationException::withMessages(['employee_id' => 'Informe o colaborador ou o usuário participante.']);
        }
        if ($employee && $user && (int) $user->employee_id !== (int) $employee->id) {
            throw ValidationException::withMessages(['user_id' => 'O usuário informado não pertence ao mesmo colaborador.']);
        }
    }
}
