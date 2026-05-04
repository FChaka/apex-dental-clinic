<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientXray extends Model
{
    protected $table = 'patient_xrays';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'patient_id',
        'title',
        'file_name',
        'file_path',
        'thumbnail_path',
        'mime_type',
        'file_size',
        'notes',
        'taken_at',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'taken_at' => 'date',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class, 'uploaded_by');
    }
}
