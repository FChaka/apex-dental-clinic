<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicService extends Model
{
    protected $connection = 'central';

    protected $table = 'clinic_services';

    protected $fillable = [
        'clinic_id',
        'service_id',
        'is_enabled',
        'unit_price_override',
        'flat_price_override',
        'monthly_quota',
        'enabled_at',
        'disabled_at',
    ];

    /**
     * @return BelongsTo<Clinic, $this>
     */
    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class, 'clinic_id');
    }

    /**
     * @return BelongsTo<PlatformService, $this>
     */
    public function platformService(): BelongsTo
    {
        return $this->belongsTo(PlatformService::class, 'service_id');
    }

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'enabled_at' => 'date',
            'disabled_at' => 'date',
            'unit_price_override' => 'decimal:4',
            'flat_price_override' => 'decimal:2',
        ];
    }
}
