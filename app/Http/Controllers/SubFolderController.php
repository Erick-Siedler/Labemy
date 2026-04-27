<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Lab;
use App\Models\Notification;
use App\Models\Project;
use App\Models\ProjectComment;
use App\Models\ProjectFile;
use App\Models\ProjectVersion;
use App\Models\SubFolder;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRelation;
use App\Services\HomeOwnerDataService;
use App\Services\UserUiPreferencesService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SubFolderController extends Controller
{
    /**
     * Lista e prepara os dados exibidos na tela.
     */
    public function index($subfolder, HomeOwnerDataService $homeOwnerData)
    {
        $subUser = Auth::user();
        $isMember = $subUser && $subUser->role !== 'owner';
        if ($isMember) {
            return $this->indexSubuser($subfolder, $subUser);
        }

        $user = Auth::user();
        $tenant = Tenant::where('creator_id', $user->id)->firstOrFail();

        $subfolderData = SubFolder::with(['project', 'lab', 'group'])
            ->where('tenant_id', $tenant->id)
            ->where('id', $subfolder)
            ->firstOrFail();

        $flow = $this->buildSubfolderFlowData($tenant, $subfolderData);

        $isSoloOwner = (string) ($user->plan ?? '') === 'solo';
        $data = $isSoloOwner
            ? $homeOwnerData->buildSolo($user)
            : $homeOwnerData->build($user);
        $data['project'] = $subfolderData->project;
        $data['subfolder'] = $subfolderData;
        $data['lab'] = $subfolderData->lab;
        $data['group'] = $subfolderData->group;
        $data['versions'] = $flow['versions'];
        $data['latestVersion'] = $flow['latestVersion'];
        $data['projectFilesCount'] = $flow['projectFilesCount'];
        $data['projectFiles'] = $flow['projectFiles'];
        $data['versionComments'] = $flow['versionComments'];
        $data['projectStorageMb'] = $flow['projectStorageMb'];
        $data['tenantStorageUsedMb'] = $flow['tenantStorageUsedMb'];
        $data['tenantStorageMaxMb'] = $flow['tenantStorageMaxMb'];
        $data['tenantStoragePercent'] = $flow['tenantStoragePercent'];
        $data['storageTrend'] = $flow['storageTrend'];
        $data['versionsTrend'] = $flow['versionsTrend'];
        $data['storageTrendByPeriod'] = $flow['storageTrendByPeriod'];
        $data['versionsTrendByPeriod'] = $flow['versionsTrendByPeriod'];
        $data['versionStats'] = $flow['versionStats'];
        $data['versionFlowRecentLimit'] = 6;
        $data['canComment'] = true;
        $data['canEditVersionStatus'] = true;
        $data['canAddVersion'] = true;
        $data['canEditVersion'] = true;
        $data['canDeleteVersion'] = true;
        $data['statusOptions'] = [
            ['value' => 'approved', 'label' => 'Aprovado'],
            ['value' => 'rejected', 'label' => 'Rejeitado'],
            ['value' => 'submitted', 'label' => 'Enviado'],
            ['value' => 'draft', 'label' => 'Rascunho'],
        ];
        $data['maxUploadMb'] = $flow['maxUploadMb'];
        $data['pageTitle'] = $subfolderData->project?->title . ' / ' . $subfolderData->name;
        $data['pageBreadcrumbHome'] = 'Inicio';
        $data['pageBreadcrumbCurrent'] = 'Subfolder';

        $theme = $this->getTheme($data['userPreferences']);

        return view('main.home.labs-groups-projects.index-subfolder', $data, [
            'theme' => $theme,
        ]);
    }

    /**
     * Executa a rotina 'indexSubuser' no fluxo de negocio.
     */
    private function indexSubuser($subfolder, $subUser)
    {
        $role = $subUser->role;
        $isAssistant = in_array($role, ['assistant', 'assitant'], true);
        $isTeacher = $role === 'teacher';
        $isStudent = $role === 'student';

        $tenantId = $subUser->tenant_id ?: Lab::where('id', $subUser->lab_id)->value('tenant_id');
        $tenant = Tenant::where('id', $tenantId)->firstOrFail();

        $subfolderData = SubFolder::with(['project', 'lab', 'group'])
            ->where('tenant_id', $tenant->id)
            ->where('id', $subfolder)
            ->firstOrFail();

        $projectData = $subfolderData->project;
        if (!$projectData) {
            abort(404);
        }

        $isTeacherOwner = (int) ($projectData->lab?->creator_subuser_id ?? 0) === (int) $subUser->id;
        $isAssignedLab = !empty($subUser->lab_id) && (int) $projectData->lab_id === (int) $subUser->lab_id;
        $isAssignedGroup = !empty($subUser->group_id) && (int) $projectData->group_id === (int) $subUser->group_id;

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

        $flow = $this->buildSubfolderFlowData($tenant, $subfolderData);

        $groups = $isStudent
            ? Group::with('projects.subfolders')
                ->where('id', (int) $subUser->group_id)
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
            'subfolder' => $subfolderData,
            'lab' => $subfolderData->lab,
            'group' => $subfolderData->group,
            'versions' => $flow['versions'],
            'latestVersion' => $flow['latestVersion'],
            'projectFilesCount' => $flow['projectFilesCount'],
            'projectFiles' => $flow['projectFiles'],
            'versionComments' => $flow['versionComments'],
            'projectStorageMb' => $flow['projectStorageMb'],
            'tenantStorageUsedMb' => $flow['tenantStorageUsedMb'],
            'tenantStorageMaxMb' => $flow['tenantStorageMaxMb'],
            'tenantStoragePercent' => $flow['tenantStoragePercent'],
            'storageTrend' => $flow['storageTrend'],
            'versionsTrend' => $flow['versionsTrend'],
            'storageTrendByPeriod' => $flow['storageTrendByPeriod'],
            'versionsTrendByPeriod' => $flow['versionsTrendByPeriod'],
            'versionStats' => $flow['versionStats'],
            'versionFlowRecentLimit' => 6,
            'canComment' => $isTeacher,
            'canEditVersionStatus' => false,
            'canAddVersion' => $isTeacherOwner || $isStudent,
            'canEditVersion' => false,
            'canDeleteVersion' => false,
            'statusOptions' => [
                ['value' => 'approved', 'label' => 'Aprovado'],
                ['value' => 'rejected', 'label' => 'Rejeitado'],
                ['value' => 'submitted', 'label' => 'Enviado'],
            ],
            'maxUploadMb' => $flow['maxUploadMb'],
            'notifications' => Notification::where('user_id', $subUser->id)->where('table', 'users')->orderBy('created_at', 'desc')->get(),
            'userPreferences' => User::where('id', $subUser->id)->value('preferences'),
            'pageTitle' => $projectData->title . ' / ' . $subfolderData->name,
            'pageBreadcrumbHome' => 'Inicio',
            'pageBreadcrumbCurrent' => 'Subfolder',
            'layout' => 'layouts.header-side-not-sub',
            'canCreateLab' => $isTeacher,
            'canCreateGroup' => $isTeacher,
            'canCreateProject' => $isStudent || ($isTeacher && ($isTeacherOwner || $isAssignedLab)),
            'canCreateSubfolder' => $isStudent || ($isTeacher && ($isTeacherOwner || $isAssignedLab)),
            'canEditLabStatus' => $isTeacherOwner,
            'canEditGroupStatus' => $isTeacherOwner,
            'canEditProjectStatus' => $isTeacherOwner,
        ];

        $theme = $this->getTheme($data['userPreferences']);

        return view('main.home.labs-groups-projects.index-subfolder', $data, [
            'theme' => $theme,
            'user' => $subUser,
        ]);
    }

    /**
     * Executa a rotina 'buildSubfolderFlowData' no fluxo de negocio.
     */
    private function buildSubfolderFlowData(Tenant $tenant, SubFolder $subfolder): array
    {
        $usedBytes = (int) ($tenant->storage_used_mb ?? 0);
        $usedMb = $usedBytes / 1048576;
        $maxStorageMb = (float) ($tenant->limitFor('storage') ?? $tenant->max_storage_mb ?? 0);
        $remainingStorageMb = max(0, $maxStorageMb - $usedMb);

        $versions = ProjectVersion::where('tenant_id', $tenant->id)
            ->where('project_id', $subfolder->project_id)
            ->where('subfolder_id', $subfolder->id)
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
        $monthLabels = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        $trendMonths = collect(range(0, 11))->map(function ($offset) {
            return Carbon::now()->startOfMonth()->subMonths(11 - $offset);
        });

        foreach ($trendMonths as $month) {
            $monthFiles = $projectFiles->filter(function ($file) use ($month) {
                return Carbon::parse($file->created_at)->isSameMonth($month);
            });

            $monthVersions = $versions->filter(function ($version) use ($month) {
                return Carbon::parse($version->created_at)->isSameMonth($month);
            });

            $storageTrend[] = [
                'label' => $monthLabels[$month->month - 1] . '/' . $month->format('y'),
                'value' => round($monthFiles->sum('size') / 1048576, 2),
                'color' => '#ff8c00',
            ];

            $versionsTrend[] = [
                'label' => $monthLabels[$month->month - 1] . '/' . $month->format('y'),
                'value' => $monthVersions->count(),
                'color' => '#3f51b5',
            ];
        }

        $periods = [3, 6, 12];
        $storageTrendByPeriod = [];
        $versionsTrendByPeriod = [];
        foreach ($periods as $period) {
            $storageTrendByPeriod[(string) $period] = array_slice($storageTrend, -$period);
            $versionsTrendByPeriod[(string) $period] = array_slice($versionsTrend, -$period);
        }

        $latestVersion = $versions->sortByDesc('version_number')->first();

        return [
            'versions' => $versions,
            'latestVersion' => $latestVersion,
            'projectFilesCount' => $projectFiles->count(),
            'projectFiles' => $projectFiles,
            'versionComments' => $versionComments,
            'projectStorageMb' => $projectStorageMb,
            'tenantStorageUsedMb' => $tenantStorageUsedMb,
            'tenantStorageMaxMb' => $tenantStorageMaxMb,
            'tenantStoragePercent' => $tenantStoragePercent,
            'storageTrend' => $storageTrendByPeriod['3'] ?? [],
            'versionsTrend' => $versionsTrendByPeriod['3'] ?? [],
            'storageTrendByPeriod' => $storageTrendByPeriod,
            'versionsTrendByPeriod' => $versionsTrendByPeriod,
            'versionStats' => [
                'draft' => $versions->where('status_version', 'draft')->count(),
                'submitted' => $versions->where('status_version', 'submitted')->count(),
                'approved' => $versions->where('status_version', 'approved')->count(),
                'rejected' => $versions->where('status_version', 'rejected')->count(),
            ],
            'maxUploadMb' => $remainingStorageMb,
        ];
    }

    /**
     * Executa a rotina 'getTheme' no fluxo de negocio.
     */
    private function getTheme($userPreferences)
    {
        return app(UserUiPreferencesService::class)->resolveTheme($userPreferences);
    }

    /**
     * Valida os dados recebidos e persiste um novo registro.
     */
    public function store(Request $request)
    {
        $subUser = Auth::user();
        $isMember = $subUser && $subUser->role !== 'owner';
        if ($isMember) {
            $isTeacher = $subUser->role === 'teacher';
            $isStudent = $subUser->role === 'student';

            if (!$isTeacher && !$isStudent) {
                abort(403);
            }
            $tenantId = $subUser->tenant_id ?: Lab::where('id', $subUser->lab_id)->value('tenant_id');
            $tenant = Tenant::where('id', $tenantId)->firstOrFail();
        } else {
            $tenant = Tenant::where('creator_id', Auth::id())->firstOrFail();
        }

        $data = $request->validate([
            'project_id' => 'required|integer|exists:projects,id',
            'name' => 'required|string|min:2|max:100',
            'description' => 'nullable|string|max:2000',
        ]);

        $projectRow = Project::where('tenant_id', $tenant->id)
            ->where('id', $data['project_id'])
            ->firstOrFail();

        if ($isMember) {
            $isTeacher = $subUser->role === 'teacher';
            $isStudent = $subUser->role === 'student';

            if ($isTeacher) {
                $labOwnerId = Lab::where('tenant_id', $tenant->id)
                    ->where('id', $projectRow->lab_id)
                    ->value('creator_subuser_id');
                $isAssignedLab = !empty($subUser->lab_id) && (int) $projectRow->lab_id === (int) $subUser->lab_id;

                if ((int) $labOwnerId !== (int) $subUser->id && !$isAssignedLab) {
                    abort(403);
                }
            } elseif ($isStudent) {
                $isAssignedGroup = !empty($subUser->group_id) && (int) $projectRow->group_id === (int) $subUser->group_id;
                if (!$isAssignedGroup) {
                    abort(403);
                }
            }
        }

        $baseSlug = Str::slug($data['name']) ?: 'subfolder';
        $slug = $baseSlug;
        $suffix = 1;

        while (SubFolder::where('tenant_id', $tenant->id)->where('project_id', $projectRow->id)->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $suffix;
            $suffix++;
        }

        $nextOrder = (int) (SubFolder::where('tenant_id', $tenant->id)->where('project_id', $projectRow->id)->max('order_index') ?? 0) + 1;

        SubFolder::create([
            'tenant_id' => $tenant->id,
            'lab_id' => $projectRow->lab_id,
            'group_id' => $projectRow->group_id,
            'project_id' => $projectRow->id,
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'order_index' => $nextOrder,
            'current_version' => 0,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
            ], 201);
        }

        return back()->with('success', 'Subfolder criada.');
    }

    /**
     * Aplica alteracoes em um registro existente.
     */
    public function update(Request $request)
    {
        $subUser = Auth::user();
        $isMember = $subUser && $subUser->role !== 'owner';
        $isTeacher = false;
        $isStudent = false;
        if ($isMember) {
            $isTeacher = $subUser->role === 'teacher';
            $isStudent = $subUser->role === 'student';
            if (!$isTeacher && !$isStudent) {
                abort(403);
            }
            $tenantId = $subUser->tenant_id ?: Lab::where('id', $subUser->lab_id)->value('tenant_id');
            $tenant = Tenant::where('id', $tenantId)->firstOrFail();
        } else {
            $tenant = Tenant::where('creator_id', Auth::id())->firstOrFail();
        }

        $data = $request->validate([
            'subfolder_id' => 'required|integer',
            'name' => 'nullable|string|min:2|max:100',
        ]);

        $hasNameUpdate = isset($data['name']) && trim((string) $data['name']) !== '';
        if (!$hasNameUpdate) {
            return response()->json([
                'message' => 'Nada para atualizar.',
            ], 422);
        }

        $subfolder = SubFolder::where('tenant_id', $tenant->id)
            ->where('id', $data['subfolder_id'])
            ->firstOrFail();

        $projectRow = Project::where('tenant_id', $tenant->id)
            ->where('id', $subfolder->project_id)
            ->firstOrFail();

        if ($isMember && $isTeacher) {
            $labOwnerId = Lab::where('tenant_id', $tenant->id)
                ->where('id', $projectRow->lab_id)
                ->value('creator_subuser_id');
            $isAssignedLab = !empty($subUser->lab_id) && (int) $projectRow->lab_id === (int) $subUser->lab_id;

            if ((int) $labOwnerId !== (int) $subUser->id && !$isAssignedLab) {
                abort(403);
            }
        }

        if ($isMember && $isStudent) {
            $isAssignedGroup = UserRelation::where('user_id', (int) $subUser->id)
                ->where('tenant_id', (int) $tenant->id)
                ->where('group_id', (int) $projectRow->group_id)
                ->where('status', 'active')
                ->exists();
            if (!$isAssignedGroup) {
                abort(403);
            }
        }

        $newName = trim((string) $data['name']);
        if ($newName === $subfolder->name) {
            return response()->json([
                'success' => true,
                'name' => $subfolder->name,
            ]);
        }

        $baseSlug = Str::slug($newName) ?: 'subfolder';
        $slug = $baseSlug;
        $suffix = 1;

        while (SubFolder::where('tenant_id', $tenant->id)
            ->where('project_id', $projectRow->id)
            ->where('slug', $slug)
            ->where('id', '!=', $subfolder->id)
            ->exists()) {
            $slug = $baseSlug . '-' . $suffix;
            $suffix++;
        }

        $subfolder->name = $newName;
        $subfolder->slug = $slug;
        $subfolder->save();

        return response()->json([
            'success' => true,
            'name' => $subfolder->name,
        ]);
    }
}

