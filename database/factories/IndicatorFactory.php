<?php

namespace Database\Factories;

use App\Models\Indicator;
use Illuminate\Database\Eloquent\Factories\Factory;

class IndicatorFactory extends Factory
{
    protected $model = Indicator::class;

    public function definition(): array
    {
        $types = ['percentage', 'number', 'currency', 'boolean', 'text'];
        $type = $this->faker->randomElement($types);

        $targetValue = match ($type) {
            'percentage' => $this->faker->numberBetween(10, 100),
            'number' => $this->faker->numberBetween(100, 10000),
            'currency' => $this->faker->numberBetween(1000000, 100000000),
            'boolean' => $this->faker->numberBetween(0, 1),
            'text' => null,
        };

        return [
            'name' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'code' => strtoupper($this->faker->unique()->lexify('??')),
            'category' => $this->faker->randomElement(['academic', 'infrastructure', 'financial', 'research', 'governance']),
            'subcategory' => $this->faker->word(),
            'type' => $type,
            'unit' => $type === 'percentage' ? '%' : ($type === 'currency' ? 'XAF' : null),
            'unit_symbol' => $type === 'percentage' ? '%' : ($type === 'currency' ? 'FCFA' : null),
            'target_value' => $targetValue,
            'minimum_value' => $type === 'percentage' ? 0 : null,
            'maximum_value' => $type === 'percentage' ? 100 : null,
            'frequency' => $this->faker->randomElement(['monthly', 'quarterly', 'semesterly', 'annually']),
            'calculation_method' => [
                'formula' => $this->faker->sentence(10),
                'variables' => $this->faker->words(3),
            ],
            'data_sources' => [
                'primary' => $this->faker->sentence(5),
                'secondary' => $this->faker->sentence(5),
            ],
            'metadata' => [
                'complexity' => $this->faker->randomElement(['low', 'medium', 'high']),
                'data_collection_method' => $this->faker->randomElement(['manual', 'automated', 'survey']),
            ],
            'is_active' => $this->faker->boolean(90), // 90% chance of being active
            'sort_order' => $this->faker->numberBetween(1, 100),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    public function percentage(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'percentage',
            'unit' => '%',
            'unit_symbol' => '%',
            'target_value' => $this->faker->numberBetween(10, 100),
            'minimum_value' => 0,
            'maximum_value' => 100,
        ]);
    }
}