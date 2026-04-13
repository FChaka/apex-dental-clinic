<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientDocument extends Model
{
    protected $table = 'patient_documents';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'patient_id',
        'name',
        'file_name',
        'type',
        'file_path',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }
}
