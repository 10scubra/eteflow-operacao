<?php

namespace Tests\Feature;

use App\Models\ChemicalInventoryCheck;
use App\Models\ChemicalOpenPackage;
use App\Models\ChemicalOpenPackageWeighing;
use App\Models\ChemicalStockCount;
use App\Models\ChemicalStockMovement;
use App\Models\ChemicalStorageLocation;
use App\Models\User;
use App\Services\ChemicalStockService;
use App\Services\DailyOperationService;
use Carbon\Carbon;
use Database\Seeders\EteFlowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ChemicalStockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-09 10:00:00');
        $this->seed(EteFlowSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_operator_sees_a_blind_count_and_cannot_access_management(): void
    {
        $operator = User::where('username', 'operador1')->firstOrFail();
        $response = $this->actingAs($operator)->get(route('chemical-stock.count.form'));
        $response->assertOk()->assertSee('Quantidade encontrada')->assertDontSee('Saldo esperado')->assertDontSee('Última contagem')
            ->assertDontSee('capacidade')->assertDontSee('Estoque crítico')->assertDontSee('Divergência');
        $this->get(route('chemical-stock.dashboard'))->assertForbidden();
        $this->get(route('chemical-stock.history'))->assertForbidden();
        $this->get(route('admin.chemical-stock.index'))->assertForbidden();
    }

    public function test_count_accepts_zero_preserves_author_and_is_unique_per_shift(): void
    {
        $operator = User::where('username', 'operador1')->firstOrFail();
        $locations = ChemicalStorageLocation::where('participates_in_shift_count', true)->pluck('id');
        $payload = ['quantities' => $locations->mapWithKeys(fn ($id) => [$id => 0])->all()];
        $this->actingAs($operator)->post(route('chemical-stock.count.store'), $payload)->assertSessionHas('success');
        $count = ChemicalStockCount::firstOrFail();
        $this->assertSame($operator->id, $count->counted_by);
        $this->assertSame($locations->count(), $count->items()->where('quantity', 0)->count());
        $this->post(route('chemical-stock.count.store'), $payload)->assertSessionHasErrors('count');
        $this->assertSame(1, ChemicalStockCount::count());
    }

    public function test_negative_and_invalid_precision_are_rejected(): void
    {
        $operator = User::where('username', 'operador1')->firstOrFail();
        $locations = ChemicalStorageLocation::with('product')->where('participates_in_shift_count', true)->get();
        $payload = $locations->mapWithKeys(fn ($location) => [$location->id => 1])->all();
        $payload[$locations->firstWhere('product.stable_key', 'polymer')->id] = -1;
        $this->actingAs($operator)->post(route('chemical-stock.count.store'), ['quantities' => $payload])->assertSessionHasErrors();
        $payload[$locations->firstWhere('product.stable_key', 'polymer')->id] = 18.5;
        $this->post(route('chemical-stock.count.store'), ['quantities' => $payload])->assertSessionHasErrors();
    }

    public function test_receipt_is_idempotent_preserves_author_and_changes_theoretical_balance(): void
    {
        $operator = User::where('username', 'operador1')->firstOrFail();
        $shift = app(DailyOperationService::class)->ensure();
        $location = ChemicalStorageLocation::whereHas('product', fn ($q) => $q->where('stable_key', 'polymer'))->firstOrFail();
        $stock = app(ChemicalStockService::class);
        $stock->count($shift, $operator, ChemicalStorageLocation::where('participates_in_shift_count', true)->pluck('id')->mapWithKeys(fn ($id) => [$id => $id === $location->id ? 23 : 100])->all());
        $key = (string) Str::uuid();
        $data = ['quantity' => 40, 'supplier' => 'Fornecedor teste', 'idempotency_key' => $key];
        $first = $stock->receipt($shift, $operator, $location, $data);
        $second = $stock->receipt($shift, $operator, $location, $data);
        $this->assertSame($first->id, $second->id);
        $this->assertSame($operator->id, $first->created_by);
        $this->assertSame(63.0, $stock->balance($location)['balance']);
        $this->assertSame(1, ChemicalStockMovement::count());
    }

    public function test_new_count_records_divergence_without_creating_movement(): void
    {
        $operator = User::where('username', 'operador1')->firstOrFail();
        $stock = app(ChemicalStockService::class);
        $firstShift = app(DailyOperationService::class)->ensure();
        $locations = ChemicalStorageLocation::where('participates_in_shift_count', true)->get();
        $values = $locations->mapWithKeys(fn ($location) => [$location->id => 100])->all();
        $stock->count($firstShift, $operator, $values);
        $secondShift = $firstShift->replicate();
        $secondShift->shift_date = '2026-09-09';
        $secondShift->starts_at = '20:00:00';
        $secondShift->status = 'active';
        $secondShift->save();
        $secondShift->members()->attach($operator->id, ['joined_at' => '2026-09-09 20:00:00']);
        $values[$locations->first()->id] = 95;
        $second = $stock->count($secondShift, $operator, $values);
        $this->assertSame('-5.0000', $second->items->firstWhere('chemical_storage_location_id', $locations->first()->id)->difference_snapshot);
        $this->assertSame(0, ChemicalStockMovement::count());
    }

    public function test_authorized_user_sees_balance_percentage_alerts_and_history(): void
    {
        $master = User::where('username', 'master.teste')->firstOrFail();
        $operator = User::where('username', 'operador1')->firstOrFail();
        $location = ChemicalStorageLocation::whereHas('product', fn ($q) => $q->where('stable_key', 'ferric-chloride'))->firstOrFail();
        $location->update(['capacity' => 1000, 'low_threshold_type' => 'PERCENTAGE', 'low_threshold_value' => 30, 'critical_threshold_type' => 'PERCENTAGE', 'critical_threshold_value' => 10]);
        $shift = app(DailyOperationService::class)->ensure();
        $values = ChemicalStorageLocation::where('participates_in_shift_count', true)->pluck('id')->mapWithKeys(fn ($id) => [$id => $id === $location->id ? 80 : 100])->all();
        app(ChemicalStockService::class)->count($shift, $operator, $values);
        $this->assertDatabaseHas('chemical_stock_alerts', ['chemical_storage_location_id' => $location->id, 'severity' => 'CRITICAL', 'status' => 'OPEN']);
        $this->actingAs($master)->get(route('chemical-stock.dashboard'))->assertOk()->assertSee('8,0%')->assertSee('Estoque crítico');
        $this->get(route('chemical-stock.history'))->assertOk()->assertSee('divergências');
    }

    public function test_recovery_resolves_alert_and_correction_preserves_original(): void
    {
        $master = User::where('username', 'master.teste')->firstOrFail();
        $operator = User::where('username', 'operador1')->firstOrFail();
        $stock = app(ChemicalStockService::class);
        $shift = app(DailyOperationService::class)->ensure();
        $location = ChemicalStorageLocation::whereHas('product', fn ($q) => $q->where('stable_key', 'polymer'))->firstOrFail();
        $location->update(['capacity' => 100, 'low_threshold_type' => 'ABSOLUTE', 'low_threshold_value' => 30, 'critical_threshold_type' => 'ABSOLUTE', 'critical_threshold_value' => 10]);
        $values = ChemicalStorageLocation::where('participates_in_shift_count', true)->pluck('id')->mapWithKeys(fn ($id) => [$id => $id === $location->id ? 5 : 100])->all();
        $count = $stock->count($shift, $operator, $values);
        $item = $count->items->firstWhere('chemical_storage_location_id', $location->id);
        $stock->correct('COUNT_ITEM', $item->id, 50, 'Erro de digitação', (string) Str::uuid(), $master);
        $this->assertSame('5.0000', $item->fresh()->quantity);
        $this->assertSame(50.0, $stock->balance($location)['balance']);
        $this->assertDatabaseHas('chemical_stock_alerts', ['chemical_storage_location_id' => $location->id, 'status' => 'RESOLVED']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'chemical_stock.corrected']);
    }

    public function test_missing_capacity_does_not_create_false_percentage_or_forbidden_payload(): void
    {
        $operator = User::where('username', 'operador1')->firstOrFail();
        $location = ChemicalStorageLocation::firstOrFail();
        $this->assertNull(app(ChemicalStockService::class)->balance($location)['percentage']);
        $html = $this->actingAs($operator)->get(route('chemical-stock.count.form'))->getContent();
        foreach (['expected_quantity_snapshot', 'difference_snapshot', 'low_threshold_value', 'critical_threshold_value', 'percentage'] as $secret) {
            $this->assertStringNotContainsString($secret, $html);
        }
        $this->assertFalse(class_exists('App\\Models\\PumpCalibration'));
        $this->assertStringNotContainsString('ranking', strtolower($html));
    }

    public function test_management_dashboard_filters_product_location_and_status(): void
    {
        $master = User::where('username', 'master.teste')->firstOrFail();
        $operator = User::where('username', 'operador1')->firstOrFail();
        $location = ChemicalStorageLocation::whereHas('product', fn ($query) => $query->where('stable_key', 'ferric-chloride'))->firstOrFail();
        $location->update(['capacity' => 1000, 'low_threshold_type' => 'PERCENTAGE', 'low_threshold_value' => 30, 'critical_threshold_type' => 'PERCENTAGE', 'critical_threshold_value' => 10]);
        $values = ChemicalStorageLocation::where('participates_in_shift_count', true)->pluck('id')->mapWithKeys(fn ($id) => [$id => $id === $location->id ? 50 : 100])->all();
        app(ChemicalStockService::class)->count(app(DailyOperationService::class)->ensure(), $operator, $values);

        $this->actingAs($master)->get(route('chemical-stock.dashboard', [
            'product' => $location->chemical_product_id,
            'location' => $location->id,
            'status' => 'CRITICAL',
            'period' => 7,
        ]))->assertOk()->assertSee('Cloreto Férrico')->assertSee('Estoque crítico')->assertDontSee('Polímero — Estoque principal');
    }

    public function test_consumption_uses_counts_and_confirmed_receipts_without_mixing_units(): void
    {
        $operator = User::where('username', 'operador1')->firstOrFail();
        $stock = app(ChemicalStockService::class);
        $location = ChemicalStorageLocation::whereHas('product', fn ($query) => $query->where('stable_key', 'ferric-chloride'))->firstOrFail();
        $location->update(['capacity' => 200]);
        $locations = ChemicalStorageLocation::where('participates_in_shift_count', true)->get();
        $values = $locations->mapWithKeys(fn ($item) => [$item->id => 100])->all();
        $firstShift = app(DailyOperationService::class)->ensure();
        $stock->count($firstShift, $operator, $values);

        Carbon::setTestNow('2026-09-09 12:00:00');
        $stock->receipt($firstShift, $operator, $location, ['quantity' => 20, 'idempotency_key' => (string) Str::uuid()]);
        $secondShift = $firstShift->replicate();
        $secondShift->starts_at = '20:00:00';
        $secondShift->ends_at = '08:00:00';
        $secondShift->status = 'active';
        $secondShift->save();
        Carbon::setTestNow('2026-09-09 21:00:00');
        $values[$location->id] = 90;
        $stock->count($secondShift, $operator, $values);

        $dashboard = $stock->dashboard($location->chemical_product_id, $location->id);
        $analytics = $stock->analytics(7, $dashboard);
        $this->assertSame(30.0, $analytics['consumption']->first()['amount']);
        $this->assertSame(15.0, $analytics['consumption']->first()['percentage']);
        $this->assertSame('L', $analytics['consumption']->first()['product']->unit->symbol);
        $this->assertSame(0.0, $analytics['consumption']->first()['divergence_gain']);
    }

    public function test_polymer_issue_opens_a_25kg_sack_and_weighings_preserve_consumption_history(): void
    {
        $operator = User::where('username', 'operador1')->firstOrFail();
        $shift = app(DailyOperationService::class)->ensure();
        $location = ChemicalStorageLocation::whereHas('product', fn ($query) => $query->where('stable_key', 'polymer'))->firstOrFail();
        $stock = app(ChemicalStockService::class);
        $values = ChemicalStorageLocation::where('participates_in_shift_count', true)->pluck('id')->mapWithKeys(fn ($id) => [$id => $id === $location->id ? 10 : 100])->all();
        $stock->count($shift, $operator, $values);
        $movement = $stock->issue($shift, $operator, $location, ['quantity' => 1, 'usage_location' => 'UAP', 'idempotency_key' => (string) Str::uuid()]);
        $package = ChemicalOpenPackage::firstOrFail();
        $this->assertSame('ISSUE', $movement->type);
        $this->assertSame(9.0, $stock->balance($location)['balance']);
        $this->assertSame('25.0000', $package->initial_content);
        $stock->weighOpenPackage($package, 18.4, 'Pesagem após preparo', $operator);
        $stock->weighOpenPackage($package->fresh(), 7.1, null, $operator);
        $this->assertSame(2, ChemicalOpenPackageWeighing::count());
        $this->assertEqualsWithDelta(6.6, (float) ChemicalOpenPackageWeighing::oldest()->first()->consumed, 0.0001);
        $this->assertEqualsWithDelta(11.3, (float) ChemicalOpenPackageWeighing::latest('id')->first()->consumed, 0.0001);
        $this->assertSame('7.1000', $package->fresh()->remaining_content);
    }

    public function test_inventory_becomes_physical_reference_and_requires_reason_for_divergence(): void
    {
        $master = User::where('username', 'master.teste')->firstOrFail();
        $operator = User::where('username', 'operador1')->firstOrFail();
        $shift = app(DailyOperationService::class)->ensure();
        $location = ChemicalStorageLocation::whereHas('product', fn ($query) => $query->where('stable_key', 'ferric-chloride'))->firstOrFail();
        $stock = app(ChemicalStockService::class);
        $values = ChemicalStorageLocation::where('participates_in_shift_count', true)->pluck('id')->mapWithKeys(fn ($id) => [$id => $id === $location->id ? 100 : 20])->all();
        $stock->count($shift, $operator, $values);
        try {
            $stock->inventory($shift, $master, $location, ['physical_quantity' => 91, 'idempotency_key' => (string) Str::uuid()]);
            $this->fail('Inventário divergente sem justificativa deveria falhar.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reason', $exception->errors());
        }
        $check = $stock->inventory($shift, $master, $location, ['physical_quantity' => 91, 'reason' => 'Medição física confirmada', 'idempotency_key' => (string) Str::uuid()]);
        $this->assertSame('-9.0000', $check->difference);
        $this->assertSame(91.0, $stock->balance($location)['balance']);
        $this->assertSame('INVENTORY', $stock->balance($location)['reference_type']);
        $this->assertSame(1, ChemicalInventoryCheck::count());
    }

    public function test_legacy_operator_keeps_previous_permissions_and_management_operations_are_protected(): void
    {
        $operator = User::where('username', 'operador1')->firstOrFail();
        $this->actingAs($operator)->get(route('chemical-stock.operations'))->assertForbidden();
        $this->post(route('chemical-stock.issue.store'), [])->assertForbidden();
        $this->post(route('chemical-stock.transfer.store'), [])->assertForbidden();
        $this->post(route('chemical-stock.inventory.store'), [])->assertForbidden();

        $master = User::where('username', 'master.teste')->firstOrFail();
        $shift = app(DailyOperationService::class)->ensure();
        $shift->members()->syncWithoutDetaching([$master->id => ['joined_at' => now()]]);
        $this->actingAs($master)->get(route('chemical-stock.operations'))->assertOk();
    }
}
