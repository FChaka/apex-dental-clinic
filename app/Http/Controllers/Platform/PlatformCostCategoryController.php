<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Central\PlatformCostCategory;
use App\Support\JsonApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PlatformCostCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $items = PlatformCostCategory::query()->orderBy('name')->get();

        return JsonApiResponse::success($items, 'OK');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:50', 'unique:platform_cost_categories,key'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $row = PlatformCostCategory::query()->create($validated);

        return JsonApiResponse::success($row, 'OK', 201);
    }

    public function update(Request $request, PlatformCostCategory $costCategory): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $costCategory->update($validated);

        return JsonApiResponse::success($costCategory->fresh(), 'OK');
    }
}
