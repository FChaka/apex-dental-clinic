<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Models\Central\Clinic;
use App\Models\Central\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $starts = fake()->dateTimeBetween('-1 month', 'now');

        return [
            'clinic_id' => Clinic::factory(),
            'plan' => fake()->randomElement(['Starter', 'Professional', 'Enterprise']),
            'amount' => fake()->randomFloat(2, 50, 2000),
            'status' => fake()->randomElement(['ok', 'past_due', 'canceled']),
            'starts_at' => $starts,
            'renews_at' => (clone $starts)->modify('+1 month'),
            'canceled_at' => null,
            'payment_method' => fake()->optional()->randomElement(['stripe', 'bank_transfer']),
            'external_id' => null,
        ];
    }
}
