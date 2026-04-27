@extends($layout ?? 'layouts.header-side-not-sub')

@section('title', 'Requisitos')

@if($theme === '"light"')
@push('styles')
<link rel="stylesheet" href="{{ asset('main/proj.css') }}">
@endpush
@else
@push('styles')
<link rel="stylesheet" href="{{ asset('main/proj-dark.css') }}">
@endpush
@endif

@section('content')
@php
    $funcReqsWithNonFunc = $funcReqsWithNonFunc ?? collect();
    $funcReqOptions = $funcReqOptions ?? collect();
    $globalNonFuncReqs = $globalNonFuncReqs ?? collect();
    $linkedNonFuncReqs = $linkedNonFuncReqs ?? collect();
    $allNonFuncReqsCount = (int) ($allNonFuncReqsCount ?? ($globalNonFuncReqs->count() + $linkedNonFuncReqs->count()));
    $readonly = $readonly ?? false;
    $paginationState = $paginationState ?? [
        'rf_page' => max(1, (int) request('rf_page', 1)),
        'rnf_global_page' => max(1, (int) request('rnf_global_page', 1)),
        'rnf_linked_page' => max(1, (int) request('rnf_linked_page', 1)),
    ];

    $funcReqTotal = method_exists($funcReqsWithNonFunc, 'total')
        ? $funcReqsWithNonFunc->total()
        : $funcReqsWithNonFunc->count();

    $rnfGlobalTotal = method_exists($globalNonFuncReqs, 'total')
        ? $globalNonFuncReqs->total()
        : $globalNonFuncReqs->count();

    $rnfLinkedTotal = method_exists($linkedNonFuncReqs, 'total')
        ? $linkedNonFuncReqs->total()
        : $linkedNonFuncReqs->count();

    $priorityMap = [
        'low' => 'Baixa',
        'medium' => 'Media',
        'high' => 'Alta',
    ];

    $statusMap = [
        'draft' => 'Rascunho',
        'in_progress' => 'Em andamento',
        'approved' => 'Aprovado',
        'rejected' => 'Rejeitado',
    ];

    $priorityLabel = fn ($value) => $priorityMap[(string) $value] ?? ucfirst((string) $value);
    $statusLabel = fn ($value) => $statusMap[(string) $value] ?? ucfirst(str_replace('_', ' ', (string) $value));
@endphp

