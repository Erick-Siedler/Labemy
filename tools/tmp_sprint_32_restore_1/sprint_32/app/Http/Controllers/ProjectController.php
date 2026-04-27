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
use App\Models\SubUsers;
use App\Services\HomeOwnerDataService;
use Illuminate\Support\Facades\DB;
use App\Services\ActivityService;

class ProjectController extends Controller
{
    public function index($project, HomeOwnerDataService $homeOwnerData)
    {
        $subUser = Auth::guard('subusers')->user();
        if ($subUser) {
            return $this->indexSubuser($project, $subUser);
        }

        $user = Auth::user();
        $tenant = Tenant::where('creator_id', $user->id)->firstOrFail();

        $projectData = Project::with(['lab', 'group'])
            ->where('tenant_id', $tenant->id)
            ->where('id', $project)
            ->firstOrFail();

        $usedBytes = (int) ($tenant->storage_used_mb ?? 0);
        $usedMb = $usedBytes / 1048576;

        $maxStorageMb = (float) ($tenant->limitFor('storage') ?? 0);
        $remainingStorageMb = max(0, $maxStorageMb - $usedMb);

        $versions = ProjectVersion::where('tenant_id', $tenant->id)
            ->where('project_id', $projectData->id)
            ->orderBy('version_number', 'asc')
            ->get();

        $versionIds = $versions->pluck('id');
        $projectFiles = $versionIds->isEmpty()
            ? collect()
            : ProjectFile::where('tenant_id', $tenant->id)
                ->whereIn('project_versions_id', $versionIds)
                ->get();

        $versionComments = $versionIds->isEmpty()
            ? collect()
            : ProjectComment::with(['creator', 'subCreator'])
                ->where('tenant_id', $tenant->id)
                ->whereIn('project_version_id', $versionIds)
                ->orderBy('created_at', 'desc')
                ->get()
                ->groupBy('project_version_id');

        $projectStorageBytes = (int) $projectFiles->sum('size');
        $projectStorageMb = round($projectStorageBytes / 1048576, 2);
        $tenantStorageUsedMb = round($usedMb, 2);
        $tenantStorageMaxMb = (float) ($tenant->limitFor('storage') ?? $tenant->max_storage_mb ?? 0);
        $tenantStoragePercent = $tenantStorageMaxMb > 0
            ? min(100, round(($tenantStorageUsedMb / $tenantStorageMaxMb) * 100, 1))
            : 0;

        $storageTrend = [];
        $versionsTrend = [];
        $trendMonths = collect(range(0, 5))->map(function ($offset) {
            return Carbon::now()->subMonths(5 - $offset);
        });

        foreach ($trendMonths as $month) {
            $monthFiles = $projectFiles->filter(function ($file) use ($month) {
                return Carbon::parse($file->created_at)->isSameMonth($month);
            });

            $monthVersions = $versions->filter(function ($version) use ($month) {
                return Carbon::parse($version->created_at)->isSameMonth($month);
            });

            $storageTrend[] = [
                'label' => $month->locale('pt_BR')->translatedFormat('M'),
                'value' => round($monthFiles->sum('size') / 1048576, 2),
                'color' => '#ff8c00',
            ];

            $versionsTrend[] = [
                'label' => $month->locale('pt_BR')->translatedFormat('M'),
                'value' => $monthVersions->count(),
                'color' => '#3f51b5',
            ];
        }

        $latestVersion = $versions->first();

        $data = $homeOwnerData->build($user);
        $data['project'] = $projectData;
        $data['lab'] = $projectData->lab;
        $data['group'] = $projectData->group;
        $data['versions'] = $versions;
        $data['latestVersion'] = $latestVersion;
        $data['projectFilesCount'] = $projectFiles->count();
        $data['projectFiles'] = $projectFiles;
        $data['versionComments'] = $versionComments;
        $data['projectStorageMb'] = $projectStorageMb;
        $data['tenantStorageUsedMb'] = $tenantStorageUsedMb;
        $data['tenantStorageMaxMb'] = $tenantStorageMaxMb;
        $data['tenantStoragePercent'] = $tenantStoragePercent;
        $data['storageTrend'] = $storageTrend;
        $data['versionsTrend'] = $versionsTrend;
        $data['versionStats'] = [
            'draft' => $versions->where('status_version', 'draft')->count(),
            'submitted' => $versions->where('status_version', 'submitted')->count(),
            'approved' => $versions->where('status_version', 'approved')->count(),
            'rejected' => $versions->where('status_version', 'rejected')->count(),
        ];
        $data['canComment'] = ($user && $user->role === 'owner')
            || (Auth::guard('subusers')->check() && Auth::guard('subusers')->user()?->role === 'teacher');
        $data['canEditVersionStatus'] = $user && $user->role === 'owner';
        $data['canAddVersion'] = $user && $user->role === 'owner';
        $data['canEditVersion'] = $user && $user->role === 'owner';
        $data['statusOptions'] = [
            ['value' => 'approved', 'label' => 'Aprovado'],
            ['value' => 'rejected', 'label' => 'Rejeitado'],
            ['value' => 'submitted', 'label' => 'Enviado'],
            ['value' => 'draft', 'label' => 'Rascunho'],
        ];
        $data['maxUploadMb'] = $remainingStorageMb;
        $data['pageTitle'] = $projectData->title;
        $data['pageBreadcrumbHome'] = 'Início';
        $data['pageBreadcrumbCurrent'] = 'Projeto';

        $theme = $this->getTheme($data['userPreferences']);

        return view('main.home.labs-groups-projects.index-project', $data, [
            'theme' => $theme
        ]);
    }

