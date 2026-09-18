<?php

namespace Tests\Feature;

use App\Models\ReadingRound;
use App\Models\ReadingSection;
use App\Models\ReadingTemplate;
use App\Models\ReadingTemplateVersion;
use App\Models\ReadingValue;
use App\Models\User;
use App\Services\ReadingTemplateService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ReadingTemplateBuilderTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_master_creates_model_and_draft_version(): void
    {
        $response = $this->actingAs(User::factory()->create(['role' => 'master', 'is_active' => true]))->post(route('admin.reading-templates.store'), ['stable_key' => 'operacional', 'name' => 'Ficha operacional']);
        $template = ReadingTemplate::where('stable_key', 'operacional')->firstOrFail();
        $response->assertRedirect(route('admin.reading-templates.edit', $template->versions()->firstOrFail()));
        $this->assertDatabaseHas('reading_template_versions', ['reading_template_id' => $template->id, 'status' => 'DRAFT']);
    }

    public function test_operator_is_forbidden_from_builder_backend(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'operator', 'is_active' => true]))->get(route('admin.reading-templates.index'))->assertForbidden();
    }

    public function test_published_version_rejects_direct_structural_request(): void
    {
        [$user,$version] = $this->definition();
        $version->update(['status' => 'PUBLISHED', 'published_at' => now()]);
        $this->actingAs($user)->post(route('admin.reading-templates.sections.store', $version), ['section_key' => 'other', 'label' => 'Outra', 'sort_order' => 2])->assertSessionHasErrors('version');
        $this->assertDatabaseMissing('reading_section_definitions', ['reading_template_version_id' => $version->id, 'section_key' => 'other']);
    }

    public function test_duplicate_creates_independent_definitions(): void
    {
        [$user,$version,$section,$rule] = $this->definition(true);
        $copy = app(ReadingTemplateService::class)->duplicate($version, $user);
        $copiedRule = $copy->sections->first()->parameterRules->first();
        $this->assertNotSame($section->id, $copy->sections->first()->id);
        $this->assertNotSame($rule->id, $copiedRule->id);
        $this->assertNotSame($rule->points->first()->id, $copiedRule->points->first()->id);
        $copiedRule->update(['label' => 'Alterado']);
        $this->assertSame('pH', $rule->fresh()->label);
    }

    public function test_adding_point_to_new_version_does_not_change_old_version(): void
    {
        [$user,$version,,$rule] = $this->definition(true);
        $copy = app(ReadingTemplateService::class)->duplicate($version, $user);
        $copy->sections->first()->parameterRules->first()->points()->create(['stable_key' => 'p2', 'label' => 'Ponto 2', 'sort_order' => 2, 'is_active' => true]);
        $this->assertSame(1, $rule->points()->count());
        $this->assertSame(2, $copy->sections->first()->parameterRules->first()->points()->count());
    }

    public function test_preview_uses_definitions_without_persisting_operation(): void
    {
        [$user,$version] = $this->definition();
        $before = [ReadingRound::count(), ReadingSection::count(), ReadingValue::count()];
        $this->actingAs($user)->get(route('admin.reading-templates.preview', $version))->assertOk()->assertSee('definition-preview')->assertSee('PREVIEW SEM PERSISTÊNCIA');
        $this->assertSame($before, [ReadingRound::count(), ReadingSection::count(), ReadingValue::count()]);
    }

    public function test_master_can_edit_parameter_options_and_more_than_six_points_only_in_draft(): void
    {
        [$user, $version, , $rule] = $this->definition(true);
        $payload = [
            'field_key' => 'ph',
            'label' => 'pH atualizado',
            'data_type' => 'single_select',
            'decimal_places' => 2,
            'sort_order' => 1,
            'condition_operator' => 'REFERENCE',
            'frequency_type' => 'EVERY_ROUND',
            'options_text' => "Opção A\nOpção B",
            'is_required' => '1',
            'points' => collect(range(1, 8))->map(fn (int $point): array => [
                'stable_key' => 'p'.$point,
                'label' => 'Ponto '.$point,
                'sort_order' => $point,
            ])->all(),
        ];

        $this->actingAs($user)
            ->put(route('admin.reading-templates.parameters.update', $rule), $payload)
            ->assertRedirect();

        $this->assertSame(['Opção A', 'Opção B'], $rule->fresh()->options);
        $this->assertSame(8, $rule->points()->where('is_active', true)->count());

        $version->update(['status' => ReadingTemplateVersion::Published, 'published_at' => now()]);
        $this->actingAs($user)
            ->put(route('admin.reading-templates.parameters.update', $rule), $payload + ['label' => 'Não deve alterar'])
            ->assertSessionHasErrors('version');
        $this->assertSame('pH atualizado', $rule->fresh()->label);
    }

    private function definition(bool $point = false): array
    {
        $user = User::factory()->create(['role' => 'master', 'is_active' => true]);
        $template = ReadingTemplate::create(['stable_key' => fake()->unique()->slug(), 'name' => 'Modelo', 'is_active' => true]);
        $version = $template->versions()->create(['version' => 1, 'status' => ReadingTemplateVersion::Draft, 'created_by' => $user->id]);
        $section = $version->sections()->create(['section_key' => 'process', 'label' => 'Processo', 'sort_order' => 1, 'version' => 1, 'is_active' => true]);
        $rule = $section->parameterRules()->create(['section_key' => 'process', 'field_key' => 'ph', 'label' => 'pH', 'data_type' => 'decimal', 'sort_order' => 1, 'condition_operator' => 'BETWEEN', 'frequency_type' => 'EVERY_ROUND', 'frequency_config' => [], 'is_required' => true, 'is_active' => true, 'version' => 1, 'effective_from' => now()]);
        if ($point) {
            $rule->points()->create(['stable_key' => 'p1', 'label' => 'Ponto 1', 'sort_order' => 1, 'is_active' => true]);
            $rule->load('points');
        }

        return [$user, $version, $section, $rule];
    }
}
