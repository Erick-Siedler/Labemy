<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Group;
use App\Models\Project;
use App\Models\ProjectVersion;
use App\Models\Tenant;
use App\Models\Lab;
use App\Models\SubUsers;
use App\Models\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use App\Services\HomeOwnerDataService;
use App\Services\ActivityService;

class GroupController extends Controller
{
    public function index($group, HomeOwnerDataService $homeOwnerData)
    {
        $subUser = Auth::guard('subusers')->user();
        if ($subUser) {
            return $this->indexSubuser($group, $subUser);
        }

        $user = Auth::user();
        $tenant = Tenant::where('creator_id', $user->id)->firstOrFail();

        $groupData = Group::with('lab')
            ->where('tenant_id', $tenant->id)
            ->where('id', $group)
            ->firstOrFail();

        $groupProjects = Project::where('tenant_id', $tenant->id)
            ->where('group_id', $groupData->id)
            ->get();

        $latestVersions = ProjectVersion::where('tenant_id', $tenant->id)
            ->where('group_id', $groupData->id)
            ->orderBy('version_number', 'desc')
            ->get()
            ->groupBy('project_id')
            ->map->first();

        $latestVersionsByProject = $latestVersions->keyBy('project_id');

        $data = $homeOwnerData->build($user);
        $data['group'] = $groupData;
        $data['lab'] = $groupData->lab;
        $data['groupProjects'] = $groupProjects;
        $data['latestVersions'] = $latestVersionsByProject;
        $data['projectStatusChart'] = [
            ['label' => 'Rascunho', 'value' => $groupProjects->where('status', 'draft')->count(), 'color' => '#90a4ae'],
            ['label' => 'Em andamento', 'value' => $groupProjects->where('status', 'in_progress')->count(), 'color' => '#ffb74d'],
            ['label' => 'Aprovado', 'value' => $groupProjects->where('status', 'approved')->count(), 'color' => '#4caf50'],
            ['label' => 'Rejeitado', 'value' => $groupProjects->where('status', 'rejected')->count(), 'color' => '#f44336'],
            ['label' => 'Arquivado', 'value' => $groupProjects->where('status', 'archived')->count(), 'color' => '#757575'],
        ];
        $data['versionStatusChart'] = [
            ['label' => 'Rascunho', 'value' => $latestVersions->where('status_version', 'draft')->count(), 'color' => '#90a4ae'],
            ['label' => 'Submetido', 'value' => $latestVersions->where('status_version', 'submitted')->count(), 'color' => '#ffb74d'],
            ['label' => 'Aprovado', 'value' => $latestVersions->where('status_version', 'approved')->count(), 'color' => '#4caf50'],
            ['label' => 'Rejeitado', 'value' => $latestVersions->where('status_version', 'rejected')->count(), 'color' => '#f44336'],
        ];
        $data['pageTitle'] = $groupData->name;
        $data['pageBreadcrumbHome'] = 'Início';
        $data['pageBreadcrumbCurrent'] = 'Grupo';

        $theme = $this->getTheme($data['userPreferences']);
        return view('main.home.labs-groups-projects.index-group', $data, [
            'theme' => $theme
        ]);
    }

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

        $latestVersions = ProjectVersion::where('tenant_id', $tenant->id)
            ->where('group_id', $groupData->id)
            ->orderBy('version_number', 'desc')
            ->get()
            ->groupBy('project_id')
            ->map->first();

        $latestVersionsByProject = $latestVersions->keyBy('project_id');

