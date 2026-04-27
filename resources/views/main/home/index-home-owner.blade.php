
@extends($layout ?? 'layouts.header-side-not')

@section('title', 'Início')

@section('overlays')
@php
    $canCreateEvent = $canCreateEvent ?? true;
    $canInviteStudents = $canInviteStudents ?? true;
@endphp
@if($canCreateEvent)
<div class="event-overlay {{ $errors->getBag('event')->any() ? 'show' : '' }}" id="eventOverlay"></div>
@endif
@if($canInviteStudents)
<div class="event-overlay {{ $errors->getBag('invite')->any() ? 'show' : '' }}" id="studentOverlay"></div>
@endif
@endsection

@section('content')
@php
    $canCreateEvent = $canCreateEvent ?? true;
    $canCreateEventAll = $canCreateEventAll ?? true;
    $canInviteStudents = $canInviteStudents ?? true;
    $activeRelationRole = (string) session('active_relation_role', '');
    $normalizedRelationRole = in_array($activeRelationRole, ['asssitant', 'assitant'], true)
        ? 'assistant'
        : $activeRelationRole;
    $isTenantCreator = isset($tenant) && (int) ($tenant->creator_id ?? 0) === (int) ($user->id ?? 0);
    $fallbackRole = in_array((string) ($user->role ?? ''), ['asssitant', 'assitant'], true)
        ? 'assistant'
        : (string) ($user->role ?? 'student');
    $currentRole = $isTenantCreator
        ? 'owner'
        : ($normalizedRelationRole !== '' ? $normalizedRelationRole : $fallbackRole);
    $isTeacherActor = $currentRole === 'teacher';
    $canManageMemberRoles = in_array($currentRole, ['owner', 'teacher'], true);
    $canExportLogs = in_array($currentRole, ['owner', 'teacher'], true);
    $canDeleteEvent = in_array($currentRole, ['owner', 'teacher'], true);
    $ownerLabs = $labs->whereNull('creator_subuser_id');
    $teacherLabs = $labs->whereNotNull('creator_subuser_id');
    $allGroups = $labs->flatMap(function ($lab) {
        return $lab->groups;
    });
    $allProjects = $allGroups->flatMap(function ($group) {
        return $group->projects;
    });
    $projectVersionCountMap = collect($projectVersionCountMap ?? []);
    $projectCommentCountMap = collect($projectCommentCountMap ?? []);
    $projectStorageMbMap = collect($projectStorageMbMap ?? []);
    $groupVersionCountMap = collect($groupVersionCountMap ?? []);
    $groupStudentCountMap = $students->groupBy('group_id')->map->count();
    $groupContextById = $labs->flatMap(function ($lab) {
        return $lab->groups->mapWithKeys(function ($group) use ($lab) {
            return [
                $group->id => [
                    'lab_id' => $lab->id,
                    'lab_name' => $lab->name,
                    'group_name' => $group->name,
                ],
            ];
        });
    });
    $groupOptionsByLab = $labs->mapWithKeys(function ($lab) {
        return [
            (int) $lab->id => $lab->groups
                ->map(fn ($group) => [
                    'id' => (int) $group->id,
                    'name' => (string) $group->name,
                ])
                ->values()
                ->all(),
        ];
    });
    $inviteOldGroupId = old('group_id');
    $inviteOldContext = $inviteOldGroupId ? ($groupContextById[$inviteOldGroupId] ?? null) : null;

    $projectStatusLabels = [
        'draft' => 'Rascunho',
        'in_progress' => 'Em andamento',
        'approved' => 'Aprovado',
        'rejected' => 'Rejeitado',
        'archived' => 'Arquivado',
        'submitted' => 'Submetido',
    ];
    $groupStatusLabels = [
        'active' => 'Ativo',
        'inactive' => 'Inativo',
        'archived' => 'Arquivado',
    ];
    $labStatusLabels = [
        'draft' => 'Rascunho',
        'active' => 'Ativo',
        'archived' => 'Arquivado',
        'closed' => 'Encerrado',
    ];
@endphp

