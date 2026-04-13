<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Database\Factories\Tenant\PatientTreatmentEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PatientTreatmentEntry extends Model
{
    /** @use HasFactory<PatientTreatmentEntryFactory> */
    use HasFactory;

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

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceTreatmentEntry::class, 'treatment_entry_id');
    }

    public function paymentRecords(): HasMany
    {
        return $this->hasMany(PatientPaymentRecord::class, 'treatment_id');
    }

    public function syncPaymentStatusFromAmounts(): void
    {
        $price = (float) $this->price;
        $paid = (float) $this->amount_paid;
        $this->payment_status = $paid >= $price ? 'Paid' : 'Pending';
    }

    protected static function newFactory(): PatientTreatmentEntryFactory
    {
        return PatientTreatmentEntryFactory::new();
    }
}
