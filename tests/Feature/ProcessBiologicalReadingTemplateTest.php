<?php

namespace Tests\Feature;

use App\Models\ParameterRule;
use App\Models\ReadingSection;
use App\Models\ReadingTemplate;
use App\Models\ReadingValue;
use App\Models\User;
use App\Services\ReadingSectionService;
use Database\Seeders\EteFlowSeeder;
use Database\Seeders\ProcessBiologicalReadingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProcessBiologicalReadingTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EteFlowSeeder::class);
    }

    public function test_original_biological_version_is_preserved_with_exactly_two_sections(): void
    {
        $template = ReadingTemplate::where('stable_key', 'daily_field_monitoring_ete')->firstOrFail();
        $version = $template->versions()->where('notes', 'ETAPA_3B_PROCESSO_BIOLOGICO_V1')->firstOrFail();

        $this->assertTrue($template->is_active);
        $this->assertTrue($template->is_default);
        $this->assertSame([
            'biological_anoxic_lagoon',
            'biological_aerobic_lagoon',
        ], $version->sections()->orderBy('sort_order')->pluck('section_key')->all());
    }

    public function test_anoxic_parameters_preserve_operational_units_and_limits(): void
    {
        $rules = ParameterRule::where('section_key', 'biological_anoxic_lagoon')->get()->keyBy('field_key');

        $this->assertSame('m³/h', $rules['anoxic_inlet_flow']->unit);
        $this->assertSame('15.000000', $rules['anoxic_inlet_flow']->reference_value);
        $this->assertSame('%', $rules['feed_pump_command']->unit);
        $this->assertSame('0.0000', $rules['feed_pump_command']->minimum_value);
        $this->assertSame('100.0000', $rules['feed_pump_command']->maximum_value);
        $this->assertSame('LESS_THAN', $rules['anoxic_dissolved_oxygen']->condition_operator);
        $this->assertSame('1.0000', $rules['anoxic_dissolved_oxygen']->maximum_value);
        $this->assertFalse($rules->contains(fn (ParameterRule $rule) => $rule->unit === 'Hz'));
    }

    public function test_aerobic_parameters_have_required_measurement_points_and_conditional_product(): void
    {
        $rules = ParameterRule::where('section_key', 'biological_aerobic_lagoon')->with('points')->get()->keyBy('field_key');

        $this->assertCount(3, $rules['aerobic_ph']->points);
        $this->assertCount(4, $rules['aerobic_dissolved_oxygen']->points);
        $this->assertCount(3, $rules['aerobic_sd30']->points);
        $this->assertSame([], $rules['alkalizer_product']->options);
        $this->assertSame([
            'field_key' => 'alkalizer_dosage',
            'operator' => 'EQUALS',
            'value' => true,
        ], $rules['alkalizer_product']->visibility_config);
    }

    public function test_bootstrap_is_idempotent(): void
    {
        $before = [
            ReadingTemplate::count(),
            ParameterRule::whereIn('section_key', ['biological_anoxic_lagoon', 'biological_aerobic_lagoon'])->count(),
        ];

        $this->seed(ProcessBiologicalReadingSeeder::class);

        $this->assertSame($before, [
            ReadingTemplate::count(),
            ParameterRule::whereIn('section_key', ['biological_anoxic_lagoon', 'biological_aerobic_lagoon'])->count(),
        ]);
    }

    public function test_draft_accepts_incomplete_values_but_completion_validates_required_fields(): void
    {
        $operator = User::where('username', 'operador1')->firstOrFail();
        $section = ReadingSection::where('section_key', 'biological_anoxic_lagoon')->firstOrFail();

        $saved = app(ReadingSectionService::class)->save($section, $operator, [
            'lock_version' => $section->lock_version,
            'status' => 'in_progress',
            'values' => [['field_key' => 'anoxic_ph', 'value_numeric' => 6.9]],
        ]);
        $this->assertSame('in_progress', $saved->status);

        $this->expectException(ValidationException::class);
        app(ReadingSectionService::class)->save($saved, $operator, [
            'lock_version' => $saved->lock_version,
            'status' => 'completed',
            'values' => [['field_key' => 'anoxic_ph', 'value_numeric' => 6.9]],
        ]);
    }

    public function test_out_of_condition_is_saved_and_reference_is_informational(): void
    {
        $operator = User::where('username', 'operador1')->firstOrFail();
        $section = ReadingSection::where('section_key', 'biological_anoxic_lagoon')->firstOrFail();

        app(ReadingSectionService::class)->save($section, $operator, [
            'lock_version' => $section->lock_version,
            'status' => 'in_progress',
            'values' => [
                ['field_key' => 'anoxic_ph', 'semantic_status' => 'MEASURED', 'value_numeric' => 8],
                ['field_key' => 'anoxic_inlet_flow', 'semantic_status' => 'MEASURED', 'value_numeric' => 14],
            ],
        ]);

        $this->assertTrue(ReadingValue::where('field_key', 'anoxic_ph')->firstOrFail()->is_out_of_range);
        $this->assertFalse(ReadingValue::where('field_key', 'anoxic_inlet_flow')->firstOrFail()->is_out_of_range);
    }

    public function test_readings_page_uses_dynamic_template_and_employee_header(): void
    {
        $operator = User::where('username', 'operador1')->firstOrFail();

        $this->actingAs($operator)
            ->get(route('readings'))
            ->assertOk()
            ->assertSee('Monitoramento Diário de Campo — ETE')
            ->assertSee($operator->employee?->display_name ?: $operator->name);
    }

    public function test_two_operators_save_different_sections_with_individual_authorship(): void
    {
        $first = User::where('username', 'operador1')->firstOrFail();
        $second = User::where('username', 'operador2')->firstOrFail();
        $anoxic = ReadingSection::where('section_key', 'biological_anoxic_lagoon')->firstOrFail();
        $aerobic = ReadingSection::where('section_key', 'biological_aerobic_lagoon')->firstOrFail();
        $service = app(ReadingSectionService::class);

        $service->save($anoxic, $first, [
            'lock_version' => 0,
            'status' => 'in_progress',
            'values' => [['field_key' => 'anoxic_ph', 'value_numeric' => 6.9]],
        ]);
        $service->save($aerobic, $second, [
            'lock_version' => 0,
            'status' => 'in_progress',
            'values' => [['field_key' => 'aerobic_level', 'value_numeric' => 2.4]],
        ]);

        $this->assertSame($first->id, ReadingValue::where('reading_section_id', $anoxic->id)->firstOrFail()->recorded_by);
        $this->assertSame($second->id, ReadingValue::where('reading_section_id', $aerobic->id)->firstOrFail()->recorded_by);
    }

    public function test_mobile_reading_response_does_not_require_horizontal_table(): void
    {
        $operator = User::where('username', 'operador1')->firstOrFail();

        $this->actingAs($operator)
            ->withHeader('User-Agent', 'Mozilla/5.0 (Linux; Android 14; Mobile)')
            ->get(route('readings'))
            ->assertOk()
            ->assertDontSee('<table', false)
            ->assertSee('reading-panel');
    }

    public function test_preview_does_not_persist_operational_values(): void
    {
        $master = User::where('username', 'master.teste')->firstOrFail();
        $version = ReadingTemplate::where('stable_key', 'daily_field_monitoring_ete')->firstOrFail()->versions()->firstOrFail();
        $before = ReadingValue::count();

        $this->actingAs($master)
            ->get(route('admin.reading-templates.preview', $version))
            ->assertOk()
            ->assertSee('biological_anoxic_lagoon')
            ->assertSee('biological_aerobic_lagoon');

        $this->assertSame($before, ReadingValue::count());
    }
}
