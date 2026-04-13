<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Database\Factories\Tenant\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    protected $table = 'invoices';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'patient_id',
        'invoice_number',
        'date',
        'due_date',
        'amount',
        'vat_rate',
        'status',
        'pdf_path',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'vat_rate' => 'decimal:2',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function invoiceTreatmentEntries(): HasMany
    {
        return $this->hasMany(InvoiceTreatmentEntry::class, 'invoice_id');
    }

    public function treatmentEntries(): BelongsToMany
    {
        return $this->belongsToMany(
            PatientTreatmentEntry::class,
            'invoice_items',
            'invoice_id',
            'treatment_entry_id'
        );
    }

    public function paymentRecords(): HasMany
    {
        return $this->hasMany(PatientPaymentRecord::class, 'invoice_id');
    }

    protected static function newFactory(): InvoiceFactory
    {
        return InvoiceFactory::new();
    }
}
