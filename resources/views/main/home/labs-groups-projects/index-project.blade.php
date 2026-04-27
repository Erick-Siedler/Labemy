@extends($layout ?? 'layouts.header-side-not')

@section('title', 'Projeto')

@if($theme === '"light"')
@push('styles')
<link rel="stylesheet" href="{{ asset('main/proj.css') }}">
<style>
.subfolder-flow {
    margin-top: 1rem;
}
.subfolder-flow-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 0.85rem;
    flex-wrap: wrap;
}
.subfolder-flow-meta {
    display: flex;
    gap: 0.65rem;
    flex-wrap: wrap;
    font-size: 0.8rem;
    color: #666;
}
.subfolder-pill {
    border: 1px solid #d4d4d4;
    border-radius: 999px;
    padding: 0.2rem 0.55rem;
    background: #f8f8f8;
}
.subfolder-empty {
    border: 1px dashed #d9d9d9;
    border-radius: 10px;
    padding: 1rem;
    color: #777;
    font-size: 0.9rem;
}
.project-overview .version-board-viewport {
    min-height: 220px;
    max-height: 360px;
}
.project-overview .version-board-canvas {
    padding: 2.5rem 2.25rem 4.5rem;
}
.project-overview .version-board-lane {
    align-items: center;
}
.project-overview .node-icon {
    width: 18px;
    height: 18px;
    color: #555;
}
</style>
@endpush
@else
@push('styles')
<link rel="stylesheet" href="{{ asset('main/proj-dark.css') }}">
<style>
.subfolder-flow {
    margin-top: 1rem;
}
.subfolder-flow-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 0.85rem;
    flex-wrap: wrap;
}
.subfolder-flow-meta {
    display: flex;
    gap: 0.65rem;
    flex-wrap: wrap;
    font-size: 0.8rem;
    color: #c1c7d0;
}
.subfolder-pill {
    border: 1px solid #2a313b;
    border-radius: 999px;
    padding: 0.2rem 0.55rem;
    background: #1a2027;
}
.subfolder-empty {
    border: 1px dashed #2a313b;
    border-radius: 10px;
    padding: 1rem;
    color: #aab2bd;
    font-size: 0.9rem;
}
.project-overview .version-board-viewport {
    min-height: 220px;
    max-height: 360px;
}
.project-overview .version-board-canvas {
    padding: 2.5rem 2.25rem 4.5rem;
}
.project-overview .version-board-lane {
    align-items: center;
}
.project-overview .node-icon {
    width: 18px;
    height: 18px;
    color: #aab2bd;
}
</style>
@endpush
@endif

@section('content')
@php
    $subfolders = $subfolders ?? collect();
    $subfolderVersions = $subfolderVersions ?? collect();
    $totalVersions = $totalVersions ?? 0;
    $latestVersion = $latestVersion ?? null;
    $isStudentView = (($user->role ?? '') === 'student');
    $isAssistantView = in_array(($user->role ?? ''), ['assistant', 'assitant'], true);
    $tasks = $tasks ?? collect();
    $projectTasks = $tasks->where('project_id', $project->id)->values();
    $canManageTasks = (bool) ($canManageTasks ?? false);
    $defaultTaskSubfolderId = (int) ($subfolders->first()?->id ?? 0);
    $taskStatusColumns = [
        ['status' => 'draft', 'class' => 'draft', 'empty' => 'Nenhuma task em rascunho'],
        ['status' => 'approved', 'class' => 'approved', 'empty' => 'Nenhuma task aprovada'],
        ['status' => 'in_progress', 'class' => 'in-progress', 'empty' => 'Nenhuma task em progresso'],
        ['status' => 'done', 'class' => 'done', 'empty' => 'Nenhuma task concluida'],
    ];
    $taskStatusLabels = [
        'draft' => 'Rascunho',
        'approved' => 'Aprovada',
        'in_progress' => 'Em progresso',
        'done' => 'Concluida',
    ];
    $approvedTaskVersions = $subfolderVersions
        ->values()
        ->flatten(1)
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

    $latestSubmittedAtFormatted = !empty($latestSubmittedAt)
        ? \Carbon\Carbon::parse($latestSubmittedAt)->format('d/m/Y')
        : '-';

    $latestApprovedAtFormatted = !empty($latestApprovedAt)
        ? \Carbon\Carbon::parse($latestApprovedAt)->format('d/m/Y')
        : '-';
    $versionFlowRecentLimit = (int) ($versionFlowRecentLimit ?? 6);
