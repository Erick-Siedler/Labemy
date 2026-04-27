@extends('layouts.header-side-not-sub')

@section('title', 'Projetos')

@if($theme === '"light"')
@push('styles')
<link rel="stylesheet" href="{{ asset('main/proj.css') }}">
@endpush
@else
@push('styles')
<link rel="stylesheet" href="{{ asset('main/proj-dark.css') }}">
@endpush
@endif

@section('overlays')
<div class="version-overlay {{ $errors->any() ? 'is-open' : '' }}" id="versionOverlay"></div>
@endsection

@section('content')
@if(in_array($user->role, ['student', 'assistant', 'assitant'], true))
@php
    $project = $project ?? null;
    $lab = $lab ?? null;
    $group = $group ?? null;
    $versions = $versions ?? collect();
    $projectFiles = $projectFiles ?? collect();
    $projectSubfolders = ($project?->subfolders ?? collect())->sortBy('order_index')->values();
    $selectedSubfolderId = (int) old('subfolder_id', (int) ($projectSubfolders->first()?->id ?? 0));
    $calendar = $calendar ?? [
        'title' => '',
        'prev' => ['year' => now()->year, 'month' => now()->month],
        'next' => ['year' => now()->year, 'month' => now()->month],
        'leadingBlanks' => 0,
        'days' => collect(),
        'events' => collect(),
    ];
    $calendarProjectQuery = $project ? '&project=' . $project->id : '';
    $latestVersionStatus = $versions->first()?->status_version;
    $isAssistant = in_array($user->role, ['assistant', 'assitant'], true);
    $studentAwaitingReview = $user->role === 'student'
        && $latestVersionStatus === 'submitted';
    $studentVersionLocked = $studentAwaitingReview;
    $versionFlowRecentLimit = (int) ($versionFlowRecentLimit ?? 6);
    $tasks = $tasks ?? collect();
    $projectTasks = $project ? $tasks->where('project_id', $project->id)->values() : collect();
    $canManageTasks = (bool) ($canManageTasks ?? false);
    $taskStatusColumns = [
        ['status' => 'draft', 'class' => 'draft', 'empty' => 'Nenhuma task em rascunho'],
        ['status' => 'approved', 'class' => 'approved', 'empty' => 'Nenhuma task aprovada'],
        ['status' => 'in_progress', 'class' => 'in-progress', 'empty' => 'Nenhuma task em progresso'],
        ['status' => 'done', 'class' => 'done', 'empty' => 'Nenhuma task finalizada'],
    ];
    $taskStatusLabels = [
        'draft' => 'Rascunho',
        'approved' => 'Aprovada',
        'in_progress' => 'Em progresso',
        'done' => 'Concluida',
    ];
    $taskVersionOptions = $versions
        ->whereIn('status_version', ['approved', 'submitted'])
        ->sortByDesc('version_number')
        ->values();
    $taskCreateHasErrors = $errors->taskCreate->any();
    $taskEditHasErrors = $errors->taskEdit->any();
    $taskFormHasErrors = $taskCreateHasErrors || $taskEditHasErrors;
    $taskFormErrorBag = $taskEditHasErrors ? 'taskEdit' : 'taskCreate';
    $taskEditingId = (int) old('task_id', 0);
    $taskEditing = $taskEditingId > 0 ? $projectTasks->firstWhere('id', $taskEditingId) : null;
    $taskFormMode = $taskEditHasErrors ? 'edit' : 'create';
    $taskFormTargetId = (int) ($taskEditing?->id ?? $taskEditingId);
    $taskFormAction = ($taskFormMode === 'edit' && $taskFormTargetId > 0)
        ? route('task-edit', $taskFormTargetId)
        : route('task-add');
    $taskFormTitle = $taskFormMode === 'edit' ? 'Editar Task' : 'Nova Task';
    $taskFormSubmitLabel = $taskFormMode === 'edit' ? 'Salvar Alteracoes' : 'Adicionar Task';
    $taskFormVersionId = (int) old('version_id', (int) ($taskEditing?->version_id ?? 0));