        $groups = Group::with('projects')
            ->where('lab_id', $groupData->lab_id)
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
            'tenantLimits' => [
                'projects' => $tenant->limitFor('projects'),
            ],
            'students' => SubUsers::where('tenant_id', $tenant->id)
                ->where('group_id', $groupData->id)
                ->get(),
            'labs' => $teacherLabs,
            'groups' => $groups,
            'group' => $groupData,
            'lab' => $groupData->lab,
            'groupProjects' => $groupProjects,
            'latestVersions' => $latestVersionsByProject,
            'projectStatusChart' => [
                ['label' => 'Rascunho', 'value' => $groupProjects->where('status', 'draft')->count(), 'color' => '#90a4ae'],
                ['label' => 'Em andamento', 'value' => $groupProjects->where('status', 'in_progress')->count(), 'color' => '#ffb74d'],
                ['label' => 'Aprovado', 'value' => $groupProjects->where('status', 'approved')->count(), 'color' => '#4caf50'],
                ['label' => 'Rejeitado', 'value' => $groupProjects->where('status', 'rejected')->count(), 'color' => '#f44336'],
                ['label' => 'Arquivado', 'value' => $groupProjects->where('status', 'archived')->count(), 'color' => '#757575'],
            ],
            'versionStatusChart' => [
                ['label' => 'Rascunho', 'value' => $latestVersions->where('status_version', 'draft')->count(), 'color' => '#90a4ae'],
                ['label' => 'Submetido', 'value' => $latestVersions->where('status_version', 'submitted')->count(), 'color' => '#ffb74d'],
                ['label' => 'Aprovado', 'value' => $latestVersions->where('status_version', 'approved')->count(), 'color' => '#4caf50'],
                ['label' => 'Rejeitado', 'value' => $latestVersions->where('status_version', 'rejected')->count(), 'color' => '#f44336'],
            ],
            'notifications' => Notification::where('user_id', $subUser->id)
                ->where('table', 'subusers')
                ->orderBy('created_at', 'desc')
                ->get(),
            'userPreferences' => SubUsers::where('id', $subUser->id)->value('preferences'),
            'pageTitle' => $groupData->name,
            'pageBreadcrumbHome' => 'Inicio',
            'pageBreadcrumbCurrent' => 'Grupo',
            'layout' => 'layouts.header-side-not-sub',
            'canManageMembers' => $isTeacherOwner,
            'canCreateLab' => $isTeacher,
            'canCreateGroup' => $isTeacher,
            'canCreateProject' => false,
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

    private function getTheme($userPreferences){
        $preferences = json_encode($userPreferences, true);

        $preferences = explode('{', $preferences)[1];
        $preferences = explode('}', $preferences)[0];

        $theme = explode(',', $preferences)[0];
        $theme = explode(':', $theme)[1];

        return $theme;
    }

    function store(Request $request){
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

        if ($subUser) {
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

    public function updateMemberRole(Request $request)
    {
        $data = $request->validate([
            'member_id' => 'required|integer',
            'group_id' => 'required|integer',
            'role' => 'required|in:teacher,assistant,student',
        ]);

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

        $group = Group::with('lab')
            ->where('tenant_id', $tenant->id)
            ->where('id', $data['group_id'])
            ->firstOrFail();

        if ($subUser && (int) ($group->lab?->creator_subuser_id ?? 0) !== (int) $subUser->id) {
            abort(403);
        }

        $member = SubUsers::where('tenant_id', $tenant->id)
            ->where('group_id', $group->id)
            ->where('id', $data['member_id'])
            ->firstOrFail();
        $oldRole = $member->role;

        $member->role = $data['role'];
        $member->save();
        $newRoleLabel = ActivityService::formatRole($member->role);
        $oldRoleLabel = ActivityService::formatRole($oldRole);
        $memberMessage = 'Seu papel foi atualizado de ' . $oldRoleLabel . ' para ' . $newRoleLabel . ' no grupo ' . $group->name . '.';
        ActivityService::notifySubUser((int) $member->id, $memberMessage, 'alert');
        ActivityService::notifyUser((int) $tenant->creator_id, 'Papel de ' . $member->name . ' atualizado para ' . $newRoleLabel . ' no grupo ' . $group->name . '.', 'alert');

        $actor = ActivityService::resolveActor();
        if (!empty($actor['tenant_id'])) {
            ActivityService::log(
                (int) $actor['tenant_id'],
                (int) $actor['actor_id'],
                (string) $actor['actor_role'],
                'role_update',
                'subuser',
                (int) $member->id,
                'Papel atualizado de ' . $oldRoleLabel . ' para ' . $newRoleLabel . ' (Grupo: ' . $group->name . ').'
            );
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'member_id' => $member->id,
                'role' => $member->role,
            ]);
        }

        return back()->with('success', 'Função atualizada.');
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'group_id' => 'required|integer',
            'status' => 'required|in:active,inactive,archived',
        ]);

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

        $group = Group::with('lab')
            ->where('tenant_id', $tenant->id)
            ->where('id', $data['group_id'])
            ->firstOrFail();

        if ($subUser && (int) ($group->lab?->creator_subuser_id ?? 0) !== (int) $subUser->id) {
            abort(403);
        }

        $group->status = $data['status'];
        $group->save();
        $actor = ActivityService::resolveActor();
        if (!empty($actor['tenant_id'])) {
            ActivityService::log(
                (int) $actor['tenant_id'],
                (int) $actor['actor_id'],
                (string) $actor['actor_role'],
                'group_update',
                'group',
                (int) $group->id,
                'Status do grupo atualizado para ' . $group->status . ' (Grupo: ' . $group->name . ').'
            );
        }

        return response()->json([
            'success' => true,
            'status' => $group->status,
        ]);
    }
}
