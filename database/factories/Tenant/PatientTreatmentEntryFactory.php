<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\Patient;
use App\Models\Tenant\PatientTreatmentEntry;
use App\Models\Tenant\StaffMember;
use App\Models\Tenant\TreatmentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientTreatmentEntry>
 */
class PatientTreatmentEntryFactory extends Factory
{
    protected $model = PatientTreatmentEntry::class;

    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'treatment_type_id' => TreatmentType::factory(),
            'dentist_id' => StaffMember::factory(),
            'date' => fake()->date(),
            'tooth_number' => null,
            'price' => fake()->randomFloat(2, 30, 500),
            'amount_paid' => 0,
            'payment_status' => 'Pending',
        ];
    }
}
