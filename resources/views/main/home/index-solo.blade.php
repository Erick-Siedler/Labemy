@extends('layouts.header-side-not')

@php
    $pageTitle = $pageTitle ?? 'Projetos';
    $pageBreadcrumbHome = $pageBreadcrumbHome ?? 'Início';
    $pageBreadcrumbCurrent = $pageBreadcrumbCurrent ?? 'Projetos';
    $labs = $labs ?? collect();
    $notifications = $notifications ?? collect();
    $projects = $projects ?? collect();

    $project = $project ?? ($selectedProject ?? $projects->first());
    $versions = $versions ?? collect();
    $maxUploadMb = $maxUploadMb ?? 0;
    $hasProject = !is_null($project);

    $projectSubfolders = ($project?->subfolders ?? collect())->sortBy('order_index')->values();
    $selectedSubfolderId = (int) old('subfolder_id', (int) ($projectSubfolders->first()?->id ?? 0));
    $normalizedVersions = $versions->map(function ($version) use ($projectSubfolders) {
        if (empty($version->subfolder_id) && $projectSubfolders->isNotEmpty()) {
            $version->subfolder_id = (int) $projectSubfolders->first()->id;
        }

        return $version;
    });
    $versionFlowRecentLimit = (int) ($versionFlowRecentLimit ?? 6);
    $totalVersions = $normalizedVersions->count();
    $latestVersion = $normalizedVersions
        ->sortByDesc(function ($version) {
            $base = $version->submitted_at ?? $version->created_at;
            return $base ? \Carbon\Carbon::parse($base)->getTimestamp() : 0;
        })
        ->first();
    $latestSubmittedAtFormatted = !empty($latestVersion?->submitted_at)
        ? \Carbon\Carbon::parse($latestVersion->submitted_at)->format('d/m/Y')
        : '-';

    $tasks = $tasks ?? collect();
    $projectTasks = $hasProject ? $tasks->where('project_id', $project->id) : collect();
    $approvedTaskVersions = $normalizedVersions
        ->where('status_version', 'approved')
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

@section('title', 'Projetos')

@if($theme === '"light"')
@push('styles')
<link rel="stylesheet" href="{{ asset('main/solo.css') }}">
@endpush
@else
@push('styles')
<link rel="stylesheet" href="{{ asset('main/solo-dark.css') }}">
@endpush
@endif

@section('overlays')
<div class="version-overlay {{ $errors->any() ? 'is-open' : '' }}" id="versionOverlay"></div>
<div class="version-overlay {{ $taskFormHasErrors ? 'is-open' : '' }}" id="taskOverlay"></div>
@endsection

