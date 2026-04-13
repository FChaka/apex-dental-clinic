<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Database\Factories\Tenant\AppointmentFactory;
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
        'date',
        'time',
        'treatment',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'time' => 'string',
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

    protected static function newFactory(): AppointmentFactory
    {
        return AppointmentFactory::new();
    }
}
