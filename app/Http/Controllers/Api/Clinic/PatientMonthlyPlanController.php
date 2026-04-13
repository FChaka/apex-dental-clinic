<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Concerns\InteractsWithClinicPatient;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Patient;
use App\Models\Tenant\PatientMonthlyPlan;
use App\Services\DataScopeService;
use App\Support\JsonApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class PatientMonthlyPlanController extends Controller
{
    use InteractsWithClinicPatient;

    public function __construct(
        private readonly DataScopeService $dataScope,
    ) {}

    public function index(Patient $patient): JsonResponse
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        if ($response = $this->guardPatientAccess($this->dataScope, $staff, $patient)) {
            return $response;
        }

        $items = PatientMonthlyPlan::query()
            ->where('patient_id', $patient->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (PatientMonthlyPlan $p) => $this->serializePlan($p))
            ->values()
            ->all();

        return JsonApiResponse::success($items, 'OK');
    }

    public function store(Request $request, Patient $patient): JsonResponse
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        if ($response = $this->guardPatientAccess($this->dataScope, $staff, $patient)) {
            return $response;
        }

        $validated = $request->validate([
            'plan_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'months' => ['required', 'integer', 'min:1'],
            'interest_percent' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'payment_day_of_month' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:31'],
            'initial_payment' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ]);

        $plan = PatientMonthlyPlan::query()->create([
            'patient_id' => $patient->id,
            'plan_name' => $validated['plan_name'] ?? null,
            'total_amount' => $validated['total_amount'],
            'months' => $validated['months'],
            'interest_percent' => $validated['interest_percent'] ?? 0,
            'start_date' => $validated['start_date'] ?? null,
            'payment_day_of_month' => $validated['payment_day_of_month'] ?? null,
            'initial_payment' => $validated['initial_payment'] ?? null,
        ]);

        return JsonApiResponse::success(
            $this->serializePlan($plan),
            'Monthly plan created successfully.',
            201
        );
    }

    public function update(Request $request, Patient $patient, PatientMonthlyPlan $plan): JsonResponse
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        if ($response = $this->guardPatientAccess($this->dataScope, $staff, $patient)) {
            return $response;
        }

        if ((int) $plan->patient_id !== (int) $patient->id) {
            return response()->json(['message' => 'Plan not found.'], 404);
        }

        $validated = $request->validate([
            'plan_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'total_amount' => ['sometimes', 'required', 'numeric', 'min:0'],
            'months' => ['sometimes', 'required', 'integer', 'min:1'],
            'interest_percent' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'payment_day_of_month' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:31'],
            'initial_payment' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ]);

        $plan->update($validated);

        return JsonApiResponse::success(
            $this->serializePlan($plan->fresh()),
            'Monthly plan updated successfully.'
        );
    }

    public function destroy(Patient $patient, PatientMonthlyPlan $plan): Response
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        if ($response = $this->guardPatientAccess($this->dataScope, $staff, $patient)) {
            return $response;
        }

        if ((int) $plan->patient_id !== (int) $patient->id) {
            return response()->json(['message' => 'Plan not found.'], 404);
        }

        $plan->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePlan(PatientMonthlyPlan $p): array
    {
        return [
            'id' => $p->id,
            'patient_id' => $p->patient_id,
            'plan_name' => $p->plan_name,
            'total_amount' => (float) $p->total_amount,
            'months' => $p->months,
            'interest_percent' => (float) $p->interest_percent,
            'start_date' => $p->start_date?->format('Y-m-d'),
            'payment_day_of_month' => $p->payment_day_of_month,
            'initial_payment' => $p->initial_payment !== null ? (float) $p->initial_payment : null,
            'created_at' => $p->created_at?->toIso8601String(),
            'updated_at' => $p->updated_at?->toIso8601String(),
        ];
    }
}
