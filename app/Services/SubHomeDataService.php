<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Group;
use App\Models\Lab;
use App\Models\Log;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Project;
use App\Models\ProjectComment;
use App\Models\ProjectFile;
use App\Models\ProjectVersion;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRelation;
use Carbon\Carbon;
use Illuminate\Support\Str;

class SubHomeDataService{

    /**
     * Executa a rotina 'buildStudent' no fluxo de negocio.
     */
    public function buildStudent($student){
        $tenantId = $student->tenant_id ?: Lab::where('id', $student->lab_id)
            ->value('tenant_id');

        $groups = $this->getGroups($student);
        $group = $groups->first();
        $lab = $group?->lab;

        $projects = $group?->projects?->sortByDesc('created_at')->values() ?? collect();
        $selectedProjectId = $this->resolveRequestId(request('project', 0));
        $project = $selectedProjectId ? $projects->firstWhere('id', $selectedProjectId) : $projects->first();

        $versions = collect();
        $projectFiles = collect();
        $versionComments = collect();
        $tasks = collect();
        $canManageTasks = false;
        $calendar = $this->emptyCalendar();

        if ($tenantId && $project) {
            $versions = ProjectVersion::where('tenant_id', $tenantId)
                ->where('project_id', $project->id)
                ->orderBy('version_number', 'asc')
                ->get();

            $tasks = $this->getProjectTasks((int) $tenantId, (int) $project->id);
            $canManageTasks = $this->canManageProjectTasks((int) $student->id, (int) $project->group_id);

            $versionIds = $versions->pluck('id');
            $projectFiles = $versionIds->isEmpty()
                ? collect()
                : ProjectFile::where('tenant_id', $tenantId)
                    ->whereIn('project_versions_id', $versionIds)
                    ->get();

            $versionComments = $versionIds->isEmpty()
                ? collect()
                : ProjectComment::with(['creator', 'subCreator'])
                    ->where('tenant_id', $tenantId)
                    ->whereIn('project_version_id', $versionIds)
                    ->orderBy('created_at', 'desc')
                    ->get()
                    ->groupBy('project_version_id');
        }

        if ($tenantId && $lab) {
            $calendar = $this->buildEventCalendar(
                $tenantId,
                (int) request('ano', now()->year),
                (int) request('mes', now()->month),
                $lab->id
            );
        }

        $maxUploadMb = 0;
        if ($tenantId) {
            $tenant = Tenant::where('id', $tenantId)->first();
            if ($tenant) {
                $usedBytes = (int) ($tenant->storage_used_mb ?? 0);
                $usedMb = $usedBytes / 1048576;
                $maxStorageMb = (float) ($tenant->limitFor('storage') ?? $tenant->max_storage_mb ?? 0);
                $maxUploadMb = max(0, $maxStorageMb - $usedMb);
            }
        }

        return [
            'groups' => $groups,
            'group' => $group,
            'lab' => $lab,
            'projects' => $projects,
            'project' => $project,
            'versions' => $versions,
            'versionFlowRecentLimit' => 6,
            'projectFiles' => $projectFiles,
            'versionComments' => $versionComments,
            'tasks' => $tasks,
            'canManageTasks' => $canManageTasks,
            'calendar' => $calendar,
            'maxUploadMb' => $maxUploadMb,
            'notifications' => Notification::where('user_id', $student->id)->where('table', 'users')->orderBy('created_at', 'desc')->get(),
            'userPreferences' => User::where('id', $student->id)->value('preferences'),
            'pageTitle' => 'Versionamento',
            'pageBreadcrumbHome' => 'Inicio',
            'pageBreadcrumbCurrent' => 'Versoes',
            'canCreateLab' => false,
            'canCreateGroup' => false,
            'canCreateProject' => true,
            'canCreateSubfolder' => true,
            'canCreateEvent' => false,
            'canCreateEventAll' => false,
            'canInviteStudents' => false,
            'canEditLabStatus' => false,
            'canEditGroupStatus' => false,
            'canEditProjectStatus' => false,
        ];
    }

