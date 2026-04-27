<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Lab;
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Models\Group;
use App\Models\Project;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use App\Models\Notification;
use App\Models\ProjectVersion;
use App\Models\ProjectFile;
use App\Models\ProjectComment;
use App\Models\SubFolder;
use App\Models\Task;
use App\Models\User;
use App\Models\UserRelation;
use App\Services\HomeOwnerDataService;
use Illuminate\Support\Facades\DB;
use App\Services\ActivityService;
use App\Services\TenantContextService;
use App\Services\UserUiPreferencesService;
use App\Services\ProjectVersionStateService;
use App\Services\ProjectOverviewService;
use App\Services\ProjectVersionService;
use Illuminate\Http\UploadedFile;

class ProjectController extends Controller
{
    private const VERSION_CHUNK_MAX_KB = 10240;
    private const VERSION_CHUNK_MAX_TOTAL = 50000;

    /**
     * Lista e prepara os dados exibidos na tela.
     */
    public function index($project, HomeOwnerDataService $homeOwnerData)
    {
        $user = Auth::user();
        if (!$user) {
            abort(403);
        }

        $tenant = $this->resolveTenantForProjectAccess($user);
        if ($this->isSoloTenantContext($tenant, $user)) {
            Project::where('tenant_id', $tenant->id)
                ->where('id', $project)
                ->firstOrFail();

            return redirect()->route('home-solo', ['project' => $project]);
        }

        $hasOwnerContext = (int) $tenant->creator_id === (int) $user->id
            || UserRelation::where('user_id', (int) $user->id)
                ->where('tenant_id', (int) $tenant->id)
                ->where('status', 'active')
                ->where('role', 'owner')
                ->exists();

        if (!$hasOwnerContext) {
            return $this->indexSubuserOverview($project, $user, $tenant);
        }

        $projectData = Project::with(['lab', 'group'])
            ->where('tenant_id', $tenant->id)
            ->where('id', $project)
            ->firstOrFail();

        $subfolders = $this->ensureProjectSubfolders($projectData, $tenant->id);
        $overview = $this->buildProjectOverviewData($tenant, $projectData, $subfolders);

        $data = $homeOwnerData->build($user);
        $data['project'] = $projectData;
        $data['lab'] = $projectData->lab;
        $data['group'] = $projectData->group;
        $data['subfolders'] = $subfolders;
        $data['subfolderVersions'] = $overview['subfolderVersions'];
        $data['subfolderStats'] = $overview['subfolderStats'];
        $data['tasks'] = $this->getProjectTasks((int) $tenant->id, (int) $projectData->id);
        $data['totalVersions'] = $overview['totalVersions'];
        $data['latestVersion'] = $overview['latestVersion'];
        $data['latestSubmittedAt'] = $overview['latestSubmittedAt'];
        $data['latestApprovedAt'] = $overview['latestApprovedAt'];
        $data['versionFlowRecentLimit'] = 6;
        $data['canCreateSubfolder'] = $hasOwnerContext;
        $data['canManageTasks'] = $this->canManageProjectTasks($tenant, $projectData);
        $data['pageTitle'] = $projectData->title;
        $data['pageBreadcrumbHome'] = 'Inicio';
        $data['pageBreadcrumbCurrent'] = 'Projeto';

        $theme = $this->getTheme($data['userPreferences']);

        return view('main.home.labs-groups-projects.index-project', $data, [
            'theme' => $theme
        ]);
    }

    /**
     * Executa a rotina 'indexSubuserOverview' no fluxo de negocio.
     */
    private function indexSubuserOverview($project, $subUser, ?Tenant $tenant = null)
    {
        $tenant = $tenant ?: $this->resolveTenantForProjectAccess($subUser);

        $role = $subUser->role;
        $isTeacher = $role === 'teacher';
        $isStudent = $role === 'student';

        $projectData = Project::with(['lab', 'group'])
            ->where('tenant_id', $tenant->id)
            ->where('id', $project)
            ->firstOrFail();
        $canManageTasks = $this->canManageProjectTasks($tenant, $projectData);

        $isAssignedLab = UserRelation::where('user_id', (int) $subUser->id)
            ->where('tenant_id', (int) $tenant->id)
            ->where('lab_id', (int) $projectData->lab_id)
            ->where('status', 'active')
            ->exists();
        $isAssignedGroup = UserRelation::where('user_id', (int) $subUser->id)
            ->where('tenant_id', (int) $tenant->id)
            ->where('group_id', (int) $projectData->group_id)
            ->where('status', 'active')
            ->exists();

        $isTeacherOwner = (int) ($projectData->lab?->creator_subuser_id ?? 0) === (int) $subUser->id;

        if ($isTeacher) {
            if (!$isTeacherOwner && !$isAssignedLab) {
                abort(403);
            }
        } elseif ($isStudent) {
            if (!$isAssignedGroup) {
                abort(403);
            }
        } elseif ($subUser->lab_id && (int) $projectData->lab_id !== (int) $subUser->lab_id) {
            abort(403);
        }

        $subfolders = $this->ensureProjectSubfolders($projectData, $tenant->id);
        $overview = $this->buildProjectOverviewData($tenant, $projectData, $subfolders);

        $groups = $isStudent
            ? Group::with('projects.subfolders')
                ->where('id', (int) $projectData->group_id)
                ->orderBy('name')
                ->get()
            : Group::with('projects.subfolders')
                ->where('lab_id', $projectData->lab_id)
                ->orderBy('name')
                ->get();

        $teacherLabs = $isTeacher
            ? Lab::with('groups.projects.subfolders')
                ->where('tenant_id', $tenant->id)
                ->where(function ($query) use ($subUser) {
                    $query->where('creator_subuser_id', $subUser->id);
                    if (!empty($subUser->lab_id)) {
                        $query->orWhere('id', $subUser->lab_id);
                    }
                })
                ->orderBy('name')
                ->get()
            : collect();

        $data = [
            'user' => $subUser,
            'tenant' => $tenant,
            'labs' => $teacherLabs,
            'groups' => $groups,
            'project' => $projectData,
            'lab' => $projectData->lab,
            'group' => $projectData->group,
            'subfolders' => $subfolders,
            'subfolderVersions' => $overview['subfolderVersions'],
            'subfolderStats' => $overview['subfolderStats'],
            'tasks' => $this->getProjectTasks((int) $tenant->id, (int) $projectData->id),
            'totalVersions' => $overview['totalVersions'],
            'latestVersion' => $overview['latestVersion'],
            'latestSubmittedAt' => $overview['latestSubmittedAt'],
            'latestApprovedAt' => $overview['latestApprovedAt'],
            'versionFlowRecentLimit' => 6,
            'notifications' => Notification::where('user_id', $subUser->id)->where('table', 'users')->orderBy('created_at', 'desc')->get(),
            'userPreferences' => User::where('id', $subUser->id)->value('preferences'),
            'pageTitle' => $projectData->title,
            'pageBreadcrumbHome' => 'Inicio',
            'pageBreadcrumbCurrent' => 'Projeto',
            'layout' => 'layouts.header-side-not-sub',
            'canCreateLab' => $isTeacher,
            'canCreateGroup' => $isTeacher,
            'canCreateProject' => $isStudent || ($isTeacher && ($isTeacherOwner || $isAssignedLab)),
            'canEditLabStatus' => $isTeacherOwner,
            'canEditGroupStatus' => $isTeacherOwner,
            'canEditProjectStatus' => $isTeacherOwner,
            'canCreateSubfolder' => $isStudent || ($isTeacher && ($isTeacherOwner || $isAssignedLab)),
            'canManageTasks' => $canManageTasks,
        ];

        $theme = $this->getTheme($data['userPreferences']);

        return view('main.home.labs-groups-projects.index-project', $data, [
            'theme' => $theme,
            'user' => $subUser,
        ]);
    }

