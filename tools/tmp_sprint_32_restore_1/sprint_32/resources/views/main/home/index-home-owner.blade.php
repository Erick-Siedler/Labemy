
@extends($layout ?? 'layouts.header-side-not')

@section('title', 'Início')

@section('overlays')
@php
    $canCreateEvent = $canCreateEvent ?? true;
    $canInviteStudents = $canInviteStudents ?? true;
@endphp
@if($canCreateEvent)
<div class="event-overlay" id="eventOverlay"></div>
@endif
@if($canInviteStudents)
<div class="event-overlay" id="studentOverlay"></div>
@endif
@endsection

@section('content')
@php
    $canCreateEvent = $canCreateEvent ?? true;
    $canCreateEventAll = $canCreateEventAll ?? true;
    $canInviteStudents = $canInviteStudents ?? true;
    $ownerLabs = $labs->whereNull('creator_subuser_id');
    $teacherLabs = $labs->whereNotNull('creator_subuser_id');
    $allGroups = $labs->flatMap(function ($lab) {
        return $lab->groups;
    });
    $allProjects = $allGroups->flatMap(function ($group) {
        return $group->projects;
    });

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
        <div class="owner-tabs" role="tablist" aria-label="Seções do painel">
            <button type="button" class="owner-tab is-active" data-owner-tab="dashboard" role="tab" aria-selected="true" aria-controls="owner-panel-dashboard">dashboard</button>
            <button type="button" class="owner-tab" data-owner-tab="labs" role="tab" aria-selected="false" aria-controls="owner-panel-labs">laboratórios</button>
            <button type="button" class="owner-tab" data-owner-tab="groups" role="tab" aria-selected="false" aria-controls="owner-panel-groups">grupos</button>
            <button type="button" class="owner-tab" data-owner-tab="projects" role="tab" aria-selected="false" aria-controls="owner-panel-projects">projetos</button>
        </div>

        <div class="owner-panel">
            <section class="owner-panel-section" id="owner-panel-dashboard" data-owner-panel="dashboard" role="tabpanel">
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
                                <h4>{{ $students->count() }}/{{ $tenantLimits['subusers'] }}</h4>
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
                                <h4>{{ $active_projs->whereIn('status', ['submitted', 'approved'])->count() }}</h4>
                                <h5>{{ $active_projs->where('status', 'draft')->count() }} pendentes</h5>
                            </div>
                        </div>
                    </div>

                    <div class="heatmap-stud" data-animate>
                        <div class="header-calendar">
                            <h3>Atividade dos Alunos</h3>
                            <select id="period-filter">
                                <option value="3">Últimos 3 meses</option>
                                <option value="6">Últimos 6 meses</option>
                                <option value="12">Último ano</option>
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
                                <span id="total-projects">Total: <strong>{{ $active_projs->whereIn('status', ['approved', 'submitted'])->count() }}</strong> projetos</span>
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
                                    <a class="calendar-nav" href="?ano={{ $calendar['prev']['year'] }}&mes={{ $calendar['prev']['month'] }}">‹</a>
                                    <span class="calendar-title">{{ $calendar['title'] }}</span>
                                    <a class="calendar-nav" href="?ano={{ $calendar['next']['year'] }}&mes={{ $calendar['next']['month'] }}">›</a>
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
            <section class="owner-panel-section" id="owner-panel-labs" data-owner-panel="labs" role="tabpanel" hidden>
                <div class="overview-grid" data-animate>
                    @if($ownerLabs->isNotEmpty())
                        <div class="overview-card group-overview">
                            <div class="overview-header">
                                <h3>Laboratórios do owner</h3>
                                <span class="overview-count">
                                    {{ $ownerLabs->count() }}
                                    @if(!empty($tenantLimits['labs']))
                                        /{{ $tenantLimits['labs'] }}
                                    @endif
                                </span>
                            </div>
                            <div class="overview-table">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Laboratório</th>
                                            <th>Código</th>
                                            <th>Status</th>
                                            <th>Grupos</th>
                                            <th>Projetos</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($ownerLabs as $lab)
                                            @php $labStatus = 'status-' . str_replace('_', '-', $lab->status); @endphp
                                            <tr>
                                                <td>
                                                    <div class="table-main">{{ $lab->name }}</div>
                                                    <div class="table-sub">ID {{ $lab->id }}</div>
                                                </td>
                                                <td><span class="code-pill">{{ $lab->code }}</span></td>
                                                <td><span class="status-badge {{ $labStatus }}">{{ $labStatusLabels[$lab->status] ?? ucfirst($lab->status) }}</span></td>
                                                <td>{{ $lab->groups->count() }}</td>
                                                <td>{{ $lab->projects->count() }}</td>
                                                <td>
                                                    <a class="btn-view" href="{{ route('lab.index', [
                                                        $lab->id,
                                                        $theme
                                                    ]) }}">Ver</a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    @if($teacherLabs->isNotEmpty())
                        <div class="overview-card group-overview">
                            <div class="overview-header">
                                <h3>{{ $ownerLabs->isEmpty() && $teacherLabs->isNotEmpty() ? 'Seus laboratórios' : 'Laboratórios dos professores' }}</h3>
                                <span class="overview-count">
                                    {{ $teacherLabs->count() }}
                                    @if(!empty($tenantLimits['labs']))
                                        /{{ $tenantLimits['labs'] }}
                                    @endif
                                </span>
                            </div>
                            <div class="overview-table">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Laboratório</th>
                                            <th>Professor</th>
                                            <th>Código</th>
                                            <th>Status</th>
                                            <th>Grupos</th>
                                            <th>Projetos</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($teacherLabs as $lab)
                                            @php $labStatus = 'status-' . str_replace('_', '-', $lab->status); @endphp
                                            <tr>
                                                <td>
                                                    <div class="table-main">{{ $lab->name }}</div>
                                                    <div class="table-sub">ID {{ $lab->id }}</div>
                                                </td>
                                                <td>{{ $lab->creatorSubuser?->name ?? 'Professor' }}</td>
                                                <td><span class="code-pill">{{ $lab->code }}</span></td>
                                                <td><span class="status-badge {{ $labStatus }}">{{ $labStatusLabels[$lab->status] ?? ucfirst($lab->status) }}</span></td>
                                                <td>{{ $lab->groups->count() }}</td>
                                                <td>{{ $lab->projects->count() }}</td>
                                                <td>
                                                    <a class="btn-view" href="{{ route('lab.index', [
                                                        $lab->id,
                                                        $theme
                                                    ]) }}">Ver</a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    @if($ownerLabs->isEmpty() && $teacherLabs->isEmpty())
                        <div class="overview-card group-overview">
                            <div class="overview-header">
                                <h3>Visão geral dos laboratórios</h3>
                                <span class="overview-count">
                                    0
                                    @if(!empty($tenantLimits['labs']))
                                        /{{ $tenantLimits['labs'] }}
                                    @endif
                                </span>
                            </div>
                            <div class="overview-table">
                                <table>
                                    <tbody>
                                        <tr>
                                            <td colspan="6" class="table-empty">Nenhum laboratório encontrado.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>
            </section>
            <section class="owner-panel-section" id="owner-panel-groups" data-owner-panel="groups" role="tabpanel" hidden>
                <div class="students-container" data-animate>
                    <div class="students-header">
                        <div class="students-title">
                            <h3>Alunos</h3>
                            <span class="students-count">{{ $students->count() }}</span>
                        </div>
                        @if($canInviteStudents)
                        <button class="students-invite-btn" id="openStudentInviteFormBtn">Convidar aluno</button>
                        @endif
                    </div>

                    <div class="students-grid">
                        @forelse ($students as $student)
                            <div class="student-card">
                                @if ($student->profile_photo_path == '')
                                    <div class="student-avatar" style="background: #223237;">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-person-fill" viewBox="0 0 16 16">
                                            <path d="M3 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6"/>
                                        </svg>
                                    </div>
                                @else
                                    <div class="student-avatar" style="background-image: url('{{ asset('storage/' . $user->profile_photo_path) }}');"></div>
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
                        @empty
                            <div class="students-empty">
                                <p>Nenhum aluno cadastrado ainda.</p>
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="overview-grid" data-animate>
                    <div class="overview-card group-overview">
                        <div class="overview-header">
                            <h3>Visão geral dos grupos</h3>
                            <span class="overview-count">
                                {{ $allGroups->count() }}
                                @if(!empty($tenantLimits['groups']))
                                    /{{ $tenantLimits['groups'] }}
                                @endif
                            </span>
                        </div>
                        <div class="overview-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Grupo</th>
                                        <th>Laboratório</th>
                                        <th>Código</th>
                                        <th>Status</th>
                                        <th>Projetos</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @if($allGroups->isEmpty())
                                        <tr>
                                            <td colspan="6" class="table-empty">Nenhum grupo encontrado.</td>
                                        </tr>
                                    @else
                                        @foreach ($labs as $lab)
                                            @foreach ($lab->groups as $group)
                                                @php $groupStatus = 'status-' . str_replace('_', '-', $group->status); @endphp
                                                <tr>
                                                    <td>
                                                        <div class="table-main">{{ $group->name }}</div>
                                                        <div class="table-sub">ID {{ $group->id }}</div>
                                                    </td>
                                                    <td>{{ $lab->name }}</td>
                                                    <td><span class="code-pill">{{ $group->code }}</span></td>
                                                    <td><span class="status-badge {{ $groupStatus }}">{{ $groupStatusLabels[$group->status] ?? ucfirst($group->status) }}</span></td>
                                                    <td>{{ $group->projects->count() }}</td>
                                                    <td><a class="btn-view" href="{{ route('group.index', $group->id) }}">Ver</a></td>
                                                </tr>
                                            @endforeach
                                        @endforeach
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
            <section class="owner-panel-section" id="owner-panel-projects" data-owner-panel="projects" role="tabpanel" hidden>
                <div class="overview-grid" data-animate>
                    <div class="overview-card project-overview">
                        <div class="overview-header">
                            <h3>Visão geral dos projetos</h3>
                            <span class="overview-count">
                                {{ $allProjects->count() }}
                                @if(!empty($tenantLimits['projects']))
                                    /{{ $tenantLimits['projects'] }}
                                @endif
                            </span>
                        </div>
                        <div class="overview-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Projeto</th>
                                        <th>Laboratório</th>
                                        <th>Grupo</th>
                                        <th>Status</th>
                                        <th>Versão</th>
                                        <th>Submetido</th>
                                        <th>Aprovado</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @if($allProjects->isEmpty())
                                        <tr>
                                            <td colspan="8" class="table-empty">Nenhum projeto encontrado.</td>
                                        </tr>
                                    @else
                                        @foreach ($labs as $lab)
                                            @foreach ($lab->groups as $group)
                                                @foreach ($group->projects as $project)
                                                    @php
                                                        $projectStatusValue = $project->status;
                                                        $projectStatus = $projectStatusValue === 'submitted'
                                                            ? 'status-in-progress'
                                                            : 'status-' . str_replace('_', '-', $projectStatusValue);
                                                    @endphp
                                                    <tr>
                                                        <td>
                                                            <div class="table-main">{{ $project->title }}</div>
                                                            <div class="table-sub">ID {{ $project->id }}</div>
                                                        </td>
                                                        <td>{{ $lab->name }}</td>
                                                        <td>{{ $group->name }}</td>
                                                        <td><span class="status-badge {{ $projectStatus }}">{{ $projectStatusLabels[$projectStatusValue] ?? ucfirst(str_replace('_', ' ', $projectStatusValue)) }}</span></td>
                                                        <td><span class="code-pill">{{ $project->current_version ?? '-' }}</span></td>
                                                        <td>{{ $project->submitted_at ?? '-' }}</td>
                                                        <td>{{ $project->approved_at ?? '-' }}</td>
                                                        <td><a class="btn-view" href="{{ route('project.index', $project->id) }}">Ver</a></td>
                                                    </tr>
                                                @endforeach
                                            @endforeach
                                        @endforeach
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>
@endsection
@section('modals')
    @if($canCreateEvent)
    <form action="{{ route('event-add') }}" method="POST" id="eventForm">
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
    <form action="{{ route('subuser-invite') }}" method="POST" id="studentInviteForm">
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
            <h3>Convidar aluno</h3>
        </div>

        <div class="body-form">
            @if ($errors->any())
                <div class="form-alert">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="form-group full-width">
                <label for="student-invite-email">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 4h16v16H4z"/>
                        <path d="M4 4l8 8 8-8"/>
                    </svg>
                    E-mail do aluno
                </label>
                <input
                    id="student-invite-email"
                    name="email"
                    placeholder="email@exemplo.com"
                    type="email"
                    required
                >
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="student-invite-lab">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                        </svg>
                        Laboratório
                    </label>
                    <select name="lab_id" id="student-invite-lab" required>
                        <option value="" selected disabled>Selecione o laboratório</option>
                        @foreach ($labs as $lab)
                            <option value="{{ $lab->id }}">{{ $lab->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="student-invite-group">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                        </svg>
                        Grupo
                    </label>
                    <select name="group_id" id="student-invite-group" required disabled>
                        <option value="" selected disabled id="student-invite-group-placeholder">Selecione o grupo</option>
                        @foreach ($labs as $lab)
                            @foreach ($lab->groups as $group)
                                <option value="{{ $group->id }}" data-lab-id="{{ $lab->id }}">{{ $group->name }}</option>
                            @endforeach
                        @endforeach
                    </select>
                </div>
            </div>

            <p class="form-hint">O link de convite expira em 24 horas.</p>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-submit">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                Enviar convite
            </button>
        </div>
    </form>
    @endif
@endsection
