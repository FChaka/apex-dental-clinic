<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicUsageRecord extends Model
{
    protected $connection = 'central';

    protected $table = 'clinic_usage_records';

    protected $fillable = [
        'clinic_id',
        'service_id',
        'month',
        'quantity',
        'unit_cost',
        'total_cost',
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
            'quantity' => 'integer',
            'unit_cost' => 'decimal:4',
            'total_cost' => 'decimal:2',
        ];
    }
}