@section('content')
<div class="container-info solo-dashboard">
    <div class="project-hero" data-animate>
        <div>
            <h2>{{ $hasProject ? $project->title : 'Nenhum projeto selecionado' }}</h2>
            <p class="project-sub">
                @if($hasProject)
                    {{ $project->lab->name ?? 'Projetos' }} · {{ $project->group->name ?? 'Geral' }}
                @else
                    Crie um projeto para começar.
                @endif
            </p>
        </div>
        <div class="project-actions">
            @if($hasProject)
            <a class="btn-secondary" href="{{ route('requirements.index', ['project' => $project->id, 'rf_page' => 1, 'rnf_global_page' => 1, 'rnf_linked_page' => 1]) }}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" class="bi bi-clipboard-check" style="width:16px;height:16px;" viewBox="0 0 16 16" aria-hidden="true">
                    <path fill-rule="evenodd" d="M10.854 7.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7.5 9.793l2.646-2.647a.5.5 0 0 1 .708 0"/>
                    <path d="M4 1.5H3a2 2 0 0 0-2 2V14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V3.5a2 2 0 0 0-2-2h-1v1h1a1 1 0 0 1 1 1V14a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V3.5a1 1 0 0 1 1-1h1z"/>
                    <path d="M9.5 1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-3a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 1 .5-.5zm-3-1A1.5 1.5 0 0 0 5 1.5v1A1.5 1.5 0 0 0 6.5 4h3A1.5 1.5 0 0 0 11 2.5v-1A1.5 1.5 0 0 0 9.5 0z"/>
                </svg>
                Requisitos
            </a>
            <button type="button" class="btn-secondary" id="openVersionFormBtnSecondary">
                <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" class="bi bi-box-arrow-up" style="width:16px;height:16px;" viewBox="0 0 16 16" aria-hidden="true">
                    <path fill-rule="evenodd" d="M3.5 6a.5.5 0 0 0-.5.5v8a.5.5 0 0 0 .5.5h9a.5.5 0 0 0 .5-.5v-8a.5.5 0 0 0-.5-.5h-2a.5.5 0 0 1 0-1h2A1.5 1.5 0 0 1 14 6.5v8a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 2 14.5v-8A1.5 1.5 0 0 1 3.5 5h2a.5.5 0 0 1 0 1z"/>
                    <path fill-rule="evenodd" d="M7.646.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1-.708.708L8.5 1.707V10.5a.5.5 0 0 1-1 0V1.707L5.354 3.854a.5.5 0 1 1-.708-.708z"/>
                </svg>
                Nova Versão
            </button>
            @endif
        </div>
    </div>

    <div class="owner-shell">
        <div class="owner-panel">
            <section class="owner-panel-section" id="project-panel-versions" data-owner-panel="versions" data-nav-label="fluxos" data-nav-icon="versions" role="tabpanel">
                <div class="versions-section versions-section--wide" data-animate>
                    <div class="summary-cards" data-animate>
                    <div class="stat-card tone-indigo">
                        <div class="icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-folder-fill" viewBox="0 0 16 16">
                                <path d="M9.828 3h3.982a2 2 0 0 1 1.992 2.181l-.637 7A2 2 0 0 1 13.174 14H2.825a2 2 0 0 1-1.991-1.819l-.637-7a2 2 0 0 1 .342-1.31L.5 3a2 2 0 0 1 2-2h3.672a2 2 0 0 1 1.414.586l.828.828A2 2 0 0 0 9.828 3m-8.322.12q.322-.119.684-.12h5.396l-.707-.707A1 1 0 0 0 6.172 2H2.5a1 1 0 0 0-1 .981z"/>
                            </svg>
                        </div>
                        <div class="info">
                            <h3>Total de subfolders</h3>
                            <h4>{{ $projectSubfolders->count() }}</h4>
                        </div>
                    </div>

                    <div class="stat-card tone-teal">
                        <div class="icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-layers-fill" viewBox="0 0 16 16">
                                <path d="M8.235.184a.5.5 0 0 0-.47 0L.148 3.892a.5.5 0 0 0 0 .916l7.617 3.708a.5.5 0 0 0 .47 0l7.617-3.708a.5.5 0 0 0 0-.916z"/>
                                <path d="M.148 6.274a.5.5 0 0 1 .67-.229L8 9.56l7.182-3.515a.5.5 0 1 1 .44.898l-7.4 3.624a.5.5 0 0 1-.444 0l-7.4-3.624a.5.5 0 0 1-.23-.669"/>
                                <path d="M.148 9.274a.5.5 0 0 1 .67-.229L8 12.56l7.182-3.515a.5.5 0 1 1 .44.898l-7.4 3.624a.5.5 0 0 1-.444 0l-7.4-3.624a.5.5 0 0 1-.23-.669"/>
                            </svg>
                        </div>
                        <div class="info">
                            <h3>Total de versões</h3>
                            <h4>{{ $totalVersions }}</h4>
                        </div>
                    </div>

                    <div class="stat-card tone-orange">
                        <div class="icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-box-arrow-up" viewBox="0 0 16 16">
                                <path fill-rule="evenodd" d="M3.5 6a.5.5 0 0 0-.5.5v8a.5.5 0 0 0 .5.5h9a.5.5 0 0 0 .5-.5v-8a.5.5 0 0 0-.5-.5h-2a.5.5 0 0 1 0-1h2A1.5 1.5 0 0 1 14 6.5v8a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 2 14.5v-8A1.5 1.5 0 0 1 3.5 5h2a.5.5 0 0 1 0 1z"/>
                                <path fill-rule="evenodd" d="M7.646.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1-.708.708L8.5 1.707V10.5a.5.5 0 0 1-1 0V1.707L5.354 3.854a.5.5 0 1 1-.708-.708z"/>
                            </svg>
                        </div>
                        <div class="info">
                            <h3>Última submissão</h3>
                            <h4>{{ $latestSubmittedAtFormatted }}</h4>
                        </div>
                    </div>   
                </div>
                    <div class="section-header">
                        <div>
                            <h3>Fluxo por subfolder</h3>
                            <p class="section-sub">Cada subfolder possui seu próprio fluxo de versões.</p>
                        </div>
                    </div>

                    @if(!$hasProject)
                        <div class="empty-state">Crie um projeto para adicionar versões.</div>
                    @elseif($projectSubfolders->isEmpty())
                        <div class="empty-state">Nenhuma subfolder encontrada para este projeto.</div>
                    @else
                        @foreach($projectSubfolders as $subfolder)
                            @php
                                $allSubfolderFlowVersions = $normalizedVersions
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

                            <div class="subfolder-flow">
                                <div class="subfolder-flow-head">
                                    <div>
                                        <h3>{{ $subfolder->name }}</h3>
                                        <div class="subfolder-flow-meta">
                                            <span class="subfolder-pill">Versões: {{ $allSubfolderFlowVersions->count() }}</span>
                                            <span class="subfolder-pill">Atual: {{ $latestSubfolderVersion?->version_number ?? '-' }}</span>
                                        </div>
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
                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" class="bi bi-folder-fill node-icon" viewBox="0 0 16 16">
                                                                <path d="M9.828 3h3.982a2 2 0 0 1 1.992 2.181l-.637 7A2 2 0 0 1 13.174 14H2.825a2 2 0 0 1-1.991-1.819l-.637-7a2 2 0 0 1 .342-1.31L.5 3a2 2 0 0 1 2-2h3.672a2 2 0 0 1 1.414.586l.828.828A2 2 0 0 0 9.828 3m-8.322.12q.322-.119.684-.12h5.396l-.707-.707A1 1 0 0 0 6.172 2H2.5a1 1 0 0 0-1 .981z"/>
                                                            </svg>
                                                            <span class="node-kicker">Subfolder</span>
                                                            <strong class="node-title">{{ $project->title }} - {{ $subfolder->name }}</strong>
                                                        </div>
                                                    </div>

                                                    @foreach($allSubfolderFlowVersions as $version)
                                                        @php $versionStatus = 'status-' . str_replace('_', '-', $version->status_version); @endphp
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
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <div class="subfolder-empty">Sem versões nessa subfolder.</div>
                                @endif
                            </div>
                        @endforeach
                    @endif
                </div>
            </section>

            <section class="owner-panel-section" id="project-panel-dashboard" data-owner-panel="dashboard" data-nav-label="tarefas" data-nav-icon="tasks" role="tabpanel">
                <div class="board">
                    <div class="board-header">
                        <div class="board-actions">
                            <button
                                type="button"
                                class="btn-secondary btn-compact"
                                id="openTaskFormBtn"
                                @if(!$hasProject) disabled title="Selecione ou crie um projeto para adicionar task" @endif
                            >
                                add task
                            </button>
                        </div>
                    </div>
                    <div class="board-body">
                        <div class="status-lists-container">
                            @php
                                $taskStatusColumns = [
                                    ['status' => 'draft', 'class' => 'draft', 'empty' => 'Nenhuma task em rascunho'],
                                    ['status' => 'approved', 'class' => 'approved', 'empty' => 'Nenhuma task aprovada'],
                                    ['status' => 'in_progress', 'class' => 'in-progress', 'empty' => 'Nenhuma task em progresso'],
                                    ['status' => 'done', 'class' => 'done', 'empty' => 'Nenhuma task terminada'],
                                ];
                            @endphp
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
                                            $taskData = \Carbon\Carbon::parse($task->created_at)->format('d-m-Y');
                                            $taskVersionUrl = null;
                                            $taskVersionSubfolderId = (int) ($task->version?->subfolder_id ?? ($projectSubfolders->first()?->id ?? 0));
                                            if ($task->version && $taskVersionSubfolderId > 0) {
                                                $taskVersionUrl = route('subfolder-index', [
                                                    'subfolder' => $taskVersionSubfolderId,
                                                    'highlight_version' => $task->version->id,
                                                ]) . '#version-node-' . $task->version->id;
                                            }
                                        @endphp
                                        <div
                                            class="task {{ $task->version ? 'has-linked-version' : '' }}"
                                            draggable="true"
                                            data-task-id="{{ $task->id }}"
                                            data-task-status="{{ $task->status }}"
                                            data-task-status-url="{{ route('task-status-update', $task->id) }}"
                                        >
                                            <div class="right">
                                                <h3 class="task-line">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-list-task" viewBox="0 0 16 16" aria-hidden="true">
                                                        <path fill-rule="evenodd" d="M2 2.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5V3a.5.5 0 0 0-.5-.5zM3 3H2v1h1z"/>
                                                        <path d="M5 3.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5M5.5 7a.5.5 0 0 0 0 1h9a.5.5 0 0 0 0-1zm0 4a.5.5 0 0 0 0 1h9a.5.5 0 0 0 0-1z"/>
                                                        <path fill-rule="evenodd" d="M1.5 7a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5H2a.5.5 0 0 1-.5-.5zM2 7h1v1H2zm0 3.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm1 .5H2v1h1z"/>
                                                    </svg>
                                                    <span>{{ $task->title }}</span>
                                                </h3>
                                                <h4 class="task-line">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-list-task" viewBox="0 0 16 16" aria-hidden="true">
                                                        <path fill-rule="evenodd" d="M2 2.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5V3a.5.5 0 0 0-.5-.5zM3 3H2v1h1z"/>
                                                        <path d="M5 3.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5M5.5 7a.5.5 0 0 0 0 1h9a.5.5 0 0 0 0-1zm0 4a.5.5 0 0 0 0 1h9a.5.5 0 0 0 0-1z"/>
                                                        <path fill-rule="evenodd" d="M1.5 7a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5H2a.5.5 0 0 1-.5-.5zM2 7h1v1H2zm0 3.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm1 .5H2v1h1z"/>
                                                    </svg>
                                                    <span>{{ $taskData }}</span>
                                                </h4>
                                                @if($taskVersionUrl)
                                                    <a href="{{ $taskVersionUrl }}" class="task-version-link" title="Abrir versão vinculada no fluxo">
                                                        Versão V{{ $task->version->version_number }} · {{ $task->version->title }}
                                                    </a>
                                                @endif
                                            </div>
                                            <div class="left">
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
                                            </div>
                                        </div>
                                    @empty
                                        <div class="empty">{{ $column['empty'] }}</div>
                                    @endforelse
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>
        </div>

        
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('script/proj.js') }}"></script>
@endpush

@section('modals')
@if($hasProject)
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
                    Título da Versão
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
                    placeholder="Resumo do que mudou nesta versão..."
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
                <small class="form-help">
                    Limite do plano: {{ $maxUploadMb ?? 0 }} MB
                </small>
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
                    Título
                </label>
                <input
                    id="task-title"
                    name="title"
                    type="text"
                    value="{{ old('title', $taskEditing?->title) }}"
                    placeholder="Ex: Ajustar fluxo de versões"
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
                    Vincular versão aprovada (opcional)
                </label>
                <select id="task-version-id" class="version-subfolder-select" name="version_id">
                    <option value="">Sem vínculo</option>
                    @foreach($approvedTaskVersions as $approvedVersion)
                        <option value="{{ $approvedVersion->id }}" {{ $taskFormVersionId === (int) $approvedVersion->id ? 'selected' : '' }}>
                            V{{ $approvedVersion->version_number }} - {{ $approvedVersion->title }}
                        </option>
                    @endforeach
                </select>
                <small class="form-help">Você pode vincular somente uma versão aprovada.</small>
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
                    Descrição
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
@endsection
