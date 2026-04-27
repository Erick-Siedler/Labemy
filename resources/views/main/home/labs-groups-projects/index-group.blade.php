@extends($layout ?? 'layouts.header-side-not')

@section('title', 'Grupo')

@if($theme === '"light"')
@push('styles')
<link rel="stylesheet" href="{{ asset('main/group.css') }}">
@endpush
@else
@push('styles')
<link rel="stylesheet" href="{{ asset('main/group-dark.css') }}">
@endpush
@endif

@section('overlays')
@endsection

@section('content')
@php
    $groupProjects = $groupProjects ?? collect();
    $latestVersions = $latestVersions ?? collect();
    $groupMembers = $students->where('group_id', $group->id);
    $canManageMembers = $canManageMembers ?? true;
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
    $latestVersionList = $latestVersions->sortByDesc(function ($version) {
        return $version->submitted_at ?? $version->created_at;
    });
    $pendingReviews = $latestVersions->where('status_version', 'submitted')->count();
    $approvedVersions = $latestVersions->where('status_version', 'approved')->count();
    $projectsWithoutVersion = $groupProjects->filter(fn($project) => !$latestVersions->has($project->id))->count();
    $isAssistantView = in_array(($user->role ?? ''), ['assistant', 'assitant'], true);
    $canEditProjectActions = $canEditProjectStatus ?? true;
    $projectVersionCountMap = collect($projectVersionCountMap ?? []);
    $projectCommentCountMap = collect($projectCommentCountMap ?? []);
    $projectStorageMbMap = collect($projectStorageMbMap ?? []);
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
        'submitted' => 'Enviado',
    ];
    $versionStatusLabels = [
        'draft' => 'Rascunho',
        'submitted' => 'Enviado',
        'approved' => 'Aprovado',
        'rejected' => 'Rejeitado',
    ];
@endphp

