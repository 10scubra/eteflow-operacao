<?php

namespace Tests\Feature;

use App\Models\ReadingSection;
use App\Models\ReadingValue;
use App\Models\ReadingValueRevision;
use App\Models\User;
use App\Services\ReadingSectionService;
use Database\Seeders\EteFlowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class ReadingSectionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EteFlowSeeder::class);
    }

    public function test_saving_one_section_does_not_change_another_section(): void
    {
        $operator = User::where('username', 'operador1')->firstOrFail();
        $section = ReadingSection::where('section_key', 'biological_anoxic_lagoon')->firstOrFail();
        $untouched = ReadingSection::where('section_key', 'biological_aerobic_lagoon')->firstOrFail();
        $untouchedBefore = $untouched->only(['status', 'lock_version', 'last_edited_by']);

        app(ReadingSectionService::class)->save($section, $operator, [
            'lock_version' => $section->lock_version,
            'status' => 'in_progress',
            'values' => [[
                'field_key' => 'anoxic_ph',
                'value_numeric' => 7.2,
            ]],
        ]);

        $this->assertSame($untouchedBefore, $untouched->fresh()->only(['status', 'lock_version', 'last_edited_by']));
        $this->assertDatabaseHas('reading_values', [
            'reading_section_id' => $section->id,
            'field_key' => 'anoxic_ph',
            'recorded_by' => $operator->id,
        ]);
    }

    public function test_out_of_range_value_is_saved_with_rule_snapshot(): void
    {
        $operator = User::where('username', 'operador1')->firstOrFail();
        $section = ReadingSection::where('section_key', 'biological_anoxic_lagoon')->firstOrFail();

        app(ReadingSectionService::class)->save($section, $operator, [
            'lock_version' => $section->lock_version,
            'status' => 'in_progress',
            'values' => [[
                'field_key' => 'anoxic_ph',
                'value_numeric' => 8,
            ]],
        ]);

        $value = ReadingValue::where('reading_section_id', $section->id)
            ->where('field_key', 'anoxic_ph')
            ->firstOrFail();

        $this->assertTrue($value->is_out_of_range);
        $this->assertSame('6.6000', $value->minimum_at_time);
        $this->assertSame('7.2000', $value->maximum_at_time);
        $this->assertSame('8.0000', $value->value_numeric);
    }

    public function test_stale_lock_version_is_rejected(): void
    {
        $operator = User::where('username', 'operador1')->firstOrFail();
        $section = ReadingSection::where('section_key', 'biological_anoxic_lagoon')->firstOrFail();

        app(ReadingSectionService::class)->save($section, $operator, [
            'lock_version' => 0,
            'status' => 'in_progress',
            'values' => [['field_key' => 'anoxic_ph', 'value_numeric' => 7]],
        ]);

        $this->expectException(ConflictHttpException::class);

        app(ReadingSectionService::class)->save($section, $operator, [
            'lock_version' => 0,
            'status' => 'completed',
            'values' => [['field_key' => 'anoxic_ph', 'value_numeric' => 7.1]],
        ]);
    }

    public function test_each_change_records_author_and_previous_value(): void
    {
        $edenir = User::where('username', 'operador1')->firstOrFail();
        $igor = User::where('username', 'operador2')->firstOrFail();
        $section = ReadingSection::where('section_key', 'biological_anoxic_lagoon')->firstOrFail();
        $service = app(ReadingSectionService::class);

        $saved = $service->save($section, $edenir, [
            'lock_version' => $section->lock_version,
            'status' => 'in_progress',
            'values' => [
                ['field_key' => 'anoxic_inlet_flow', 'value_numeric' => 15],
                ['field_key' => 'feed_pump_command', 'value_numeric' => 50],
                ['field_key' => 'chemical_feed_flow', 'value_numeric' => 4],
                ['field_key' => 'anoxic_ph', 'value_numeric' => 7.0],
                ['field_key' => 'anoxic_dissolved_oxygen', 'value_numeric' => 0.8],
                ['field_key' => 'anoxic_sd30', 'value_numeric' => 400],
                ['field_key' => 'anoxic_mixers_running', 'value_numeric' => 2],
            ],
        ]);

        $service->save($saved, $igor, [
            'lock_version' => $saved->lock_version,
            'status' => 'completed',
            'reason' => 'Conferência do segundo operador.',
            'values' => [['field_key' => 'anoxic_ph', 'value_numeric' => 7.2]],
        ]);

        $value = ReadingValue::where('reading_section_id', $section->id)
            ->where('field_key', 'anoxic_ph')
            ->firstOrFail();
        $revisions = ReadingValueRevision::where('reading_value_id', $value->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $revisions);
        $this->assertSame($edenir->id, $revisions[0]->changed_by);
        $this->assertSame($igor->id, $revisions[1]->changed_by);
        $this->assertSame('7.0000', $revisions[1]->previous_value['value_numeric']);
        $this->assertSame('7.2000', $revisions[1]->new_value['value_numeric']);
        $this->assertSame($igor->id, $section->fresh()->completed_by);
    }

    public function test_second_operator_is_warned_and_can_open_the_same_block(): void
    {
        $firstOperator = User::where('username', 'operador1')->firstOrFail();
        $secondOperator = User::where('username', 'operador2')->firstOrFail();
        $section = ReadingSection::where('section_key', 'biological_anoxic_lagoon')->firstOrFail();

        $this->actingAs($firstOperator)
            ->postJson('/api/reading-sections/'.$section->id.'/open')
            ->assertOk()
            ->assertJsonPath('section.editing_by.id', $firstOperator->id);

        $this->actingAs($secondOperator)
            ->postJson('/api/reading-sections/'.$section->id.'/open')
            ->assertConflict()
            ->assertJsonPath('can_override', true)
            ->assertJsonPath('editor', $firstOperator->name);

        $this->actingAs($secondOperator)
            ->postJson('/api/reading-sections/'.$section->id.'/open', ['force' => true])
            ->assertOk()
            ->assertJsonPath('section.editing_by.id', $secondOperator->id);
    }

    public function test_next_round_can_receive_previous_values_without_automatic_save(): void
    {
        $operator = User::where('username', 'operador1')->firstOrFail();
        $sections = ReadingSection::where('section_key', 'biological_anoxic_lagoon')
            ->whereHas('round')
            ->with('round')
            ->get()
            ->sortBy(fn ($section) => $section->round->scheduled_at)
            ->values();

        $first = $sections[0];
        $second = $sections[1];

        app(ReadingSectionService::class)->save($first, $operator, [
            'lock_version' => $first->lock_version,
            'status' => 'in_progress',
            'values' => [['field_key' => 'anoxic_ph', 'value_numeric' => 7.15]],
        ]);

        $this->actingAs($operator)
            ->getJson('/api/reading-sections/'.$second->id)
            ->assertOk()
            ->assertJsonPath('previous_values.0.field_key', 'anoxic_ph')
            ->assertJsonPath('previous_values.0.value_numeric', '7.1500');

        $this->assertDatabaseMissing('reading_values', [
            'reading_section_id' => $second->id,
            'field_key' => 'anoxic_ph',
        ]);
    }
}
