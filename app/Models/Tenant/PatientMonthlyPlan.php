<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientMonthlyPlan extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'patient_id',
        'plan_name',
        'total_amount',
        'months',
        'interest_percent',
        'start_date',
        'payment_day_of_month',
        'initial_payment',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'interest_percent' => 'decimal:2',
            'start_date' => 'date',
            'initial_payment' => 'decimal:2',
            'months' => 'integer',
            'payment_day_of_month' => 'integer',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }
}
