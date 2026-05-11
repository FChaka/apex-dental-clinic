<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Tenant\DateTimeSetting;

final class ClinicAppTimezone
{
    public static function current(): string
    {
        $setting = DateTimeSetting::query()->firstWhere('id', 1);

        if ($setting !== null
            && $setting->time_zone_mode === 'manual'
            && is_string($setting->manual_time_zone)
            && $setting->manual_time_zone !== '') {
            return $setting->manual_time_zone;
        }

        return (string) config('app.timezone', 'UTC');
    }
}
