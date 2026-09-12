<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Shift;
use App\Models\ShiftMember;
use App\Models\User;
use App\Services\DailyOperationService;
use Carbon\Carbon;
use Database\Seeders\EteFlowSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ShiftTeamTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_master_can_view_the_current_shift_team(): void
    {
        $this->travelTo(Carbon::parse('2026-09-10 10:00:00'));
        $this->seed(EteFlowSeeder::class);
        $master = User::where('username', 'master.teste')->firstOrFail();

        $response = $this->actingAs($master)->get(route('team.index'));

        $response
            ->assertOk()
            ->assertSee('Equipe do turno')
            ->assertSee('Operador 1')
            ->assertSee('Operador 2');
    }

    public function test_operator_cannot_change_the_shift_team(): void
    {
        $this->travelTo(Carbon::parse('2026-09-10 10:00:00'));
        $this->seed(EteFlowSeeder::class);
        $operator = User::where('username', 'operador1')->firstOrFail();
        $otherOperator = User::where('username', 'operador2')->firstOrFail();
        $shift = Shift::firstOrFail();

        $response = $this->actingAs($operator)->put(route('team.update'), [
            'operator_ids' => [$otherOperator->id],
        ]);

        $response->assertForbidden();
        $this->assertSame(2, $shift->fresh()->members()->count());
        $this->assertSame(0, AuditLog::where('action', 'shift.team_updated')->count());
    }

    public function test_master_can_leave_one_operator_without_losing_membership_history(): void
    {
        $this->travelTo(Carbon::parse('2026-09-10 10:00:00'));
        $this->seed(EteFlowSeeder::class);
        $master = User::where('username', 'master.teste')->firstOrFail();
        $firstOperator = User::where('username', 'operador1')->firstOrFail();
        $secondOperator = User::where('username', 'operador2')->firstOrFail();
        $shift = Shift::firstOrFail();

        $response = $this->actingAs($master)->put(route('team.update'), [
            'operator_ids' => [$secondOperator->id],
        ]);

        $response
            ->assertRedirect(route('team.index'))
            ->assertSessionHas('success');
        $this->assertSame([$secondOperator->id], $shift->fresh()->members()->pluck('users.id')->all());
        $this->assertNotNull(
            ShiftMember::where('shift_id', $shift->id)
                ->where('user_id', $firstOperator->id)
                ->value('left_at'),
        );
        $this->assertSame(2, $shift->fresh()->allMembers()->count());
        $this->assertSame(
            [$secondOperator->id],
            app(DailyOperationService::class)->ensure()->members()->pluck('users.id')->all(),
        );
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Shift::class,
            'auditable_id' => $shift->id,
            'action' => 'shift.team_updated',
            'user_id' => $master->id,
        ]);
    }

    public function test_team_requires_at_least_one_active_operator(): void
    {
        $this->travelTo(Carbon::parse('2026-09-10 10:00:00'));
        $this->seed(EteFlowSeeder::class);
        $master = User::where('username', 'master.teste')->firstOrFail();
        $shift = Shift::firstOrFail();

        $response = $this->actingAs($master)->from(route('team.index'))->put(route('team.update'), [
            'operator_ids' => [],
        ]);

        $response
            ->assertRedirect(route('team.index'))
            ->assertSessionHasErrors(['operator_ids']);
        $this->assertSame(2, $shift->fresh()->members()->count());
    }
}