<div class="container-info project-dashboard requirements-dashboard">
    <div class="project-hero" data-animate>
        <div>
            <h2>Requisitos</h2>
            <p class="project-sub">{{ $project->title }} - {{ $lab->name }} - {{ $group->name }}</p>
        </div>
        <div class="project-actions">
            <a class="btn-secondary" title="Ver Projeto" href="{{ $projectBackUrl }}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" class="bi bi-journal" style="width:16px;height:16px;" viewBox="0 0 16 16" aria-hidden="true">
                    <path d="M3 0h10a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2v-1h1v1a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1H3a1 1 0 0 0-1 1v1H1V2a2 2 0 0 1 2-2"/>
                    <path d="M1 5v-.5a.5.5 0 0 1 1 0V5h.5a.5.5 0 0 1 0 1h-2a.5.5 0 0 1 0-1zm0 3v-.5a.5.5 0 0 1 1 0V8h.5a.5.5 0 0 1 0 1h-2a.5.5 0 0 1 0-1zm0 3v-.5a.5.5 0 0 1 1 0v.5h.5a.5.5 0 0 1 0 1h-2a.5.5 0 0 1 0-1z"/>
                </svg>
                Projeto
            </a>
            <a class="btn-secondary" title="Exportar requisitos" href="{{ route('requirements.export', ['project' => $project->id]) }}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" class="bi bi-file-earmark-excel" style="width:16px;height:16px;" viewBox="0 0 16 16" aria-hidden="true">
                    <path d="M5.884 6.68a.5.5 0 0 0-.768.64L6.433 9l-1.317 1.68a.5.5 0 1 0 .768.64L7 9.958l1.116 1.362a.5.5 0 0 0 .768-.64L7.567 9l1.317-1.68a.5.5 0 0 0-.768-.64L7 8.042z"/>
                    <path d="M14 4.5V14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2h3.5zM6 1a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V5h-2.5A1.5 1.5 0 0 1 9 3.5V1z"/>
                    <path d="M14 3h-3a1 1 0 0 1-1-1V0z"/>
                </svg>
                Exportar Excel
            </a>
        </div>
    </div>

    <div class="owner-shell">
        <div class="owner-tabs" role="tablist" aria-label="Secoes do projeto">
            <a class="owner-tab is-active" href="{{ route('requirements.index', array_merge(['project' => $project->id], $paginationState)) }}">Requisitos</a>
        </div>

        <div class="owner-panel">
            @if(session('success'))
                <div class="form-alert form-alert-success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="form-alert">{{ session('error') }}</div>
            @endif

            @if ($errors->any())
                <div class="form-alert">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section class="versions-section requirements-section" data-animate id="rf-section">
                <div class="section-header">
                    <div>
                        <h3>Requisitos Funcionais (RF)</h3>
                        <p class="section-sub">Total: {{ $funcReqTotal }}</p>
                        @if($readonly)
                            <p class="section-sub">Modo somente leitura</p>
                        @endif
                    </div>
                </div>

                @if(!$readonly)
                    <details class="req-form-card">
                        <summary>Adicionar RF</summary>
                        <form method="POST" action="{{ route('requirements.func.store', ['project' => $project->id]) }}" class="req-form-grid">
                            @csrf
                            @include('main.home.partials.requirements-pagination-fields', ['paginationState' => $paginationState])
                            <div class="form-group">
                                <label>Codigo (opcional)</label>
                                <input type="text" name="code" placeholder="RF-01" value="{{ old('code') }}">
                            </div>
                            <div class="form-group">
                                <label>Titulo</label>
                                <input type="text" name="title" value="{{ old('title') }}" required>
                            </div>
                            <div class="form-group">
                                <label>Prioridade</label>
                                @php $currentPriority = old('priority', 'medium'); @endphp
                                <select name="priority">
                                    <option value="low" {{ $currentPriority === 'low' ? 'selected' : '' }}>Baixa</option>
                                    <option value="medium" {{ $currentPriority === 'medium' ? 'selected' : '' }}>Media</option>
                                    <option value="high" {{ $currentPriority === 'high' ? 'selected' : '' }}>Alta</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Status</label>
                                @php $currentStatus = old('status', 'draft'); @endphp
                                <select name="status">
                                    <option value="draft" {{ $currentStatus === 'draft' ? 'selected' : '' }}>Rascunho</option>
                                    <option value="in_progress" {{ $currentStatus === 'in_progress' ? 'selected' : '' }}>Em andamento</option>
                                    <option value="approved" {{ $currentStatus === 'approved' ? 'selected' : '' }}>Aprovado</option>
                                    <option value="rejected" {{ $currentStatus === 'rejected' ? 'selected' : '' }}>Rejeitado</option>
                                </select>
                            </div>
                            <div class="form-group full-width">
                                <label>Descricao</label>
                                <textarea name="description" rows="3">{{ old('description') }}</textarea>
                            </div>
                            <div class="form-group full-width">
                                <label>Criterios de aceitacao</label>
                                <textarea name="acceptance_criteria" rows="3">{{ old('acceptance_criteria') }}</textarea>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn-submit">Salvar RF</button>
                            </div>
                        </form>
                    </details>
                @endif

                @if($funcReqsWithNonFunc->isEmpty())
                    <div class="empty-state">Nenhum requisito funcional cadastrado.</div>
                @else
                    <div class="requirements-table-wrap">
                        <table class="requirements-table">
                            <thead>
                                <tr>
                                    <th>Codigo</th>
                                    <th>Titulo</th>
                                    <th>Prioridade</th>
                                    <th>Status</th>
                                    @if(!$readonly)
                                        <th>Acoes</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($funcReqsWithNonFunc as $funcReq)
                                    @php $funcStatusClass = 'status-' . str_replace('_', '-', $funcReq->status); @endphp
                                    <tr>
                                        <td>{{ $funcReq->code }}</td>
                                        <td>{{ $funcReq->title }}</td>
                                        <td>{{ $priorityLabel($funcReq->priority) }}</td>
                                        <td><span class="status-badge {{ $funcStatusClass }}">{{ $statusLabel($funcReq->status) }}</span></td>
                                        @if(!$readonly)
                                            <td>
                                                <div class="req-actions">
                                                    <details class="inline-edit">
                                                        <summary class="btn-secondary btn-compact">Editar</summary>
                                                        <form method="POST" action="{{ route('requirements.func.update', ['project' => $project->id, 'funcReq' => $funcReq->id]) }}" class="req-form-grid">
                                                            @csrf
                                                            @method('PUT')
                                                            @include('main.home.partials.requirements-pagination-fields', ['paginationState' => $paginationState])
                                                            <div class="form-group"><label>Codigo</label><input type="text" name="code" value="{{ $funcReq->code }}"></div>
                                                            <div class="form-group"><label>Titulo</label><input type="text" name="title" value="{{ $funcReq->title }}" required></div>
                                                            <div class="form-group">
                                                                <label>Prioridade</label>
                                                                <select name="priority">
                                                                    <option value="low" {{ $funcReq->priority === 'low' ? 'selected' : '' }}>Baixa</option>
                                                                    <option value="medium" {{ $funcReq->priority === 'medium' ? 'selected' : '' }}>Media</option>
                                                                    <option value="high" {{ $funcReq->priority === 'high' ? 'selected' : '' }}>Alta</option>
                                                                </select>
                                                            </div>
                                                            <div class="form-group">
                                                                <label>Status</label>
                                                                <select name="status">
                                                                    <option value="draft" {{ $funcReq->status === 'draft' ? 'selected' : '' }}>Rascunho</option>
                                                                    <option value="in_progress" {{ $funcReq->status === 'in_progress' ? 'selected' : '' }}>Em andamento</option>
                                                                    <option value="approved" {{ $funcReq->status === 'approved' ? 'selected' : '' }}>Aprovado</option>
                                                                    <option value="rejected" {{ $funcReq->status === 'rejected' ? 'selected' : '' }}>Rejeitado</option>
                                                                </select>
                                                            </div>
                                                            <div class="form-group full-width"><label>Descricao</label><textarea name="description" rows="3">{{ $funcReq->description }}</textarea></div>
                                                            <div class="form-group full-width"><label>Criterios de aceitacao</label><textarea name="acceptance_criteria" rows="3">{{ $funcReq->acceptance_criteria }}</textarea></div>
                                                            <div class="form-actions"><button type="submit" class="btn-submit">Atualizar</button></div>
                                                        </form>
                                                    </details>
                                                    <form method="POST" action="{{ route('requirements.func.destroy', ['project' => $project->id, 'funcReq' => $funcReq->id]) }}" onsubmit="return confirm('Excluir este RF?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        @include('main.home.partials.requirements-pagination-fields', ['paginationState' => $paginationState])
                                                        <button type="submit" class="btn-secondary btn-danger btn-compact">Excluir</button>
                                                    </form>
                                                </div>
                                            </td>
                                        @endif
                                    </tr>
                                    <tr class="req-linked-row">
                                        <td colspan="{{ $readonly ? 4 : 5 }}">
                                            <details class="req-linked-panel">
                                                <summary>RNFs atrelados ({{ $funcReq->nonFunctional->count() }})</summary>
                                                <div class="req-linked-list">
                                                    @forelse($funcReq->nonFunctional as $nonFunc)
                                                        <span class="status-badge status-submitted">{{ $nonFunc->code }} - {{ $nonFunc->title }}</span>
                                                    @empty
                                                        <span class="muted-label">Nenhum RNF atrelado.</span>
                                                    @endforelse
                                                </div>
                                            </details>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if(method_exists($funcReqsWithNonFunc, 'links'))
                        <div class="requirements-pagination">
                            {{ $funcReqsWithNonFunc->onEachSide(1)->links() }}
                        </div>
                    @endif
                @endif
            </section>

            <section class="versions-section requirements-section" data-animate id="rnf-section">
                <div class="section-header">
                    <div>
                        <h3>Requisitos Nao Funcionais (RNF)</h3>
                        <p class="section-sub">Total: {{ $allNonFuncReqsCount }}</p>
                        @if($readonly)
                            <p class="section-sub">Modo somente leitura</p>
                        @endif
                    </div>
                </div>

                <div class="req-metrics">
                    <div class="req-metric-chip">
                        <span>RNFs globais</span>
                        <strong>{{ $rnfGlobalTotal }}</strong>
                    </div>
                    <div class="req-metric-chip">
                        <span>RNFs atrelados</span>
                        <strong>{{ $rnfLinkedTotal }}</strong>
                    </div>
                    <div class="req-metric-chip">
                        <span>Total RNFs</span>
                        <strong>{{ $allNonFuncReqsCount }}</strong>
                    </div>
                </div>

                @if(!$readonly)
                    <details class="req-form-card">
                        <summary>Adicionar RNF</summary>
                        <form method="POST" action="{{ route('requirements.nonfunc.store', ['project' => $project->id]) }}" class="req-form-grid">
                            @csrf
                            @include('main.home.partials.requirements-pagination-fields', ['paginationState' => $paginationState])
                            <div class="form-group"><label>Codigo (opcional)</label><input type="text" name="code" placeholder="RNF-01" value="{{ old('code') }}"></div>
                            <div class="form-group"><label>Titulo</label><input type="text" name="title" value="{{ old('title') }}" required></div>
                            <div class="form-group"><label>Categoria</label><input type="text" name="category" value="{{ old('category') }}" placeholder="Seguranca, Performance..."></div>
                            <div class="form-group">
                                <label>Prioridade</label>
                                @php $currentPriority = old('priority', 'medium'); @endphp
                                <select name="priority">
                                    <option value="low" {{ $currentPriority === 'low' ? 'selected' : '' }}>Baixa</option>
                                    <option value="medium" {{ $currentPriority === 'medium' ? 'selected' : '' }}>Media</option>
                                    <option value="high" {{ $currentPriority === 'high' ? 'selected' : '' }}>Alta</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Status</label>
                                @php $currentStatus = old('status', 'draft'); @endphp
                                <select name="status">
                                    <option value="draft" {{ $currentStatus === 'draft' ? 'selected' : '' }}>Rascunho</option>
                                    <option value="in_progress" {{ $currentStatus === 'in_progress' ? 'selected' : '' }}>Em andamento</option>
                                    <option value="approved" {{ $currentStatus === 'approved' ? 'selected' : '' }}>Aprovado</option>
                                    <option value="rejected" {{ $currentStatus === 'rejected' ? 'selected' : '' }}>Rejeitado</option>
                                </select>
                            </div>
                            <div class="form-group full-width">
                                <label>RFs vinculados (opcional)</label>
                                <select name="func_req_ids[]" multiple size="5">
                                    @foreach($funcReqOptions as $funcReqOption)
                                        <option value="{{ $funcReqOption->id }}">{{ $funcReqOption->code }} - {{ $funcReqOption->title }}</option>
                                    @endforeach
                                </select>
                                @if($funcReqOptions->isEmpty())
                                    <small class="form-help">Nenhum RF disponivel para vinculo.</small>
                                @else
                                    <small class="form-help">Se nenhum RF for selecionado, o RNF sera global.</small>
                                @endif
                            </div>
                            <div class="form-group full-width"><label>Descricao</label><textarea name="description" rows="3">{{ old('description') }}</textarea></div>
                            <div class="form-group full-width"><label>Criterios de aceitacao</label><textarea name="acceptance_criteria" rows="3">{{ old('acceptance_criteria') }}</textarea></div>
                            <div class="form-actions"><button type="submit" class="btn-submit">Salvar RNF</button></div>
                        </form>
                    </details>
                @endif

                <div class="requirements-split">
                    <div class="requirements-card" id="rnf-global-section">
                        <div class="requirements-card-head">
                            <div>
                                <h4>RNFs Globais</h4>
                                <p class="requirements-card-sub">Sem vinculo direto com RF.</p>
                            </div>
                            <span class="req-type-badge">{{ $rnfGlobalTotal }}</span>
                        </div>
                        @php $hasGlobalPagination = method_exists($globalNonFuncReqs, 'hasPages') && $globalNonFuncReqs->hasPages(); @endphp
                        @if($globalNonFuncReqs->isEmpty())
                            <p class="muted-label">Nenhum RNF global.</p>
                        @else
                            <div class="requirements-list">
                                @foreach($globalNonFuncReqs as $nonFuncReq)
                                    @include('main.home.partials.requirement-nonfunc-item', [
                                        'nonFuncReq' => $nonFuncReq,
                                        'project' => $project,
                                        'funcReqOptions' => $funcReqOptions,
                                        'readonly' => $readonly,
                                        'paginationState' => $paginationState,
                                    ])
                                @endforeach
                            </div>
                            @if(method_exists($globalNonFuncReqs, 'links'))
                                <div class="requirements-pagination{{ $hasGlobalPagination ? '' : ' requirements-pagination-placeholder' }}">
                                    {{ $globalNonFuncReqs->onEachSide(1)->links() }}
                                </div>
                            @endif
                        @endif
                    </div>

                    <div class="requirements-card" id="rnf-linked-section">
                        <div class="requirements-card-head">
                            <div>
                                <h4>RNFs Atrelados</h4>
                                <p class="requirements-card-sub">Associados a um ou mais RFs.</p>
                            </div>
                            <span class="req-type-badge">{{ $rnfLinkedTotal }}</span>
                        </div>
                        @php $hasLinkedPagination = method_exists($linkedNonFuncReqs, 'hasPages') && $linkedNonFuncReqs->hasPages(); @endphp
                        @if($linkedNonFuncReqs->isEmpty())
                            <p class="muted-label">Nenhum RNF atrelado.</p>
                        @else
                            <div class="requirements-list">
                                @foreach($linkedNonFuncReqs as $nonFuncReq)
                                    @include('main.home.partials.requirement-nonfunc-item', [
                                        'nonFuncReq' => $nonFuncReq,
                                        'project' => $project,
                                        'funcReqOptions' => $funcReqOptions,
                                        'readonly' => $readonly,
                                        'paginationState' => $paginationState,
                                    ])
                                @endforeach
                            </div>
                            @if(method_exists($linkedNonFuncReqs, 'links'))
                                <div class="requirements-pagination{{ $hasLinkedPagination ? '' : ' requirements-pagination-placeholder' }}">
                                    {{ $linkedNonFuncReqs->onEachSide(1)->links() }}
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>
@endsection
