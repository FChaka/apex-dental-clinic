<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeethChartSurface extends Model
{
    protected $table = 'teeth_chart_surfaces';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'patient_id',
        'tooth_number',
        'surface_key',
        'values',
        'is_initial_exam',
    ];

    protected function casts(): array
    {
        return [
            'values' => 'array',
            'is_initial_exam' => 'boolean',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }
}