@endphp

<div class="container-info project-dashboard">
    @if($project)
    <div
        class="project-hero {{ $isAssistant ? '' : 'hero-editable' }}"
        data-animate
        @if(!$isAssistant)
        data-project-hero
        data-project-id="{{ $project->id }}"
        data-update-url="{{ route('project-update') }}"
        @endif
    >
        <div class="project-hero-main">
            <label class="hero-label" for="heroProjectTitle">Projeto</label>
            <input
                id="heroProjectTitle"
                class="hero-input"
                type="text"
                @if(!$isAssistant) data-project-field="title" @endif
                {{ $isAssistant ? 'readonly' : '' }}
                value="{{ $project->title }}"
            >
            <label class="hero-label" for="heroProjectDescription">Descrição</label>
            <textarea
                id="heroProjectDescription"
                class="hero-textarea"
                rows="3"
                @if(!$isAssistant) data-project-field="description" @endif
                {{ $isAssistant ? 'readonly' : '' }}
                placeholder="Breve Descrição do projeto..."
            >{{ $project->description }}</textarea>
        </div>

        <div class="project-hero-info">
            <div class="hero-field">
                <span class="hero-label">Laboratório</span>
                <input class="hero-input" type="text" value="{{ $lab?->name }}" readonly>
            </div>
            <div class="hero-field">
                <span class="hero-label">Grupo</span>
                <input class="hero-input" type="text" value="{{ $group?->name }}" readonly>
            </div>
            <div class="hero-field">
                <span class="hero-label">Status</span>
                @if($project->status === 'approved')
                <input class="hero-input" type="text" value="Aprovado" readonly>
                @elseif($project->status === 'archived')
                <input class="hero-input" type="text" value="Arquivado" readonly>
                @elseif($project->status === 'in_progress')
                <input class="hero-input" type="text" value="Em andamento" readonly>
                @elseif($project->status === 'draft')
                <input class="hero-input" type="text" value="Rascunho" readonly>
                @else
                <input class="hero-input" type="text" value="Rejeitado" readonly>
                @endif
                
            </div>
        </div>

        @if(!$isAssistant)
        <div class="project-hero-actions">
            @if($project)
            <a class="btn-secondary" href="{{ route('requirements.index', ['project' => $project->id, 'rf_page' => 1, 'rnf_global_page' => 1, 'rnf_linked_page' => 1]) }}">
                Requisitos
            </a>
            @endif
            <button type="button" class="btn-secondary project-hero-save" data-project-save>Salvar</button>
            <span class="project-hero-status" data-project-save-status></span>
        </div>
        @elseif($project)
        <div class="project-hero-actions">
            <a class="btn-secondary" href="{{ route('requirements.index', ['project' => $project->id, 'rf_page' => 1, 'rnf_global_page' => 1, 'rnf_linked_page' => 1]) }}">
                Requisitos
            </a>
        </div>
        @endif
    </div>
    @else
    <div class="project-hero" data-animate>
        <div>
            <h2>Projetos</h2>
        </div>
    </div>
    @endif

    <div class="owner-shell">
        <div class="owner-panel">
            <div class="project-stack">
                <section
                    class="owner-panel-section project-panel panel-versions is-active"
                    id="student-panel-versions"
                    data-owner-panel="versions"
                    data-nav-label="subpastas"
                    data-nav-icon="versions"
                    role="tabpanel"
                >
                    <div class="versions-section" data-animate>
                        <div class="section-header">
                            <div>
                                <h3>Fluxo por subfolder</h3>
                                <p class="section-sub">Cada subfolder possui seu próprio fluxo de versões.</p>
                            </div>
                            @if($user->role === 'student' && !$studentVersionLocked)
                                @if($project && ($project->status === 'approved' || $project->status === 'in_progress' || $latestVersionStatus === 'approved' || $latestVersionStatus === 'in_progress'))
                                    <button type="button" class="btn-secondary" id="openVersionFormBtnSecondary">Nova Versão</button>
                                @else
                                    <span class="status-lock-message" role="status" aria-live="polite">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                                            <path d="M8.982 1.566a1.13 1.13 0 0 0-1.964 0L.165 13.233c-.457.778.091 1.767.982 1.767h13.706c.89 0 1.438-.99.982-1.767z"/>
                                            <path d="M8 5c.535 0 .954.462.9.995l-.35 3.507a.55.55 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5m.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2"/>
                                        </svg>
                                        <span>Seu status de projeto nao permite novas versoes.</span>
                                    </span>
                                @endif
                            @endif
                        </div>

                        @if($studentAwaitingReview)
                            <p class="section-sub">Aguardando avaliacao da ultima versao.</p>
                        @endif

                        @if(!$project)
                            <div class="empty-state">Nenhum projeto encontrado.</div>
                        @elseif($projectSubfolders->isEmpty())
                            <div class="empty-state">Nenhuma subfolder encontrada para este projeto.</div>
                        @else
                            @foreach($projectSubfolders as $subfolder)
                                @php
                                    $allSubfolderFlowVersions = $versions
                                        ->where('subfolder_id', $subfolder->id)
                                        ->sortBy('version_number')
                                        ->values();
                                    $recentSubfolderFlowIds = $allSubfolderFlowVersions
                                        ->sortByDesc('version_number')
                                        ->take($versionFlowRecentLimit)
                                        ->pluck('id')
                                        ->map(fn ($id) => (int) $id)
                                        ->all();
                                    $hiddenSubfolderFlowCount = max(0, $allSubfolderFlowVersions->count() - count($recentSubfolderFlowIds));
                                    $latestSubfolderVersion = $allSubfolderFlowVersions->sortByDesc('version_number')->first();
                                @endphp
                                <div class="versions-section">
                                    <div class="section-header">
                                        <div>
                                            <h3>{{ $subfolder->name }}</h3>
                                            <p class="section-sub">Versões: {{ $allSubfolderFlowVersions->count() }} | Atual: {{ $latestSubfolderVersion?->version_number ?? '-' }}</p>
                                        </div>
                                        <a class="btn-secondary" href="{{ route('subfolder-index', $subfolder->id) }}">Abrir subfolder</a>
                                    </div>

                                    @if($allSubfolderFlowVersions->isNotEmpty())
                                        <div class="version-board" data-version-board data-flow-board>
                                            <div class="version-board-toolbar">
                                                <span class="board-hint">Arraste para navegar no quadro</span>
                                                <div class="board-actions">
                                                    <button type="button" class="btn-secondary btn-compact" data-board-reset>Centralizar</button>
                                                    @if($hiddenSubfolderFlowCount > 0)
                                                    <button
                                                        type="button"
                                                        class="btn-secondary btn-compact"
                                                        data-flow-toggle
                                                        data-label-expand="Ver fluxo completo ({{ $allSubfolderFlowVersions->count() }})"
                                                        data-label-collapse="Mostrar apenas recentes"
                                                    >
                                                        Ver fluxo completo ({{ $allSubfolderFlowVersions->count() }})
                                                    </button>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="version-board-viewport" data-board-viewport>
                                                <div class="version-board-canvas">
                                                    <div class="version-board-lane">
                                                        <div class="board-column board-column--project">
                                                            <div class="board-node project-node">
                                                                <span class="node-kicker">Subfolder</span>
                                                                <strong class="node-title">{{ $project->title }} - {{ $subfolder->name }}</strong>
                                                            </div>
                                                        </div>

                                                        @foreach($allSubfolderFlowVersions as $version)
                                                            @php $versionStatus = 'status-' . str_replace('_', '-', $version->status_version); @endphp
                                                            @php $file = ($projectFiles ?? collect())->firstWhere('project_versions_id', $version->id); @endphp
                                                            @php $isHiddenFlowVersion = !in_array((int) $version->id, $recentSubfolderFlowIds, true); @endphp
                                                            <div class="board-column{{ $isHiddenFlowVersion ? ' flow-version-hidden' : '' }}" @if($isHiddenFlowVersion) data-flow-version-hidden hidden @endif>
                                                                <div class="board-node version-node">
                                                                    <div class="version-node-header">
                                                                        <div>
                                                                            <span class="node-kicker">Versão {{ $version->version_number }}</span>
                                                                            <strong class="node-title">{{ $version->title }}</strong>
                                                                        </div>
                                                                        <div class="version-node-badges">
                                                                            @if($versionStatus === 'status-approved')
                                                                                <span class="status-badge {{ $versionStatus }}">Aprovado</span>
                                                                            @elseif($versionStatus === 'status-rejected')
                                                                                <span class="status-badge {{ $versionStatus }}">Rejeitado</span>
                                                                            @elseif($versionStatus === 'status-draft')
                                                                                <span class="status-badge {{ $versionStatus }}">Rascunho</span>
                                                                            @else
                                                                                <span class="status-badge {{ $versionStatus }}">Enviado</span>
                                                                            @endif
                                                                        </div>
                                                                    </div>

                                                                    <div class="version-node-meta">
                                                                        <div class="node-meta-line">
                                                                            <span>Enviada</span>
                                                                            <strong>{{ $version->submitted_at ?? '-' }}</strong>
                                                                        </div>
                                                                        <div class="node-meta-line">
                                                                            <span>Aprovada</span>
                                                                            <strong>{{ $version->approved_at ?? '-' }}</strong>
                                                                        </div>
                                                                    </div>

                                                                    <div class="version-node-actions">
                                                                        @if($file)
                                                                            <a class="btn-secondary btn-icon" href="{{ route('versions.files', $version->id) }}" title="Arquivos" aria-label="Arquivos">
                                                                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-files" viewBox="0 0 16 16" aria-hidden="true">
                                                                                    <path d="M13 0H6a2 2 0 0 0-2 2v1H2a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h7a2 2 0 0 0 2-2v-1h2a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2M5 2a1 1 0 0 1 1-1h7a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1h-2V5a2 2 0 0 0-2-2H5z"/>
                                                                                    <path d="M2 4a1 1 0 0 1 1-1h7a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H2z"/>
                                                                                </svg>
                                                                            </a>
                                                                            <a class="btn-secondary btn-icon" href="{{ route('project-version-file-download', $file->id) }}" title="Baixar ZIP" aria-label="Baixar ZIP">
                                                                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-download" viewBox="0 0 16 16" aria-hidden="true">
                                                                                    <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.6a.5.5 0 0 0 .5.5h13a.5.5 0 0 0 .5-.5v-2.6a.5.5 0 0 1 1 0v2.6A1.5 1.5 0 0 1 14.5 14.5h-13A1.5 1.5 0 0 1 0 13V10.4a.5.5 0 0 1 .5-.5"/>
                                                                                    <path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708z"/>
                                                                                </svg>
                                                                            </a>
                                                                        @else
                                                                            <span class="version-file-empty">Sem arquivo</span>
                                                                        @endif
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <div class="empty-state">Sem versões nessa subfolder.</div>
                                    @endif
                                </div>
                            @endforeach
                        @endif
                    </div>
                </section>

                <section
                    class="owner-panel-section project-panel panel-tasks"
                    id="student-panel-tasks"
                    data-owner-panel="tasks"
                    data-nav-label="tarefas"
                    data-nav-icon="tasks"
                    role="tabpanel"
                    hidden
                >
                    <div class="versions-section task-board" data-animate>
                        <div class="section-header">
                            <div>
                                <h3>Tarefas do projeto</h3>
                                <p class="section-sub">Acompanhe o status das tasks por coluna.</p>
                            </div>
                            @if($canManageTasks)
                            <button type="button" class="btn-secondary btn-compact" id="openTaskFormBtn">add task</button>
                            @endif
                        </div>

                        @if(!$project)
                            <div class="empty-state">Nenhum projeto selecionado.</div>
                        @else
                            <div class="task-board-body">
                                <div class="status-lists-container">
                                    @foreach($taskStatusColumns as $column)
                                        @php
                                            $tasksByStatus = $projectTasks->where('status', $column['status'])->values();
                                        @endphp
                                        <div
                                            class="status-list {{ $column['class'] }}"
                                            data-task-status="{{ $column['status'] }}"
                                            data-empty-message="{{ $column['empty'] }}"
                                        >
                                            @forelse($tasksByStatus as $task)
                                                @php
                                                    $taskDate = \Carbon\Carbon::parse($task->created_at)->format('d-m-Y');
                                                    $taskStatusClass = 'status-' . str_replace('_', '-', $task->status);
                                                    $taskVersionUrl = null;
                                                    $taskVersionSubfolderId = (int) ($task->version?->subfolder_id ?? ($projectSubfolders->first()?->id ?? 0));
                                                    if ($task->version && $taskVersionSubfolderId > 0) {
                                                        $taskVersionUrl = route('subfolder-index', [
                                                            'subfolder' => $taskVersionSubfolderId,
                                                            'highlight_version' => $task->version->id,
                                                        ]) . '#version-node-' . $task->version->id;
                                                    }
                                                @endphp
                                                <article
                                                    class="task {{ $task->version ? 'has-linked-version' : '' }}"
                                                    @if($canManageTasks)
                                                    draggable="true"
                                                    data-task-id="{{ $task->id }}"
                                                    data-task-status="{{ $task->status }}"
                                                    data-task-status-url="{{ route('task-status-update', $task->id) }}"
                                                    @endif
                                                >
                                                    <div class="right">
                                                        <h3 class="task-line">
                                                            <span>{{ $task->title }}</span>
                                                        </h3>
                                                        <h4 class="task-line">
                                                            <span>{{ $taskDate }}</span>
                                                        </h4>
                                                        @if($taskVersionUrl)
                                                            <a href="{{ $taskVersionUrl }}" class="task-version-link" title="Abrir versao vinculada no fluxo">
                                                                Versao V{{ $task->version->version_number }} - {{ $task->version->title }}
                                                            </a>
                                                        @endif
                                                    </div>
                                                    <div class="left">
                                                        <span class="task-state-pill {{ $taskStatusClass }}">
                                                            {{ $taskStatusLabels[$task->status] ?? ucfirst(str_replace('_', ' ', $task->status)) }}
                                                        </span>
                                                        @if($canManageTasks)
                                                            <a
                                                                href="#"
                                                                class="task-action-edit"
                                                                aria-label="Editar task"
                                                                data-task-id="{{ $task->id }}"
                                                                data-task-title="{{ $task->title }}"
                                                                data-task-description="{{ rawurlencode($task->description) }}"
                                                                data-task-version-id="{{ $task->version_id ?? '' }}"
                                                            >
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-pencil-square" viewBox="0 0 16 16" aria-hidden="true">
                                                                    <path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/>
                                                                    <path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5z"/>
                                                                </svg>
                                                            </a>
                                                            <form action="{{ route('task-destroy', $task->id) }}" method="POST" onsubmit="return confirm('Excluir esta task?');">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="task-action-delete" aria-label="Excluir task">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-trash3-fill" viewBox="0 0 16 16" aria-hidden="true">
                                                                        <path d="M11 1.5v1h3.5a.5.5 0 0 1 0 1h-.538l-.853 10.66A2 2 0 0 1 11.115 16h-6.23a2 2 0 0 1-1.994-1.84L2.038 3.5H1.5a.5.5 0 0 1 0-1H5v-1A1.5 1.5 0 0 1 6.5 0h3A1.5 1.5 0 0 1 11 1.5m-5 0v1h4v-1a.5.5 0 0 0-.5-.5h-3a.5.5 0 0 0-.5.5M4.5 5.029l.5 8.5a.5.5 0 1 0 .998-.06l-.5-8.5a.5.5 0 1 0-.998.06m6.53-.528a.5.5 0 0 0-.528.47l-.5 8.5a.5.5 0 0 0 .998.058l.5-8.5a.5.5 0 0 0-.47-.528M8 4.5a.5.5 0 0 0-.5.5v8.5a.5.5 0 0 0 1 0V5a.5.5 0 0 0-.5-.5"/>
                                                                    </svg>
                                                                </button>
                                                            </form>
                                                        @endif
                                                    </div>
                                                </article>
                                            @empty
                                                <div class="empty">{{ $column['empty'] }}</div>
                                            @endforelse
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </section>

                <section
                    class="owner-panel-section project-panel panel-calendar"
                    id="student-panel-calendar"
                    data-owner-panel="calendar"
                    data-nav-label="calendario"
                    data-nav-icon="calendar"
                    role="tabpanel"
                    hidden
                >
                    <div class="versions-section versions-section--wide" data-animate>
                    <div class="section-header">
                        <div>
                            <h3>Calendário</h3>
                            <p class="section-sub">Eventos do laboratório</p>
                        </div>
                    </div>

                    <div class="calendar-logs calendar-logs--split">
                        <div class="event-calendar compact">

                            <div class="body-calendar">
                                <div class="body-calendar-header">
                                    <a class="calendar-nav" href="?ano={{ $calendar['prev']['year'] }}&mes={{ $calendar['prev']['month'] }}{{ $calendarProjectQuery }}">‹</a>
                                    <span class="calendar-title">{{ $calendar['title'] }}</span>
                                    <a class="calendar-nav" href="?ano={{ $calendar['next']['year'] }}&mes={{ $calendar['next']['month'] }}{{ $calendarProjectQuery }}">›</a>
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
                                    @for ($i = 0; $i < ($calendar['leadingBlanks'] ?? 0); $i++)
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
                </section>
            </div>
        </div>
    </div>
