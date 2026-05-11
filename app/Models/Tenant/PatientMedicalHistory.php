<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientMedicalHistory extends Model
{
    protected $table = 'patient_medical_history';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'patient_id',
        'allergies',
        'conditions',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'allergies' => 'array',
            'conditions' => 'array',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }
}
