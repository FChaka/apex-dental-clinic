<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Database\Factories\Tenant\TreatmentRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreatmentRecord extends Model
{
    /** @use HasFactory<TreatmentRecordFactory> */
    use HasFactory;

    protected $table = 'treatment_records';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'patient_id',
        'dentist_id',
        'name',
        'description',
        'status',
        'date',
        'duration_minutes',
        'price',
        'payment_status',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'duration_minutes' => 'integer',
            'price' => 'decimal:2',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function dentist(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class, 'dentist_id');
    }

    protected static function newFactory(): TreatmentRecordFactory
    {
        return TreatmentRecordFactory::new();
    }
}
