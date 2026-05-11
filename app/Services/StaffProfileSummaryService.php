<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant\Appointment;
use App\Models\Tenant\StaffMember;
use App\Models\Tenant\TreatmentRecord;
use App\Support\AppointmentEffectiveDisplay;
use App\Support\ClinicAppTimezone;
use App\Support\TreatmentRecordPaymentSemantics;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * §7.5.1 Staff profile summary aggregates. Clinic boundaries use {@see ClinicAppTimezone::current()}.
 * Non-clinical staff: workload and revenue surfaces are zeroed / empty (see inline comments).
 * Recent appointments window: `appointments.date >= today - 2 years` in clinic TZ, then merged sort (upcoming by soonest, then past/recents by latest).
 */
final class StaffProfileSummaryService
{
    /**
     * @return array{
     *     summary: array{
     *         years_experience: ?int,
     *         this_week_completed_cases: int,
     *         monthly_completed_cases: int,
     *         total_cases: int,
     *         upcoming_appointments: int,
     *     },
     *     treatments_by_type: list<array{treatment_name: string, count: int}>,
     *     recent_appointments: list<array{
     *         date: string,
     *         time: string,
     *         patient_name: string,
     *         treatment: string,
     *         status: string,
     *         effective_status: string,
     *     }>,
     *     revenue: array{total: float, paid: float, pending: float},
     * }
     */
    public function build(StaffMember $target, string $treatmentsPeriod, string $revenuePeriod, int $appointmentsLimit): array
    {
        if (! $this->isClinicalStaff($target)) {
            return [
                'summary' => [
                    'years_experience' => $this->parseYearsExperience($target->experience),
                    'this_week_completed_cases' => 0,
                    'monthly_completed_cases' => 0,
                    'total_cases' => 0,
                    'upcoming_appointments' => 0,
                ],
                'treatments_by_type' => [],
                'recent_appointments' => [],
                // Non-clinical targets do not attribute treatment revenue per product model.
                'revenue' => ['total' => 0.0, 'paid' => 0.0, 'pending' => 0.0],
            ];
        }

        $tz = ClinicAppTimezone::current();
        $nowTz = CarbonImmutable::now($tz);
        $dentistId = (int) $target->id;

        $treatmentBounds = $this->periodInclusiveDateBounds($treatmentsPeriod, $nowTz);
        $revenueBounds = $this->periodInclusiveDateBounds($revenuePeriod, $nowTz);

        $weekStart = $nowTz->startOfWeek(CarbonInterface::MONDAY);
        $weekEnd = $weekStart->endOfWeek(CarbonInterface::MONDAY);

        $thisWeekCompleted = TreatmentRecord::query()
            ->where('dentist_id', $dentistId)
            ->where('status', 'Completed')
            ->whereDate('date', '>=', $weekStart->toDateString())
            ->whereDate('date', '<=', $weekEnd->toDateString())
            ->count();

        $monthStart = $nowTz->startOfMonth();
        $monthEnd = $nowTz->endOfMonth();
        $monthlyCompleted = TreatmentRecord::query()
            ->where('dentist_id', $dentistId)
            ->where('status', 'Completed')
            ->whereDate('date', '>=', $monthStart->toDateString())
            ->whereDate('date', '<=', $monthEnd->toDateString())
            ->count();

        // Lifetime count of all treatment rows for this dentist (status-agnostic; blueprint allows ambiguity).
        $totalCases = TreatmentRecord::query()->where('dentist_id', $dentistId)->count();

        $treatmentsByType = $this->treatmentsByType($dentistId, $treatmentBounds);

        $revenueRecords = $this->treatmentRecordsInBounds($dentistId, $revenueBounds)->get();
        $revenueTotals = TreatmentRecordPaymentSemantics::sumPaidPendingTotals($revenueRecords);

        $minApptDate = $nowTz->subYears(2)->toDateString();

        /** @var Collection<int, Appointment> $appts */
        $appts = Appointment::query()
            ->where('dentist_id', $dentistId)
            ->whereDate('date', '>=', $minApptDate)
            ->with(['patient:id,name,surname', 'treatmentType:id,name'])
            ->get();

        $upcomingCount = $appts
            ->filter(fn (Appointment $a) => AppointmentEffectiveDisplay::effectiveStatus($a, $nowTz) === 'Upcoming')
            ->count();

        $recent = $this->buildRecentAppointments($appts, $nowTz, $tz, $appointmentsLimit);

        return [
            'summary' => [
                'years_experience' => $this->parseYearsExperience($target->experience),
                'this_week_completed_cases' => $thisWeekCompleted,
                'monthly_completed_cases' => $monthlyCompleted,
                'total_cases' => $totalCases,
                'upcoming_appointments' => $upcomingCount,
            ],
            'treatments_by_type' => $treatmentsByType,
            'recent_appointments' => $recent,
            'revenue' => $revenueTotals,
        ];
    }

    private function isClinicalStaff(StaffMember $staff): bool
    {
        return in_array((string) $staff->role, ['Dentist', 'Dental Hygienist'], true);
    }

