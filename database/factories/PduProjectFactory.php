<?php

namespace Database\Factories;

use App\Models\PduProject;
use App\Models\University;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PduProjectFactory extends Factory
{
    protected $model = PduProject::class;

    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('-1 year', '+6 months');
        $endDate = $this->faker->dateTimeBetween($startDate, '+2 years');

        return [
            'title' => 'Projet PDU ' . $this->faker->words(3, true),
            'description' => $this->faker->paragraphs(3, true),
            'code' => 'PDU-' . $this->faker->unique()->numberBetween(1000, 9999),
            'university_id' => University::factory(),
            'created_by' => User::factory(),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'planned_completion_date' => $this->faker->dateTimeBetween($startDate, $endDate),
            'status' => $this->faker->randomElement(['draft', 'submitted', 'approved', 'in_progress', 'completed', 'cancelled']),
            'progress_percentage' => $this->faker->numberBetween(0, 100),
            'budget_allocated' => $this->faker->numberBetween(50000000, 500000000), // 50M - 500M FCFA
            'budget_spent' => $this->faker->numberBetween(0, 500000000),
            'currency' => 'XAF',
            'objectives' => $this->faker->paragraphs(2),
            'stakeholders' => [
                'ministry' => $this->faker->name(),
                'university_admin' => $this->faker->name(),
                'project_coordinator' => $this->faker->name(),
            ],
            'metadata' => [
                'priority_level' => $this->faker->randomElement(['high', 'medium', 'low']),
                'funding_source' => $this->faker->randomElement(['government', 'world_bank', 'eu', 'mixed']),
            ],
            'director_id' => User::factory(),
            'project_manager_id' => User::factory(),
            'financial_agent_id' => User::factory(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in_progress',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'progress_percentage' => 100,
        ]);
    }
}