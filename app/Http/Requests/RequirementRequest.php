<?php

namespace App\Http\Requests;

use App\Models\Tenant;
use App\Models\UserRelation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

abstract class RequirementRequest extends FormRequest
{
    /**
     * Executa a rotina 'isReadOnlyActor' no fluxo de negocio.
     */
    protected function isReadOnlyActor(): bool
    {
        $user = Auth::guard('web')->user();
        if (!$user) {
            return true;
        }

        if ((string) ($user->plan ?? '') === 'solo') {
            return false;
        }

        $tenantId = $this->currentTenantId();
        if (!$tenantId) {
            return true;
        }

        $ownsTenant = Tenant::where('id', $tenantId)
            ->where('creator_id', $user->id)
            ->exists();

        if ($ownsTenant) {
            return true;
        }

        $role = UserRelation::where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->value('role');

        return (string) $role === 'teacher';
    }

    /**
     * Executa a rotina 'currentProjectId' no fluxo de negocio.
     */
    protected function currentProjectId(): ?int
    {
        $project = $this->route('project');

        if (is_object($project) && isset($project->id)) {
            return (int) $project->id;
        }

        if (is_numeric($project)) {
            return (int) $project;
        }

        return null;
    }

    /**
     * Executa a rotina 'currentTenantId' no fluxo de negocio.
     */
    protected function currentTenantId(): ?int
    {
        $user = Auth::guard('web')->user();
        if (!$user) {
            return null;
        }

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

        $ownerTenantId = Tenant::where('creator_id', $user->id)->value('id');
        if ($ownerTenantId) {
            return (int) $ownerTenantId;
        }

        $relatedTenantId = UserRelation::where('user_id', $user->id)
            ->where('status', 'active')
            ->value('tenant_id');

        return $relatedTenantId ? (int) $relatedTenantId : null;
    }
}

