<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\Appointment;
use App\Models\Tenant\Patient;
use App\Models\Tenant\StaffMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'dentist_id' => StaffMember::factory(),
            'date' => fake()->date(),
            'time' => '09:00',
            'treatment' => fake()->words(3, true),
            'status' => 'Upcoming',
            'notes' => null,
        ];
    }
}
