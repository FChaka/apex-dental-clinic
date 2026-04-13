<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    protected $model = Patient::class;

    public function definition(): array
    {
        return [
            'name' => fake()->firstName(),
            'surname' => fake()->lastName(),
            'fathers_name' => null,
            'birthday' => fake()->optional()->date(),
            'gender' => fake()->randomElement(['Male', 'Female', 'Other']),
            'phone' => fake()->boolean(50) ? fake()->phoneNumber() : null,
            'email' => fake()->boolean(40) ? fake()->unique()->safeEmail() : null,
            'address' => null,
            'city' => null,
            'personal_number' => null,
            'blood_type' => null,
            'avatar_path' => null,
            'general_notes' => null,
            'assigned_dentist_id' => null,
            'last_visit' => null,
            'status' => 'Active',
            'medical_alert' => null,
        ];
    }
}
