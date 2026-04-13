<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientAnamnesis extends Model
{
    protected $table = 'patient_anamnesis';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'patient_id',
        'chief_complaint',
        'present_illness',
        'current_medications',
        'previous_surgeries',
        'family_history',
        'dental_history',
        'other',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }
}
