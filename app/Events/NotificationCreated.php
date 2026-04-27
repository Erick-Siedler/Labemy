<?php

namespace App\Events;

use App\Models\Notification;
use App\Services\NotificationTenantService;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public Notification $notification)
    {
    }

    /**
     * Define canal privado do destinatario.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.' . (int) $this->notification->user_id),
        ];
    }

    /**
     * Nome publico do evento no cliente.
     */
    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    /**
     * Payload entregue no cliente.
     */
    public function broadcastWith(): array
    {
        $notificationService = app(NotificationTenantService::class);
        $tenantId = $notificationService->resolveTenantIdForNotification($this->notification);

        return [
            'id' => (int) $this->notification->id,
            'description' => (string) $this->notification->description,
            'type' => (string) ($this->notification->type ?? 'alert'),
            'source' => (string) ($this->notification->source ?? ''),
            'reference_type' => (string) ($this->notification->reference_type ?? ''),
            'reference_id' => (int) ($this->notification->reference_id ?? 0),
            'tenant_id' => $tenantId ? (int) $tenantId : 0,
            'created_at' => optional($this->notification->created_at)?->toIso8601String(),
        ];
    }
}
