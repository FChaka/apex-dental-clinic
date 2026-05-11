<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Controller;
use App\Models\Tenant\StaffMember;
use App\Models\Tenant\WidgetPreference;
use App\Support\JsonApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WidgetPreferenceController extends Controller
{
    public function show(): JsonResponse
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        $rows = WidgetPreference::query()
            ->where('staff_id', $staff->id)
            ->whereIn('page', ['dashboard', 'reports'])
            ->get()
            ->keyBy('page');

        $dashboard = $rows->get('dashboard')?->widget_order ?? [];
        $reports = $rows->get('reports')?->widget_order ?? [];

        return JsonApiResponse::success([
            'dashboard' => is_array($dashboard) ? $dashboard : [],
            'reports' => is_array($reports) ? $reports : [],
        ], 'OK');
    }

    public function update(Request $request): JsonResponse
    {
        $staff = $this->clinicStaff();
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        $validated = $request->validate([
            'dashboard' => ['sometimes', 'array'],
            'reports' => ['sometimes', 'array'],
        ]);

        if (array_key_exists('dashboard', $validated)) {
            WidgetPreference::query()->updateOrCreate(
                ['staff_id' => $staff->id, 'page' => 'dashboard'],
                ['widget_order' => $validated['dashboard']]
            );
        }

        if (array_key_exists('reports', $validated)) {
            WidgetPreference::query()->updateOrCreate(
                ['staff_id' => $staff->id, 'page' => 'reports'],
                ['widget_order' => $validated['reports']]
            );
        }

        return $this->show();
    }

    private function clinicStaff(): StaffMember|JsonResponse
    {
        $staff = auth('clinic_session')->user();
        if (! $staff instanceof StaffMember) {
            return JsonApiResponse::unauthorized();
        }

        return $staff;
    }
}
