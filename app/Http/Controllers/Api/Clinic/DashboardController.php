<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Concerns\InteractsWithClinicStaff;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\MonthlyRevenueRequest;
use App\Http\Requests\Tenant\WeeklyAppointmentsRequest;
use App\Models\Tenant\Appointment;
use App\Models\Tenant\Patient;
use App\Models\Tenant\TreatmentRecord;
use App\Services\DataScopeService;
use App\Support\ClinicAppTimezone;
use App\Support\JsonApiResponse;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;

final class DashboardController extends Controller
{
    use InteractsWithClinicStaff;

    public function __construct(
        private readonly DataScopeService $dataScope,
    ) {}

    public function stats(): JsonResponse
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        $tz = ClinicAppTimezone::current();
        $today = CarbonImmutable::now($tz)->toDateString();

        $totalPatients = Patient::query()->count();

        $apptQuery = Appointment::query()->clinicalOnly()->whereDate('date', $today);
        $calendarScopeId = $this->dataScope->resolveTargetDentistId($staff, null, 'calendar_wide');
        if ($calendarScopeId !== null) {
            $apptQuery->where('dentist_id', $calendarScopeId);
        }
        $todaysAppointments = $apptQuery->count();

        $reportsScopeId = $this->dataScope->resolveTargetDentistId($staff, null, 'reports_wide');
        $pendingQuery = TreatmentRecord::query()
            ->where('payment_status', 'Pending')
            ->where('price', '>', 0);
        if ($reportsScopeId !== null) {
            $pendingQuery->where('dentist_id', $reportsScopeId);
        }
        $pendingTreatments = $pendingQuery->count();

        $monthStart = CarbonImmutable::now($tz)->startOfMonth();
        $monthEnd = CarbonImmutable::now($tz)->endOfMonth();
        $revenueQuery = TreatmentRecord::query()
            ->whereDate('date', '>=', $monthStart->toDateString())
            ->whereDate('date', '<=', $monthEnd->toDateString())
            ->where(function ($q): void {
                $q->where('payment_status', 'Paid')->orWhere('price', '<=', 0);
            });
        if ($reportsScopeId !== null) {
            $revenueQuery->where('dentist_id', $reportsScopeId);
        }
        $monthlyRevenue = (float) $revenueQuery->sum('price');

        return JsonApiResponse::success([
            'total_patients' => $totalPatients,
            'todays_appointments' => $todaysAppointments,
            'pending_treatments' => $pendingTreatments,
            'monthly_revenue' => round($monthlyRevenue, 2),
        ]);
    }

    public function weeklyAppointments(WeeklyAppointmentsRequest $request): JsonResponse
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        $tz = ClinicAppTimezone::current();
        $validated = $request->validated();
        if (! empty($validated['week_start'])) {
            $weekStart = CarbonImmutable::parse((string) $validated['week_start'], $tz)
                ->startOfWeek(CarbonInterface::MONDAY);
        } else {
            $weekStart = CarbonImmutable::now($tz)->startOfWeek(CarbonInterface::MONDAY);
        }
        $weekEnd = $weekStart->addDays(6);

        $calendarScopeId = $this->dataScope->resolveTargetDentistId($staff, null, 'calendar_wide');

        $days = [];
        for ($offset = 0; $offset < 7; $offset++) {
            $d = $weekStart->addDays($offset);
            $dateStr = $d->toDateString();
            $q = Appointment::query()->clinicalOnly()->whereDate('date', $dateStr);
            if ($calendarScopeId !== null) {
                $q->where('dentist_id', $calendarScopeId);
            }
            $days[] = [
                'date' => $dateStr,
                'day' => $d->format('D'),
                'count' => $q->count(),
            ];
        }

        return JsonApiResponse::success([
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekEnd->toDateString(),
            'days' => $days,
        ]);
    }

    public function monthlyRevenue(MonthlyRevenueRequest $request): JsonResponse
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        $validated = $request->validated();
        $months = (int) ($validated['months'] ?? 6);
        $months = max(1, min(24, $months));

        $tz = ClinicAppTimezone::current();
        $reportsScopeId = $this->dataScope->resolveTargetDentistId($staff, null, 'reports_wide');

        $nowMonthStart = CarbonImmutable::now($tz)->startOfMonth();

        $series = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $monthStart = $nowMonthStart->subMonths($i)->startOfMonth();
            $monthEnd = $monthStart->endOfMonth();
            $q = TreatmentRecord::query()
                ->whereDate('date', '>=', $monthStart->toDateString())
                ->whereDate('date', '<=', $monthEnd->toDateString())
                ->where(function ($q): void {
                    $q->where('payment_status', 'Paid')->orWhere('price', '<=', 0);
                });
            if ($reportsScopeId !== null) {
                $q->where('dentist_id', $reportsScopeId);
            }
            $revenue = (float) $q->sum('price');
            $series[] = [
                'month' => $monthStart->format('Y-m'),
                'label' => $monthStart->format('M'),
                'revenue' => round($revenue, 2),
            ];
        }

        return JsonApiResponse::success(['series' => $series]);
    }
}
