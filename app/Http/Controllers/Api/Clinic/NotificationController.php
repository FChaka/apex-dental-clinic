<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Concerns\InteractsWithClinicStaff;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Notification;
use App\Support\JsonApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class NotificationController extends Controller
{
    use InteractsWithClinicStaff;

    public function index(Request $request): JsonResponse
    {
        $auth = $this->clinicStaff();
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
        $paginator = Notification::query()
            ->where('receiver_staff_id', $auth->id)
            ->with(['sender:id,name'])
            ->orderByDesc('id')
            ->paginate($perPage);

        $unreadCount = Notification::query()
            ->where('receiver_staff_id', $auth->id)
            ->where('is_read', false)
            ->count();

        $data = collect($paginator->items())->map(fn (Notification $n) => $this->serialize($n))->values()->all();

        return JsonApiResponse::notificationsIndex($paginator, $data, $unreadCount);
    }

    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        $auth = $this->clinicStaff();
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        if ((int) $notification->receiver_staff_id !== (int) $auth->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $notification->update(['is_read' => true]);

        return JsonApiResponse::success(
            $this->serialize($notification->fresh(['sender:id,name'])),
            'OK',
        );
    }

    public function readAll(Request $request): JsonResponse
    {
        $auth = $this->clinicStaff();
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        Notification::query()
            ->where('receiver_staff_id', $auth->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return JsonApiResponse::success(null, 'All notifications marked as read.');
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Notification $notification): array
    {
        $from = null;
        if ($notification->from_staff_id !== null) {
            $sender = $notification->relationLoaded('sender') ? $notification->sender : null;
            $from = [
                'id' => (int) $notification->from_staff_id,
                'name' => $sender?->name,
            ];
        }

        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'message' => $notification->message,
            'path' => $notification->path,
            'is_read' => (bool) $notification->is_read,
            'from_staff' => $from,
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }
}
