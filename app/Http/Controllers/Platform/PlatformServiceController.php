<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Central\ClinicUsageRecord;
use App\Models\Central\PlatformService;
use App\Models\Central\PlatformSpending;
use App\Services\AuditService;
use App\Support\JsonApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class PlatformServiceController extends Controller
{
    public function index(): JsonResponse
    {
        $items = PlatformService::query()->orderBy('key')->get();

        return JsonApiResponse::success($items, 'OK');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:50', 'unique:platform_services,key'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['core', 'addon'])],
            'billing_model' => ['required', Rule::in(['flat', 'per_unit', 'tiered', 'included'])],
            'unit_label' => ['nullable', 'string', 'max:50'],
            'default_unit_price' => ['nullable', 'numeric', 'min:0'],
            'default_flat_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $service = PlatformService::query()->create($validated);

        AuditService::log('service.created', null, null, ['service_id' => $service->id, 'key' => $service->key]);

        return JsonApiResponse::success($service, 'OK', 201);
    }

    public function update(Request $request, PlatformService $service): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'type' => ['sometimes', Rule::in(['core', 'addon'])],
            'billing_model' => ['sometimes', Rule::in(['flat', 'per_unit', 'tiered', 'included'])],
            'unit_label' => ['sometimes', 'nullable', 'string', 'max:50'],
            'default_unit_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'default_flat_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'launched_at' => ['sometimes', 'nullable', 'date'],
        ]);

        $service->fill($validated);
        $service->save();

        AuditService::log('service.updated', null, null, ['service_id' => $service->id]);

        return JsonApiResponse::success($service->fresh(), 'OK');
    }

    public function destroy(PlatformService $service): JsonResponse
    {
        $service->is_active = false;
        $service->save();

        AuditService::log('service.updated', null, null, [
            'service_id' => $service->id,
            'deactivated' => true,
        ]);

        return JsonApiResponse::success($service, 'OK');
    }

    public function usage(Request $request, PlatformService $service): JsonResponse
    {
        $query = ClinicUsageRecord::query()->where('service_id', $service->id);

        if ($request->filled('month')) {
            $query->where('month', $request->string('month'));
        }

        $rows = $query
            ->selectRaw('month, SUM(quantity) as quantity, SUM(total_cost) as total_cost')
            ->groupBy('month')
            ->orderBy('month')
            ->get()->map(fn ($r) => [
                'month' => $r->month,
                'quantity' => (int) $r->quantity,
                'total_cost' => (float) $r->total_cost,
            ])->values()->all();

        return JsonApiResponse::success($rows, 'OK');
    }

    public function profitability(Request $request, PlatformService $service): JsonResponse
    {
        $revenueQuery = ClinicUsageRecord::query()->where('service_id', $service->id);
        $costQuery = PlatformSpending::query()->where('service_id', $service->id);

        if ($request->filled('month')) {
            $month = $request->string('month');
            $revenueQuery->where('month', $month);
            $costQuery->where('month', $month);
        }

        $revenue = (float) $revenueQuery->sum('total_cost');
        $cost = (float) $costQuery->sum('amount');

        return JsonApiResponse::success([
            'revenue' => $revenue,
            'cost' => $cost,
            'profit' => $revenue - $cost,
        ], 'OK');
    }
}
