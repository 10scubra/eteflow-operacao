<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ShiftMember;
use App\Models\User;
use App\Services\DailyOperationService;
use App\Services\PermissionService;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StageTwoInterfaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_master_accesses_administration_and_operator_and_viewer_are_denied(): void
    {
        $master = $this->user('master');
        $this->actingAs($master)->get(route('admin.employees.index'))->assertOk()->assertSee('Colaboradores');
        $this->actingAs($master)->get(route('admin.roles.index'))
            ->assertOk()
            ->assertSee('Perfis e permissões')
            ->assertDontSee('Equipamentos')
            ->assertDontSee('equipment.view');
        $this->assertDatabaseHas('permissions', ['code' => 'equipment.view']);
        foreach (['operator', 'viewer'] as $role) {
            $this->actingAs($this->user($role))->get(route('admin.employees.index'))->assertForbidden();
        }
    }

    public function test_master_creates_edits_and_changes_employee_status_with_audit(): void
    {
        $master = $this->user('master');
        $response = $this->actingAs($master)->post(route('admin.employees.store'), [
            'full_name' => 'João da Silva', 'display_name' => 'João', 'registration_number' => '1234',
            'job_title' => 'Operador ETE', 'status' => 'active', 'personal_email' => 'joao@pessoal.test',
        ]);
        $employee = Employee::where('registration_number', '1234')->firstOrFail();
        $response->assertRedirect(route('admin.employees.show', $employee));
        $this->actingAs($master)->put(route('admin.employees.update', $employee), [
            'full_name' => 'João da Silva', 'display_name' => 'João', 'registration_number' => '1234',
            'job_title' => 'Operador ETE', 'status' => 'vacation',
        ])->assertRedirect(route('admin.employees.show', $employee));
        $this->assertSame('vacation', $employee->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'employee.created', 'auditable_id' => $employee->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'employee.updated', 'auditable_id' => $employee->id]);
    }

    public function test_sensitive_data_is_absent_from_html_without_specific_permission(): void
    {
        $role = Role::create(['code' => 'employee_reader', 'name' => 'Consulta', 'is_active' => true]);
        $role->permissions()->attach(Permission::where('code', 'employees.view')->firstOrFail());
        $user = $this->user('viewer', $role);
        $employee = Employee::create([
            'full_name' => 'Pessoa Restrita', 'display_name' => 'Pessoa', 'status' => 'active',
            'personal_email' => 'segredo@pessoal.test', 'phone' => '99999',
            'administrative_notes' => 'observação ultrassecreta', 'termination_reason' => 'motivo confidencial',
        ]);
        $this->actingAs($user)->get(route('admin.employees.show', $employee))->assertOk()
            ->assertDontSee('segredo@pessoal.test')->assertDontSee('99999')
            ->assertDontSee('observação ultrassecreta')->assertDontSee('motivo confidencial');
    }

    public function test_master_creates_access_and_temporary_password_is_shown_once(): void
    {
        $master = $this->user('master');
        $employee = Employee::create(['full_name' => 'Carlos Souza', 'display_name' => 'Carlos', 'status' => 'active']);
        $role = Role::where('code', 'operator')->firstOrFail();
        $response = $this->actingAs($master)->post(route('admin.employees.access.store', $employee), [
            'username' => 'carlos', 'email' => 'carlos@eteflow.test', 'role_id' => $role->id,
            'temporary_password' => 'Temporaria2026',
        ]);
        $user = $employee->fresh()->user;
        $response->assertRedirect(route('admin.employees.access.show', $employee))->assertSessionHas('temporary_password', 'Temporaria2026');
        $this->assertTrue(Hash::check('Temporaria2026', $user->password));
        $this->assertTrue($user->must_change_password);
        $payload = AuditLog::where('action', 'user.access_created')->firstOrFail()->toJson();
        $this->assertStringNotContainsString('Temporaria2026', $payload);
        $this->assertStringNotContainsString($user->password, $payload);
    }

    public function test_must_change_password_blocks_application_until_password_is_changed(): void
    {
        $user = $this->user('operator');
        $user->update(['must_change_password' => true, 'password' => 'Temporaria2026']);
        $this->actingAs($user)->get(route('operation.home'))->assertRedirect(route('password.change'));
        $this->get(route('password.change'))->assertOk()->assertSee('Defina sua nova senha');
        $this->put(route('password.update'), ['password' => 'NovaSenha2026', 'password_confirmation' => 'NovaSenha2026'])
            ->assertRedirect(route('operation.home'));
        $this->assertFalse($user->fresh()->must_change_password);
        $this->assertTrue(Hash::check('NovaSenha2026', $user->fresh()->password));
        $this->assertStringNotContainsString('NovaSenha2026', AuditLog::where('action', 'user.password_changed')->firstOrFail()->toJson());
    }

    public function test_master_blocks_unblocks_deactivates_and_reactivates_access(): void
    {
        $master = $this->user('master');
        [$employee, $user] = $this->employeeWithUser('Operador', 'operator');
        $this->actingAs($master)->put(route('admin.employees.access.block', $employee))->assertRedirect();
        $this->assertNotNull($user->fresh()->blocked_at);
        $this->put(route('admin.employees.access.unblock', $employee))->assertRedirect();
        $this->assertNull($user->fresh()->blocked_at);
        $this->put(route('admin.employees.access.deactivate', $employee))->assertRedirect();
        $this->assertFalse($user->fresh()->is_active);
        $this->put(route('admin.employees.access.activate', $employee))->assertRedirect();
        $this->assertTrue($user->fresh()->is_active);
        $response = $this->put(route('admin.employees.access.password', $employee))->assertRedirect();
        $response->assertSessionHas('temporary_password');
        $temporaryPassword = session('temporary_password');
        $this->assertTrue($user->fresh()->must_change_password);
        $this->assertTrue(Hash::check($temporaryPassword, $user->fresh()->password));
        $this->assertStringNotContainsString($temporaryPassword, AuditLog::where('action', 'user.temporary_password_set')->firstOrFail()->toJson());
    }

    public function test_last_administrator_is_protected_through_interface(): void
    {
        $master = $this->user('master');
        $employee = Employee::create(['full_name' => 'Master', 'display_name' => 'Master', 'status' => 'active']);
        $master->update(['employee_id' => $employee->id]);
        $this->actingAs($master)->put(route('admin.employees.access.block', $employee))
            ->assertSessionHasErrors('administrator');
        $this->assertNull($master->fresh()->blocked_at);
    }

    public function test_master_changes_role_and_role_permissions(): void
    {
        $master = $this->user('master');
        [$employee, $user] = $this->employeeWithUser('Visualizador', 'viewer');
        $operator = Role::where('code', 'operator')->firstOrFail();
        $this->actingAs($master)->put(route('admin.employees.access.role', $employee), ['role_id' => $operator->id])->assertRedirect();
        $this->assertSame($operator->id, $user->fresh()->role_id);
        $permission = Permission::where('code', 'employees.view')->firstOrFail();
        $this->put(route('admin.roles.update', $operator), ['permission_ids' => [$permission->id]])->assertRedirect();
        $this->assertTrue($operator->permissions()->whereKey($permission->id)->exists());
        $this->assertDatabaseHas('audit_logs', ['action' => 'role.permissions_updated', 'auditable_id' => $operator->id]);
    }

    public function test_individual_override_allow_and_deny_are_visible_and_enforced(): void
    {
        $master = $this->user('master');
        [$employee, $user] = $this->employeeWithUser('Consulta', 'viewer');
        $permission = Permission::where('code', 'employees.view')->firstOrFail();
        $this->actingAs($master)->put(route('admin.employees.access.override', [$employee, $permission]), ['effect' => 'allow'])->assertRedirect();
        $this->assertTrue(app(PermissionService::class)->allows($user->fresh(), 'employees.view'));
        $this->put(route('admin.employees.access.override', [$employee, $permission]), ['effect' => 'deny'])->assertRedirect();
        $this->assertFalse(app(PermissionService::class)->allows($user->fresh(), 'employees.view'));
        $this->get(route('admin.employees.access.show', $employee))->assertOk()->assertSee('Negado individualmente');
    }

    public function test_team_interface_registers_entry_exit_and_preserves_intervals(): void
    {
        $master = $this->user('master');
        [$employee] = $this->employeeWithUser('Igor', 'operator');
        $shift = app(DailyOperationService::class)->ensure();
        $this->actingAs($master)->post(route('team.join'), ['employee_id' => $employee->id])->assertRedirect();
        $membership = ShiftMember::where('shift_id', $shift->id)->where('employee_id', $employee->id)->firstOrFail();
        $this->travel(1)->seconds();
        $this->put(route('team.leave', $membership))->assertRedirect();
        $this->assertNotNull($membership->fresh()->left_at);
        $this->get(route('team.index'))->assertOk()->assertSee('Intervalos do turno')->assertSee('Igor');
    }

    public function test_team_substitution_preserves_previous_record(): void
    {
        $master = $this->user('master');
        [$igor] = $this->employeeWithUser('Igor', 'operator');
        [$carlos] = $this->employeeWithUser('Carlos', 'operator');
        $shift = app(DailyOperationService::class)->ensure();
        $this->actingAs($master)->post(route('team.join'), ['employee_id' => $igor->id]);
        $old = ShiftMember::where('shift_id', $shift->id)->where('employee_id', $igor->id)->firstOrFail();
        $joinedAt = $old->joined_at;
        $this->travel(1)->seconds();
        $this->post(route('team.substitute', $old), ['employee_id' => $carlos->id])->assertRedirect();
        $this->assertEquals($joinedAt, $old->fresh()->joined_at);
        $this->assertNotNull($old->fresh()->left_at);
        $this->assertDatabaseHas('shift_members', ['shift_id' => $shift->id, 'employee_id' => $carlos->id, 'left_at' => null]);
    }

    public function test_direct_team_route_without_permission_is_forbidden(): void
    {
        $this->actingAs($this->user('viewer'))->post(route('team.join'), ['employee_id' => 999])->assertForbidden();
    }

    private function user(string $legacyRole, ?Role $role = null): User
    {
        return User::factory()->create(['role' => $legacyRole, 'role_id' => $role?->id, 'is_active' => true, 'blocked_at' => null, 'must_change_password' => false]);
    }

    private function employeeWithUser(string $name, string $roleCode): array
    {
        $employee = Employee::create(['full_name' => $name, 'display_name' => $name, 'status' => 'active']);
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user = $this->user($roleCode, $role);
        $user->update(['employee_id' => $employee->id]);

        return [$employee, $user];
    }
}
