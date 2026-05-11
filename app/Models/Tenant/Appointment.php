<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Support\ClinicAppTimezone;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\Tenant\AppointmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    protected $table = 'appointments';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'patient_id',
        'dentist_id',
        'treatment_type_id',
        'date',
        'time',
        'treatment',
        'duration',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'time' => 'string',
            'duration' => 'integer',
            'starts_at' => 'immutable_datetime:UTC',
            'notification_sent' => 'boolean',
        ];
    }

    public static function computeStartsAtUtcFromDateAndTime(mixed $date, string $time): ?CarbonImmutable
    {
        $trimmedTime = trim($time);
        if ($date === null || $trimmedTime === '') {
            return null;
        }

        $dateStr = $date instanceof CarbonInterface ? $date->format('Y-m-d') : (string) $date;

        try {
            return CarbonImmutable::parse($dateStr.' '.$trimmedTime, ClinicAppTimezone::current())->utc();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  Builder<Appointment>  $query
     * @return Builder<Appointment>
     */
    public function scopeClinicalOnly(Builder $query): Builder
    {
        return $query->whereHas('dentist', function (Builder $q): void {
            $q->whereIn('role', ['Dentist', 'Dental Hygienist']);
        });
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function dentist(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class, 'dentist_id');
    }

    public function treatmentType(): BelongsTo
    {
        return $this->belongsTo(TreatmentType::class, 'treatment_type_id');
    }

    public function getEffectiveDurationAttribute(): ?int
    {
        if ($this->duration !== null) {
            return (int) $this->duration;
        }

        $default = $this->treatmentType?->default_duration;

        return $default !== null ? (int) $default : null;
    }

    protected static function newFactory(): AppointmentFactory
    {
        return AppointmentFactory::new();
    }
}
