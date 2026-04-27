<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Group;
use App\Models\Lab;
use App\Models\Notification;
use App\Models\Project;
use App\Models\ProjectVersion;
use App\Models\SubFolder;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRelation;
use Illuminate\Support\Collection;

class NotificationTenantService
{
    /**
     * Filtra apenas notificacoes que pertencem ao tenant ativo.
     */
    public function filterVisibleCollection($notifications, ?User $user): Collection
    {
        $collection = collect($notifications)->filter(function ($item) {
            return $item instanceof Notification;
        })->values();

        if (!$user) {
            return collect();
        }

        $activeTenantId = $this->resolveActiveTenantId($user);
        if ($activeTenantId <= 0 || !$this->shouldEnableForContext($user, $activeTenantId)) {
            return collect();
        }

        $tenantByNotificationId = $this->resolveTenantIdsForNotifications($collection);
        return $collection->filter(function (Notification $notification) use ($tenantByNotificationId, $activeTenantId) {
            $tenantId = $tenantByNotificationId[$notification->id] ?? null;
            if ($tenantId === null) {
                return false;
            }

            return (int) $tenantId === (int) $activeTenantId;
        })->values();
    }

    /**
     * Define se notificacoes devem ficar ativas no contexto atual.
     */
    public function shouldEnableForContext(?User $user, ?int $tenantId = null): bool
    {
        if (!$user) {
            return false;
        }

        $resolvedTenantId = (int) ($tenantId ?? 0);
        if ($resolvedTenantId <= 0) {
            $resolvedTenantId = $this->resolveActiveTenantId($user);
        }

        if ($resolvedTenantId <= 0) {
            return (string) ($user->plan ?? '') !== 'solo';
        }

        $tenantPlan = (string) (Tenant::where('id', $resolvedTenantId)->value('plan') ?? '');
        if ($tenantPlan !== 'solo') {
            return true;
        }

        return !$this->isOwnerInTenant($user, $resolvedTenantId);
    }

