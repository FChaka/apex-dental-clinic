<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\Invoice;
use App\Models\Tenant\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $seq = fake()->unique()->numberBetween(1, 9999);

        return [
            'patient_id' => Patient::factory(),
            'invoice_number' => 'INV-'.now()->year.'-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
            'date' => fake()->date(),
            'due_date' => fake()->date(),
            'amount' => fake()->randomFloat(2, 50, 500),
            'vat_rate' => null,
            'status' => 'Pending',
        ];
    }
}
