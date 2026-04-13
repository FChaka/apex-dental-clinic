<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Database\Factories\Tenant\PatientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    /** @use HasFactory<PatientFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'patients';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'surname',
        'fathers_name',
        'birthday',
        'gender',
        'phone',
        'email',
        'address',
        'city',
        'personal_number',
        'blood_type',
        'avatar_path',
        'general_notes',
        'assigned_dentist_id',
        'last_visit',
        'status',
        'medical_alert',
    ];

    protected function casts(): array
    {
        return [
            'birthday' => 'date',
            'last_visit' => 'date',
        ];
    }

    public function medicalHistory(): HasOne
    {
        return $this->hasOne(PatientMedicalHistory::class, 'patient_id');
    }

    public function anamnesis(): HasOne
    {
        return $this->hasOne(PatientAnamnesis::class, 'patient_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PatientDocument::class, 'patient_id');
    }

    public function patientMonthlyPlans(): HasMany
    {
        return $this->hasMany(PatientMonthlyPlan::class, 'patient_id');
    }

    public function teethChartData(): HasMany
    {
        return $this->hasMany(TeethChartData::class, 'patient_id');
    }

    public function teethChartSurfaces(): HasMany
    {
        return $this->hasMany(TeethChartSurface::class, 'patient_id');
    }

    public function treatmentEntries(): HasMany
    {
        return $this->hasMany(PatientTreatmentEntry::class, 'patient_id');
    }

    public function paymentRecords(): HasMany
    {
        return $this->hasMany(PatientPaymentRecord::class, 'patient_id');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'patient_id');
    }

    public function assignedDentist(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class, 'assigned_dentist_id');
    }

    protected static function newFactory(): PatientFactory
    {
        return PatientFactory::new();
    }
}
