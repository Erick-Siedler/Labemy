<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Lab;
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\ProjectVersion;
use App\Models\ProjectComment;
use App\Models\ProjectFile;
use App\Services\HomeOwnerDataService;
use App\Services\SubHomeDataService;
use App\Services\ActivityService;
use App\Services\UserUiPreferencesService;

class LabController extends Controller
{
    /**
     * Lista e prepara os dados exibidos na tela.
     */
    public function index($lab, HomeOwnerDataService $homeOwnerData, SubHomeDataService $subHomeData)
    {
        $subUser = Auth::user();
        $isMember = $subUser && $subUser->role !== 'owner';
        if ($isMember) {
            return $this->indexSubuser($lab, $subUser, $homeOwnerData, $subHomeData);
        }

        $user = Auth::user();
        if ((string) ($user->plan ?? '') === 'solo') {
            return redirect()->route('home-solo');
        }
        $tenant = Tenant::where('creator_id', $user->id)->firstOrFail();

        $labData = Lab::with('groups', 'projects', 'subUsers')
            ->where('tenant_id', $tenant->id)
            ->where('id', $lab)
            ->firstOrFail();

        $data = $homeOwnerData->build($user);
        $data['lab'] = $labData;
        $labMetrics = $this->buildLabProjectMetrics($labData);
        $data['projectVersionCountMap'] = $labMetrics['projectVersionCountMap'];
        $data['projectCommentCountMap'] = $labMetrics['projectCommentCountMap'];
        $data['projectStorageMbMap'] = $labMetrics['projectStorageMbMap'];
        $data['groupVersionCountMap'] = $labMetrics['groupVersionCountMap'];
        $data['groupStudentCountMap'] = $labData->subUsers
            ->where('role', 'student')
            ->groupBy('group_id')
            ->map
            ->count()
            ->all();
        $data['dadosPorAnoProj'] = $homeOwnerData->getLabProjectHeatmap($tenant->id, $labData->id);
        $labCharts = $this->buildLabChartsByPeriod($labData->projects);
        $data['projectStatusChartByPeriod'] = $labCharts['statusByPeriod'];
        $data['projectStatusChart'] = $labCharts['statusByPeriod']['3'] ?? [];
        $data['projectEvolutionChartByPeriod'] = $labCharts['evolutionByPeriod'];
        $data['projectEvolutionChart'] = $labCharts['evolutionByPeriod']['3'] ?? [];
        $data['calendar'] = $homeOwnerData->buildLabCalendar(
            (int) $tenant->id,
            (int) $labData->id,
            (int) request('ano', now()->year),
            (int) request('mes', now()->month)
        );
        $data['pageTitle'] = $labData->name;
        $data['pageBreadcrumbHome'] = 'Início';
        $data['pageBreadcrumbCurrent'] = 'Laboratório';

        $theme = $this->getTheme($data['userPreferences']);

        return view('main.home.labs-groups-projects.index-lab', $data, [
            'theme' => $theme
        ]);
    }

