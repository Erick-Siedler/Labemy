@extends($layout ?? 'layouts.header-side-not')

@section('title', 'Laboratório')

@if($theme === '"light"' || $theme === '"automatic"')
@push('styles')
<link rel="stylesheet" href="{{ asset('main/lab.css') }}">
@endpush
@else
@push('styles')
<link rel="stylesheet" href="{{ asset('main/lab-dark.css') }}">
@endpush
@endif

@section('overlays')
@if($canCreateEvent ?? true)
<div class="event-overlay {{ $errors->getBag('event')->any() ? 'show' : '' }}" id="eventOverlay"></div>
@endif
@endsection

@section('content')
@php
    $canCreateEvent = $canCreateEvent ?? true;
    $groupStatusLabels = [
        'active' => 'Ativo',
        'inactive' => 'Inativo',
        'archived' => 'Arquivado',
    ];
    $projectStatusLabels = [
        'draft' => 'Rascunho',
        'in_progress' => 'Em andamento',
        'approved' => 'Aprovado',
        'rejected' => 'Rejeitado',
        'archived' => 'Arquivado',
        'submitted' => 'Submetido',
    ];
    $canEditGroupActions = $canEditGroupStatus ?? true;
    $canEditProjectActions = $canEditProjectStatus ?? true;
    $canManageMembers = $canEditGroupStatus ?? true;
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
    $canDeleteEvent = in_array($currentRole, ['owner', 'teacher'], true);
    $groupVersionCountMap = collect($groupVersionCountMap ?? []);
    $groupStudentCountMap = collect($groupStudentCountMap ?? []);
    $projectVersionCountMap = collect($projectVersionCountMap ?? []);
    $projectCommentCountMap = collect($projectCommentCountMap ?? []);
    $projectStorageMbMap = collect($projectStorageMbMap ?? []);
    $labMembers = ($lab->subUsers ?? collect())->sortBy('name')->values();
    $groupNamesById = $lab->groups->pluck('name', 'id');
    $labGroupOptions = $lab->groups
        ->map(fn ($group) => [
            'id' => (int) $group->id,
            'name' => (string) $group->name,
        ])
        ->values()
        ->all();