    /**
     * Lista ids visiveis para um usuario e tenant ativo.
     */
    public function visibleIdsForUser(User $user, ?array $candidateIds = null): array
    {
        $query = Notification::where('user_id', (int) $user->id)
            ->where('table', 'users')
            ->orderByDesc('created_at');

        if (!empty($candidateIds)) {
            $query->whereIn('id', $candidateIds);
        }

        return $this->filterVisibleCollection($query->get(), $user)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Remove somente notificacoes visiveis.
     */
    public function deleteVisible(User $user, ?array $candidateIds = null): int
    {
        $visibleIds = $this->visibleIdsForUser($user, $candidateIds);
        if (empty($visibleIds)) {
            return 0;
        }

        return (int) Notification::where('user_id', (int) $user->id)
            ->where('table', 'users')
            ->whereIn('id', $visibleIds)
            ->delete();
    }

    /**
     * Resolve tenant da notificacao a partir da entidade referenciada.
     */
    public function resolveTenantIdForNotification(Notification $notification): ?int
    {
        return $this->resolveTenantIdByReference(
            (string) ($notification->reference_type ?? ''),
            (int) ($notification->reference_id ?? 0)
        );
    }

    /**
     * Resolve tenant por tipo/id de referencia.
     */
    public function resolveTenantIdByReference(?string $referenceType, ?int $referenceId): ?int
    {
        $normalizedType = strtolower(trim((string) $referenceType));
        $normalizedId = (int) ($referenceId ?? 0);
        if ($normalizedType === '' || $normalizedId <= 0) {
            return null;
        }

        return match ($normalizedType) {
            'tenant' => $normalizedId,
            'event' => $this->pluckTenantId(Event::query(), $normalizedId),
            'lab' => $this->pluckTenantId(Lab::query(), $normalizedId),
            'group' => $this->pluckTenantId(Group::query(), $normalizedId),
            'project' => $this->pluckTenantId(Project::query(), $normalizedId),
            'subfolder' => $this->pluckTenantId(SubFolder::query(), $normalizedId),
            'project_version', 'version' => $this->pluckTenantId(ProjectVersion::query(), $normalizedId),
            'task' => $this->pluckTenantId(Task::query(), $normalizedId),
            default => null,
        };
    }

    /**
     * Resolve tenant ativo em sessao.
     */
    private function resolveActiveTenantId(User $user): int
    {
        $selectedTenantId = (int) session('active_tenant_id', 0);
        if ($selectedTenantId > 0) {
            $ownsTenant = Tenant::where('id', $selectedTenantId)
                ->where('creator_id', (int) $user->id)
                ->exists();

            $hasRelation = UserRelation::where('user_id', (int) $user->id)
                ->where('tenant_id', $selectedTenantId)
                ->where('status', 'active')
                ->exists();

            if ($ownsTenant || $hasRelation) {
                return $selectedTenantId;
            }
        }

        $ownedTenantId = (int) (Tenant::where('creator_id', (int) $user->id)->value('id') ?? 0);
        if ($ownedTenantId > 0) {
            return $ownedTenantId;
        }

        return (int) (UserRelation::where('user_id', (int) $user->id)
            ->where('status', 'active')
            ->value('tenant_id') ?? 0);
    }

    /**
     * Verifica se usuario e owner do tenant informado.
     */
    private function isOwnerInTenant(User $user, int $tenantId): bool
    {
        if ($tenantId <= 0) {
            return false;
        }

        $ownsTenant = Tenant::where('id', $tenantId)
            ->where('creator_id', (int) $user->id)
            ->exists();

        if ($ownsTenant) {
            return true;
        }

        return UserRelation::where('user_id', (int) $user->id)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where('role', 'owner')
            ->exists();
    }

    /**
     * Resolve tenant_id para um conjunto de notificacoes.
     */
    private function resolveTenantIdsForNotifications(Collection $notifications): array
    {
        $tenantByNotificationId = [];
        if ($notifications->isEmpty()) {
            return $tenantByNotificationId;
        }

        $groupedIds = [];

        foreach ($notifications as $notification) {
            $referenceType = strtolower(trim((string) ($notification->reference_type ?? '')));
            $referenceId = (int) ($notification->reference_id ?? 0);

            if ($referenceType === '' || $referenceId <= 0) {
                continue;
            }

            if ($referenceType === 'tenant') {
                $tenantByNotificationId[(int) $notification->id] = $referenceId;
                continue;
            }

            if (!isset($groupedIds[$referenceType])) {
                $groupedIds[$referenceType] = [];
            }
            $groupedIds[$referenceType][] = $referenceId;
        }

        $tenantByReference = [];
        foreach ($groupedIds as $referenceType => $ids) {
            $uniqueIds = collect($ids)->map(fn ($id) => (int) $id)->unique()->values()->all();

            $tenantByReference[$referenceType] = match ($referenceType) {
                'event' => $this->pluckTenantMap(Event::query(), $uniqueIds),
                'lab' => $this->pluckTenantMap(Lab::query(), $uniqueIds),
                'group' => $this->pluckTenantMap(Group::query(), $uniqueIds),
                'project' => $this->pluckTenantMap(Project::query(), $uniqueIds),
                'subfolder' => $this->pluckTenantMap(SubFolder::query(), $uniqueIds),
                'project_version', 'version' => $this->pluckTenantMap(ProjectVersion::query(), $uniqueIds),
                'task' => $this->pluckTenantMap(Task::query(), $uniqueIds),
                default => [],
            };
        }

        foreach ($notifications as $notification) {
            $referenceType = strtolower(trim((string) ($notification->reference_type ?? '')));
            $referenceId = (int) ($notification->reference_id ?? 0);
            if ($referenceType === '' || $referenceId <= 0 || $referenceType === 'tenant') {
                continue;
            }

            $tenantId = $tenantByReference[$referenceType][$referenceId] ?? null;
            if (!empty($tenantId)) {
                $tenantByNotificationId[(int) $notification->id] = (int) $tenantId;
            }
        }

        return $tenantByNotificationId;
    }

    /**
     * Busca tenant_id por id da entidade.
     */
    private function pluckTenantId($query, int $id): ?int
    {
        $tenantId = $query->where('id', $id)->value('tenant_id');
        return $tenantId ? (int) $tenantId : null;
    }

    /**
     * Busca mapa [entidade_id => tenant_id].
     */
    private function pluckTenantMap($query, array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return $query->whereIn('id', $ids)
            ->pluck('tenant_id', 'id')
            ->map(fn ($tenantId) => (int) $tenantId)
            ->all();
    }
}