@endphp

<div class="container-info project-dashboard project-overview">
    <div class="project-hero" data-animate>
        <div>
            <h2>{{ $project->title }}</h2>
            <p class="project-sub">{{ $lab->name }} · {{ $group->name }}</p>
        </div>
        <div class="project-actions">
            @if(!$isStudentView)
            <a class="btn-secondary" href="{{ route('group.index', $group->id) }}">
                @if($theme === '"light"' || $theme === '"automatic"')
                <svg class="group" fill="#333" style="width:16px;height:16px;" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M24,15.9c0-2.8-1.5-5-3.7-6.1C21.3,8.8,22,7.5,22,6c0-2.8-2.2-5-5-5c-2.1,0-3.8,1.2-4.6,3c-0.1,0-0.3,0-0.4,0 c-0.1,0-0.3,0-0.4,0C10.8,2.2,9.1,1,7,1C4.2,1,2,3.2,2,6c0,1.5,0.7,2.8,1.7,3.8C1.5,10.9,0,13.2,0,15.9V20h5v3h14v-3h5V15.9z M17,3 c1.7,0,3,1.3,3,3c0,1.6-1.3,3-3,3c0-1.9-1.1-3.5-2.7-4.4C14.8,3.6,15.8,3,17,3z M15,9c0,1.7-1.3,3-3,3S9,10.7,9,9s1.3-3,3-3 S15,7.3,15,9z M7,3c1.2,0,2.2,0.6,2.7,1.6C8.1,5.5,7,7.1,7,9C5.3,9,4,7.7,4,6S5.3,3,7,3z M5.1,18H2v-2.1C2,13.1,4.1,11,7,11 c0.1,0,0.2,0,0.3,0c0.3,0.7,0.8,1.3,1.3,1.8C6.7,13.8,5.4,15.7,5.1,18z M17,21H7v-2.1c0-2.8,2.2-4.9,5-4.9c2.9,0,5,2.1,5,4.9V21z M22,18h-3.1c-0.3-2.3-1.7-4.2-3.7-5.2c0.6-0.5,1-1.1,1.3-1.8c0.1,0,0.2,0,0.4,0c2.9,0,5,2.1,5,4.9V18z"/>
                </svg>
                @else
                <svg class="group" fill="#ffffff" style="width:16px;height:16px;" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M24,15.9c0-2.8-1.5-5-3.7-6.1C21.3,8.8,22,7.5,22,6c0-2.8-2.2-5-5-5c-2.1,0-3.8,1.2-4.6,3c-0.1,0-0.3,0-0.4,0 c-0.1,0-0.3,0-0.4,0C10.8,2.2,9.1,1,7,1C4.2,1,2,3.2,2,6c0,1.5,0.7,2.8,1.7,3.8C1.5,10.9,0,13.2,0,15.9V20h5v3h14v-3h5V15.9z M17,3 c1.7,0,3,1.3,3,3c0,1.6-1.3,3-3,3c0-1.9-1.1-3.5-2.7-4.4C14.8,3.6,15.8,3,17,3z M15,9c0,1.7-1.3,3-3,3S9,10.7,9,9s1.3-3,3-3 S15,7.3,15,9z M7,3c1.2,0,2.2,0.6,2.7,1.6C8.1,5.5,7,7.1,7,9C5.3,9,4,7.7,4,6S5.3,3,7,3z M5.1,18H2v-2.1C2,13.1,4.1,11,7,11 c0.1,0,0.2,0,0.3,0c0.3,0.7,0.8,1.3,1.3,1.8C6.7,13.8,5.4,15.7,5.1,18z M17,21H7v-2.1c0-2.8,2.2-4.9,5-4.9c2.9,0,5,2.1,5,4.9V21z M22,18h-3.1c-0.3-2.3-1.7-4.2-3.7-5.2c0.6-0.5,1-1.1,1.3-1.8c0.1,0,0.2,0,0.4,0c2.9,0,5,2.1,5,4.9V18z"/>
                </svg>
                @endif
                Grupo
            </a>
            @if(!$isAssistantView)
            <a class="btn-secondary" href="{{ route('lab.index', $lab->id) }}">
                @if($theme === '"light"' || $theme === '"automatic"')
                <svg class="glass" fill="#333" style="width:16px;height:16px;" viewBox="0 0 31.166 31.166" aria-hidden="true">
                    <path d="M28.055,24.561l-7.717-11.044V3.442c0.575-0.197,0.99-0.744,0.99-1.386V1.464C21.329,0.657,20.673,0,19.866,0h-8.523 c-0.807,0-1.464,0.657-1.464,1.464v0.593c0,0.642,0.416,1.189,0.992,1.386v10l-7.76,11.118c-0.898,1.289-1.006,2.955-0.28,4.348 c0.727,1.393,2.154,2.258,3.725,2.258h18.056c1.571,0,2.999-0.866,3.725-2.259C29.062,27.514,28.954,25.848,28.055,24.561z M17.505,3.048v11.21c0,0.097,0.029,0.191,0.085,0.27l2.028,2.904h-8.077l0.906-1.298h3.135c0.261,0,0.472-0.211,0.472-0.473 c0-0.261-0.211-0.472-0.472-0.472h-2.476l0.512-0.733c0.055-0.08,0.084-0.173,0.084-0.271v-0.294h1.879 c0.261,0,0.472-0.211,0.472-0.472c0-0.261-0.211-0.472-0.472-0.472h-1.879v-1.299h1.879c0.261,0,0.472-0.211,0.472-0.472 c0-0.261-0.211-0.472-0.472-0.472h-1.879V9.405h1.879c0.261,0,0.472-0.211,0.472-0.472c0-0.261-0.211-0.473-0.472-0.473h-1.879 V7.162h1.879c0.261,0,0.472-0.211,0.472-0.472c0-0.261-0.211-0.472-0.472-0.472h-1.879v-3.17H17.505z M25.825,27.598 c-0.236,0.453-0.702,0.734-1.213,0.734H6.556c-0.511,0-0.976-0.282-1.212-0.734c-0.237-0.453-0.202-0.994,0.09-1.414l5.448-7.807 h9.396l5.454,7.805C26.025,26.602,26.06,27.145,25.825,27.598z"></path>
                    <path d="M15.583,19.676h-3.272c-0.261,0-0.472,0.211-0.472,0.473c0,0.261,0.211,0.472,0.472,0.472h3.272 c0.261,0,0.472-0.211,0.472-0.472C16.056,19.887,15.845,19.676,15.583,19.676z"></path>
                    <circle cx="10.113" cy="25.402" r="1.726"></circle>
                    <circle cx="17.574" cy="22.321" r="0.512"></circle>
                    <circle cx="20.977" cy="25.302" r="0.904"></circle>
                    <circle cx="14.723" cy="25.174" r="0.776"></circle>
                </svg>
                @else
                <svg class="glass" fill="#ffffff" style="width:16px;height:16px;" viewBox="0 0 31.166 31.166" aria-hidden="true">
                    <path d="M28.055,24.561l-7.717-11.044V3.442c0.575-0.197,0.99-0.744,0.99-1.386V1.464C21.329,0.657,20.673,0,19.866,0h-8.523 c-0.807,0-1.464,0.657-1.464,1.464v0.593c0,0.642,0.416,1.189,0.992,1.386v10l-7.76,11.118c-0.898,1.289-1.006,2.955-0.28,4.348 c0.727,1.393,2.154,2.258,3.725,2.258h18.056c1.571,0,2.999-0.866,3.725-2.259C29.062,27.514,28.954,25.848,28.055,24.561z M17.505,3.048v11.21c0,0.097,0.029,0.191,0.085,0.27l2.028,2.904h-8.077l0.906-1.298h3.135c0.261,0,0.472-0.211,0.472-0.473 c0-0.261-0.211-0.472-0.472-0.472h-2.476l0.512-0.733c0.055-0.08,0.084-0.173,0.084-0.271v-0.294h1.879 c0.261,0,0.472-0.211,0.472-0.472c0-0.261-0.211-0.472-0.472-0.472h-1.879v-1.299h1.879c0.261,0,0.472-0.211,0.472-0.472 c0-0.261-0.211-0.472-0.472-0.472h-1.879V9.405h1.879c0.261,0,0.472-0.211,0.472-0.472c0-0.261-0.211-0.473-0.472-0.473h-1.879 V7.162h1.879c0.261,0,0.472-0.211,0.472-0.472c0-0.261-0.211-0.472-0.472-0.472h-1.879v-3.17H17.505z M25.825,27.598 c-0.236,0.453-0.702,0.734-1.213,0.734H6.556c-0.511,0-0.976-0.282-1.212-0.734c-0.237-0.453-0.202-0.994,0.09-1.414l5.448-7.807 h9.396l5.454,7.805C26.025,26.602,26.06,27.145,25.825,27.598z"></path>
                    <path d="M15.583,19.676h-3.272c-0.261,0-0.472,0.211-0.472,0.473c0,0.261,0.211,0.472,0.472,0.472h3.272 c0.261,0,0.472-0.211,0.472-0.472C16.056,19.887,15.845,19.676,15.583,19.676z"></path>
                    <circle cx="10.113" cy="25.402" r="1.726"></circle>
                    <circle cx="17.574" cy="22.321" r="0.512"></circle>
                    <circle cx="20.977" cy="25.302" r="0.904"></circle>
                    <circle cx="14.723" cy="25.174" r="0.776"></circle>
                </svg>
                @endif
                Laboratório
            </a>
            @endif
            @endif
            <a class="btn-secondary" href="{{ route('requirements.index', ['project' => $project->id, 'rf_page' => 1, 'rnf_global_page' => 1, 'rnf_linked_page' => 1]) }}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" class="bi bi-clipboard-check" style="width:16px;height:16px;" viewBox="0 0 16 16" aria-hidden="true">
                    <path fill-rule="evenodd" d="M10.854 7.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7.5 9.793l2.646-2.647a.5.5 0 0 1 .708 0"/>
                    <path d="M4 1.5H3a2 2 0 0 0-2 2V14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V3.5a2 2 0 0 0-2-2h-1v1h1a1 1 0 0 1 1 1V14a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V3.5a1 1 0 0 1 1-1h1z"/>
                    <path d="M9.5 1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-3a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 1 .5-.5zm-3-1A1.5 1.5 0 0 0 5 1.5v1A1.5 1.5 0 0 0 6.5 4h3A1.5 1.5 0 0 0 11 2.5v-1A1.5 1.5 0 0 0 9.5 0z"/>
                </svg>
                Requisitos
            </a>
        </div>
    </div>

    <div class="owner-shell">
        <div class="owner-panel">
            <section class="owner-panel-section" id="project-panel-versions" data-owner-panel="versions" data-nav-label="fluxos" data-nav-icon="versions" role="tabpanel">
            <div class="summary-cards" data-animate>
                <div class="stat-card tone-indigo">
                    <div class="icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-folder-fill" viewBox="0 0 16 16">
                        <path d="M9.828 3h3.982a2 2 0 0 1 1.992 2.181l-.637 7A2 2 0 0 1 13.174 14H2.825a2 2 0 0 1-1.991-1.819l-.637-7a2 2 0 0 1 .342-1.31L.5 3a2 2 0 0 1 2-2h3.672a2 2 0 0 1 1.414.586l.828.828A2 2 0 0 0 9.828 3m-8.322.12q.322-.119.684-.12h5.396l-.707-.707A1 1 0 0 0 6.172 2H2.5a1 1 0 0 0-1 .981z"/>
                        </svg>
                    </div>
                    <div class="info">
                        <h3>Total de subfolders</h3>
                        <h4>{{ $subfolders->count() }}</h4>
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

                <div class="stat-card tone-green">
                    <div class="icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                        </svg>
                    </div>
                    <div class="info">
                        <h3>Última aprovação</h3>
                        <h4>{{ $latestApprovedAtFormatted }}</h4>
                    </div>
                </div>
            </div>

            <div class="versions-section" data-animate>
                <div class="section-header">
                    <div>
                        <h3>Fluxo por subfolder</h3>
                        <p class="section-sub">Cada subfolder possui seu próprio fluxo de versões.</p>
                    </div>
                </div>

                @forelse($subfolders as $subfolder)
                    @php
                        $allFlowVersions = ($subfolderVersions->get($subfolder->id, collect()) ?? collect())
                            ->sortBy('version_number')
                            ->values();
                        $recentFlowVersionIds = $allFlowVersions
                            ->sortByDesc('version_number')
                            ->take($versionFlowRecentLimit)
                            ->pluck('id')
                            ->map(fn ($id) => (int) $id)
                            ->all();
                        $hiddenFlowCount = max(0, $allFlowVersions->count() - count($recentFlowVersionIds));
                        $latest = $allFlowVersions->sortByDesc('version_number')->first();
                    @endphp

                    <div class="subfolder-flow">
                        <div class="subfolder-flow-head">
                                <div>
                                    <h3>{{ $subfolder->name }}</h3>
                                    <div class="subfolder-flow-meta">
                                        <span class="subfolder-pill">Versões: {{ $allFlowVersions->count() }}</span>
                                        <span class="subfolder-pill">Atual: {{ $latest?->version_number ?? '-' }}</span>
                                    </div>
                                </div>
                            <a class="btn-secondary" href="{{ route('subfolder-index', $subfolder->id) }}">Abrir subfolder</a>
                        </div>

                        @if($allFlowVersions->isNotEmpty())
                            <div class="version-board" data-version-board data-flow-board>
                                <div class="version-board-toolbar">
                                    <span class="board-hint">Arraste para navegar no quadro</span>
                                    <div class="board-actions">
                                        <button type="button" class="btn-secondary btn-compact" data-board-reset>Centralizar</button>
                                        @if($hiddenFlowCount > 0)
                                        <button
                                            type="button"
                                            class="btn-secondary btn-compact"
                                            data-flow-toggle
                                            data-label-expand="Ver fluxo completo ({{ $allFlowVersions->count() }})"
                                            data-label-collapse="Mostrar apenas recentes"
                                        >
                                            Ver fluxo completo ({{ $allFlowVersions->count() }})
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

                                            @foreach($allFlowVersions as $version)
                                                @php $versionStatus = 'status-' . str_replace('_', '-', $version->status_version); @endphp
                                                @php $isHiddenFlowVersion = !in_array((int) $version->id, $recentFlowVersionIds, true); @endphp
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
                @empty
                    <div class="empty-state">Nenhuma subfolder encontrada para este projeto.</div>
                @endforelse
            </div>
            </section>

            <section class="owner-panel-section" id="project-panel-dashboard" data-owner-panel="dashboard" data-nav-label="tarefas" data-nav-icon="tasks" role="tabpanel" hidden>
            <div class="versions-section task-board" data-animate>
                <div class="section-header">
                    <div>
                        <h3>Tarefas do projeto</h3>
                        <p class="section-sub">As tasks deste quadro pertencem somente a este projeto.</p>
                    </div>
                    @if($canManageTasks)
                    <button type="button" class="btn-secondary btn-compact" id="openTaskFormBtn">add task</button>
                    @endif
                </div>

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
                                        $taskVersionSubfolderId = (int) ($task->version?->subfolder_id ?? $defaultTaskSubfolderId);
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
                                                <a href="{{ $taskVersionUrl }}" class="task-version-link" title="Abrir versão vinculada no fluxo">
                                                    Versão V{{ $task->version->version_number }} · {{ $task->version->title }}
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
            </div>
            </section>
        </div>
    </div>
</div>

@if($canManageTasks)
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

@push('scripts')
<script src="{{ asset('script/proj.js') }}"></script>
@endpush
@endsection


