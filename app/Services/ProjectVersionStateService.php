<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectVersion;
use App\Models\SubFolder;
use App\Models\Tenant;

class ProjectVersionStateService
{
    public function decrementTenantStorage(Tenant $tenant, int $freedBytes): void
    {
        if ($freedBytes <= 0) {
            return;
        }

        $currentUsed = (int) ($tenant->storage_used_mb ?? 0);
        $newUsed = max(0, $currentUsed - $freedBytes);

        Tenant::where('id', (int) $tenant->id)->update([
            'storage_used_mb' => $newUsed,
        ]);
    }

    public function refreshSubfolderCurrentVersion(int $tenantId, int $projectId, int $subfolderId): void
    {
        if ($subfolderId <= 0) {
            return;
        }

        $subfolderLatest = ProjectVersion::where('tenant_id', $tenantId)
            ->where('project_id', $projectId)
            ->where('subfolder_id', $subfolderId)
            ->orderBy('version_number', 'desc')
            ->first();

        SubFolder::where('tenant_id', $tenantId)
            ->where('project_id', $projectId)
            ->where('id', $subfolderId)
            ->update([
                'current_version' => (int) ($subfolderLatest?->version_number ?? 0),
            ]);
    }

    public function refreshProjectVersionSummary(int $tenantId, int $projectId): void
    {
        $project = Project::where('tenant_id', $tenantId)
            ->where('id', $projectId)
            ->first();

        if (!$project) {
            return;
        }

        $latestVersion = ProjectVersion::where('tenant_id', $tenantId)
            ->where('project_id', $project->id)
            ->orderBy('version_number', 'desc')
            ->first();

        if ($latestVersion) {
            $project->current_version = $latestVersion->version_number;
            $project->submitted_at = $latestVersion->submitted_at;
            $project->approved_at = $latestVersion->approved_at;
        } else {
            $project->current_version = 1;
            $project->submitted_at = null;
            $project->approved_at = null;
        }

        $project->save();
    }
}
