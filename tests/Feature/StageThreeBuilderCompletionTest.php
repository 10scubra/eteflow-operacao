<?php

namespace Tests\Feature;

use App\Models\ParameterRule;
use App\Models\ReadingRound;
use App\Models\ReadingSection;
use App\Models\ReadingSectionDefinition;
use App\Models\ReadingTemplate;
use App\Models\ReadingTemplateVersion;
use App\Models\ReadingValue;
use App\Models\ReadingValueRevision;
use App\Models\Shift;
use App\Models\User;
use App\Services\DailyOperationService;
use App\Services\ReadingSectionService;
use App\Services\ReadingTemplateService;
use App\Support\ReadingDefinitionLabels;
use Carbon\Carbon;
use Database\Seeders\EteFlowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StageThreeBuilderCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EteFlowSeeder::class);
    }

    public function test_section_can_be_edited_and_reordered_only_in_draft(): void
    {
        $master = User::where('username', 'master.teste')->firstOrFail();
        $published = ReadingTemplate::where('stable_key', 'daily_field_monitoring_ete')->firstOrFail()->versions()->firstOrFail();
        $draft = app(ReadingTemplateService::class)->duplicate($published, $master);
        $section = $draft->sections()->orderBy('sort_order')->firstOrFail();

        $this->actingAs($master)->put(route('admin.reading-templates.sections.update', $section), [
            'section_key' => $section->section_key,
            'label' => 'Seção reordenada',
            'description' => 'Descrição editada.',
            'sort_order' => 9,
            'is_active' => '0',
        ])->assertRedirect();

        $this->assertSame('Seção reordenada', $section->fresh()->label);
        $this->assertSame(9, $section->fresh()->sort_order);
        $this->assertFalse($section->fresh()->is_active);

        $publishedSection = $published->sections()->firstOrFail();
        $this->actingAs($master)->put(route('admin.reading-templates.sections.update', $publishedSection), [
            'section_key' => $publishedSection->section_key,
            'label' => 'Alteração proibida',
            'sort_order' => 1,
            'is_active' => '1',
        ])->assertSessionHasErrors('version');
        $this->assertNotSame('Alteração proibida', $publishedSection->fresh()->label);
    }

    public function test_conditional_required_rule_is_enforced_only_when_applicable(): void
    {
        [$section, $operator, $controller, $dependent] = $this->conditionalContext();
        $service = app(ReadingSectionService::class);

        $saved = $service->save($section, $operator, [
            'lock_version' => 0,
            'status' => 'completed',
            'values' => [[
                'field_key' => $controller->field_key,
                'parameter_rule_id' => $controller->id,
                'value_boolean' => false,
                'semantic_status' => 'MEASURED',
            ]],
        ]);
        $this->assertSame('completed', $saved->status);

        $otherRound = ReadingRound::create([
            'shift_id' => $section->round->shift_id,
            'reading_template_version_id' => $section->round->reading_template_version_id,
            'scheduled_at' => $section->round->scheduled_at->copy()->addHours(2),
            'status' => 'pending',
        ]);
        $other = ReadingSection::create([
            'reading_round_id' => $otherRound->id,
            'reading_section_definition_id' => $section->reading_section_definition_id,
            'section_key' => $section->section_key,
            'label' => 'Teste condicional 2',
            'status' => 'pending',
        ]);
        $this->expectException(ValidationException::class);
        $service->save($other, $operator, [
            'lock_version' => 0,
            'status' => 'completed',
            'values' => [[
                'field_key' => $controller->field_key,
                'parameter_rule_id' => $controller->id,
                'value_boolean' => true,
                'semantic_status' => 'MEASURED',
            ]],
        ]);
    }

    public function test_controller_change_preserves_dependent_value_and_records_not_applicable_revision(): void
    {
        [$section, $operator, $controller, $dependent] = $this->conditionalContext();
        $service = app(ReadingSectionService::class);
        $saved = $service->save($section, $operator, [
            'lock_version' => 0,
            'status' => 'completed',
            'values' => [
                ['field_key' => $controller->field_key, 'parameter_rule_id' => $controller->id, 'value_boolean' => true, 'semantic_status' => 'MEASURED'],
                ['field_key' => $dependent->field_key, 'parameter_rule_id' => $dependent->id, 'value_text' => 'Produto confirmado', 'semantic_status' => 'MEASURED'],
            ],
        ]);
        $service->save($saved, $operator, [
            'lock_version' => $saved->lock_version,
            'status' => 'completed',
            'values' => [
                ['field_key' => $controller->field_key, 'parameter_rule_id' => $controller->id, 'value_boolean' => false, 'semantic_status' => 'MEASURED'],
            ],
        ]);

        $value = ReadingValue::where('reading_section_id', $section->id)->where('parameter_rule_id', $dependent->id)->firstOrFail();
        $this->assertSame('NOT_APPLICABLE', $value->semantic_status);
        $this->assertSame('Produto confirmado', $value->value_text);
        $this->assertGreaterThanOrEqual(2, ReadingValueRevision::where('reading_value_id', $value->id)->count());
    }

    public function test_portuguese_labels_are_centralized(): void
    {
        $this->assertSame('Entre', ReadingDefinitionLabels::CONDITIONS['BETWEEN']);
        $this->assertSame('Todas as rodadas', ReadingDefinitionLabels::FREQUENCIES['EVERY_ROUND']);
        $this->assertSame('Leitura não realizada', ReadingDefinitionLabels::SEMANTIC_STATUSES['NOT_MEASURED']);
    }

    public function test_night_rounds_after_midnight_keep_previous_operational_date_and_render(): void
    {
        Carbon::setTestNow('2026-09-16 01:00:00');
        $shift = app(DailyOperationService::class)->ensure();
        $operator = User::where('username', 'operador1')->firstOrFail();

        $this->assertSame('2026-09-15', $shift->shift_date->format('Y-m-d'));
        $this->assertSame(['20:30', '21:00', '22:30', '00:00', '00:30', '02:30', '04:30', '06:30'], $shift->rounds()->orderBy('scheduled_at')->get()->map->scheduled_at->map->format('H:i')->all());
        $this->actingAs($operator)->get(route('readings'))
            ->assertOk()
            ->assertSeeInOrder(['20:30', '21:00', '22:30', '00:00', '00:30', '02:30', '04:30', '06:30']);
    }

    private function conditionalContext(): array
    {
        $operator = User::where('username', 'operador1')->firstOrFail();
        $shift = Shift::create(['shift_date' => today()->addDays(10), 'starts_at' => '08:00:00', 'ends_at' => '20:00:00', 'status' => 'active']);
        $shift->members()->attach($operator->id, ['joined_at' => now()]);
        $template = ReadingTemplate::create(['stable_key' => 'conditional_test', 'name' => 'Condicional', 'is_active' => true]);
        $version = $template->versions()->create(['version' => 1, 'status' => ReadingTemplateVersion::Published, 'created_by' => $operator->id, 'published_at' => now()]);
        $definition = ReadingSectionDefinition::create(['reading_template_version_id' => $version->id, 'section_key' => 'conditional', 'label' => 'Condicional', 'sort_order' => 1, 'version' => 1, 'is_active' => true]);
        $controller = ParameterRule::create(['reading_section_definition_id' => $definition->id, 'section_key' => 'conditional', 'field_key' => 'enabled', 'label' => 'Habilitado?', 'data_type' => 'boolean', 'sort_order' => 1, 'condition_operator' => 'REFERENCE', 'frequency_type' => 'EVERY_ROUND', 'frequency_config' => [], 'is_required' => true, 'is_active' => true, 'version' => 1, 'effective_from' => now()]);
        $dependent = ParameterRule::create(['reading_section_definition_id' => $definition->id, 'section_key' => 'conditional', 'field_key' => 'choice', 'label' => 'Escolha', 'data_type' => 'single_select', 'options' => ['Produto confirmado'], 'sort_order' => 2, 'condition_operator' => 'REFERENCE', 'frequency_type' => 'EVERY_ROUND', 'frequency_config' => [], 'visibility_config' => ['field_key' => 'enabled', 'operator' => 'EQUALS', 'value' => true], 'is_required' => true, 'is_active' => true, 'version' => 1, 'effective_from' => now()]);
        $round = ReadingRound::create([
            'shift_id' => $shift->id,
            'reading_template_version_id' => $version->id,
            'scheduled_at' => $shift->shift_date->copy()->setTime(8, 30),
            'status' => 'pending',
        ]);
        $section = ReadingSection::create(['reading_round_id' => $round->id, 'reading_section_definition_id' => $definition->id, 'section_key' => 'conditional', 'label' => 'Condicional', 'status' => 'pending']);

        return [$section, $operator, $controller, $dependent];
    }
}
