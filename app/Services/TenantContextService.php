<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRelation;

class TenantContextService
{
    public function resolveTenantFromSession(User $user, bool $requireSelected = false): Tenant
    {
        $selectedTenantId = (int) session('active_tenant_id', 0);

        if ($selectedTenantId > 0) {
            $tenant = Tenant::where('id', $selectedTenantId)->first();
            if (!$tenant) {
                abort(403, 'Tenant selecionado invalido.');
            }

            if (!$this->hasAccessToTenant($user, (int) $tenant->id)) {
                abort(403, 'Voce nao possui acesso ao tenant selecionado.');
            }

            return $tenant;
        }

        if ($requireSelected) {
            abort(403, 'Nenhum tenant ativo selecionado.');
        }

        return $this->resolveFallbackTenant($user);
    }

    public function resolveFallbackTenant(User $user): Tenant
    {
        $ownedTenant = Tenant::where('creator_id', (int) $user->id)->first();
        if ($ownedTenant) {
            return $ownedTenant;
        }

        $relatedTenantId = (int) (UserRelation::where('user_id', (int) $user->id)
            ->where('status', 'active')
            ->value('tenant_id') ?? 0);

        if ($relatedTenantId > 0) {
            return Tenant::where('id', $relatedTenantId)->firstOrFail();
        }

        abort(403);
    }

    public function hasAccessToTenant(User $user, int $tenantId): bool
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
            ->exists();
    }

    public function isOwnerContext(User $user, Tenant $tenant): bool
    {
        if ((int) $tenant->creator_id === (int) $user->id) {
            return true;
        }

        return UserRelation::where('user_id', (int) $user->id)
            ->where('tenant_id', (int) $tenant->id)
            ->where('status', 'active')
            ->where('role', 'owner')
            ->exists();
    }

    public function resolveRoleInTenant(User $user, Tenant $tenant): string
    {
        if ((int) $tenant->creator_id === (int) $user->id) {
            return 'owner';
        }

        $role = (string) UserRelation::where('tenant_id', (int) $tenant->id)
            ->where('user_id', (int) $user->id)
            ->where('status', 'active')
            ->orderByRaw("FIELD(role, 'owner', 'teacher', 'assistant', 'student')")
            ->value('role');

        if ($role === '') {
            abort(403);
        }

        if (in_array($role, ['assitant', 'asssitant'], true)) {
            return 'assistant';
        }

        return $role;
    }
}
