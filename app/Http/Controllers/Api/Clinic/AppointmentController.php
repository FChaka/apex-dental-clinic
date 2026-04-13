<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Concerns\InteractsWithClinicPatient;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Appointment;
use App\Models\Tenant\StaffMember;
use App\Services\DataScopeService;
use App\Support\JsonApiResponse;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

final class AppointmentController extends Controller
{
    use InteractsWithClinicPatient;

    private const STATUS_VALUES = ['Upcoming', 'Completed', 'Cancelled', 'No Show'];

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
            'date_from' => ['sometimes', 'nullable', 'date'],
            'date_to' => ['sometimes', 'nullable', 'date'],
            'dentist_id' => ['sometimes', 'nullable', 'integer', 'exists:staff_members,id'],
            'status' => ['sometimes', 'nullable', Rule::in(self::STATUS_VALUES)],
            'patient_id' => ['sometimes', 'nullable', 'integer', 'exists:patients,id'],
        ]);

        $query = Appointment::query()
            ->with([
                'patient' => fn ($q) => $q->select('id', 'name', 'surname'),
                'dentist' => fn ($q) => $q->select('id', 'name', 'color'),
            ]);

        $this->dataScope->scopeAppointments($query, $staff);

        if (! empty($validated['date_from'])) {
            $query->whereDate('date', '>=', $validated['date_from']);
        }

        if (! empty($validated['date_to'])) {
            $query->whereDate('date', '<=', $validated['date_to']);
        }

        if ($this->isClinicAdmin($staff) && ! empty($validated['dentist_id'])) {
            $query->where('dentist_id', $validated['dentist_id']);
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['patient_id'])) {
            $query->where('patient_id', $validated['patient_id']);
        }

        $query->orderBy('date')->orderBy('time');

        $items = $query->get()->map(fn (Appointment $a) => $this->appointmentPayload($a))->values()->all();

        return JsonApiResponse::success($items, 'OK');
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
            'date' => ['required', 'date'],
            'time' => ['required', 'date_format:H:i'],
            'treatment' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(self::STATUS_VALUES)],
            'notes' => ['nullable', 'string'],
        ]);

        $date = $this->normalizeDateString($validated['date']);
        $time = $validated['time'];

        if ($this->dentistSlotTaken((int) $validated['dentist_id'], $date, $time)) {
            return response()->json([
                'message' => 'This dentist already has an appointment at this time.',
            ], 422);
        }

        $appointment = Appointment::query()->create([
            'patient_id' => $validated['patient_id'],
            'dentist_id' => $validated['dentist_id'],
            'date' => $date,
            'time' => $time,
            'treatment' => $validated['treatment'],
            'status' => $validated['status'] ?? 'Upcoming',
            'notes' => $validated['notes'] ?? null,
        ]);

        $appointment->load([
            'patient' => fn ($q) => $q->select('id', 'name', 'surname'),
            'dentist' => fn ($q) => $q->select('id', 'name', 'color'),
        ]);

        return JsonApiResponse::success($this->appointmentPayload($appointment), 'Appointment created successfully.', 201);
    }

    public function update(Request $request, Appointment $appointment): JsonResponse
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        if (! $this->canManageAppointment($staff, $appointment)) {
            return response()->json([
                'message' => 'You do not have permission to modify this appointment.',
            ], 403);
        }

        $validated = $request->validate([
            'patient_id' => ['sometimes', 'integer', 'exists:patients,id'],
            'dentist_id' => ['sometimes', 'integer', 'exists:staff_members,id'],
            'date' => ['sometimes', 'date'],
            'time' => ['sometimes', 'date_format:H:i'],
            'treatment' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(self::STATUS_VALUES)],
            'notes' => ['nullable', 'string'],
        ]);

        $dentistId = isset($validated['dentist_id']) ? (int) $validated['dentist_id'] : (int) $appointment->dentist_id;
        $date = isset($validated['date'])
            ? $this->normalizeDateString($validated['date'])
            : $this->normalizeDateString($appointment->date);
        $time = $validated['time'] ?? $this->formatTimeForApi($appointment->time);

        if ($this->dentistSlotTaken($dentistId, $date, $time, (int) $appointment->id)) {
            return response()->json([
                'message' => 'This dentist already has an appointment at this time.',
            ], 422);
        }

        $appointment->fill(array_intersect_key($validated, array_flip([
            'patient_id',
            'dentist_id',
            'treatment',
            'status',
            'notes',
        ])));

        if (isset($validated['date'])) {
            $appointment->date = $date;
        }

        if (isset($validated['time'])) {
            $appointment->time = $time;
        }

        $appointment->save();

        $appointment->load([
            'patient' => fn ($q) => $q->select('id', 'name', 'surname'),
            'dentist' => fn ($q) => $q->select('id', 'name', 'color'),
        ]);

        return JsonApiResponse::success($this->appointmentPayload($appointment), 'Appointment updated successfully.');
    }

    public function destroy(Appointment $appointment): Response
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        if (! $this->canManageAppointment($staff, $appointment)) {
            return response()->json([
                'message' => 'You do not have permission to delete this appointment.',
            ], 403);
        }

        $appointment->delete();

        return response()->noContent();
    }

    private function isClinicAdmin(StaffMember $staff): bool
    {
        return in_array($staff->clinic_access_level, ['super_admin', 'admin'], true);
    }

    private function canManageAppointment(StaffMember $staff, Appointment $appointment): bool
    {
        if ($this->isClinicAdmin($staff)) {
            return true;
        }

        return (int) $appointment->dentist_id === (int) $staff->id;
    }

    /**
     * @return array<string, mixed>
     */
    private function appointmentPayload(Appointment $row): array
    {
        $row->loadMissing([
            'patient' => fn ($q) => $q->select('id', 'name', 'surname'),
            'dentist' => fn ($q) => $q->select('id', 'name', 'color'),
        ]);

        return [
            'id' => $row->id,
            'patient_id' => $row->patient_id,
            'dentist_id' => $row->dentist_id,
            'date' => $this->normalizeDateString($row->date),
            'time' => $this->formatTimeForApi($row->time),
            'treatment' => $row->treatment,
            'status' => $row->status,
            'notes' => $row->notes,
            'patient' => $row->patient !== null ? [
                'id' => $row->patient->id,
                'name' => $row->patient->name,
                'surname' => $row->patient->surname,
            ] : null,
            'dentist' => $row->dentist !== null ? [
                'id' => $row->dentist->id,
                'name' => $row->dentist->name,
                'color' => $row->dentist->color,
            ] : null,
        ];
    }

    private function dentistSlotTaken(int $dentistId, string $date, string $time, ?int $ignoreId = null): bool
    {
        $slot = $this->formatTimeForApi($time);

        $query = Appointment::query()
            ->where('dentist_id', $dentistId)
            ->whereDate('date', $date);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->get()->contains(
            fn (Appointment $existing): bool => $this->formatTimeForApi($existing->time) === $slot
        );
    }

    private function normalizeDateString(mixed $value): string
    {
        if ($value instanceof CarbonInterface) {
            return $value->format('Y-m-d');
        }

        return (string) $value;
    }

    private function formatTimeForApi(mixed $value): string
    {
        if ($value instanceof CarbonInterface) {
            return $value->format('H:i');
        }

        if (is_string($value) && strlen($value) >= 5) {
            return substr($value, 0, 5);
        }

        return is_string($value) ? $value : '00:00';
    }
}
