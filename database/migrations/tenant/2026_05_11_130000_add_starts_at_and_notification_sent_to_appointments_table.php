<?php

declare(strict_types=1);

use App\Models\Tenant\Appointment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->timestamp('starts_at')->nullable()->after('time');
            $table->boolean('notification_sent')->default(false)->after('starts_at');
            $table->index(['notification_sent', 'starts_at']);
        });

        Appointment::query()->whereNull('starts_at')->orderBy('id')->chunkById(100, function ($appointments): void {
            foreach ($appointments as $appointment) {
                $utc = Appointment::computeStartsAtUtcFromDateAndTime($appointment->date, (string) $appointment->time);
                if ($utc === null) {
                    continue;
                }
                $appointment->starts_at = $utc;
                $appointment->saveQuietly();
            }
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropIndex(['notification_sent', 'starts_at']);
            $table->dropColumn(['starts_at', 'notification_sent']);
        });
    }
};
