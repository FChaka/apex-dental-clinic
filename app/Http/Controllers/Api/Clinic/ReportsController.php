<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Concerns\InteractsWithClinicStaff;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\DailyReportRequest;
use App\Http\Requests\Tenant\ReportsOverviewRequest;
use App\Models\Tenant\Appointment;
use App\Models\Tenant\Patient;
use App\Models\Tenant\TreatmentRecord;
use App\Services\DataScopeService;
use App\Support\ClinicAppTimezone;
use App\Support\JsonApiResponse;
use App\Support\TreatmentRecordPaymentSemantics;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

final class ReportsController extends Controller
{
    use InteractsWithClinicStaff;

    public function __construct(
        private readonly DataScopeService $dataScope,
    ) {}

    public function overview(ReportsOverviewRequest $request): JsonResponse
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        $validated = $request->validated();
        $monthsCount = match ($validated['period']) {
            '3m' => 3,
            '12m' => 12,
            default => 6,
        };

        $scopeId = $this->dataScope->resolveTargetDentistId(
            $staff,
            isset($validated['dentist_id']) ? (int) $validated['dentist_id'] : null,
            'reports_wide',
        );

        $tz = ClinicAppTimezone::current();
        $nowMonthStart = CarbonImmutable::now($tz)->startOfMonth();
        $firstMonth = $nowMonthStart->subMonths($monthsCount - 1)->startOfMonth();
        $lastMonthEnd = $nowMonthStart->endOfMonth();

        $startDate = $firstMonth->toDateString();
        $endDate = $lastMonthEnd->toDateString();

