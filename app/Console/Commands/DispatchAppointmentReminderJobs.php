<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SendAppointmentReminders;
use App\Models\Central\Clinic;
use Illuminate\Console\Command;

final class DispatchAppointmentReminderJobs extends Command
{
    protected $signature = 'appointments:dispatch-reminders';

    protected $description = 'Dispatch per-tenant queued jobs for 15-minute dentist appointment reminders';

    public function handle(): int
    {
        $query = Clinic::query()->whereIn('status', ['active', 'trial']);

        foreach ($query->cursor() as $clinic) {
            SendAppointmentReminders::dispatch($clinic);
        }

        return self::SUCCESS;
    }
}
