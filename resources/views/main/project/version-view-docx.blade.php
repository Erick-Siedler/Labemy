@php
    $paragraphs = $paragraphs ?? [];
    $truncated = $truncated ?? false;
    $ext = $ext ?? 'docx';
@endphp

<div class="zip-viewer-content">
    <div class="zip-code-header">
        <span class="zip-code-label">Arquivo {{ $ext ? '.' . $ext : '' }}</span>
        <span class="zip-code-tag">DOCX</span>
    </div>

    <div class="zip-docx-content">
        @if(empty($paragraphs))
            <p class="zip-docx-empty">Nenhum texto extraivel encontrado neste DOCX.</p>
        @else
            @foreach($paragraphs as $paragraph)
                <p class="zip-docx-paragraph">{!! nl2br(e($paragraph)) !!}</p>
            @endforeach
        @endif
    </div>

    @if($truncated)
        <div class="zip-docx-note">
            Preview parcial: limite de paragrafos ou tamanho de conteudo atingido.
        </div>
    @endif
</div>