    private function parseYearsExperience(?string $raw): ?int
    {
        if ($raw === null) {
            return null;
        }

        $t = trim($raw);
        if ($t === '') {
            return null;
        }

        // Prefer trailing digits ("12 years").
        if (preg_match('/(\d+)\s*$/', $t, $m) === 1) {
            return (int) $m[1];
        }

        if (preg_match_all('/(\d+)/', $t, $m) > 0) {
            $ints = array_map(static fn ($s) => (int) $s, $m[1]);

            return (int) end($ints);
        }

        return null;
    }

    /**
     * @return array{0: string, 1: string}|null inclusive Y-m-d bounds, or null when unbounded (all time).
     */
    private function periodInclusiveDateBounds(string $period, CarbonImmutable $nowTz): ?array
    {
        return match ($period) {
            'month' => [
                $nowTz->startOfMonth()->toDateString(),
                $nowTz->endOfMonth()->toDateString(),
            ],
            'year' => [
                $nowTz->startOfYear()->toDateString(),
                $nowTz->endOfYear()->toDateString(),
            ],
            'all' => null,
            default => [
                $nowTz->startOfMonth()->toDateString(),
                $nowTz->endOfMonth()->toDateString(),
            ],
        };
    }

    /**
     * @param  array{0: string, 1: string}|null  $bounds
     * @return list<array{treatment_name: string, count: int}>
     */
    private function treatmentsByType(int $dentistId, ?array $bounds): array
    {
        $query = TreatmentRecord::query()
            ->where('dentist_id', $dentistId)
            ->selectRaw('name, COUNT(*) as cnt')
            ->groupBy('name');

        if ($bounds !== null) {
            $query
                ->whereDate('date', '>=', $bounds[0])
                ->whereDate('date', '<=', $bounds[1]);
        }

        $rows = $query->get();

        /** @var Collection<int, object{name: string, cnt: int|string}> $rows */
        return $rows
            ->sort(function ($left, $right): int {
                $lc = (int) $left->cnt;
                $rc = (int) $right->cnt;
                if ($lc !== $rc) {
                    return $rc <=> $lc;
                }

                return strcmp((string) $left->name, (string) $right->name);
            })
            ->values()
            ->map(fn ($r) => [
                'treatment_name' => (string) $r->name,
                'count' => (int) $r->cnt,
            ])
            ->all();
    }

    /**
     * @param  array{0: string, 1: string}|null  $bounds
     * @return Builder<TreatmentRecord>
     */
    private function treatmentRecordsInBounds(int $dentistId, ?array $bounds)
    {
        $q = TreatmentRecord::query()->where('dentist_id', $dentistId);

        if ($bounds !== null) {
            $q
                ->whereDate('date', '>=', $bounds[0])
                ->whereDate('date', '<=', $bounds[1]);
        }

        return $q;
    }

    /**
     * @param  Collection<int, Appointment>  $appts
     * @return list<array{date: string, time: string, patient_name: string, treatment: string, status: string, effective_status: string}>
     */
    private function buildRecentAppointments(Collection $appts, CarbonImmutable $nowTz, string $tz, int $limit): array
    {
        /** @var Collection<int, Appointment> $upcomingSorted */
        $upcomingSorted = $appts
            ->filter(fn (Appointment $a) => AppointmentEffectiveDisplay::effectiveStatus($a, $nowTz) === 'Upcoming')
            ->sortBy(fn (Appointment $a) => $this->appointmentSortInstant($a, $tz)->timestamp)
            ->values();

        /** @var Collection<int, Appointment> $otherSorted */
        $otherSorted = $appts
            ->filter(fn (Appointment $a) => AppointmentEffectiveDisplay::effectiveStatus($a, $nowTz) !== 'Upcoming')
            ->sortByDesc(fn (Appointment $a) => $this->appointmentSortInstant($a, $tz)->timestamp)
            ->values();

        $merged = $upcomingSorted->concat($otherSorted)->take($limit);

        return $merged
            ->map(function (Appointment $a) use ($nowTz): array {
                $patient = $a->patient;
                $patientName = $patient !== null
                    ? trim($patient->name.' '.$patient->surname)
                    : '';

                $treatment = trim((string) $a->treatment);
                if ($treatment === '' && $a->treatmentType !== null) {
                    $treatment = (string) $a->treatmentType->name;
                }

                return [
                    'date' => $a->date instanceof CarbonInterface ? $a->date->format('Y-m-d') : (string) $a->date,
                    'time' => trim((string) $a->time),
                    'patient_name' => $patientName,
                    'treatment' => $treatment,
                    'status' => (string) $a->status,
                    'effective_status' => AppointmentEffectiveDisplay::effectiveStatus($a, $nowTz),
                ];
            })
            ->values()
            ->all();
    }

    private function appointmentSortInstant(Appointment $a, string $tz): CarbonImmutable
    {
        $parsed = AppointmentEffectiveDisplay::appointmentStartAtClinic($a);
        if ($parsed !== null) {
            return $parsed;
        }

        $dateStr = $a->date instanceof CarbonInterface ? $a->date->format('Y-m-d') : '';

        return CarbonImmutable::parse(($dateStr !== '' ? $dateStr : '1970-01-01').' 12:00:00', $tz);
    }
}