    /**
     * Executa a rotina 'buildAssistant' no fluxo de negocio.
     */
    public function buildAssistant($assistant): array
    {
        $tenantId = $assistant->tenant_id ?: Lab::where('id', $assistant->lab_id)
            ->value('tenant_id');

        $lab = $assistant->lab_id
            ? Lab::where('id', $assistant->lab_id)->first()
            : null;

        $groups = $assistant->lab_id
            ? Group::with('projects.subfolders')
                ->where('lab_id', $assistant->lab_id)
                ->orderBy('name')
                ->get()
            : collect();

        $selectedGroupId = $this->resolveRequestId(request('group', 0));
        $group = $selectedGroupId
            ? $groups->firstWhere('id', $selectedGroupId)
            : $groups->first();

        $projects = $group?->projects?->sortByDesc('created_at')->values() ?? collect();
        $selectedProjectId = $this->resolveRequestId(request('project', 0));
        $project = $selectedProjectId ? $projects->firstWhere('id', $selectedProjectId) : $projects->first();

        $versions = collect();
        $projectFiles = collect();
        $versionComments = collect();
        $tasks = collect();
        $canManageTasks = false;
        $calendar = $this->emptyCalendar();

        if ($tenantId && $project) {
            $versions = ProjectVersion::where('tenant_id', $tenantId)
                ->where('project_id', $project->id)
                ->orderBy('version_number', 'asc')
                ->get();

            $tasks = $this->getProjectTasks((int) $tenantId, (int) $project->id);
            $canManageTasks = $this->canManageProjectTasks((int) $assistant->id, (int) $project->group_id);

            $versionIds = $versions->pluck('id');
            $projectFiles = $versionIds->isEmpty()
                ? collect()
                : ProjectFile::where('tenant_id', $tenantId)
                    ->whereIn('project_versions_id', $versionIds)
                    ->get();

            $versionComments = $versionIds->isEmpty()
                ? collect()
                : ProjectComment::with(['creator', 'subCreator'])
                    ->where('tenant_id', $tenantId)
                    ->whereIn('project_version_id', $versionIds)
                    ->orderBy('created_at', 'desc')
                    ->get()
                    ->groupBy('project_version_id');
        }

        if ($tenantId && $lab) {
            $calendar = $this->buildEventCalendar(
                $tenantId,
                (int) request('ano', now()->year),
                (int) request('mes', now()->month),
                $lab->id
            );
        }

        $maxUploadMb = 0;
        if ($tenantId) {
            $tenant = Tenant::where('id', $tenantId)->first();
            if ($tenant) {
                $usedBytes = (int) ($tenant->storage_used_mb ?? 0);
                $usedMb = $usedBytes / 1048576;
                $maxStorageMb = (float) ($tenant->limitFor('storage') ?? $tenant->max_storage_mb ?? 0);
                $maxUploadMb = max(0, $maxStorageMb - $usedMb);
            }
        }

        return [
            'groups' => $groups,
            'group' => $group,
            'lab' => $lab,
            'projects' => $projects,
            'project' => $project,
            'versions' => $versions,
            'versionFlowRecentLimit' => 6,
            'projectFiles' => $projectFiles,
            'versionComments' => $versionComments,
            'tasks' => $tasks,
            'canManageTasks' => $canManageTasks,
            'calendar' => $calendar,
            'maxUploadMb' => $maxUploadMb,
            'notifications' => Notification::where('user_id', $assistant->id)->where('table', 'users')->orderBy('created_at', 'desc')->get(),
            'userPreferences' => User::where('id', $assistant->id)->value('preferences'),
            'pageTitle' => 'Revisao',
            'pageBreadcrumbHome' => 'Inicio',
            'pageBreadcrumbCurrent' => 'Revisao',
            'canCreateLab' => false,
            'canCreateGroup' => false,
            'canCreateProject' => false,
            'canCreateSubfolder' => false,
            'canCreateEvent' => false,
            'canCreateEventAll' => false,
            'canInviteStudents' => false,
            'canEditLabStatus' => false,
            'canEditGroupStatus' => false,
            'canEditProjectStatus' => false,
        ];
    }

