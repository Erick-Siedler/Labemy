<?php

namespace App\Services;

use App\Events\NotificationCreated;
use App\Models\Log;
use App\Models\Notification;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRelation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log as Logger;
use Illuminate\Support\Str;
use Throwable;

class ActivityService
{
    /**
     * Executa a rotina 'notifyUser' no fluxo de negocio.
     */
    public static function notifyUser(
        int $userId,
        string $message,
        string $type = 'alert',
        ?string $source = null,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): void
    {
        $recipient = User::where('id', $userId)->first();
        if (!$recipient) {
            return;
        }

        $notificationService = app(NotificationTenantService::class);
        $contextTenantId = null;
        if (!empty($referenceType) && !empty($referenceId)) {
            $resolvedTenantId = (int) ($notificationService->resolveTenantIdByReference($referenceType, (int) $referenceId) ?? 0);
            if ($resolvedTenantId > 0) {
                $contextTenantId = $resolvedTenantId;
            }
        }

        if (empty($referenceType) || empty($referenceId)) {
            $sessionTenantId = (int) session('active_tenant_id', 0);
            if ($sessionTenantId > 0) {
                $contextTenantId = $sessionTenantId;
                $referenceType = 'tenant';
                $referenceId = $sessionTenantId;
            }
        }

        if (!$notificationService->shouldEnableForContext($recipient, $contextTenantId)) {
            return;
        }

        $payload = [
            'user_id' => $userId,
            'table' => 'users',
            'description' => $message,
            'type' => $type,
        ];

        if (!empty($source)) {
            $payload['source'] = $source;
        }
        if (!empty($referenceType) && !empty($referenceId)) {
            $payload['reference_type'] = $referenceType;
            $payload['reference_id'] = (int) $referenceId;
        }

        $notification = Notification::create($payload);

        try {
            NotificationCreated::dispatch($notification);
        } catch (Throwable $exception) {
            Logger::warning('Falha ao enviar notificacao em tempo real.', [
                'notification_id' => (int) $notification->id,
                'user_id' => $userId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Envia notificacao para uma lista de usuarios.
     */
    public static function notifyUsers(iterable $users, string $message, string $type = 'alert'): void
    {
        foreach ($users as $user) {
            $id = is_object($user) ? (int) ($user->id ?? 0) : (int) $user;
            if ($id > 0) {
                self::notifyUser($id, $message, $type);
            }
        }
    }

    /**
     * Executa a rotina 'notifyOwnerAndStaff' no fluxo de negocio.
     */
    public static function notifyOwnerAndStaff(Tenant $tenant, string $message, ?int $labId = null, ?int $groupId = null, bool $includeAssistants = true): void
    {
        self::notifyUser(
            (int) $tenant->creator_id,
            $message,
            'approval',
            'tenant_activity',
            'tenant',
            (int) $tenant->id
        );

        $roles = $includeAssistants ? ['teacher', 'assistant', 'asssitant', 'assitant'] : ['teacher'];

        $staffQuery = UserRelation::where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->whereIn('role', $roles);

        if ($labId || $groupId) {
            $staffQuery->where(function ($query) use ($labId, $groupId) {
                if ($groupId) {
                    $query->where('group_id', $groupId);
                }
                if ($labId) {
                    $query->orWhere('lab_id', $labId);
                }
            });
        }

        $staffIds = $staffQuery->distinct()->pluck('user_id');
        foreach ($staffIds as $staffId) {
            self::notifyUser(
                (int) $staffId,
                $message,
                'approval',
                'tenant_activity',
                'tenant',
                (int) $tenant->id
            );
        }
    }

    /**
     * Executa a rotina 'log' no fluxo de negocio.
     */
    public static function log(int $tenantId, int $actorId, string $actorRole, string $action, string $entityType, ?int $entityId, string $description): void
    {
        Log::create([
            'tenant_id' => $tenantId,
            'user_id' => $actorId,
            'action' => $action,
            'user_role' => $actorRole,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'description' => $description,
        ]);
    }

    /**
     * Executa a rotina 'resolveActor' no fluxo de negocio.
     */
    public static function resolveActor(): array
    {
        $user = Auth::user();
        if ($user) {
            $tenantId = self::resolveTenantIdForUser($user);
            $actorRole = self::resolveRoleForUserAndTenant($user, $tenantId);

            return [
                'actor_id' => (int) $user->id,
                'actor_role' => $actorRole,
                'tenant_id' => $tenantId,
                'actor_type' => 'user',
            ];
        }

        return [
            'actor_id' => 0,
            'actor_role' => 'guest',
            'tenant_id' => null,
            'actor_type' => 'guest',
        ];
    }

    /**
     * Resolve tenant do usuario com prioridade para sessao ativa.
     */
    private static function resolveTenantIdForUser(User $user): ?int
    {
        $selectedTenantId = (int) session('active_tenant_id', 0);
        if ($selectedTenantId > 0) {
            $ownsTenant = Tenant::where('id', $selectedTenantId)
                ->where('creator_id', $user->id)
                ->exists();

            $hasRelation = UserRelation::where('user_id', $user->id)
                ->where('tenant_id', $selectedTenantId)
                ->where('status', 'active')
                ->exists();

            if ($ownsTenant || $hasRelation) {
                return $selectedTenantId;
            }
        }

        $creatorTenantId = Tenant::where('creator_id', $user->id)->value('id');
        if ($creatorTenantId) {
            return (int) $creatorTenantId;
        }

        $relatedTenantId = UserRelation::where('user_id', $user->id)
            ->where('status', 'active')
            ->value('tenant_id');

        return $relatedTenantId ? (int) $relatedTenantId : null;
    }

    /**
     * Resolve papel do usuario no tenant atual.
     */
    private static function resolveRoleForUserAndTenant(User $user, ?int $tenantId): string
    {
        if (!$tenantId) {
            return (string) ($user->role ?? 'owner');
        }

        $ownsTenant = Tenant::where('id', $tenantId)
            ->where('creator_id', $user->id)
            ->exists();

        if ($ownsTenant) {
            return 'owner';
        }

        $relationRole = UserRelation::where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->value('role');

        return (string) ($relationRole ?: ($user->role ?? 'student'));
    }

    /**
     * Executa a rotina 'formatRole' no fluxo de negocio.
     */
    public static function formatRole(string $role): string
    {
        $map = [
            'owner' => 'Owner',
            'teacher' => 'Professor',
            'assistant' => 'Assistente',
            'asssitant' => 'Assistente',
            'assitant' => 'Assistente',
            'student' => 'Aluno',
        ];

        return $map[$role] ?? Str::title($role);
    }
}
