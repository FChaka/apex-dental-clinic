<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\LeaveRequestSubmitted;
use App\Models\Tenant\StaffMember;
use App\Services\Notifications\NotificationService;

class SendLeaveRequestNotification
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function handle(LeaveRequestSubmitted $event): void
    {
        $lr = $event->leaveRequest->loadMissing('staff');

        $requester = $lr->staff;
        $requesterName = $requester?->name ?? 'A staff member';
        $staffId = (int) $lr->staff_id;

        $recipients = StaffMember::query()
            ->forLeaveManagementAlerts()
            ->pluck('id')
            ->unique()
            ->values()
            ->all();

        $recipients = array_values(array_filter(
            $recipients,
            fn (int $id): bool => $id !== $staffId,
        ));

        foreach ($recipients as $receiverId) {
            $this->notifications->send(
                receiverStaffId: $receiverId,
                type: NotificationType::LeaveRequestSubmitted,
                message: "{$requesterName} has submitted a leave request.",
                path: '/staff?tab=leave-requests',
                fromStaffId: $staffId,
            );
        }
    }
}