    /**
     * Executa a rotina 'buildTeacher' no fluxo de negocio.
     */
    public function buildTeacher($teacher): array
    {
        $tenantId = $teacher->tenant_id ?: Lab::where('id', $teacher->lab_id)
            ->value('tenant_id');

        $tenant = $tenantId ? Tenant::where('id', $tenantId)->first() : null;

        $labs = $tenantId
            ? Lab::with(['groups.projects.subfolders', 'subUsers', 'creatorSubuser'])
                ->where('tenant_id', $tenantId)
                ->where(function ($query) use ($teacher) {
                    $query->where('creator_subuser_id', $teacher->id);
                    if (!empty($teacher->lab_id)) {
                        $query->orWhere('id', $teacher->lab_id);
                    }
                })
                ->orderBy('name')
                ->get()
            : collect();

        $labIds = $labs->pluck('id')->all();
        $groups = $labs->flatMap(function ($lab) {
            return $lab->groups ?? collect();
        })->values();

        $projects = $groups->flatMap(function ($group) {
            return $group->projects ?? collect();
        })->values();

        $students = $tenantId && !empty($labIds)
            ? UserRelation::with(['user', 'lab', 'group'])
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->whereIn('lab_id', $labIds)
                ->whereIn('role', ['teacher', 'assistant', 'asssitant', 'assitant', 'student'])
                ->get()
                ->map(function (UserRelation $relation) {
                    $user = $relation->user;
                    if (!$user) {
                        return null;
                    }

                    $user->setAttribute('tenant_id', (int) $relation->tenant_id);
                    $user->setAttribute('lab_id', $relation->lab_id);
                    $user->setAttribute('group_id', $relation->group_id);
                    $user->setAttribute('role', (string) $relation->role);
                    $user->setRelation('lab', $relation->lab);
                    $user->setRelation('group', $relation->group);

                    return $user;
                })
                ->filter()
                ->values()
            : collect();

        $calendar = $this->buildEventCalendarForLabs(
            $tenantId,
            (int) request('ano', now()->year),
            (int) request('mes', now()->month),
            $labIds
        );

        $tenantLimits = $tenant
            ? [
                'labs' => $tenant->limitFor('labs'),
                'groups' => $tenant->limitFor('groups'),
                'projects' => $tenant->limitFor('projects'),
                'storage' => $tenant->limitFor('storage'),
                'users' => $tenant->limitFor('users'),
            ]
            : [
                'labs' => null,
                'groups' => null,
                'projects' => null,
                'storage' => null,
                'users' => null,
            ];

        $dadosPorAnoProj = $this->buildHeatmap($projects);

        return [
            'user' => $teacher,
            'tenant' => $tenant,
            'tenantLimits' => $tenantLimits,
            'tenantUsage' => [
                'labs' => $labs->count(),
                'groups' => $groups->count(),
                'projects' => $projects->count(),
                'users' => $students->count(),
                'storage' => $tenant?->storage_used_mb,
            ],
            'students' => $students,
            'labs' => $labs,
            'active_projs' => $projects,
            'dadosPorAnoProj' => $dadosPorAnoProj,
            'logs' => $tenantId ? $this->getTenantLogs((int) $tenantId) : collect(),
            'events' => collect(),
            'dadosPorAnoEvent' => [],
            'eventosProximos' => collect(),
            'calendar' => $calendar,
            'notifications' => Notification::where('user_id', $teacher->id)
                ->where('table', 'users')
                ->orderBy('created_at', 'desc')
                ->get(),
            'userPreferences' => User::where('id', $teacher->id)->value('preferences'),
            'pageTitle' => 'Painel',
            'pageBreadcrumbHome' => 'Inicio',
            'pageBreadcrumbCurrent' => 'Visao geral',
            'layout' => 'layouts.header-side-not-sub',
            'canCreateLab' => true,
            'canCreateGroup' => true,
            'canCreateProject' => true,
            'canCreateSubfolder' => true,
            'canCreateEvent' => true,
            'canCreateEventAll' => false,
            'canInviteStudents' => true,
            'canEditLabStatus' => true,
            'canEditGroupStatus' => true,
            'canEditProjectStatus' => true,
        ];
    }

    /**
     * Executa a rotina 'getGroups' no fluxo de negocio.
     */
    private function getGroups($student){
        return Group::with('lab', 'projects.subfolders', 'projects_versions')
        ->where('id', $student->group_id)
        ->orderBy('created_at', 'desc')
        ->get();
    }

