<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Central\Clinic;
use App\Services\Notifications\AppointmentReminderService;
use App\Services\Notifications\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendAppointmentReminders implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public Clinic $clinic) {}

    public function handle(AppointmentReminderService $reminders, NotificationService $notifications): void
    {
        $this->clinic->run(function () use ($reminders, $notifications): void {
            $reminders->sendRemindersInFifteenMinuteWindow($notifications);
        });
    }
}
