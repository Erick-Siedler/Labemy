<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Group;
use App\Models\Lab;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectVersion;
use App\Models\SubFolder;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CascadeDeleteService
{
    public function destroyLab(Tenant $tenant, Lab $labModel): void
    {
        $files = ProjectFile::where('tenant_id', (int) $tenant->id)
            ->where('lab_id', (int) $labModel->id)
            ->get(['path', 'size']);

        $freedBytes = (int) $files->sum('size');

        DB::transaction(function () use ($files, $labModel, $tenant, $freedBytes): void {
            $this->deleteFiles($files);
            Event::where('tenant_id', (int) $tenant->id)
                ->where('lab_id', (int) $labModel->id)
                ->delete();
            $labModel->delete();
            $this->decrementTenantStorage($tenant, $freedBytes);
        });
    }

    public function destroyGroup(Tenant $tenant, Group $groupModel): void
    {
        $files = ProjectFile::where('tenant_id', (int) $tenant->id)
            ->where('group_id', (int) $groupModel->id)
            ->get(['path', 'size']);

        $freedBytes = (int) $files->sum('size');

        DB::transaction(function () use ($files, $groupModel, $tenant, $freedBytes): void {
            $this->deleteFiles($files);
            $groupModel->delete();
            $this->decrementTenantStorage($tenant, $freedBytes);
        });
    }

    public function destroyProject(Tenant $tenant, Project $projectModel): void
    {
        $versionIds = ProjectVersion::where('tenant_id', (int) $tenant->id)
            ->where('project_id', (int) $projectModel->id)
            ->pluck('id');

        $files = $versionIds->isEmpty()
            ? collect()
            : ProjectFile::where('tenant_id', (int) $tenant->id)
                ->whereIn('project_versions_id', $versionIds)
                ->get(['path', 'size']);

        $freedBytes = (int) $files->sum('size');

        DB::transaction(function () use ($files, $projectModel, $tenant, $freedBytes): void {
            $this->deleteFiles($files);
            $projectModel->delete();
            $this->decrementTenantStorage($tenant, $freedBytes);
        });
    }

    public function destroySubfolder(Tenant $tenant, SubFolder $subfolderModel): void
    {
        $versionIds = ProjectVersion::where('tenant_id', (int) $tenant->id)
            ->where('subfolder_id', (int) $subfolderModel->id)
            ->pluck('id');

        $files = $versionIds->isEmpty()
            ? collect()
            : ProjectFile::where('tenant_id', (int) $tenant->id)
                ->whereIn('project_versions_id', $versionIds)
                ->get(['path', 'size']);

        $freedBytes = (int) $files->sum('size');
        $projectId = (int) $subfolderModel->project_id;

        DB::transaction(function () use ($files, $subfolderModel, $tenant, $freedBytes, $projectId): void {
            $this->deleteFiles($files);
            $subfolderModel->delete();
            $this->decrementTenantStorage($tenant, $freedBytes);
            $this->refreshProjectVersionSummary((int) $tenant->id, $projectId);
        });
    }

    private function deleteFiles(Collection $files): void
    {
        foreach ($files as $file) {
            $path = (string) ($file->path ?? '');
            if ($path !== '' && Storage::disk('private')->exists($path)) {
                Storage::disk('private')->delete($path);
            }
        }
    }

    private function decrementTenantStorage(Tenant $tenant, int $freedBytes): void
    {
        app(ProjectVersionStateService::class)->decrementTenantStorage($tenant, $freedBytes);
    }

    private function refreshProjectVersionSummary(int $tenantId, int $projectId): void
    {
        app(ProjectVersionStateService::class)->refreshProjectVersionSummary($tenantId, $projectId);
    }
}
