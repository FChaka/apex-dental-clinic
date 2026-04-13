<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\Patient;
use App\Models\Tenant\StaffMember;
use App\Models\Tenant\TreatmentRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TreatmentRecord>
 */
class TreatmentRecordFactory extends Factory
{
    protected $model = TreatmentRecord::class;

    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'dentist_id' => StaffMember::factory(),
            'name' => fake()->words(3, true),
            'description' => null,
            'status' => 'In Progress',
            'date' => fake()->date(),
            'duration_minutes' => 30,
            'price' => fake()->randomFloat(2, 20, 300),
            'payment_status' => 'Pending',
        ];
    }
}
