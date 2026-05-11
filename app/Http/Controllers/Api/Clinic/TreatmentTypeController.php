<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Controller;
use App\Models\Tenant\PatientTreatmentEntry;
use App\Models\Tenant\StaffMember;
use App\Models\Tenant\TreatmentType;
use App\Support\JsonApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class TreatmentTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        $query = TreatmentType::query()->orderBy('name');

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $items = $query->get()->map(fn (TreatmentType $t) => $this->serializeType($t))->values()->all();

        return JsonApiResponse::success($items, 'OK');
    }

    public function store(Request $request): JsonResponse
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        if (! $this->isClinicAdmin($staff)) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'default_duration' => ['required', 'integer', 'min:1'],
            'default_price' => ['required', 'numeric', 'min:0'],
            'vat' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $type = TreatmentType::query()->create($validated);

        return JsonApiResponse::success($this->serializeType($type), 'Treatment type created successfully.', Response::HTTP_CREATED);
    }

    public function update(Request $request, TreatmentType $type): JsonResponse
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        if (! $this->isClinicAdmin($staff)) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'default_duration' => ['sometimes', 'integer', 'min:1'],
            'default_price' => ['sometimes', 'numeric', 'min:0'],
            'vat' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $type->update($validated);

        return JsonApiResponse::success($this->serializeType($type->fresh()), 'Treatment type updated successfully.');
    }

    public function destroy(TreatmentType $type): JsonResponse|Response
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        if (! $this->isClinicAdmin($staff)) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        if (PatientTreatmentEntry::query()->where('treatment_type_id', $type->id)->exists()) {
            return response()->json([
                'message' => 'Cannot delete a treatment type that has been used in patient treatments.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $type->delete();

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
    private function serializeType(TreatmentType $t): array
    {
        return [
            'id' => $t->id,
            'name' => $t->name,
            'description' => $t->description,
            'default_duration' => $t->default_duration,
            'default_price' => $t->default_price,
            'vat' => $t->vat,
            'is_active' => $t->is_active,
            'created_at' => $t->created_at?->toIso8601String(),
            'updated_at' => $t->updated_at?->toIso8601String(),
        ];
    }
}
