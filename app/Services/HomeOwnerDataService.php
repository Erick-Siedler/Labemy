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
use App\Models\Tenant;
use App\Models\Task;
use App\Models\User;
use App\Models\UserRelation;
use Carbon\Carbon;
use Illuminate\Support\Str;

class HomeOwnerDataService
{
    /**
     * Executa a rotina 'build' no fluxo de negocio.
     */
    public function build($user): array
    {
        $tenant = $this->resolveTenantForUser($user);

        if (!$tenant) {
            return [
                'user' => $user,
                'tenant' => null,
                'tenantLimits' => [
                    'labs' => null,
                    'groups' => null,
                    'projects' => null,
                ],
                'tenantUsage' => [
                    'labs' => 0,
                    'groups' => 0,
                    'projects' => 0,
                ],
                'students' => collect(),
                'labs' => collect(),
                'projectVersionCountMap' => [],
                'projectCommentCountMap' => [],
                'projectStorageMbMap' => [],
                'groupVersionCountMap' => [],
                'active_projs' => collect(),
                'dadosPorAnoProj' => [],
                'logs' => collect(),
                'events' => collect(),
                'dadosPorAnoEvent' => [],
                'eventosProximos' => collect(),
                'notifications' => $this->getNots((int) $user->id, 'users'),
                'calendar' => $this->emptyCalendar(),
            ];
        }

        $tenant_id = $tenant->id;
        $calendar = $this->buildEventCalendar(
            $tenant_id,
            (int) request('ano', now()->year),
            (int) request('mes', now()->month)
        );

        $labs = $this->getLabs($tenant_id);
        $projectMetrics = $this->buildProjectMetrics($labs);

        return [
            'user'              => $user,
            'tenant'            => $tenant,
            'tenantLimits'      =>  $this->getTenantLimits($tenant),
            'userPreferences'   => $this->getUserPreferences($user),
            'tenantUsage'       => $this->getTenantUsage($tenant_id),
            'students'          => $this->getStudents($tenant_id),
            'labs'              => $labs,
            'projectVersionCountMap' => $projectMetrics['projectVersionCountMap'],
            'projectCommentCountMap' => $projectMetrics['projectCommentCountMap'],
            'projectStorageMbMap' => $projectMetrics['projectStorageMbMap'],
            'groupVersionCountMap' => $projectMetrics['groupVersionCountMap'],
            'active_projs'      => $this->getActiveProjects($tenant_id),
            'dadosPorAnoProj'   => $this->getProjectHeatmap($tenant_id),
            'logs'              => $this->getLogs($tenant_id),
            'events'            => $this->getEvents($tenant_id),
            'dadosPorAnoEvent'  => $this->getEventHeatmap($tenant_id),
            'eventosProximos'   => $this->getUpcomingEvents($tenant_id),
            'notifications'     => $this->getNots((int) $user->id, 'users'),
            'calendar'          => $calendar,
        ];
    }

