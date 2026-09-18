<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\ParameterRule;
use App\Models\ReadingSection;
use App\Models\ReadingTemplate;
use App\Models\ReadingValue;
use App\Models\User;
use App\Services\DailyOperationService;
use App\Services\OperationalConfigurationService;
use App\Services\ReadingSectionService;
use Carbon\Carbon;
use Database\Seeders\EteFlowSeeder;
use Database\Seeders\SecondaryProcessReadingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SecondaryProcessReadingTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-16 10:00:00');
        $this->seed(EteFlowSeeder::class);
    }

    public function test_second_version_contains_the_four_new_sections_and_preserves_the_first_version(): void
    {
        $template = ReadingTemplate::where('stable_key', 'daily_field_monitoring_ete')->firstOrFail();
        $published = $template->versions()->where('status', 'PUBLISHED')->firstOrFail();

        $this->assertSame(2, $published->version);
        $this->assertSame('ETAPA_3C_SECONDARY_PROCESS_V1', $published->notes);
        $this->assertEqualsCanonicalizing(
            ['flotator_01', 'flotator_02', 'decanter_operation', 'operational_totalizers'],
            $published->sections()->whereIn('section_key', ['flotator_01', 'flotator_02', 'decanter_operation', 'operational_totalizers'])->pluck('section_key')->all(),
        );
        $this->assertSame(2, $template->versions()->where('notes', 'ETAPA_3B_PROCESSO_BIOLOGICO_V1')->firstOrFail()->sections()->count());
    }

    public function test_uap_commands_are_independent_percentages_linked_to_the_correct_equipment(): void
    {
        $uap1 = ParameterRule::where('field_key', 'flotator_01_uap_01_command')->firstOrFail();
        $uap2 = ParameterRule::where('field_key', 'flotator_02_uap_02_command')->firstOrFail();

        $this->assertSame('percentage', $uap1->data_type);
        $this->assertSame('%', $uap1->unit);
        $this->assertSame('percentage', $uap2->data_type);
        $this->assertSame('%', $uap2->unit);
        $this->assertSame('polymer-pump-uap-01', $uap1->equipment->code);
        $this->assertSame('polymer-pump-uap-02', $uap2->equipment->code);
        $this->assertNotSame($uap1->equipment_id, $uap2->equipment_id);
        $this->assertFalse(ParameterRule::whereIn('field_key', [$uap1->field_key, $uap2->field_key])->where('unit', 'Hz')->exists());
    }

    public function test_flotator_limits_zero_reference_and_decanter_are_configured(): void
    {
        $f1Ph = ParameterRule::where('field_key', 'flotator_01_ph')->firstOrFail();
        $f2Ph = ParameterRule::where('field_key', 'flotator_02_outlet_ph')->firstOrFail();
        $sd30 = ParameterRule::where('field_key', 'flotator_01_sd30_outlet')->firstOrFail();
        $rotation = ParameterRule::where('field_key', 'decanter_rotation')->firstOrFail();
        $startedAt = ParameterRule::where('field_key', 'decanter_started_at')->firstOrFail();

        $this->assertSame(['6.6000', '7.0000', 'BETWEEN'], [$f1Ph->minimum_value, $f1Ph->maximum_value, $f1Ph->condition_operator]);
        $this->assertSame(['6.5000', '7.0000', 'BETWEEN'], [$f2Ph->minimum_value, $f2Ph->maximum_value, $f2Ph->condition_operator]);
        $this->assertSame('0.000000', $sd30->reference_value);
        $this->assertSame('1800.000000', $rotation->reference_value);
        $this->assertSame('rpm', $rotation->unit);
        $this->assertSame('time', $startedAt->data_type);
    }

    public function test_zero_is_measured_and_equipment_stopped_completes_without_fake_numbers(): void
    {
        $operator = User::where('username', 'operador1')->firstOrFail();
        $zeroSection = ReadingSection::where('section_key', 'flotator_01')->firstOrFail();
        app(ReadingSectionService::class)->save($zeroSection, $operator, [
            'lock_version' => 0,
            'status' => 'in_progress',
            'values' => [['field_key' => 'flotator_01_sd30_outlet', 'semantic_status' => 'MEASURED', 'value_numeric' => 0]],
        ]);
        $zero = ReadingValue::where('reading_section_id', $zeroSection->id)->where('field_key', 'flotator_01_sd30_outlet')->firstOrFail();
        $this->assertSame('MEASURED', $zero->semantic_status);
        $this->assertSame('0.0000', $zero->value_numeric);

        $stoppedSection = ReadingSection::where('section_key', 'flotator_02')->firstOrFail();
        $rules = app(OperationalConfigurationService::class)->rulesForSection($stoppedSection);
        $saved = app(ReadingSectionService::class)->save($stoppedSection, $operator, [
            'lock_version' => 0,
            'status' => 'completed',
            'values' => $rules->map(fn (ParameterRule $rule): array => ['field_key' => $rule->field_key, 'parameter_rule_id' => $rule->id, 'semantic_status' => 'EQUIPMENT_STOPPED'])->values()->all(),
        ]);
        $this->assertSame('completed', $saved->status);
        $this->assertTrue($saved->values->every(fn (ReadingValue $value): bool => $value->value_numeric === null));
    }

    public function test_totalizer_specific_times_include_midnight_in_the_previous_night_shift(): void
    {
        $day = app(DailyOperationService::class)->ensure(Carbon::parse('2026-09-16 10:00:00'));
        $this->assertTrue($day->rounds()->whereTime('scheduled_at', '09:00:00')->exists());
        $this->assertTrue($day->rounds()->whereTime('scheduled_at', '12:00:00')->exists());

        $night = app(DailyOperationService::class)->ensure(Carbon::parse('2026-09-16 21:30:00'));
        $times = $night->rounds()->orderBy('scheduled_at')->get()->map->scheduled_at->map->format('H:i')->all();
        $this->assertContains('21:00', $times);
        $this->assertContains('00:00', $times);
        $midnight = $night->rounds()->whereTime('scheduled_at', '00:00:00')->firstOrFail();
        $this->assertSame('2026-09-17', $midnight->scheduled_at->format('Y-m-d'));
        $this->assertSame('2026-09-16', $midnight->shift->shift_date->format('Y-m-d'));
        $this->assertSame(6, $midnight->sections()->where('section_key', 'operational_totalizers')->firstOrFail()->definition->parameterRules()->count());
    }

    public function test_totalizer_stores_only_raw_value_without_delta_or_consumption(): void
    {
        $operator = User::where('username', 'operador1')->firstOrFail();
        $section = ReadingSection::where('section_key', 'operational_totalizers')->firstOrFail();
        $rule = app(OperationalConfigurationService::class)->rulesForSection($section)->firstOrFail();
        app(ReadingSectionService::class)->save($section, $operator, [
            'lock_version' => 0,
            'status' => 'in_progress',
            'values' => [['field_key' => $rule->field_key, 'parameter_rule_id' => $rule->id, 'semantic_status' => 'MEASURED', 'value_numeric' => 154275]],
        ]);
        $value = ReadingValue::where('reading_section_id', $section->id)->firstOrFail();
        $this->assertSame('154275.0000', $value->value_numeric);
        $this->assertSame('totalizer', $value->definition_snapshot['data_type']);
        $this->assertArrayNotHasKey('delta', $value->definition_snapshot);
        $this->assertArrayNotHasKey('consumption', $value->definition_snapshot);
    }

    public function test_seeder_is_idempotent_and_calibration_domain_was_not_started(): void
    {
        $before = [Equipment::count(), ReadingTemplate::firstOrFail()->versions()->count(), ParameterRule::count()];
        $this->seed(SecondaryProcessReadingSeeder::class);
        $this->assertSame($before, [Equipment::count(), ReadingTemplate::firstOrFail()->versions()->count(), ParameterRule::count()]);
        $this->assertFalse(Schema::hasTable('pump_calibrations'));
        $this->assertFalse(Schema::hasTable('calibration_points'));
    }

    public function test_mobile_rendering_does_not_require_horizontal_table(): void
    {
        $operator = User::where('username', 'operador1')->firstOrFail();
        $this->actingAs($operator)
            ->withHeader('User-Agent', 'Mozilla/5.0 (Linux; Android 14; Mobile)')
            ->get(route('readings'))
            ->assertOk()
            ->assertDontSee('<table', false);
    }
}