    /**
     * Executa a rotina 'getProjectTasks' no fluxo de negocio.
     */
    private function getProjectTasks(int $tenantId, int $projectId)
    {
        return Task::with('version')
            ->where('tenant_id', $tenantId)
            ->where('project_id', $projectId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Executa a rotina 'getTasksForProjects' no fluxo de negocio.
     */
    private function getTasksForProjects(int $tenantId, array $projectIds)
    {
        if (empty($projectIds)) {
            return collect();
        }

        return Task::with('version')
            ->where('tenant_id', $tenantId)
            ->whereIn('project_id', $projectIds)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Verifica se usuario pode gerenciar tasks pelo grupo do projeto.
     */
    private function canManageProjectTasks(int $userId, int $groupId): bool
    {
        if ($groupId <= 0) {
            return false;
        }

        return UserRelation::where('user_id', $userId)
            ->where('group_id', $groupId)
            ->exists();
    }

    /**
     * Executa a rotina 'buildEventCalendar' no fluxo de negocio.
     */
    private function buildEventCalendar($tenantId, $year, $month, $labId)
    {
        $year = max(1, $year);
        $month = min(max(1, $month), 12);

        $dataAtual = Carbon::create($year, $month, 1);
        $prev = $dataAtual->copy()->subMonth();
        $next = $dataAtual->copy()->addMonth();

        $eventosMesAtual = Event::with('lab')
            ->where('tenant_id', $tenantId)
            ->where('lab_id', $labId)
            ->whereYear('due', $year)
            ->whereMonth('due', $month)
            ->orderBy('due')
            ->get();

        $today = Carbon::today();
        $eventosMesAtual = $eventosMesAtual->map(function ($event) use ($today) {
            $eventDate = Carbon::parse($event->due)->startOfDay();
            $daysUntil = $today->diffInDays($eventDate, false);

            $event->event_day = $eventDate->format('d');
            $event->event_month = $eventDate->translatedFormat('M');
            $event->days_until = $daysUntil;
            $event->is_upcoming = $daysUntil >= 0 && $daysUntil <= 7;
            $event->lab_name = $event->lab?->name ?? 'Todos';

            return $event;
        });

        $eventsByDay = $eventosMesAtual->groupBy(function ($event) {
            return Carbon::parse($event->due)->day;
        });

        $calendarDays = [];
        for ($dia = 1; $dia <= $dataAtual->daysInMonth; $dia++) {
            $date = Carbon::create($year, $month, $dia);
            $events = $eventsByDay->get($dia, collect());
            $primaryColor = $events->first()->color ?? null;

            $calendarDays[] = [
                'day' => $dia,
                'count' => $events->count(),
                'events' => $events->take(3),
                'primaryColor' => $primaryColor,
                'isToday' => $date->isToday(),
                'isPast' => $date->isPast() && !$date->isToday(),
            ];
        }

        return [
            'title' => ucfirst($dataAtual->locale('pt_BR')->translatedFormat('F Y')),
            'year' => $year,
            'month' => $month,
            'daysInMonth' => $dataAtual->daysInMonth,
            'leadingBlanks' => $dataAtual->dayOfWeek,
            'prev' => ['year' => $prev->year, 'month' => $prev->month],
            'next' => ['year' => $next->year, 'month' => $next->month],
            'days' => $calendarDays,
            'events' => $eventosMesAtual,
        ];
    }

    /**
     * Executa a rotina 'buildEventCalendarForLabs' no fluxo de negocio.
     */
    private function buildEventCalendarForLabs($tenantId, $year, $month, array $labIds = [])
    {
        $year = max(1, $year);
        $month = min(max(1, $month), 12);

        $dataAtual = Carbon::create($year, $month, 1);
        $prev = $dataAtual->copy()->subMonth();
        $next = $dataAtual->copy()->addMonth();

        $query = Event::with('lab')
            ->where('tenant_id', $tenantId)
            ->whereYear('due', $year)
            ->whereMonth('due', $month)
            ->orderBy('due');

        if (!empty($labIds)) {
            $query->whereIn('lab_id', $labIds);
        } else {
            return $this->emptyCalendar();
        }

        $eventosMesAtual = $query->get();

        $today = Carbon::today();
        $eventosMesAtual = $eventosMesAtual->map(function ($event) use ($today) {
            $eventDate = Carbon::parse($event->due)->startOfDay();
            $daysUntil = $today->diffInDays($eventDate, false);

            $event->event_day = $eventDate->format('d');
            $event->event_month = $eventDate->translatedFormat('M');
            $event->days_until = $daysUntil;
            $event->is_upcoming = $daysUntil >= 0 && $daysUntil <= 7;
            $event->lab_name = $event->lab?->name ?? 'Todos';

            return $event;
        });

        $eventsByDay = $eventosMesAtual->groupBy(function ($event) {
            return Carbon::parse($event->due)->day;
        });

        $calendarDays = [];
        for ($dia = 1; $dia <= $dataAtual->daysInMonth; $dia++) {
            $date = Carbon::create($year, $month, $dia);
            $events = $eventsByDay->get($dia, collect());
            $primaryColor = $events->first()->color ?? null;

            $calendarDays[] = [
                'day' => $dia,
                'count' => $events->count(),
                'events' => $events->take(3),
                'primaryColor' => $primaryColor,
                'isToday' => $date->isToday(),
                'isPast' => $date->isPast() && !$date->isToday(),
            ];
        }

        return [
            'title' => ucfirst($dataAtual->locale('pt_BR')->translatedFormat('F Y')),
            'year' => $year,
            'month' => $month,
            'daysInMonth' => $dataAtual->daysInMonth,
            'leadingBlanks' => $dataAtual->dayOfWeek,
            'prev' => ['year' => $prev->year, 'month' => $prev->month],
            'next' => ['year' => $next->year, 'month' => $next->month],
            'days' => $calendarDays,
            'events' => $eventosMesAtual,
        ];
    }

    /**
     * Executa a rotina 'buildHeatmap' no fluxo de negocio.
     */
    private function buildHeatmap($collection)
    {
        $anos = $collection->pluck('created_at')
            ->filter()
            ->map(fn($d) => Carbon::parse($d)->year)
            ->unique()
            ->sort()
            ->values();

        $dados = [];

        foreach ($anos as $ano) {
            for ($mes = 1; $mes <= 12; $mes++) {
                $data = Carbon::create($ano, $mes, 1);

                for ($dia = 1; $dia <= $data->daysInMonth; $dia++) {
                    $dados[$ano][$mes]['nome'] = $data->locale('pt_BR')->translatedFormat('F');
                    $dados[$ano][$mes]['dias'][$dia] =
                        $collection->filter(fn($item) =>
                            Carbon::parse($item->created_at)->isSameDay(
                                Carbon::create($ano, $mes, $dia)
                            )
                        )->count();
                }
            }
        }

        return $dados;
    }

    /**
     * Executa a rotina 'getTenantLogs' no fluxo de negocio.
     */
    private function getTenantLogs(int $tenantId)
    {
        $logs = Log::where('tenant_id', $tenantId)
            ->latest()
            ->limit(20)
            ->get();

        if ($logs->isEmpty()) {
            return $logs;
        }

        $ownerIds = $logs->where('user_role', 'owner')->pluck('user_id')->filter()->unique();
        $subRoles = ['teacher', 'assistant', 'asssitant', 'assitant', 'student'];
        $subIds = $logs->whereIn('user_role', $subRoles)->pluck('user_id')->filter()->unique();

        $ownerNames = $ownerIds->isEmpty()
            ? collect()
            : User::whereIn('id', $ownerIds)->pluck('name', 'id');

        $subNames = $subIds->isEmpty()
            ? collect()
            : User::whereIn('id', $subIds)->pluck('name', 'id');

        $roleLabels = [
            'owner' => 'Owner',
            'teacher' => 'Professor',
            'assistant' => 'Assistente',
            'asssitant' => 'Assistente',
            'assitant' => 'Assistente',
            'student' => 'Aluno',
            'guest' => 'Visitante',
        ];

        return $logs->map(function ($log) use ($ownerNames, $subNames, $roleLabels) {
            $role = $log->user_role ?? 'guest';
            $roleLabel = $roleLabels[$role] ?? ucfirst($role);
            $name = $role === 'owner'
                ? ($ownerNames[$log->user_id] ?? null)
                : ($subNames[$log->user_id] ?? null);

            $log->actor_name = $name;
            $log->actor_role_label = $roleLabel;
            $log->actor_label = $name ? $roleLabel . ': ' . $name : $roleLabel;

            return $log;
        });
    }

    /**
     * Executa a rotina 'emptyCalendar' no fluxo de negocio.
     */
    private function emptyCalendar()
    {
        $dataAtual = Carbon::create(now()->year, now()->month, 1);

        return [
            'title' => ucfirst($dataAtual->locale('pt_BR')->translatedFormat('F Y')),
            'year' => $dataAtual->year,
            'month' => $dataAtual->month,
            'daysInMonth' => $dataAtual->daysInMonth,
            'leadingBlanks' => $dataAtual->dayOfWeek,
            'prev' => ['year' => $dataAtual->copy()->subMonth()->year, 'month' => $dataAtual->copy()->subMonth()->month],
            'next' => ['year' => $dataAtual->copy()->addMonth()->year, 'month' => $dataAtual->copy()->addMonth()->month],
            'days' => [],
            'events' => collect(),
        ];
    }

    /**
     * Executa a rotina 'resolveRequestId' no fluxo de negocio.
     */
    private function resolveRequestId($value): int
    {
        if (is_object($value) && isset($value->id)) {
            return (int) $value->id;
        }

        if (is_array($value) && isset($value['id'])) {
            return (int) $value['id'];
        }

        return (int) $value;
    }
}


