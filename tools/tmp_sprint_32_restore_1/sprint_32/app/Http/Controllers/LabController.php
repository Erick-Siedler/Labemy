<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Lab;
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Services\HomeOwnerDataService;
use App\Services\SubHomeDataService;
use App\Services\ActivityService;

class LabController extends Controller
{
    public function index($lab, HomeOwnerDataService $homeOwnerData, SubHomeDataService $subHomeData)
    {
        $subUser = Auth::guard('subusers')->user();
        if ($subUser) {
            return $this->indexSubuser($lab, $subUser, $homeOwnerData, $subHomeData);
        }

        $user = Auth::user();
        $tenant = Tenant::where('creator_id', $user->id)->firstOrFail();

        $labData = Lab::with('groups', 'projects', 'subUsers')
            ->where('tenant_id', $tenant->id)
            ->where('id', $lab)
            ->firstOrFail();

        $data = $homeOwnerData->build($user);
        $data['lab'] = $labData;
        $data['dadosPorAnoProj'] = $homeOwnerData->getLabProjectHeatmap($tenant->id, $labData->id);
        $data['projectStatusChart'] = [
            ['label' => 'Rascunho', 'value' => $labData->projects->where('status', 'draft')->count(), 'color' => '#90a4ae'],
            ['label' => 'Em andamento', 'value' => $labData->projects->where('status', 'in_progress')->count(), 'color' => '#ffb74d'],
            ['label' => 'Aprovado', 'value' => $labData->projects->where('status', 'approved')->count(), 'color' => '#4caf50'],
            ['label' => 'Rejeitado', 'value' => $labData->projects->where('status', 'rejected')->count(), 'color' => '#f44336'],
            ['label' => 'Arquivado', 'value' => $labData->projects->where('status', 'archived')->count(), 'color' => '#757575'],
        ];
        $monthLabels = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        $projectEvolutionChart = [];

        for ($month = 1; $month <= 12; $month++) {
            $count = $labData->projects->filter(function ($project) use ($month) {
                if (!$project->created_at) {
                    return false;
                }

                $date = Carbon::parse($project->created_at);
                return $date->year === 2026 && $date->month === $month;
            })->count();

            $projectEvolutionChart[] = [
                'label' => $monthLabels[$month - 1],
                'value' => $count,
                'color' => '#ff8c00',
            ];
        }

        $data['projectEvolutionChart'] = $projectEvolutionChart;
        $data['pageTitle'] = $labData->name;
        $data['pageBreadcrumbHome'] = 'Início';
        $data['pageBreadcrumbCurrent'] = 'Laboratório';

        $theme = $this->getTheme($data['userPreferences']);

        return view('main.home.labs-groups-projects.index-lab', $data, [
            'theme' => $theme
        ]);
    }

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
        $data['dadosPorAnoProj'] = $homeOwnerData->getLabProjectHeatmap($tenant->id, $labData->id);
        $data['projectStatusChart'] = [
            ['label' => 'Rascunho', 'value' => $labData->projects->where('status', 'draft')->count(), 'color' => '#90a4ae'],
            ['label' => 'Em andamento', 'value' => $labData->projects->where('status', 'in_progress')->count(), 'color' => '#ffb74d'],
            ['label' => 'Aprovado', 'value' => $labData->projects->where('status', 'approved')->count(), 'color' => '#4caf50'],
            ['label' => 'Rejeitado', 'value' => $labData->projects->where('status', 'rejected')->count(), 'color' => '#f44336'],
            ['label' => 'Arquivado', 'value' => $labData->projects->where('status', 'archived')->count(), 'color' => '#757575'],
        ];

        $monthLabels = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        $projectEvolutionChart = [];

        for ($month = 1; $month <= 12; $month++) {
            $count = $labData->projects->filter(function ($project) use ($month) {
                if (!$project->created_at) {
                    return false;
                }

                $date = Carbon::parse($project->created_at);
                return $date->year === 2026 && $date->month === $month;
            })->count();

            $projectEvolutionChart[] = [
                'label' => $monthLabels[$month - 1],
                'value' => $count,
                'color' => '#ff8c00',
            ];
        }

        $data['projectEvolutionChart'] = $projectEvolutionChart;
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
            'creator_subuser_id' => $subUser?->id,
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

    public function update(Request $request)
    {
        $data = $request->validate([
            'lab_id' => 'required|integer',
            'status' => 'required|in:draft,active,archived,closed',
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

        $lab = Lab::where('tenant_id', $tenant->id)
            ->where('id', $data['lab_id'])
            ->firstOrFail();

        if ($subUser && (int) $lab->creator_subuser_id !== (int) $subUser->id) {
            abort(403);
        }

        $lab->status = $data['status'];
        $lab->save();
        $actor = ActivityService::resolveActor();
        if (!empty($actor['tenant_id'])) {
            ActivityService::log(
                (int) $actor['tenant_id'],
                (int) $actor['actor_id'],
                (string) $actor['actor_role'],
                'lab_update',
                'lab',
                (int) $lab->id,
                'Status do laboratório atualizado para ' . $lab->status . ' (Lab: ' . $lab->name . ').'
            );
        }

        return response()->json([
            'success' => true,
            'status' => $lab->status,
        ]);
    }
}
