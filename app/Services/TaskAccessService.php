<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Tenant;
use App\Models\UserRelation;
use Illuminate\Support\Facades\Auth;

class TaskAccessService
{
    public function resolveTenantForTaskAccess(): Tenant
    {
        $user = Auth::user();
        abort_if(!$user, 403);

        return app(TenantContextService::class)->resolveTenantFromSession($user, false);
    }

    public function canManageProjectTasks(Tenant $tenant, Project $project): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        $isTenantOwner = (int) $tenant->creator_id === (int) $user->id;
        if ($isTenantOwner) {
            return true;
        }

        $isOwnerRelation = UserRelation::where('user_id', (int) $user->id)
            ->where('tenant_id', (int) $tenant->id)
            ->where('status', 'active')
            ->where('role', 'owner')
            ->exists();
        if ($isOwnerRelation) {
            return true;
        }

        if ((string) ($user->plan ?? '') === 'solo') {
            return true;
        }

        return UserRelation::where('user_id', (int) $user->id)
            ->where('tenant_id', (int) $tenant->id)
            ->where('group_id', (int) $project->group_id)
            ->where('status', 'active')
            ->exists();
    }
}
