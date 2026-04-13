<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Controller;
use App\Models\Tenant\StaffMember;
use App\Models\Tenant\TreatmentRecord;
use App\Services\DataScopeService;
use App\Support\JsonApiResponse;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

final class TreatmentRecordController extends Controller
{
    private const STATUS_VALUES = ['Completed', 'In Progress'];

    private const PAYMENT_STATUS_VALUES = ['Paid', 'Pending'];

    public function __construct(
        private readonly DataScopeService $dataScope,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        $validated = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'date_from' => ['sometimes', 'nullable', 'date'],
            'date_to' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', 'nullable', Rule::in(self::STATUS_VALUES)],
        ]);

        $query = TreatmentRecord::query()
            ->with([
                'patient' => fn ($q) => $q->select('id', 'name', 'surname'),
                'dentist' => fn ($q) => $q->select('id', 'name'),
            ]);

        $this->dataScope->scopeTreatmentRecords($query, $staff);

        if (! empty($validated['search'])) {
            $term = '%'.addcslashes($validated['search'], '%_\\').'%';
            $query->where('name', 'like', $term);
        }

        if (! empty($validated['date_from'])) {
            $query->whereDate('date', '>=', $validated['date_from']);
        }

        if (! empty($validated['date_to'])) {
            $query->whereDate('date', '<=', $validated['date_to']);
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $query->orderByDesc('date');

        $paginator = $query->paginate(20)->withQueryString();
        $paginator->setCollection(
            $paginator->getCollection()->map(fn (TreatmentRecord $r) => $this->serializeRecord($r))
        );

        return JsonApiResponse::paginated($paginator, 'OK');
    }

    public function store(Request $request): JsonResponse
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        $validated = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'dentist_id' => ['required', 'integer', 'exists:staff_members,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(self::STATUS_VALUES)],
            'date' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'min:1'],
            'price' => ['required', 'numeric', 'min:0'],
            'payment_status' => ['sometimes', Rule::in(self::PAYMENT_STATUS_VALUES)],
        ]);

        $record = TreatmentRecord::query()->create($validated);
        $record->load([
            'patient' => fn ($q) => $q->select('id', 'name', 'surname'),
            'dentist' => fn ($q) => $q->select('id', 'name'),
        ]);

        return JsonApiResponse::success($this->serializeRecord($record), 'Treatment record created successfully.', Response::HTTP_CREATED);
    }

    public function update(Request $request, TreatmentRecord $record): JsonResponse
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        if (! $this->canMutateRecord($staff, $record)) {
            return response()->json([
                'message' => 'You do not have permission to update this treatment record.',
            ], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'patient_id' => ['sometimes', 'integer', 'exists:patients,id'],
            'dentist_id' => ['sometimes', 'integer', 'exists:staff_members,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(self::STATUS_VALUES)],
            'date' => ['sometimes', 'date'],
            'duration_minutes' => ['sometimes', 'integer', 'min:1'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'payment_status' => ['sometimes', Rule::in(self::PAYMENT_STATUS_VALUES)],
        ]);

        if (! $this->isClinicAdmin($staff) && array_key_exists('dentist_id', $validated)
            && (int) $validated['dentist_id'] !== (int) $staff->id) {
            return response()->json([
                'message' => 'You cannot reassign this treatment record to another dentist.',
            ], Response::HTTP_FORBIDDEN);
        }

        $record->update($validated);
        $record->load([
            'patient' => fn ($q) => $q->select('id', 'name', 'surname'),
            'dentist' => fn ($q) => $q->select('id', 'name'),
        ]);

        return JsonApiResponse::success($this->serializeRecord($record->fresh()), 'Treatment record updated successfully.');
    }

    public function destroy(TreatmentRecord $record): JsonResponse|Response
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        if (! $this->canMutateRecord($staff, $record)) {
            return response()->json([
                'message' => 'You do not have permission to delete this treatment record.',
            ], Response::HTTP_FORBIDDEN);
        }

        $record->delete();

        return response()->noContent();
    }

    private function clinicStaff(): StaffMember|JsonResponse
    {
        $staff = auth('clinic_session')->user();
        if (! $staff instanceof StaffMember) {
            return JsonApiResponse::unauthorized();
        }

        return $staff;
    }

    private function isClinicAdmin(StaffMember $staff): bool
    {
        return in_array($staff->clinic_access_level, ['super_admin', 'admin'], true);
    }

    private function canMutateRecord(StaffMember $staff, TreatmentRecord $record): bool
    {
        if ($this->isClinicAdmin($staff)) {
            return true;
        }

        return (int) $record->dentist_id === (int) $staff->id;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRecord(TreatmentRecord $r): array
    {
        return [
            'id' => $r->id,
            'patient_id' => $r->patient_id,
            'dentist_id' => $r->dentist_id,
            'name' => $r->name,
            'description' => $r->description,
            'status' => $r->status,
            'date' => $r->date instanceof CarbonInterface ? $r->date->format('Y-m-d') : (string) $r->date,
            'duration_minutes' => $r->duration_minutes,
            'price' => $r->price,
            'payment_status' => $r->payment_status,
            'patient' => $r->relationLoaded('patient') && $r->patient !== null ? [
                'id' => $r->patient->id,
                'name' => $r->patient->name,
                'surname' => $r->patient->surname,
            ] : null,
            'dentist' => $r->relationLoaded('dentist') && $r->dentist !== null ? [
                'id' => $r->dentist->id,
                'name' => $r->dentist->name,
            ] : null,
        ];
    }
}
