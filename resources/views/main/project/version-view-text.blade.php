@php
    $mode = $mode ?? 'code';
    $ext = $ext ?? '';
    $content = $content ?? '';
    $message = $message ?? null;
@endphp

@if($mode === 'unsupported')
    <div class="zip-viewer-unsupported">
        <strong>Visualizacao indisponivel</strong>
        <p>{{ $message ?: 'Este tipo de arquivo nao suporta visualizacao em texto.' }}</p>
    </div>
@else
    @php $lines = explode("\n", $content); @endphp
    <div class="zip-viewer-content">
        <div class="zip-code-header">
            <span class="zip-code-label">Arquivo {{ $ext ? '.' . $ext : '' }}</span>
            @if($mode === 'csv')
                <span class="zip-code-tag">CSV</span>
            @endif
        </div>
        <div class="zip-code-container" data-code-mode="{{ $mode }}">
            @foreach($lines as $index => $line)
                <div class="zip-code-line">
                    <span class="zip-code-number">{{ $index + 1 }}</span>
                    <span class="zip-code-text">{!! $line === '' ? '&nbsp;' : e($line) !!}</span>
                </div>
            @endforeach
        </div>
    </div>
@endif