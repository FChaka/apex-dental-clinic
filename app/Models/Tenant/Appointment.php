<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
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
}
