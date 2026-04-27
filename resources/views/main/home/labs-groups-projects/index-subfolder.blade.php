@extends($layout ?? 'layouts.header-side-not')

@section('title', 'Subfolder')

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
@php
    $latestVersion = $latestVersion ?? null;
    $versionStats = $versionStats ?? ['draft' => 0, 'submitted' => 0, 'approved' => 0, 'rejected' => 0];
    $projectStatus = 'status-' . str_replace('_', '-', $project->status);
    $latestSubmittedAt = $latestVersion?->submitted_at ?? $project->submitted_at;
    $latestApprovedAt = $latestVersion?->approved_at ?? $project->approved_at;
    $submittedAtFormatted = $latestSubmittedAt
        ? \Carbon\Carbon::parse($latestSubmittedAt)->format('d/m/Y')
        : '-';
    $approvedAtFormatted = $latestApprovedAt
        ? \Carbon\Carbon::parse($latestApprovedAt)->format('d/m/Y')
        : '-';
    $isStudentView = (($user->role ?? '') === 'student');
    $isAssistantView = in_array(($user->role ?? ''), ['assistant', 'assitant'], true);
    $isSoloView = (($user->role ?? '') === 'owner') && ((string) ($user->plan ?? '') === 'solo');
    $studentStatusBlocked = $isStudentView && !in_array((string) ($project->status ?? ''), ['approved', 'in_progress'], true);
    $studentVersionLocked = $isStudentView && (($latestVersion?->status_version ?? null) === 'submitted');
    $canOpenVersionForm = !empty($canAddVersion)
        && !$isAssistantView
        && (!$isStudentView || (!$studentStatusBlocked && !$studentVersionLocked));
    $canDeleteVersion = $canDeleteVersion ?? ($canEditVersion ?? false);
    $versionFlowRecentLimit = (int) ($versionFlowRecentLimit ?? 6);
    $highlightVersionId = max(0, (int) request('highlight_version', 0));
    $projectBackUrl = $projectBackUrl
        ?? (($isStudentView || $isAssistantView)
            ? route('subuser-home', ['project' => $project->id, 'group' => $group->id ?? null])
            : route('project.index', $project->id));
@endphp