    /**
     * Executa a rotina 'buildSolo' no fluxo de negocio.
     */
    public function buildSolo($user): array
    {
        $tenant = Tenant::where('creator_id', $user->id)->first();
        $payment = Payment::where('user_id', $user->id)
            ->where('status', 'paid')
            ->latest()
            ->first();

        $hasSoloTrial = !$payment && $user?->plan === 'solo';

        if (!$tenant && ($payment || $hasSoloTrial)) {
            $baseSlug = Str::slug($user->name ?: 'solo');
            $slug = $baseSlug;
            $suffix = 1;

            while (Tenant::where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $suffix;
                $suffix++;
            }

            $tenant = Tenant::create([
                'creator_id' => $user->id,
                'name' => 'Solo ' . ($user->name ?: 'Usuario'),
                'slug' => $slug,
                'type' => 'other',
                'status' => 'active',
                'plan' => $payment?->plan ?? 'solo',
                'trial_ends_at' => $payment ? null : Carbon::now()->addDays(7),
                'settings' => [
                    'max_labs' => 1,
                    'max_groups' => 1,
                    'max_projects' => 100,
                    'max_users' => 1,
                    'max_storage_mb' => 1000,
                ],
                'storage_used_mb' => 0,
            ]);
        }

        $lab = null;
        $group = null;

        if ($tenant) {
            $lab = Lab::where('tenant_id', $tenant->id)
                ->orderBy('id')
                ->first();

            if (!$lab) {
                $labName = 'Projetos';
                $lab = Lab::create([
                    'tenant_id' => $tenant->id,
                    'creator_id' => $user->id,
                    'name' => $labName,
                    'code' => Str::replace(' ', '-', $labName),
                    'status' => 'active',
                ]);
            }

            $group = Group::where('tenant_id', $tenant->id)
                ->where('lab_id', $lab->id)
                ->orderBy('id')
                ->first();

            if (!$group) {
                $groupName = 'Geral';
                $group = Group::create([
                    'tenant_id' => $tenant->id,
                    'lab_id' => $lab->id,
                    'creator_id' => $user->id,
                    'name' => $groupName,
                    'code' => Str::replace(' ', '-', $groupName),
                    'status' => 'active',
                ]);
            }
        }

        $projects = $tenant
            ? Project::with(['subfolders', 'lab', 'group'])
                ->where('tenant_id', $tenant->id)
                ->latest()
                ->get()
            : collect();

        $selectedProjectId = $this->resolveRequestId(
            request()->route('project', request('project', 0))
        );
        $selectedProject = $selectedProjectId
            ? ($projects->firstWhere('id', $selectedProjectId) ?: $projects->first())
            : $projects->first();

        $versions = collect();
        $latestVersion = null;
        $projectFiles = collect();
        $projectFilesCount = 0;
        $projectStorageMb = 0;
        $versionStats = [
            'draft' => 0,
            'submitted' => 0,
            'approved' => 0,
            'rejected' => 0,
        ];

        if ($tenant && $selectedProject) {
            $versions = ProjectVersion::where('tenant_id', $tenant->id)
                ->where('project_id', $selectedProject->id)
                ->orderBy('version_number', 'desc')
                ->get();

            $latestVersion = $versions->first();
            $versionStats = [
                'draft' => $versions->where('status_version', 'draft')->count(),
                'submitted' => $versions->where('status_version', 'submitted')->count(),
                'approved' => $versions->where('status_version', 'approved')->count(),
                'rejected' => $versions->where('status_version', 'rejected')->count(),
            ];

            $versionIds = $versions->pluck('id');
            $projectFiles = $versionIds->isEmpty()
                ? collect()
                : ProjectFile::where('tenant_id', $tenant->id)
                    ->whereIn('project_versions_id', $versionIds)
                    ->get();

            $projectFilesCount = $projectFiles->count();
            $projectStorageBytes = (int) $projectFiles->sum('size');
            $projectStorageMb = round($projectStorageBytes / 1048576, 2);
        }

        $remainingStorageMb = 0;
        if ($tenant) {
            $usedBytes = (int) ($tenant->storage_used_mb ?? 0);
            $usedMb = $usedBytes / 1048576;
            $maxStorageMb = (float) ($tenant->limitFor('storage') ?? $tenant->max_storage_mb ?? 0);
            $remainingStorageMb = max(0, $maxStorageMb - $usedMb);
        }

        return [
            'user' => $user,
            'tenant' => $tenant,
            'labs' => collect(),
            'notifications' => collect(),
            'userPreferences' => $this->getUserPreferences($user),
            'projects' => $projects,
            'project' => $selectedProject,
            'versions' => $versions,
            'versionFlowRecentLimit' => 6,
            'latestVersion' => $latestVersion,
            'projectFiles' => $projectFiles,
            'projectFilesCount' => $projectFilesCount,
            'projectStorageMb' => $projectStorageMb,
            'versionStats' => $versionStats,
            'maxUploadMb' => $remainingStorageMb,
            'soloLabId' => $lab?->id,
            'soloGroupId' => $group?->id,
            'tasks' => $this->getTasks($tenant->id),
        ];
    }

