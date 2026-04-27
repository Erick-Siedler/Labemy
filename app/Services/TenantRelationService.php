<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRelation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TenantRelationService
{
    /**
     * Ordem de prioridade de papeis.
     *
     * owner > teacher > assistant > student
     */
    private const ROLE_PRIORITY = [
        'student' => 1,
        'assistant' => 2,
        'teacher' => 3,
        'owner' => 4,
    ];

    /**
     * Lista tenants acessiveis para um usuario.
     */
    public function listAccessibleTenants(User $user): Collection
    {
        $ownedTenantIds = Tenant::where('creator_id', $user->id)->pluck('id');

        $relatedTenantIds = UserRelation::where('user_id', $user->id)
            ->where('status', 'active')
            ->pluck('tenant_id');

        $tenantIds = $ownedTenantIds
            ->merge($relatedTenantIds)
            ->unique()
            ->values();

        if ($tenantIds->isEmpty()) {
            return new Collection();
        }

        return Tenant::whereIn('id', $tenantIds)
            ->orderBy('name')
            ->get();
    }

    /**
     * Verifica se usuario possui qualquer acesso owner.
     */
    public function hasOwnerAccess(User $user): bool
    {
        $ownsTenant = Tenant::where('creator_id', $user->id)->exists();
        if ($ownsTenant) {
            return true;
        }

        return UserRelation::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('role', 'owner')
            ->exists();
    }

    /**
     * Resolve papel ativo considerando tenant selecionado.
     */
    public function resolveActiveRole(User $user, ?int $tenantId = null): string
    {
        $tenantId = $tenantId ?? (int) session('active_tenant_id', 0);

        if ($tenantId > 0) {
            $ownsTenant = Tenant::where('id', $tenantId)
                ->where('creator_id', $user->id)
                ->exists();

            if ($ownsTenant) {
                return 'owner';
            }

            $relationRole = UserRelation::where('user_id', $user->id)
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->orderByRaw("FIELD(role, 'owner', 'teacher', 'assistant', 'student')")
                ->value('role');

            if (!empty($relationRole)) {
                return (string) $relationRole;
            }
        }

        return $this->hasOwnerAccess($user) ? 'owner' : 'student';
    }

    /**
     * Resolve rota de dashboard conforme papel ativo.
     */
    public function resolveDashboardRoute(User $user, ?int $tenantId = null): string
    {
        $role = $this->resolveActiveRole($user, $tenantId);

        if (in_array($role, ['teacher', 'assistant', 'asssitant', 'assitant', 'student'], true)) {
            return 'subuser-home';
        }

        return 'home';
    }

    /**
     * Resolve o tenant ativo com base na sessao.
     */
    public function resolveActiveTenant(User $user): ?Tenant
    {
        $selectedTenantId = (int) session('active_tenant_id', 0);
        if ($selectedTenantId > 0 && $this->userHasAccessToTenant($user, $selectedTenantId)) {
            return Tenant::where('id', $selectedTenantId)->first();
        }

        $ownedTenant = Tenant::where('creator_id', (int) $user->id)
            ->orderBy('name')
            ->first();
        if ($ownedTenant) {
            session([
                'active_tenant_id' => (int) $ownedTenant->id,
            ]);

            return $ownedTenant;
        }

        $firstTenant = $this->listAccessibleTenants($user)->first();
        if (!$firstTenant) {
            return null;
        }

        session([
            'active_tenant_id' => (int) $firstTenant->id,
        ]);

        return $firstTenant;
    }

    /**
     * Resolve a relacao ativa para um tenant.
     */
    public function resolveActiveRelation(User $user, int $tenantId): ?UserRelation
    {
        return UserRelation::where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderByRaw("FIELD(role, 'owner', 'teacher', 'assistant', 'student')")
            ->first();
    }

    /**
     * Seta tenant e relacao ativos em sessao.
     */
    public function activateTenant(User $user, int $tenantId): bool
    {
        if (!$this->userHasAccessToTenant($user, $tenantId)) {
            return false;
        }

        $relation = $this->resolveActiveRelation($user, $tenantId);

        session([
            'active_tenant_id' => $tenantId,
            'active_relation_id' => $relation?->id,
            'active_relation_role' => $relation?->role,
            'active_lab_id' => $relation?->lab_id,
            'active_group_id' => $relation?->group_id,
        ]);

        return true;
    }

    /**
     * Garante relacao owner para tenant criado pelo usuario.
     */
    public function ensureOwnerRelation(User $user, Tenant $tenant): UserRelation
    {
        return DB::transaction(function () use ($user, $tenant) {
            $relations = UserRelation::query()
                ->where('user_id', (int) $user->id)
                ->where('tenant_id', (int) $tenant->id)
                ->lockForUpdate()
                ->orderByDesc('id')
                ->get();

            $existing = $relations->first();

            if ($existing) {
                if ($relations->count() > 1) {
                    UserRelation::query()
                        ->whereIn('id', $relations->skip(1)->pluck('id'))
                        ->delete();
                }

                $scope = $this->normalizeRelationScope(
                    'owner',
                    $existing->lab_id,
                    $existing->group_id
                );

                $existing->update([
                    'lab_id' => $scope['lab_id'],
                    'group_id' => $scope['group_id'],
                    'role' => 'owner',
                    'status' => 'active',
                    'accepted_at' => now(),
                ]);

                return $existing->fresh();
            }

            $scope = $this->normalizeRelationScope('owner', null, null);

            return UserRelation::query()->create([
                'user_id' => (int) $user->id,
                'tenant_id' => (int) $tenant->id,
                'lab_id' => $scope['lab_id'],
                'group_id' => $scope['group_id'],
                'role' => 'owner',
                'status' => 'active',
                'accepted_at' => now(),
            ]);
        });
    }

    /**
     * Cria ou atualiza relacao de membro.
     */
    public function attachUserToTenant(
        User $user,
        int $tenantId,
        ?int $labId,
        ?int $groupId,
        string $role = 'student'
    ): UserRelation {
        return DB::transaction(function () use ($user, $tenantId, $labId, $groupId, $role) {
            $normalizedRole = $this->normalizeRole($role);

            $relations = UserRelation::query()
                ->where('user_id', (int) $user->id)
                ->where('tenant_id', (int) $tenantId)
                ->lockForUpdate()
                ->orderByDesc('id')
                ->get();

            $existing = $relations->first();

            if ($existing) {
                $resolvedRole = $normalizedRole;
                foreach ($relations as $relation) {
                    $resolvedRole = $this->resolveHighestRole((string) $relation->role, $resolvedRole);
                }

                if ($relations->count() > 1) {
                    UserRelation::query()
                        ->whereIn('id', $relations->skip(1)->pluck('id'))
                        ->delete();
                }

                $scope = $this->normalizeRelationScope(
                    $resolvedRole,
                    $labId ?? $existing->lab_id,
                    $groupId ?? $existing->group_id
                );

                $existing->update([
                    'lab_id' => $scope['lab_id'],
                    'group_id' => $scope['group_id'],
                    'role' => $resolvedRole,
                    'status' => 'active',
                    'accepted_at' => now(),
                ]);

                return $existing->fresh();
            }

            $scope = $this->normalizeRelationScope($normalizedRole, $labId, $groupId);

            return UserRelation::query()->create([
                'user_id' => (int) $user->id,
                'tenant_id' => (int) $tenantId,
                'lab_id' => $scope['lab_id'],
                'group_id' => $scope['group_id'],
                'role' => $normalizedRole,
                'status' => 'active',
                'accepted_at' => now(),
            ]);
        });
    }

    /**
     * Normaliza escopo da relacao conforme o papel.
     *
     * owner: sem lab/grupo
     * teacher/assistant: sem grupo
     * student: mantem lab/grupo recebidos
     */
    private function normalizeRelationScope(string $role, ?int $labId, ?int $groupId): array
    {
        $normalizedRole = $this->normalizeRole($role);
        $normalizedLabId = !empty($labId) ? (int) $labId : null;
        $normalizedGroupId = !empty($groupId) ? (int) $groupId : null;

        if ($normalizedRole === 'owner') {
            return [
                'lab_id' => null,
                'group_id' => null,
            ];
        }

        if (in_array($normalizedRole, ['teacher', 'assistant'], true)) {
            return [
                'lab_id' => $normalizedLabId,
                'group_id' => null,
            ];
        }

        return [
            'lab_id' => $normalizedLabId,
            'group_id' => $normalizedGroupId,
        ];
    }

    /**
     * Normaliza papel recebido para os valores aceitos.
     */
    private function normalizeRole(?string $role): string
    {
        $value = strtolower(trim((string) $role));

        if (in_array($value, ['assitant', 'asssitant'], true)) {
            $value = 'assistant';
        }

        if (!array_key_exists($value, self::ROLE_PRIORITY)) {
            return 'student';
        }

        return $value;
    }

    /**
     * Mantem o papel mais privilegiado entre atual e novo.
     */
    private function resolveHighestRole(string $currentRole, string $newRole): string
    {
        $current = $this->normalizeRole($currentRole);
        $new = $this->normalizeRole($newRole);

        if (self::ROLE_PRIORITY[$new] > self::ROLE_PRIORITY[$current]) {
            return $new;
        }

        return $current;
    }

    /**
     * Verifica se usuario possui acesso ao tenant.
     */
    public function userHasAccessToTenant(User $user, int $tenantId): bool
    {
        $ownsTenant = Tenant::where('id', $tenantId)
            ->where('creator_id', $user->id)
            ->exists();

        if ($ownsTenant) {
            return true;
        }

        return UserRelation::where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->exists();
    }

    /**
     * Revoga o acesso do usuario a um tenant nao-proprio.
     */
    public function revokeTenantAccess(User $user, int $tenantId): bool
    {
        $ownsTenant = Tenant::where('id', $tenantId)
            ->where('creator_id', $user->id)
            ->exists();

        if ($ownsTenant) {
            return false;
        }

        $affected = UserRelation::where('user_id', (int) $user->id)
            ->where('tenant_id', (int) $tenantId)
            ->delete();

        return $affected > 0;
    }
}
