<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Clinic;

use App\Events\LeaveRequestDecided;
use App\Events\LeaveRequestSubmitted;
use App\Http\Controllers\Controller;
use App\Models\Tenant\LeaveRequest;
use App\Models\Tenant\StaffMember;
use App\Services\DataScopeService;
use App\Support\JsonApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

final class LeaveRequestController extends Controller
{
    public function __construct(
        private readonly DataScopeService $dataScope,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $auth = $this->clinicStaff();
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        $query = LeaveRequest::query()
            ->with(['staff' => fn ($q) => $q->select('id', 'name', 'role')])
            ->orderByDesc('requested_at');

        $this->dataScope->scopeLeaveRequests($query, $auth);

        $status = $request->query('status');
        if (is_string($status) && $status !== '') {
            $query->where('status', $status);
        }

        $staffId = $request->query('staff_id');
        if ($this->isClinicAdmin($auth) && is_string($staffId) && $staffId !== '') {
            $query->where('staff_id', (int) $staffId);
        }

        $items = $query->get()
            ->map(fn (LeaveRequest $lr) => $this->serializeLeaveRequest($lr))
            ->values()
            ->all();

        return JsonApiResponse::success($items, 'OK');
    }

    public function store(Request $request): JsonResponse
    {
        $auth = $this->clinicStaff();
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        $rules = [
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'note' => ['nullable', 'string'],
        ];
        if ($this->canManageOtherLeave($auth)) {
            $rules['staff_id'] = ['sometimes', 'integer', 'exists:staff_members,id'];
        }

        $validated = $request->validate($rules);

        $staffId = $auth->id;
        if ($this->canManageOtherLeave($auth) && ! empty($validated['staff_id'])) {
            $staffId = (int) $validated['staff_id'];
        }

        $lr = LeaveRequest::query()->create([
            'staff_id' => $staffId,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'note' => $validated['note'] ?? null,
            'status' => 'Pending',
            'requested_at' => now(),
            'responded_at' => null,
        ]);

        $lr->load(['staff' => fn ($q) => $q->select('id', 'name', 'role')]);

        event(new LeaveRequestSubmitted($lr));

        return JsonApiResponse::success($this->serializeLeaveRequest($lr), 'OK', 201);
    }

    public function update(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $auth = $this->clinicStaff();
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        $isAdmin = $this->isClinicAdmin($auth);
        $isOwner = (int) $leaveRequest->staff_id === (int) $auth->id;

        $canManage = $this->canManageOtherLeave($auth);

        $rules = [
            'status' => ['sometimes', Rule::in(['Approved', 'Rejected', 'Removed'])],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
            'note' => ['nullable', 'string'],
        ];
        if ($canManage) {
            $rules['staff_id'] = ['sometimes', 'integer', 'exists:staff_members,id'];
        }

        $validated = $request->validate($rules);

        $statusBefore = $leaveRequest->status;

        if (! $isAdmin && ! $canManage) {
            if (! $isOwner) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }

            if ($leaveRequest->status !== 'Pending') {
                return response()->json(['message' => 'Forbidden.'], 403);
            }

            if (array_key_exists('status', $validated)) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }
        }

        if ($isAdmin && array_key_exists('status', $validated)) {
            $newStatus = (string) $validated['status'];
            if (in_array($newStatus, ['Approved', 'Rejected'], true)) {
                $validated['responded_at'] = now();
            }
        }

        if (array_key_exists('staff_id', $validated) && $canManage) {
            $leaveRequest->staff_id = $validated['staff_id'];
        }
        unset($validated['staff_id']);

        $leaveRequest->fill($validated);
        $leaveRequest->save();
        $leaveRequest->load(['staff' => fn ($q) => $q->select('id', 'name', 'role')]);

        if (array_key_exists('status', $validated)) {
            $newStatus = (string) $validated['status'];
            if (in_array($newStatus, ['Approved', 'Rejected'], true) && $newStatus !== $statusBefore) {
                $decision = $newStatus === 'Approved' ? 'approved' : 'rejected';
                event(new LeaveRequestDecided($leaveRequest, $decision, $auth));
            }
        }

        return JsonApiResponse::success($this->serializeLeaveRequest($leaveRequest), 'OK');
    }

    public function destroy(LeaveRequest $leaveRequest): Response
    {
        $auth = $this->clinicStaff();
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        $isAdmin = $this->isClinicAdmin($auth);
        $isOwner = (int) $leaveRequest->staff_id === (int) $auth->id;

        if (! $isAdmin && ! $isOwner) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $leaveRequest->delete();

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

    private function canManageOtherLeave(StaffMember $staff): bool
    {
        return $this->isClinicAdmin($staff) || $staff->role === 'Receptionist';
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeLeaveRequest(LeaveRequest $lr): array
    {
        return [
            'id' => $lr->id,
            'staff_id' => $lr->staff_id,
            'start_date' => $lr->start_date?->toDateString(),
            'end_date' => $lr->end_date?->toDateString(),
            'status' => $lr->status,
            'note' => $lr->note,
            'requested_at' => $lr->requested_at?->toIso8601String(),
            'responded_at' => $lr->responded_at?->toIso8601String(),
            'staff' => $lr->relationLoaded('staff') && $lr->staff !== null ? [
                'id' => $lr->staff->id,
                'name' => $lr->staff->name,
                'role' => $lr->staff->role,
            ] : null,
            'created_at' => $lr->created_at?->toIso8601String(),
            'updated_at' => $lr->updated_at?->toIso8601String(),
        ];
    }
}
