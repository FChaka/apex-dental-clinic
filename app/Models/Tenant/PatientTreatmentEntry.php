<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientTreatmentEntry extends Model
{
    protected $table = 'patient_treatment_entries';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'patient_id',
        'treatment_type_id',
        'dentist_id',
        'date',
        'tooth_number',
        'price',
        'amount_paid',
        'payment_status',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'price' => 'decimal:2',
            'amount_paid' => 'decimal:2',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function treatmentType(): BelongsTo
    {
        return $this->belongsTo(TreatmentType::class, 'treatment_type_id');
    }

    public function dentist(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class, 'dentist_id');
    }
}
