<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Central\Subscription;
use App\Models\Central\SubscriptionInvoice;
use App\Services\AuditService;
use App\Support\JsonApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class PlatformSubscriptionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Subscription::query()->with(['clinic:id,name,slug'])->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('clinic_id')) {
            $query->where('clinic_id', $request->integer('clinic_id'));
        }

        $paginator = $query->paginate(15);

        return JsonApiResponse::paginated($paginator, 'OK');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'clinic_id' => ['required', 'exists:clinics,id'],
            'plan' => ['required', Rule::in(['Starter', 'Professional', 'Enterprise'])],
            'amount' => ['required', 'numeric', 'min:0'],
            'starts_at' => ['required', 'date'],
            'renews_at' => ['required', 'date', 'after:starts_at'],
            'payment_method' => ['nullable', 'string', 'max:50'],
        ]);

        $subscription = Subscription::query()->create([
            ...$validated,
            'status' => 'ok',
            'canceled_at' => null,
        ]);

        AuditService::log('subscription.created', (int) $subscription->clinic_id, null, [
            'subscription_id' => $subscription->id,
        ]);

        $subscription->load('clinic:id,name,slug');

        return JsonApiResponse::success($subscription, 'OK', 201);
    }

    public function update(Request $request, Subscription $subscription): JsonResponse
    {
        $validated = $request->validate([
            'plan' => ['sometimes', Rule::in(['Starter', 'Professional', 'Enterprise'])],
            'amount' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['sometimes', Rule::in(['ok', 'past_due', 'canceled'])],
            'renews_at' => ['sometimes', 'date'],
            'payment_method' => ['nullable', 'string', 'max:50'],
        ]);

        $wasCanceled = $subscription->status === 'canceled';

        if (array_key_exists('status', $validated) && $validated['status'] === 'canceled' && ! $wasCanceled) {
            $subscription->canceled_at = now()->toDateString();
        }

        $subscription->fill($validated);
        $subscription->save();

        if (array_key_exists('status', $validated) && $validated['status'] === 'canceled' && ! $wasCanceled) {
            AuditService::log('subscription.canceled', (int) $subscription->clinic_id, null, [
                'subscription_id' => $subscription->id,
            ]);
        } else {
            AuditService::log('subscription.updated', (int) $subscription->clinic_id, null, [
                'subscription_id' => $subscription->id,
            ]);
        }

        $subscription->load('clinic:id,name,slug');

        return JsonApiResponse::success($subscription, 'OK');
    }

    public function invoices(Subscription $subscription): JsonResponse
    {
        $items = SubscriptionInvoice::query()
            ->where('subscription_id', $subscription->id)
            ->orderByDesc('issued_at')
            ->get();

        return JsonApiResponse::success($items, 'OK');
    }
}