        $totalAppointments = Appointment::query()
            ->clinicalOnly()
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate)
            ->when($scopeId !== null, fn ($q) => $q->where('dentist_id', $scopeId))
            ->count();

        $paidRevenueQuery = TreatmentRecord::query()
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate)
            ->when($scopeId !== null, fn ($q) => $q->where('dentist_id', $scopeId))
            ->where(function ($q): void {
                $q->where('payment_status', 'Paid')->orWhere('price', '<=', 0);
            });
        $totalRevenue = (float) $paidRevenueQuery->sum('price');

        $avgRevenuePerAppointment = $totalAppointments > 0
            ? round($totalRevenue / $totalAppointments, 2)
            : 0.0;

        $appointmentTrend = [];
        $revenueTrend = [];
        $newPatientsTrend = [];

        for ($i = 0; $i < $monthsCount; $i++) {
            $bucket = $firstMonth->addMonths($i);
            $mStartStr = $bucket->startOfMonth()->toDateString();
            $mEndStr = $bucket->endOfMonth()->toDateString();

            $apptCount = Appointment::query()
                ->clinicalOnly()
                ->whereDate('date', '>=', $mStartStr)
                ->whereDate('date', '<=', $mEndStr)
                ->when($scopeId !== null, fn ($q) => $q->where('dentist_id', $scopeId))
                ->count();

            $monthRevenue = (float) TreatmentRecord::query()
                ->whereDate('date', '>=', $mStartStr)
                ->whereDate('date', '<=', $mEndStr)
                ->when($scopeId !== null, fn ($q) => $q->where('dentist_id', $scopeId))
                ->where(function ($q): void {
                    $q->where('payment_status', 'Paid')->orWhere('price', '<=', 0);
                })
                ->sum('price');

            $utcStart = $bucket->startOfMonth()->startOfDay()->utc();
            $utcEnd = $bucket->endOfMonth()->endOfDay()->utc();

            $newPatients = Patient::query()
                ->whereBetween('created_at', [$utcStart, $utcEnd])
                ->count();

            $label = $bucket->startOfMonth()->format('M');
            $ym = $bucket->startOfMonth()->format('Y-m');

            $appointmentTrend[] = ['month' => $ym, 'label' => $label, 'count' => $apptCount];
            $revenueTrend[] = ['month' => $ym, 'label' => $label, 'revenue' => round((float) $monthRevenue, 2)];
            $newPatientsTrend[] = ['month' => $ym, 'label' => $label, 'count' => $newPatients];
        }

        $procedureRows = TreatmentRecord::query()
            ->selectRaw('name, COUNT(*) as cnt, SUM(price) as rev')
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate)
            ->when($scopeId !== null, fn ($q) => $q->where('dentist_id', $scopeId))
            ->groupBy('name')
            ->get();

        /** @var Collection<int, array{name: string, count: int, revenue: float}> $ranked */
        $ranked = $procedureRows->map(fn ($r) => [
            'name' => (string) $r->name,
            'count' => (int) $r->cnt,
            'revenue' => (float) $r->rev,
        ])->sortByDesc('revenue')->values();

        $proceduresMix = $this->buildProceduresMix($ranked);

        return JsonApiResponse::success([
            'kpis' => [
                'total_revenue' => round((float) $totalRevenue, 2),
                'total_appointments' => $totalAppointments,
                'new_patients' => Patient::query()
                    ->whereBetween('created_at', [
                        $firstMonth->startOfDay()->utc(),
                        $lastMonthEnd->endOfDay()->utc(),
                    ])
                    ->count(),
                'avg_revenue_per_appointment' => $avgRevenuePerAppointment,
            ],
            'appointment_trend' => $appointmentTrend,
            'revenue_trend' => $revenueTrend,
            'new_patients_trend' => $newPatientsTrend,
            'procedures_mix' => $proceduresMix,
        ]);
    }

    /**
     * @param  Collection<int, array{name: string, count: int, revenue: float}>  $sortedByRevenueDesc
     * @return list<array{treatment_type: string, count: int, revenue: float}>
     */
    private function buildProceduresMix(Collection $sortedByRevenueDesc): array
    {
        if ($sortedByRevenueDesc->count() <= 8) {
            return $sortedByRevenueDesc
                ->map(fn (array $r) => [
                    'treatment_type' => $r['name'],
                    'count' => $r['count'],
                    'revenue' => round($r['revenue'], 2),
                ])
                ->values()
                ->all();
        }

        $top = $sortedByRevenueDesc->take(8)->values();
        $rest = $sortedByRevenueDesc->slice(8)->values();
        $out = $top->map(fn (array $r) => [
            'treatment_type' => $r['name'],
            'count' => $r['count'],
            'revenue' => round($r['revenue'], 2),
        ])->all();
        $out[] = [
            'treatment_type' => 'Other',
            'count' => $rest->sum('count'),
            'revenue' => round((float) $rest->sum('revenue'), 2),
        ];

        return $out;
    }

    public function daily(DailyReportRequest $request): JsonResponse
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        $validated = $request->validated();
        $scopeId = $this->dataScope->resolveTargetDentistId(
            $staff,
            isset($validated['dentist_id']) ? (int) $validated['dentist_id'] : null,
            'reports_wide',
        );
        $date = (string) $validated['date'];

        $query = TreatmentRecord::query()
            ->with([
                'patient' => fn ($q) => $q->select('id', 'name', 'surname'),
                'dentist' => fn ($q) => $q->select('id', 'name'),
            ])
            ->whereDate('date', $date);

        if ($scopeId !== null) {
            $query->where('dentist_id', $scopeId);
        }

        $records = $query->orderBy('id')->get();

        /** @var float $totalPaid */
        $totalPaid = 0.0;
        /** @var float $totalPending */
        $totalPending = 0.0;
        foreach ($records as $r) {
            if (TreatmentRecordPaymentSemantics::isEffectivePaid($r->price, $r->payment_status)) {
                $totalPaid += (float) $r->price;
            } else {
                $totalPending += (float) $r->price;
            }
        }

        $rows = $records->map(function (TreatmentRecord $r): array {
            $patientName = '';
            if ($r->patient !== null) {
                $patientName = trim($r->patient->name.' '.$r->patient->surname);
            }

            return [
                'id' => $r->id,
                'patient_name' => $patientName,
                'treatment_type' => $r->name,
                'price' => round((float) $r->price, 2),
                'payment_status' => $this->paymentStatusNormalized($r->payment_status),
                'effective_payment_status' => TreatmentRecordPaymentSemantics::effectivePaymentStatus((float) $r->price, (string) $r->payment_status),
                'dentist_id' => $r->dentist_id,
            ];
        })->values()->all();

        $byTreatment = $records
            ->groupBy('name')
            ->map(function (Collection $group, string $name): array {
                return [
                    'treatment_type' => $name,
                    'count' => $group->count(),
                    'total' => round((float) $group->sum('price'), 2),
                ];
            })
            ->values()
            ->all();

        $byStaff = [];
        if ($this->dataScope->isReportsWide($staff) && $scopeId === null) {
            $byStaff = $this->buildByStaffAggregates($records);
        }

        return JsonApiResponse::success([
            'date' => $date,
            'totals' => [
                'total' => round((float) $records->sum('price'), 2),
                'paid' => round($totalPaid, 2),
                'pending' => round($totalPending, 2),
            ],
            'by_staff' => $byStaff,
            'by_treatment' => $byTreatment,
            'rows' => $rows,
        ]);
    }

    /**
     * @param  Collection<int, TreatmentRecord>  $records
     * @return list<array{staff_id: int, name: string, total: float, paid: float, pending: float}>
     */
    private function buildByStaffAggregates(Collection $records): array
    {
        return $records
            ->groupBy('dentist_id')
            ->map(function (Collection $group): array {
                /** @var TreatmentRecord $first */
                $first = $group->first();
                $paid = 0.0;
                $pending = 0.0;
                foreach ($group as $r) {
                    if (TreatmentRecordPaymentSemantics::isEffectivePaid($r->price, $r->payment_status)) {
                        $paid += (float) $r->price;
                    } else {
                        $pending += (float) $r->price;
                    }
                }

                $name = $first->dentist !== null ? (string) $first->dentist->name : '';

                return [
                    'staff_id' => (int) $first->dentist_id,
                    'name' => $name,
                    'total' => round((float) $group->sum('price'), 2),
                    'paid' => round($paid, 2),
                    'pending' => round($pending, 2),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return 'paid'|'pending'
     */
    private function paymentStatusNormalized(string $paymentStatus): string
    {
        return strcasecmp(trim($paymentStatus), 'Paid') === 0 ? 'paid' : 'pending';
    }
}
