<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Tenant\Appointment;
use Carbon\CarbonImmutable;

/**
 * Server-side analogue of SPA effective appointment rows: past slots with stored "Upcoming" are surfaced as Completed.
 */
final class AppointmentEffectiveDisplay
{
    public static function effectiveStatus(Appointment $appointment, CarbonImmutable $nowClinicTz): string
    {
        $stored = trim((string) $appointment->status);

        if (in_array($stored, ['Cancelled', 'No Show', 'Completed'], true)) {
            return $stored;
        }

        if ($stored !== 'Upcoming') {
            return $stored;
        }

        $startAtClinic = self::appointmentStartAtClinic($appointment);
        if ($startAtClinic === null) {
            return 'Upcoming';
        }

        if ($startAtClinic->lte($nowClinicTz)) {
            return 'Completed';
        }

        return 'Upcoming';
    }

    /**
     * Start instant in clinic timezone (for ordering), or null if unparseable.
     */
    public static function appointmentStartAtClinic(Appointment $appointment): ?CarbonImmutable
    {
        $tz = ClinicAppTimezone::current();

        $startsAt = $appointment->starts_at;
        if ($startsAt !== null) {
            return CarbonImmutable::createFromInterface($startsAt)->setTimezone($tz);
        }

        $utc = Appointment::computeStartsAtUtcFromDateAndTime($appointment->date, (string) $appointment->time);
        if ($utc === null) {
            try {
                $dateStr = $appointment->date?->format('Y-m-d') ?? '';

                return CarbonImmutable::parse($dateStr.' 09:00:00', $tz);
            } catch (\Throwable) {
                return null;
            }
        }

        return $utc->setTimezone($tz);
    }
}
