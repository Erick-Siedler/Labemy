@extends('layouts.header-side-not-sub')

@section('title', 'Projetos')

@if($theme === '"light"')
@push('styles')
<link rel="stylesheet" href="{{ asset('main/proj.css') }}">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
@endpush
@else
@push('styles')
<link rel="stylesheet" href="{{ asset('main/proj-dark.css') }}">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
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
    $versionComments = $versionComments ?? collect();
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
    $studentRejectedLocked = $user->role === 'student'
        && $latestVersionStatus === 'rejected';
    $studentVersionLocked = $studentAwaitingReview || $studentRejectedLocked;
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
                <span class="hero-label">ID</span>
                <input class="hero-input" type="text" value="{{ $project->id }}" readonly>
            </div>
            <div class="hero-field">
                <span class="hero-label">Laboratório</span>
                <input class="hero-input" type="text" data-project-field="lab_name" value="{{ $lab?->name }}" readonly>
            </div>
            <div class="hero-field">
                <span class="hero-label">Grupo</span>
                <input class="hero-input" type="text" data-project-field="group_name" value="{{ $group?->name }}" readonly>
            </div>
            <div class="hero-field">
                <span class="hero-label">Status</span>
                @if($project->status === 'approved')
                <input class="hero-input" type="text" data-project-field="project-status" value="Aprovado" readonly>
                @elseif($project->status === 'archived')
                <input class="hero-input" type="text" data-project-field="project-status" value="Arquivado" readonly>
                @elseif($project->status === 'in_progress')
                <input class="hero-input" type="text" data-project-field="project-status" value="Em andamento" readonly>
                @elseif($project->status === 'draft')
                <input class="hero-input" type="text" data-project-field="project-status" value="Rascunho" readonly>
                @else
                <input class="hero-input" type="text" data-project-field="project-status" value="Rejeitado" readonly>
                @endif
                
            </div>
        </div>

        @if(!$isAssistant)
        <div class="project-hero-actions">
            <button type="button" class="btn-secondary project-hero-save" data-project-save>Salvar</button>
            <span class="project-hero-status" data-project-save-status></span>
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
        <div class="owner-tabs" data-panel-tabs role="tablist" aria-label="Seções do projeto">
            <button type="button" class="owner-tab is-active" data-panel-target="versions" aria-selected="true" aria-controls="student-panel-versions">Versões</button>
            <button type="button" class="owner-tab" data-panel-target="calendar" aria-selected="false" aria-controls="student-panel-calendar">Calendário</button>
        </div>

        <div class="owner-panel">
            <div class="project-stack" data-panel-stack>
                <section class="project-panel panel-versions is-active" data-panel="versions" id="student-panel-versions">
                    <div class="versions-section" data-animate>
                    <div class="section-header">
                        <div>
                            <h3>Versões do projeto</h3>
                            <p class="section-sub">Total: {{ $versions->count() }}</p>
                    </div>
                    @if(!$isAssistant && !$studentVersionLocked)
                        @if($project && ($project->status === 'approved' || $project->status === 'in_progress' || $latestVersionStatus === 'approved' || $latestVersionStatus === 'in_progress'))
                            <button type="button" class="btn-secondary" id="openVersionFormBtnSecondary">Nova Versão</button>
                        @else
                            <span style="color:red;">Seu status de projeto não permite novas versões</span>
                        @endif
                    @endif
                </div>
                @if($studentAwaitingReview)
                    <p class="section-sub">Aguardando avaliação da última versão.</p>
                @elseif($studentRejectedLocked)
                    <p class="section-sub" style="color:red;">A última versão foi rejeitada. Não é possível enviar uma nova versão.</p>
                @endif

                @if(!$project)
                    <div class="empty-state">Nenhum projeto encontrado.</div>
                @elseif($versions->count() > 0)
                    <div class="version-board" data-version-board>
                        <div class="version-board-toolbar">
                            <span class="board-hint">Arraste para navegar no quadro</span>
                            <div class="board-actions">
                                <button type="button" class="btn-secondary btn-compact" data-board-reset>Centralizar</button>
                            </div>
                        </div>
                        <div class="version-board-viewport" data-board-viewport>
                            <div class="version-board-canvas">
                                <div class="version-board-lane">
                                    <div class="board-column board-column--project">
                                        <div class="board-node project-node">
                                            <span class="node-kicker">Projeto</span>
                                            <strong class="node-title">{{ $project->title }}</strong>
                                            <span class="node-sub">ID {{ $project->id }}</span>
                                        </div>
                                    </div>

                                    @foreach($versions as $version)
                                        @php $versionStatus = 'status-' . str_replace('_', '-', $version->status_version); @endphp
                                        @php $file = ($projectFiles ?? collect())->firstWhere('project_versions_id', $version->id); @endphp
                                        @php
                                            $commentsForVersion = ($versionComments ?? collect())->get($version->id, collect());
                                            $commentPreview = $commentsForVersion->take(3);
                                            $commentOverflow = max(0, $commentsForVersion->count() - $commentPreview->count());
                                        @endphp
                                        <div class="board-column">
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
                                                    <div class="node-meta-line">
                                                        <span>ID</span>
                                                        <strong>{{ $version->id }}</strong>
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
                                                        <button type="button" class="detail-tab" data-detail-tab="edit" aria-selected="false">Editar</button>
                                                    </div>
                                                    <div class="detail-body">
                                                        <div class="detail-view" data-detail-view>
                                                            <h4 class="detail-title">{{ $version->title }}</h4>
                                                            <div class="detail-meta">
                                                                <span>ID {{ $version->id }}</span>
                                                                <span>Enviada: {{ $version->submitted_at ?? '-' }}</span>
                                                                <span>Aprovada: {{ $version->approved_at ?? '-' }}</span>
                                                            </div>
                                                            <p class="detail-description">{{ $version->description ?: 'Sem descrição.' }}</p>
                                                        </div>
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
                                                    </div>
                                                    <div class="detail-actions">
                                                        <button type="button" class="btn-secondary action-view" data-detail-close>Voltar</button>
                                                        <button type="submit" class="btn-submit action-edit" form="version-edit-{{ $version->id }}">Salvar</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <div class="comment-list-overlay" data-comment-overlay></div>
                        <div class="version-detail-overlay" data-version-detail-overlay></div>
                    </div>
                @else
                    <div class="empty-state">Sem Versões cadastradas.</div>
                @endif
                </div>
                </section>

                <section class="project-panel panel-calendar" data-panel="calendar" id="student-panel-calendar">
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

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
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

