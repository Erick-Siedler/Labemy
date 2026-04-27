<?php

namespace App\Http\Controllers;

use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use App\Models\Group;
use App\Models\Project;
use App\Models\ProjectVersion;
use App\Models\ProjectComment;
use App\Models\ProjectFile;
use App\Models\Tenant;
use App\Models\Lab;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserRelation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use App\Services\HomeOwnerDataService;
use App\Services\ActivityService;
use App\Services\UserUiPreferencesService;

class GroupController extends Controller
{
    /**
     * Lista e prepara os dados exibidos na tela.
     */
    public function index($group, HomeOwnerDataService $homeOwnerData)
    {
        $subUser = Auth::user();
        $isMember = $subUser && $subUser->role !== 'owner';
        if ($isMember) {
            return $this->indexSubuser($group, $subUser);
        }

        $user = Auth::user();
        if ((string) ($user->plan ?? '') === 'solo') {
            return redirect()->route('home-solo');
        }
        $tenant = Tenant::where('creator_id', $user->id)->firstOrFail();

        $groupData = Group::with('lab')
            ->where('tenant_id', $tenant->id)
            ->where('id', $group)
            ->firstOrFail();

        $groupProjects = Project::where('tenant_id', $tenant->id)
            ->where('group_id', $groupData->id)
            ->get();
        $groupProjectMetrics = $this->buildGroupProjectMetrics($tenant->id, $groupData->id, $groupProjects);
        $latestVersionsByProject = $groupProjectMetrics['latestVersionsByProject'];
        $latestVersions = $latestVersionsByProject->values();
        $groupCharts = $this->buildGroupChartsByPeriod($groupProjects, $latestVersionsByProject);

        $data = $homeOwnerData->build($user);
        $data['group'] = $groupData;
        $data['lab'] = $groupData->lab;
        $data['groupProjects'] = $groupProjects;
        $data['latestVersions'] = $latestVersionsByProject;
        $data['projectVersionCountMap'] = $groupProjectMetrics['projectVersionCountMap'];
        $data['projectCommentCountMap'] = $groupProjectMetrics['projectCommentCountMap'];
        $data['projectStorageMbMap'] = $groupProjectMetrics['projectStorageMbMap'];
        $data['projectStatusChartByPeriod'] = $groupCharts['projectStatusByPeriod'];
        $data['projectStatusChart'] = $groupCharts['projectStatusByPeriod']['3'] ?? [];
        $data['versionStatusChartByPeriod'] = $groupCharts['versionStatusByPeriod'];
        $data['versionStatusChart'] = $groupCharts['versionStatusByPeriod']['3'] ?? [];
        $data['pageTitle'] = $groupData->name;
        $data['pageBreadcrumbHome'] = 'Início';
        $data['pageBreadcrumbCurrent'] = 'Grupo';

        $theme = $this->getTheme($data['userPreferences']);
        return view('main.home.labs-groups-projects.index-group', $data, [
            'theme' => $theme
        ]);
    }

