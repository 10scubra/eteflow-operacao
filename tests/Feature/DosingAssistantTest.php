<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DosingAlert;
use App\Models\DosingConsistencyCheck;
use App\Models\DosingCycle;
use App\Models\DosingCycleEvent;
use App\Models\DosingRule;
use App\Models\ParameterRule;
use App\Models\ReadingSection;
use App\Models\ReadingValue;
use App\Models\User;
use App\Services\DailyOperationService;
use App\Services\DosingAssistantService;
use App\Services\ReadingSectionService;
use Carbon\Carbon;
use Database\Seeders\EteFlowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DosingAssistantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-09-16 10:35:00'));
        $this->seed(EteFlowSeeder::class);
        app(DailyOperationService::class)->ensure();
    }

    public function test_initial_rule_requires_explicit_point_and_inactive_rule_does_not_open_alert(): void
    {
        $rule = $this->rule();
        $value = $this->reading(6.40);

        app(DosingAssistantService::class)->evaluate($value, $this->operator(1));

        $this->assertFalse($rule->is_active);
        $this->assertNull($rule->parameter_rule_point_id);
        $this->assertSame(0, DosingAlert::count());
        $this->actingAs(User::where('username', 'master.teste')->firstOrFail())
            ->put(route('admin.dosing.update'), $this->configurationPayload(['is_active' => 1, 'parameter_rule_point_id' => null]))
            ->assertSessionHasErrors('parameter_rule_point_id');
    }

    public function test_saving_measured_ph_through_operational_flow_opens_persistent_alert(): void
    {
        $rule = $this->activateRule();
        $section = ReadingSection::query()->where('section_key', 'biological_aerobic_lagoon')->firstOrFail();

        app(ReadingSectionService::class)->save($section, $this->operator(1), [
            'lock_version' => $section->lock_version,
            'status' => 'in_progress',
            'values' => [[
                'field_key' => 'aerobic_ph',
                'parameter_rule_id' => $rule->parameter_rule_id,
                'parameter_rule_point_id' => $rule->parameter_rule_point_id,
                'semantic_status' => 'MEASURED',
                'value_numeric' => 6.49,
            ]],
        ]);

        $this->assertSame('OPEN', DosingAlert::firstOrFail()->status);
        $this->assertSame('6.4900', DosingAlert::firstOrFail()->trigger_value);
    }

    public function test_configured_threshold_opens_at_equal_and_below_but_not_above_without_hardcode(): void
    {
        $rule = $this->activateRule(['start_value' => 7.10]);

        app(DosingAssistantService::class)->evaluate($this->reading(7.11), $this->operator(1));
        $this->assertSame(0, DosingAlert::count());

        app(DosingAssistantService::class)->evaluate($this->reading(7.10), $this->operator(1));
        $this->assertSame(1, DosingAlert::count());
        $this->assertSame($rule->id, DosingAlert::firstOrFail()->dosing_rule_id);

        DosingAlert::query()->update(['status' => 'RESOLVED_WITHOUT_DOSING', 'resolved_at' => now()]);
        app(DosingAssistantService::class)->evaluate($this->reading(7.00), $this->operator(1));
        $this->assertSame(2, DosingAlert::count());
    }

    public function test_control_point_triggers_dosing_while_average_is_only_informative(): void
    {
        $this->activateRule(['consistency_max_spread' => 0.20]);

        $values = $this->sameSectionReadings([6.49, 6.91, 6.53]);

        $alert = DosingAlert::firstOrFail();
        $check = DosingConsistencyCheck::firstOrFail();
        $this->assertSame($values[0]->id, $alert->trigger_reading_value_id);
        $this->assertSame('6.4900', $alert->trigger_value);
        $this->assertSame('6.4900', $check->minimum_value);
        $this->assertSame('6.9100', $check->maximum_value);
        $this->assertSame('6.6433', $check->average_value);
        $this->assertSame('0.4200', $check->spread_value);
        $this->assertTrue($check->is_divergent);
        $this->assertSame(['6.4900', '6.9100', '6.5300'], collect($values)->map->fresh()->pluck('value_numeric')->all());
    }

    public function test_consistency_snapshot_keeps_the_historical_limit_after_rule_changes(): void
    {
        $rule = $this->activateRule(['consistency_max_spread' => 0.10]);
        $this->sameSectionReadings([6.60, 6.72, 6.65]);
        $check = DosingConsistencyCheck::firstOrFail();

        $rule->update(['consistency_max_spread' => 0.50]);

        $this->assertSame('0.1000', $check->fresh()->configured_max_spread);
        $this->assertSame('0.1200', $check->fresh()->spread_value);
        $this->assertTrue($check->fresh()->is_divergent);
        $this->assertTrue(AuditLog::where('action', 'dosing.consistency_divergence_detected')->exists());
    }

    public function test_alert_persists_and_becomes_reminder_after_configured_time(): void
    {
        $rule = $this->activateRule(['reminder_after_minutes' => 7]);
        $value = $this->reading(6.49);
        $this->assertSame($rule->parameter_rule_id, $value->parameter_rule_id);
        $this->assertSame($rule->parameter_rule_point_id, $value->parameter_rule_point_id);
        $this->assertTrue($rule->effective_from->lte(now()), $rule->effective_from->toIso8601String().' / '.now()->toIso8601String());
        $this->assertTrue(DosingRule::query()->whereKey($rule->id)->where('is_active', true)->where('effective_from', '<=', now())->whereNull('effective_until')->exists());
        app(DosingAssistantService::class)->evaluate($value, $this->operator(1));
        $alertId = DosingAlert::firstOrFail()->id;

        $this->travel(8)->minutes();
        $this->actingAs($this->operator(1))->get(route('dosing.index'))->assertOk()->assertSee('DOSAGEM AINDA NÃO REGISTRADA');

        $this->assertSame($alertId, DosingAlert::firstOrFail()->id);
        $this->assertSame('REMINDER', DosingAlert::firstOrFail()->status);
    }

    public function test_start_is_atomic_second_operator_cannot_duplicate_and_other_operator_can_finish(): void
    {
        $this->travelBack();
        $this->activateRule();
        $assistant = app(DosingAssistantService::class);
        $assistant->evaluate($this->reading(6.49), $this->operator(1));
        $alert = DosingAlert::firstOrFail();
        $cycle = $assistant->start($alert, $this->operator(1), 35);

        try {
            $assistant->start($alert, $this->operator(2), 40);
            $this->fail('Um segundo ciclo ativo deveria ser recusado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cycle', $exception->errors());
        }

        $finished = $assistant->finish($cycle, $this->operator(2), 6.81, 'Processo verificado.');
        $finished->update(['started_at' => $finished->ended_at->copy()->subMinutes(61)]);
        $finished->refresh();
        $this->assertSame('COMPLETED', $finished->status);
        $this->assertSame($this->operator(1)->id, $finished->started_by);
        $this->assertSame($this->operator(2)->id, $finished->ended_by);
        $this->assertSame(61.0, $finished->started_at->diffInMinutes($finished->ended_at));
        $this->assertSame('6.4900', $finished->initial_value);
        $this->assertSame('6.8100', $finished->final_value);
        $this->assertNull($finished->active_lock_key);
    }

    public function test_percentage_ph_and_continue_decision_create_append_only_events(): void
    {
        $cycle = $this->activeCycle();
        $assistant = app(DosingAssistantService::class);

        $assistant->changePercentage($cycle, $this->operator(2), 42);
        $stop = $assistant->recordPh($cycle, $this->operator(2), 6.81);
        $this->assertTrue($stop);
        $this->expectException(ValidationException::class);
        $assistant->continueAfterStop($cycle, $this->operator(2), '');
    }

    public function test_continue_with_justification_preserves_percentage_and_ph_history(): void
    {
        $cycle = $this->activeCycle();
        $assistant = app(DosingAssistantService::class);
        $assistant->changePercentage($cycle, $this->operator(2), 42);
        $assistant->recordPh($cycle, $this->operator(2), 6.81);
        $assistant->continueAfterStop($cycle, $this->operator(2), 'Aguardar estabilização do processo.');

        $this->assertSame(['STARTED', 'PERCENTAGE_CHANGED', 'PH_MEASURED', 'CONTINUED_AFTER_STOP_CONDITION'], $cycle->events()->orderBy('id')->pluck('type')->all());
        $this->assertSame(['35.00', '42.00'], $cycle->events()->whereNotNull('percentage')->orderBy('id')->pluck('percentage')->all());
        $this->assertSame(['6.4900', '6.8100'], $cycle->events()->whereNotNull('ph_value')->orderBy('id')->pluck('ph_value')->all());
    }

    public function test_impediment_is_structured_and_decision_reasons_require_justification(): void
    {
        $alert = $this->openAlert();
        $assistant = app(DosingAssistantService::class);
        $assistant->reportImpediment($alert, $this->operator(1), 'PUMP_FAILURE', null);
        $this->assertSame('PUMP_FAILURE', $alert->fresh()->impediment_code);

        $decision = $this->openAlert();
        $this->expectException(ValidationException::class);
        $assistant->reportImpediment($decision, $this->operator(1), 'NOT_NEEDED_NOW', null);
    }

    public function test_condition_resolved_without_dosing_preserves_both_readings(): void
    {
        $this->activateRule();
        $assistant = app(DosingAssistantService::class);
        $trigger = $this->reading(6.49);
        $assistant->evaluate($trigger, $this->operator(1));
        $resolved = $this->reading(6.58);
        $assistant->evaluate($resolved, $this->operator(2));
        $alert = DosingAlert::firstOrFail();

        $this->assertSame('RESOLVED_WITHOUT_DOSING', $alert->status);
        $this->assertSame($trigger->id, $alert->trigger_reading_value_id);
        $this->assertSame($resolved->id, $alert->resolved_reading_value_id);
        $this->assertNotNull($alert->resolved_at);
    }

    public function test_active_cycle_survives_shift_change_and_next_operator_can_see_it(): void
    {
        $cycle = $this->activeCycle();
        $this->travelTo(Carbon::parse('2026-09-16 20:05:00'));
        $night = app(DailyOperationService::class)->ensure();

        $this->assertSame('20:00:00', $night->starts_at);
        $this->assertSame($cycle->id, app(DosingAssistantService::class)->currentState()['cycle']->id);
        $this->actingAs($this->operator(2))->get(route('dosing.index'))->assertOk()->assertSee('DOSAGEM EM ANDAMENTO');
    }

    public function test_audit_permissions_and_scope_exclusions_are_preserved(): void
    {
        $cycle = $this->activeCycle();
        app(DosingAssistantService::class)->changePercentage($cycle, $this->operator(1), 38);

        $this->assertTrue(AuditLog::whereIn('action', ['dosing.condition_detected', 'dosing.started', 'dosing.percentage_changed'])->exists());
        $this->actingAs($this->operator(1))->get(route('admin.dosing.configure'))->assertForbidden();
        $this->actingAs(User::where('username', 'master.teste')->firstOrFail())->get(route('admin.dosing.configure'))->assertOk();
        $this->assertFalse(Schema::hasTable('pump_calibrations'));
        $this->assertFalse(Schema::hasTable('chemical_stocks'));
        $this->assertFalse(Schema::hasColumn('dosing_cycles', 'consumption'));
        $this->assertSame(2, DosingCycleEvent::where('dosing_cycle_id', $cycle->id)->count());
    }

    private function activateRule(array $overrides = []): DosingRule
    {
        $rule = $this->rule();
        $rule->update(array_merge(['is_active' => true, 'parameter_rule_point_id' => $this->point()->id, 'effective_from' => now()->subMinute(), 'effective_until' => null], $overrides));

        return $rule->fresh();
    }

    private function openAlert(): DosingAlert
    {
        $this->activateRule();
        app(DosingAssistantService::class)->evaluate($this->reading(6.49), $this->operator(1));

        return DosingAlert::query()->latest('id')->firstOrFail();
    }

    private function activeCycle(): DosingCycle
    {
        $alert = $this->openAlert();

        return app(DosingAssistantService::class)->start($alert, $this->operator(1), 35);
    }

    private function reading(float $ph): ReadingValue
    {
        $source = ReadingSection::query()->where('section_key', 'biological_aerobic_lagoon')->firstOrFail();
        $section = ReadingSection::query()->create([
            'reading_round_id' => $source->reading_round_id,
            'reading_section_definition_id' => $source->reading_section_definition_id,
            'section_key' => 'dosing_test_'.uniqid(),
            'label' => 'Leitura de teste do assistente',
            'status' => 'in_progress',
        ]);

        return ReadingValue::query()->create(['reading_section_id' => $section->id, 'parameter_rule_id' => $this->parameter()->id, 'parameter_rule_point_id' => $this->point()->id, 'parameter_point_slot' => $this->point()->id, 'field_key' => 'aerobic_ph', 'semantic_status' => 'MEASURED', 'value_numeric' => $ph, 'recorded_by' => $this->operator(1)->id]);
    }

    /** @return array<int, ReadingValue> */
    private function sameSectionReadings(array $numbers): array
    {
        $source = ReadingSection::query()->where('section_key', 'biological_aerobic_lagoon')->firstOrFail();
        $section = ReadingSection::query()->create(['reading_round_id' => $source->reading_round_id, 'reading_section_definition_id' => $source->reading_section_definition_id, 'section_key' => 'consistency_test_'.uniqid(), 'label' => 'Consistência de pH', 'status' => 'in_progress']);
        $points = $this->parameter()->points()->orderBy('sort_order')->get();

        return $points->values()->map(function ($point, int $index) use ($numbers, $section): ReadingValue {
            $value = ReadingValue::query()->create(['reading_section_id' => $section->id, 'parameter_rule_id' => $this->parameter()->id, 'parameter_rule_point_id' => $point->id, 'parameter_point_slot' => $point->id, 'field_key' => 'aerobic_ph', 'semantic_status' => 'MEASURED', 'value_numeric' => $numbers[$index], 'recorded_by' => $this->operator(1)->id]);
            app(DosingAssistantService::class)->evaluate($value, $this->operator(1));

            return $value;
        })->all();
    }

    private function rule(): DosingRule
    {
        return DosingRule::query()->latest('version')->firstOrFail();
    }

    private function parameter(): ParameterRule
    {
        return $this->rule()->parameterRule;
    }

    private function point()
    {
        return $this->parameter()->points()->orderBy('sort_order')->firstOrFail();
    }

    private function operator(int $number): User
    {
        return User::where('username', 'operador'.$number)->firstOrFail();
    }

    private function configurationPayload(array $overrides = []): array
    {
        return array_merge(['parameter_rule_point_id' => $this->point()->id, 'equipment_id' => $this->rule()->equipment_id, 'start_operator' => 'LESS_THAN_OR_EQUAL', 'start_value' => 6.5, 'stop_operator' => 'GREATER_THAN_OR_EQUAL', 'stop_value' => 6.8, 'reminder_after_minutes' => 15, 'reminder_interval_minutes' => 15, 'recheck_after_minutes' => 20], $overrides);
    }
}