    /**
     * Executa a rotina 'buildProjectOverviewData' no fluxo de negocio.
     */
    private function buildProjectOverviewData(Tenant $tenant, Project $projectData, $subfolders): array
    {
        return app(ProjectOverviewService::class)->buildProjectOverviewData($tenant, $projectData, $subfolders);
    }

    /**
     * Executa a rotina 'ensureProjectSubfolders' no fluxo de negocio.
     */
    private function ensureProjectSubfolders(Project $project, int $tenantId)
    {
        return app(ProjectOverviewService::class)->ensureProjectSubfolders($project, $tenantId);
    }

    /**
     * Executa a rotina 'createDefaultSubfolder' no fluxo de negocio.
     */
    private function createDefaultSubfolder(Project $project, int $tenantId, ?string $name = null): SubFolder
    {
        return app(ProjectOverviewService::class)->createDefaultSubfolder($project, $tenantId, $name);
    }
    /**
     * Executa a rotina 'getTheme' no fluxo de negocio.
     */
    private function getTheme($userPreferences)
    {
        return app(UserUiPreferencesService::class)->resolveTheme($userPreferences);
    }

    /**
     * Executa a rotina 'resolveTenantFromAuth' no fluxo de negocio.
     */
    private function resolveTenantFromAuth()
    {
        $user = Auth::user();
        abort_if(!$user, 403);

        return app(TenantContextService::class)->resolveTenantFromSession($user, true);
    }

    /**
     * Resolve o tenant para acesso ao modulo de projetos.
     */
    private function resolveTenantForProjectAccess(User $user): Tenant
    {
        return app(TenantContextService::class)->resolveTenantFromSession($user, true);
    }

    /**
     * Define se o usuario atual pode gerenciar tasks do projeto.
     */
    private function canManageProjectTasks(Tenant $tenant, Project $project): bool
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

        if ($this->isSoloTenantContext($tenant, $user)) {
            return true;
        }