    private function indexSubuser($project, $subUser)
    {
        if ($subUser->role === 'student') {
            abort(403);
        }

        $role = $subUser->role;
        $isAssistant = in_array($role, ['assistant', 'assitant'], true);
        $isTeacher = $role === 'teacher';

        $tenantId = $subUser->tenant_id ?: Lab::where('id', $subUser->lab_id)->value('tenant_id');
        $tenant = Tenant::where('id', $tenantId)->firstOrFail();

        $projectData = Project::with(['lab', 'group'])
            ->where('tenant_id', $tenant->id)
            ->where('id', $project)
            ->firstOrFail();

        $isTeacherOwner = (int) ($projectData->lab?->creator_subuser_id ?? 0) === (int) $subUser->id;
        $isAssignedLab = !empty($subUser->lab_id) && (int) $projectData->lab_id === (int) $subUser->lab_id;

        if ($isTeacher) {
            if (!$isTeacherOwner && !$isAssignedLab) {
                abort(403);
            }
        } elseif ($subUser->lab_id && (int) $projectData->lab_id !== (int) $subUser->lab_id) {
            abort(403);
        }

        $usedBytes = (int) ($tenant->storage_used_mb ?? 0);
        $usedMb = $usedBytes / 1048576;
        $maxStorageMb = (float) ($tenant->limitFor('storage') ?? 0);
        $remainingStorageMb = max(0, $maxStorageMb - $usedMb);

        $versions = ProjectVersion::where('tenant_id', $tenant->id)
            ->where('project_id', $projectData->id)
            ->orderBy('version_number', 'asc')
            ->get();

        $versionIds = $versions->pluck('id');
        $projectFiles = $versionIds->isEmpty()
            ? collect()
            : ProjectFile::where('tenant_id', $tenant->id)
                ->whereIn('project_versions_id', $versionIds)
                ->get();

        $versionComments = $versionIds->isEmpty()
            ? collect()
            : ProjectComment::with(['creator', 'subCreator'])
                ->where('tenant_id', $tenant->id)
                ->whereIn('project_version_id', $versionIds)
                ->orderBy('created_at', 'desc')
                ->get()
                ->groupBy('project_version_id');

        $projectStorageBytes = (int) $projectFiles->sum('size');
        $projectStorageMb = round($projectStorageBytes / 1048576, 2);
        $tenantStorageUsedMb = round($usedMb, 2);
        $tenantStorageMaxMb = (float) ($tenant->limitFor('storage') ?? $tenant->max_storage_mb ?? 0);
        $tenantStoragePercent = $tenantStorageMaxMb > 0
            ? min(100, round(($tenantStorageUsedMb / $tenantStorageMaxMb) * 100, 1))
            : 0;

        $storageTrend = [];
        $versionsTrend = [];
        $trendMonths = collect(range(0, 5))->map(function ($offset) {
            return Carbon::now()->subMonths(5 - $offset);
        });

        foreach ($trendMonths as $month) {
            $monthFiles = $projectFiles->filter(function ($file) use ($month) {
                return Carbon::parse($file->created_at)->isSameMonth($month);
            });

            $monthVersions = $versions->filter(function ($version) use ($month) {
                return Carbon::parse($version->created_at)->isSameMonth($month);
            });

            $storageTrend[] = [
                'label' => $month->locale('pt_BR')->translatedFormat('M'),
                'value' => round($monthFiles->sum('size') / 1048576, 2),
                'color' => '#ff8c00',
            ];

            $versionsTrend[] = [
                'label' => $month->locale('pt_BR')->translatedFormat('M'),
                'value' => $monthVersions->count(),
                'color' => '#3f51b5',
            ];
        }

        $latestVersion = $versions->first();

        $groups = Group::with('projects')
            ->where('lab_id', $projectData->lab_id)
            ->orderBy('name')
            ->get();

        $teacherLabs = $isTeacher
            ? Lab::with('groups.projects')
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
            'versions' => $versions,
            'latestVersion' => $latestVersion,
            'projectFilesCount' => $projectFiles->count(),
            'projectFiles' => $projectFiles,
            'versionComments' => $versionComments,
            'projectStorageMb' => $projectStorageMb,
            'tenantStorageUsedMb' => $tenantStorageUsedMb,
            'tenantStorageMaxMb' => $tenantStorageMaxMb,
            'tenantStoragePercent' => $tenantStoragePercent,
            'storageTrend' => $storageTrend,
            'versionsTrend' => $versionsTrend,
            'versionStats' => [
                'draft' => $versions->where('status_version', 'draft')->count(),
                'submitted' => $versions->where('status_version', 'submitted')->count(),
                'approved' => $versions->where('status_version', 'approved')->count(),
                'rejected' => $versions->where('status_version', 'rejected')->count(),
            ],
            'canComment' => $isAssistant || $isTeacher,
            'canEditVersionStatus' => $isAssistant,
            'canAddVersion' => $isTeacherOwner,
            'canEditVersion' => false,
            'statusOptions' => [
                ['value' => 'approved', 'label' => 'Aprovado'],
                ['value' => 'rejected', 'label' => 'Rejeitado'],
                ['value' => 'submitted', 'label' => 'Enviado'],
            ],
            'maxUploadMb' => $remainingStorageMb,
            'notifications' => Notification::where('user_id', $subUser->id)->where('table', 'subusers')->orderBy('created_at', 'desc')->get(),
            'userPreferences' => SubUsers::where('id', $subUser->id)->value('preferences'),
            'pageTitle' => $projectData->title,
            'pageBreadcrumbHome' => 'Inicio',
            'pageBreadcrumbCurrent' => 'Projeto',
            'layout' => 'layouts.header-side-not-sub',
            'canCreateLab' => $isTeacher,
            'canCreateGroup' => $isTeacher,
            'canCreateProject' => false,
            'canEditLabStatus' => $isTeacherOwner,
            'canEditGroupStatus' => $isTeacherOwner,
            'canEditProjectStatus' => $isTeacherOwner,
        ];

