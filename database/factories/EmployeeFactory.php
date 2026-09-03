<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'registration_number' => 'EMP-' . fake()->unique()->numerify('####'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'birth_date' => fake()->dateTimeBetween('-60 years', '-18 years')->format('Y-m-d'),
            'birth_place' => fake()->city(),
            'address' => fake()->address(),
            'city' => fake()->city(),
            'postal_code' => fake()->postcode(),
            'country' => 'France',
            'gender' => fake()->randomElement(['male', 'female', 'other']),
            'nationality' => 'French',
            'marital_status' => fake()->randomElement(['single', 'married', 'divorced', 'widowed']),
            'children_count' => fake()->numberBetween(0, 4),
            'hiring_date' => fake()->dateTimeBetween('-10 years', 'now')->format('Y-m-d'),
            'contract_type' => fake()->randomElement(['cdi', 'cdd', 'stage', 'alternance']),
            'status' => fake()->randomElement(['active', 'inactive', 'on_leave', 'terminated']),
            'department_id' => null,
            'position_id' => null,
            'hierarchy_level' => fake()->numberBetween(1, 5),
            'base_salary' => fake()->numberBetween(25000, 100000),
            'hourly_rate' => fake()->optional()->numberBetween(15, 50),
            'bank_name' => fake()->optional()->company(),
            'iban' => fake()->optional()->iban('FR'),
            'bic' => fake()->optional()->swiftBicNumber(),
            'social_security_number' => fake()->optional()->numerify('# ## ## ## ### ###'),
            'photo_url' => fake()->optional()->url(),
            'user_id' => null,
            'manager_id' => null,
            'emergency_contact' => fake()->name(),
            'emergency_phone' => fake()->phoneNumber(),
        ];
    }
}