<div class="container-info group-dashboard">
    <div class="owner-shell">
        <div class="owner-panel">
            <section class="owner-panel-section" id="group-panel-dashboard" data-owner-panel="dashboard" data-nav-label="dashboard" data-nav-icon="dashboard" role="tabpanel">
                <div class="summary-cards" data-animate>
                    <div class="stat-card tone-orange">
                        <div class="icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M4 4h16v4H4V4zm0 6h16v10H4V10z"/>
                            </svg>
                        </div>
                        <div class="info">
                            <h3>Total de projetos</h3>
                            <h4>{{ $groupProjects->count() }}@if(!empty($tenantLimits['projects']))/{{ $tenantLimits['projects'] }}@endif</h4>
                        </div>
                    </div>

                    <div class="stat-card tone-indigo">
                        <div class="icon">
                            <svg class="group" fill="#3f51b5" height="200px" width="200px" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 24 24" xml:space="preserve"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <g id="group"> <path d="M24,15.9c0-2.8-1.5-5-3.7-6.1C21.3,8.8,22,7.5,22,6c0-2.8-2.2-5-5-5c-2.1,0-3.8,1.2-4.6,3c0,0,0,0,0,0c-0.1,0-0.3,0-0.4,0 c-0.1,0-0.3,0-0.4,0c0,0,0,0,0,0C10.8,2.2,9.1,1,7,1C4.2,1,2,3.2,2,6c0,1.5,0.7,2.8,1.7,3.8C1.5,10.9,0,13.2,0,15.9V20h5v3h14v-3h5 V15.9z M17,3c1.7,0,3,1.3,3,3c0,1.6-1.3,3-3,3c0-1.9-1.1-3.5-2.7-4.4c0,0,0,0,0,0C14.8,3.6,15.8,3,17,3z M13.4,4.2 C13.4,4.2,13.4,4.2,13.4,4.2C13.4,4.2,13.4,4.2,13.4,4.2z M15,9c0,1.7-1.3,3-3,3s-3-1.3-3-3s1.3-3,3-3S15,7.3,15,9z M10.6,4.2 C10.6,4.2,10.6,4.2,10.6,4.2C10.6,4.2,10.6,4.2,10.6,4.2z M7,3c1.2,0,2.2,0.6,2.7,1.6C8.1,5.5,7,7.1,7,9C5.3,9,4,7.7,4,6S5.3,3,7,3 z M5.1,18H2v-2.1C2,13.1,4.1,11,7,11v0c0,0,0,0,0,0c0.1,0,0.2,0,0.3,0c0,0,0,0,0,0c0.3,0.7,0.8,1.3,1.3,1.8 C6.7,13.8,5.4,15.7,5.1,18z M17,21H7v-2.1c0-2.8,2.2-4.9,5-4.9c2.9,0,5,2.1,5,4.9V21z M22,18h-3.1c-0.3-2.3-1.7-4.2-3.7-5.2 c0.6-0.5,1-1.1,1.3-1.8c0.1,0,0.2,0,0.4,0v0c2.9,0,5,2.1,5,4.9V18z"></path> </g> </g></svg>
                        </div>
                        <div class="info">
                            <h3>Membros do grupo</h3>
                            <h4>{{ $groupMembers->count() }}</h4>
                        </div>
                    </div>

                    <div class="stat-card tone-teal">
                        <div class="icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                            </svg>
                        </div>
                        <div class="info">
                            <h3>Pendentes de Revisão</h3>
                            <h4>{{ $pendingReviews }}</h4>
                            <h5>{{ $groupProjects->where('status', 'in_progress')->count() }} em andamento</h5>
                        </div>
                    </div>

                    <div class="stat-card tone-green">
                        <div class="icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/>
                            </svg>
                        </div>
                        <div class="info">
                            <h3>Últimas Versões aprovadas</h3>
                            <h4>{{ $approvedVersions }}</h4>
                            <h5>{{ $projectsWithoutVersion }} sem Versão</h5>
                        </div>
                    </div>
                </div>

                <div class="info-grid" data-animate>
                    <div class="info-card">
                        <h3>Informações do grupo</h3>
                        <div class="info-line">
                            <span>Laboratório</span>
                            <strong>{{ $lab->name }}</strong>
                        </div>
                        <div class="info-line">
                            <span>Código</span>
                            <strong>{{ $group->code }}</strong>
                        </div>
                        <div class="info-line">
                            <span>Status</span>
                            @php $groupStatus = 'status-' . str_replace('_', '-', $group->status); @endphp
                            <span class="status-badge {{ $groupStatus }}">{{ $groupStatusLabels[$group->status] ?? ucfirst($group->status) }}</span>
                        </div>
                    </div>

                    <div class="info-card">
                        <h3>Distribuição de membros</h3>
                        <div class="info-line">
                            <span>Professores</span>
                            <strong>{{ $groupMembers->where('role', 'teacher')->count() }}</strong>
                        </div>
                        <div class="info-line">
                            <span>Assistentes</span>
                            <strong>{{ $groupMembers->where('role', 'assistant')->count() }}</strong>
                        </div>
                        <div class="info-line">
                            <span>Alunos</span>
                            <strong>{{ $groupMembers->where('role', 'student')->count() }}</strong>
                        </div>
                    </div>

                    <div class="info-card">
                        <h3>Pontos de Atenção</h3>
                        <div class="info-line">
                            <span>Projetos em rascunho</span>
                            <strong>{{ $groupProjects->where('status', 'draft')->count() }}</strong>
                        </div>
                        <div class="info-line">
                            <span>Projetos arquivados</span>
                            <strong>{{ $groupProjects->where('status', 'archived')->count() }}</strong>
                        </div>
                        <div class="info-line">
                            <span>Versões rejeitadas</span>
                            <strong>{{ $latestVersions->where('status_version', 'rejected')->count() }}</strong>
                        </div>
                    </div>
                </div>

                <div class="graph-grid" data-animate>
                    <div class="graph-card">
                        <div class="graph-header">
                            <div>
                                <h3>Status dos projetos</h3>
                                <p class="graph-sub">Total: {{ $groupProjects->count() }}</p>
                            </div>
                            <div class="graph-period-filter-wrap">
                                <label class="graph-period-label" for="groupChartPeriod">Periodo</label>
                                <select id="groupChartPeriod" class="graph-period-select" data-chart-period-filter>
                                    <option value="3">Ultimos 3 meses</option>
                                    <option value="6">Ultimo semestre</option>
                                    <option value="12">Ultimo ano</option>
                                </select>
                            </div>
                        </div>
                        <div class="graph-body">
                            <div class="chart-wrap chart-bar">
                                <canvas
                                    id="groupProjectBar"
                                    class="chart-canvas"
                                    data-series='@json($projectStatusChart)'
                                    data-period-series='@json($projectStatusChartByPeriod ?? [])'
                                ></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="graph-card">
                        <div class="graph-header">
                            <div>
                                <h3>Status das Últimas Versões</h3>
                                <p class="graph-sub">Total: {{ $latestVersions->count() }}</p>
                            </div>
                        </div>
                        <div class="graph-body">
                            <div class="chart-wrap chart-bar">
                                <canvas
                                    id="groupVersionBar"
                                    class="chart-canvas"
                                    data-series='@json($versionStatusChart)'
                                    data-period-series='@json($versionStatusChartByPeriod ?? [])'
                                ></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="board-grid" data-animate>
                    <div class="board-card">
                        <div class="board-header">
                            <h3>Últimas Versões submetidas</h3>
                            <span class="board-count">{{ $latestVersions->where('status_version', 'submitted')->count() }}</span>
                        </div>
                        <div class="board-body">
                            @forelse ($latestVersionList->take(6) as $version)
                                @php
                                    $projectName = $groupProjects->firstWhere('id', $version->project_id)?->title ?? 'Projeto';
                                    $versionStatus = 'status-' . str_replace('_', '-', $version->status_version);
                                @endphp
                                <div class="board-item">
                                    <div class="board-main">
                                        <h4>{{ $projectName }}</h4>
                                        <span class="board-sub">Versão {{ $version->version_number }}</span>
                                    </div>
                                    <div class="board-meta">
                                        <span class="status-badge {{ $versionStatus }}">{{ $versionStatusLabels[$version->status_version] ?? $version->status_version }}</span>
                                        <span class="board-date">{{ $version->submitted_at ?? '-' }}</span>
                                    </div>
                                </div>
                            @empty
                                <div class="empty-state">Nenhuma Versão encontrada.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </section>

            <section class="owner-panel-section" id="group-panel-members" data-owner-panel="members" data-nav-label="membros" data-nav-icon="members" role="tabpanel" hidden>
                <div class="board-grid" data-animate>
                    <div class="board-card">
                        <div class="board-header">
                            <h3>Membros do grupo</h3>
                            <span class="board-count">{{ $groupMembers->count() }}</span>
                        </div>
                        <div class="board-body">
                            @forelse ($groupMembers as $member)
                                <div class="member-item">
                                    @if ($member->profile_photo_path == '')
                                        <div class="member-avatar" style="background: #ffe4b3;">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-person-fill" viewBox="0 0 16 16">
                                                <path d="M3 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6"/>
                                            </svg>
                                        </div>
                                    @else
                                        <div class="member-avatar" style="background-image: url('{{ asset('storage/' . $member->profile_photo_path) }}');"></div>
                                    @endif
                                    <div class="member-info">
                                        <h4>{{ $member->name }}</h4>
                                        <p>{{ $member->email }}</p>
                                    </div>
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
                                        $canEditMemberRole = $canManageMembers
                                            && in_array($memberRole, ['teacher', 'assistant', 'student'], true)
                                            && !($isTeacherActor && $memberRole === 'teacher');
                                        $canRevokeMemberRelation = $canManageMembers
                                            && (int) ($member->id ?? 0) !== (int) ($user->id ?? 0);
                                    @endphp
                                    <div class="member-controls">
                                        @if($canEditMemberRole)
                                            <form class="member-role-form" action="{{ route('group-member-role-update') }}" method="POST">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="member_id" value="{{ $member->id }}">
                                                <input type="hidden" name="group_id" value="{{ $group->id }}">
                                                <input type="hidden" name="lab_id" value="{{ $lab->id }}">
                                                <select
                                                    class="member-role-select"
                                                    name="role"
                                                    data-current="{{ $memberRole }}"
                                                    aria-label="Função do membro {{ $member->name }}"
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
                                                <input type="hidden" name="group_id" value="{{ $group->id }}">
                                                <input type="hidden" name="lab_id" value="{{ $lab->id }}">
                                                <button type="submit" class="member-remove-btn">Remover</button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="empty-state">Nenhum membro associado a este grupo.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </section>

            <section class="owner-panel-section" id="group-panel-projects" data-owner-panel="projects" data-nav-label="projetos" data-nav-icon="projects" role="tabpanel" hidden>
                <div class="overview-grid overview-grid-cards" data-animate>
                    <div class="overview-stack">
                        <div class="overview-header">
                            <h3>Visão geral dos projetos</h3>
                            <span class="overview-count">{{ $groupProjects->count() }}@if(!empty($tenantLimits['projects']))/{{ $tenantLimits['projects'] }}@endif</span>
                        </div>

                        @if($groupProjects->isEmpty())
                            <div class="overview-empty-card">Nenhum projeto encontrado.</div>
                        @else
                            <div class="overview-card-list">
                                @foreach ($groupProjects as $project)
                                    @php
                                        $latest = $latestVersions->get($project->id);
                                        $projectStatus = 'status-' . str_replace('_', '-', $project->status);
                                        $latestVersionLabel = $latest ? ('v' . $latest->version_number) : 'Sem versão';
                                        $projectVersionCount = (int) ($projectVersionCountMap[$project->id] ?? 0);
                                        $projectCommentCount = (int) ($projectCommentCountMap[$project->id] ?? 0);
                                        $projectStorageMb = number_format((float) ($projectStorageMbMap[$project->id] ?? 0), 2, ',', '.');
                                        $viewProjectUrl = $isAssistantView
                                            ? route('subuser-home', ['project' => $project->id, 'group' => $group->id])
                                            : route('project.index', $project->id);
                                    @endphp
                                    <article class="overview-entity-card overview-entity-card-project">
                                        <div class="overview-entity-main overview-entity-main-project">
                                            <div>
                                                <h4>{{ $project->title }}</h4>
                                                <p>{{ $lab->name }} &gt; {{ $group->name }}</p>
                                            </div>
                                            <div class="overview-project-badges">
                                                <span class="status-badge {{ $projectStatus }}">{{ $projectStatusLabels[$project->status] ?? str_replace('_', ' ', $project->status) }}</span>
                                                <span class="code-pill">{{ $latestVersionLabel }}</span>
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
                                                aria-controls="group-project-overview-{{ $project->id }}"
                                            >ver detalhes</button>
                                        </div>

                                        <div class="overview-entity-expand" id="group-project-overview-{{ $project->id }}" hidden>
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
                                                <a class="overview-action-btn overview-action-view" href="{{ $viewProjectUrl }}">Ver</a>
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
<script src="{{ asset('script/group.js') }}"></script>
@endpush
@endsection