        $theme = $this->getTheme($data['userPreferences']);

        return view('main.home.labs-groups-projects.index-project', $data, [
            'theme' => $theme,
            'user' => $subUser,
        ]);
    }
    private function getTheme($userPreferences){
        $preferences = json_encode($userPreferences, true);

        $preferences = explode('{', $preferences)[1];
        $preferences = explode('}', $preferences)[0];

        $theme = explode(',', $preferences)[0];
        $theme = explode(':', $theme)[1];

        return $theme;
    }

    private function resolveTenantFromAuth()
    {
        $subUser = Auth::guard('subusers')->user();
        if ($subUser) {
            $tenantId = $subUser->tenant_id ?: Lab::where('id', $subUser->lab_id)->value('tenant_id');

            return Tenant::where('id', $tenantId)->firstOrFail();
        }

        return Tenant::where('creator_id', Auth::id())->firstOrFail();
    }

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

    function store(Request $request){
        $tenant = $this->resolveTenantFromAuth();
        $subUser = Auth::guard('subusers')->user();
        $isOwner = Auth::check() && Auth::user()->role === 'owner';
        $isPrivileged = $isOwner || ($subUser && $subUser->role === 'teacher');
        $isSubUser = !is_null($subUser);

        if ($subUser && $subUser->role !== 'student') {
            return response()->json([
                'message' => 'Somente alunos podem criar projetos.',
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

        $status = $isOwner ? 'approved' : 'submitted';
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

    
    public function storeVersion(Request $request)
    {
        $tenant = $this->resolveTenantFromAuth();

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
            'title'        => 'required|min:3',
            'description'  => 'required|min:3',
            'version_file' => 'required|file|mimes:zip|max:' . $maxUploadKb,
        ]);

        $project = Project::where('tenant_id', $tenant->id)
            ->where('id', $data['project_id'])
            ->firstOrFail();

        $subUser = Auth::guard('subusers')->user();
        if ($subUser && $subUser->role === 'teacher') {
            $labOwnerId = Lab::where('id', $project->lab_id)->value('creator_subuser_id');
            if ((int) $labOwnerId !== (int) $subUser->id) {
                abort(403);
            }
        }
        if ($subUser && in_array($subUser->role, ['assistant', 'assitant'], true)) {
            return back()->withErrors([
                'version_file' => 'Assistente nao pode enviar novas versoes.',
            ]);
        }

        if ($subUser && $subUser->role === 'student') {
            $latestVersion = ProjectVersion::where('tenant_id', $tenant->id)
                ->where('project_id', $project->id)
                ->orderBy('version_number', 'desc')
                ->first();

            if ($latestVersion && $latestVersion->status_version === 'submitted') {
                return back()->withErrors([
                    'version_file' => 'Aguarde a avaliacao da ultima versao antes de enviar uma nova.',
                ]);
            }

            if ($latestVersion && $latestVersion->status_version === 'rejected') {
                return back()->withErrors([
                    'version_file' => 'A ultima versao foi rejeitada. Nao e possivel enviar uma nova versao.',
                ]);
            }
        }

        $latestNumber = ProjectVersion::where('tenant_id', $tenant->id)
            ->where('project_id', $project->id)
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
            $path = "project-versions/{$tenant->id}/{$project->id}";
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

            $project->current_version = $nextVersion;
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
            $message = "Nova versao submetida: {$project->title} - Versao {$nextVersion} (Lab: {$labName} / Grupo: {$groupName}).";
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

    public function storeComment(Request $request, ProjectVersion $version)
    {
        $tenant = $this->resolveTenantFromAuth();

        if ((int) $version->tenant_id !== (int) $tenant->id) {
            abort(403);
        }

        $user = Auth::guard('web')->user();
        $subUser = Auth::guard('subusers')->user();
        $isOwner = $user && $user->role === 'owner';
        $isTeacher = $subUser && $subUser->role === 'teacher';
        $isAssistant = $subUser && in_array($subUser->role, ['assistant', 'assitant'], true);

        if (!$isOwner && !$isTeacher && !$isAssistant) {
            abort(403);
        }

        $rules = [
            'comment' => 'required|min:3',
        ];

        if ($isOwner || $isAssistant) {
            $allowedStatuses = $isOwner
                ? ['draft', 'submitted', 'approved', 'rejected']
                : ['submitted', 'approved', 'rejected'];
            $rules['status_version'] = ['nullable', Rule::in($allowedStatuses)];
        }

        $validated = $request->validateWithBag('comment_' . $version->id, $rules);

        $creatorId = $user?->id ?? $tenant->creator_id;
        $creatorSubId = $subUser?->id;
        $project = Project::where('tenant_id', $tenant->id)
            ->where('id', $version->project_id)
            ->first();

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

        if (($isOwner || $isAssistant) && !empty($validated['status_version'])) {
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

                    $students = SubUsers::where('tenant_id', $tenant->id)
                        ->where('group_id', $version->group_id)
                        ->where('role', 'student')
                        ->get(['id']);

                    ActivityService::notifySubUsers($students, $message, 'alert');
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


public function destroyVersion(ProjectVersion $version)
    {
        $user = Auth::guard('web')->user();
        if (!$user || $user->role !== 'owner') {
            abort(403);
        }

        $tenant = $this->resolveTenantFromAuth();

        if ((int) $version->tenant_id !== (int) $tenant->id) {
            abort(403);
        }

        $project = Project::where('tenant_id', $tenant->id)
            ->where('id', $version->project_id)
            ->first();

        $files = ProjectFile::where('tenant_id', $tenant->id)
            ->where('project_versions_id', $version->id)
            ->get();

        $totalSize = (int) $files->sum('size');

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

            if ($totalSize > 0) {
                $currentUsed = (int) ($tenant->storage_used_mb ?? 0);
                $newUsed = max(0, $currentUsed - $totalSize);
                Tenant::where('id', $tenant->id)->update([
                    'storage_used_mb' => $newUsed,
                ]);
            }

            if ($project) {
                $latestVersion = ProjectVersion::where('tenant_id', $tenant->id)
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
                'Versão excluída: ' . $version->title . '.'
            );
        }

        return back()->with('success', 'VersÃ£o excluÃ­da.');
    }

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
                $path = 'project-versions/' . $tenant->id . '/' . $version->project_id;
                $storedPath = $file->storeAs($path, $storedName, 'private');

                $sizeBytes = (int) $file->getSize();

                if ($existingFile && !empty($existingFile->path) && Storage::disk('private')->exists($existingFile->path)) {
                    Storage::disk('private')->delete($existingFile->path);
                }

                $user = Auth::guard('web')->user();
                $subUser = Auth::guard('subusers')->user();
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
        return back()->with('success', 'VersÃ£o atualizada.');
    }

    private function notifyOwnerAndTeachers(Tenant $tenant, string $message, ?int $labId = null, ?int $groupId = null): void
    {
        ActivityService::notifyOwnerAndStaff($tenant, $message, $labId, $groupId, true);
    }

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

        $subUser = Auth::guard('subusers')->user();
        if ($subUser) {
            if ($subUser->role !== 'teacher') {
                abort(403);
            }
            $tenantId = $subUser->tenant_id ?: Lab::where('id', $subUser->lab_id)->value('tenant_id');
            $tenant = Tenant::where('id', $tenantId)->firstOrFail();
        } else {
            $tenant = Tenant::where('creator_id', Auth::id())->firstOrFail();
        }

        $project = Project::where('tenant_id', $tenant->id)
            ->where('id', $data['project_id'])
            ->firstOrFail();
        $oldStatus = $project->status;
        $oldTitle = $project->title;

        if ($subUser) {
            $labOwnerId = Lab::where('tenant_id', $tenant->id)
                ->where('id', $project->lab_id)
                ->value('creator_subuser_id');

            if ((int) $labOwnerId !== (int) $subUser->id) {
                abort(403);
            }

            if (!empty($data['title']) || !empty($data['description']) || !empty($data['lab_name']) || !empty($data['group_name'])) {
                return response()->json([
                    'message' => 'Apenas o status pode ser alterado.',
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
}














