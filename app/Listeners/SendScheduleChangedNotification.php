<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\StaffScheduleChanged;
use App\Services\Notifications\NotificationService;

class SendScheduleChangedNotification
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function handle(StaffScheduleChanged $event): void
    {
        $affected = $event->affectedStaff;
        $changer = $event->changedBy;

        if ((int) $affected->id === (int) $changer->id) {
            return;
        }

        $name = $changer->name;

        $this->notifications->send(
            receiverStaffId: $affected->id,
            type: NotificationType::ScheduleChanged,
            message: "Your work schedule has been updated by {$name}.",
            path: '/profile',
            fromStaffId: $changer->id,
        );
    }
}
