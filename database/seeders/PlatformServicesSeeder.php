<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Central\PlatformService;
use Illuminate\Database\Seeder;

class PlatformServicesSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['key' => 'core', 'name' => 'Core Clinic Platform', 'type' => 'core', 'billing_model' => 'included', 'unit_label' => null, 'launched_at' => '2026-01-01'],
            ['key' => 'sms', 'name' => 'SMS Notifications (Twilio)', 'type' => 'addon', 'billing_model' => 'per_unit', 'unit_label' => 'SMS', 'launched_at' => '2026-04-01'],
            ['key' => 'ai_smile', 'name' => 'AI Smile Design', 'type' => 'addon', 'billing_model' => 'per_unit', 'unit_label' => 'generation', 'launched_at' => null],
            ['key' => 'social_media', 'name' => 'Social Media Studio', 'type' => 'addon', 'billing_model' => 'flat', 'unit_label' => null, 'launched_at' => null],
            ['key' => 'whatsapp', 'name' => 'WhatsApp Business', 'type' => 'addon', 'billing_model' => 'per_unit', 'unit_label' => 'message', 'launched_at' => null],
            ['key' => 'storage', 'name' => 'Extra Storage', 'type' => 'addon', 'billing_model' => 'tiered', 'unit_label' => 'GB', 'launched_at' => null],
        ];

        foreach ($rows as $row) {
            PlatformService::query()->updateOrCreate(
                ['key' => $row['key']],
                [
                    'name' => $row['name'],
                    'description' => null,
                    'type' => $row['type'],
                    'billing_model' => $row['billing_model'],
                    'unit_label' => $row['unit_label'],
                    'default_unit_price' => null,
                    'default_flat_price' => null,
                    'is_active' => true,
                    'launched_at' => $row['launched_at'],
                ],
            );
        }
    }
}
