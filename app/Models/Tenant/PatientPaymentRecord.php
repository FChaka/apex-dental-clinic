<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Database\Factories\Tenant\PatientPaymentRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientPaymentRecord extends Model
{
    /** @use HasFactory<PatientPaymentRecordFactory> */
    use HasFactory;

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

    public function treatment(): BelongsTo
    {
        return $this->belongsTo(PatientTreatmentEntry::class, 'treatment_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function monthlyPlan(): BelongsTo
    {
        return $this->belongsTo(PatientMonthlyPlan::class, 'monthly_plan_id');
    }

    protected static function newFactory(): PatientPaymentRecordFactory
    {
        return PatientPaymentRecordFactory::new();
    }
}
