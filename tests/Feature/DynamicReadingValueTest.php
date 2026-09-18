<?php

namespace Tests\Feature;

use App\Models\ParameterRule;
use App\Models\ReadingRound;
use App\Models\ReadingSection;
use App\Models\ReadingValue;
use App\Models\Shift;
use App\Models\User;
use App\Services\ReadingDefinitionValidator;
use App\Services\ReadingSectionService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DynamicReadingValueTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_zero_remains_a_measured_numeric_value(): void
    {
        [$section,$user,$rule] = $this->context();
        $this->save($section, $user, $rule, ['semantic_status' => 'MEASURED', 'value_numeric' => 0]);
        $value = ReadingValue::firstOrFail();
        $this->assertSame('MEASURED', $value->semantic_status);
        $this->assertSame('0.0000', $value->value_numeric);
    }

    public function test_no_flow_remains_null_with_semantic_status(): void
    {
        [$section,$user,$rule] = $this->context();
        $this->save($section, $user, $rule, ['semantic_status' => 'NO_FLOW']);
        $value = ReadingValue::firstOrFail();
        $this->assertSame('NO_FLOW', $value->semantic_status);
        $this->assertNull($value->value_numeric);
    }

    public function test_required_meter_fault_resolves_completion(): void
    {
        [$section,$user,$rule] = $this->context();
        $saved = $this->save($section, $user, $rule, ['semantic_status' => 'METER_FAULT']);
        $this->assertSame('completed', $saved->status);
    }

    public function test_not_measured_without_justification_does_not_complete(): void
    {
        [$section,$user,$rule] = $this->context();
        $this->expectException(ValidationException::class);
        $this->save($section, $user, $rule, ['semantic_status' => 'NOT_MEASURED']);
    }

    public function test_multipoint_creates_independent_values_for_same_rule(): void
    {
        [$section,$user,$rule] = $this->context();
        $p1 = $rule->points()->create(['stable_key' => 'p1', 'label' => 'P1', 'sort_order' => 1, 'is_active' => true]);
        $p2 = $rule->points()->create(['stable_key' => 'p2', 'label' => 'P2', 'sort_order' => 2, 'is_active' => true]);
        app(ReadingSectionService::class)->save($section, $user, ['lock_version' => 0, 'status' => 'completed', 'values' => [$this->entry($rule, ['semantic_status' => 'MEASURED', 'value_numeric' => 6.7, 'parameter_rule_point_id' => $p1->id]), $this->entry($rule, ['semantic_status' => 'MEASURED', 'value_numeric' => 6.8, 'parameter_rule_point_id' => $p2->id])]]);
        $this->assertSame(2, ReadingValue::where('parameter_rule_id', $rule->id)->count());
        $this->assertEqualsCanonicalizing([$p1->id, $p2->id], ReadingValue::pluck('parameter_rule_point_id')->all());
    }

    public function test_percentage_uses_configured_limit_instead_of_hardcoded_one_hundred(): void
    {
        [$section,$user,$rule] = $this->context(['data_type' => 'percentage', 'unit' => '%', 'maximum_value' => 150]);
        $this->save($section, $user, $rule, ['semantic_status' => 'MEASURED', 'value_numeric' => 120]);
        $value = ReadingValue::firstOrFail();
        $this->assertFalse($value->is_out_of_range);
        $this->assertSame('%', $value->unit);
        $this->assertSame('percentage', $value->definition_snapshot['data_type']);
    }

    public function test_equipment_state_is_distinct_from_reading_status(): void
    {
        [$section,$user,$rule] = $this->context(['data_type' => 'equipment_status']);
        $this->save($section, $user, $rule, ['semantic_status' => 'MEASURED', 'equipment_state' => 'STOPPED']);
        $value = ReadingValue::firstOrFail();
        $this->assertSame('MEASURED', $value->semantic_status);
        $this->assertSame('STOPPED', $value->equipment_state);
        $this->assertNull($value->value_numeric);
    }

    public function test_totalizer_stores_only_the_raw_accumulated_reading(): void
    {
        [$section, $user, $rule] = $this->context(['data_type' => 'totalizer', 'unit' => 'm³', 'maximum_value' => null]);
        $this->save($section, $user, $rule, ['semantic_status' => 'MEASURED', 'value_numeric' => 154275]);
        $value = ReadingValue::firstOrFail();
        $this->assertSame('154275.0000', $value->value_numeric);
        $this->assertSame('totalizer', $value->definition_snapshot['data_type']);
        $this->assertArrayNotHasKey('consumption', $value->definition_snapshot);
    }

    public function test_frequency_configuration_rejects_arbitrary_json(): void
    {
        $this->expectException(ValidationException::class);
        app(ReadingDefinitionValidator::class)->validateFrequency('EVERY_N_HOURS', ['unexpected' => 2]);
    }

    private function context(array $overrides = []): array
    {
        $user = User::factory()->create(['role' => 'operator', 'is_active' => true]);
        $shift = Shift::create(['shift_date' => today(), 'starts_at' => '08:00:00', 'ends_at' => '20:00:00', 'status' => 'active']);
        $shift->members()->attach($user->id, ['joined_at' => now()]);
        $round = ReadingRound::create([
            'shift_id' => $shift->id,
            'scheduled_at' => $shift->shift_date->copy()->setTime(8, 30),
            'status' => 'pending',
        ]);
        $section = ReadingSection::create(['reading_round_id' => $round->id, 'section_key' => 'process', 'label' => 'Processo', 'status' => 'pending']);
        $rule = ParameterRule::create(array_merge(['section_key' => 'process', 'field_key' => 'reading', 'label' => 'Leitura', 'data_type' => 'decimal', 'unit' => 'mg/L', 'minimum_value' => 0, 'maximum_value' => 10, 'sort_order' => 1, 'condition_operator' => 'BETWEEN', 'frequency_type' => 'EVERY_ROUND', 'frequency_config' => [], 'is_required' => true, 'is_active' => true, 'version' => 1, 'effective_from' => now()->subMinute()], $overrides));

        return [$section, $user, $rule];
    }

    private function entry(ParameterRule $rule, array $values): array
    {
        return array_merge(['field_key' => $rule->field_key, 'parameter_rule_id' => $rule->id, 'parameter_rule_point_id' => null], $values);
    }

    private function save(ReadingSection $section, User $user, ParameterRule $rule, array $values): ReadingSection
    {
        return app(ReadingSectionService::class)->save($section, $user, ['lock_version' => $section->lock_version, 'status' => 'completed', 'values' => [$this->entry($rule, $values)]]);
    }
}
