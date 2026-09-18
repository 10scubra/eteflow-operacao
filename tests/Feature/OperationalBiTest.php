<?php

namespace Tests\Feature;

use App\Models\ChemicalProduct;
use App\Models\ChemicalProductPrice;
use App\Models\ChemicalUnit;
use App\Models\DashboardPage;
use App\Models\Indicator;
use App\Models\LaboratoryParameter;
use App\Models\LaboratoryResult;
use App\Models\OperationalUnit;
use App\Models\ParameterRule;
use App\Models\ReadingRound;
use App\Models\ReadingValue;
use App\Models\Role;
use App\Models\SamplingPoint;
use App\Models\User;
use App\Services\IndicatorDataService;
use App\Services\PublicDashboardService;
use Carbon\Carbon;
use Database\Seeders\EteFlowSeeder;
use Database\Seeders\OperationalBiSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OperationalBiTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-18 10:00:00');
        $this->seed(EteFlowSeeder::class);
        $this->seed(OperationalBiSeeder::class);
    }

    public function test_chemist_registers_multiple_results_and_correction_preserves_original(): void
    {
        $unit = OperationalUnit::firstOrFail();
        $chemist = User::factory()->create(['role' => 'operator', 'role_id' => Role::where('code', 'chemist')->value('id'), 'is_active' => true]);
        $chemist->operationalUnits()->attach($unit->id, ['is_default' => true]);
        $point = SamplingPoint::where('operational_unit_id', $unit->id)->firstOrFail();
        $parameters = LaboratoryParameter::where('operational_unit_id', $unit->id)->take(2)->get();
        $resultsBefore = LaboratoryResult::count();

        $this->actingAs($chemist)->post(route('laboratory.store'), [
            'operational_unit_id' => $unit->id, 'sampling_point_id' => $point->id, 'origin_type' => 'INTERNAL',
            'collected_at' => '2026-09-18 09:30:00', 'results' => $parameters->mapWithKeys(fn ($parameter, $index) => [$parameter->id => 20 + $index])->all(),
        ])->assertRedirect();

        $this->assertSame($resultsBefore + 2, LaboratoryResult::count());
        $result = LaboratoryResult::where('created_by', $chemist->id)->firstOrFail();
        $this->assertSame($chemist->id, $result->created_by);
        $original = $result->result_value;
        $this->post(route('laboratory.results.correct', $result), ['corrected_value' => 18.5, 'reason' => 'Erro de digitação'])->assertRedirect();
        $this->assertSame($original, $result->fresh()->result_value);
        $this->assertSame(18.5, $result->fresh()->effectiveValue());
        $this->assertDatabaseHas('audit_logs', ['action' => 'laboratory.result_corrected']);
    }

    public function test_indicator_reuses_measured_operational_values_and_never_turns_not_measured_into_zero(): void
    {
        $unit = OperationalUnit::firstOrFail();
        $rule = ParameterRule::whereIn('data_type', ['decimal', 'integer', 'percentage', 'totalizer'])->firstOrFail();
        $round = ReadingRound::with('shift')->firstOrFail();
        $round->shift->update(['operational_unit_id' => $unit->id]);
        $section = $round->sections()->firstOrFail();
        ReadingValue::create(['reading_section_id' => $section->id, 'parameter_rule_id' => $rule->id, 'field_key' => $rule->field_key, 'semantic_status' => 'MEASURED', 'value_numeric' => 0, 'unit' => $rule->unit, 'recorded_by' => User::first()->id]);
        ReadingValue::create(['reading_section_id' => $section->id, 'parameter_rule_id' => $rule->id, 'parameter_point_slot' => 999999, 'field_key' => $rule->field_key, 'semantic_status' => 'NOT_MEASURED', 'value_numeric' => null, 'unit' => $rule->unit, 'recorded_by' => User::first()->id]);
        $indicator = Indicator::create(['operational_unit_id' => $unit->id, 'dashboard_page_id' => DashboardPage::first()->id, 'stable_key' => 'test_zero', 'name' => 'Teste zero', 'source_type' => 'OPERATIONAL_READING', 'parameter_rule_id' => $rule->id, 'aggregation' => 'LATEST', 'visualization' => 'KPI', 'unit' => $rule->unit, 'is_active' => true, 'is_shareable' => true]);

        $data = app(IndicatorDataService::class)->data($indicator, now()->subDay(), now()->addDay());
        $this->assertSame(1, count($data['series']));
        $this->assertSame(0.0, $data['value']);
        $this->assertSame('READY', $data['state']);
    }

    public function test_public_link_is_scoped_read_only_and_revocation_blocks_it(): void
    {
        $unit = OperationalUnit::firstOrFail();
        $master = User::where('username', 'master.teste')->firstOrFail();
        $page = DashboardPage::where('operational_unit_id', $unit->id)->firstOrFail();
        $created = app(PublicDashboardService::class)->create($unit, $master, ['name' => 'Teste Diretoria', 'page_ids' => [$page->id], 'allow_export' => false]);
        $token = $created['token'];

        $this->get(route('public.indicators.show', $token))->assertOk()->assertSee('Teste Diretoria')->assertDontSee($master->email);
        $this->post(route('laboratory.store'), [])->assertRedirect(route('login'));
        app(PublicDashboardService::class)->revoke($created['share'], $master);
        $this->get(route('public.indicators.show', $token))->assertNotFound();
        $this->get(route('public.indicators.show', 'invalid-token'))->assertNotFound();
    }

    public function test_units_are_isolated_for_internal_and_public_access(): void
    {
        $unitA = OperationalUnit::firstOrFail();
        $unitB = OperationalUnit::create(['stable_key' => 'unit_b', 'name' => 'Unidade B', 'timezone' => 'America/Sao_Paulo', 'is_active' => true]);
        $user = User::factory()->create(['role' => 'viewer', 'is_active' => true]);
        $user->operationalUnits()->attach($unitA->id, ['is_default' => true]);

        $this->actingAs($user)->get(route('indicators.index', ['unit' => $unitB->id]))->assertForbidden();
        $pageB = DashboardPage::create(['operational_unit_id' => $unitB->id, 'stable_key' => 'geral_b', 'name' => 'Geral B', 'slug' => 'geral-b', 'sort_order' => 1, 'is_active' => true, 'is_shareable' => true]);
        $master = User::where('username', 'master.teste')->firstOrFail();
        $created = app(PublicDashboardService::class)->create($unitA, $master, ['name' => 'Somente A', 'page_ids' => [$pageB->id]]);
        $this->assertFalse($created['share']->pages()->whereKey($pageB->id)->exists());
    }

    public function test_price_history_keeps_independent_validity_records(): void
    {
        $unit = OperationalUnit::firstOrFail();
        $master = User::where('username', 'master.teste')->firstOrFail();
        $product = ChemicalProduct::firstOrFail();
        $measure = ChemicalUnit::findOrFail($product->unit_id);
        ChemicalProductPrice::create(['operational_unit_id' => $unit->id, 'chemical_product_id' => $product->id, 'price' => 10, 'unit_id' => $measure->id, 'currency' => 'BRL', 'effective_from' => '2026-08-01', 'effective_until' => '2026-08-31 23:59:59', 'created_by' => $master->id]);
        ChemicalProductPrice::create(['operational_unit_id' => $unit->id, 'chemical_product_id' => $product->id, 'price' => 12, 'unit_id' => $measure->id, 'currency' => 'BRL', 'effective_from' => '2026-09-01', 'created_by' => $master->id]);
        $this->assertSame(2, ChemicalProductPrice::where('chemical_product_id', $product->id)->count());
        $this->assertSame('10.000000', ChemicalProductPrice::oldest('effective_from')->first()->price);
    }
}
