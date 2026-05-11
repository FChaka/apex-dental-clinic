<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Tenant\Appointment;

final class AppointmentObserver
{
    public function saving(Appointment $appointment): void
    {
        $startsAt = Appointment::computeStartsAtUtcFromDateAndTime($appointment->date, (string) $appointment->time);
        $appointment->starts_at = $startsAt;

        if ($appointment->exists && $appointment->status === 'Upcoming') {
            $dirtySlot = $appointment->isDirty('date') || $appointment->isDirty('time');
            if ($dirtySlot && $startsAt !== null && $startsAt->greaterThan(now('UTC'))) {
                $appointment->notification_sent = false;
            }
        }
    }
}
