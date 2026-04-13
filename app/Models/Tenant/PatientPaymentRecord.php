<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientPaymentRecord extends Model
{
    protected $table = 'patient_payment_records';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'patient_id',
        'date',
        'amount',
        'method',
        'note',
        'treatment_id',
        'treatment_label',
        'invoice_id',
        'monthly_plan_id',
        'is_monthly_plan_payment',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'decimal:2',
            'is_monthly_plan_payment' => 'boolean',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }
}
