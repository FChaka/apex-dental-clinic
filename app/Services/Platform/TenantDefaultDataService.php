<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Models\Central\Clinic;
use App\Models\Tenant\ClinicSchedule;
use App\Models\Tenant\ClinicSetting;
use App\Models\Tenant\DateTimeSetting;
use App\Models\Tenant\InvoiceSetting;
use Illuminate\Support\Facades\DB;

/**
 * Default rows for a newly provisioned tenant database (inside {@see Clinic::run()}).
 */
final class TenantDefaultDataService
{
    public static function seed(string $clinicDisplayName): void
    {
        foreach (range(0, 6) as $dayOfWeek) {
            $isWeekday = $dayOfWeek >= 1 && $dayOfWeek <= 5;

            ClinicSchedule::query()->create([
                'day_of_week' => $dayOfWeek,
                'is_open' => $isWeekday,
                'start_hour' => '08:00:00',
                'end_hour' => '17:00:00',
            ]);
        }

        ClinicSetting::query()->create([
            'clinic_name' => $clinicDisplayName,
        ]);

        InvoiceSetting::query()->create([]);

        DateTimeSetting::query()->create([]);

        $now = now();
        $categoryId = DB::table('planner_categories')->insertGetId([
            'label' => 'General',
            'sort_order' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('planner_materials')->insert([
            'category_id' => $categoryId,
            'name' => 'Default material',
            'default_price' => 0,
            'treatment_type_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