        return UserRelation::where('user_id', (int) $user->id)
            ->where('tenant_id', (int) $tenant->id)
            ->where('group_id', (int) $project->group_id)
            ->where('status', 'active')
            ->exists();
    }

    /**
     * Define se o contexto atual eh de tenant solo do proprio usuario.
     */
    private function isSoloTenantContext(Tenant $tenant, User $user): bool
    {
        return (int) $tenant->creator_id === (int) $user->id
            && (string) ($tenant->plan ?? '') === 'solo';
    }

    /**
     * Executa a rotina 'downloadVersionFile' no fluxo de negocio.
     */
    public function downloadVersionFile(ProjectFile $projectFile)
    {
        $tenant = $this->resolveTenantFromAuth();

        if ((int) $projectFile->tenant_id !== (int) $tenant->id) {
            abort(403);
        }

        if (!Storage::disk('private')->exists($projectFile->path)) {
            abort(404);
        }

        $baseName = $projectFile->original_name ?: $projectFile->stored_name;
        $downloadName = 'projeto_' . $projectFile->project_versions_id . '_' . $baseName;
        $actor = ActivityService::resolveActor();
        if (!empty($actor['tenant_id'])) {
            ActivityService::log(
                (int) $actor['tenant_id'],
                (int) $actor['actor_id'],
                (string) $actor['actor_role'],
                'file_download',
                'project_file',
                (int) $projectFile->id,
                'Download de arquivo: ' . $baseName . '.'
            );
        }

        return Storage::disk('private')->download(
            $projectFile->path,
            $downloadName,
            ['Content-Type' => $projectFile->mime_type ?: 'application/zip']
        );
    }

    /**
     * Valida os dados recebidos e persiste um novo registro.
     */
    function store(Request $request){
        $tenant = $this->resolveTenantFromAuth();
        $subUser = Auth::user();
        $isMember = $subUser && $subUser->role !== 'owner';
        $isOwner = Auth::check() && Auth::user()->role === 'owner';
        $isPrivileged = $isOwner || ($subUser && $subUser->role === 'teacher');
        $isSubUser = !is_null($subUser);

        if ($isMember && !in_array((string) $subUser->role, ['student', 'teacher'], true)) {
            return response()->json([
                'message' => 'Somente alunos e professores podem criar projetos.',
            ], 403);
        }

        if ($tenant->hasReachedLimit('projects', Project::where('tenant_id', $tenant->id)->count())) {
            return response()->json([
                'message' => 'Limite de projetos atingido para o seu plano.',
            ], 422);
        }

        $rules = [
            'title' => 'required|min:3',
            'lab_id' => [
                'required',
                'integer',
                Rule::exists('labs', 'id')->where('tenant_id', $tenant->id),
            ],
            'group_id' => [
                'required',
                'integer',
                Rule::exists('groups', 'id')->where('tenant_id', $tenant->id),
            ],
        ];

        if ($isPrivileged) {
            $rules['description'] = 'nullable|min:3';
        } else {
            $rules['description'] = 'required|min:3';
        }

        $data = $request->validate($rules);

        $tenant_id = $tenant->id;
        $group = Group::where('tenant_id', $tenant_id)
            ->where('id', $data['group_id'])
            ->where('lab_id', $data['lab_id'])
            ->firstOrFail();

        if ($isMember && $subUser->role === 'teacher') {
            $labOwnerId = Lab::where('tenant_id', $tenant_id)
                ->where('id', $data['lab_id'])
                ->value('creator_subuser_id');
            $isAssignedLab = !empty($subUser->lab_id) && (int) $data['lab_id'] === (int) $subUser->lab_id;

            if ((int) $labOwnerId !== (int) $subUser->id && !$isAssignedLab) {
                abort(403);
            }
        }

        if ($isMember && $subUser->role === 'student') {
            $studentGroupId = (int) ($subUser->group_id ?? 0);
            $studentLabId = (int) ($subUser->lab_id ?? 0);
            if ($studentGroupId <= 0 || (int) $data['group_id'] !== $studentGroupId) {
                abort(403);
            }
            if ($studentLabId > 0 && (int) $data['lab_id'] !== $studentLabId) {
                abort(403);
            }
        }

        $status = $isOwner ? 'approved' : 'draft';
        $projectData = [
            'tenant_id' => $tenant_id,
            'lab_id' => $data['lab_id'],
            'group_id' => $group->id,
            'title' => $data['title'],
            'slug' => Str::replace(' ', '-', $data['title']),
            'description' => $data['description'] ?? '',
            'status' => $status,
            'submitted_at' => Carbon::now(),
        ];

        if ($isOwner) {
            $projectData['approved_at'] = Carbon::now();
        }

        $project = Project::create($projectData);
        $this->createDefaultSubfolder($project, (int) $tenant->id, 'Subfolder 1');
        $actor = ActivityService::resolveActor();
        if (!empty($actor['tenant_id'])) {
            ActivityService::log(
                (int) $actor['tenant_id'],
                (int) $actor['actor_id'],
                (string) $actor['actor_role'],
                'project_create',
                'project',
                (int) $project->id,
                'Projeto criado: ' . $project->title . '.'
            );
        }
        if ($isSubUser) {
            $labName = Lab::where('id', $group->lab_id)->value('name') ?? 'Laboratorio';
            $groupName = $group->name ?? 'Grupo';
            $message = "Novo projeto submetido: {$project->title} (Lab: {$labName} / Grupo: {$groupName}).";
            $this->notifyOwnerAndTeachers($tenant, $message, $group->lab_id, $group->id);
        }

        return response()->json([
            'success' => true   
        ], 201);
    }

    
    /**
     * Executa a rotina 'storeVersion' no fluxo de negocio.
     */
    public function storeVersion(Request $request)
    {
        $tenant = $this->resolveTenantFromAuth();
        $result = app(ProjectVersionService::class)->storeVersion($request, $tenant);

        if (!empty($result['error'])) {
            $error = $result['error'];
            $bag = (string) ($error['bag'] ?? 'default');
            $field = (string) ($error['field'] ?? 'version_file');
            $message = (string) ($error['message'] ?? 'Nao foi possivel enviar a versao.');

            return back()->withErrors([$field => $message], $bag);
        }

        /** @var \App\Models\Project $project */
        $project = $result['project'];
        /** @var \App\Models\SubFolder $subfolder */
        $subfolder = $result['subfolder'];
        /** @var \App\Models\ProjectVersion $version */
        $version = $result['version'];
        $nextVersion = (int) ($result['nextVersion'] ?? 0);
        $isSubUser = (bool) ($result['isSubUser'] ?? false);

        $this->notifyAndLogVersionCreated($tenant, $project, $subfolder, $version, $nextVersion, $isSubUser);

        return back()->with('success', 'Versao adicionada.');

        $usedBytes = (int) ($tenant->storage_used_mb ?? 0);
        $usedMb    = $usedBytes / 1048576;

        $maxStorageMb = (float) ($tenant->limitFor('storage') ?? $tenant->max_storage_mb ?? 0);
        $remainingMb  = max(0, $maxStorageMb - $usedMb);

        $maxUploadKb = (int) floor($remainingMb * 1024);

        if ($maxUploadKb <= 0) {
            return back()->withErrors([
                'version_file' => 'Limite de armazenamento atingido para o seu plano.',
            ]);
        }

        $data = $request->validate([
            'project_id'   => 'required|exists:projects,id',
            'subfolder_id' => 'nullable|integer|exists:sub_folders,id',
            'title'        => 'required|min:3',
            'description'  => 'required|min:3',
            'version_file' => 'required|file|mimes:zip|max:' . $maxUploadKb,
        ]);

        $project = Project::where('tenant_id', $tenant->id)
            ->where('id', $data['project_id'])
            ->firstOrFail();

        $subfolder = null;
        if (!empty($data['subfolder_id'])) {
            $subfolder = SubFolder::where('tenant_id', $tenant->id)
                ->where('project_id', $project->id)
                ->where('id', $data['subfolder_id'])
                ->firstOrFail();
        }

        if (!$subfolder) {
            $subfolder = $this->ensureProjectSubfolders($project, (int) $tenant->id)->first();
            if (!$subfolder) {
                $subfolder = $this->createDefaultSubfolder($project, (int) $tenant->id);
            }
        }

        $subUser = Auth::user();
        $isMember = $subUser && $subUser->role !== 'owner';
        if ($isMember && $subUser->role === 'teacher') {
            $labOwnerId = Lab::where('id', $project->lab_id)->value('creator_subuser_id');
            if ((int) $labOwnerId !== (int) $subUser->id) {
                abort(403);
            }
        }
        if ($isMember && in_array($subUser->role, ['assistant', 'assitant'], true)) {
            return back()->withErrors([
                'version_file' => 'Assistente nao pode enviar novas versoes.',
            ]);
        }

        if ($isMember && $subUser->role === 'student') {
            if (!in_array((string) $project->status, ['approved', 'in_progress'], true)) {
                return back()->withErrors([
                    'version_file' => 'Seu status de projeto nao permite novas versoes.',
                ]);
            }

            $latestVersion = ProjectVersion::where('tenant_id', $tenant->id)
                ->where('project_id', $project->id)
                ->where('subfolder_id', $subfolder->id)
                ->orderBy('version_number', 'desc')
                ->first();

            if ($latestVersion && $latestVersion->status_version === 'submitted') {
                return back()->withErrors([
                    'version_file' => 'Aguarde a avaliacao da ultima versao antes de enviar uma nova.',
                ]);
            }
        }

        $latestNumber = ProjectVersion::where('tenant_id', $tenant->id)
            ->where('project_id', $project->id)
            ->where('subfolder_id', $subfolder->id)
            ->max('version_number');

        $nextVersion = $latestNumber ? $latestNumber + 1 : 1;

        $user = Auth::guard('web')->user();
        $isOwner = $user && $user->role === 'owner';
        $isSubUser = !is_null($subUser);

        $statusVersion = $isOwner ? 'approved' : 'submitted';
        $submittedAt   = Carbon::now();
        $approvedAt    = $isOwner ? Carbon::now() : null;
        $approvedBy    = $isOwner ? $user->id : null;
        $submittedByUserId = $isSubUser ? $tenant->creator_id : ($user?->id ?? $tenant->creator_id);

        $storedPath = null;

        DB::beginTransaction();
        try {
            $version = ProjectVersion::create([
                'tenant_id'      => $tenant->id,
                'lab_id'         => $project->lab_id,
                'group_id'       => $project->group_id,
                'project_id'     => $project->id,
                'subfolder_id'   => $subfolder->id,
                'version_number' => $nextVersion,
                'title'          => $data['title'],
                'description'    => $data['description'],
                'status_version' => $statusVersion,
                'submitted_by'   => $submittedByUserId,
                'submitted_at'   => $submittedAt,
                'approved_by'    => $approvedBy,
                'approved_at'    => $approvedAt,
            ]);

            $file = $request->file('version_file');

            $storedName = uniqid('version_', true) . '.' . $file->getClientOriginalExtension();
            $path = "project-versions/{$tenant->id}/{$project->id}/{$subfolder->id}";
            $storedPath = $file->storeAs($path, $storedName, 'private');

            $sizeBytes = (int) $file->getSize();

            Tenant::where('id', $tenant->id)->update([
                'storage_used_mb' => DB::raw('COALESCE(storage_used_mb, 0) + ' . $sizeBytes),
            ]);

            ProjectFile::create([
                'tenant_id'           => $tenant->id,
                'lab_id'              => $project->lab_id,
                'group_id'            => $project->group_id,
                'project_versions_id' => $version->id,
                'uploaded_by'         => $submittedByUserId,
                'original_name'       => $file->getClientOriginalName(),
                'stored_name'         => $storedName,
                'path'                => $storedPath,
                'extension'           => $file->getClientOriginalExtension(),
                'mime_type'           => $file->getClientMimeType(),
                'size'                => $sizeBytes,
                'type'                => 'compressed',
            ]);

            $subfolder->current_version = $nextVersion;
            $subfolder->save();

            $project->current_version = (int) (
                ProjectVersion::where('tenant_id', $tenant->id)
                    ->where('project_id', $project->id)
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

        if ($isSubUser) {
            $labName = Lab::where('id', $project->lab_id)->value('name') ?? 'Laboratorio';
            $groupName = Group::where('id', $project->group_id)->value('name') ?? 'Grupo';
            $message = "Nova versao submetida: {$project->title} - {$subfolder->name} - Versao {$nextVersion} (Lab: {$labName} / Grupo: {$groupName}).";
            $this->notifyOwnerAndTeachers($tenant, $message, $project->lab_id, $project->group_id);
        }

        $actor = ActivityService::resolveActor();
        if (!empty($actor['tenant_id'])) {
            ActivityService::log(
                (int) $actor['tenant_id'],
                (int) $actor['actor_id'],
                (string) $actor['actor_role'],
                'version_create',
                'project_version',
                (int) $version->id,
                'Nova versão enviada: ' . $project->title . ' (Versão ' . $nextVersion . ').'
            );
        }
        return back()->with('success', 'Versao adicionada.');
    }

    /**
     * Retorna o status atual de upload em partes para retomada.
     */
    public function chunkUploadStatus(Request $request)
    {
        $tenant = $this->resolveTenantFromAuth();
        $user = Auth::user();
        abort_if(!$user, 403);

        $data = $request->validate([
            'upload_id' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{16,120}$/'],
            'total_chunks' => 'required|integer|min:1|max:' . self::VERSION_CHUNK_MAX_TOTAL,
        ]);

        $uploadId = (string) $data['upload_id'];
        $manifest = $this->readVersionChunkManifest((int) $tenant->id, $uploadId);

        if (!$manifest) {
            return response()->json([
                'success' => true,
                'uploaded_chunks' => [],
                'uploaded_count' => 0,
            ]);
        }

        if ((int) ($manifest['tenant_id'] ?? 0) !== (int) $tenant->id
            || (int) ($manifest['user_id'] ?? 0) !== (int) $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Upload nao pertence ao usuario atual.',
            ], 403);
        }

        if ((int) ($manifest['total_chunks'] ?? 0) !== (int) $data['total_chunks']) {
            return response()->json([
                'success' => false,
                'message' => 'Total de partes divergente do upload em andamento.',
            ], 409);
        }

        $uploadedChunks = $this->listVersionUploadedChunks((int) $tenant->id, $uploadId);

        return response()->json([
            'success' => true,
            'uploaded_chunks' => $uploadedChunks,
            'uploaded_count' => count($uploadedChunks),
        ]);
    }

    /**
     * Recebe e persiste uma parte do arquivo ZIP da versao.
     */
    public function chunkUpload(Request $request)
    {
        $tenant = $this->resolveTenantFromAuth();
        $user = Auth::user();
        abort_if(!$user, 403);

        $data = $request->validate([
            'upload_id' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{16,120}$/'],
            'chunk_index' => 'required|integer|min:0',
            'total_chunks' => 'required|integer|min:1|max:' . self::VERSION_CHUNK_MAX_TOTAL,
            'file_name' => 'required|string|max:255',
            'file_size' => 'required|integer|min:1',
            'file_mime' => 'nullable|string|max:120',
            'chunk' => 'required|file|max:' . self::VERSION_CHUNK_MAX_KB,
        ]);

        $chunkIndex = (int) $data['chunk_index'];
        $totalChunks = (int) $data['total_chunks'];
        if ($chunkIndex >= $totalChunks) {
            return response()->json([
                'success' => false,
                'message' => 'Indice da parte invalido.',
            ], 422);
        }

        $uploadId = (string) $data['upload_id'];
        $tenantId = (int) $tenant->id;
        $userId = (int) $user->id;

        $manifest = $this->readVersionChunkManifest($tenantId, $uploadId);

        if ($manifest) {
            $sameOwner = (int) ($manifest['tenant_id'] ?? 0) === $tenantId
                && (int) ($manifest['user_id'] ?? 0) === $userId;
            if (!$sameOwner) {
                return response()->json([
                    'success' => false,
                    'message' => 'Upload nao pertence ao usuario atual.',
                ], 403);
            }

            $sameShape = (int) ($manifest['total_chunks'] ?? 0) === $totalChunks
                && (int) ($manifest['file_size'] ?? 0) === (int) $data['file_size'];
            if (!$sameShape) {
                return response()->json([
                    'success' => false,
                    'message' => 'Metadados do upload divergentes.',
                ], 409);
            }
        } else {
            $manifest = [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'upload_id' => $uploadId,
                'file_name' => (string) $data['file_name'],
                'file_size' => (int) $data['file_size'],
                'file_mime' => (string) ($data['file_mime'] ?? ''),
                'total_chunks' => $totalChunks,
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ];
        }

        $disk = Storage::disk('local');
        $dir = $this->getVersionChunkDirectory($tenantId, $uploadId);
        $disk->makeDirectory($dir);

        $chunkFileName = 'chunk_' . $chunkIndex . '.part';
        $chunkPath = $dir . '/' . $chunkFileName;
        $disk->putFileAs($dir, $request->file('chunk'), $chunkFileName);

        $manifest['updated_at'] = now()->toDateTimeString();
        $this->writeVersionChunkManifest($tenantId, $uploadId, $manifest);

        $uploadedChunks = $this->listVersionUploadedChunks($tenantId, $uploadId);

        return response()->json([
            'success' => true,
            'chunk_index' => $chunkIndex,
            'uploaded_count' => count($uploadedChunks),
            'total_chunks' => $totalChunks,
            'chunk_path' => $chunkPath,
        ]);
    }

    /**
     * Finaliza o upload em partes, monta o ZIP e persiste a versao.
     */
    public function completeChunkUpload(Request $request)
    {
        $tenant = $this->resolveTenantFromAuth();
        $user = Auth::user();
        abort_if(!$user, 403);

        $data = $request->validate([
            'upload_id' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{16,120}$/'],
            'project_id' => 'required|integer|exists:projects,id',
            'subfolder_id' => 'nullable|integer|exists:sub_folders,id',
            'title' => 'required|string|min:3|max:255',
            'description' => 'required|string|min:3',
            'total_chunks' => 'required|integer|min:1|max:' . self::VERSION_CHUNK_MAX_TOTAL,
            'file_name' => 'required|string|max:255',
            'file_size' => 'required|integer|min:1',
            'file_mime' => 'nullable|string|max:120',
        ]);

        $tenantId = (int) $tenant->id;
        $uploadId = (string) $data['upload_id'];

        $manifest = $this->readVersionChunkManifest($tenantId, $uploadId);
        if (!$manifest) {
            return response()->json([
                'success' => false,
                'message' => 'Upload em partes nao encontrado. Reenvie o arquivo.',
            ], 422);
        }

        $sameOwner = (int) ($manifest['tenant_id'] ?? 0) === $tenantId
            && (int) ($manifest['user_id'] ?? 0) === (int) $user->id;
        if (!$sameOwner) {
            return response()->json([
                'success' => false,
                'message' => 'Upload nao pertence ao usuario atual.',
            ], 403);
        }

        $sameShape = (int) ($manifest['total_chunks'] ?? 0) === (int) $data['total_chunks']
            && (int) ($manifest['file_size'] ?? 0) === (int) $data['file_size'];
        if (!$sameShape) {
            return response()->json([
                'success' => false,
                'message' => 'Metadados do upload divergentes.',
            ], 409);
        }

        $uploadedChunks = $this->listVersionUploadedChunks($tenantId, $uploadId);
        $expectedChunks = (int) $data['total_chunks'];
        if (count($uploadedChunks) !== $expectedChunks) {
            return response()->json([
                'success' => false,
                'message' => 'Upload incompleto. Continue o envio das partes faltantes.',
                'uploaded_count' => count($uploadedChunks),
                'total_chunks' => $expectedChunks,
            ], 422);
        }

        $disk = Storage::disk('local');
        $dir = $this->getVersionChunkDirectory($tenantId, $uploadId);
        $assembledRelativePath = $dir . '/assembled_upload.zip';
        if ($disk->exists($assembledRelativePath)) {
            $disk->delete($assembledRelativePath);
        }

        $assembledAbsolutePath = $disk->path($assembledRelativePath);
        $output = fopen($assembledAbsolutePath, 'wb');
        if ($output === false) {
            return response()->json([
                'success' => false,
                'message' => 'Falha ao montar arquivo final para envio.',
            ], 500);
        }

        try {
            for ($i = 0; $i < $expectedChunks; $i++) {
                $chunkRelative = $dir . '/chunk_' . $i . '.part';
                if (!$disk->exists($chunkRelative)) {
                    fclose($output);

                    return response()->json([
                        'success' => false,
                        'message' => 'Upload incompleto. Parte ausente: ' . $i . '.',
                    ], 422);
                }

                $input = fopen($disk->path($chunkRelative), 'rb');
                if ($input === false) {
                    fclose($output);

                    return response()->json([
                        'success' => false,
                        'message' => 'Falha ao ler parte enviada.',
                    ], 500);
                }

                stream_copy_to_stream($input, $output);
                fclose($input);
            }
        } finally {
            fclose($output);
        }

        $uploadedFile = new UploadedFile(
            $assembledAbsolutePath,
            (string) $data['file_name'],
            (string) ($data['file_mime'] ?: 'application/zip'),
            null,
            true
        );

        $request->files->set('version_file', $uploadedFile);
        $request->merge([
            'project_id' => (int) $data['project_id'],
            'subfolder_id' => isset($data['subfolder_id']) ? (int) $data['subfolder_id'] : null,
            'title' => (string) $data['title'],
            'description' => (string) $data['description'],
        ]);

        $result = app(ProjectVersionService::class)->storeVersion($request, $tenant);
        if (!empty($result['error'])) {
            $error = $result['error'];
            $message = (string) ($error['message'] ?? 'Nao foi possivel concluir o envio da versao.');

            return response()->json([
                'success' => false,
                'message' => $message,
            ], 422);
        }

        /** @var \App\Models\Project $project */
        $project = $result['project'];
        /** @var \App\Models\SubFolder $subfolder */
        $subfolder = $result['subfolder'];
        /** @var \App\Models\ProjectVersion $version */
        $version = $result['version'];
        $nextVersion = (int) ($result['nextVersion'] ?? 0);
        $isSubUser = (bool) ($result['isSubUser'] ?? false);

        $this->notifyAndLogVersionCreated($tenant, $project, $subfolder, $version, $nextVersion, $isSubUser);
        $this->cleanupVersionChunkUpload($tenantId, $uploadId);

        return response()->json([
            'success' => true,
            'message' => 'Versao adicionada.',
            'redirect_to' => url()->previous(),
        ]);
    }

    /**
     * Executa a rotina 'storeComment' no fluxo de negocio.
     */
    public function storeComment(Request $request, ProjectVersion $version)
    {
        $tenant = $this->resolveTenantFromAuth();

        if ((int) $version->tenant_id !== (int) $tenant->id) {
            abort(403);
        }

        $user = Auth::guard('web')->user();
        $subUser = Auth::user();
        $isMember = $subUser && $subUser->role !== 'owner';
        $isOwner = $user && $user->role === 'owner';
        $isTeacher = $subUser && $subUser->role === 'teacher';

        if (!$isOwner && !$isTeacher) {
            abort(403);
        }

        $rules = [
            'comment' => 'required|min:3',
        ];

        if ($isOwner) {
            $rules['status_version'] = ['nullable', Rule::in(['draft', 'submitted', 'approved', 'rejected'])];
        }

        $validated = $request->validateWithBag('comment_' . $version->id, $rules);

        $creatorId = $user?->id ?? $tenant->creator_id;
        $creatorSubId = $subUser?->id;
        $project = Project::where('tenant_id', $tenant->id)
            ->where('id', $version->project_id)
            ->first();
        $subfolderId = (int) ($version->subfolder_id ?? 0);

        $comment = ProjectComment::create([
            'tenant_id' => $tenant->id,
            'project_version_id' => $version->id,
            'creator_id' => $creatorId,
            'creator_subuser_id' => $creatorSubId,
            'description' => $request->input('comment'),
        ]);

        $actor = ActivityService::resolveActor();
        if (!empty($actor['tenant_id'])) {
            ActivityService::log(
                (int) $actor['tenant_id'],
                (int) $actor['actor_id'],
                (string) $actor['actor_role'],
                'comment_create',
                'project_comment',
                (int) $comment->id,
                'Comentário na versão ' . $version->version_number . ' do projeto ' . ($project?->title ?? 'N/A') . '.'
            );
        }

        if ($isOwner && !empty($validated['status_version'])) {
            $newStatus = $validated['status_version'];
            if ($newStatus !== $version->status_version) {
                $updates = [
                    'status_version' => $newStatus,
                ];

                if ($newStatus === 'approved') {
                    $updates['approved_at'] = Carbon::now();
                    $updates['approved_by'] = $isOwner ? $user->id : ($subUser?->id ?? null);
                    if (!$version->submitted_at) {
                        $updates['submitted_at'] = Carbon::now();
                    }
                } else {
                    $updates['approved_at'] = null;
                    $updates['approved_by'] = null;

                    if ($newStatus === 'submitted' && !$version->submitted_at) {
                        $updates['submitted_at'] = Carbon::now();
                    }

                    if ($newStatus === 'draft') {
                        $updates['submitted_at'] = null;
                    }
                }

                $version->update($updates);
                if (in_array($newStatus, ['approved', 'rejected'], true)) {
                    $statusLabel = $newStatus === 'approved' ? 'aprovada' : 'reprovada';
                    $commentSnippet = Str::limit((string) $request->input('comment'), 120);
                    $message = 'Revisão da versão ' . $version->version_number . ' do projeto ' . ($project?->title ?? 'N/A') . ' foi ' . $statusLabel . '. Comentário: ' . $commentSnippet;

                    $students = UserRelation::where('tenant_id', $tenant->id)
                        ->where('group_id', $version->group_id)
                        ->where('status', 'active')
                        ->where('role', 'student')
                        ->distinct()
                        ->pluck('user_id');

                    ActivityService::notifyUsers($students, $message, 'alert');
                }

                $actor = ActivityService::resolveActor();
                if (!empty($actor['tenant_id'])) {
                    ActivityService::log(
                        (int) $actor['tenant_id'],
                        (int) $actor['actor_id'],
                        (string) $actor['actor_role'],
                        'version_status_update',
                        'project_version',
                        (int) $version->id,
                        'Status da versão alterado para ' . $newStatus . ' (Projeto: ' . ($project?->title ?? 'N/A') . ').'
                    );
                }
            }
        }

        return back()->with('success', 'Comentario adicionado.');
    }


