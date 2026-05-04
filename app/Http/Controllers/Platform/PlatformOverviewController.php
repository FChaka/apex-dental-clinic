<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Central\Clinic;
use App\Models\Central\PlatformSpending;
use App\Models\Central\Subscription;
use App\Support\JsonApiResponse;
use Illuminate\Http\JsonResponse;

final class PlatformOverviewController extends Controller
{
    public function index(): JsonResponse
    {
        $currentMonth = now()->format('Y-m');

        $clinicCounts = Clinic::query()->selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'trial' THEN 1 ELSE 0 END) as trial,
            SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended,
            COALESCE(SUM(mrr), 0) as total_mrr,
            COALESCE(SUM(seats), 0) as total_seats
        ")->first();

        $subscriptionBreakdown = Subscription::query()->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $totalCostThisMonth = (float) PlatformSpending::query()
            ->where('month', $currentMonth)
            ->sum('amount');

        $totalMrr = (float) $clinicCounts->total_mrr;

        $recentClinics = Clinic::query()
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'name', 'slug', 'plan', 'status', 'created_at']);

        return JsonApiResponse::success([
            'total_clinics' => (int) $clinicCounts->total,
            'active_clinics' => (int) $clinicCounts->active,
            'trial_clinics' => (int) $clinicCounts->trial,
            'suspended_clinics' => (int) $clinicCounts->suspended,
            'total_mrr' => $totalMrr,
            'total_seats' => (int) $clinicCounts->total_seats,
            'total_cost_this_month' => $totalCostThisMonth,
            'revenue_vs_cost' => [
                'revenue' => $totalMrr,
                'cost' => $totalCostThisMonth,
                'profit' => $totalMrr - $totalCostThisMonth,
            ],
            'recent_clinics' => $recentClinics->map(fn (Clinic $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'plan' => $c->plan,
                'status' => $c->status,
                'created_at' => $c->created_at?->toIso8601String(),
            ])->values()->all(),
            'subscription_status_breakdown' => [
                'ok' => (int) ($subscriptionBreakdown['ok'] ?? 0),
                'past_due' => (int) ($subscriptionBreakdown['past_due'] ?? 0),
                'canceled' => (int) ($subscriptionBreakdown['canceled'] ?? 0),
            ],
        ], 'OK');
    }
}
