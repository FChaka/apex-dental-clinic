<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Jobs\SendOwnerWelcomeEmail;
use App\Models\Central\Clinic;
use App\Models\Central\ClinicService;
use App\Models\Central\ClinicUsageRecord;
use App\Models\Tenant\StaffMember;
use App\Services\AuditService;
use App\Services\Platform\TenantDefaultDataService;
use App\Support\JsonApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

final class PlatformClinicController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Clinic::query()->orderByDesc('created_at');

        if ($request->filled('search')) {
            $search = '%'.$request->string('search').'%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                    ->orWhere('slug', 'like', $search)
                    ->orWhere('contact_email', 'like', $search);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('plan')) {
            $query->where('plan', $request->string('plan'));
        }

        $paginator = $query->paginate(15);
        $paginator->setCollection(
            $paginator->getCollection()->map(fn (Clinic $c) => $this->serializeClinicList($c))
        );

        return JsonApiResponse::paginated($paginator, 'OK');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/', Rule::unique('clinics', 'slug')],
            'contact_email' => ['required', 'email', 'max:255'],
            'plan' => ['required', Rule::in(['Starter', 'Professional', 'Enterprise'])],
            'seats' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_username' => ['required', 'string', 'max:100'],
            'region' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $slug = $validated['slug'];
        $dbName = 'apex_clinic_'.str_replace('-', '_', $slug);

        $clinic = DB::connection('central')->transaction(function () use ($validated, $dbName, $slug) {
            /** @var Clinic $clinic */
            $clinic = Clinic::query()->create([
                'name' => $validated['name'],
                'slug' => $slug,
                'contact_email' => $validated['contact_email'],
                'plan' => $validated['plan'],
                'seats' => $validated['seats'] ?? 5,
                'region' => $validated['region'] ?? null,
                'status' => 'trial',
                'trial_ends_at' => now()->addDays(14),
                'mrr' => 0,
                'db_name' => $dbName,
            ]);
            $clinic->domains()->create([
                'domain' => $slug,
            ]);

            return $clinic->fresh();
        });

        $temporaryPin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $pinExpiresAt = now()->addHours(24);

        /** @var StaffMember $owner */
        $owner = $clinic->run(function () use ($validated, $temporaryPin, $pinExpiresAt) {
            TenantDefaultDataService::seed($validated['name']);

            return StaffMember::query()->create([
                'name' => $validated['owner_name'],
                'email' => $validated['contact_email'],
                'username' => $validated['owner_username'],
                'role' => 'Dentist',
                'clinic_access_level' => 'super_admin',
                'status' => 'Active',
                'sign_in_method' => 'pin',
                'pin_length' => 6,
                'login_pin' => $temporaryPin,
                'temp_pin_expires_at' => $pinExpiresAt,
                'must_change_credentials' => true,
            ]);
        });

        SendOwnerWelcomeEmail::dispatch(
            $validated['contact_email'],
            $validated['owner_name'],
            $validated['owner_username'],
            $temporaryPin,
            $slug,
            $pinExpiresAt->toIso8601String(),
        );

        AuditService::log('clinic.created', $clinic->id, null, [
            'slug' => $slug,
            'plan' => $validated['plan'],
        ]);

        return JsonApiResponse::success(
            $this->serializeClinicDetail($clinic->fresh()),
            'Clinic created.',
            201
        );
    }

    public function show(Clinic $clinic): JsonResponse
    {
        $clinic->load(['clinicServices.platformService']);
        $currentMonth = now()->format('Y-m');
        $usageSummary = ClinicUsageRecord::query()
            ->where('clinic_id', $clinic->id)
            ->where('month', $currentMonth)
            ->selectRaw('COUNT(*) as rows, COALESCE(SUM(total_cost), 0) as total_billed')
            ->first();

        $data = $this->serializeClinicDetail($clinic);
        $data['usage_summary'] = [
            'month' => $currentMonth,
            'line_items' => (int) $usageSummary->rows,
            'total_billed' => (float) $usageSummary->total_billed,
        ];

        return JsonApiResponse::success($data, 'OK');
    }

    public function update(Request $request, Clinic $clinic): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'plan' => ['sometimes', Rule::in(['Starter', 'Professional', 'Enterprise'])],
            'seats' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'status' => ['sometimes', Rule::in(['active', 'trial', 'suspended'])],
            'contact_email' => ['sometimes', 'email', 'max:255'],
            'region' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $previousStatus = (string) $clinic->status;

        $clinic->fill($validated);
        $clinic->save();

        AuditService::log('clinic.updated', $clinic->id, null, [
            'changed' => array_keys($validated),
        ]);

        if (array_key_exists('status', $validated) && $validated['status'] === 'suspended' && $previousStatus !== 'suspended') {
            AuditService::log('clinic.suspended', $clinic->id);
        }

        return JsonApiResponse::success($this->serializeClinicDetail($clinic->fresh()), 'OK');
    }

    public function destroy(Clinic $clinic): JsonResponse
    {
        $clinic->delete();

        AuditService::log('clinic.deleted', $clinic->id);

        return JsonApiResponse::success(null, 'Clinic deleted.');
    }

    public function services(Clinic $clinic): JsonResponse
    {
        $items = $clinic->clinicServices()->with('platformService')->orderBy('id')->get()
            ->map(fn (ClinicService $row) => $this->serializeClinicService($row))
            ->values()
            ->all();

        return JsonApiResponse::success($items, 'OK');
    }

    public function enableService(Request $request, Clinic $clinic): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => ['required', 'exists:platform_services,id'],
            'unit_price_override' => ['nullable', 'numeric', 'min:0'],
            'flat_price_override' => ['nullable', 'numeric', 'min:0'],
            'monthly_quota' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($clinic->clinicServices()->where('service_id', $validated['service_id'])->exists()) {
            return response()->json(['message' => 'Service already enabled for this clinic.'], 422);
        }

        $row = $clinic->clinicServices()->create([
            'service_id' => $validated['service_id'],
            'is_enabled' => true,
            'unit_price_override' => $validated['unit_price_override'] ?? null,
            'flat_price_override' => $validated['flat_price_override'] ?? null,
            'monthly_quota' => $validated['monthly_quota'] ?? null,
            'enabled_at' => now()->toDateString(),
            'disabled_at' => null,
        ]);

        $row->load('platformService');

        AuditService::log('service.enabled_for_clinic', $clinic->id, null, [
            'service_id' => $validated['service_id'],
        ]);

        return JsonApiResponse::success($this->serializeClinicService($row), 'OK', 201);
    }

    public function updateService(Request $request, Clinic $clinic, ClinicService $clinicService): JsonResponse
    {
        if ((int) $clinicService->clinic_id !== (int) $clinic->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'unit_price_override' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'flat_price_override' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'monthly_quota' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'is_enabled' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('is_enabled', $validated) && $validated['is_enabled'] === false && $clinicService->is_enabled) {
            $validated['disabled_at'] = now()->toDateString();
        }

        if (array_key_exists('is_enabled', $validated) && $validated['is_enabled'] === true) {
            $validated['disabled_at'] = null;
        }

        $clinicService->fill($validated);
        $clinicService->save();
        $clinicService->load('platformService');

        if (array_key_exists('is_enabled', $validated) && $validated['is_enabled'] === false) {
            AuditService::log('service.disabled_for_clinic', $clinic->id, null, [
                'clinic_service_id' => $clinicService->id,
            ]);
        }

        return JsonApiResponse::success($this->serializeClinicService($clinicService), 'OK');
    }

    public function usage(Request $request, Clinic $clinic): JsonResponse
    {
        $query = $clinic->usageRecords()->with('platformService:id,name')->orderByDesc('month');

        if ($request->filled('month')) {
            $query->where('month', $request->string('month'));
        }

        if ($request->filled('service_id')) {
            $query->where('service_id', $request->integer('service_id'));
        }

        $items = $query->get()->map(fn (ClinicUsageRecord $r) => [
            'id' => $r->id,
            'month' => $r->month,
            'service_id' => $r->service_id,
            'service' => $r->platformService !== null
                ? ['id' => $r->platformService->id, 'name' => $r->platformService->name]
                : null,
            'quantity' => $r->quantity,
            'unit_cost' => (float) $r->unit_cost,
            'total_cost' => (float) $r->total_cost,
        ])->values()->all();

        return JsonApiResponse::success($items, 'OK');
    }

    public function resendOwnerPin(Clinic $clinic): JsonResponse
    {
        $temporaryPin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $pinExpiresAt = now()->addHours(24);

        $payload = $clinic->run(function () use ($temporaryPin, $pinExpiresAt) {
            $owner = StaffMember::query()
                ->where('clinic_access_level', 'super_admin')
                ->orderBy('id')
                ->first();

            if ($owner === null) {
                return null;
            }

            $owner->forceFill([
                'sign_in_method' => 'pin',
                'pin_length' => 6,
                'login_pin' => $temporaryPin,
                'temp_pin_expires_at' => $pinExpiresAt,
                'must_change_credentials' => true,
            ]);
            $owner->save();

            return [
                'email' => (string) $owner->email,
                'name' => (string) $owner->name,
                'username' => (string) $owner->username,
            ];
        });

        if ($payload === null) {
            return response()->json(['message' => 'No super_admin staff found for this clinic.'], 404);
        }

        SendOwnerWelcomeEmail::dispatch(
            $payload['email'],
            $payload['name'],
            $payload['username'],
            $temporaryPin,
            (string) $clinic->slug,
            $pinExpiresAt->toIso8601String(),
        );

        AuditService::log('clinic.owner_pin_resent', $clinic->id);

        return JsonApiResponse::success(null, 'Temporary PIN reissued.');
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeClinicList(Clinic $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'slug' => $c->slug,
            'region' => $c->region,
            'plan' => $c->plan,
            'seats' => (int) $c->seats,
            'status' => $c->status,
            'contact_email' => $c->contact_email,
            'mrr' => (float) $c->mrr,
            'created_at' => $this->toIso8601OrNull($c->created_at),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeClinicDetail(Clinic $c): array
    {
        $base = $this->serializeClinicList($c);
        $base['db_name'] = $c->db_name;
        $base['trial_ends_at'] = $this->toIso8601OrNull($c->trial_ends_at);
        $base['updated_at'] = $this->toIso8601OrNull($c->updated_at);
        $base['services'] = $c->relationLoaded('clinicServices')
            ? $c->clinicServices->map(fn (ClinicService $row) => $this->serializeClinicService($row))->values()->all()
            : [];

        return $base;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeClinicService(ClinicService $row): array
    {
        $svc = $row->platformService;

        return [
            'id' => $row->id,
            'clinic_id' => $row->clinic_id,
            'service_id' => $row->service_id,
            'is_enabled' => (bool) $row->is_enabled,
            'unit_price_override' => $row->unit_price_override !== null ? (float) $row->unit_price_override : null,
            'flat_price_override' => $row->flat_price_override !== null ? (float) $row->flat_price_override : null,
            'monthly_quota' => $row->monthly_quota,
            'enabled_at' => $this->toDateStringOrNull($row->enabled_at),
            'disabled_at' => $this->toDateStringOrNull($row->disabled_at),
            'platform_service' => $svc !== null
                ? [
                    'id' => $svc->id,
                    'key' => $svc->key,
                    'name' => $svc->name,
                    'type' => $svc->type,
                    'billing_model' => $svc->billing_model,
                ]
                : null,
        ];
    }

    /**
     * Central {@see Clinic} / stancl tenant rows may expose dates as strings until cast.
     */
    private function toIso8601OrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::parse($value)->toIso8601String();
        }

        if (is_string($value)) {
            return Carbon::parse($value)->toIso8601String();
        }

        return null;
    }

    private function toDateStringOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->toDateString();
    }
}