/**
 * Executa a rotina 'destroyVersion' no fluxo de negocio.
 */
    public function destroyVersion(ProjectVersion $version)
    {
        $user = Auth::guard('web')->user();
        $isOwner = $user && $user->role === 'owner';

        if (!$isOwner) {
            abort(403);
        }

        $tenant = $this->resolveTenantFromAuth();

        if ((int) $version->tenant_id !== (int) $tenant->id) {
            abort(403);
        }

        app(ProjectVersionService::class)->destroyVersion($tenant, $version);
        $actor = ActivityService::resolveActor();
        if (!empty($actor['tenant_id'])) {
            ActivityService::log(
                (int) $actor['tenant_id'],
                (int) $actor['actor_id'],
                (string) $actor['actor_role'],
                'version_delete',
                'project_version',
                (int) $version->id,
                'Versao excluida: ' . $version->title . '.'
            );
        }

        return back()->with('success', 'Versao excluida.');

        $project = Project::where('tenant_id', $tenant->id)
            ->where('id', $version->project_id)
            ->first();

        $files = ProjectFile::where('tenant_id', $tenant->id)
            ->where('project_versions_id', $version->id)
            ->get();

        $totalSize = (int) $files->sum('size');
        $subfolderId = (int) ($version->subfolder_id ?? 0);
        $versionState = app(ProjectVersionStateService::class);

        DB::beginTransaction();
        try {
            foreach ($files as $file) {
                if (!empty($file->path) && Storage::disk('private')->exists($file->path)) {
                    Storage::disk('private')->delete($file->path);
                }
            }

            ProjectFile::where('tenant_id', $tenant->id)
                ->where('project_versions_id', $version->id)
                ->delete();

            ProjectComment::where('tenant_id', $tenant->id)
                ->where('project_version_id', $version->id)
                ->delete();

            $version->delete();
            $versionState->decrementTenantStorage($tenant, $totalSize);

            if ($project) {
                $versionState->refreshSubfolderCurrentVersion((int) $tenant->id, (int) $project->id, $subfolderId);
                $versionState->refreshProjectVersionSummary((int) $tenant->id, (int) $project->id);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        $actor = ActivityService::resolveActor();
        if (!empty($actor['tenant_id'])) {
            ActivityService::log(
                (int) $actor['tenant_id'],
                (int) $actor['actor_id'],
                (string) $actor['actor_role'],
                'version_delete',
                'project_version',
                (int) $version->id,
                'Versão excluí­da: ' . $version->title . '.'
            );
        }

        return back()->with('success', 'Versão excluí­da.');
    }

    /**
     * Executa a rotina 'updateVersion' no fluxo de negocio.
     */
    public function updateVersion(Request $request, ProjectVersion $version)
    {
        $user = Auth::guard('web')->user();
        if (!$user || $user->role !== 'owner') {
            abort(403);
        }

        $tenant = $this->resolveTenantFromAuth();

        if ((int) $version->tenant_id !== (int) $tenant->id) {
            abort(403);
        }

        $result = app(ProjectVersionService::class)->updateVersion($request, $tenant, $version);
        if (!empty($result['error'])) {
            $error = $result['error'];
            $field = (string) ($error['field'] ?? 'version_file');
            $message = (string) ($error['message'] ?? 'Nao foi possivel atualizar a versao.');

            return back()->withErrors([$field => $message]);
        }

        $actor = ActivityService::resolveActor();
        if (!empty($actor['tenant_id'])) {
            ActivityService::log(
                (int) $actor['tenant_id'],
                (int) $actor['actor_id'],
                (string) $actor['actor_role'],
                'version_update',
                'project_version',
                (int) $version->id,
                'Versao atualizada: ' . $version->title . '.'
            );
        }

        return back()->with('success', 'Versao atualizada.');

        $existingFile = ProjectFile::where('tenant_id', $tenant->id)
            ->where('project_versions_id', $version->id)
            ->first();

        $existingBytes = (int) ($existingFile->size ?? 0);
        $usedBytes = (int) ($tenant->storage_used_mb ?? 0);
        $usedMb = $usedBytes / 1048576;
        $maxStorageMb = (float) ($tenant->limitFor('storage') ?? $tenant->max_storage_mb ?? 0);
        $remainingMb = max(0, $maxStorageMb - $usedMb);
        $maxUploadKb = (int) floor(($remainingMb + ($existingBytes / 1048576)) * 1024);

        if ($request->hasFile('version_file') && $maxUploadKb <= 0) {
            return back()->withErrors([
                'version_file' => 'Limite de armazenamento atingido para o seu plano.',
            ]);
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
                        'tenant_id' => $tenant->id,
                        'lab_id' => $version->lab_id,
                        'group_id' => $version->group_id,
                        'project_versions_id' => $version->id,
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
                    Tenant::where('id', $tenant->id)->update([
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

        $actor = ActivityService::resolveActor();
        if (!empty($actor['tenant_id'])) {
            ActivityService::log(
                (int) $actor['tenant_id'],
                (int) $actor['actor_id'],
                (string) $actor['actor_role'],
                'version_update',
                'project_version',
                (int) $version->id,
                'Versão atualizada: ' . $version->title . '.'
            );
        }
        return back()->with('success', 'Versão atualizada.');
    }

    /**
     * Padroniza notificacao e log apos criacao de versao.
     */
    private function notifyAndLogVersionCreated(
        Tenant $tenant,
        Project $project,
        SubFolder $subfolder,
        ProjectVersion $version,
        int $nextVersion,
        bool $isSubUser
    ): void {
        if ($isSubUser) {
            $labName = Lab::where('id', (int) $project->lab_id)->value('name') ?? 'Laboratorio';
            $groupName = Group::where('id', (int) $project->group_id)->value('name') ?? 'Grupo';
            $message = "Nova versao submetida: {$project->title} - {$subfolder->name} - Versao {$nextVersion} (Lab: {$labName} / Grupo: {$groupName}).";
            $this->notifyOwnerAndTeachers($tenant, $message, (int) $project->lab_id, (int) $project->group_id);
        }

        $actor = ActivityService::resolveActor();
        if (!empty($actor['tenant_id'])) {
            ActivityService::log(
                (int) $actor['tenant_id'],
                (int) $actor['actor_id'],
                (string) $actor['actor_role'],
                'version_create',
                'project_version',
                (int) $version->id,
                'Nova versao enviada: ' . $project->title . ' (Versao ' . $nextVersion . ').'
            );
        }
    }

    /**
     * Retorna o caminho base da sessao de upload em partes.
     */
    private function getVersionChunkDirectory(int $tenantId, string $uploadId): string
    {
        return 'chunk-uploads/' . $tenantId . '/' . $uploadId;
    }

    /**
     * Retorna o caminho do manifesto de upload em partes.
     */
    private function getVersionChunkManifestPath(int $tenantId, string $uploadId): string
    {
        return $this->getVersionChunkDirectory($tenantId, $uploadId) . '/manifest.json';
    }

    /**
     * Carrega metadados do upload em partes.
     */
    private function readVersionChunkManifest(int $tenantId, string $uploadId): ?array
    {
        $disk = Storage::disk('local');
        $manifestPath = $this->getVersionChunkManifestPath($tenantId, $uploadId);
        if (!$disk->exists($manifestPath)) {
            return null;
        }

        $raw = $disk->get($manifestPath);
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * Persiste metadados do upload em partes.
     */
    private function writeVersionChunkManifest(int $tenantId, string $uploadId, array $manifest): void
    {
        $disk = Storage::disk('local');
        $manifestPath = $this->getVersionChunkManifestPath($tenantId, $uploadId);
        $disk->put($manifestPath, json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Lista as partes ja recebidas para retomada do upload.
     */
    private function listVersionUploadedChunks(int $tenantId, string $uploadId): array
    {
        $disk = Storage::disk('local');
        $dir = $this->getVersionChunkDirectory($tenantId, $uploadId);
        if (!$disk->exists($dir)) {
            return [];
        }

        $indexes = [];
        $files = $disk->files($dir);
        foreach ($files as $file) {
            $name = basename($file);
            if (preg_match('/^chunk_(\d+)\.part$/', $name, $matches)) {
                $indexes[] = (int) $matches[1];
            }
        }

        sort($indexes, SORT_NUMERIC);
        return $indexes;
    }

    /**
     * Remove dados temporarios do upload em partes.
     */
    private function cleanupVersionChunkUpload(int $tenantId, string $uploadId): void
    {
        $disk = Storage::disk('local');
        $dir = $this->getVersionChunkDirectory($tenantId, $uploadId);
        if ($disk->exists($dir)) {
            $disk->deleteDirectory($dir);
        }
    }

    /**
     * Executa a rotina 'notifyOwnerAndTeachers' no fluxo de negocio.
     */
    private function notifyOwnerAndTeachers(Tenant $tenant, string $message, ?int $labId = null, ?int $groupId = null): void
    {
        ActivityService::notifyOwnerAndStaff($tenant, $message, $labId, $groupId, true);
    }

    /**
     * Aplica alteracoes em um registro existente.
     */
    public function update(Request $request){
        $data = $request->validate([
            'project_id' => 'required|integer',
            'status' => 'nullable|in:draft,in_progress,approved,rejected,archived',
            'title' => 'nullable|min:3',
            'description' => 'nullable|min:3',
            'lab_name' => 'nullable|min:3',
            'group_name' => 'nullable|min:3',
        ]);

        $hasUpdates = !empty($data['status'])
            || !empty($data['title'])
            || !empty($data['description'])
            || !empty($data['lab_name'])
            || !empty($data['group_name']);

        if (!$hasUpdates) {
            return response()->json([
                'message' => 'Nada para atualizar.',
            ], 422);
        }

        $subUser = Auth::user();
        if (!$subUser) {
            abort(401);
        }

        $tenant = $this->resolveTenantForProjectAccess($subUser);
        $tenantContext = app(TenantContextService::class);
        $role = $tenantContext->resolveRoleInTenant($subUser, $tenant);

        $isMember = $role !== 'owner';
        $isTeacher = $isMember && $role === 'teacher';
        $isStudent = $isMember && $role === 'student';

        if ($isMember && !$isTeacher && !$isStudent) {
            abort(403);
        }

        $project = Project::where('tenant_id', $tenant->id)
            ->where('id', $data['project_id'])
            ->firstOrFail();
        $oldStatus = $project->status;
        $oldTitle = $project->title;

        if ($isMember) {
            $isAssignedLab = UserRelation::where('user_id', (int) $subUser->id)
                ->where('tenant_id', (int) $tenant->id)
                ->where('lab_id', (int) $project->lab_id)
                ->where('status', 'active')
                ->exists();

            $isAssignedGroup = UserRelation::where('user_id', (int) $subUser->id)
                ->where('tenant_id', (int) $tenant->id)
                ->where('group_id', (int) $project->group_id)
                ->where('status', 'active')
                ->exists();

            if ($isTeacher && !$isAssignedLab && !$isAssignedGroup) {
                abort(403);
            }

            if ($isStudent && !$isAssignedGroup) {
                abort(403);
            }

            // Subusuarios nao podem renomear laboratorio/grupo por este formulario.
            if (!empty($data['lab_name']) || !empty($data['group_name'])) {
                unset($data['lab_name'], $data['group_name']);
            }

            // Estudantes podem editar nome/descricao, mas nao o status do projeto.
            if ($isStudent && !empty($data['status'])) {
                return response()->json([
                    'message' => 'Estudante pode alterar apenas nome e descricao do projeto.',
                ], 403);
            }
        }

        if (!empty($data['title'])) {
            $project->title = $data['title'];
            $project->slug = Str::replace(' ', '-', $data['title']);
        }

        if (!empty($data['description'])) {
            $project->description = $data['description'];
        }

        if (!empty($data['status'])) {
            $project->status = $data['status'];
        }

        $project->save();

        if (!empty($data['lab_name']) && $project->lab) {
            $project->lab->name = $data['lab_name'];
            $project->lab->code = Str::replace(' ', '-', $data['lab_name']);
            $project->lab->save();
        }

        if (!empty($data['group_name']) && $project->group) {
            $project->group->name = $data['group_name'];
            $project->group->code = Str::replace(' ', '-', $data['group_name']);
            $project->group->save();
        }

        if ($oldStatus !== $project->status) {
            $statusLabels = [
                'draft' => 'Rascunho',
                'in_progress' => 'Em andamento',
                'approved' => 'Aprovado',
                'rejected' => 'Rejeitado',
                'archived' => 'Arquivado',
            ];
            $oldStatusLabel = $statusLabels[$oldStatus] ?? $oldStatus;
            $newStatusLabel = $statusLabels[$project->status] ?? $project->status;
            $actorName = (string) ($subUser->name ?? 'Membro da equipe');

            $message = $actorName . ' alterou o status do projeto "' . $project->title
                . '" de ' . $oldStatusLabel . ' para ' . $newStatusLabel . '.';

            $groupMemberIds = UserRelation::where('tenant_id', (int) $tenant->id)
                ->where('group_id', (int) $project->group_id)
                ->where('status', 'active')
                ->where('user_id', '!=', (int) $subUser->id)
                ->distinct()
                ->pluck('user_id');

            ActivityService::notifyUsers($groupMemberIds, $message, 'alert');
        }

        $actor = ActivityService::resolveActor();
        if (!empty($actor['tenant_id'])) {
            if ($oldStatus !== $project->status) {
                ActivityService::log(
                    (int) $actor['tenant_id'],
                    (int) $actor['actor_id'],
                    (string) $actor['actor_role'],
                    'project_status_update',
                    'project',
                    (int) $project->id,
                    'Status do projeto atualizado para ' . $project->status . ' (Projeto: ' . $project->title . ').'
                );
            }

            ActivityService::log(
                (int) $actor['tenant_id'],
                (int) $actor['actor_id'],
                (string) $actor['actor_role'],
                'project_update',
                'project',
                (int) $project->id,
                'Projeto atualizado: ' . $project->title . '.'
            );
        }
        return response()->json([
            'success' => true,
            'status' => $project->status,
            'title' => $project->title,
            'description' => $project->description,
            'lab_name' => $project->lab?->name,
            'group_name' => $project->group?->name,
        ]);
    }

    /**
     * Retorna tasks do projeto com versao vinculada quando existir.
     */
    private function getProjectTasks(int $tenantId, int $projectId)
    {
        return Task::with('version')
            ->where('tenant_id', $tenantId)
            ->where('project_id', $projectId)
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
