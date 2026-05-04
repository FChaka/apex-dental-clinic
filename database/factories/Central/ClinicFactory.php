<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Models\Central\Clinic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Clinic>
 */
class ClinicFactory extends Factory
{
    protected $model = Clinic::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = str_replace('.', '', fake()->unique()->slug(2));

        return [
            'name' => fake()->company(),
            'slug' => $slug,
            'region' => fake()->optional()->state(),
            'plan' => fake()->randomElement(['Starter', 'Professional', 'Enterprise']),
            'seats' => fake()->numberBetween(1, 20),
            'status' => fake()->randomElement(['active', 'trial', 'suspended']),
            'contact_email' => fake()->companyEmail(),
            'mrr' => fake()->randomFloat(2, 0, 5000),
            'db_name' => 'apex_clinic_'.str_replace('-', '_', $slug),
            'db_host' => '127.0.0.1',
            'db_port' => '3306',
            'trial_ends_at' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Clinic $clinic): void {
            if ($clinic->domains()->where('domain', $clinic->slug)->doesntExist()) {
                $clinic->domains()->create([
                    'domain' => $clinic->slug,
                ]);
            }
        });
    }
}