    /**
     * Executa a rotina 'indexSubuser' no fluxo de negocio.
     */
    private function indexSubuser($lab, $subUser, HomeOwnerDataService $homeOwnerData, SubHomeDataService $subHomeData)
    {
        if ($subUser->role !== 'teacher') {
            abort(403);
        }

        $tenantId = $subUser->tenant_id ?: Lab::where('id', $subUser->lab_id)->value('tenant_id');
        $tenant = Tenant::where('id', $tenantId)->firstOrFail();

        $labData = Lab::with('groups', 'projects', 'subUsers')
            ->where('tenant_id', $tenant->id)
            ->where('id', $lab)
            ->firstOrFail();

        $isTeacherOwner = (int) ($labData->creator_subuser_id ?? 0) === (int) $subUser->id;
        $isAssignedLab = !empty($subUser->lab_id) && (int) $labData->id === (int) $subUser->lab_id;

        if (!$isTeacherOwner && !$isAssignedLab) {
            abort(403);
        }

        $data = $subHomeData->buildTeacher($subUser);
        $data['lab'] = $labData;
        $labMetrics = $this->buildLabProjectMetrics($labData);
        $data['projectVersionCountMap'] = $labMetrics['projectVersionCountMap'];
        $data['projectCommentCountMap'] = $labMetrics['projectCommentCountMap'];
        $data['projectStorageMbMap'] = $labMetrics['projectStorageMbMap'];
        $data['groupVersionCountMap'] = $labMetrics['groupVersionCountMap'];
        $data['groupStudentCountMap'] = $labData->subUsers
            ->where('role', 'student')
            ->groupBy('group_id')
            ->map
            ->count()
            ->all();
        $data['dadosPorAnoProj'] = $homeOwnerData->getLabProjectHeatmap($tenant->id, $labData->id);
        $labCharts = $this->buildLabChartsByPeriod($labData->projects);
        $data['projectStatusChartByPeriod'] = $labCharts['statusByPeriod'];
        $data['projectStatusChart'] = $labCharts['statusByPeriod']['3'] ?? [];
        $data['projectEvolutionChartByPeriod'] = $labCharts['evolutionByPeriod'];
        $data['projectEvolutionChart'] = $labCharts['evolutionByPeriod']['3'] ?? [];
        $data['calendar'] = $homeOwnerData->buildLabCalendar(
            (int) $tenant->id,
            (int) $labData->id,
            (int) request('ano', now()->year),
            (int) request('mes', now()->month)
        );
        $data['pageTitle'] = $labData->name;
        $data['pageBreadcrumbHome'] = 'Início';
        $data['pageBreadcrumbCurrent'] = 'Laboratório';
        $data['canEditLabStatus'] = $isTeacherOwner;
        $data['canEditGroupStatus'] = $isTeacherOwner;
        $data['canEditProjectStatus'] = $isTeacherOwner;

        $theme = $this->getTheme($data['userPreferences']);

        return view('main.home.labs-groups-projects.index-lab', $data, [
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
     * Monta dados de graficos por periodo (3, 6 e 12 meses).
     */
    private function buildLabChartsByPeriod($projects): array
    {
        $periods = [3, 6, 12];
        $monthLabels = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        $now = Carbon::now()->startOfMonth();

        $projectRows = collect($projects)->map(function ($project) {
            return [
                'status' => (string) ($project->status ?? ''),
                'created_at' => $project->created_at ? Carbon::parse($project->created_at) : null,
            ];
        });

        $statusByPeriod = [];
        foreach ($periods as $period) {
            $startDate = $now->copy()->subMonths($period - 1)->startOfDay();
            $periodProjects = $projectRows->filter(function ($project) use ($startDate) {
                return $project['created_at'] && $project['created_at']->greaterThanOrEqualTo($startDate);
            });

            $statusByPeriod[(string) $period] = [
                ['label' => 'Rascunho', 'value' => $periodProjects->where('status', 'draft')->count(), 'color' => '#90a4ae'],
                ['label' => 'Em andamento', 'value' => $periodProjects->where('status', 'in_progress')->count(), 'color' => '#ffb74d'],
                ['label' => 'Aprovado', 'value' => $periodProjects->where('status', 'approved')->count(), 'color' => '#4caf50'],
                ['label' => 'Rejeitado', 'value' => $periodProjects->where('status', 'rejected')->count(), 'color' => '#f44336'],
                ['label' => 'Arquivado', 'value' => $periodProjects->where('status', 'archived')->count(), 'color' => '#757575'],
            ];
        }

        $evolutionAllMonths = collect(range(0, 11))
            ->map(function ($offset) use ($now, $projectRows, $monthLabels) {
                $monthCursor = $now->copy()->subMonths(11 - $offset);
                $count = $projectRows->filter(function ($project) use ($monthCursor) {
                    return $project['created_at'] && $project['created_at']->isSameMonth($monthCursor);
                })->count();

                return [
                    'label' => $monthLabels[$monthCursor->month - 1] . '/' . $monthCursor->format('y'),
                    'value' => $count,
                    'color' => '#ff8c00',
                ];
            })
            ->values()
            ->all();

        $evolutionByPeriod = [];
        foreach ($periods as $period) {
            $evolutionByPeriod[(string) $period] = array_slice($evolutionAllMonths, -$period);
        }

        return [
            'statusByPeriod' => $statusByPeriod,
            'evolutionByPeriod' => $evolutionByPeriod,
        ];
    }

    /**
     * Agrega metricas de versoes, armazenamento e comentarios dos projetos do laboratorio.
     */
    private function buildLabProjectMetrics(Lab $labData): array
    {
        $projectIds = $labData->projects->pluck('id')->filter()->values();

        if ($projectIds->isEmpty()) {
            return [
                'projectVersionCountMap' => [],
                'projectCommentCountMap' => [],
                'projectStorageMbMap' => [],
                'groupVersionCountMap' => [],
            ];
        }

        $versions = ProjectVersion::where('tenant_id', $labData->tenant_id)
            ->where('lab_id', $labData->id)
            ->whereIn('project_id', $projectIds)
            ->get(['id', 'project_id']);

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

        $groupVersionCountMap = $labData->groups
            ->mapWithKeys(function ($group) use ($projectVersionCountMap) {
                $total = (int) $group->projects->sum(fn ($project) => (int) ($projectVersionCountMap[$project->id] ?? 0));
                return [$group->id => $total];
            });

        return [
            'projectVersionCountMap' => $projectVersionCountMap->all(),
            'projectCommentCountMap' => $projectCommentCountMap->all(),
            'projectStorageMbMap' => $projectStorageMbMap->all(),
            'groupVersionCountMap' => $groupVersionCountMap->all(),
        ];
    }

    /**
     * Valida os dados recebidos e persiste um novo registro.
     */
    function store(Request $request){
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

        if ($tenant->hasReachedLimit('labs', Lab::where('tenant_id', $tenant->id)->count())) {
            return response()->json([
                'message' => 'Limite de laboratórios atingido para o seu plano.',
            ], 422);
        }

        $data = $request->validate([
            'name' => 'required|min:3'
        ]);

        $tenant_id = $tenant->id;
        $creatorId = $tenant->creator_id;

        $lab = Lab::create([
            'tenant_id' => $tenant_id,
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
                'lab_create',
                'lab',
                (int) $lab->id,
                'Laboratório criado: ' . $lab->name . '.'
            );
        }

        return response()->json([
            'success' => true   
        ], 201);
    }

    /**
     * Aplica alteracoes em um registro existente.
     */
    public function update(Request $request)
    {
        $data = $request->validate([
            'lab_id' => 'required|integer',
            'status' => 'nullable|in:draft,active,archived,closed',
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

        $lab = Lab::where('tenant_id', $tenant->id)
            ->where('id', $data['lab_id'])
            ->firstOrFail();

        if ($isMember && (int) $lab->creator_subuser_id !== (int) $subUser->id) {
            abort(403);
        }

        $oldStatus = $lab->status;
        $oldName = $lab->name;

        if ($hasStatusUpdate) {
            $lab->status = $data['status'];
        }

        if ($hasNameUpdate) {
            $newName = trim((string) $data['name']);
            $lab->name = $newName;
            $lab->code = Str::replace(' ', '-', $newName);
        }

        $lab->save();
        $actor = ActivityService::resolveActor();
        if (!empty($actor['tenant_id'])) {
            $changes = [];
            if ($oldName !== $lab->name) {
                $changes[] = 'Nome do laboratorio atualizado para ' . $lab->name . '.';
            }
            if ($oldStatus !== $lab->status) {
                $changes[] = 'Status do laboratorio atualizado para ' . $lab->status . '.';
            }

            ActivityService::log(
                (int) $actor['tenant_id'],
                (int) $actor['actor_id'],
                (string) $actor['actor_role'],
                'lab_update',
                'lab',
                (int) $lab->id,
                !empty($changes)
                    ? implode(' ', $changes)
                    : 'Laboratorio atualizado: ' . $lab->name . '.'
            );
        }

        return response()->json([
            'success' => true,
            'status' => $lab->status,
            'name' => $lab->name,
        ]);
    }
}
