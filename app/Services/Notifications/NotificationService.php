<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Enums\NotificationType;
use App\Events\NotificationCreated;
use App\Models\Tenant\Notification;

class NotificationService
{
    public function send(
        int $receiverStaffId,
        NotificationType $type,
        string $message,
        ?string $path = null,
        ?int $fromStaffId = null,
    ): Notification {
        $notification = Notification::query()->create([
            'receiver_staff_id' => $receiverStaffId,
            'from_staff_id' => $fromStaffId,
            'type' => $type->value,
            'message' => $message,
            'path' => $path,
            'is_read' => false,
        ]);

        $notification->loadMissing('sender:id,name');

        broadcast(new NotificationCreated($notification))->toOthers();

        return $notification;
    }

    /**
     * @param  list<int>  $receiverStaffIds
     */
    public function sendToMany(
        array $receiverStaffIds,
        NotificationType $type,
        string $message,
        ?string $path = null,
        ?int $fromStaffId = null,
    ): void {
        foreach ($receiverStaffIds as $staffId) {
            $this->send((int) $staffId, $type, $message, $path, $fromStaffId);
        }
    }
}
