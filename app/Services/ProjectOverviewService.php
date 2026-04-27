<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectVersion;
use App\Models\SubFolder;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProjectOverviewService
{
    public function buildProjectOverviewData(Tenant $tenant, Project $project, Collection $subfolders): array
    {
        $subfolderIds = $subfolders->pluck('id');
        $defaultSubfolderId = (int) ($subfolders->first()?->id ?? 0);

        $versions = ProjectVersion::where('tenant_id', (int) $tenant->id)
            ->where('project_id', (int) $project->id)
            ->orderBy('version_number', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->map(function ($version) use ($defaultSubfolderId) {
                if (empty($version->subfolder_id) && $defaultSubfolderId > 0) {
                    $version->subfolder_id = $defaultSubfolderId;
                }

                return $version;
            });

        $subfolderVersions = $subfolderIds->mapWithKeys(function ($subfolderId) use ($versions) {
            return [
                $subfolderId => $versions
                    ->where('subfolder_id', $subfolderId)
                    ->sortBy('version_number')
                    ->values(),
            ];
        });

        $subfolderStats = $subfolderIds->mapWithKeys(function ($subfolderId) use ($subfolderVersions) {
            $subfolderVersionsCollection = $subfolderVersions->get($subfolderId, collect());
            $latestVersion = $subfolderVersionsCollection->sortByDesc('version_number')->first();

            return [
                $subfolderId => [
                    'versions_count' => $subfolderVersionsCollection->count(),
                    'latest_version' => $latestVersion,
                ],
            ];
        });

        $latestVersion = $versions
            ->sortByDesc(function ($version) {
                $base = $version->submitted_at ?? $version->created_at;
                return $base ? Carbon::parse($base)->getTimestamp() : 0;
            })
            ->first();

        return [
            'subfolderVersions' => $subfolderVersions,
            'subfolderStats' => $subfolderStats,
            'totalVersions' => $versions->count(),
            'latestVersion' => $latestVersion,
            'latestSubmittedAt' => $latestVersion?->submitted_at,
            'latestApprovedAt' => $latestVersion?->approved_at,
        ];
    }

    public function ensureProjectSubfolders(Project $project, int $tenantId): Collection
    {
        $subfolders = SubFolder::where('tenant_id', $tenantId)
            ->where('project_id', (int) $project->id)
            ->orderBy('order_index')
            ->orderBy('id')
            ->get();

        if ($subfolders->isEmpty()) {
            $this->createDefaultSubfolder($project, $tenantId);
            $subfolders = SubFolder::where('tenant_id', $tenantId)
                ->where('project_id', (int) $project->id)
                ->orderBy('order_index')
                ->orderBy('id')
                ->get();
        }

        return $subfolders;
    }

    public function createDefaultSubfolder(Project $project, int $tenantId, ?string $name = null): SubFolder
    {
        $defaultName = trim((string) ($name ?: 'Subfolder 1'));
        $baseSlug = Str::slug($defaultName) ?: 'subfolder-1';
        $slug = $baseSlug;
        $suffix = 1;

        while (
            SubFolder::where('tenant_id', $tenantId)
                ->where('project_id', (int) $project->id)
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $baseSlug . '-' . $suffix;
            $suffix++;
        }

        $nextOrder = (int) (
            SubFolder::where('tenant_id', $tenantId)
                ->where('project_id', (int) $project->id)
                ->max('order_index') ?? 0
        ) + 1;

        return SubFolder::create([
            'tenant_id' => $tenantId,
            'lab_id' => $project->lab_id,
            'group_id' => $project->group_id,
            'project_id' => $project->id,
            'name' => $defaultName,
            'slug' => $slug,
            'description' => null,
            'order_index' => $nextOrder,
            'current_version' => 0,
        ]);
    }
}
