<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Central\PlatformSpending;
use App\Services\AuditService;
use App\Support\JsonApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PlatformSpendingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PlatformSpending::query()
            ->with([
                'category:id,name',
                'service:id,name',
            ])
            ->orderByDesc('id');

        if ($request->filled('month')) {
            $query->where('month', $request->string('month'));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('service_id')) {
            $query->where('service_id', $request->integer('service_id'));
        }

        $paginator = $query->paginate(15);

        return JsonApiResponse::paginated($paginator, 'OK');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => ['nullable', 'exists:platform_cost_categories,id'],
            'service_id' => ['nullable', 'exists:platform_services,id'],
            'month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'amount' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $row = PlatformSpending::query()->create($validated);

        AuditService::log('spending.created', null, null, ['spending_id' => $row->id]);

        $row->load(['category:id,name', 'service:id,name']);

        return JsonApiResponse::success($row, 'OK', 201);
    }

    public function update(Request $request, PlatformSpending $spending): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => ['sometimes', 'nullable', 'exists:platform_cost_categories,id'],
            'service_id' => ['sometimes', 'nullable', 'exists:platform_services,id'],
            'month' => ['sometimes', 'regex:/^\d{4}-\d{2}$/'],
            'amount' => ['sometimes', 'numeric', 'min:0'],
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $spending->fill($validated);
        $spending->save();

        AuditService::log('spending.updated', null, null, ['spending_id' => $spending->id]);

        $spending->load(['category:id,name', 'service:id,name']);

        return JsonApiResponse::success($spending, 'OK');
    }

    public function destroy(PlatformSpending $spending): JsonResponse
    {
        $id = $spending->id;
        $spending->delete();

        AuditService::log('spending.deleted', null, null, ['spending_id' => $id]);

        return JsonApiResponse::success(null, 'Deleted.');
    }

    public function summary(Request $request): JsonResponse
    {
        $query = PlatformSpending::query()->with(['category', 'service']);

        if ($request->filled('month')) {
            $months = collect([$request->string('month')]);
            $query->where('month', $request->string('month'));
        } else {
            $months = collect(range(0, 11))->map(fn (int $i) => now()->subMonths(11 - $i)->format('Y-m'));
            $query->whereIn('month', $months->all());
        }

        $rows = $query->get();

        $payload = $months->map(function (string $month) use ($rows) {
            $bucket = $rows->where('month', $month);
            $total = (float) $bucket->sum('amount');

            $byCategory = $bucket->groupBy(fn ($r) => $r->category_id ?? 0)->map(function ($group) {
                $first = $group->first();

                return [
                    'category_id' => $first?->category_id !== null ? (int) $first->category_id : null,
                    'category_name' => $first?->category?->name ?? 'General',
                    'total' => (float) $group->sum('amount'),
                ];
            })->values()->all();

            $byService = $bucket->groupBy(fn ($r) => $r->service_id ?? 0)->map(function ($group) {
                $first = $group->first();

                return [
                    'service_id' => $first?->service_id !== null ? (int) $first->service_id : null,
                    'service_name' => $first?->service?->name ?? 'General',
                    'total' => (float) $group->sum('amount'),
                ];
            })->values()->all();

            return [
                'month' => $month,
                'total' => $total,
                'by_category' => $byCategory,
                'by_service' => $byService,
            ];
        })->values()->all();

        return JsonApiResponse::success(['months' => $payload], 'OK');
    }
}
