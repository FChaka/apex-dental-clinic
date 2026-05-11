<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Models\Central\PlatformService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformService>
 */
class PlatformServiceFactory extends Factory
{
    protected $model = PlatformService::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $key = fake()->unique()->bothify('svc_??????');

        return [
            'key' => $key,
            'name' => fake()->words(3, true),
            'description' => null,
            'type' => fake()->randomElement(['core', 'addon']),
            'billing_model' => fake()->randomElement(['flat', 'per_unit', 'tiered', 'included']),
            'unit_label' => fake()->optional()->word(),
            'default_unit_price' => fake()->optional()->randomFloat(4, 0, 1),
            'default_flat_price' => fake()->optional()->randomFloat(2, 0, 500),
            'is_active' => true,
            'launched_at' => fake()->optional()->date(),
        ];
    }
}
