<?php

namespace Database\Factories;

use App\Models\ParameterRule;
use App\Models\ParameterRulePoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ParameterRulePoint>
 */
class ParameterRulePointFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parameter_rule_id' => ParameterRule::factory(),
            'stable_key' => fake()->unique()->slug(2),
            'label' => fake()->words(2, true),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
