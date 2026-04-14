<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

final class DateTimeSetting extends Model
{
    protected $table = 'date_time_settings';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'time_zone_mode',
        'manual_time_zone',
        'date_format',
    ];
}
