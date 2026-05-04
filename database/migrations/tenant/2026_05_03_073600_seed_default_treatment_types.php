<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const DEFAULTS = [
        'Consultation',
        'Cleaning',
        'Root Canal',
        'Crown Placement',
        'Filling',
        'Implant Check',
        'Surgery',
        'Emergency',
    ];

    public function up(): void
    {
        $now = now();
        $rows = array_map(fn (string $name) => [
            'name' => $name,
            'description' => null,
            'default_duration' => null,
            'default_price' => null,
            'vat' => null,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], self::DEFAULTS);

        DB::table('treatment_types')->insert($rows);
    }

    public function down(): void
    {
        DB::table('treatment_types')->whereIn('name', self::DEFAULTS)->delete();
    }
};
