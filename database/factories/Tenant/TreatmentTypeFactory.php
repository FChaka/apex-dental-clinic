<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\TreatmentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TreatmentType>
 */
class TreatmentTypeFactory extends Factory
{
    protected $model = TreatmentType::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'description' => null,
            'default_duration' => 30,
            'default_price' => fake()->randomFloat(2, 20, 200),
            'vat' => null,
            'is_active' => true,
        ];
    }
}
