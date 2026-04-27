<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Group;
use App\Models\Lab;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectVersion;
use App\Models\SubFolder;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRelation;
use App\Services\ActivityService;
use App\Services\CascadeDeleteService;
use App\Services\ProjectVersionStateService;
use App\Services\TenantContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SidebarDeleteController extends Controller
{
    /**
     * Executa a rotina 'destroyLab' no fluxo de negocio.
     */
    public function destroyLab(int $lab): JsonResponse
    {
        [$tenant, $subUser] = $this->resolveContext();

        $labModel = Lab::where('tenant_id', $tenant->id)
            ->where('id', $lab)
            ->firstOrFail();

        $this->authorizeTeacherForLab($subUser, (int) ($labModel->creator_subuser_id ?? 0));

        $membersCount = UserRelation::where('tenant_id', $tenant->id)
            ->where('lab_id', $labModel->id)
            ->where('status', 'active')
            ->distinct()
            ->count('user_id');

        if ($membersCount > 0) {
            return response()->json([
                'message' => 'Nao e possivel excluir este laboratorio enquanto houver usuarios vinculados.',
            ], 422);
        }

        $files = ProjectFile::where('tenant_id', $tenant->id)
            ->where('lab_id', $labModel->id)
            ->get(['path', 'size']);

        $freedBytes = (int) $files->sum('size');
        $name = $labModel->name;
        $labId = (int) $labModel->id;
        $redirectUrl = route('home');

        app(CascadeDeleteService::class)->destroyLab($tenant, $labModel);
        $this->logDelete('lab_delete', 'lab', $labId, 'Laboratorio excluido: ' . $name . '.');

        return response()->json([
            'success' => true,
            'message' => 'Laboratorio excluido.',
            'redirect_url' => $redirectUrl,
        ]);

        DB::transaction(function () use ($files, $labModel, $tenant, $freedBytes): void {
            $this->deleteFiles($files);
            Event::where('tenant_id', $tenant->id)
                ->where('lab_id', $labModel->id)
                ->delete();
            $labModel->delete();
            $this->decrementTenantStorage($tenant, $freedBytes);
        });

        $this->logDelete('lab_delete', 'lab', $labId, 'Laboratorio excluido: ' . $name . '.');

        return response()->json([
            'success' => true,
            'message' => 'Laboratorio excluido.',
            'redirect_url' => $redirectUrl,
        ]);

    }

    /**
     * Executa a rotina 'destroyGroup' no fluxo de negocio.
     */
    public function destroyGroup(int $group): JsonResponse
    {
        [$tenant, $subUser] = $this->resolveContext();

        $groupModel = Group::where('tenant_id', $tenant->id)
            ->where('id', $group)
            ->firstOrFail();

        $labOwnerId = (int) (Lab::where('tenant_id', $tenant->id)
            ->where('id', $groupModel->lab_id)
            ->value('creator_subuser_id') ?? 0);

        $this->authorizeTeacherForLab($subUser, $labOwnerId);

        $membersCount = UserRelation::where('tenant_id', $tenant->id)
            ->where('group_id', $groupModel->id)
            ->where('status', 'active')
            ->distinct()
            ->count('user_id');

        if ($membersCount > 0) {
            return response()->json([
                'message' => 'Nao e possivel excluir este grupo enquanto houver usuarios vinculados.',
            ], 422);
        }

        $files = ProjectFile::where('tenant_id', $tenant->id)
            ->where('group_id', $groupModel->id)
            ->get(['path', 'size']);

        $freedBytes = (int) $files->sum('size');
        $name = $groupModel->name;
        $groupId = (int) $groupModel->id;
        $labId = (int) $groupModel->lab_id;
        $redirectUrl = route('lab.index', ['lab' => $labId]);

        app(CascadeDeleteService::class)->destroyGroup($tenant, $groupModel);
        $this->logDelete('group_delete', 'group', $groupId, 'Grupo excluido: ' . $name . '.');

        return response()->json([
            'success' => true,
            'message' => 'Grupo excluido.',
            'redirect_url' => $redirectUrl,
        ]);

        DB::transaction(function () use ($files, $groupModel, $tenant, $freedBytes): void {
            $this->deleteFiles($files);
            $groupModel->delete();
            $this->decrementTenantStorage($tenant, $freedBytes);
        });

        $this->logDelete('group_delete', 'group', $groupId, 'Grupo excluido: ' . $name . '.');

        return response()->json([
            'success' => true,
            'message' => 'Grupo excluido.',
            'redirect_url' => $redirectUrl,
        ]);
        
    }

    /**
     * Executa a rotina 'destroyProject' no fluxo de negocio.
     */
    public function destroyProject(int $project): JsonResponse
    {
        [$tenant, $subUser] = $this->resolveContext();

        $projectModel = Project::where('tenant_id', $tenant->id)
            ->where('id', $project)
            ->firstOrFail();

        $labOwnerId = (int) (Lab::where('tenant_id', $tenant->id)
            ->where('id', $projectModel->lab_id)
            ->value('creator_subuser_id') ?? 0);

        if ($subUser && $subUser->role === 'student') {
            $this->authorizeStudentForProject($subUser, $projectModel);
        } else {
            $this->authorizeTeacherForLab($subUser, $labOwnerId);
        }

        $versionIds = ProjectVersion::where('tenant_id', $tenant->id)
            ->where('project_id', $projectModel->id)
            ->pluck('id');

        $files = $versionIds->isEmpty()
            ? collect()
            : ProjectFile::where('tenant_id', $tenant->id)
                ->whereIn('project_versions_id', $versionIds)
                ->get(['path', 'size']);

        $freedBytes = (int) $files->sum('size');
        $title = $projectModel->title;
        $projectId = (int) $projectModel->id;
        $groupId = (int) $projectModel->group_id;
        $labId = (int) $projectModel->lab_id;
        $redirectUrl = ($subUser && $subUser->role === 'student')
            ? route('subuser-home')
            : ($groupId > 0
                ? route('group.index', ['group' => $groupId])
                : route('lab.index', ['lab' => $labId]));

        app(CascadeDeleteService::class)->destroyProject($tenant, $projectModel);
        $this->logDelete('project_delete', 'project', $projectId, 'Projeto excluido: ' . $title . '.');

        return response()->json([
            'success' => true,
            'message' => 'Projeto excluido.',
            'redirect_url' => $redirectUrl,
        ]);

        DB::transaction(function () use ($files, $projectModel, $tenant, $freedBytes): void {
            $this->deleteFiles($files);
            $projectModel->delete();
            $this->decrementTenantStorage($tenant, $freedBytes);
        });

        $this->logDelete('project_delete', 'project', $projectId, 'Projeto excluido: ' . $title . '.');

        return response()->json([
            'success' => true,
            'message' => 'Projeto excluido.',
            'redirect_url' => $redirectUrl,
        ]);

    }

    /**
     * Executa a rotina 'destroySubfolder' no fluxo de negocio.
     */
    public function destroySubfolder(int $subfolder): JsonResponse
    {
        [$tenant, $subUser] = $this->resolveContext();

        $subfolderModel = SubFolder::where('tenant_id', $tenant->id)
            ->where('id', $subfolder)
            ->firstOrFail();

        $labOwnerId = (int) (Lab::where('tenant_id', $tenant->id)
            ->where('id', $subfolderModel->lab_id)
            ->value('creator_subuser_id') ?? 0);

        $this->authorizeTeacherForLab($subUser, $labOwnerId);

        $versionIds = ProjectVersion::where('tenant_id', $tenant->id)
            ->where('subfolder_id', $subfolderModel->id)
            ->pluck('id');

        $files = $versionIds->isEmpty()
            ? collect()
            : ProjectFile::where('tenant_id', $tenant->id)
                ->whereIn('project_versions_id', $versionIds)
                ->get(['path', 'size']);

        $freedBytes = (int) $files->sum('size');
        $name = $subfolderModel->name;
        $subfolderId = (int) $subfolderModel->id;
        $projectId = (int) $subfolderModel->project_id;
        $redirectUrl = route('project.index', ['project' => $projectId]);

        app(CascadeDeleteService::class)->destroySubfolder($tenant, $subfolderModel);
        $this->logDelete('subfolder_delete', 'subfolder', $subfolderId, 'Subfolder excluida: ' . $name . '.');

        return response()->json([
            'success' => true,
            'message' => 'Subfolder excluida.',
            'redirect_url' => $redirectUrl,
        ]);

        DB::transaction(function () use ($files, $subfolderModel, $tenant, $freedBytes, $projectId): void {
            $this->deleteFiles($files);
            $subfolderModel->delete();
            $this->decrementTenantStorage($tenant, $freedBytes);
            $this->refreshProjectVersionSummary((int) $tenant->id, $projectId);
        });

        $this->logDelete('subfolder_delete', 'subfolder', $subfolderId, 'Subfolder excluida: ' . $name . '.');

        return response()->json([
            'success' => true,
            'message' => 'Subfolder excluida.',
            'redirect_url' => $redirectUrl,
        ]);

    }

    /**
     * Executa a rotina 'resolveContext' no fluxo de negocio.
     */
    private function resolveContext(): array
    {
        $user = Auth::guard('web')->user();
        if (!$user) {
            abort(401);
        }

        $tenantContext = app(TenantContextService::class);
        $tenant = $tenantContext->resolveTenantFromSession($user, false);
        $role = $tenantContext->resolveRoleInTenant($user, $tenant);

        if ($role === 'owner') {
            return [$tenant, null];
        }

        if (!in_array($role, ['teacher', 'student'], true)) {
            abort(403);
        }

        $user->setAttribute('role', $role);

        return [$tenant, $user];
    }

    /**
     * Executa a rotina 'authorizeTeacherForLab' no fluxo de negocio.
     */
    private function authorizeTeacherForLab(?User $subUser, int $labOwnerId): void
    {
        if (!$subUser) {
            return;
        }

        if ($subUser->role !== 'teacher') {
            abort(403);
        }

        $isTeacherOwner = $labOwnerId > 0 && $labOwnerId === (int) $subUser->id;

        if (!$isTeacherOwner) {
            abort(403);
        }
    }

    /**
     * Executa a rotina 'authorizeStudentForProject' no fluxo de negocio.
     */
    private function authorizeStudentForProject(User $subUser, Project $project): void
    {
        if ($subUser->role !== 'student') {
            abort(403);
        }

        $studentGroupId = (int) ($subUser->group_id ?? 0);
        if ($studentGroupId <= 0 || (int) $project->group_id !== $studentGroupId) {
            abort(403);
        }

        if (!empty($subUser->lab_id) && (int) $project->lab_id !== (int) $subUser->lab_id) {
            abort(403);
        }
    }

    /**
     * Executa a rotina 'deleteFiles' no fluxo de negocio.
     */
    private function deleteFiles(Collection $files): void
    {
        foreach ($files as $file) {
            $path = (string) ($file->path ?? '');
            if ($path !== '' && Storage::disk('private')->exists($path)) {
                Storage::disk('private')->delete($path);
            }
        }
    }

    /**
     * Executa a rotina 'decrementTenantStorage' no fluxo de negocio.
     */
    private function decrementTenantStorage(Tenant $tenant, int $freedBytes): void
    {
        app(ProjectVersionStateService::class)->decrementTenantStorage($tenant, $freedBytes);
    }

    /**
     * Executa a rotina 'refreshProjectVersionSummary' no fluxo de negocio.
     */
    private function refreshProjectVersionSummary(int $tenantId, int $projectId): void
    {
        app(ProjectVersionStateService::class)->refreshProjectVersionSummary($tenantId, $projectId);
    }

    /**
     * Executa a rotina 'logDelete' no fluxo de negocio.
     */
    private function logDelete(string $action, string $entityType, int $entityId, string $description): void
    {
        $actor = ActivityService::resolveActor();
        if (empty($actor['tenant_id'])) {
            return;
        }

        ActivityService::log(
            (int) $actor['tenant_id'],
            (int) $actor['actor_id'],
            (string) $actor['actor_role'],
            $action,
            $entityType,
            $entityId,
            $description
        );
    }
}

