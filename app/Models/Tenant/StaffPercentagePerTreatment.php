<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StaffPercentagePerTreatment extends Model
{
    public $timestamps = false;

    protected $table = 'staff_percentage_per_treatment';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'staff_id',
        'treatment_type_id',
        'percentage',
    ];

    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<StaffMember, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class, 'staff_id');
    }

    /**
     * @return BelongsTo<TreatmentType, $this>
     */
    public function treatmentType(): BelongsTo
    {
        return $this->belongsTo(TreatmentType::class, 'treatment_type_id');
    }
}