    private function getTasks($tenant_id){
        return Task::with('version')
        ->where('tenant_id', $tenant_id)
        ->orderBy('created_at', 'desc')
        ->get();

    }

    /**
     * Executa a rotina 'getStudents' no fluxo de negocio.
     */
    private function getStudents($tenant_id)
    {
        return UserRelation::with(['user', 'lab', 'group'])
            ->where('tenant_id', $tenant_id)
            ->where('status', 'active')
            ->whereIn('role', ['teacher', 'assistant', 'asssitant', 'assitant', 'student'])
            ->latest()
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
            ->values();
    }

    /**
     * Executa a rotina 'getLabs' no fluxo de negocio.
     */
    private function getLabs($tenant_id)
    {
        return Lab::with(['groups.projects.subfolders', 'creatorSubuser', 'subUsers'])
            ->where('tenant_id', $tenant_id)
            ->orderBy('name')
            ->get();
    }

    /**
     * Agrega metricas de versoes, armazenamento e comentarios por projeto/grupo.
     */
    private function buildProjectMetrics($labs): array
    {
        $projectIds = $labs
            ->flatMap(fn ($lab) => $lab->groups->flatMap(fn ($group) => $group->projects->pluck('id')))
            ->filter()
            ->unique()
            ->values();

        if ($projectIds->isEmpty()) {
            return [
                'projectVersionCountMap' => [],
                'projectCommentCountMap' => [],
                'projectStorageMbMap' => [],
                'groupVersionCountMap' => [],
            ];
        }

        $versions = ProjectVersion::whereIn('project_id', $projectIds)
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

        $groupVersionCountMap = $labs
            ->flatMap(fn ($lab) => $lab->groups)
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
     * Executa a rotina 'getUserPreferences' no fluxo de negocio.
     */
    private function getUserPreferences($user)
    {
        return User::where('id', $user->id)
                ->value('preferences');
    }

    /**
     * Executa a rotina 'getTenantLimits' no fluxo de negocio.
     */
    private function getTenantLimits($tenant)
    {
        return [
            'labs' => $tenant->limitFor('labs'),
            'groups' => $tenant->limitFor('groups'),
            'projects' => $tenant->limitFor('projects'),
            'storage' => $tenant->limitFor('storage'),
            'users' => $tenant->limitFor('users'),
        ];
    }

    /**
     * Executa a rotina 'getTenantUsage' no fluxo de negocio.
     */
    private function getTenantUsage($tenant_id): array
    {
        $relatedUsersCount = UserRelation::where('tenant_id', $tenant_id)
            ->where('status', 'active')
            ->distinct()
            ->count('user_id');

        return [
            'labs' => Lab::where('tenant_id', $tenant_id)->count(),
            'groups' => Group::where('tenant_id', $tenant_id)->count(),
            'projects' => Project::where('tenant_id', $tenant_id)->count(),
            'users' => $relatedUsersCount,
            'storage' => Tenant::where('id', $tenant_id)->value('storage_used_mb'),
        ];
    }

    /**
     * Executa a rotina 'getActiveProjects' no fluxo de negocio.
     */
    private function getActiveProjects($tenant_id)
    {
        return Project::where('tenant_id', $tenant_id)
            ->latest()
            ->get();
    }

    /**
     * Executa a rotina 'buildHeatmap' no fluxo de negocio.
     */
    private function buildHeatmap($collection)
    {
        $anos = $collection->pluck('created_at')
            ->map(fn ($d) => Carbon::parse($d)->year)
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
                        $collection->filter(fn ($item) =>
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
     * Executa a rotina 'getProjectHeatmap' no fluxo de negocio.
     */
    private function getProjectHeatmap($tenant_id)
    {
        $projects = Project::where('tenant_id', $tenant_id)
            ->whereIn('status', ['approved', 'draft'])
            ->get();

        return $this->buildHeatmap($projects);
    }

    /**
     * Executa a rotina 'getLabProjectHeatmap' no fluxo de negocio.
     */
    public function getLabProjectHeatmap($tenant_id, $lab_id)
    {
        $projects = Project::where('tenant_id', $tenant_id)
            ->where('lab_id', $lab_id)
            ->whereIn('status', ['approved', 'draft'])
            ->get();

        return $this->buildHeatmap($projects);
    }

    /**
     * Executa a rotina 'getEventHeatmap' no fluxo de negocio.
     */
    private function getEventHeatmap($tenant_id)
    {
        $events = Event::where('tenant_id', $tenant_id)
            ->whereDate('due', '>=', now()->subMonth())
            ->get();

        return $this->buildHeatmap($events);
    }

    /**
     * Executa a rotina 'getUpcomingEvents' no fluxo de negocio.
     */
    private function getUpcomingEvents($tenant_id)
    {
        return Event::where('tenant_id', $tenant_id)
            ->whereBetween('due', [now(), now()->addDays(7)])
            ->orderBy('due')
            ->get();
    }

    /**
     * Monta calendario mensal de eventos para um laboratorio especifico.
     */
    public function buildLabCalendar(int $tenant_id, int $lab_id, int $year, int $month): array
    {
        return $this->buildEventCalendar($tenant_id, $year, $month, $lab_id);
    }

    /**
     * Executa a rotina 'buildEventCalendar' no fluxo de negocio.
     */
    private function buildEventCalendar($tenant_id, $year, $month, ?int $labId = null)
    {
        $year = max(1, $year);
        $month = min(max(1, $month), 12);

        $dataAtual = Carbon::create($year, $month, 1);
        $prev = $dataAtual->copy()->subMonth();
        $next = $dataAtual->copy()->addMonth();

        $query = Event::with('lab')
            ->where('tenant_id', $tenant_id)
            ->whereYear('due', $year)
            ->whereMonth('due', $month)
            ->orderBy('due');

        if (!is_null($labId)) {
            $query->where('lab_id', $labId);
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
     * Executa a rotina 'getLogs' no fluxo de negocio.
     */
    private function getLogs($tenant_id)
    {
        $logs = Log::where('tenant_id', $tenant_id)
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
     * Executa a rotina 'getEvents' no fluxo de negocio.
     */
    private function getEvents($tenant_id)
    {
        return Event::where('tenant_id', $tenant_id)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Resolve tenant usando sessao ativa e vinculos do usuario.
     */
    private function resolveTenantForUser($user): ?Tenant
    {
        $selectedTenantId = (int) session('active_tenant_id', 0);
        if ($selectedTenantId > 0) {
            $ownsTenant = Tenant::where('id', $selectedTenantId)
                ->where('creator_id', $user->id)
                ->exists();

            $hasRelation = UserRelation::where('user_id', $user->id)
                ->where('tenant_id', $selectedTenantId)
                ->where('status', 'active')
                ->exists();

            if ($ownsTenant || $hasRelation) {
                return Tenant::where('id', $selectedTenantId)->first();
            }
        }

        $ownedTenant = Tenant::where('creator_id', $user->id)->first();
        if ($ownedTenant) {
            return $ownedTenant;
        }

        $relatedTenantId = UserRelation::where('user_id', $user->id)
            ->where('status', 'active')
            ->value('tenant_id');

        return $relatedTenantId ? Tenant::where('id', (int) $relatedTenantId)->first() : null;
    }

    /**
     * Executa a rotina 'resolveRequestId' no fluxo de negocio.
     */
    private function resolveRequestId($value): int
    {
        if (is_object($value) && isset($value->id) && is_numeric($value->id)) {
            return (int) $value->id;
        }

        if (is_array($value) && isset($value['id']) && is_numeric($value['id'])) {
            return (int) $value['id'];
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return 0;
    }

    /**
     * Executa a rotina 'getNots' no fluxo de negocio.
     */
    private function getNots(int $user_id, string $table = 'users')
    {
        return Notification::where('user_id', $user_id)
            ->where('table', $table)
            ->orderBy('created_at', 'desc')
            ->get();
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
}

