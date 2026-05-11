<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\Patient;
use App\Models\Tenant\PatientPaymentRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientPaymentRecord>
 */
class PatientPaymentRecordFactory extends Factory
{
    protected $model = PatientPaymentRecord::class;

    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'date' => fake()->date(),
            'amount' => fake()->randomFloat(2, 10, 200),
            'method' => 'cash',
            'note' => null,
            'treatment_id' => null,
            'treatment_label' => null,
            'invoice_id' => null,
            'monthly_plan_id' => null,
            'is_monthly_plan_payment' => false,
            'source' => 'manual',
        ];
    }
}