@endphp
<div class="container-info lab-dashboard">
    <div class="owner-shell">
        <div class="owner-panel">
            <section class="owner-panel-section" id="lab-panel-dashboard" data-owner-panel="dashboard" data-nav-label="dashboard" data-nav-icon="dashboard" role="tabpanel">
                <div class="summary-cards" data-animate>
                    <div class="expand-card tone-orange">
                        <div class="card-top">
                            <div class="expand-main">
                                <span class="expand-icon">
                                    <svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                        <path d="M9.828 3h3.982a2 2 0 0 1 1.992 2.181l-.637 7A2 2 0 0 1 13.174 14H2.825a2 2 0 0 1-1.991-1.819l-.637-7a2 2 0 0 1 .342-1.31L.5 3a2 2 0 0 1 2-2h3.672a2 2 0 0 1 1.414.586l.828.828A2 2 0 0 0 9.828 3m-8.322.12q.322-.119.684-.12h5.396l-.707-.707A1 1 0 0 0 6.172 2H2.5a1 1 0 0 0-1 .981z"/>
                                    </svg>
                                </span>
                                <div class="expand-text">
                                    <h3>Total de Projetos</h3>
                                    <h4>{{ $lab->projects->count() }}@if(!empty($tenantLimits['projects']))/{{ $tenantLimits['projects'] }}@endif</h4>
                                </div>
                            </div>
                            <button type="button" class="expand-trigger">Detalhes</button>
                        </div>
                        <div class="card-body">
                            <div class="card-body-header">
                                <h4>Status dos projetos</h4>
                                <button type="button" class="expand-close">&times;</button>
                            </div>
                            <div class="expand-grid">
                                <div class="expand-item">
                                    <span>Rascunho</span>
                                    <strong>{{ $lab->projects->where('status', 'draft')->count() }}</strong>
                                </div>
                                <div class="expand-item">
                                    <span>Em andamento</span>
                                    <strong>{{ $lab->projects->where('status', 'in_progress')->count() }}</strong>
                                </div>
                                <div class="expand-item">
                                    <span>Aprovado</span>
                                    <strong>{{ $lab->projects->where('status', 'approved')->count() }}</strong>
                                </div>
                                <div class="expand-item">
                                    <span>Rejeitado</span>
                                    <strong>{{ $lab->projects->where('status', 'rejected')->count() }}</strong>
                                </div>
                                <div class="expand-item">
                                    <span>Arquivado</span>
                                    <strong>{{ $lab->projects->where('status', 'archived')->count() }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="expand-card tone-indigo">
                        <div class="card-top">
                            <div class="expand-main">
                                <span class="expand-icon">
                                    <svg class="group" fill="#3f51b5" height="200px" width="200px" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 24 24" xml:space="preserve"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <g id="group"> <path d="M24,15.9c0-2.8-1.5-5-3.7-6.1C21.3,8.8,22,7.5,22,6c0-2.8-2.2-5-5-5c-2.1,0-3.8,1.2-4.6,3c0,0,0,0,0,0c-0.1,0-0.3,0-0.4,0 c-0.1,0-0.3,0-0.4,0c0,0,0,0,0,0C10.8,2.2,9.1,1,7,1C4.2,1,2,3.2,2,6c0,1.5,0.7,2.8,1.7,3.8C1.5,10.9,0,13.2,0,15.9V20h5v3h14v-3h5 V15.9z M17,3c1.7,0,3,1.3,3,3c0,1.6-1.3,3-3,3c0-1.9-1.1-3.5-2.7-4.4c0,0,0,0,0,0C14.8,3.6,15.8,3,17,3z M13.4,4.2 C13.4,4.2,13.4,4.2,13.4,4.2C13.4,4.2,13.4,4.2,13.4,4.2z M15,9c0,1.7-1.3,3-3,3s-3-1.3-3-3s1.3-3,3-3S15,7.3,15,9z M10.6,4.2 C10.6,4.2,10.6,4.2,10.6,4.2C10.6,4.2,10.6,4.2,10.6,4.2z M7,3c1.2,0,2.2,0.6,2.7,1.6C8.1,5.5,7,7.1,7,9C5.3,9,4,7.7,4,6S5.3,3,7,3 z M5.1,18H2v-2.1C2,13.1,4.1,11,7,11v0c0,0,0,0,0,0c0.1,0,0.2,0,0.3,0c0,0,0,0,0,0c0.3,0.7,0.8,1.3,1.3,1.8 C6.7,13.8,5.4,15.7,5.1,18z M17,21H7v-2.1c0-2.8,2.2-4.9,5-4.9c2.9,0,5,2.1,5,4.9V21z M22,18h-3.1c-0.3-2.3-1.7-4.2-3.7-5.2 c0.6-0.5,1-1.1,1.3-1.8c0.1,0,0.2,0,0.4,0v0c2.9,0,5,2.1,5,4.9V18z"></path> </g> </g></svg>
                                </span>
                                <div class="expand-text">
                                    <h3>Grupos no laboratório</h3>
                                    <h4>{{ $lab->groups->count() }}@if(!empty($tenantLimits['groups']))/{{ $tenantLimits['groups'] }}@endif</h4>
                                </div>
                            </div>
                            <button type="button" class="expand-trigger">Detalhes</button>
                        </div>
                        <div class="card-body">
                            <div class="card-body-header">
                                <h4>Status dos grupos</h4>
                                <button type="button" class="expand-close">&times;</button>
                            </div>
                            <div class="expand-grid">
                                <div class="expand-item">
                                    <span>Ativo</span>
                                    <strong>{{ $lab->groups->where('status', 'active')->count() }}</strong>
                                </div>
                                <div class="expand-item">
                                    <span>Inativo</span>
                                    <strong>{{ $lab->groups->where('status', 'inactive')->count() }}</strong>
                                </div>
                                <div class="expand-item">
                                    <span>Arquivado</span>
                                    <strong>{{ $lab->groups->where('status', 'archived')->count() }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="expand-card tone-teal">
                        <div class="card-top">
                            <div class="expand-main">
                                <span class="expand-icon">
                                    <svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                        <path d="M3 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6"/>
                                    </svg>
                                </span>
                                <div class="expand-text">
                                    <h3>Membros</h3>
                                    <h4>{{ $lab->subUsers->count() }}</h4>
                                </div>
                            </div>
                            <button type="button" class="expand-trigger">Detalhes</button>
                        </div>
                        <div class="card-body">
                            <div class="card-body-header">
                                <h4>Distribuição</h4>
                                <button type="button" class="expand-close">&times;</button>
                            </div>
                            <div class="expand-grid">
                                <div class="expand-item">
                                    <span>Assistentes</span>
                                    <strong>{{ $lab->subUsers->where('role', 'assistant')->count() }}</strong>
                                </div>
                                <div class="expand-item">
                                    <span>Professores</span>
                                    <strong>{{ $lab->subUsers->where('role', 'teacher')->count() }}</strong>
                                </div>
                                <div class="expand-item">
                                    <span>Alunos</span>
                                    <strong>{{ $lab->subUsers->where('role', 'student')->count() }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="graph-grid" data-animate>
                    <div class="graph-card">
                        <div class="graph-header">
                            <div>
                                <h3>Status dos projetos</h3>
                                <p class="graph-sub">Total: {{ $lab->projects->count() }}</p>
                            </div>
                            <div class="graph-period-filter-wrap">
                                <label class="graph-period-label" for="labChartPeriod">Periodo</label>
                                <select id="labChartPeriod" class="graph-period-select" data-chart-period-filter>
                                    <option value="3">Ultimos 3 meses</option>
                                    <option value="6">Ultimo semestre</option>
                                    <option value="12">Ultimo ano</option>
                                </select>
                            </div>
                        </div>
                        <div class="graph-body">
                            <div class="chart-wrap">
                                <canvas
                                    id="labPieChart"
                                    class="chart-canvas"
                                    data-series='@json($projectStatusChart)'
                                    data-period-series='@json($projectStatusChartByPeriod ?? [])'
                                ></canvas>
                            </div>
                            <div class="chart-legend" id="labPieLegend"></div>
                        </div>
                    </div>

                    <div class="graph-card">
                        <div class="graph-header">
                            <div>
                                <h3>Evolucao de projetos</h3>
                                <p class="graph-sub">Periodo selecionado</p>
                            </div>
                        </div>
                        <div class="graph-body">
                            <div class="chart-wrap">
                                <canvas
                                    id="labLineChart"
                                    class="chart-canvas"
                                    data-series='@json($projectEvolutionChart)'
                                    data-period-series='@json($projectEvolutionChartByPeriod ?? [])'
                                ></canvas>
                            </div>
                            <div class="chart-legend" id="labLineLegend"></div>
                        </div>
                    </div>
                </div>

                <div class="activity-row" data-animate>
                    <div class="heatmap-stud">
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
                                <p>Nenhum dado de projetos Disponível</p>
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
                            <span id="total-projects">Total: <strong>{{ $lab->projects->whereIn('status', ['approved', 'draft'])->count() }}</strong> projetos</span>
                        </div>
                    </div>
                </div>

                    <div class="calendar-logs lab-calendar">
                    <div class="event-calendar compact">
                        <div class="header-calendar">
                            <h3>Calendário do Laboratório</h3>
                            @if($canCreateEvent)
                            <a id="openEventFormBtn">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
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
                                <span>Sab</span>
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
                </div>
                </div>
            </section>

            <section class="owner-panel-section" id="lab-panel-members" data-owner-panel="members" data-nav-label="membros" data-nav-icon="members" role="tabpanel" hidden>
                <div class="overview-grid overview-grid-cards" data-animate>
                    <div class="overview-stack">
                        <div class="overview-header">
                            <h3>Membros do laboratorio</h3>
                            <span class="overview-count">{{ $labMembers->count() }}</span>
                        </div>

                        @if($labMembers->isEmpty())
                            <div class="overview-empty-card">Nenhum membro ativo neste laboratorio.</div>
                        @else
                            <div class="overview-card-list overview-member-list">
                                @foreach ($labMembers as $member)
                                    @php
                                        $memberRole = in_array((string) ($member->role ?? ''), ['asssitant', 'assitant'], true)
                                            ? 'assistant'
                                            : (string) ($member->role ?? 'student');
                                        $memberRoleLabel = match ($memberRole) {
                                            'owner' => 'Owner',
                                            'teacher' => 'Professor',
                                            'assistant' => 'Assistente',
                                            'student' => 'Aluno',
                                            default => ucfirst($memberRole),
                                        };
                                        $memberGroupId = (int) ($member->group_id ?? 0);
                                        $memberGroupName = $memberGroupId > 0
                                            ? ($groupNamesById[$memberGroupId] ?? 'Grupo')
                                            : 'Sem grupo';
                                        $isTeacherTarget = $memberRole === 'teacher';
                                        $canEditMemberRole = $canManageMembers
                                            && in_array($memberRole, ['teacher', 'assistant', 'student'], true)
                                            && !($isTeacherActor && $isTeacherTarget);
                                        $canRevokeMemberRelation = $canManageMembers
                                            && (int) ($member->id ?? 0) !== (int) ($user->id ?? 0);
                                    @endphp
                                    <article class="overview-entity-card overview-member-card">
                                        <div class="overview-entity-main overview-member-main">
                                            @if ($member->profile_photo_path == '')
                                                <div class="member-avatar" style="background: #ffe4b3;">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-person-fill" viewBox="0 0 16 16">
                                                        <path d="M3 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6"/>
                                                    </svg>
                                                </div>
                                            @else
                                                <div class="member-avatar" style="background-image: url('{{ asset('storage/' . $member->profile_photo_path) }}');"></div>
                                            @endif
                                            <div class="overview-member-info">
                                                <h4>{{ $member->name }}</h4>
                                                <p>{{ $member->email }}</p>
                                                <span class="overview-mini-meta">{{ $memberGroupName }}</span>
                                            </div>
                                            <div class="member-controls">
                                                @if($canEditMemberRole)
                                                    <form
                                                        class="member-role-form"
                                                        action="{{ route('group-member-role-update') }}"
                                                        method="POST"
                                                        data-group-options='@json($labGroupOptions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)'
                                                    >
                                                        @csrf
                                                        @method('PUT')
                                                        <input type="hidden" name="member_id" value="{{ $member->id }}">
                                                        <input type="hidden" name="lab_id" value="{{ $lab->id }}">
                                                        <input type="hidden" name="group_id" value="{{ $memberGroupId > 0 ? $memberGroupId : '' }}">
                                                        <select
                                                            class="member-role-select"
                                                            name="role"
                                                            data-current="{{ $memberRole }}"
                                                            aria-label="Funcao do membro {{ $member->name }}"
                                                        >
                                                            <option value="teacher" {{ $memberRole === 'teacher' ? 'selected' : '' }}>Professor</option>
                                                            <option value="assistant" {{ $memberRole === 'assistant' ? 'selected' : '' }}>Assistente</option>
                                                            <option value="student" {{ $memberRole === 'student' ? 'selected' : '' }}>Aluno</option>
                                                        </select>
                                                    </form>
                                                @else
                                                    <span class="status-badge status-{{ $memberRole }}">{{ $memberRoleLabel }}</span>
                                                @endif

                                                @if($canRevokeMemberRelation)
                                                    <form class="member-remove-form" action="{{ route('group-member-relation-revoke') }}" method="POST" onsubmit="return confirm('Remover este usuario do tenant?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <input type="hidden" name="member_id" value="{{ $member->id }}">
                                                        <input type="hidden" name="lab_id" value="{{ $lab->id }}">
                                                        <input type="hidden" name="group_id" value="{{ $memberGroupId > 0 ? $memberGroupId : '' }}">
                                                        <button type="submit" class="member-remove-btn">Remover</button>
                                                    </form>
                                                @endif
                                            </div>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </section>

            <section class="owner-panel-section" id="lab-panel-groups" data-owner-panel="groups" data-nav-label="grupos" data-nav-icon="groups" role="tabpanel" hidden>
                <div class="overview-grid overview-grid-cards" data-animate>
                    <div class="overview-stack">
                        <div class="overview-header">
                            <h3>Visão geral dos grupos</h3>
                            <span class="overview-count">{{ $lab->groups->count() }}/{{ $tenantLimits['groups'] }}</span>
                        </div>

                        @if($lab->groups->isEmpty())
                            <div class="overview-empty-card">Nenhum grupo encontrado.</div>
                        @else
                            <div class="overview-card-list">
                                @foreach ($lab->groups as $group)
                                    @php
                                        $groupStatus = 'status-' . str_replace('_', '-', $group->status);
                                        $groupStudentsCount = (int) ($groupStudentCountMap[$group->id] ?? 0);
                                        $groupVersionCount = (int) ($groupVersionCountMap[$group->id] ?? 0);
                                    @endphp
                                    <article class="overview-entity-card overview-entity-card-group">
                                        <div class="overview-entity-main">
                                            <div>
                                                <h4>{{ $group->name }}</h4>
                                                <p>{{ $group->code }}</p>
                                            </div>
                                            <span class="status-badge {{ $groupStatus }}">{{ $groupStatusLabels[$group->status] ?? ucfirst($group->status) }}</span>
                                        </div>

                                        <div class="overview-entity-footer">
                                            <button
                                                type="button"
                                                class="overview-toggle-btn"
                                                data-overview-toggle
                                                data-open-label="ver detalhes"
                                                data-close-label="ocultar detalhes"
                                                aria-expanded="false"
                                                aria-controls="lab-group-overview-{{ $group->id }}"
                                            >ver detalhes</button>
                                        </div>

                                        <div class="overview-entity-expand" id="lab-group-overview-{{ $group->id }}" hidden>
                                            <div class="overview-expand-stats">
                                                <div class="overview-expand-stat">
                                                    <span>Projetos</span>
                                                    <strong>{{ $lab->projects->where('group_id', $group->id)->count() }}</strong>
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
                                                @if($canEditGroupActions)
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
                            </div>
                        @endif
                    </div>
                </div>
            </section>

            <section class="owner-panel-section" id="lab-panel-projects" data-owner-panel="projects" data-nav-label="projetos" data-nav-icon="projects" role="tabpanel" hidden>
                @php
                    $groupNames = $lab->groups->pluck('name', 'id');
                @endphp

                <div class="overview-grid overview-grid-cards" data-animate>
                    <div class="overview-stack">
                        <div class="overview-header">
                            <h3>Visão geral dos projetos</h3>
                            <span class="overview-count">{{ $lab->projects->count() }}@if(!empty($tenantLimits['projects']))/{{ $tenantLimits['projects'] }}@endif</span>
                        </div>

                        @if($lab->projects->isEmpty())
                            <div class="overview-empty-card">Nenhum projeto encontrado.</div>
                        @else
                            <div class="overview-card-list">
                                @foreach ($lab->projects as $project)
                                    @php
                                        $projectStatus = 'status-' . str_replace('_', '-', $project->status);
                                        $projectVersionRaw = trim((string) ($project->current_version ?? ''));
                                        $projectVersionLabel = $projectVersionRaw === '' || $projectVersionRaw === '-'
                                            ? 'Sem versão'
                                            : (preg_match('/^v/i', $projectVersionRaw) ? $projectVersionRaw : 'v' . $projectVersionRaw);
                                        $projectVersionCount = (int) ($projectVersionCountMap[$project->id] ?? 0);
                                        $projectCommentCount = (int) ($projectCommentCountMap[$project->id] ?? 0);
                                        $projectStorageMb = number_format((float) ($projectStorageMbMap[$project->id] ?? 0), 2, ',', '.');
                                    @endphp
                                    <article class="overview-entity-card overview-entity-card-project">
                                        <div class="overview-entity-main overview-entity-main-project">
                                            <div>
                                                <h4>{{ $project->title }}</h4>
                                                <p>{{ $groupNames[$project->group_id] ?? 'Grupo' }}</p>
                                            </div>
                                            <div class="overview-project-badges">
                                                <span class="status-badge {{ $projectStatus }}">{{ $projectStatusLabels[$project->status] ?? str_replace('_', ' ', $project->status) }}</span>
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
                                                aria-controls="lab-project-overview-{{ $project->id }}"
                                            >ver detalhes</button>
                                        </div>

                                        <div class="overview-entity-expand" id="lab-project-overview-{{ $project->id }}" hidden>
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
                                                @if($canEditProjectActions)
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
                            </div>
                        @endif
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="{{ asset('script/lab.js') }}"></script>
@endpush
@endsection

@section('modals')
    @if($canCreateEvent)
    <form action="{{ route('event-add') }}" method="POST" id="eventForm" class="{{ $errors->getBag('event')->any() ? 'show' : '' }}">
        @csrf
        <input type="hidden" name="lab_id" value="{{ $lab->id }}">
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
                    value="{{ old('title') }}"
                    required
                >
                @error('title', 'event')
                    <span class="error-message">{{ $message }}</span>
                @enderror
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
                >{{ old('description') }}</textarea>
                @error('description', 'event')
                    <span class="error-message">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-row">
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
                        value="{{ old('due') }}"
                        required
                    >
                    @error('due', 'event')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>

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
                        value="{{ old('color', '#ff8c00') }}"
                    >
                    @error('color', 'event')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="event-mandatory">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        </svg>
                        Obrigatório?
                    </label>
                    <div class="checkbox-field">
                        <input type="hidden" name="is_mandatory" value="0">
                        <input id="event-mandatory" type="checkbox" name="is_mandatory" value="1" @checked(old('is_mandatory') == '1')>
                        <span>Sim</span>
                    </div>
                    @error('is_mandatory', 'event')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
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
@endsection





