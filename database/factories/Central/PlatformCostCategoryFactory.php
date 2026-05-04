<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Models\Central\PlatformCostCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformCostCategory>
 */
class PlatformCostCategoryFactory extends Factory
{
    protected $model = PlatformCostCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->bothify('cat_??????'),
            'name' => fake()->words(2, true),
        ];
    }
}
