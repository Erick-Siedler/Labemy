@php
    $nonFuncStatusClass = 'status-' . str_replace('_', '-', $nonFuncReq->status);
    $selectedFuncIds = $nonFuncReq->functional->pluck('id')->map(fn ($id) => (int) $id)->all();
    $readonly = $readonly ?? false;
    $funcReqOptions = $funcReqOptions ?? ($funcReqsWithNonFunc ?? collect());
    $paginationState = $paginationState ?? [
        'rf_page' => max(1, (int) request('rf_page', 1)),
        'rnf_global_page' => max(1, (int) request('rnf_global_page', 1)),
        'rnf_linked_page' => max(1, (int) request('rnf_linked_page', 1)),
    ];

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

    $linkedFunctional = $nonFuncReq->functional ?? collect();
    $linkedCount = $linkedFunctional->count();
    $linkedPreview = $linkedFunctional->take(6);
    $linkedOverflow = max(0, $linkedCount - $linkedPreview->count());
    $hasDetails = !empty($nonFuncReq->description)
        || !empty($nonFuncReq->acceptance_criteria)
        || $linkedCount > 0;
@endphp

<article class="req-item req-item-nonfunc">
    <div class="req-item-head">
        <div>
            <h5>{{ $nonFuncReq->code }} - {{ $nonFuncReq->title }}</h5>
            <div class="req-item-tags">
                <span class="req-tag">Categoria: {{ $nonFuncReq->category ?: '-' }}</span>
                <span class="req-tag">Prioridade: {{ $priorityLabel($nonFuncReq->priority) }}</span>
                <span class="req-tag">RFs: {{ $linkedCount }}</span>
            </div>
        </div>
        <span class="status-badge {{ $nonFuncStatusClass }}">{{ $statusLabel($nonFuncReq->status) }}</span>
    </div>

    @if($hasDetails)
        <details class="req-item-toggle">
            <summary>Detalhes do RNF</summary>
            <div class="req-item-details">
                @if(!empty($nonFuncReq->description))
                    <div class="req-item-detail-block">
                        <strong>Descricao</strong>
                        <p class="req-item-desc">{{ $nonFuncReq->description }}</p>
                    </div>
                @endif

                @if(!empty($nonFuncReq->acceptance_criteria))
                    <div class="req-item-detail-block">
                        <strong>Criterios de aceitacao</strong>
                        <p class="req-item-desc">{{ $nonFuncReq->acceptance_criteria }}</p>
                    </div>
                @endif

                <div class="req-item-detail-block">
                    <strong>RFs vinculados</strong>
                    <div class="req-linked-list">
                        @if($linkedCount > 0)
                            @foreach($linkedPreview as $funcReqLinked)
                                <span class="status-badge status-submitted">{{ $funcReqLinked->code }}</span>
                            @endforeach
                            @if($linkedOverflow > 0)
                                <span class="muted-label">+{{ $linkedOverflow }} adicionais</span>
                            @endif
                        @else
                            <span class="muted-label">Nenhum vinculo ativo.</span>
                        @endif
                    </div>
                </div>
            </div>
        </details>
    @endif

    @if(!$readonly)
        <div class="req-actions req-actions-end">
            <details class="inline-edit">
                <summary class="btn-secondary btn-compact">Editar</summary>
                <form method="POST" action="{{ route('requirements.nonfunc.update', ['project' => $project->id, 'nonFuncReq' => $nonFuncReq->id]) }}" class="req-form-grid">
                    @csrf
                    @method('PUT')
                    @include('main.home.partials.requirements-pagination-fields', ['paginationState' => $paginationState])
                    <div class="form-group">
                        <label>Codigo</label>
                        <input type="text" name="code" value="{{ $nonFuncReq->code }}">
                    </div>
                    <div class="form-group">
                        <label>Titulo</label>
                        <input type="text" name="title" value="{{ $nonFuncReq->title }}" required>
                    </div>
                    <div class="form-group">
                        <label>Categoria</label>
                        <input type="text" name="category" value="{{ $nonFuncReq->category }}">
                    </div>
                    <div class="form-group">
                        <label>Prioridade</label>
                        <select name="priority">
                            <option value="low" {{ $nonFuncReq->priority === 'low' ? 'selected' : '' }}>Baixa</option>
                            <option value="medium" {{ $nonFuncReq->priority === 'medium' ? 'selected' : '' }}>Media</option>
                            <option value="high" {{ $nonFuncReq->priority === 'high' ? 'selected' : '' }}>Alta</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="draft" {{ $nonFuncReq->status === 'draft' ? 'selected' : '' }}>Rascunho</option>
                            <option value="in_progress" {{ $nonFuncReq->status === 'in_progress' ? 'selected' : '' }}>Em andamento</option>
                            <option value="approved" {{ $nonFuncReq->status === 'approved' ? 'selected' : '' }}>Aprovado</option>
                            <option value="rejected" {{ $nonFuncReq->status === 'rejected' ? 'selected' : '' }}>Rejeitado</option>
                        </select>
                    </div>
                    <div class="form-group full-width">
                        <label>RFs vinculados (opcional)</label>
                        <select name="func_req_ids[]" multiple size="5">
                            @foreach($funcReqOptions as $funcReqOption)
                                <option
                                    value="{{ $funcReqOption->id }}"
                                    {{ in_array((int) $funcReqOption->id, $selectedFuncIds, true) ? 'selected' : '' }}
                                >
                                    {{ $funcReqOption->code }} - {{ $funcReqOption->title }}
                                </option>
                            @endforeach
                        </select>
                        <small class="form-help">Para tornar global, remova todas as selecoes.</small>
                    </div>
                    <div class="form-group full-width">
                        <label>Descricao</label>
                        <textarea name="description" rows="3">{{ $nonFuncReq->description }}</textarea>
                    </div>
                    <div class="form-group full-width">
                        <label>Criterios de aceitacao</label>
                        <textarea name="acceptance_criteria" rows="3">{{ $nonFuncReq->acceptance_criteria }}</textarea>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-submit">Atualizar</button>
                    </div>
                </form>
            </details>

            <form method="POST" action="{{ route('requirements.nonfunc.destroy', ['project' => $project->id, 'nonFuncReq' => $nonFuncReq->id]) }}" onsubmit="return confirm('Excluir este RNF?');">
                @csrf
                @method('DELETE')
                @include('main.home.partials.requirements-pagination-fields', ['paginationState' => $paginationState])
                <button type="submit" class="btn-secondary btn-danger btn-compact">Excluir</button>
            </form>
        </div>
    @endif
</article>