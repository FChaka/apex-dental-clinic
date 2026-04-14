<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

final class ClinicSchedule extends Model
{
    protected $table = 'clinic_schedules';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'day_of_week',
        'is_open',
        'start_hour',
        'end_hour',
    ];

    protected function casts(): array
    {
        return [
            'is_open' => 'boolean',
        ];
    }
}