    /**
     * Executa a rotina 'indexSubuser' no fluxo de negocio.
     */
    private function indexSubuser($group, $subUser)
    {
        $role = $subUser->role;
        $isAssistant = in_array($role, ['assistant', 'assitant'], true);
        $isTeacher = $role === 'teacher';

        if (!$isAssistant && !$isTeacher) {
            abort(403);
        }

        $tenantId = $subUser->tenant_id ?: Lab::where('id', $subUser->lab_id)->value('tenant_id');
        $tenant = Tenant::where('id', $tenantId)->firstOrFail();

        $groupData = Group::with('lab')
            ->where('tenant_id', $tenant->id)
            ->where('id', $group)
            ->firstOrFail();

        $isTeacherOwner = (int) ($groupData->lab?->creator_subuser_id ?? 0) === (int) $subUser->id;
        $isAssignedLab = !empty($subUser->lab_id) && (int) $groupData->lab_id === (int) $subUser->lab_id;

        if ($isTeacher) {
            if (!$isTeacherOwner && !$isAssignedLab) {
                abort(403);
            }
        } elseif ($subUser->lab_id && (int) $groupData->lab_id !== (int) $subUser->lab_id) {
            abort(403);
        }

        $groupProjects = Project::where('tenant_id', $tenant->id)
            ->where('group_id', $groupData->id)
            ->get();
        $groupProjectMetrics = $this->buildGroupProjectMetrics($tenant->id, $groupData->id, $groupProjects);
        $latestVersionsByProject = $groupProjectMetrics['latestVersionsByProject'];
        $latestVersions = $latestVersionsByProject->values();
        $groupCharts = $this->buildGroupChartsByPeriod($groupProjects, $latestVersionsByProject);

        $groups = Group::with('projects.subfolders')
            ->where('lab_id', $groupData->lab_id)
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
            'tenantLimits' => [
                'projects' => $tenant->limitFor('projects'),
            ],
            'students' => UserRelation::with(['user', 'lab', 'group'])
                ->where('tenant_id', $tenant->id)
                ->where('group_id', $groupData->id)
                ->where('status', 'active')
                ->whereIn('role', ['teacher', 'assistant', 'asssitant', 'assitant', 'student'])
                ->get()
                ->map(function (UserRelation $relation) {
                    $member = $relation->user;
                    if (!$member) {
                        return null;
                    }

                    $member->setAttribute('tenant_id', (int) $relation->tenant_id);
                    $member->setAttribute('lab_id', $relation->lab_id);
                    $member->setAttribute('group_id', $relation->group_id);
                    $member->setAttribute('role', (string) $relation->role);
                    $member->setRelation('lab', $relation->lab);
                    $member->setRelation('group', $relation->group);

                    return $member;
                })
                ->filter()
                ->values(),
            'labs' => $teacherLabs,
            'groups' => $groups,
            'group' => $groupData,
            'lab' => $groupData->lab,
            'groupProjects' => $groupProjects,
            'latestVersions' => $latestVersionsByProject,
            'projectVersionCountMap' => $groupProjectMetrics['projectVersionCountMap'],
            'projectCommentCountMap' => $groupProjectMetrics['projectCommentCountMap'],
            'projectStorageMbMap' => $groupProjectMetrics['projectStorageMbMap'],
            'projectStatusChartByPeriod' => $groupCharts['projectStatusByPeriod'],
            'projectStatusChart' => $groupCharts['projectStatusByPeriod']['3'] ?? [],
            'versionStatusChartByPeriod' => $groupCharts['versionStatusByPeriod'],
            'versionStatusChart' => $groupCharts['versionStatusByPeriod']['3'] ?? [],
            'notifications' => Notification::where('user_id', $subUser->id)
                ->where('table', 'users')
                ->orderBy('created_at', 'desc')
                ->get(),
            'userPreferences' => User::where('id', $subUser->id)->value('preferences'),
            'pageTitle' => $groupData->name,
            'pageBreadcrumbHome' => 'Inicio',
            'pageBreadcrumbCurrent' => 'Grupo',
            'layout' => 'layouts.header-side-not-sub',
            'canManageMembers' => $isTeacherOwner,
            'canCreateLab' => $isTeacher,
            'canCreateGroup' => $isTeacher,
            'canCreateProject' => $isTeacher && ($isTeacherOwner || $isAssignedLab),
            'canCreateSubfolder' => $isTeacher,
            'canEditLabStatus' => $isTeacherOwner,
            'canEditGroupStatus' => $isTeacherOwner,
            'canEditProjectStatus' => $isTeacherOwner,
        ];

        $theme = $this->getTheme($data['userPreferences']);

