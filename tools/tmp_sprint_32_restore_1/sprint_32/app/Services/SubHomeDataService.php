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
use App\Models\SubUsers;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

class SubHomeDataService{

    public function buildStudent($student){
        $tenantId = $student->tenant_id ?: Lab::where('id', $student->lab_id)
            ->value('tenant_id');

        $groups = $this->getGroups($student);
        $group = $groups->first();
        $lab = $group?->lab;

        $projects = $group?->projects?->sortByDesc('created_at')->values() ?? collect();
        $selectedProjectId = (int) request('project', 0);
        $project = $selectedProjectId ? $projects->firstWhere('id', $selectedProjectId) : $projects->first();

        $versions = collect();
        $projectFiles = collect();
        $versionComments = collect();
        $calendar = $this->emptyCalendar();

        if ($tenantId && $project) {
            $versions = ProjectVersion::where('tenant_id', $tenantId)
                ->where('project_id', $project->id)
                ->orderBy('version_number', 'asc')
                ->get();

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
            'projectFiles' => $projectFiles,
            'versionComments' => $versionComments,
            'calendar' => $calendar,
            'maxUploadMb' => $maxUploadMb,
            'notifications' => Notification::where('user_id', $student->id)->where('table', 'subusers')->orderBy('created_at', 'desc')->get(),
            'userPreferences' => SubUsers::where('id', $student->id)->value('preferences'),
            'pageTitle' => 'Versionamento',
            'pageBreadcrumbHome' => 'Inicio',
            'pageBreadcrumbCurrent' => 'Versoes',
            'canCreateLab' => false,
            'canCreateGroup' => false,
            'canCreateProject' => true,
            'canCreateEvent' => false,
            'canCreateEventAll' => false,
            'canInviteStudents' => false,
            'canEditLabStatus' => false,
            'canEditGroupStatus' => false,
            'canEditProjectStatus' => false,
        ];
    }

    public function buildAssistant($assistant): array
    {
        $tenantId = $assistant->tenant_id ?: Lab::where('id', $assistant->lab_id)
            ->value('tenant_id');

        $lab = $assistant->lab_id
            ? Lab::where('id', $assistant->lab_id)->first()
            : null;

        $groups = $assistant->lab_id
            ? Group::with('projects')
                ->where('lab_id', $assistant->lab_id)
                ->orderBy('name')
                ->get()
            : collect();

        $selectedGroupId = (int) request('group', 0);
        $group = $selectedGroupId
            ? $groups->firstWhere('id', $selectedGroupId)
            : $groups->first();

        $projects = $group?->projects?->sortByDesc('created_at')->values() ?? collect();
        $selectedProjectId = (int) request('project', 0);
        $project = $selectedProjectId ? $projects->firstWhere('id', $selectedProjectId) : $projects->first();

        $versions = collect();
        $projectFiles = collect();
        $versionComments = collect();
        $calendar = $this->emptyCalendar();

        if ($tenantId && $project) {
            $versions = ProjectVersion::where('tenant_id', $tenantId)
                ->where('project_id', $project->id)
                ->orderBy('version_number', 'asc')
                ->get();

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
            'projectFiles' => $projectFiles,
            'versionComments' => $versionComments,
            'calendar' => $calendar,
            'maxUploadMb' => $maxUploadMb,
            'notifications' => Notification::where('user_id', $assistant->id)->where('table', 'subusers')->orderBy('created_at', 'desc')->get(),
            'userPreferences' => SubUsers::where('id', $assistant->id)->value('preferences'),
            'pageTitle' => 'Revisao',
            'pageBreadcrumbHome' => 'Inicio',
            'pageBreadcrumbCurrent' => 'Revisao',
            'canCreateLab' => false,
            'canCreateGroup' => false,
            'canCreateProject' => false,
            'canCreateEvent' => false,
            'canCreateEventAll' => false,
            'canInviteStudents' => false,
            'canEditLabStatus' => false,
            'canEditGroupStatus' => false,
            'canEditProjectStatus' => false,
        ];
    }

    public function buildTeacher($teacher): array
    {
        $tenantId = $teacher->tenant_id ?: Lab::where('id', $teacher->lab_id)
            ->value('tenant_id');

        $tenant = $tenantId ? Tenant::where('id', $tenantId)->first() : null;

        $labs = $tenantId
            ? Lab::with(['groups.projects', 'subUsers', 'creatorSubuser'])
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
            ? SubUsers::with(['lab', 'group'])
                ->where('tenant_id', $tenantId)
                ->whereIn('lab_id', $labIds)
                ->get()
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
                'subusers' => $tenant->limitFor('users'),
            ]
            : [
                'labs' => null,
                'groups' => null,
                'projects' => null,
                'storage' => null,
                'subusers' => null,
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
                'subusers' => $students->count(),
                'storage' => $tenant?->storage_used_mb,
            ],
            'students' => $students,
            'labs' => $labs,
            'active_projs' => $projects,
            'dadosPorAnoProj' => $dadosPorAnoProj,
            'logs' => collect(),
            'events' => collect(),
            'dadosPorAnoEvent' => [],
            'eventosProximos' => collect(),
            'calendar' => $calendar,
            'notifications' => Notification::where('user_id', $teacher->id)
                ->where('table', 'subusers')
                ->orderBy('created_at', 'desc')
                ->get(),
            'userPreferences' => SubUsers::where('id', $teacher->id)->value('preferences'),
            'pageTitle' => 'Painel',
            'pageBreadcrumbHome' => 'Inicio',
            'pageBreadcrumbCurrent' => 'Visao geral',
            'layout' => 'layouts.header-side-not-sub',
            'canCreateLab' => true,
            'canCreateGroup' => true,
            'canCreateProject' => false,
            'canCreateEvent' => true,
            'canCreateEventAll' => false,
            'canInviteStudents' => true,
            'canEditLabStatus' => true,
            'canEditGroupStatus' => true,
            'canEditProjectStatus' => true,
        ];
    }

    private function getGroups($student){
        return Group::with('lab', 'projects', 'projects_versions')
        ->where('id', $student->group_id)
        ->orderBy('created_at', 'desc')
        ->get();
    }

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
}
