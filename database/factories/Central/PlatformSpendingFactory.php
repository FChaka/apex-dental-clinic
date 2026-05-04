<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Models\Central\PlatformCostCategory;
use App\Models\Central\PlatformService;
use App\Models\Central\PlatformSpending;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformSpending>
 */
class PlatformSpendingFactory extends Factory
{
    protected $model = PlatformSpending::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => PlatformCostCategory::factory(),
            'service_id' => fake()->boolean(40) ? PlatformService::factory() : null,
            'month' => now()->format('Y-m'),
            'amount' => fake()->randomFloat(2, 10, 2000),
            'note' => fake()->optional()->sentence(),
        ];
    }
}
