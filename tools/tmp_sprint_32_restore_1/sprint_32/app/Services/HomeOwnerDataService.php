<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Group;
use App\Models\Lab;
use App\Models\Log;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectVersion;
use App\Models\SubUsers;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

class HomeOwnerDataService
{
    public function build($user): array
    {
        $tenant = Tenant::where('creator_id', $user->id)->first();

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
                'active_projs' => collect(),
                'dadosPorAnoProj' => [],
                'logs' => collect(),
                'events' => collect(),
                'dadosPorAnoEvent' => [],
                'eventosProximos' => collect(),
                'notifications' => $this->getNots($user->id),
                'calendar' => $this->emptyCalendar(),
            ];
        }

        $tenant_id = $tenant->id;
        $calendar = $this->buildEventCalendar(
            $tenant_id,
            (int) request('ano', now()->year),
            (int) request('mes', now()->month)
        );

        return [
            'user'              => $user,
            'tenant'            => $tenant,
            'tenantLimits'      =>  $this->getTenantLimits($tenant),
            'userPreferences'   => $this->getUserPreferences($user),
            'tenantUsage'       => $this->getTenantUsage($tenant_id),
            'students'          => $this->getStudents($tenant_id),
            'labs'              => $this->getLabs($tenant_id),
            'active_projs'      => $this->getActiveProjects($tenant_id),
            'dadosPorAnoProj'   => $this->getProjectHeatmap($tenant_id),
            'logs'              => $this->getLogs($tenant_id),
            'events'            => $this->getEvents($tenant_id),
            'dadosPorAnoEvent'  => $this->getEventHeatmap($tenant_id),
            'eventosProximos'   => $this->getUpcomingEvents($tenant_id),
            'notifications'     => $this->getNots($user->id),
            'calendar'          => $calendar,
        ];
    }

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
            ? Project::where('tenant_id', $tenant->id)->latest()->get()
            : collect();

        $selectedProjectId = (int) request('project', 0);
        $selectedProject = $selectedProjectId
            ? $projects->firstWhere('id', $selectedProjectId)
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
            'notifications' => $this->getNots($user->id),
            'userPreferences' => $this->getUserPreferences($user),
            'projects' => $projects,
            'project' => $selectedProject,
            'versions' => $versions,
            'latestVersion' => $latestVersion,
            'projectFiles' => $projectFiles,
            'projectFilesCount' => $projectFilesCount,
            'projectStorageMb' => $projectStorageMb,
            'versionStats' => $versionStats,
            'maxUploadMb' => $remainingStorageMb,
            'soloLabId' => $lab?->id,
            'soloGroupId' => $group?->id,
        ];
    }

    private function getStudents($tenant_id)
    {
        return SubUsers::with(['lab', 'group'])
            ->where('tenant_id', $tenant_id)
            ->latest()
            ->get();
    }

    private function getLabs($tenant_id)
    {
        return Lab::with(['groups.projects', 'creatorSubuser'])
            ->where('tenant_id', $tenant_id)
            ->orderBy('name')
            ->get();
    }

    private function getUserPreferences($user)
    {
        return User::where('id', $user->id)
                ->value('preferences');
    }

    private function getTenantLimits($tenant)
    {
        return [
            'labs' => $tenant->limitFor('labs'),
            'groups' => $tenant->limitFor('groups'),
            'projects' => $tenant->limitFor('projects'),
            'storage' => $tenant->limitFor('storage'),
            'subusers' => $tenant->limitFor('users'),
        ];
    }

    private function getTenantUsage($tenant_id): array
    {
        return [
            'labs' => Lab::where('tenant_id', $tenant_id)->count(),
            'groups' => Group::where('tenant_id', $tenant_id)->count(),
            'projects' => Project::where('tenant_id', $tenant_id)->count(),
            'subusers' => SubUsers::where('tenant_id', $tenant_id)->count(),
            'storage' => Tenant::where('id', $tenant_id)->value('storage_used_mb'),
        ];
    }

    private function getActiveProjects($tenant_id)
    {
        return Project::where('tenant_id', $tenant_id)
            ->latest()
            ->get();
    }

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

    private function getProjectHeatmap($tenant_id)
    {
        $projects = Project::where('tenant_id', $tenant_id)
            ->whereIn('status', ['approved', 'submitted'])
            ->get();

        return $this->buildHeatmap($projects);
    }

    public function getLabProjectHeatmap($tenant_id, $lab_id)
    {
        $projects = Project::where('tenant_id', $tenant_id)
            ->where('lab_id', $lab_id)
            ->whereIn('status', ['approved', 'submitted'])
            ->get();

        return $this->buildHeatmap($projects);
    }

    private function getEventHeatmap($tenant_id)
    {
        $events = Event::where('tenant_id', $tenant_id)
            ->whereDate('due', '>=', now()->subMonth())
            ->get();

        return $this->buildHeatmap($events);
    }

    private function getUpcomingEvents($tenant_id)
    {
        return Event::where('tenant_id', $tenant_id)
            ->whereBetween('due', [now(), now()->addDays(7)])
            ->orderBy('due')
            ->get();
    }

    private function buildEventCalendar($tenant_id, $year, $month)
    {
        $year = max(1, $year);
        $month = min(max(1, $month), 12);

        $dataAtual = Carbon::create($year, $month, 1);
        $prev = $dataAtual->copy()->subMonth();
        $next = $dataAtual->copy()->addMonth();

        $eventosMesAtual = Event::with('lab')
            ->where('tenant_id', $tenant_id)
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
            : SubUsers::whereIn('id', $subIds)->pluck('name', 'id');

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

    private function getEvents($tenant_id)
    {
        return Event::where('tenant_id', $tenant_id)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    private function getNots($user_id)
    {
        return Notification::where('user_id', $user_id)
            ->orderBy('created_at', 'desc')
            ->get();
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
