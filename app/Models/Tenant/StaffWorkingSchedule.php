<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

final class StaffWorkingSchedule extends Model
{
    public $timestamps = false;

    protected $table = 'staff_working_schedules';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'staff_id',
        'day_of_week',
        'is_open',
        'start_hour',
        'end_hour',
    ];

    protected function casts(): array
    {
        return [
            'is_open' => 'boolean',
            'day_of_week' => 'integer',
            'start_hour' => 'integer',
            'end_hour' => 'integer',
        ];
    }
}
