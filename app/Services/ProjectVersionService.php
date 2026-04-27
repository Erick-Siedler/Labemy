<?php

namespace App\Services;

use App\Models\Lab;
use App\Models\Project;
use App\Models\ProjectComment;
use App\Models\ProjectFile;
use App\Models\ProjectVersion;
use App\Models\SubFolder;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProjectVersionService
{
    public function __construct(
        private readonly ProjectOverviewService $overviewService,
        private readonly ProjectVersionStateService $versionStateService
    ) {
    }

    public function storeVersion(Request $request, Tenant $tenant): array
    {
        $usedBytes = (int) ($tenant->storage_used_mb ?? 0);
        $usedMb = $usedBytes / 1048576;

        $maxStorageMb = (float) ($tenant->limitFor('storage') ?? $tenant->max_storage_mb ?? 0);
        $remainingMb = max(0, $maxStorageMb - $usedMb);
        $maxUploadKb = (int) floor($remainingMb * 1024);

        if ($maxUploadKb <= 0) {
            return [
                'error' => [
                    'bag' => 'default',
                    'field' => 'version_file',
                    'message' => 'Limite de armazenamento atingido para o seu plano.',
                ],
            ];
        }

        $data = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'subfolder_id' => 'nullable|integer|exists:sub_folders,id',
            'title' => 'required|min:3',
            'description' => 'required|min:3',
            'version_file' => 'required|file|mimes:zip|max:' . $maxUploadKb,
        ]);

        $project = Project::where('tenant_id', (int) $tenant->id)
            ->where('id', (int) $data['project_id'])
            ->firstOrFail();

        $subfolder = null;
        if (!empty($data['subfolder_id'])) {
            $subfolder = SubFolder::where('tenant_id', (int) $tenant->id)
                ->where('project_id', (int) $project->id)
                ->where('id', (int) $data['subfolder_id'])
                ->firstOrFail();
        }

        if (!$subfolder) {
            $subfolder = $this->overviewService->ensureProjectSubfolders($project, (int) $tenant->id)->first();
            if (!$subfolder) {
                $subfolder = $this->overviewService->createDefaultSubfolder($project, (int) $tenant->id);
            }
        }

        $subUser = Auth::user();
        $isMember = $subUser && $subUser->role !== 'owner';
        if ($isMember && $subUser->role === 'teacher') {
            $labOwnerId = Lab::where('id', (int) $project->lab_id)->value('creator_subuser_id');
            if ((int) $labOwnerId !== (int) $subUser->id) {
                abort(403);
            }
        }

        if ($isMember && in_array((string) $subUser->role, ['assistant', 'assitant'], true)) {
            return [
                'error' => [
                    'bag' => 'default',
                    'field' => 'version_file',
                    'message' => 'Assistente nao pode enviar novas versoes.',
                ],
            ];
        }

        if ($isMember && $subUser->role === 'student') {
            if (!in_array((string) $project->status, ['approved', 'in_progress'], true)) {
                return [
                    'error' => [
                        'bag' => 'default',
                        'field' => 'version_file',
                        'message' => 'Seu status de projeto nao permite novas versoes.',
                    ],
                ];
            }

            $latestVersion = ProjectVersion::where('tenant_id', (int) $tenant->id)
                ->where('project_id', (int) $project->id)
                ->where('subfolder_id', (int) $subfolder->id)
                ->orderBy('version_number', 'desc')
                ->first();

            if ($latestVersion && $latestVersion->status_version === 'submitted') {
                return [
                    'error' => [
                        'bag' => 'default',
                        'field' => 'version_file',
                        'message' => 'Aguarde a avaliacao da ultima versao antes de enviar uma nova.',
                    ],
                ];
            }
        }

        $latestNumber = ProjectVersion::where('tenant_id', (int) $tenant->id)
            ->where('project_id', (int) $project->id)
            ->where('subfolder_id', (int) $subfolder->id)
            ->max('version_number');
        $nextVersion = $latestNumber ? $latestNumber + 1 : 1;

        $user = Auth::guard('web')->user();
        $isOwner = $user && $user->role === 'owner';
        $isSubUser = !is_null($subUser);

        $statusVersion = $isOwner ? 'approved' : 'submitted';
        $submittedAt = Carbon::now();
        $approvedAt = $isOwner ? Carbon::now() : null;
        $approvedBy = $isOwner ? $user->id : null;
        $submittedByUserId = $isSubUser ? $tenant->creator_id : ($user?->id ?? $tenant->creator_id);

        $storedPath = null;

        DB::beginTransaction();
        try {
            $version = ProjectVersion::create([
                'tenant_id' => (int) $tenant->id,
                'lab_id' => (int) $project->lab_id,
                'group_id' => (int) $project->group_id,
                'project_id' => (int) $project->id,
                'subfolder_id' => (int) $subfolder->id,
                'version_number' => $nextVersion,
                'title' => $data['title'],
                'description' => $data['description'],
                'status_version' => $statusVersion,
                'submitted_by' => $submittedByUserId,
                'submitted_at' => $submittedAt,
                'approved_by' => $approvedBy,
                'approved_at' => $approvedAt,
            ]);

            $file = $request->file('version_file');
            $storedName = uniqid('version_', true) . '.' . $file->getClientOriginalExtension();
            $path = "project-versions/{$tenant->id}/{$project->id}/{$subfolder->id}";
            $storedPath = $file->storeAs($path, $storedName, 'private');
            $sizeBytes = (int) $file->getSize();

            Tenant::where('id', (int) $tenant->id)->update([
                'storage_used_mb' => DB::raw('COALESCE(storage_used_mb, 0) + ' . $sizeBytes),
            ]);

            ProjectFile::create([
                'tenant_id' => (int) $tenant->id,
                'lab_id' => (int) $project->lab_id,
                'group_id' => (int) $project->group_id,
                'project_versions_id' => (int) $version->id,
                'uploaded_by' => $submittedByUserId,
                'original_name' => $file->getClientOriginalName(),
                'stored_name' => $storedName,
                'path' => $storedPath,
                'extension' => $file->getClientOriginalExtension(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $sizeBytes,
                'type' => 'compressed',
            ]);

            $subfolder->current_version = $nextVersion;
            $subfolder->save();

            $project->current_version = (int) (
                ProjectVersion::where('tenant_id', (int) $tenant->id)
                    ->where('project_id', (int) $project->id)
                    ->max('version_number') ?? $nextVersion
            );
            if ($submittedAt) {
                $project->submitted_at = $submittedAt;
            }
            if ($approvedAt) {
                $project->approved_at = $approvedAt;
            }
            $project->save();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            if ($storedPath) {
                Storage::disk('private')->delete($storedPath);
            }

            throw $e;
        }

        return [
            'project' => $project,
            'subfolder' => $subfolder,
            'version' => $version,
            'nextVersion' => $nextVersion,
            'isSubUser' => $isSubUser,
        ];
    }

    public function destroyVersion(Tenant $tenant, ProjectVersion $version): void
    {
        if ((int) $version->tenant_id !== (int) $tenant->id) {
            abort(403);
        }

        $project = Project::where('tenant_id', (int) $tenant->id)
            ->where('id', (int) $version->project_id)
            ->first();

        $files = ProjectFile::where('tenant_id', (int) $tenant->id)
            ->where('project_versions_id', (int) $version->id)
            ->get();
        $totalSize = (int) $files->sum('size');
        $subfolderId = (int) ($version->subfolder_id ?? 0);

        DB::beginTransaction();
        try {
            foreach ($files as $file) {
                if (!empty($file->path) && Storage::disk('private')->exists($file->path)) {
                    Storage::disk('private')->delete($file->path);
                }
            }

            ProjectFile::where('tenant_id', (int) $tenant->id)
                ->where('project_versions_id', (int) $version->id)
                ->delete();

            ProjectComment::where('tenant_id', (int) $tenant->id)
                ->where('project_version_id', (int) $version->id)
                ->delete();

            $version->delete();
            $this->versionStateService->decrementTenantStorage($tenant, $totalSize);

            if ($project) {
                $this->versionStateService->refreshSubfolderCurrentVersion((int) $tenant->id, (int) $project->id, $subfolderId);
                $this->versionStateService->refreshProjectVersionSummary((int) $tenant->id, (int) $project->id);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function updateVersion(Request $request, Tenant $tenant, ProjectVersion $version): array
    {
        if ((int) $version->tenant_id !== (int) $tenant->id) {
            abort(403);
        }

        $existingFile = ProjectFile::where('tenant_id', (int) $tenant->id)
            ->where('project_versions_id', (int) $version->id)
            ->first();

        $existingBytes = (int) ($existingFile->size ?? 0);
        $usedBytes = (int) ($tenant->storage_used_mb ?? 0);
        $usedMb = $usedBytes / 1048576;
        $maxStorageMb = (float) ($tenant->limitFor('storage') ?? $tenant->max_storage_mb ?? 0);
        $remainingMb = max(0, $maxStorageMb - $usedMb);
        $maxUploadKb = (int) floor(($remainingMb + ($existingBytes / 1048576)) * 1024);

        if ($request->hasFile('version_file') && $maxUploadKb <= 0) {
            return [
                'error' => [
                    'field' => 'version_file',
                    'message' => 'Limite de armazenamento atingido para o seu plano.',
                ],
            ];
        }

        $data = $request->validate([
            'title' => 'required|min:3',
            'description' => 'required|min:3',
            'version_file' => 'nullable|file|mimes:zip|max:' . $maxUploadKb,
        ]);

        $storedPath = null;
        DB::beginTransaction();
        try {
            $version->update([
                'title' => $data['title'],
                'description' => $data['description'],
            ]);

            if ($request->hasFile('version_file')) {
                $file = $request->file('version_file');
                $storedName = uniqid('version_', true) . '.' . $file->getClientOriginalExtension();
                $path = 'project-versions/' . $tenant->id . '/' . $version->project_id . '/' . (int) ($version->subfolder_id ?? 0);
                $storedPath = $file->storeAs($path, $storedName, 'private');
                $sizeBytes = (int) $file->getSize();

                if ($existingFile && !empty($existingFile->path) && Storage::disk('private')->exists($existingFile->path)) {
                    Storage::disk('private')->delete($existingFile->path);
                }

                $user = Auth::guard('web')->user();
                $subUser = Auth::user();
                $uploadedBy = $subUser ? $tenant->creator_id : ($user?->id ?? $tenant->creator_id);

                if ($existingFile) {
                    $existingFile->update([
                        'uploaded_by' => $uploadedBy,
                        'original_name' => $file->getClientOriginalName(),
                        'stored_name' => $storedName,
                        'path' => $storedPath,
                        'extension' => $file->getClientOriginalExtension(),
                        'mime_type' => $file->getClientMimeType(),
                        'size' => $sizeBytes,
                        'type' => 'compressed',
                    ]);
                } else {
                    ProjectFile::create([
                        'tenant_id' => (int) $tenant->id,
                        'lab_id' => (int) $version->lab_id,
                        'group_id' => (int) $version->group_id,
                        'project_versions_id' => (int) $version->id,
                        'uploaded_by' => $uploadedBy,
                        'original_name' => $file->getClientOriginalName(),
                        'stored_name' => $storedName,
                        'path' => $storedPath,
                        'extension' => $file->getClientOriginalExtension(),
                        'mime_type' => $file->getClientMimeType(),
                        'size' => $sizeBytes,
                        'type' => 'compressed',
                    ]);
                }

                $deltaBytes = $sizeBytes - $existingBytes;
                if ($deltaBytes !== 0) {
                    $currentUsed = (int) ($tenant->storage_used_mb ?? 0);
                    $newUsed = max(0, $currentUsed + $deltaBytes);
                    Tenant::where('id', (int) $tenant->id)->update([
                        'storage_used_mb' => $newUsed,
                    ]);
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            if ($storedPath) {
                Storage::disk('private')->delete($storedPath);
            }

            throw $e;
        }
        return [
            'version' => $version,
        ];
    }
}
