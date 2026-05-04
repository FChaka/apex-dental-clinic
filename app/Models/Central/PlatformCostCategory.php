<?php

declare(strict_types=1);

namespace App\Models\Central;

use Database\Factories\Central\PlatformCostCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformCostCategory extends Model
{
    /** @use HasFactory<PlatformCostCategoryFactory> */
    use HasFactory;

    protected $connection = 'central';

    protected $table = 'platform_cost_categories';

    protected $fillable = [
        'key',
        'name',
    ];

    /**
     * @return HasMany<PlatformSpending, $this>
     */
    public function spendings(): HasMany
    {
        return $this->hasMany(PlatformSpending::class, 'category_id');
    }

    protected static function newFactory(): PlatformCostCategoryFactory
    {
        return PlatformCostCategoryFactory::new();
    }
}