<div class="container-info owner-dashboard">
    <div class="owner-shell">
        <div class="owner-panel">
            <section class="owner-panel-section" id="owner-panel-dashboard" data-owner-panel="dashboard" data-nav-label="dashboard" data-nav-icon="dashboard" role="tabpanel">
                <div class="owner-dashboard-grid">
                    <div class="cont-boxes-stat" data-animate>
                        <div class="stud-total">
                            <div class="icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2L2 7v10c0 5.55 3.84 10.74 10 12 6.16-1.26 10-6.45 10-12V7l-10-5z"/>
                                </svg>
                            </div>
                            <div class="info">
                                <h3>Total de Alunos</h3>
                                <h4>{{ $students->count() }}/{{ $tenantLimits['users'] }}</h4>
                            </div>
                        </div>
                        <div class="active-proj">
                            <div class="icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M4 4h16v4H4V4zm0 6h16v10H4V10z"/>
                                </svg>
                            </div>
                            <div class="info">
                                <h3>Projetos Ativos</h3>
                                <h4>{{ $active_projs->whereIn('status', 'approved')->count() }}</h4>
                            </div>
                        </div>
                        <div class="today-commit">
                            <div class="icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/>
                                </svg>
                            </div>
                            <div class="info">
                                <h3>Entregas hoje</h3>
                                <h4>{{ $active_projs->whereIn('status', ['draft', 'approved'])->count() }}</h4>
                                <h5>{{ $active_projs->where('status', 'draft')->count() }} pendentes</h5>
                            </div>
                        </div>
                    </div>

                    <div class="heatmap-stud" data-animate>
                        <div class="header-calendar">
                            <h3>Atividade dos Alunos</h3>
                            <select id="period-filter">
                                <option value="3">Ultimos 3 meses</option>
                                <option value="6">Ultimo semestre</option>
                                <option value="12">Ultimo ano</option>
                            </select>
                        </div>
                        <div class="body-calendar">
                            @if(count($dadosPorAnoProj) > 0)
                                @foreach ($dadosPorAnoProj as $ano => $meses)
                                    <div class="year-cont" data-year="{{ $ano }}">
                                        <h3>{{ $ano }}</h3>
                                        @foreach ($meses as $numeroMes => $mes)
                                            <div class="month-cont" data-month="{{ $numeroMes }}">
                                                <span class="month-label">{{ $mes['nome'] }}</span>
                                                @foreach ($mes['dias'] as $dia => $count)
                                                    @php
                                                        $class = '';
                                                        if ($count == 0) {$class = '';} elseif ($count <= 2) {$class = 'level-1';} elseif ($count <= 5) {$class = 'level-2';} elseif ($count <= 10) {$class = 'level-3';} else {$class = 'level-4';}
                                                    @endphp
                                                    <span class="day {{ $class }}"
                                                        data-day="{{ $dia }}"
                                                        data-count="{{ $count }}"
                                                        title="{{ $count }} projeto(s) em {{ $dia }}/{{ $numeroMes }}/{{ $ano }}">
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                            @else
                                <div class="empty-state">
                                    <p>Nenhum dado de projetos disponível</p>
                                </div>
                            @endif
                        </div>
                        <div class="sub-calendar">
                            <div class="heatmap-legend">
                                <span class="legend-label">Menos</span>
                                <span class="legend-cell level-0"></span>
                                <span class="legend-cell level-1"></span>
                                <span class="legend-cell level-2"></span>
                                <span class="legend-cell level-3"></span>
                                <span class="legend-cell level-4"></span>
                                <span class="legend-label">Mais</span>
                            </div>
                            <div class="heatmap-info">
                                <span id="total-projects">Total: <strong>{{ $active_projs->whereIn('status', ['approved', 'draft'])->count() }}</strong> projetos</span>
                            </div>
                        </div>
                    </div>
                    <div class="calendar-logs" data-animate>
                        <div class="event-calendar">
                        <div class="header-calendar">
                            <h3>Calendário Acadêmico</h3>
                            @if($canCreateEvent)
                            <a id="openEventFormBtn">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-plus-lg" viewBox="0 0 16 16">
                                    <path fill-rule="evenodd" d="M8 2a.5.5 0 0 1 .5.5v5h5a.5.5 0 0 1 0 1h-5v5a.5.5 0 0 1-1 0v-5h-5a.5.5 0 0 1 0-1h5v-5A.5.5 0 0 1 8 2"/>
                                </svg>
                            </a>
                            @endif
                        </div>

                            <div class="body-calendar">
                                <div class="body-calendar-header">
                                    <a class="calendar-nav" href="?ano={{ $calendar['prev']['year'] }}&mes={{ $calendar['prev']['month'] }}">&lsaquo;</a>
                                    <span class="calendar-title">{{ $calendar['title'] }}</span>
                                    <a class="calendar-nav" href="?ano={{ $calendar['next']['year'] }}&mes={{ $calendar['next']['month'] }}">&rsaquo;</a>
                                </div>

                                <div class="calendar-weekdays">
                                    <span>Dom</span>
                                    <span>Seg</span>
                                    <span>Ter</span>
                                    <span>Qua</span>
                                    <span>Qui</span>
                                    <span>Sex</span>
                                    <span>Sáb</span>
                                </div>

                                <div class="calendar-days">
                                    @for ($i = 0; $i < $calendar['leadingBlanks']; $i++)
                                        <span class="empty"></span>
                                    @endfor

                                    @foreach ($calendar['days'] as $day)
                                        <span class="calendar-day
                                            {{ $day['count'] > 0 ? 'has-event' : '' }}
                                            {{ $day['isToday'] ? 'today' : '' }}
                                            {{ $day['isPast'] ? 'past' : '' }}"
                                            data-dia="{{ $day['day'] }}"
                                            data-eventos="{{ $day['count'] }}"
                                            @if($day['primaryColor'])
                                                style="--event-color: {{ $day['primaryColor'] }};
                                                    background: linear-gradient(135deg, {{ $day['primaryColor'] }}20 0%, {{ $day['primaryColor'] }}40 100%);"
                                            @endif>

                                            <span class="day-number">{{ $day['day'] }}</span>

                                            @if ($day['count'] > 0)
                                                <div class="event-indicators">
                                                    @foreach ($day['events'] as $event)
                                                        <span class="event-indicator"
                                                            style="background: {{ $event->color }}"
                                                            title="{{ $event->title }}">
                                                        </span>
                                                    @endforeach
                                                    @if ($day['count'] > 3)
                                                        <span class="event-more" style="color: {{ $day['primaryColor'] }}">
                                                            +{{ $day['count'] - 3 }}
                                                        </span>
                                                    @endif
                                                </div>
                                            @endif
                                        </span>
                                    @endforeach
                                </div>
                            </div>

                            <div class="calendar-events-list">
                                <div class="events-list-header">
                                    <h4>Eventos de {{ $calendar['title'] }}</h4>
                                    <span class="event-count">{{ $calendar['events']->count() }}</span>
                                </div>

                                <div class="events-list-body">
                                    @forelse($calendar['events'] as $event)
                                        <div class="event-list-item {{ $event->is_upcoming ? 'upcoming' : '' }}"
                                            style="background: {{ $event->color }}10;">
                                            <div class="event-list-header">
                                                <div class="event-date-badge" style="background: {{ $event->color }}20; color: {{ $event->color }}">
                                                    <span class="day">{{ $event->event_day }}</span>
                                                    <span class="month">{{ $event->event_month }}</span>
                                                </div>
                                                <div class="event-details">
                                                    <h5>{{ $event->title }}</h5>
                                                    <p>{{ Str::limit($event->description, 60) }}</p>
                                                    <div class="event-meta">
                                                        <span class="event-lab">
                                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                                                            </svg>
                                                            {{ $event->lab_name }}
                                                        </span>
                                                        @if($event->is_mandatory)
                                                            <span class="badge-mandatory">Obrigatório</span>
                                                        @endif
                                                        @if($event->is_upcoming)
                                                            <span class="badge-upcoming" style="background: {{ $event->color }}">
                                                                Em {{ $event->days_until }} {{ $event->days_until == 1 ? 'dia' : 'dias' }}
                                                            </span>
                                                        @endif
                                                        @if($canDeleteEvent)
                                                            <form
                                                                action="{{ route('event-destroy', $event->id) }}"
                                                                method="POST"
                                                                class="event-delete-form"
                                                                onsubmit="return confirm('Excluir este evento?');"
                                                            >
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="event-delete-btn">Excluir</button>
                                                            </form>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="empty-state-events">
                                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#ccc" stroke-width="2">
                                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                                <line x1="16" y1="2" x2="16" y2="6"/>
                                                <line x1="8" y1="2" x2="8" y2="6"/>
                                                <line x1="3" y1="10" x2="21" y2="10"/>
                                            </svg>
                                            <p>Nenhum evento neste mês</p>
                                        </div>
                                    @endforelse
                                </div>
                        </div>
                    </div>

                    <div class="stud-logs" data-animate>
                        <div class="log-header">
                            <h3>Logs do Sistema</h3>
                            @if($canExportLogs)
                                <a href="{{ route('logs.export') }}" class="log-export-btn">Exportar Excel</a>
                            @endif
                        </div>
                        <div class="log-cont" id="logCont">
                            @foreach ($logs as $log)
                                <div class="log-line">
                                    <h5>{{ $log->description }}</h5>
                                    <p class="log-meta">
                                        {{ $log->actor_label ?? 'Sistema' }}
                                        @if(!empty($log->created_at))
                                            · {{ $log->created_at->format('d/m/Y H:i') }}
                                        @endif
                                    </p>
                                </div>
                            @endforeach
                        </div>
                        </div>
                    </div>
                </div>
            </section>
            <section class="owner-panel-section" id="owner-panel-labs" data-owner-panel="labs" data-nav-label="laboratorios" data-nav-icon="labs" role="tabpanel" hidden>
                <div class="overview-grid overview-grid-cards" data-animate>
                    @if($ownerLabs->isNotEmpty())
                        <div class="overview-stack">
                            <div class="overview-header">
                                <h3>Laboratórios do owner</h3>
                                <span class="overview-count">
                                    {{ $ownerLabs->count() }}
                                    @if(!empty($tenantLimits['labs']))
                                        /{{ $tenantLimits['labs'] }}
                                    @endif
                                </span>
                            </div>

                            <div class="overview-card-list">
                                @foreach ($ownerLabs as $lab)
                                    @php
                                        $labStatus = 'status-' . str_replace('_', '-', $lab->status);
                                        $labCreatedAt = $lab->created_at?->format('d/m/Y') ?? '-';
                                        $canManageLabActions = $currentRole === 'owner'
                                            || ($currentRole === 'teacher' && (int) ($lab->creator_subuser_id ?? 0) === (int) ($user->id ?? 0));
                                    @endphp
                                    <article class="overview-entity-card overview-entity-card-lab">
                                        <div class="overview-entity-main">
                                            <div>
                                                <h4>{{ $lab->name }}</h4>
                                                <p>{{ $lab->code }}</p>
                                            </div>
                                            <span class="status-badge {{ $labStatus }}">{{ $labStatusLabels[$lab->status] ?? ucfirst($lab->status) }}</span>
                                        </div>

                                        <div class="overview-entity-footer">
                                            <button
                                                type="button"
                                                class="overview-toggle-btn"
                                                data-overview-toggle
                                                data-open-label="ver detalhes"
                                                data-close-label="ocultar detalhes"
                                                aria-expanded="false"
                                                aria-controls="lab-overview-{{ $lab->id }}"
                                            >ver detalhes</button>
                                            <span class="overview-date-meta">criado em: <strong>{{ $labCreatedAt }}</strong></span>
                                        </div>

                                        <div class="overview-entity-expand" id="lab-overview-{{ $lab->id }}" hidden>
                                            <div class="overview-expand-stats">
                                                <div class="overview-expand-stat">
                                                    <span>Grupos</span>
                                                    <strong>{{ $lab->groups->count() }}</strong>
                                                </div>
                                                <div class="overview-expand-stat">
                                                    <span>Projetos</span>
                                                    <strong>{{ $lab->projects->count() }}</strong>
                                                </div>
                                                <div class="overview-expand-stat">
                                                    <span>Alunos</span>
                                                    <strong>{{ $lab->subUsers->where('role', 'student')->count() }}</strong>
                                                </div>
                                            </div>

                                            <div class="overview-expand-actions">
                                                @if($canManageLabActions)
                                                    <button
                                                        type="button"
                                                        class="overview-action-btn overview-action-edit"
                                                        data-overview-edit
                                                        data-rename-type="lab"
                                                        data-rename-id="{{ $lab->id }}"
                                                        data-rename-value="{{ $lab->name }}"
                                                    >Editar</button>
                                                    <button
                                                        type="button"
                                                        class="overview-action-btn overview-action-delete"
                                                        data-overview-delete
                                                        data-rename-type="lab"
                                                        data-rename-id="{{ $lab->id }}"
                                                        data-rename-value="{{ $lab->name }}"
                                                    >Excluir</button>
                                                @endif
                                                <a class="overview-action-btn overview-action-view" href="{{ route('lab.index', $lab->id) }}">Ver</a>
                                            </div>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($teacherLabs->isNotEmpty())
                        <div class="overview-stack">
                            <div class="overview-header">
                                <h3>{{ $ownerLabs->isEmpty() && $teacherLabs->isNotEmpty() ? 'Seus laboratórios' : 'Laboratórios dos professores' }}</h3>
                                <span class="overview-count">
                                    {{ $teacherLabs->count() }}
                                    @if(!empty($tenantLimits['labs']))
                                        /{{ $tenantLimits['labs'] }}
                                    @endif
                                </span>
                            </div>

                            <div class="overview-card-list">
                                @foreach ($teacherLabs as $lab)
                                    @php
                                        $labStatus = 'status-' . str_replace('_', '-', $lab->status);
                                        $labCreatedAt = $lab->created_at?->format('d/m/Y') ?? '-';
                                        $canManageLabActions = $currentRole === 'owner'
                                            || ($currentRole === 'teacher' && (int) ($lab->creator_subuser_id ?? 0) === (int) ($user->id ?? 0));
                                    @endphp
                                    <article class="overview-entity-card overview-entity-card-lab">
                                        <div class="overview-entity-main">
                                            <div>
                                                <h4>{{ $lab->name }}</h4>
                                                <p>{{ $lab->code }}</p>
                                                <span class="overview-mini-meta">Professor: {{ $lab->creatorSubuser?->name ?? 'Professor' }}</span>
                                            </div>
                                            <span class="status-badge {{ $labStatus }}">{{ $labStatusLabels[$lab->status] ?? ucfirst($lab->status) }}</span>
                                        </div>

                                        <div class="overview-entity-footer">
                                            <button
                                                type="button"
                                                class="overview-toggle-btn"
                                                data-overview-toggle
                                                data-open-label="ver detalhes"
                                                data-close-label="ocultar detalhes"
                                                aria-expanded="false"
                                                aria-controls="lab-overview-teacher-{{ $lab->id }}"
                                            >ver detalhes</button>
                                            <span class="overview-date-meta">criado em: <strong>{{ $labCreatedAt }}</strong></span>
                                        </div>

                                        <div class="overview-entity-expand" id="lab-overview-teacher-{{ $lab->id }}" hidden>
                                            <div class="overview-expand-stats">
                                                <div class="overview-expand-stat">
                                                    <span>Grupos</span>
                                                    <strong>{{ $lab->groups->count() }}</strong>
                                                </div>
                                                <div class="overview-expand-stat">
                                                    <span>Projetos</span>
                                                    <strong>{{ $lab->projects->count() }}</strong>
                                                </div>
                                                <div class="overview-expand-stat">
                                                    <span>Alunos</span>
                                                    <strong>{{ $lab->subUsers->where('role', 'student')->count() }}</strong>
                                                </div>
                                            </div>

                                            <div class="overview-expand-actions">
                                                @if($canManageLabActions)
                                                    <button
                                                        type="button"
                                                        class="overview-action-btn overview-action-edit"
                                                        data-overview-edit
                                                        data-rename-type="lab"
                                                        data-rename-id="{{ $lab->id }}"
                                                        data-rename-value="{{ $lab->name }}"
                                                    >Editar</button>
                                                    <button
                                                        type="button"
                                                        class="overview-action-btn overview-action-delete"
                                                        data-overview-delete
                                                        data-rename-type="lab"
                                                        data-rename-id="{{ $lab->id }}"
                                                        data-rename-value="{{ $lab->name }}"
                                                    >Excluir</button>
                                                @endif
                                                <a class="overview-action-btn overview-action-view" href="{{ route('lab.index', $lab->id) }}">Ver</a>
                                            </div>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($ownerLabs->isEmpty() && $teacherLabs->isEmpty())
                        <div class="overview-empty-card">Nenhum laboratório encontrado.</div>
                    @endif
                </div>
            </section>
            <section class="owner-panel-section" id="owner-panel-groups" data-owner-panel="groups" data-nav-label="grupos" data-nav-icon="groups" role="tabpanel" hidden>
                <div class="overview-grid overview-grid-cards" data-animate>
                    <div class="overview-stack">
                        <div class="overview-header">
                            <h3>Visão geral dos grupos</h3>
                            <span class="overview-count">
                                {{ $allGroups->count() }}
                                @if(!empty($tenantLimits['groups']))
                                    /{{ $tenantLimits['groups'] }}
                                @endif
                            </span>
                        </div>

                        @if($allGroups->isEmpty())
                            <div class="overview-empty-card">Nenhum grupo encontrado.</div>
                        @else
                            <div class="overview-card-list">
                                @foreach ($labs as $lab)
                                    @foreach ($lab->groups as $group)
                                        @php
                                            $groupStatus = 'status-' . str_replace('_', '-', $group->status);
                                            $groupStudentsCount = (int) ($groupStudentCountMap[$group->id] ?? 0);
                                            $groupVersionCount = (int) ($groupVersionCountMap[$group->id] ?? 0);
                                            $canManageGroupActions = $currentRole === 'owner'
                                                || ($currentRole === 'teacher' && (int) ($lab->creator_subuser_id ?? 0) === (int) ($user->id ?? 0));
                                        @endphp
                                        <article class="overview-entity-card overview-entity-card-group">
                                            <div class="overview-entity-main">
                                                <div>
                                                    <h4>{{ $group->name }}</h4>
                                                    <p>{{ $group->code }}</p>
                                                    <span class="overview-mini-meta">{{ $lab->name }}</span>
                                                </div>
                                                <span class="status-badge {{ $groupStatus }}">{{ $groupStatusLabels[$group->status] ?? ucfirst($group->status) }}</span>
                                            </div>

                                            <div class="overview-entity-footer">
                                                @if($canInviteStudents)
                                                    <button
                                                        type="button"
                                                        class="overview-invite-btn"
                                                        data-open-student-invite
                                                        data-lab-id="{{ $lab->id }}"
                                                        data-group-id="{{ $group->id }}"
                                                        data-lab-name="{{ $lab->name }}"
                                                        data-group-name="{{ $group->name }}"
                                                    >Gerar link</button>
                                                @endif
                                                <button
                                                    type="button"
                                                    class="overview-toggle-btn"
                                                    data-overview-toggle
                                                    data-open-label="ver detalhes"
                                                    data-close-label="ocultar detalhes"
                                                    aria-expanded="false"
                                                    aria-controls="group-overview-{{ $group->id }}"
                                                >ver detalhes</button>
                                            </div>

                                            <div class="overview-entity-expand" id="group-overview-{{ $group->id }}" hidden>
                                                <p class="overview-path">{{ $lab->name }} &gt; {{ $group->name }}</p>
                                                <div class="overview-expand-stats">
                                                    <div class="overview-expand-stat">
                                                        <span>Projetos</span>
                                                        <strong>{{ $group->projects->count() }}</strong>
                                                    </div>
                                                    <div class="overview-expand-stat">
                                                        <span>Alunos</span>
                                                        <strong>{{ $groupStudentsCount }}</strong>
                                                    </div>
                                                    <div class="overview-expand-stat">
                                                        <span>Versões</span>
                                                        <strong>{{ $groupVersionCount }}</strong>
                                                    </div>
                                                </div>

                                                <div class="overview-expand-actions">
                                                    @if($canManageGroupActions)
                                                        <button
                                                            type="button"
                                                            class="overview-action-btn overview-action-edit"
                                                            data-overview-edit
                                                            data-rename-type="group"
                                                            data-rename-id="{{ $group->id }}"
                                                            data-rename-value="{{ $group->name }}"
                                                        >Editar</button>
                                                        <button
                                                            type="button"
                                                            class="overview-action-btn overview-action-delete"
                                                            data-overview-delete
                                                            data-rename-type="group"
                                                            data-rename-id="{{ $group->id }}"
                                                            data-rename-value="{{ $group->name }}"
                                                            data-lab-id="{{ $lab->id }}"
                                                        >Excluir</button>
                                                    @endif
                                                    <a class="overview-action-btn overview-action-view" href="{{ route('group.index', $group->id) }}">Ver</a>
                                                </div>
                                            </div>
                                        </article>
                                    @endforeach
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </section>
            <section class="owner-panel-section" id="owner-panel-members" data-owner-panel="members" data-nav-label="membros" data-nav-icon="members" role="tabpanel" hidden>
                <div class="students-container" data-animate>
                    <div class="students-header">
                        <div class="students-title">
                            <h3>Alunos</h3>
                            <span class="students-count">{{ $students->count() }}</span>
                        </div>
                    </div>
                    @if($canInviteStudents)
                        <p class="students-invite-hint">O convite agora é feito direto no card do grupo.</p>
                    @endif

                    <div class="students-grid">
                        @forelse ($students as $student)
                            @php
                                $studentRole = in_array((string) ($student->role ?? ''), ['asssitant', 'assitant'], true)
                                    ? 'assistant'
                                    : (string) ($student->role ?? 'student');
                                $studentRoleLabel = match ($studentRole) {
                                    'owner' => 'Owner',
                                    'teacher' => 'Professor',
                                    'assistant' => 'Assistente',
                                    'student' => 'Aluno',
                                    default => ucfirst($studentRole),
                                };
                                $isTeacherTarget = $studentRole === 'teacher';
                                $requiresGroupScope = $studentRole === 'student';
                                $canEditStudentRole = $canManageMemberRoles
                                    && in_array($studentRole, ['teacher', 'assistant', 'student'], true)
                                    && (!$requiresGroupScope || (int) ($student->group_id ?? 0) > 0)
                                    && !($isTeacherActor && $isTeacherTarget);
                                $canRevokeStudentRelation = $canManageMemberRoles
                                    && (int) ($student->id ?? 0) !== (int) ($user->id ?? 0);
                            @endphp
                            <div class="student-card">
                                <div class="student-info-av">
                                @if ($student->profile_photo_path == '')
                                    <div class="student-avatar" style="background: #ffe4b3;">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-person-fill" viewBox="0 0 16 16">
                                            <path d="M3 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6"/>
                                        </svg>
                                    </div>
                                @else
                                    <div class="student-avatar" style="background-image: url('{{ asset('storage/' . $student->profile_photo_path) }}');"></div>
                                @endif
                                <div class="student-info">
                                    <h4>{{ $student->name }}</h4>
                                    <p>{{ $student->email }}</p>
                                    <span class="student-meta">
                                        {{ $student->lab?->name ?? 'Laboratório' }}
                                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" class="bi bi-dot" viewBox="0 0 16 16">
                                            <path d="M8 9.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3"/>
                                        </svg>
                                        {{ $student->group?->name ?? 'Grupo' }}
                                    </span>
                                </div>
                                </div>
                                
                                <div class="member-controls">
                                        @if($canEditStudentRole)
                                            <form
                                                class="member-role-form"
                                                action="{{ route('group-member-role-update') }}"
                                                method="POST"
                                                data-group-options='@json($groupOptionsByLab->get((int) ($student->lab_id ?? 0), []), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)'
                                            >
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="member_id" value="{{ $student->id }}">
                                                <input type="hidden" name="group_id" value="{{ $student->group_id }}">
                                                <input type="hidden" name="lab_id" value="{{ $student->lab_id }}">
                                                <select
                                                    class="member-role-select"
                                                    name="role"
                                                    data-current="{{ $studentRole }}"
                                                    aria-label="Funcao do membro {{ $student->name }}"
                                                >
                                                    <option value="teacher" {{ $studentRole === 'teacher' ? 'selected' : '' }}>Professor</option>
                                                    <option value="assistant" {{ $studentRole === 'assistant' ? 'selected' : '' }}>Assistente</option>
                                                    <option value="student" {{ $studentRole === 'student' ? 'selected' : '' }}>Aluno</option>
                                                </select>
                                            </form>
                                        @else
                                            <span class="status-badge status-{{ $studentRole }}">{{ $studentRoleLabel }}</span>
                                        @endif

                                        @if($canRevokeStudentRelation)
                                            <form class="member-remove-form" action="{{ route('group-member-relation-revoke') }}" method="POST" onsubmit="return confirm('Remover este usuario do tenant?');">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="member_id" value="{{ $student->id }}">
                                                <input type="hidden" name="group_id" value="{{ $student->group_id }}">
                                                <input type="hidden" name="lab_id" value="{{ $student->lab_id }}">
                                                <button type="submit" class="member-remove-btn">Remover</button>
                                            </form>
                                        @endif
                                    </div>
                            </div>
                        @empty
                            <div class="students-empty">
                                <p>Nenhum aluno cadastrado ainda.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </section>
            <section class="owner-panel-section" id="owner-panel-projects" data-owner-panel="projects" data-nav-label="projetos" data-nav-icon="projects" role="tabpanel" hidden>
                <div class="overview-grid overview-grid-cards" data-animate>
                    <div class="overview-stack">
                        <div class="overview-header">
                            <h3>Visão geral dos projetos</h3>
                            <span class="overview-count">
                                {{ $allProjects->count() }}
                                @if(!empty($tenantLimits['projects']))
                                    /{{ $tenantLimits['projects'] }}
                                @endif
                            </span>
                        </div>

                        @if($allProjects->isEmpty())
                            <div class="overview-empty-card">Nenhum projeto encontrado.</div>
                        @else
                            <div class="overview-card-list">
                                @foreach ($labs as $lab)
                                    @foreach ($lab->groups as $group)
                                        @foreach ($group->projects as $project)
                                            @php
                                                $projectStatusValue = $project->status;
                                                $projectStatus = $projectStatusValue === 'submitted'
                                                    ? 'status-in-progress'
                                                    : 'status-' . str_replace('_', '-', $projectStatusValue);
                                                $projectVersionRaw = trim((string) ($project->current_version ?? ''));
                                                $projectVersionLabel = $projectVersionRaw === '' || $projectVersionRaw === '-'
                                                    ? 'Sem versão'
                                                    : (preg_match('/^v/i', $projectVersionRaw) ? $projectVersionRaw : 'v' . $projectVersionRaw);
                                                $projectVersionCount = (int) ($projectVersionCountMap[$project->id] ?? 0);
                                                $projectCommentCount = (int) ($projectCommentCountMap[$project->id] ?? 0);
                                                $projectStorageMb = number_format((float) ($projectStorageMbMap[$project->id] ?? 0), 2, ',', '.');
                                                $canManageProjectActions = $currentRole === 'owner'
                                                    || ($currentRole === 'teacher' && (int) ($lab->creator_subuser_id ?? 0) === (int) ($user->id ?? 0));
                                            @endphp
                                            <article class="overview-entity-card overview-entity-card-project">
                                                <div class="overview-entity-main overview-entity-main-project">
                                                    <div>
                                                        <h4>{{ $project->title }}</h4>
                                                        <p>{{ $lab->name }} &gt; {{ $group->name }}</p>
                                                    </div>
                                                    <div class="overview-project-badges">
                                                        <span class="status-badge {{ $projectStatus }}">{{ $projectStatusLabels[$projectStatusValue] ?? ucfirst(str_replace('_', ' ', $projectStatusValue)) }}</span>
                                                        <span class="code-pill">{{ $projectVersionLabel }}</span>
                                                    </div>
                                                </div>

                                                <div class="overview-entity-footer">
                                                    <button
                                                        type="button"
                                                        class="overview-toggle-btn"
                                                        data-overview-toggle
                                                        data-open-label="ver detalhes"
                                                        data-close-label="ocultar detalhes"
                                                        aria-expanded="false"
                                                        aria-controls="project-overview-{{ $project->id }}"
                                                    >ver detalhes</button>
                                                </div>

                                                <div class="overview-entity-expand" id="project-overview-{{ $project->id }}" hidden>
                                                    <p class="overview-path">{{ $lab->name }} &gt; {{ $group->name }}</p>
                                                    <div class="overview-expand-stats">
                                                        <div class="overview-expand-stat">
                                                            <span>Versões</span>
                                                            <strong>{{ $projectVersionCount }}</strong>
                                                        </div>
                                                        <div class="overview-expand-stat">
                                                            <span>Armazenamento</span>
                                                            <strong>{{ $projectStorageMb }} MB</strong>
                                                        </div>
                                                        <div class="overview-expand-stat">
                                                            <span>Comentários</span>
                                                            <strong>{{ $projectCommentCount }}</strong>
                                                        </div>
                                                    </div>

                                                    <div class="overview-expand-actions">
                                                        @if($canManageProjectActions)
                                                            <button
                                                                type="button"
                                                                class="overview-action-btn overview-action-edit"
                                                                data-overview-edit
                                                                data-rename-type="project"
                                                                data-rename-id="{{ $project->id }}"
                                                                data-rename-value="{{ $project->title }}"
                                                            >Editar</button>
                                                            <button
                                                                type="button"
                                                                class="overview-action-btn overview-action-delete"
                                                                data-overview-delete
                                                                data-rename-type="project"
                                                                data-rename-id="{{ $project->id }}"
                                                                data-rename-value="{{ $project->title }}"
                                                                data-lab-id="{{ $lab->id }}"
                                                            >Excluir</button>
                                                        @endif
                                                        <a class="overview-action-btn overview-action-view" href="{{ route('project.index', $project->id) }}">Ver</a>
                                                    </div>
                                                </div>
                                            </article>
                                        @endforeach
                                    @endforeach
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>
@endsection
@section('modals')
    @if($canCreateEvent)
    <form action="{{ route('event-add') }}" method="POST" id="eventForm" class="{{ $errors->getBag('event')->any() ? 'show' : '' }}">
        @csrf
        <button type="button" class="close-form" id="closeForm">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>

        <div class="info-box">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor">
                <path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10zm0-12H5V6h14v2z"/>
            </svg>
            <h3>Criar Novo Evento</h3>
        </div>

        <div class="body-form">
            @if ($errors->getBag('event')->any())
                <div class="form-alert">
                    <ul>
                        @foreach ($errors->getBag('event')->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="form-group full-width">
                <label for="event-title">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 7h16M4 12h16M4 17h10"/>
                    </svg>
                    Título do Evento
                </label>
                <input
                    id="event-title"
                    name="title"
                    placeholder="Ex: Entrega do Projeto Final"
                    type="text"
                    required
                >
            </div>

            <div class="form-group full-width">
                <label for="event-description">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="16" y1="13" x2="8" y2="13"/>
                        <line x1="16" y1="17" x2="8" y2="17"/>
                        <polyline points="10 9 9 9 8 9"/>
                    </svg>
                    Descrição
                </label>
                <textarea
                    id="event-description"
                    name="description"
                    placeholder="Descreva os detalhes do evento..."
                    rows="3"
                    required
                ></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="event-lab">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                            <polyline points="9 22 9 12 15 12 15 22"/>
                        </svg>
                        Laboratório
                    </label>
                    <select name="lab_id" id="event-lab" required>
                        <option value="" selected disabled>Selecione o laboratório</option>
                        @if($canCreateEventAll)
                        <option value="all">Todos os laboratórios</option>
                        @endif
                        @foreach ($labs as $lab)
                            <option value="{{ $lab->id }}">{{ $lab->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="event-due">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        Data do Evento
                    </label>
                    <input
                        id="event-due"
                        name="due"
                        type="date"
                        min="{{ now()->toDateString() }}"
                        required
                    >
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="event-color">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                        </svg>
                        Cor
                    </label>
                    <input
                        id="event-color"
                        type="color"
                        name="color"
                        value="#ff8c00"
                    >
                </div>

                <div class="form-group">
                    <label for="event-mandatory">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        </svg>
                        Obrigatório?
                    </label>
                    <div class="checkbox-field">
                        <input type="hidden" name="is_mandatory" value="0">
                        <input id="event-mandatory" type="checkbox" name="is_mandatory" value="1">
                        <span>Sim</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-submit">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                Criar Evento
            </button>
        </div>
    </form>
    @endif

    @if($canInviteStudents)
    <form action="{{ route('subuser-invite') }}" method="POST" id="studentInviteForm" class="{{ $errors->getBag('invite')->any() ? 'show' : '' }}">
        @csrf
        <button type="button" class="close-form" id="closeStudentInviteForm">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>

        <div class="info-box">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
            </svg>
            <h3>Gerar link de convite do grupo</h3>
        </div>

        <div class="body-form">
            @if ($errors->getBag('invite')->any())
                <div class="form-alert">
                    <ul>
                        @foreach ($errors->getBag('invite')->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <input type="hidden" name="lab_id" id="student-invite-lab" value="{{ old('lab_id') }}">
            <input type="hidden" name="group_id" id="student-invite-group" value="{{ old('group_id') }}">

            <div class="invite-context-card">
                <p class="invite-context-label">Grupo selecionado</p>
                <strong id="student-invite-group-name">{{ $inviteOldContext['group_name'] ?? 'Selecione um grupo no card' }}</strong>
                <span id="student-invite-lab-name">{{ $inviteOldContext['lab_name'] ?? 'Laboratório não definido' }}</span>
            </div>

            <p class="form-hint">O sistema gera um link com expiracao de 24 horas para compartilhar com os alunos.</p>

            @php
                $inviteLink = (string) session('invite_link', '');
                $inviteLabel = (string) session('invite_label', 'Abrir convite');
                $inviteExpiresAt = (string) session('invite_expires_at', '');
                $inviteGroupName = (string) session('invite_group_name', '');
                $inviteLabName = (string) session('invite_lab_name', '');
            @endphp
            <div class="invite-result-card" id="inviteResultCard" @if($inviteLink === '') hidden @endif>
                <p class="invite-result-label">Ultimo link de convite gerado</p>
                <div class="invite-result-row">
                    <input
                        type="text"
                        id="invite-result-url"
                        class="invite-result-input"
                        value="{{ $inviteLink }}"
                        readonly
                    >
                    <button type="button" class="btn-cancel invite-copy-btn" id="inviteCopyBtn">Copiar</button>
                </div>
                <div class="invite-result-links">
                    <a
                        href="{{ $inviteLink !== '' ? $inviteLink : '#' }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="invite-link-output"
                        id="invite-result-open"
                    >{{ $inviteLabel }}</a>
                    <span class="invite-link-raw" id="invite-result-raw">{{ $inviteLink }}</span>
                </div>
                <p class="invite-result-meta" id="invite-result-meta">
                    @if($inviteExpiresAt !== '')
                        Expira em {{ $inviteExpiresAt }}
                    @endif
                    @if($inviteGroupName !== '')
                        <span id="invite-result-group"> | Grupo: {{ $inviteGroupName }}</span>
                    @endif
                    @if($inviteLabName !== '')
                        <span id="invite-result-lab"> | Lab: {{ $inviteLabName }}</span>
                    @endif
                </p>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-submit" data-invite-action="generate" data-submitting-label="Gerando link...">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                Gerar link
            </button>
            <button
                type="submit"
                class="btn-submit btn-submit-danger"
                data-invite-action="revoke"
                data-submitting-label="Revogando links..."
                formaction="{{ route('subuser-invite-revoke-active') }}"
                formmethod="POST"
                onclick="return confirm('Revogar todos os links ativos deste grupo?');"
            >
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
                Revogar links ativos
            </button>
        </div>
    </form>
    @endif
@endsection