<div class="container-info project-dashboard">
    <div class="project-hero" data-animate>
        <div>
            <h2>{{ $subfolder->name ?? 'Subfolder' }}</h2>
            @if($isSoloView)
                <p class="project-sub">Projeto {{ $project->title }}</p>
            @else
                <p class="project-sub">Projeto {{ $project->title }} · {{ $lab->name }} · {{ $group->name }}</p>
            @endif
        </div>
        <div class="project-actions">
            <a class="btn-secondary" title="Ver Projeto" href="{{ $projectBackUrl }}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" class="bi bi-journal" style="width:16px;height:16px;" viewBox="0 0 16 16" aria-hidden="true">
                    <path d="M3 0h10a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2v-1h1v1a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1H3a1 1 0 0 0-1 1v1H1V2a2 2 0 0 1 2-2"/>
                    <path d="M1 5v-.5a.5.5 0 0 1 1 0V5h.5a.5.5 0 0 1 0 1h-2a.5.5 0 0 1 0-1zm0 3v-.5a.5.5 0 0 1 1 0V8h.5a.5.5 0 0 1 0 1h-2a.5.5 0 0 1 0-1zm0 3v-.5a.5.5 0 0 1 1 0v.5h.5a.5.5 0 0 1 0 1h-2a.5.5 0 0 1 0-1z"/>
                </svg>
                Projeto
            </a>
            @if(!$isStudentView && !$isSoloView)
            <a class="btn-secondary" title="Ver Grupo" href="{{ route('group.index', $group->id) }}">
                <svg class="group" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M24,15.9c0-2.8-1.5-5-3.7-6.1C21.3,8.8,22,7.5,22,6c0-2.8-2.2-5-5-5c-2.1,0-3.8,1.2-4.6,3c-0.1,0-0.3,0-0.4,0 c-0.1,0-0.3,0-0.4,0C10.8,2.2,9.1,1,7,1C4.2,1,2,3.2,2,6c0,1.5,0.7,2.8,1.7,3.8C1.5,10.9,0,13.2,0,15.9V20h5v3h14v-3h5V15.9z M17,3 c1.7,0,3,1.3,3,3c0,1.6-1.3,3-3,3c0-1.9-1.1-3.5-2.7-4.4C14.8,3.6,15.8,3,17,3z M15,9c0,1.7-1.3,3-3,3S9,10.7,9,9s1.3-3,3-3 S15,7.3,15,9z M7,3c1.2,0,2.2,0.6,2.7,1.6C8.1,5.5,7,7.1,7,9C5.3,9,4,7.7,4,6S5.3,3,7,3z M5.1,18H2v-2.1C2,13.1,4.1,11,7,11 c0.1,0,0.2,0,0.3,0c0.3,0.7,0.8,1.3,1.3,1.8C6.7,13.8,5.4,15.7,5.1,18z M17,21H7v-2.1c0-2.8,2.2-4.9,5-4.9c2.9,0,5,2.1,5,4.9V21z M22,18h-3.1c-0.3-2.3-1.7-4.2-3.7-5.2c0.6-0.5,1-1.1,1.3-1.8c0.1,0,0.2,0,0.4,0c2.9,0,5,2.1,5,4.9V18z"/>
                </svg>
                Grupo
            </a>
            @endif
            @if(!$isStudentView && !$isSoloView && !$isAssistantView)
            <a class="btn-secondary" title="Ver Laboratório" href="{{ route('lab.index', $lab->id) }}">
                <svg class="glass" fill="currentColor" viewBox="0 0 31.166 31.166" aria-hidden="true">
                    <path d="M28.055,24.561l-7.717-11.044V3.442c0.575-0.197,0.99-0.744,0.99-1.386V1.464C21.329,0.657,20.673,0,19.866,0h-8.523 c-0.807,0-1.464,0.657-1.464,1.464v0.593c0,0.642,0.416,1.189,0.992,1.386v10l-7.76,11.118c-0.898,1.289-1.006,2.955-0.28,4.348 c0.727,1.393,2.154,2.258,3.725,2.258h18.056c1.571,0,2.999-0.866,3.725-2.259C29.062,27.514,28.954,25.848,28.055,24.561z M17.505,3.048v11.21c0,0.097,0.029,0.191,0.085,0.27l2.028,2.904h-8.077l0.906-1.298h3.135c0.261,0,0.472-0.211,0.472-0.473 c0-0.261-0.211-0.472-0.472-0.472h-2.476l0.512-0.733c0.055-0.08,0.084-0.173,0.084-0.271v-0.294h1.879 c0.261,0,0.472-0.211,0.472-0.472c0-0.261-0.211-0.472-0.472-0.472h-1.879v-1.299h1.879c0.261,0,0.472-0.211,0.472-0.472 c0-0.261-0.211-0.472-0.472-0.472h-1.879V9.405h1.879c0.261,0,0.472-0.211,0.472-0.472c0-0.261-0.211-0.473-0.472-0.473h-1.879 V7.162h1.879c0.261,0,0.472-0.211,0.472-0.472c0-0.261-0.211-0.472-0.472-0.472h-1.879v-3.17H17.505z M25.825,27.598 c-0.236,0.453-0.702,0.734-1.213,0.734H6.556c-0.511,0-0.976-0.282-1.212-0.734c-0.237-0.453-0.202-0.994,0.09-1.414l5.448-7.807 h9.396l5.454,7.805C26.025,26.602,26.06,27.145,25.825,27.598z"/>
                    <path d="M15.583,19.676h-3.272c-0.261,0-0.472,0.211-0.472,0.473c0,0.261,0.211,0.472,0.472,0.472h3.272 c0.261,0,0.472-0.211,0.472-0.472C16.056,19.887,15.845,19.676,15.583,19.676z"/>
                    <circle cx="10.113" cy="25.402" r="1.726"></circle>
                    <circle cx="17.574" cy="22.321" r="0.512"></circle>
                    <circle cx="20.977" cy="25.302" r="0.904"></circle>
                    <circle cx="14.723" cy="25.174" r="0.776"></circle>
                </svg>
                Laboratório
            </a>
            @endif
        </div>
    </div>

    <div class="owner-shell">
        <div class="owner-panel">
            @if(!$isStudentView)
            <section class="owner-panel-section" id="project-panel-dashboard" data-owner-panel="dashboard" data-nav-label="dashboard" data-nav-icon="dashboard" role="tabpanel" hidden>
                <div class="summary-cards" data-animate>
                    <div class="stat-card">
                        @if ($project->status == 'approved')
                        <div class="icon green">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-check-circle" viewBox="0 0 16 16">
                            <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/>
                            <path d="m10.97 4.97-.02.022-3.473 4.425-2.093-2.094a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-1.071-1.05"/>
                            </svg>
                        </div>
                        <div class="info">
                            <h3>Status atual</h3>
                            <span class="status-badge {{ $projectStatus }}">Aprovado</span>
                        </div> 
                        @elseif ($project->status === 'in_progress')
                        <div class="icon orange">
                            <svg viewBox="0 0 16 16" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" fill="orange"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path fill="orange" d="M0 5v6h16v-6h-16zM15 10h-14v-4h14v4z"></path> <path fill="orange" d="M2 7h7v2h-7v-2z"></path> </g></svg>
                        </div>
                        <div class="info">
                            <h3>Status atual</h3>
                            <span class="status-badge {{ $projectStatus }}">Em andamento</span>
                        </div> 
                        @elseif ($project->status === 'rejected')
                        <div class="icon red">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-x-circle" viewBox="0 0 16 16">
                            <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/>
                            <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708"/>
                            </svg>
                        </div>
                        <div class="info">
                            <h3>Status atual</h3>
                            <span class="status-badge {{ $projectStatus }}">Rejeitado</span>
                        </div> 
                        @else
                        <div class="icon yellow">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-earmark-fill" viewBox="0 0 16 16">
                            <path d="M4 0h5.293A1 1 0 0 1 10 .293L13.707 4a1 1 0 0 1 .293.707V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2m5.5 1.5v2a1 1 0 0 0 1 1h2z"/>
                            </svg>
                        </div>
                        <div class="info">
                            <h3>Status atual</h3>
                            <span class="status-badge {{ $projectStatus }}">Arquivado</span>
                        </div> 
                        @endif
                    </div>

                    <div class="stat-card tone-indigo">
                        <div class="icon">
                            <svg viewBox="0 0 24 24" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" fill="#3f51b5"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <g id="??-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd"> <g id="System" transform="translate(-1488.000000, -48.000000)" fill-rule="nonzero"> <g id="version_fill" transform="translate(1488.000000, 48.000000)"> <path d="M24,0 L24,24 L0,24 L0,0 L24,0 Z M12.5934901,23.257841 L12.5819402,23.2595131 L12.5108777,23.2950439 L12.4918791,23.2987469 L12.4918791,23.2987469 L12.4767152,23.2950439 L12.4056548,23.2595131 C12.3958229,23.2563662 12.3870493,23.2590235 12.3821421,23.2649074 L12.3780323,23.275831 L12.360941,23.7031097 L12.3658947,23.7234994 L12.3769048,23.7357139 L12.4804777,23.8096931 L12.4953491,23.8136134 L12.4953491,23.8136134 L12.5071152,23.8096931 L12.6106902,23.7357139 L12.6232938,23.7196733 L12.6232938,23.7196733 L12.6266527,23.7031097 L12.609561,23.275831 C12.6075724,23.2657013 12.6010112,23.2592993 12.5934901,23.257841 L12.5934901,23.257841 Z M12.8583906,23.1452862 L12.8445485,23.1473072 L12.6598443,23.2396597 L12.6498822,23.2499052 L12.6498822,23.2499052 L12.6471943,23.2611114 L12.6650943,23.6906389 L12.6699349,23.7034178 L12.6699349,23.7034178 L12.678386,23.7104931 L12.8793402,23.8032389 C12.8914285,23.8068999 12.9022333,23.8029875 12.9078286,23.7952264 L12.9118235,23.7811639 L12.8776777,23.1665331 C12.8752882,23.1545897 12.8674102,23.1470016 12.8583906,23.1452862 L12.8583906,23.1452862 Z M12.1430473,23.1473072 C12.1332178,23.1423925 12.1221763,23.1452606 12.1156365,23.1525954 L12.1099173,23.1665331 L12.0757714,23.7811639 C12.0751323,23.7926639 12.0828099,23.8018602 12.0926481,23.8045676 L12.108256,23.8032389 L12.3092106,23.7104931 L12.3186497,23.7024347 L12.3186497,23.7024347 L12.3225043,23.6906389 L12.340401,23.2611114 L12.337245,23.2485176 L12.337245,23.2485176 L12.3277531,23.2396597 L12.1430473,23.1473072 Z" id="MingCute" fill-rule="nonzero"> </path> <path d="M20.2451,14.75 C21.18,15.3637 21.1371,16.7868 20.1165,17.3263 L12.9348,21.1225 C12.35,21.4316 11.6503,21.4316 11.0655,21.1225 L3.88381,17.3263 C2.86325,16.7868 2.82034,15.3638 3.75508,14.75 L3.817995,14.7892 L3.817995,14.7892 L11.0654,18.6222 C11.6502,18.9313 12.35,18.9313 12.9347,18.6222 L20.1164,14.826 C20.1612,14.8023 20.2041,14.7769 20.2451,14.75 Z M20.2451,10.75 C21.1393522,11.3370174 21.138982,12.6645822 20.2440771,13.2510586 L20.1165,13.3263 L12.9348,17.1225 C12.4031636,17.4035 11.7765686,17.4290455 11.227667,17.1991364 L11.0655,17.1225 L3.88381,13.3263 C2.86325,12.7868 2.82034,11.3638 3.75508,10.75 L3.817995,10.7892 L3.817995,10.7892 L11.0654,14.6222 C11.5970364,14.9032 12.223714,14.9287455 12.7725555,14.6988364 L12.9347,14.6222 L20.1164,10.826 C20.1612,10.8023 20.2041,10.7769 20.2451,10.75 Z M12.9347,2.87782 L20.1164,6.67404 C21.1818,7.23718 21.1818,8.76316 20.1164,9.32629 L12.9347,13.1225 C12.35,13.4316 11.6502,13.4316 11.0654,13.1225 L3.88373,9.32629 C2.81838,8.76315 2.81838,7.23718 3.88373,6.67404 L11.0654,2.87782 C11.6502,2.56872 12.35,2.56872 12.9347,2.87782 Z" id="??" fill="#3f51b5"> </path> </g> </g> </g> </g></svg>
                        </div>
                        <div class="info">
                            <h3>Versão atual</h3>
                            <h4>{{ $project->current_version ?? ($latestVersion->version_number ?? '-') }}</h4>
                        </div>
                    </div>

                    <div class="stat-card tone-teal">
                        <div class="icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-box-arrow-up" viewBox="0 0 16 16">
                            <path fill-rule="evenodd" d="M3.5 6a.5.5 0 0 0-.5.5v8a.5.5 0 0 0 .5.5h9a.5.5 0 0 0 .5-.5v-8a.5.5 0 0 0-.5-.5h-2a.5.5 0 0 1 0-1h2A1.5 1.5 0 0 1 14 6.5v8a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 2 14.5v-8A1.5 1.5 0 0 1 3.5 5h2a.5.5 0 0 1 0 1z"/>
                            <path fill-rule="evenodd" d="M7.646.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1-.708.708L8.5 1.707V10.5a.5.5 0 0 1-1 0V1.707L5.354 3.854a.5.5 0 1 1-.708-.708z"/>
                            </svg>
                        </div>
                        <div class="info">
                            <h3>Última Submissão</h3>
                            <h4>{{ $submittedAtFormatted }}</h4>
                        </div>
                    </div>

                    <div class="stat-card tone-green">
                        <div class="icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                            </svg>
                        </div>
                        <div class="info">
                            <h3>Última Aprovação</h3>
                            <h4>{{ $approvedAtFormatted }}</h4>
                        </div>
                    </div>

                    <div class="stat-card tone-orange">
                        <div class="icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-archive-fill" viewBox="0 0 16 16">
                            <path d="M12.643 15C13.979 15 15 13.845 15 12.5V5H1v7.5C1 13.845 2.021 15 3.357 15zM5.5 7h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1 0-1M.8 1a.8.8 0 0 0-.8.8V3a.8.8 0 0 0 .8.8h14.4A.8.8 0 0 0 16 3V1.8a.8.8 0 0 0-.8-.8z"/>
                            </svg>
                        </div>
                        <div class="info">
                            <h3>Arquivos do projeto</h3>
                            <h4>{{ $projectFilesCount ?? 0 }}</h4>
                        </div>
                    </div>

                    <div class="stat-card tone-purple">
                        <div class="icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-box-seam-fill" viewBox="0 0 16 16">
                            <path fill-rule="evenodd" d="M15.528 2.973a.75.75 0 0 1 .472.696v8.662a.75.75 0 0 1-.472.696l-7.25 2.9a.75.75 0 0 1-.557 0l-7.25-2.9A.75.75 0 0 1 0 12.331V3.669a.75.75 0 0 1 .471-.696L7.443.184l.01-.003.268-.108a.75.75 0 0 1 .558 0l.269.108.01.003zM10.404 2 4.25 4.461 1.846 3.5 1 3.839v.4l6.5 2.6v7.922l.5.2.5-.2V6.84l6.5-2.6v-.4l-.846-.339L8 5.961 5.596 5l6.154-2.461z"/>
                            </svg>
                        </div>
                        <div class="info">
                            <h3>Armazenamento no projeto</h3>
                            <h4>{{ number_format($projectStorageMb ?? 0, 2, ',', '.') }} MB</h4>
                        </div>
                    </div>
                </div>

                <div class="info-grid" data-animate>
                    <div class="info-card">
                        <h3>Informações do projeto</h3>
                        @if($isSoloView)
                            <div class="info-line">
                                <span>Projeto</span>
                                <strong>{{ $project->title }}</strong>
                            </div>
                            <div class="info-line">
                                <span>Subfolder</span>
                                <strong>{{ $subfolder->name ?? '-' }}</strong>
                            </div>
                        @else
                            <div class="info-line">
                                <span>Laboratório</span>
                                <strong>{{ $lab->name }}</strong>
                            </div>
                            <div class="info-line">
                                <span>Grupo</span>
                                <strong>{{ $group->name }}</strong>
                            </div>
                            <div class="info-line">
                                <span>Código do grupo</span>
                                <strong>{{ $group->code }}</strong>
                            </div>
                        @endif
                    </div>

                    <div class="info-card">
                        <h3>Resumo de Versões</h3>
                        <div class="info-line">
                            <span>Rascunho</span>
                            <strong>{{ $versionStats['draft'] }}</strong>
                        </div>
                        <div class="info-line">
                            <span>Enviado</span>
                            <strong>{{ $versionStats['submitted'] }}</strong>
                        </div>
                        <div class="info-line">
                            <span>Aprovado</span>
                            <strong>{{ $versionStats['approved'] }}</strong>
                        </div>
                        <div class="info-line">
                            <span>Rejeitado</span>
                            <strong>{{ $versionStats['rejected'] }}</strong>
                        </div>
                    </div>

                    <div class="info-card">
                        <h3>Armazenamento da instituicao</h3>
                        <div class="info-line">
                            <span>Usado</span>
                            <strong>{{ number_format($tenantStorageUsedMb ?? 0, 2, ',', '.') }} MB</strong>
                        </div>
                        <div class="info-line">
                            <span>Limite</span>
                            <strong>{{ number_format($tenantStorageMaxMb ?? 0, 2, ',', '.') }} MB</strong>
                        </div>
                        <div class="storage-meter">
                            <span style="width: {{ $tenantStoragePercent ?? 0 }}%"></span>
                        </div>
                        <div class="storage-meter-label">
                            {{ $tenantStoragePercent ?? 0 }}% utilizado
                        </div>
                    </div>

                    <div class="info-card">
                        <h3>Descrição</h3>
                        <p class="project-description">{{ $project->description ?: 'Sem Descrição.' }}</p>
                    </div>
                </div>

                <div class="graph-grid" data-animate>
                    <div class="graph-card">
                        <div class="graph-header">
                            <div>
                                <h3>Armazenamento por mes</h3>
                                <p class="graph-sub">Periodo selecionado (MB)</p>
                            </div>
                            <div class="graph-period-filter-wrap">
                                <label class="graph-period-label" for="projectChartPeriod">Periodo</label>
                                <select id="projectChartPeriod" class="graph-period-select" data-chart-period-filter>
                                    <option value="3">Ultimos 3 meses</option>
                                    <option value="6">Ultimo semestre</option>
                                    <option value="12">Ultimo ano</option>
                                </select>
                            </div>
                        </div>
                        <div class="graph-body">
                            <div class="chart-wrap chart-bar">
                                <canvas
                                    id="projectStorageChart"
                                    class="chart-canvas"
                                    data-series='@json($storageTrend)'
                                    data-period-series='@json($storageTrendByPeriod ?? [])'
                                ></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="graph-card">
                        <div class="graph-header">
                            <div>
                                <h3>Novas versoes</h3>
                                <p class="graph-sub">Periodo selecionado</p>
                            </div>
                        </div>
                        <div class="graph-body">
                            <div class="chart-wrap chart-bar">
                                <canvas
                                    id="projectVersionsChart"
                                    class="chart-canvas"
                                    data-series='@json($versionsTrend)'
                                    data-period-series='@json($versionsTrendByPeriod ?? [])'
                                ></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="dashboard-actions" data-animate>
                </div>
            </section>
            @endif

            <section class="owner-panel-section" id="project-panel-versions" data-owner-panel="versions" data-nav-label="versoes" data-nav-icon="versions" role="tabpanel">
                <div class="versions-section" data-animate>
                    <div class="section-header">
                        <div>
                            <h3>Versões da subfolder</h3>
                            <p class="section-sub">Total: {{ ($versions ?? collect())->count() }}</p>
                        </div>
                        @if($canOpenVersionForm)
                            <button type="button" class="btn-secondary" id="openVersionFormBtnSecondary">Nova Versão</button>
                        @elseif($isStudentView && $studentStatusBlocked)
                            <span class="status-lock-message" role="status" aria-live="polite">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                                    <path d="M8.982 1.566a1.13 1.13 0 0 0-1.964 0L.165 13.233c-.457.778.091 1.767.982 1.767h13.706c.89 0 1.438-.99.982-1.767z"/>
                                    <path d="M8 5c.535 0 .954.462.9.995l-.35 3.507a.55.55 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5m.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2"/>
                                </svg>
                                <span>Seu status de projeto nao permite novas versoes.</span>
                            </span>
                        @elseif($isStudentView && $studentVersionLocked)
                            <span class="section-sub">Aguarde a avaliacao da ultima versao antes de enviar uma nova.</span>
                        @endif
                    </div>

                    @php
                        $flowVersions = ($versions ?? collect())->sortBy('version_number')->values();
                        $recentFlowVersionIds = $flowVersions
                            ->sortByDesc('version_number')
                            ->take($versionFlowRecentLimit)
                            ->pluck('id')
                            ->map(fn ($id) => (int) $id)
                            ->all();
                        $hiddenFlowCount = max(0, $flowVersions->count() - count($recentFlowVersionIds));
                    @endphp

                    @if($flowVersions->count() > 0)
                        <div class="version-board" data-version-board data-flow-board data-flow-auto-expand="{{ $highlightVersionId > 0 ? '1' : '0' }}">
                            <div class="version-board-toolbar">
                                <span class="board-hint">Arraste para navegar no quadro</span>
                                <div class="board-actions">
                                    <button type="button" class="btn-secondary btn-compact" data-board-reset>Centralizar</button>
                                    @if($hiddenFlowCount > 0)
                                    <button
                                        type="button"
                                        class="btn-secondary btn-compact"
                                        data-flow-toggle
                                        data-label-expand="Ver fluxo completo ({{ $flowVersions->count() }})"
                                        data-label-collapse="Mostrar apenas recentes"
                                    >
                                        Ver fluxo completo ({{ $flowVersions->count() }})
                                    </button>
                                    @endif
                                </div>
                            </div>
                            <div class="version-board-viewport" data-board-viewport>
                                <div class="version-board-canvas">
                                    <div class="version-board-lane">
                                        <div class="board-column board-column--project">
                                            <div class="board-node project-node">
                                                <span class="node-kicker">Projeto</span>
                                                <strong class="node-title">{{ $project->title }} - {{ $subfolder->name ?? 'Subfolder' }}</strong>
                                            </div>
                                        </div>

                                        @foreach($flowVersions as $version)
                                            @php $versionStatus = 'status-' . str_replace('_', '-', $version->status_version); @endphp
                                            @php $file = ($projectFiles ?? collect())->firstWhere('project_versions_id', $version->id); @endphp
                                            @php
                                                $commentBag = 'comment_' . $version->id;
                                                $commentsForVersion = ($versionComments ?? collect())->get($version->id, collect());
                                                $commentPreview = $commentsForVersion->take(3);
                                                $commentOverflow = max(0, $commentsForVersion->count() - $commentPreview->count());
                                                $commentPanelOpen = $errors->hasBag($commentBag);
                                                $commentOld = $commentPanelOpen ? old('comment') : '';
                                                $statusOld = $commentPanelOpen ? old('status_version', '') : '';
                                            @endphp
                                            @php $isHighlightedFlowVersion = ((int) $version->id === $highlightVersionId); @endphp
                                            @php $isHiddenFlowVersion = !in_array((int) $version->id, $recentFlowVersionIds, true) && !$isHighlightedFlowVersion; @endphp
                                            <div
                                                class="board-column{{ $isHiddenFlowVersion ? ' flow-version-hidden' : '' }}{{ $isHighlightedFlowVersion ? ' is-version-highlight' : '' }}"
                                                id="version-node-{{ $version->id }}"
                                                data-version-node-id="{{ $version->id }}"
                                                @if($isHiddenFlowVersion) data-flow-version-hidden hidden @endif
                                            >
                                                @if($commentPreview->isNotEmpty())
                                                    <div class="board-comments">
                                                        @foreach($commentPreview as $comment)
                                                            <button
                                                                type="button"
                                                                class="comment-bubble comment-bubble-toggle"
                                                                data-comment-list-toggle
                                                                aria-controls="comment-list-{{ $version->id }}"
                                                                aria-expanded="false"
                                                            >
                                                                <span class="comment-author">{{ $comment->creator?->name ?? $comment->subCreator?->name ?? 'Owner' }}</span>
                                                                <span class="comment-text">{{ Str::limit($comment->description, 60) }}</span>
                                                            </button>
                                                        @endforeach
                                                        @if($commentOverflow > 0)
                                                            <button
                                                                type="button"
                                                                class="comment-bubble is-muted comment-bubble-toggle"
                                                                data-comment-list-toggle
                                                                aria-controls="comment-list-{{ $version->id }}"
                                                                aria-expanded="false"
                                                            >
                                                                +{{ $commentOverflow }} comentários
                                                            </button>
                                                        @endif
                                                    </div>
                                                @endif

                                                <div class="board-node version-node">
                                                    <div class="version-node-header">
                                                        <div>
                                                            <span class="node-kicker">Versão {{ $version->version_number }}</span>
                                                            <strong class="node-title">{{ $version->title }}</strong>
                                                        </div>
                                                        <div class="version-node-badges">
                                                            @if($versionStatus === 'status-approved')
                                                            <span class="status-badge {{ $versionStatus }}">Aprovado</span>
                                                            @elseif($versionStatus == 'status-rejected')
                                                            <span class="status-badge {{ $versionStatus }}">Rejeitado</span>
                                                            @elseif($versionStatus == 'status-draft')
                                                            <span class="status-badge {{ $versionStatus }}">Rascunho</span>
                                                            @else
                                                            <span class="status-badge {{ $versionStatus }}">Enviado</span>
                                                            @endif
                                                            @if(!empty($canComment))
                                                            <button
                                                                type="button"
                                                                class="version-more-btn"
                                                                aria-label="Abrir comentários"
                                                                aria-expanded="{{ $commentPanelOpen ? 'true' : 'false' }}"
                                                                aria-controls="comment-panel-{{ $version->id }}"
                                                            >
                                                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                                                    <circle cx="5" cy="12" r="2"></circle>
                                                                    <circle cx="12" cy="12" r="2"></circle>
                                                                    <circle cx="19" cy="12" r="2"></circle>
                                                                </svg>
                                                            </button>
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
                                                        <button
                                                            type="button"
                                                            class="btn-secondary btn-icon"
                                                            data-version-detail-open
                                                            aria-controls="version-detail-{{ $version->id }}"
                                                            title="Ver versão"
                                                            aria-label="Ver versão"
                                                        >
                                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-eye" viewBox="0 0 16 16" aria-hidden="true">
                                                                <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8a13 13 0 0 1-1.66 2.043C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/>
                                                                <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M6.5 8a1.5 1.5 0 1 1 3 0 1.5 1.5 0 0 1-3 0"/>
                                                            </svg>
                                                        </button>
                                                        @if(!empty($canDeleteVersion))
                                                        <form
                                                            action="{{ route('versions.destroy', $version->id) }}"
                                                            method="POST"
                                                            onsubmit="return confirm('Tem certeza que deseja excluir esta versão?');"
                                                        >
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn-secondary btn-danger btn-icon" title="Excluir" aria-label="Excluir">
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-trash" viewBox="0 0 16 16" aria-hidden="true">
                                                                    <path d="M5.5 5.5A.5.5 0 0 1 6 5h4a.5.5 0 0 1 .5.5v7a.5.5 0 0 1-1 0V6H6v6.5a.5.5 0 0 1-1 0z"/>
                                                                    <path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2h4.5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1H14v1zM4.118 4 4 13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4z"/>
                                                                </svg>
                                                            </button>
                                                        </form>
                                                        @endif
                                                    </div>
                                                </div>

                                                <div
                                                    class="version-comment-panel {{ $commentPanelOpen ? 'is-open' : '' }}"
                                                    id="comment-panel-{{ $version->id }}"
                                                >
                                                    @if(!empty($canComment))
                                                    <form action="{{ route('versions.comments.store', $version->id) }}" method="POST" class="version-comment-form">
                                                        @csrf
                                                        <label for="comment-{{ $version->id }}" class="comment-label">Adicionar comentário</label>
                                                        <textarea
                                                            id="comment-{{ $version->id }}"
                                                            name="comment"
                                                            rows="3"
                                                            placeholder="Deixe um feedback sobre essa versão..."
                                                            required
                                                        >{{ $commentOld }}</textarea>
                                                        @error('comment', $commentBag)
                                                            <span class="error-message">{{ $message }}</span>
                                                        @enderror
                                                        @if(!empty($canEditVersionStatus) && !empty($statusOptions))
                                                        <label for="status-{{ $version->id }}" class="comment-label">Alterar status</label>
                                                        <select id="status-{{ $version->id }}" name="status_version">
                                                            <option value="">Manter status atual</option>
                                                            @foreach($statusOptions as $option)
                                                                <option value="{{ $option['value'] }}" {{ $statusOld === $option['value'] ? 'selected' : '' }}>{{ $option['label'] }}</option>
                                                            @endforeach
                                                        </select>
                                                        @error('status_version', $commentBag)
                                                            <span class="error-message">{{ $message }}</span>
                                                        @enderror
                                                        @endif
                                                        <div class="comment-actions">
                                                            <button type="submit" class="btn-submit">Enviar comentário</button>
                                                        </div>
                                                    </form>
                                                    @endif
                                                </div>

                                                @if($commentsForVersion->isNotEmpty())
                                                    <div class="comment-list-panel" id="comment-list-{{ $version->id }}" aria-hidden="true">
                                                        <div class="comment-list-panel-header">
                                                            <h5>Comentários</h5>
                                                            <button type="button" class="comment-list-close" data-comment-list-close aria-label="Fechar comentários">×</button>
                                                        </div>
                                                        <div class="comment-list">
                                                            @foreach($commentsForVersion as $comment)
                                                                <div class="comment-item">
                                                                    <div class="comment-meta">
                                                                        <span>{{ $comment->creator?->name ?? $comment->subCreator?->name ?? 'Owner' }}</span>
                                                                        <span>{{ $comment->created_at?->format('d/m/Y H:i') }}</span>
                                                                    </div>
                                                                    <p class="comment-text">{{ $comment->description }}</p>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endif

                                                <div class="version-detail-panel" id="version-detail-{{ $version->id }}" data-version-detail-panel aria-hidden="true">
                                                    <div class="detail-tabs" role="tablist" aria-label="Detalhes da versão">
                                                        <button type="button" class="detail-tab is-active" data-detail-tab="view" aria-selected="true">Visualizar</button>
                                                        @if(!empty($canEditVersion))
                                                        <button type="button" class="detail-tab" data-detail-tab="edit" aria-selected="false">Editar</button>
                                                        @endif
                                                    </div>
                                                    <div class="detail-body">
                                                        <div class="detail-view" data-detail-view>
                                                            <h4 class="detail-title">{{ $version->title }}</h4>
                                                            <div class="detail-meta">
                                                                <span>Enviada: {{ $version->submitted_at ?? '-' }}</span>
                                                                <span>Aprovada: {{ $version->approved_at ?? '-' }}</span>
                                                            </div>
                                                            <p class="detail-description">{{ $version->description ?: 'Sem descrição.' }}</p>
                                                        </div>
                                                        @if(!empty($canEditVersion))
                                                        <form
                                                            id="version-edit-{{ $version->id }}"
                                                            class="detail-edit"
                                                            data-detail-edit
                                                            action="{{ route('versions.update', $version->id) }}"
                                                            method="POST"
                                                            enctype="multipart/form-data"
                                                        >
                                                            @csrf
                                                            @method('PUT')
                                                            <label for="version-title-{{ $version->id }}">Título</label>
                                                            <input
                                                                id="version-title-{{ $version->id }}"
                                                                name="title"
                                                                type="text"
                                                                value="{{ old('title', $version->title) }}"
                                                                required
                                                            >
                                                            <label for="version-desc-{{ $version->id }}">Descrição</label>
                                                            <textarea
                                                                id="version-desc-{{ $version->id }}"
                                                                name="description"
                                                                rows="6"
                                                                required
                                                            >{{ old('description', $version->description) }}</textarea>
                                                            <label for="version-file-{{ $version->id }}">Substituir arquivo (ZIP)</label>
                                                            <input
                                                                id="version-file-{{ $version->id }}"
                                                                name="version_file"
                                                                type="file"
                                                                accept=".zip"
                                                            >
                                                            <small class="form-help">Opcional: envie outro .zip para substituir o arquivo atual.</small>
                                                        </form>
                                                        @endif
                                                    </div>
                                                    <div class="detail-actions">
                                                        <button type="button" class="btn-secondary action-view" data-detail-close>Voltar</button>
                                                        @if(!empty($canEditVersion))
                                                        <button type="submit" class="btn-submit action-edit" form="version-edit-{{ $version->id }}">Salvar</button>
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
                        <div class="empty-state">Sem Versões cadastradas.</div>
                    @endif
                    <div class="comment-list-overlay" data-comment-overlay></div>
                    <div class="version-detail-overlay" data-version-detail-overlay></div>
                </div>
            </section>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="{{ asset('script/proj.js') }}"></script>
@endpush
@endsection

@section('modals')
    @if($canOpenVersionForm)
    <form action="{{ route('project-version-add') }}" method="POST" id="versionForm" class="version-form {{ $errors->any() ? 'is-open' : '' }}" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="project_id" value="{{ $project->id }}">
        <input type="hidden" name="subfolder_id" value="{{ $subfolder->id }}">
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
            <h3>Nova versão da subfolder</h3>
        </div>

        <div class="body-form">
            <div class="form-group full-width">
                <label for="version-title">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 7h16M4 12h16M4 17h10"/>
                    </svg>
                    Título da versão
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
                Adicionar versão
            </button>
        </div>
    </form>
    @endif
@endsection

