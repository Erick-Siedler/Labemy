<?php

namespace App\Services;

use App\Events\NotificationCreated;
use App\Models\Event;
use App\Models\Notification;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRelation;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

class EventReminderService
{
    public const SOURCE_DUE_TODAY = 'event_due_today';
    public const SOURCE_DUE_PASSED = 'event_due_passed';
    private const REFERENCE_TYPE_EVENT = 'event';

    /**
     * Dispara lembretes diarios de eventos do dia e eventos vencidos.
     */
    public function dispatch(?CarbonInterface $referenceDate = null): array
    {
        $today = $referenceDate
            ? Carbon::instance($referenceDate)->startOfDay()
            : now()->startOfDay();

        $events = Event::with('lab')
            ->whereDate('due', '<=', $today->toDateString())
            ->get();

        if ($events->isEmpty()) {
            return [
                'events_processed' => 0,
                'notifications_created' => 0,
                'notifications_skipped' => 0,
            ];
        }

        $tenantIds = $events->pluck('tenant_id')->unique()->values();
        $ownerIdsByTenant = Tenant::whereIn('id', $tenantIds)->pluck('creator_id', 'id');
        $relatedUsersByTenant = UserRelation::whereIn('tenant_id', $tenantIds)
            ->where('status', 'active')
            ->select(['user_id', 'tenant_id'])
            ->distinct()
            ->get()
            ->groupBy('tenant_id');

        $eventsProcessed = 0;
        $created = 0;
        $skipped = 0;

        foreach ($events as $event) {
            $dueDate = Carbon::parse($event->due)->startOfDay();
            if ($dueDate->greaterThan($today)) {
                continue;
            }

            $eventsProcessed++;
            $source = $dueDate->equalTo($today)
                ? self::SOURCE_DUE_TODAY
                : self::SOURCE_DUE_PASSED;
            $message = $this->buildReminderMessage($event, $source, $dueDate);

            $ownerId = (int) ($ownerIdsByTenant[$event->tenant_id] ?? 0);
            if ($ownerId > 0) {
                if ($this->notifyRecipient($ownerId, 'users', $event, $source, $message)) {
                    $created++;
                } else {
                    $skipped++;
                }
            }

            $relatedUsers = $relatedUsersByTenant->get($event->tenant_id, collect());
            foreach ($relatedUsers as $relatedUser) {
                if ($this->notifyRecipient((int) $relatedUser->user_id, 'users', $event, $source, $message)) {
                    $created++;
                } else {
                    $skipped++;
                }
            }
        }

        return [
            'events_processed' => $eventsProcessed,
            'notifications_created' => $created,
            'notifications_skipped' => $skipped,
        ];
    }

    /**
     * Monta a mensagem de lembrete enviada ao usuario.
     */
    private function buildReminderMessage(Event $event, string $source, CarbonInterface $dueDate): string
    {
        $labName = $event->lab?->name ?? 'Laboratorio';
        $dueLabel = $dueDate->format('d/m/Y');

        if ($source === self::SOURCE_DUE_TODAY) {
            return 'Hoje vence o evento "' . $event->title . '" (Lab: ' . $labName . ').';
        }

        return 'O evento "' . $event->title . '" venceu em ' . $dueLabel . ' (Lab: ' . $labName . ').';
    }

    /**
     * Garante envio unico de notificacao por evento, status e usuario.
     */
    private function notifyRecipient(int $userId, string $tableName, Event $event, string $source, string $message): bool
    {
        $recipient = User::where('id', $userId)->first();
        if (!$recipient) {
            return false;
        }

        $notificationService = app(NotificationTenantService::class);
        if (!$notificationService->shouldEnableForContext($recipient, (int) $event->tenant_id)) {
            return false;
        }

        $alreadyExists = Notification::withTrashed()
            ->where('user_id', $userId)
            ->where('table', $tableName)
            ->where('source', $source)
            ->where('reference_type', self::REFERENCE_TYPE_EVENT)
            ->where('reference_id', $event->id)
            ->exists();

        if ($alreadyExists) {
            return false;
        }

        $notification = Notification::create([
            'user_id' => $userId,
            'table' => $tableName,
            'description' => $message,
            'type' => 'alert',
            'source' => $source,
            'reference_type' => self::REFERENCE_TYPE_EVENT,
            'reference_id' => $event->id,
        ]);

        try {
            NotificationCreated::dispatch($notification);
        } catch (Throwable $exception) {
            Log::warning('Falha ao enviar lembrete de evento em tempo real.', [
                'notification_id' => (int) $notification->id,
                'user_id' => $userId,
                'event_id' => (int) $event->id,
                'error' => $exception->getMessage(),
            ]);
        }

        return true;
    }
}