        return view('main.home.labs-groups-projects.index-group', $data, [
            'theme' => $theme,
            'user' => $subUser,
        ]);
    }

    /**
     * Executa a rotina 'getTheme' no fluxo de negocio.
     */
    private function getTheme($userPreferences)
    {
        return app(UserUiPreferencesService::class)->resolveTheme($userPreferences);
    }

    /**
     * Monta dados dos graficos por periodo (3, 6 e 12 meses).
     */
    private function buildGroupChartsByPeriod($groupProjects, $latestVersionsByProject): array
    {
        $periods = [3, 6, 12];
        $now = now()->startOfMonth();

        $projectRows = collect($groupProjects)->map(function ($project) {
            return [
                'status' => (string) ($project->status ?? ''),
                'created_at' => $this->normalizeChartDate($project->created_at),
            ];
        });

        $versionRows = collect($latestVersionsByProject)->map(function ($version) {
            $referenceDate = $version->submitted_at ?: $version->created_at;
            return [
                'status' => (string) ($version->status_version ?? ''),
                'reference_date' => $this->normalizeChartDate($referenceDate),
            ];
        });

        $projectStatusByPeriod = [];
        $versionStatusByPeriod = [];

        foreach ($periods as $period) {
            $startDate = $now->copy()->subMonths($period - 1)->startOfDay();

            $periodProjects = $projectRows->filter(function ($project) use ($startDate) {
                return $project['created_at'] && $project['created_at']->greaterThanOrEqualTo($startDate);
            });

            $periodVersions = $versionRows->filter(function ($version) use ($startDate) {
                return $version['reference_date'] && $version['reference_date']->greaterThanOrEqualTo($startDate);
            });

            $projectStatusByPeriod[(string) $period] = [
                ['label' => 'Rascunho', 'value' => $periodProjects->where('status', 'draft')->count(), 'color' => '#90a4ae'],
                ['label' => 'Em andamento', 'value' => $periodProjects->where('status', 'in_progress')->count(), 'color' => '#ffb74d'],
                ['label' => 'Aprovado', 'value' => $periodProjects->where('status', 'approved')->count(), 'color' => '#4caf50'],
                ['label' => 'Rejeitado', 'value' => $periodProjects->where('status', 'rejected')->count(), 'color' => '#f44336'],
                ['label' => 'Arquivado', 'value' => $periodProjects->where('status', 'archived')->count(), 'color' => '#757575'],
            ];

            $versionStatusByPeriod[(string) $period] = [
                ['label' => 'Rascunho', 'value' => $periodVersions->where('status', 'draft')->count(), 'color' => '#90a4ae'],
                ['label' => 'Submetido', 'value' => $periodVersions->where('status', 'submitted')->count(), 'color' => '#ffb74d'],
                ['label' => 'Aprovado', 'value' => $periodVersions->where('status', 'approved')->count(), 'color' => '#4caf50'],
                ['label' => 'Rejeitado', 'value' => $periodVersions->where('status', 'rejected')->count(), 'color' => '#f44336'],
            ];
        }

        return [
            'projectStatusByPeriod' => $projectStatusByPeriod,
            'versionStatusByPeriod' => $versionStatusByPeriod,
        ];
    }

    /**
     * Normaliza valores de data para Carbon sem quebrar quando vierem como string.
     */
    private function normalizeChartDate($value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value->copy();
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $exception) {
            return null;
        }
    }

    /**
     * Agrega metricas de versoes, armazenamento e comentarios dos projetos do grupo.
     */
    private function buildGroupProjectMetrics(int $tenantId, int $groupId, $groupProjects): array
    {
        $projectIds = $groupProjects->pluck('id')->filter()->values();

        if ($projectIds->isEmpty()) {
            return [
                'latestVersionsByProject' => collect(),
                'projectVersionCountMap' => [],
                'projectCommentCountMap' => [],
                'projectStorageMbMap' => [],
            ];
        }

        $versions = ProjectVersion::where('tenant_id', $tenantId)
            ->where('group_id', $groupId)
            ->whereIn('project_id', $projectIds)
            ->orderBy('version_number', 'desc')
            ->get(['id', 'project_id', 'version_number', 'status_version', 'submitted_at', 'approved_at', 'created_at']);

        $latestVersionsByProject = $versions
            ->groupBy('project_id')
            ->map
            ->first()
            ->keyBy('project_id');

        $versionsByProject = $versions
            ->groupBy('project_id')
            ->map(fn ($items) => $items->pluck('id'));

        $projectVersionCountMap = $versionsByProject
            ->map(fn ($versionIds) => $versionIds->count());

        $allVersionIds = $versions->pluck('id');
        $projectCommentCountMap = collect();
        $projectStorageMbMap = collect();

        if ($allVersionIds->isNotEmpty()) {
            $commentsByVersion = ProjectComment::whereIn('project_version_id', $allVersionIds)
                ->selectRaw('project_version_id, COUNT(*) as total')
                ->groupBy('project_version_id')
                ->pluck('total', 'project_version_id');

            $bytesByVersion = ProjectFile::whereIn('project_versions_id', $allVersionIds)
                ->selectRaw('project_versions_id, SUM(size) as total')
                ->groupBy('project_versions_id')
                ->pluck('total', 'project_versions_id');

            $projectCommentCountMap = $versionsByProject->map(function ($versionIds) use ($commentsByVersion) {
                return (int) $versionIds->sum(fn ($versionId) => (int) ($commentsByVersion[$versionId] ?? 0));
            });

            $projectStorageMbMap = $versionsByProject->map(function ($versionIds) use ($bytesByVersion) {
                $totalBytes = (int) $versionIds->sum(fn ($versionId) => (int) ($bytesByVersion[$versionId] ?? 0));
                return round($totalBytes / 1048576, 2);
            });
        }

        return [
            'latestVersionsByProject' => $latestVersionsByProject,
            'projectVersionCountMap' => $projectVersionCountMap->all(),
            'projectCommentCountMap' => $projectCommentCountMap->all(),
            'projectStorageMbMap' => $projectStorageMbMap->all(),
        ];
    }

    /**
     * Valida os dados recebidos e persiste um novo registro.
     */
    function store(Request $request){
        $subUser = Auth::user();
        if (!$subUser) {
            abort(401);
        }

        $activeTenantId = (int) session('active_tenant_id', 0);
        if ($activeTenantId > 0) {
            $tenant = Tenant::where('id', $activeTenantId)->firstOrFail();
        } else {
            $tenant = Tenant::where('creator_id', Auth::id())->first();
            if (!$tenant) {
                $relatedTenantId = UserRelation::where('user_id', (int) $subUser->id)
                    ->where('status', 'active')
                    ->value('tenant_id');

                if (!$relatedTenantId) {
                    abort(403);
                }

                $tenant = Tenant::where('id', (int) $relatedTenantId)->firstOrFail();
            }
        }

        $isTenantCreator = (int) $tenant->creator_id === (int) $subUser->id;
        $actorRole = $isTenantCreator
            ? 'owner'
            : (string) UserRelation::where('tenant_id', (int) $tenant->id)
                ->where('user_id', (int) $subUser->id)
                ->where('status', 'active')
                ->orderByRaw("FIELD(role, 'owner', 'teacher', 'assistant', 'student')")
                ->value('role');

        if (!$isTenantCreator && $actorRole === '') {
            abort(403);
        }

        $isMember = $actorRole !== 'owner';
        if ($isMember && $actorRole !== 'teacher') {
            abort(403);
        }

        if ($tenant->hasReachedLimit('groups', Group::where('tenant_id', $tenant->id)->count())) {
            return response()->json([
                'message' => 'Limite de grupos atingido para o seu plano.',
            ], 422);
        }

        $data = $request->validate([
            'name' => 'required|min:3',
            'lab_id' => [
                'required',
                'integer',
                Rule::exists('labs', 'id')->where('tenant_id', $tenant->id),
            ],
        ]);

        $tenant_id = $tenant->id;
        $lab = Lab::where('tenant_id', $tenant_id)
            ->where('id', $data['lab_id'])
            ->firstOrFail();

        if ($isMember) {
            $isTeacherOwner = (int) ($lab->creator_subuser_id ?? 0) === (int) $subUser->id;
            $isAssignedLab = !empty($subUser->lab_id) && (int) $lab->id === (int) $subUser->lab_id;

            if (!$isTeacherOwner && !$isAssignedLab) {
                abort(403);
            }
        }

        $creatorId = $subUser ? $tenant->creator_id : Auth::id();

        $group = Group::create([
            'tenant_id' => $tenant_id,
            'lab_id' => $lab->id,
            'creator_id' => $creatorId,
            'name' => $data['name'],
            'code' => Str::replace(' ', '-', $data['name']),
            'status' => 'active'
        ]);

        $actor = ActivityService::resolveActor();
        if (!empty($actor['tenant_id'])) {
            ActivityService::log(
                (int) $actor['tenant_id'],
                (int) $actor['actor_id'],
                (string) $actor['actor_role'],
                'group_create',
                'group',
                (int) $group->id,
                'Grupo criado: ' . $group->name . ' (Lab: ' . ($lab->name ?? 'N/A') . ').'
            );
        }

        return response()->json([
            'success' => true   
        ], 201);
    }

    /**
     * Executa a rotina 'updateMemberRole' no fluxo de negocio.
     */
    public function updateMemberRole(Request $request)
    {
        $data = $request->validate([
            'member_id' => 'required|integer',
            'group_id' => 'nullable|integer',
            'lab_id' => 'nullable|integer',
            'role' => 'required|in:teacher,assistant,student',
        ]);

        $groupId = (int) ($data['group_id'] ?? 0);
        $labId = (int) ($data['lab_id'] ?? 0);
        if ($groupId <= 0 && $labId <= 0) {
            abort(422, 'Informe o grupo ou laboratorio do membro.');
        }

        $subUser = Auth::user();
        if (!$subUser) {
            abort(401);
        }

        $activeTenantId = (int) session('active_tenant_id', 0);
        if ($activeTenantId > 0) {
            $tenant = Tenant::where('id', $activeTenantId)->firstOrFail();
        } else {
            $tenant = Tenant::where('creator_id', Auth::id())->first();
            if (!$tenant) {
                $relatedTenantId = UserRelation::where('user_id', (int) $subUser->id)
                    ->where('status', 'active')
                    ->value('tenant_id');

                if (!$relatedTenantId) {
                    abort(403);
                }

                $tenant = Tenant::where('id', (int) $relatedTenantId)->firstOrFail();
            }
        }

        $isTenantCreator = (int) $tenant->creator_id === (int) $subUser->id;
        $actorRole = $isTenantCreator
            ? 'owner'
            : (string) UserRelation::where('tenant_id', (int) $tenant->id)
                ->where('user_id', (int) $subUser->id)
                ->where('status', 'active')
                ->orderByRaw("FIELD(role, 'owner', 'teacher', 'assistant', 'student')")
                ->value('role');

        if (!$isTenantCreator && $actorRole === '') {
            abort(403);
        }

        $group = null;
        $lab = null;

        if ($groupId > 0) {
            $group = Group::with('lab')
                ->where('tenant_id', $tenant->id)
                ->where('id', $groupId)
                ->firstOrFail();

            $lab = $group->lab;

            if ($actorRole === 'teacher' && (int) ($group->lab?->creator_subuser_id ?? 0) !== (int) $subUser->id) {
                abort(403);
            }
        } else {
            $lab = Lab::where('tenant_id', $tenant->id)
                ->where('id', $labId)
                ->firstOrFail();

            if ($actorRole === 'teacher' && (int) ($lab->creator_subuser_id ?? 0) !== (int) $subUser->id) {
                abort(403);
            }
        }

        $memberRelationQuery = UserRelation::where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->where('user_id', $data['member_id']);

        if ($group) {
            $memberRelation = (clone $memberRelationQuery)
                ->where('group_id', $group->id)
                ->first();

            if (!$memberRelation && $lab) {
                $memberRelation = (clone $memberRelationQuery)
                    ->where('lab_id', $lab->id)
                    ->orderByRaw("FIELD(role, 'owner', 'teacher', 'assistant', 'student')")
                    ->first();
            }
        } else {
            $memberRelation = (clone $memberRelationQuery)
                ->where('lab_id', $lab->id)
                ->orderByRaw("FIELD(role, 'owner', 'teacher', 'assistant', 'student')")
                ->first();
        }

        if (!$memberRelation) {
            abort(404);
        }

        if ($actorRole === 'teacher' && (string) $memberRelation->role === 'teacher') {
            $message = 'Professor nao pode alterar papel de professor.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 403);
            }
            abort(403, $message);
        }

        $member = User::where('id', $memberRelation->user_id)->firstOrFail();
        $oldRole = (string) $memberRelation->role;
        $newRole = in_array((string) $data['role'], ['asssitant', 'assitant'], true)
            ? 'assistant'
            : (string) $data['role'];

        $resolvedGroupId = null;
        if ($newRole === 'student') {
            if ($group) {
                $resolvedGroupId = (int) $group->id;
            } else {
                $resolvedGroupId = $groupId > 0
                    ? $groupId
                    : (int) ($memberRelation->group_id ?? 0);
            }

            if ($resolvedGroupId <= 0) {
                $message = 'Aluno precisa estar vinculado a um grupo.';
                if ($request->expectsJson()) {
                    return response()->json(['message' => $message], 422);
                }
                return back()->withErrors(['role' => $message]);
            }

            $validGroup = Group::where('tenant_id', (int) $tenant->id)
                ->where('id', $resolvedGroupId)
                ->when($lab, fn ($query) => $query->where('lab_id', (int) $lab->id))
                ->exists();

            if (!$validGroup) {
                $message = 'Grupo informado e invalido para este laboratorio.';
                if ($request->expectsJson()) {
                    return response()->json(['message' => $message], 422);
                }
                return back()->withErrors(['group_id' => $message]);
            }
        }

        $memberRelation->role = $newRole;
        $memberRelation->group_id = $newRole === 'student' ? $resolvedGroupId : null;
        $memberRelation->save();

        $newRoleLabel = ActivityService::formatRole((string) $memberRelation->role);
        $oldRoleLabel = ActivityService::formatRole($oldRole);
        $contextLabel = $group
            ? ('grupo ' . $group->name)
            : ('laboratorio ' . ($lab->name ?? 'N/A'));
        $memberMessage = 'Seu papel foi atualizado de ' . $oldRoleLabel . ' para ' . $newRoleLabel . ' no ' . $contextLabel . '.';
        ActivityService::notifyUser((int) $member->id, $memberMessage, 'alert');
        ActivityService::notifyUser((int) $tenant->creator_id, 'Papel de ' . $member->name . ' atualizado para ' . $newRoleLabel . ' no ' . $contextLabel . '.', 'alert');

        $actor = ActivityService::resolveActor();
        if (!empty($actor['tenant_id'])) {
            ActivityService::log(
                (int) $actor['tenant_id'],
                (int) $actor['actor_id'],
                (string) $actor['actor_role'],
                'role_update',
                'user_relation',
                (int) $member->id,
                'Papel atualizado de ' . $oldRoleLabel . ' para ' . $newRoleLabel . ' (' . ucfirst($contextLabel) . ').'
            );
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'member_id' => $member->id,
                'role' => $memberRelation->role,
            ]);
        }

        return back()->with('success', 'Função atualizada.');
    }

    /**
     * Revoga o vinculo do membro com o tenant atual.
     */
    public function revokeMemberRelation(Request $request)
    {
        $data = $request->validate([
            'member_id' => 'required|integer',
            'group_id' => 'nullable|integer',
            'lab_id' => 'nullable|integer',
        ]);

        $groupId = (int) ($data['group_id'] ?? 0);
        $labId = (int) ($data['lab_id'] ?? 0);
        if ($groupId <= 0 && $labId <= 0) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Informe o grupo ou laboratorio do membro.',
                ], 422);
            }

            return back()->withErrors([
                'member_id' => 'Informe o grupo ou laboratorio do membro.',
            ]);
        }

        $authUser = Auth::user();
        $isMember = $authUser && $authUser->role !== 'owner';

        if ($isMember) {
            if ($authUser->role !== 'teacher') {
                abort(403);
            }

            $tenantId = $authUser->tenant_id ?: Lab::where('id', $authUser->lab_id)->value('tenant_id');
            $tenant = Tenant::where('id', $tenantId)->firstOrFail();
        } else {
            $activeTenantId = (int) session('active_tenant_id', 0);
            $tenantQuery = Tenant::where('creator_id', Auth::id());
            if ($activeTenantId > 0) {
                $tenantQuery->where('id', $activeTenantId);
            }
            $tenant = $tenantQuery->firstOrFail();
        }

        $group = null;
        $lab = null;

        if ($groupId > 0) {
            $group = Group::with('lab')
                ->where('tenant_id', $tenant->id)
                ->where('id', $groupId)
                ->firstOrFail();

            $lab = $group->lab;

            if ($isMember && (int) ($group->lab?->creator_subuser_id ?? 0) !== (int) $authUser->id) {
                abort(403);
            }
        } else {
            $lab = Lab::where('tenant_id', $tenant->id)
                ->where('id', $labId)
                ->firstOrFail();

            if ($isMember && (int) ($lab->creator_subuser_id ?? 0) !== (int) $authUser->id) {
                abort(403);
            }
        }

        $memberId = (int) $data['member_id'];
        if ($memberId <= 0) {
            abort(422);
        }

        if ($memberId === (int) $authUser->id) {
            $message = 'Nao e permitido remover sua propria relacao.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->withErrors(['member_id' => $message]);
        }

        if ((int) $tenant->creator_id === $memberId) {
            $message = 'Nao e permitido remover o owner do tenant.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->withErrors(['member_id' => $message]);
        }

        $memberRelationQuery = UserRelation::where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->where('user_id', $memberId);

        if ($group) {
            $memberRelation = (clone $memberRelationQuery)
                ->where('group_id', $group->id)
                ->first();

            if (!$memberRelation && $lab) {
                $memberRelation = (clone $memberRelationQuery)
                    ->where('lab_id', $lab->id)
                    ->first();
            }
        } else {
            $memberRelation = (clone $memberRelationQuery)
                ->where('lab_id', $lab->id)
                ->first();
        }

        if (!$memberRelation) {
            abort(404);
        }

        $member = User::where('id', $memberId)->first();
        $memberLabel = $member?->name ?: ('usuario #' . $memberId);

        $affected = UserRelation::where('tenant_id', $tenant->id)
            ->where('user_id', $memberId)
            ->delete();

        if ($affected <= 0) {
            $message = 'Nao foi possivel remover a relacao.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->withErrors(['member_id' => $message]);
        }

        ActivityService::notifyUser($memberId, 'Seu acesso ao tenant foi removido.', 'alert');

        $actor = ActivityService::resolveActor();
        if (!empty($actor['tenant_id'])) {
            ActivityService::log(
                (int) $actor['tenant_id'],
                (int) $actor['actor_id'],
                (string) $actor['actor_role'],
                'relation_revoke',
                'user_relation',
                $memberId,
                'Relacao removida do membro ' . $memberLabel . '.'
            );
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'member_id' => $memberId,
            ]);
        }

        return back()->with('success', 'Relacao removida com sucesso.');
    }

    /**
     * Aplica alteracoes em um registro existente.
     */
    public function update(Request $request)
    {
        $data = $request->validate([
            'group_id' => 'required|integer',
            'status' => 'nullable|in:active,inactive,archived',
            'name' => 'nullable|string|min:3|max:120',
        ]);

        $hasStatusUpdate = isset($data['status']) && $data['status'] !== '';
        $hasNameUpdate = isset($data['name']) && trim((string) $data['name']) !== '';
        if (!$hasStatusUpdate && !$hasNameUpdate) {
            return response()->json([
                'message' => 'Nada para atualizar.',
            ], 422);
        }

        $subUser = Auth::user();
        $isMember = $subUser && $subUser->role !== 'owner';
        if ($isMember) {
            if ($subUser->role !== 'teacher') {
                abort(403);
            }
            $tenantId = $subUser->tenant_id ?: Lab::where('id', $subUser->lab_id)->value('tenant_id');
            $tenant = Tenant::where('id', $tenantId)->firstOrFail();
        } else {
            $tenant = Tenant::where('creator_id', Auth::id())->firstOrFail();
        }

        $group = Group::with('lab')
            ->where('tenant_id', $tenant->id)
            ->where('id', $data['group_id'])
            ->firstOrFail();

        if ($isMember && (int) ($group->lab?->creator_subuser_id ?? 0) !== (int) $subUser->id) {
            abort(403);
        }

        $oldStatus = $group->status;
        $oldName = $group->name;

        if ($hasStatusUpdate) {
            $group->status = $data['status'];
        }

        if ($hasNameUpdate) {
            $newName = trim((string) $data['name']);
            $group->name = $newName;
            $group->code = Str::replace(' ', '-', $newName);
        }

        $group->save();

        $actor = ActivityService::resolveActor();
        if (!empty($actor['tenant_id'])) {
            $changes = [];
            if ($oldName !== $group->name) {
                $changes[] = 'Nome do grupo atualizado para ' . $group->name . '.';
            }
            if ($oldStatus !== $group->status) {
                $changes[] = 'Status do grupo atualizado para ' . $group->status . '.';
            }

            ActivityService::log(
                (int) $actor['tenant_id'],
                (int) $actor['actor_id'],
                (string) $actor['actor_role'],
                'group_update',
                'group',
                (int) $group->id,
                !empty($changes)
                    ? implode(' ', $changes)
                    : 'Grupo atualizado: ' . $group->name . '.'
            );
        }

        return response()->json([
            'success' => true,
            'status' => $group->status,
            'name' => $group->name,
        ]);
    }
}
