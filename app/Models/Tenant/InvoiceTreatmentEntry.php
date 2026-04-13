<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot row in `invoice_items` linking invoices to patient treatment entries.
 */
class InvoiceTreatmentEntry extends Model
{
    public $timestamps = false;

    protected $table = 'invoice_items';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'invoice_id',
        'treatment_entry_id',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function treatmentEntry(): BelongsTo
    {
        return $this->belongsTo(PatientTreatmentEntry::class, 'treatment_entry_id');
    }
}
