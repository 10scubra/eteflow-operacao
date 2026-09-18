<?php

namespace Tests\Feature;

use App\Models\ParameterRule;
use App\Models\ReadingSection;
use App\Models\User;
use App\Services\DailyOperationService;
use App\Services\OperationalConfigurationService;
use Carbon\Carbon;
use Database\Seeders\EteFlowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalMvpFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_operators_complete_different_sections_and_master_sees_authorship_and_progress(): void
    {
        $this->travelTo(Carbon::parse('2026-09-16 10:35:00'));
        $this->seed(EteFlowSeeder::class);
        $shift = app(DailyOperationService::class)->ensure();
        $round = $shift->rounds()->whereTime('scheduled_at', '10:30:00')->firstOrFail();
        $anoxic = $round->sections()->where('section_key', 'biological_anoxic_lagoon')->firstOrFail();
        $flotator = $round->sections()->where('section_key', 'flotator_01')->firstOrFail();

        $this->post(route('login.store'), ['username' => 'operador1', 'password' => 'Operador1@2026'])
            ->assertRedirect(route('operation.home'));
        $this->get(route('readings', ['round' => $round->id]))->assertOk()->assertSee('10:30');
        $this->postJson('/api/reading-sections/'.$anoxic->id.'/open')->assertOk();
        $this->putJson('/api/reading-sections/'.$anoxic->id, [
            'lock_version' => 0,
            'status' => 'in_progress',
            'values' => [$this->valueFor(app(OperationalConfigurationService::class)->rulesForSection($anoxic)->firstOrFail())],
        ])->assertOk()->assertJsonPath('section.status', 'in_progress');
        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->post(route('login.store'), ['username' => 'operador2', 'password' => 'Operador2@2026'])
            ->assertRedirect(route('operation.home'));
        $this->postJson('/api/reading-sections/'.$flotator->id.'/open')->assertOk();
        $this->putJson('/api/reading-sections/'.$flotator->id, [
            'lock_version' => 0,
            'status' => 'completed',
            'values' => $this->completeValues($flotator),
        ])->assertOk()->assertJsonPath('section.status', 'completed');
        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->post(route('login.store'), ['username' => 'operador1', 'password' => 'Operador1@2026'])
            ->assertRedirect(route('operation.home'));
        $this->putJson('/api/reading-sections/'.$anoxic->id, [
            'lock_version' => 1,
            'status' => 'completed',
            'values' => $this->completeValues($anoxic),
        ])->assertOk()->assertJsonPath('section.status', 'completed');
        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->post(route('login.store'), ['username' => 'master.teste', 'password' => 'Master@2026'])
            ->assertRedirect(route('master'));
        $this->get(route('master'))->assertOk()->assertSee('Visão rápida da operação');
        $snapshot = $this->getJson('/api/operation-snapshot')->assertOk()->json();
        $roundSnapshot = collect($snapshot['rounds'])->firstWhere('id', $round->id);
        $sections = collect($roundSnapshot['sections']);

        $this->assertSame('completed', $sections->firstWhere('id', $anoxic->id)['status']);
        $this->assertSame('Operador 1', $sections->firstWhere('id', $anoxic->id)['last_editor']);
        $this->assertSame('completed', $sections->firstWhere('id', $flotator->id)['status']);
        $this->assertSame('Operador 2', $sections->firstWhere('id', $flotator->id)['last_editor']);
        $this->assertSame(2, $roundSnapshot['completed']);
        $this->assertSame('Operador 1', $anoxic->fresh()->completedBy->name);
        $this->assertSame('Operador 2', $flotator->fresh()->completedBy->name);
        $this->assertTrue($anoxic->values()->where('recorded_by', User::where('username', 'operador1')->value('id'))->exists());
        $this->assertTrue($flotator->values()->where('recorded_by', User::where('username', 'operador2')->value('id'))->exists());
    }

    public function test_night_shift_keeps_midnight_totalizers_in_the_previous_operational_date(): void
    {
        $this->travelTo(Carbon::parse('2026-09-17 00:05:00'));
        $this->seed(EteFlowSeeder::class);

        $shift = app(DailyOperationService::class)->ensure();
        $midnight = $shift->rounds()->whereTime('scheduled_at', '00:00:00')->firstOrFail();

        $this->assertSame('2026-09-16', $shift->shift_date->format('Y-m-d'));
        $this->assertSame('2026-09-17', $midnight->scheduled_at->format('Y-m-d'));
        $this->assertTrue($midnight->sections()->where('section_key', 'operational_totalizers')->exists());
    }

    /** @return array<int, array<string, mixed>> */
    private function completeValues(ReadingSection $section): array
    {
        return app(OperationalConfigurationService::class)
            ->rulesForSection($section)
            ->flatMap(function (ParameterRule $rule): array {
                $points = $rule->points()->where('is_active', true)->pluck('id');

                return ($points->isEmpty() ? collect([null]) : $points)
                    ->map(fn (?int $pointId): array => $this->valueFor($rule, $pointId))
                    ->all();
            })
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function valueFor(ParameterRule $rule, ?int $pointId = null): array
    {
        $value = [
            'field_key' => $rule->field_key,
            'parameter_rule_id' => $rule->id,
            'parameter_rule_point_id' => $pointId,
            'semantic_status' => 'MEASURED',
        ];

        return match ($rule->data_type) {
            'boolean' => $value + ['value_boolean' => true],
            'equipment_status' => $value + ['equipment_state' => 'OPERATING'],
            'text', 'textarea' => $value + ['value_text' => 'Teste operacional'],
            'single_select' => $value + ['value_text' => $rule->options[0] ?? 'Teste'],
            'time' => $value + ['value_text' => '10:00'],
            default => $value + ['value_numeric' => $rule->reference_value ?? $rule->minimum_value ?? 1],
        };
    }
}
