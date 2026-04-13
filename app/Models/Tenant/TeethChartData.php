<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeethChartData extends Model
{
    protected $table = 'teeth_chart_data';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'patient_id',
        'tooth_number',
        'procedure',
        'is_initial_exam',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_initial_exam' => 'boolean',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }
}
