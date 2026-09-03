<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Position>
 */
class PositionFactory extends Factory
{
    protected $model = Position::class;

    public function definition(): array
    {
        return [
            'title' => fake()->jobTitle(),
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'description' => fake()->sentence(),
            'level' => fake()->randomElement(['junior', 'mid', 'senior', 'lead', 'manager', 'director']),
            'department_id' => Department::factory(),
            'min_salary' => fake()->numberBetween(25000, 50000),
            'max_salary' => fake()->numberBetween(50000, 100000),
            'is_active' => true,
        ];
    }
}