</div>

@if($project && $canManageTasks)
<div class="version-overlay {{ $taskFormHasErrors ? 'is-open' : '' }}" id="taskOverlay"></div>

<form
    action="{{ $taskFormAction }}"
    method="POST"
    id="taskForm"
    class="version-form {{ $taskFormHasErrors ? 'is-open' : '' }}"
    data-create-action="{{ route('task-add') }}"
    data-edit-action-template="{{ route('task-edit', ['task' => '__TASK__']) }}"
>
    @csrf
    <input type="hidden" name="project_id" value="{{ $project->id }}">
    <input type="hidden" name="task_id" id="task-id" value="{{ old('task_id', $taskEditing?->id) }}">
    <input type="hidden" name="_task_form_mode" id="taskFormMode" value="{{ old('_task_form_mode', $taskFormMode) }}">
    <input type="hidden" name="_method" id="taskFormMethod" value="{{ $taskFormMode === 'edit' ? 'PUT' : '' }}" {{ $taskFormMode === 'edit' ? '' : 'disabled' }}>
    <button type="button" class="close-form" id="closeTaskForm">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
    </button>

    <div class="info-box">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor">
            <path d="M9 11h6a1 1 0 1 0 0-2H9a1 1 0 1 0 0 2m0 4h4a1 1 0 1 0 0-2H9a1 1 0 1 0 0 2m9-11h-1V3a1 1 0 1 0-2 0v1H9V3a1 1 0 1 0-2 0v1H6a3 3 0 0 0-3 3v11a3 3 0 0 0 3 3h12a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3m1 14a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V10h14z"/>
        </svg>
        <h3 id="taskFormTitle">{{ $taskFormTitle }}</h3>
    </div>

    <div class="body-form">
        @error('task_id', $taskFormErrorBag)
            <span class="error-message">{{ $message }}</span>
        @enderror
        <div class="form-group full-width">
            <label for="task-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 7h16M4 12h16M4 17h10"/>
                </svg>
                Titulo
            </label>
            <input
                id="task-title"
                name="title"
                type="text"
                value="{{ old('title', $taskEditing?->title) }}"
                placeholder="Ex: Ajustar fluxo de versoes"
                required
            >
            @error('title', $taskFormErrorBag)
                <span class="error-message">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group full-width">
            <label for="task-version-id">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M7 10h10M7 14h6M5 4h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/>
                </svg>
                Vincular versao (aprovada ou enviada)
            </label>
            <select id="task-version-id" class="version-subfolder-select" name="version_id">
                <option value="">Sem vinculo</option>
                @foreach($taskVersionOptions as $taskVersionOption)
                    <option value="{{ $taskVersionOption->id }}" {{ $taskFormVersionId === (int) $taskVersionOption->id ? 'selected' : '' }}>
                        V{{ $taskVersionOption->version_number }} - {{ $taskVersionOption->title }}
                    </option>
                @endforeach
            </select>
            <small class="form-help">Voce pode vincular uma versao aprovada ou enviada.</small>
            @error('version_id', $taskFormErrorBag)
                <span class="error-message">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group full-width">
            <label for="task-description">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="16" y1="13" x2="8" y2="13"/>
                    <line x1="16" y1="17" x2="8" y2="17"/>
                </svg>
                Descricao
            </label>
            <textarea
                id="task-description"
                name="description"
                rows="4"
                placeholder="Descreva a task..."
                required
            >{{ old('description', $taskEditing?->description) }}</textarea>
            @error('description', $taskFormErrorBag)
                <span class="error-message">{{ $message }}</span>
            @enderror
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn-submit">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
            <span id="taskFormSubmitLabel">{{ $taskFormSubmitLabel }}</span>
        </button>
    </div>
