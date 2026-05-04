<?php

declare(strict_types=1);

namespace App\Models\Central;

use Database\Factories\Central\PlatformServiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformService extends Model
{
    /** @use HasFactory<PlatformServiceFactory> */
    use HasFactory;

    protected $connection = 'central';

    protected $table = 'platform_services';

    protected $fillable = [
        'key',
        'name',
        'description',
        'type',
        'billing_model',
        'unit_label',
        'default_unit_price',
        'default_flat_price',
        'is_active',
        'launched_at',
    ];

    /**
     * @return HasMany<ClinicService, $this>
     */
    public function clinicServices(): HasMany
    {
        return $this->hasMany(ClinicService::class, 'service_id');
    }

    /**
     * @return HasMany<ClinicUsageRecord, $this>
     */
    public function usageRecords(): HasMany
    {
        return $this->hasMany(ClinicUsageRecord::class, 'service_id');
    }

    /**
     * @return HasMany<PlatformSpending, $this>
     */
    public function spendings(): HasMany
    {
        return $this->hasMany(PlatformSpending::class, 'service_id');
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'launched_at' => 'date',
            'default_unit_price' => 'decimal:4',
            'default_flat_price' => 'decimal:2',
        ];
    }

    protected static function newFactory(): PlatformServiceFactory
    {
        return PlatformServiceFactory::new();
    }
}
