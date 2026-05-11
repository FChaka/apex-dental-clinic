<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Enums\NotificationType;
use App\Models\Tenant\Appointment;
use App\Models\Tenant\Patient;
use App\Support\ClinicAppTimezone;
use Illuminate\Support\Facades\DB;

class AppointmentReminderService
{
    public function sendRemindersInFifteenMinuteWindow(NotificationService $notifications): void
    {
        $tz = ClinicAppTimezone::current();
        $windowStartUtc = now($tz)->copy()->addMinutes(14)->utc();
        $windowEndUtc = now($tz)->copy()->addMinutes(16)->utc();

        Appointment::query()
            ->where('status', 'Upcoming')
            ->whereNotNull('dentist_id')
            ->whereNotNull('starts_at')
            ->where('notification_sent', false)
            ->whereBetween('starts_at', [$windowStartUtc, $windowEndUtc])
            ->orderBy('id')
            ->chunkById(100, function ($appointments) use ($notifications, $windowStartUtc, $windowEndUtc, $tz): void {
                foreach ($appointments as $appointment) {
                    DB::transaction(function () use ($appointment, $notifications, $windowStartUtc, $windowEndUtc, $tz): void {
                        $row = Appointment::query()
                            ->whereKey($appointment->id)
                            ->where('notification_sent', false)
                            ->where('status', 'Upcoming')
                            ->whereBetween('starts_at', [$windowStartUtc, $windowEndUtc])
                            ->with(['patient' => fn ($q) => $q->select('id', 'name', 'surname')])
                            ->lockForUpdate()
                            ->first();

                        if ($row === null) {
                            return;
                        }

                        if (! $this->shouldSendReminderNow($row)) {
                            return;
                        }

                        $staffId = $row->dentist_id;
                        if ($staffId === null) {
                            return;
                        }

                        $patientName = $this->patientDisplayName($row->patient);
                        $time = $row->starts_at->timezone($tz)->format('H:i');
                        $marker = '(#'.$row->id.')';

                        $notifications->send(
                            receiverStaffId: (int) $staffId,
                            type: NotificationType::UpcomingAppointment,
                            message: "Reminder: you have an appointment at {$time} with {$patientName}. {$marker}",
                            path: '/appointments',
                            fromStaffId: null,
                        );

                        $row->forceFill(['notification_sent' => true])->saveQuietly();
                    });
                }
            });
    }

    protected function shouldSendReminderNow(Appointment $row): bool
    {
        return $row->starts_at !== null && $row->starts_at->greaterThan(now('UTC'));
    }

    private function patientDisplayName(?Patient $patient): string
    {
        if ($patient === null) {
            return 'a patient';
        }

        $parts = array_filter([
            is_string($patient->name) ? trim($patient->name) : '',
            is_string($patient->surname) ? trim($patient->surname) : '',
        ]);

        $full = implode(' ', $parts);

        return $full !== '' ? $full : 'a patient';
    }
}
