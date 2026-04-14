<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Appointment;
use App\Models\Tenant\StaffMember;
use App\Models\Tenant\StaffWorkingSchedule;
use App\Support\JsonApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

final class StaffController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $auth = $this->clinicStaff();
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        $query = StaffMember::query()->with([
            'workingSchedules' => fn ($q) => $q->orderBy('day_of_week'),
        ])->orderBy('name');

        $role = $request->query('role');
        if (is_string($role) && $role !== '') {
            $query->where('role', $role);
        }

        $items = $query->get()
            ->map(fn (StaffMember $s) => $this->serializeStaffList($s))
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

        if (! $this->isClinicAdmin($auth)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'role' => ['required', Rule::in(['Dentist', 'Dental Hygienist', 'Receptionist', 'Dental Nurse', 'Other'])],
            'clinic_access_level' => ['sometimes', Rule::in(['super_admin', 'admin', 'staff'])],
            'specialty' => ['nullable', 'string', 'max:255'],
            'experience' => ['nullable', 'string', 'max:100'],
            'status' => ['sometimes', Rule::in(['Active', 'On Leave', 'Off Duty'])],
            'username' => ['required', 'string', 'max:100', 'unique:staff_members,username'],
            'sign_in_method' => ['required', Rule::in(['pin', 'password'])],
            'pin_length' => ['sometimes', Rule::in([4, 6])],
            'login_pin' => ['required_if:sign_in_method,pin', 'nullable', 'string'],
            'login_password' => ['required_if:sign_in_method,password', 'nullable', 'string'],
            'color' => ['nullable', 'string', 'max:7'],
            'paid_by_percentage' => ['sometimes', 'boolean'],
        ]);

        if ($auth->clinic_access_level !== 'super_admin') {
            unset($validated['clinic_access_level']);
        }

        if (array_key_exists('login_pin', $validated) && is_string($validated['login_pin']) && $validated['login_pin'] !== '') {
            $validated['login_pin'] = Hash::make($validated['login_pin']);
        }

        if (array_key_exists('login_password', $validated) && is_string($validated['login_password']) && $validated['login_password'] !== '') {
            $validated['login_password'] = Hash::make($validated['login_password']);
        }

        $staff = DB::transaction(function () use ($validated) {
            /** @var StaffMember $staff */
            $staff = StaffMember::query()->create($validated);

            $defaults = collect(range(0, 6))->map(function (int $day) use ($staff) {
                $isWeekday = $day >= 1 && $day <= 5;

                return [
                    'staff_id' => $staff->id,
                    'day_of_week' => $day,
                    'is_open' => $isWeekday,
                    'start_hour' => 8,
                    'end_hour' => 17,
                ];
            })->all();

            StaffWorkingSchedule::query()->insert($defaults);

            return $staff->load(['workingSchedules' => fn ($q) => $q->orderBy('day_of_week')]);
        });

        return JsonApiResponse::success($this->serializeStaffDetail($staff), 'OK', 201);
    }

    public function show(StaffMember $staff): JsonResponse
    {
        $auth = $this->clinicStaff();
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        $staff->load([
            'workingSchedules' => fn ($q) => $q->orderBy('day_of_week'),
            'documents' => fn ($q) => $q->orderByDesc('id'),
            'percentagePerTreatment.treatmentType' => fn ($q) => $q->select('id', 'name'),
        ]);

        return JsonApiResponse::success($this->serializeStaffDetail($staff), 'OK');
    }

    public function update(Request $request, StaffMember $staff): JsonResponse
    {
        $auth = $this->clinicStaff();
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        $isSelf = (int) $auth->id === (int) $staff->id;
        $canAdminUpdate = $this->isClinicAdmin($auth);

        if (! $canAdminUpdate && ! $isSelf) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'role' => ['sometimes', Rule::in(['Dentist', 'Dental Hygienist', 'Receptionist', 'Dental Nurse', 'Other'])],
            'clinic_access_level' => ['sometimes', Rule::in(['super_admin', 'admin', 'staff'])],
            'specialty' => ['sometimes', 'nullable', 'string', 'max:255'],
            'experience' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', Rule::in(['Active', 'On Leave', 'Off Duty'])],
            'username' => ['sometimes', 'string', 'max:100', Rule::unique('staff_members', 'username')->ignore($staff->id)],
            'sign_in_method' => ['sometimes', Rule::in(['pin', 'password'])],
            'pin_length' => ['sometimes', Rule::in([4, 6])],
            'login_pin' => ['sometimes', 'nullable', 'string'],
            'login_password' => ['sometimes', 'nullable', 'string'],
            'color' => ['sometimes', 'nullable', 'string', 'max:7'],
            'paid_by_percentage' => ['sometimes', 'boolean'],
            'avatar' => ['sometimes', 'file', 'max:10240'],
        ]);

        if (! $canAdminUpdate) {
            $validated = array_intersect_key($validated, array_flip([
                'name',
                'email',
                'phone',
                'specialty',
                'experience',
            ]));
        }

        if ($canAdminUpdate && $auth->clinic_access_level !== 'super_admin') {
            unset($validated['clinic_access_level']);
        }

        if (array_key_exists('login_pin', $validated) && is_string($validated['login_pin']) && $validated['login_pin'] !== '') {
            $validated['login_pin'] = Hash::make($validated['login_pin']);
        }

        if (array_key_exists('login_password', $validated) && is_string($validated['login_password']) && $validated['login_password'] !== '') {
            $validated['login_password'] = Hash::make($validated['login_password']);
        }

        /** @var UploadedFile|null $avatar */
        $avatar = $validated['avatar'] ?? null;
        unset($validated['avatar']);

        if ($avatar instanceof UploadedFile) {
            $disk = config('filesystems.default');
            $oldPath = $staff->avatar_path;
            if (is_string($oldPath) && $oldPath !== '') {
                Storage::disk($disk)->delete($oldPath);
            }

            $tenantSlug = (string) tenancy()->tenant->slug;
            $ext = $avatar->getClientOriginalExtension() ?: 'bin';
            $path = "tenants/{$tenantSlug}/staff/{$staff->id}/avatar.{$ext}";

            Storage::disk($disk)->putFileAs(
                "tenants/{$tenantSlug}/staff/{$staff->id}",
                $avatar,
                "avatar.{$ext}"
            );

            $validated['avatar_path'] = $path;
        }

        $staff->fill($validated);
        $staff->save();

        $staff->load([
            'workingSchedules' => fn ($q) => $q->orderBy('day_of_week'),
        ]);

        return JsonApiResponse::success($this->serializeStaffDetail($staff), 'OK');
    }

    public function destroy(StaffMember $staff): Response
    {
        $auth = $this->clinicStaff();
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        if ($auth->role === 'Receptionist') {
            return response()->json([
                'message' => 'Receptionists cannot delete staff members.',
            ], 403);
        }

        if (! $this->isClinicAdmin($auth)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $hasUpcoming = Appointment::query()
            ->where('dentist_id', $staff->id)
            ->where('status', 'Upcoming')
            ->whereDate('date', '>=', today())
            ->exists();

        if ($hasUpcoming) {
            return response()->json([
                'message' => 'Cannot delete a staff member with upcoming appointments.',
            ], 422);
        }

        $staff->delete();

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

    /**
     * @return array<string, mixed>
     */
    private function serializeStaffList(StaffMember $s): array
    {
        return [
            'id' => $s->id,
            'name' => $s->name,
            'email' => $s->email,
            'phone' => $s->phone,
            'avatar_path' => $s->avatar_path,
            'role' => $s->role,
            'clinic_access_level' => $s->clinic_access_level,
            'specialty' => $s->specialty,
            'experience' => $s->experience,
            'status' => $s->status,
            'username' => $s->username,
            'sign_in_method' => $s->sign_in_method,
            'pin_length' => $s->pin_length,
            'color' => $s->color,
            'annual_leave_days' => $s->annual_leave_days,
            'leave_start' => $s->leave_start?->toDateString(),
            'leave_end' => $s->leave_end?->toDateString(),
            'paid_by_percentage' => (bool) $s->paid_by_percentage,
            'working_schedules' => $s->relationLoaded('workingSchedules')
                ? $s->workingSchedules->map(fn (StaffWorkingSchedule $w) => [
                    'day_of_week' => $w->day_of_week,
                    'is_open' => (bool) $w->is_open,
                    'start_hour' => $w->start_hour,
                    'end_hour' => $w->end_hour,
                ])->values()->all()
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeStaffDetail(StaffMember $s): array
    {
        $base = $this->serializeStaffList($s);

        $base['documents'] = $s->relationLoaded('documents')
            ? $s->documents->map(fn ($d) => [
                'id' => $d->id,
                'name' => $d->name,
                'type' => $d->type,
                'file_name' => $d->file_name,
                'file_path' => $d->file_path,
                'uploaded_at' => $d->uploaded_at?->toIso8601String(),
            ])->values()->all()
            : [];

        $base['staff_percentage_per_treatment'] = $s->relationLoaded('percentagePerTreatment')
            ? $s->percentagePerTreatment->map(fn ($p) => [
                'id' => $p->id,
                'treatment_type_id' => $p->treatment_type_id,
                'percentage' => $p->percentage,
                'treatment_type' => $p->relationLoaded('treatmentType') && $p->treatmentType !== null
                    ? ['id' => $p->treatmentType->id, 'name' => $p->treatmentType->name]
                    : null,
            ])->values()->all()
            : [];

        return $base;
    }
}
