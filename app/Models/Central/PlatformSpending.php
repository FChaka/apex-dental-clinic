<?php

declare(strict_types=1);

namespace App\Models\Central;

use Database\Factories\Central\PlatformSpendingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformSpending extends Model
{
    /** @use HasFactory<PlatformSpendingFactory> */
    use HasFactory;

    protected $connection = 'central';

    protected $table = 'platform_spendings';

    protected $fillable = [
        'category_id',
        'service_id',
        'month',
        'amount',
        'note',
    ];

    /**
     * @return BelongsTo<PlatformCostCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(PlatformCostCategory::class, 'category_id');
    }

    /**
     * @return BelongsTo<PlatformService, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(PlatformService::class, 'service_id');
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    protected static function newFactory(): PlatformSpendingFactory
    {
        return PlatformSpendingFactory::new();
    }
}
