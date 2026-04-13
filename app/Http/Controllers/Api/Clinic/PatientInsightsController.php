<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Concerns\InteractsWithClinicPatient;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Appointment;
use App\Models\Tenant\Patient;
use App\Models\Tenant\PatientPaymentRecord;
use App\Models\Tenant\PatientTreatmentEntry;
use App\Models\Tenant\TreatmentType;
use App\Services\DataScopeService;
use App\Support\JsonApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class PatientInsightsController extends Controller
{
    use InteractsWithClinicPatient;

    public function __construct(
        private readonly DataScopeService $dataScope,
    ) {}

    public function show(Patient $patient): JsonResponse
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        if ($response = $this->guardPatientAccess($this->dataScope, $staff, $patient)) {
            return $response;
        }

        $totalBilled = (float) PatientTreatmentEntry::query()
            ->where('patient_id', $patient->id)
            ->sum('price');

        $totalPaid = (float) PatientPaymentRecord::query()
            ->where('patient_id', $patient->id)
            ->sum('amount');

        $completedAppointments = Appointment::query()
            ->where('patient_id', $patient->id)
            ->where('status', 'Completed')
            ->orderBy('date')
            ->get(['date']);

        $totalVisits = $completedAppointments->count();

        $lastVisit = $completedAppointments->isNotEmpty()
            ? $completedAppointments->last()->date->format('Y-m-d')
            : null;

        $mostFrequent = PatientTreatmentEntry::query()
            ->where('patient_id', $patient->id)
            ->selectRaw('treatment_type_id, COUNT(*) as cnt')
            ->groupBy('treatment_type_id')
            ->orderByDesc('cnt')
            ->first();

        $mostFrequentTreatment = null;
        if ($mostFrequent !== null) {
            $mostFrequentTreatment = TreatmentType::query()
                ->whereKey($mostFrequent->treatment_type_id)
                ->value('name');
        }

        $avgInterval = $this->averageAppointmentIntervalDays(
            $completedAppointments->pluck('date')->filter()
        );

        return JsonApiResponse::success([
            'total_billed' => round($totalBilled, 2),
            'total_paid' => round($totalPaid, 2),
            'outstanding_balance' => round($totalBilled - $totalPaid, 2),
            'total_visits' => $totalVisits,
            'last_visit' => $lastVisit,
            'most_frequent_treatment' => $mostFrequentTreatment,
            'avg_appointment_interval_days' => $avgInterval,
        ], 'OK');
    }

    /**
     * @param  Collection<int, Carbon|string>  $dates
     */
    private function averageAppointmentIntervalDays(Collection $dates): ?float
    {
        if ($dates->count() < 2) {
            return null;
        }

        $sorted = $dates
            ->map(fn ($d) => $d instanceof Carbon ? $d : Carbon::parse((string) $d))
            ->sortBy(fn (Carbon $d) => $d->timestamp)
            ->values();

        $intervals = [];
        $prev = null;
        foreach ($sorted as $d) {
            if ($prev instanceof Carbon) {
                $intervals[] = $prev->diffInDays($d);
            }
            $prev = $d;
        }

        return round(array_sum($intervals) / count($intervals), 2);
    }
}