</form>
@endif

@push('scripts')
<script src="{{ asset('script/proj.js') }}"></script>
@endpush
@endif
@endsection

@section('modals')
@if($user->role === 'student' && $project && !$studentVersionLocked)
    <form action="{{ route('project-version-add') }}" method="POST" id="versionForm" class="version-form {{ $errors->any() ? 'is-open' : '' }}" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="project_id" value="{{ $project->id }}">
        <button type="button" class="close-form" id="closeVersionForm">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>

        <div class="info-box">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor">
                <path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10zm0-12H5V6h14v2z"/>
            </svg>
            <h3>Nova Versão</h3>
        </div>

        <div class="body-form">
            @if($projectSubfolders->isNotEmpty())
            <div class="form-group full-width">
                <label for="version-subfolder">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 6h5l2 2h11v10a2 2 0 0 1-2 2H3z"/>
                    </svg>
                    Subfolder
                </label>
                <select id="version-subfolder" class="version-subfolder-select" name="subfolder_id" required>
                    @foreach($projectSubfolders as $subfolder)
                        <option value="{{ $subfolder->id }}" {{ $selectedSubfolderId === (int) $subfolder->id ? 'selected' : '' }}>
                            {{ $subfolder->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            @endif

            <div class="form-group full-width">
                <label for="version-title">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 7h16M4 12h16M4 17h10"/>
                    </svg>
                    Titulo da Versão
                </label>
                <input
                    id="version-title"
                    name="title"
                    placeholder="Ex: Ajustes finais"
                    type="text"
                    value="{{ old('title', $project->title) }}"
                    required
                >
                @error('title')
                    <span class="error-message">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group full-width">
                <label for="version-description">
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
                    id="version-description"
                    name="description"
                    placeholder="Resumo do que mudou nesta Versão..."
                    rows="3"
                    required
                >{{ old('description') }}</textarea>
                @error('description')
                    <span class="error-message">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group full-width">
                <label for="version-file">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 5v14M5 12h14"/>
                    </svg>
                    Arquivo ZIP
                </label>
                <input
                    id="version-file"
                    name="version_file"
                    type="file"
                    accept=".zip"
                    {{ ($maxUploadMb ?? 0) > 0 ? '' : 'disabled' }}
                    required
                >
                @error('version_file')
                    <span class="error-message">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-submit">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                Adicionar Versão
            </button>
        </div>
    </form>
@endif
@endsection
