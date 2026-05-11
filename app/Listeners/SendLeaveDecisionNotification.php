<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\LeaveRequestDecided;
use App\Services\Notifications\NotificationService;

class SendLeaveDecisionNotification
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function handle(LeaveRequestDecided $event): void
    {
        $lr = $event->leaveRequest;
        $requesterId = (int) $lr->staff_id;

        $type = $event->decision === 'approved'
            ? NotificationType::LeaveRequestApproved
            : NotificationType::LeaveRequestRejected;

        $word = $event->decision === 'approved' ? 'approved' : 'rejected';

        $this->notifications->send(
            receiverStaffId: $requesterId,
            type: $type,
            message: "Your leave request has been {$word}.",
            path: '/profile',
            fromStaffId: $event->decidedBy->id,
        );
    }
}
