<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Shift;
use App\Models\ShiftMember;
use App\Models\User;
use App\Services\EmployeeService;
use App\Services\PermissionService;
use App\Services\ShiftParticipationService;
use App\Services\UserAdministrationService;
use Carbon\Carbon;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StageTwoFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_access_control_seeder_is_idempotent(): void
    {
        $counts = [Role::count(), Permission::count(), \DB::table('permission_role')->count()];
        $this->seed(AccessControlSeeder::class);
        $this->assertSame($counts, [Role::count(), Permission::count(), \DB::table('permission_role')->count()]);
    }

    public function test_employee_can_exist_without_user_and_link_is_optional(): void
    {
        $employee = Employee::query()->create(['full_name' => 'Igor Silva', 'display_name' => 'Igor']);
        $this->assertNull($employee->user);
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'status' => 'active']);
    }

    public function test_user_can_be_linked_to_employee_and_legacy_user_still_works(): void
    {
        $actor = $this->master();
        $employee = Employee::query()->create(['full_name' => 'Igor Silva', 'display_name' => 'Igor']);
        $user = User::factory()->create(['role' => 'operator', 'is_active' => true]);
        app(EmployeeService::class)->linkUser($employee, $user, $actor);
        $this->assertSame($employee->id, $user->fresh()->employee_id);

        $legacy = User::factory()->create(['username' => 'legado', 'password' => 'segredo', 'role' => 'operator', 'is_active' => true]);
        $this->post('/login', ['username' => 'legado', 'password' => 'segredo'])->assertRedirect();
        $this->assertAuthenticatedAs($legacy);
        $this->assertNotNull($legacy->fresh()->last_login_at);
    }

    public function test_blocked_user_with_allow_override_is_denied_and_cannot_login(): void
    {
        $user = User::factory()->create(['username' => 'bloqueado', 'password' => 'segredo', 'role' => 'viewer', 'is_active' => true, 'blocked_at' => now()]);
        $permission = Permission::where('code', 'employees.view')->firstOrFail();
        $user->permissionOverrides()->create(['permission_id' => $permission->id, 'effect' => 'allow']);
        $this->assertFalse(app(PermissionService::class)->allows($user->fresh(), 'employees.view'));
        $this->post('/login', ['username' => 'bloqueado', 'password' => 'segredo'])->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_role_grants_permission_and_deny_override_precedes_it(): void
    {
        $role = Role::where('code', 'operator')->firstOrFail();
        $user = User::factory()->create(['role' => 'viewer', 'role_id' => $role->id, 'is_active' => true]);
        $service = app(PermissionService::class);
        $this->assertTrue($service->allows($user, 'readings.fill'));
        $permission = Permission::where('code', 'readings.fill')->firstOrFail();
        $user->permissionOverrides()->create(['permission_id' => $permission->id, 'effect' => 'deny']);
        $this->assertFalse($service->allows($user->fresh(), 'readings.fill'));
    }

    public function test_legacy_role_fallback_and_sensitive_permission_are_distinct(): void
    {
        $viewOnlyRole = Role::query()->create(['code' => 'employee_viewer', 'name' => 'Consulta de colaboradores', 'is_active' => true]);
        $viewOnlyRole->permissions()->attach(Permission::where('code', 'employees.view')->firstOrFail());
        $viewer = User::factory()->create(['role' => 'viewer', 'role_id' => $viewOnlyRole->id, 'is_active' => true]);
        $employee = Employee::query()->create(['full_name' => 'Pessoa', 'display_name' => 'Pessoa', 'phone' => '9999']);
        $this->assertTrue(app(PermissionService::class)->allows($viewer, 'employees.view'));
        $this->assertFalse(Gate::forUser($viewer)->allows('viewSensitive', $employee));

        $master = $this->master();
        $this->assertTrue(app(PermissionService::class)->allows($master, 'employees.view_sensitive'));
        $this->assertTrue(Gate::forUser($master)->allows('viewSensitive', $employee));
    }

    public function test_incompatible_employee_and_user_are_rejected(): void
    {
        [$shift, $actor] = $this->shiftAndActor();
        $employee = Employee::query()->create(['full_name' => 'Igor', 'display_name' => 'Igor']);
        $other = Employee::query()->create(['full_name' => 'Carlos', 'display_name' => 'Carlos']);
        $user = User::factory()->create(['employee_id' => $other->id]);
        $this->expectException(ValidationException::class);
        app(ShiftParticipationService::class)->join($shift, $employee, $user, Carbon::parse('2026-09-14 08:00'), $actor);
    }

    public function test_two_open_or_overlapping_memberships_are_rejected(): void
    {
        [$shift, $actor] = $this->shiftAndActor();
        $employee = Employee::query()->create(['full_name' => 'Igor', 'display_name' => 'Igor']);
        $service = app(ShiftParticipationService::class);
        $service->join($shift, $employee, null, Carbon::parse('2026-09-14 08:00'), $actor);

        try {
            $service->join($shift, $employee, null, Carbon::parse('2026-09-14 09:00'), $actor);
            $this->fail('Uma segunda participação aberta deveria ser rejeitada.');
        } catch (ValidationException) {
            $this->assertSame(1, ShiftMember::count());
        }

        $open = ShiftMember::firstOrFail();
        $service->leave($open, Carbon::parse('2026-09-14 12:00'), $actor);
        $this->expectException(ValidationException::class);
        $service->join($shift, $employee, null, Carbon::parse('2026-09-14 11:00'), $actor, Carbon::parse('2026-09-14 13:00'));
    }

    public function test_reentry_after_exit_is_allowed_and_preserves_first_interval(): void
    {
        [$shift, $actor] = $this->shiftAndActor();
        $employee = Employee::query()->create(['full_name' => 'Igor', 'display_name' => 'Igor']);
        $service = app(ShiftParticipationService::class);
        $first = $service->join($shift, $employee, null, Carbon::parse('2026-09-14 08:00'), $actor);
        $service->leave($first, Carbon::parse('2026-09-14 12:00'), $actor);
        $second = $service->join($shift, $employee, null, Carbon::parse('2026-09-14 14:00'), $actor);
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame('2026-09-14 08:00:00', $first->fresh()->joined_at->format('Y-m-d H:i:s'));
        $this->assertSame(2, ShiftMember::where('employee_id', $employee->id)->count());
    }

    public function test_substitution_closes_old_record_and_creates_a_new_one(): void
    {
        [$shift, $actor] = $this->shiftAndActor();
        $igor = Employee::query()->create(['full_name' => 'Igor', 'display_name' => 'Igor']);
        $carlos = Employee::query()->create(['full_name' => 'Carlos', 'display_name' => 'Carlos']);
        $service = app(ShiftParticipationService::class);
        $first = $service->join($shift, $igor, null, Carbon::parse('2026-09-14 08:00'), $actor);
        $replacement = $service->substitute($first, $carlos, null, Carbon::parse('2026-09-14 14:00'), $actor);
        $this->assertSame('2026-09-14 08:00:00', $first->fresh()->joined_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-14 14:00:00', $first->fresh()->left_at->format('Y-m-d H:i:s'));
        $this->assertNotSame($first->id, $replacement->id);
        $this->assertSame($carlos->id, $replacement->employee_id);
    }

    public function test_repeated_start_requests_do_not_create_duplicate_membership(): void
    {
        [$shift, $actor] = $this->shiftAndActor();
        $employee = Employee::query()->create(['full_name' => 'Igor', 'display_name' => 'Igor']);
        $service = app(ShiftParticipationService::class);
        $service->join($shift, $employee, null, Carbon::parse('2026-09-14 08:00'), $actor);
        try {
            $service->join($shift, $employee, null, Carbon::parse('2026-09-14 08:00'), $actor);
        } catch (ValidationException) {
            // A trava do turno serializa requisições concorrentes; a segunda encontra o intervalo aberto.
        }
        $this->assertSame(1, ShiftMember::where('shift_id', $shift->id)->where('employee_id', $employee->id)->count());
    }

    public function test_last_administrator_cannot_be_blocked_or_lose_administration(): void
    {
        $master = $this->master();
        $service = app(UserAdministrationService::class);
        try {
            $service->block($master, $master);
            $this->fail('O último administrador não deveria ser bloqueado.');
        } catch (ValidationException) {
            $this->assertNull($master->fresh()->blocked_at);
        }

        $permission = Permission::where('code', 'administration.manage_permissions')->firstOrFail();
        $this->expectException(ValidationException::class);
        $service->setPermissionOverride($master, $permission, 'deny', $master);
    }

    public function test_employee_and_temporary_password_audits_never_store_password_or_hash(): void
    {
        $master = $this->master();
        $employee = app(EmployeeService::class)->create([
            'full_name' => 'Pessoa Auditada', 'display_name' => 'Pessoa', 'personal_email' => 'pessoal@example.com',
        ], $master);
        app(EmployeeService::class)->update($employee, ['phone' => '99999'], $master);
        app(UserAdministrationService::class)->setTemporaryPassword($master, 'Temporaria@2026', $master);
        $payload = AuditLog::query()->get()->flatMap(fn (AuditLog $log) => [$log->old_values, $log->new_values])->filter()->toJson();
        $this->assertStringNotContainsString('Temporaria@2026', $payload);
        $this->assertStringNotContainsString('$2y$', $payload);
        $this->assertStringNotContainsString('password', strtolower($payload));
        $this->assertTrue($master->fresh()->must_change_password);
    }

    public function test_employee_with_linked_history_is_protected_from_physical_deletion(): void
    {
        $employee = Employee::query()->create(['full_name' => 'Pessoa', 'display_name' => 'Pessoa']);
        User::factory()->create(['employee_id' => $employee->id]);
        $this->expectException(QueryException::class);
        $employee->delete();
    }

    private function master(): User
    {
        return User::factory()->create(['role' => 'master', 'is_active' => true, 'blocked_at' => null]);
    }

    /** @return array{Shift, User} */
    private function shiftAndActor(): array
    {
        return [Shift::query()->create([
            'shift_date' => '2026-09-14', 'starts_at' => '08:00:00', 'ends_at' => '20:00:00', 'status' => 'active',
        ]), $this->master()];
    }
}
