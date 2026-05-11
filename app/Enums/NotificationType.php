<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationType: string
{
    case LeaveRequestSubmitted = 'leave_request_submitted';
    case LeaveRequestApproved = 'leave_request_approved';
    case LeaveRequestRejected = 'leave_request_rejected';
    case ScheduleChanged = 'schedule_changed';
    case UpcomingAppointment = 'upcoming_appointment';
}
