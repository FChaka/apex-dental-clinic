<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Tenant\Notification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationCreated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public Notification $notification) {}

    public function broadcastOn(): Channel
    {
        $tenantSlug = (string) tenant()->getTenantKey();
        $receiverId = (int) $this->notification->receiver_staff_id;

        return new PrivateChannel("{$tenantSlug}.staff.{$receiverId}");
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $sender = null;
        if ($this->notification->from_staff_id !== null) {
            $this->notification->loadMissing('sender:id,name');
            $model = $this->notification->sender;
            $sender = [
                'id' => (int) $this->notification->from_staff_id,
                'name' => $model?->name,
            ];
        }

        return [
            'id' => $this->notification->id,
            'type' => $this->notification->type,
            'message' => $this->notification->message,
            'path' => $this->notification->path,
            'is_read' => false,
            'created_at' => $this->notification->created_at?->toIso8601String(),
            'from_staff' => $sender,
        ];
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }
}
