<?php

namespace Tests\Feature;

use App\Models\AspersionPoint;
use App\Models\AuditLog;
use App\Models\Equipment;
use App\Models\EquipmentStatus;
use App\Models\ParameterRule;
use App\Models\ReadingSection;
use App\Models\ReadingSectionDefinition;
use App\Models\ReadingValue;
use App\Models\ShiftTemplate;
use App\Models\User;
use App\Services\AuditService;
use App\Services\DailyOperationService;
use App\Services\ReadingSectionService;
use Carbon\Carbon;
use Database\Seeders\EteFlowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_legacy_configuration_keeps_current_schedule_when_tables_are_empty(): void
    {
        Carbon::setTestNow('2026-10-01 09:00:00');
        User::factory()->create(['role' => 'operator', 'is_active' => true]);

        $shift = app(DailyOperationService::class)->ensure();

        $this->assertNull($shift->shift_template_id);
        $this->assertSame('08:00:00', $shift->starts_at);
        $this->assertSame('20:00:00', $shift->ends_at);
        $this->assertSame(
            ['08:30', '10:30', '12:30', '14:30', '16:30', '18:30'],
            $shift->rounds()->orderBy('scheduled_at')->get()->map->scheduled_at->map->format('H:i')->all(),
        );
        $this->assertSame(
            ['process', 'flotators', 'decanter', 'totalizers', 'chemicals', 'observations'],
            $shift->rounds()->oldest('scheduled_at')->firstOrFail()->sections()->orderBy('id')->pluck('section_key')->all(),
        );
        $this->assertSame(0, ReadingSection::query()->whereNotNull('reading_section_definition_id')->count());
    }

    public function test_configuration_applies_only_to_new_shifts_and_keeps_history_unchanged(): void
    {
        Carbon::setTestNow('2026-10-01 09:00:00');
        User::factory()->create(['role' => 'operator', 'is_active' => true]);
        $operations = app(DailyOperationService::class);
        $historical = $operations->ensure();
        $historicalRoundIds = $historical->rounds()->orderBy('scheduled_at')->pluck('id')->all();

        $template = ShiftTemplate::query()->create([
            'code' => 'extended-day',
            'name' => 'Diurno estendido',
            'starts_at' => '07:00:00',
            'ends_at' => '19:00:00',
            'version' => 1,
            'is_active' => true,
            'effective_from' => '2026-10-02 00:00:00',
        ]);
        $template->rounds()->createMany([
            ['offset_minutes' => 60, 'sort_order' => 1, 'is_active' => true],
            ['offset_minutes' => 180, 'sort_order' => 2, 'is_active' => true],
        ]);
        $definition = ReadingSectionDefinition::query()->create([
            'section_key' => 'process-v2',
            'label' => 'Processo configurado',
            'sort_order' => 1,
            'version' => 1,
            'is_active' => true,
            'effective_from' => '2026-10-02 00:00:00',
        ]);

        Carbon::setTestNow('2026-10-02 08:00:00');
        $configured = $operations->ensure();

        $this->assertNull($historical->fresh()->shift_template_id);
        $this->assertSame($historicalRoundIds, $historical->rounds()->orderBy('scheduled_at')->pluck('id')->all());
        $this->assertSame('08:00:00', $historical->fresh()->starts_at);
        $this->assertSame($template->id, $configured->shift_template_id);
        $this->assertSame('07:00:00', $configured->starts_at);
        $this->assertSame(['08:00', '10:00'], $configured->rounds()->orderBy('scheduled_at')->get()->map->scheduled_at->map->format('H:i')->all());
        $this->assertSame(
            [$definition->id],
            $configured->rounds()->oldest('scheduled_at')->firstOrFail()->sections()->pluck('reading_section_definition_id')->all(),
        );
    }

    public function test_draft_can_be_incomplete_but_completion_requires_applicable_rules(): void
    {
        Carbon::setTestNow('2026-09-09 10:00:00');
        $this->seed(EteFlowSeeder::class);
        $operator = User::query()->where('username', 'operador1')->firstOrFail();
        $section = ReadingSection::query()->where('section_key', 'biological_anoxic_lagoon')->firstOrFail();

        $this->actingAs($operator)->putJson('/api/reading-sections/'.$section->id, [
            'lock_version' => $section->lock_version,
            'status' => 'in_progress',
            'values' => [['field_key' => 'anoxic_ph', 'value_numeric' => 7.1]],
        ])->assertOk();

        $section->refresh();
        $this->actingAs($operator)->putJson('/api/reading-sections/'.$section->id, [
            'lock_version' => $section->lock_version,
            'status' => 'completed',
            'values' => [['field_key' => 'anoxic_ph', 'value_numeric' => 7.2]],
        ])->assertUnprocessable()->assertJsonValidationErrors('values');

        $this->assertSame('in_progress', $section->fresh()->status);

        $section->refresh();
        $this->actingAs($operator)->putJson('/api/reading-sections/'.$section->id, [
            'lock_version' => $section->lock_version,
            'status' => 'completed',
            'values' => [
                ['field_key' => 'anoxic_inlet_flow', 'value_numeric' => 15],
                ['field_key' => 'feed_pump_command', 'value_numeric' => 50],
                ['field_key' => 'chemical_feed_flow', 'value_numeric' => 4],
                ['field_key' => 'anoxic_ph', 'value_numeric' => 7.2],
                ['field_key' => 'anoxic_dissolved_oxygen', 'value_numeric' => 0.8],
                ['field_key' => 'anoxic_sd30', 'value_numeric' => 400],
                ['field_key' => 'anoxic_mixers_running', 'value_numeric' => 2],
            ],
        ])->assertOk();

        $this->assertSame('completed', $section->fresh()->status);
    }

    public function test_unconfigured_field_key_is_rejected_without_saving(): void
    {
        Carbon::setTestNow('2026-09-09 10:00:00');
        $this->seed(EteFlowSeeder::class);
        $operator = User::query()->where('username', 'operador1')->firstOrFail();
        $section = ReadingSection::query()->where('section_key', 'biological_anoxic_lagoon')->firstOrFail();

        $this->actingAs($operator)->putJson('/api/reading-sections/'.$section->id, [
            'lock_version' => $section->lock_version,
            'status' => 'in_progress',
            'values' => [['field_key' => 'arbitrary_parameter', 'value_numeric' => 99]],
        ])->assertUnprocessable()->assertJsonValidationErrors('values');

        $this->assertDatabaseMissing('reading_values', [
            'reading_section_id' => $section->id,
            'field_key' => 'arbitrary_parameter',
        ]);
    }

    public function test_historical_reading_keeps_its_original_rule_and_snapshot(): void
    {
        Carbon::setTestNow('2026-09-09 10:00:00');
        $this->seed(EteFlowSeeder::class);
        $operator = User::query()->where('username', 'operador1')->firstOrFail();
        $section = ReadingSection::query()->where('section_key', 'biological_anoxic_lagoon')->firstOrFail();

        $saved = app(ReadingSectionService::class)->save($section, $operator, [
            'lock_version' => $section->lock_version,
            'status' => 'in_progress',
            'values' => [['field_key' => 'feed_pump_command', 'value_numeric' => 50]],
        ]);
        $value = ReadingValue::query()->where('reading_section_id', $section->id)->firstOrFail();
        $originalRuleId = $value->parameter_rule_id;
        $originalRule = ParameterRule::query()->findOrFail($originalRuleId);
        $originalRule->update(['is_active' => false, 'effective_until' => '2026-09-10 00:00:00']);
        ParameterRule::query()->create([
            'section_key' => 'biological_anoxic_lagoon',
            'field_key' => 'feed_pump_command',
            'label' => 'Comando novo',
            'unit' => '%',
            'minimum_value' => 10,
            'maximum_value' => 90,
            'is_required' => true,
            'is_active' => true,
            'version' => 2,
            'effective_from' => '2026-09-10 00:00:00',
        ]);

        Carbon::setTestNow('2026-09-11 10:00:00');
        $this->actingAs($operator)
            ->getJson('/api/reading-sections/'.$section->id)
            ->assertOk()
            ->assertJsonFragment([
                'id' => $originalRuleId,
                'field_key' => 'feed_pump_command',
                'minimum_value' => '0.0000',
            ]);

        app(ReadingSectionService::class)->save($saved, $operator, [
            'lock_version' => $saved->lock_version,
            'status' => 'in_progress',
            'values' => [['field_key' => 'feed_pump_command', 'value_numeric' => 60]],
        ]);

        $value->refresh();
        $this->assertSame($originalRuleId, $value->parameter_rule_id);
        $this->assertSame('0.0000', $value->minimum_at_time);
        $this->assertSame('100.0000', $value->maximum_at_time);
    }

    public function test_equipment_reference_is_optional_and_does_not_backfill_legacy_status(): void
    {
        Carbon::setTestNow('2026-09-09 10:00:00');
        $this->seed(EteFlowSeeder::class);
        $shift = app(DailyOperationService::class)->ensure();
        $legacy = EquipmentStatus::query()->create([
            'shift_id' => $shift->id,
            'name' => 'Equipamento legado',
            'status' => 'operating',
            'reported_at' => now(),
        ]);
        $equipment = Equipment::query()->create([
            'code' => 'new-equipment',
            'name' => 'Equipamento novo',
            'is_active' => true,
        ]);
        $linked = EquipmentStatus::query()->create([
            'shift_id' => $shift->id,
            'equipment_id' => $equipment->id,
            'name' => $equipment->name,
            'status' => 'attention',
            'reported_at' => now(),
        ]);

        $this->assertNull($legacy->fresh()->equipment_id);
        $this->assertSame($equipment->id, $linked->fresh()->equipment_id);
        $this->assertSame($equipment->name, $linked->equipment->name);
    }

    public function test_central_audit_service_reuses_existing_table_and_qr_token_is_unchanged(): void
    {
        Carbon::setTestNow('2026-09-09 10:00:00');
        $user = User::factory()->create(['role' => 'master', 'is_active' => true]);
        $point = AspersionPoint::query()->create([
            'name' => 'Ponto preservado',
            'public_token' => 'existing-public-token-that-must-not-change',
            'is_active' => true,
        ]);

        $audit = app(AuditService::class)->record(
            $point,
            'aspersion_point.reviewed',
            $user,
            ['is_active' => false],
            ['is_active' => true],
        );

        $this->assertInstanceOf(AuditLog::class, $audit);
        $this->assertSame($point->getMorphClass(), $audit->auditable_type);
        $this->assertSame($user->id, $audit->user_id);
        $this->assertSame(['is_active' => false], $audit->old_values);
        $this->assertSame(['is_active' => true], $audit->new_values);
        $this->assertSame('existing-public-token-that-must-not-change', $point->fresh()->public_token);
    }
}
