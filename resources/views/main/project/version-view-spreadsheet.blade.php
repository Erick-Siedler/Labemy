@php
    $sheetPreviews = $sheetPreviews ?? [];
    $truncated = $truncated ?? false;
    $ext = $ext ?? '';
@endphp

<div class="zip-viewer-content">
    <div class="zip-code-header">
        <span class="zip-code-label">Arquivo {{ $ext ? '.' . $ext : '' }}</span>
        <span class="zip-code-tag">Planilha</span>
    </div>

    @if(empty($sheetPreviews))
        <div class="zip-viewer-unsupported">
            <strong>Visualizacao indisponivel</strong>
            <p>Este arquivo nao possui dados tabulares para exibir.</p>
        </div>
    @else
        @foreach($sheetPreviews as $sheet)
            @php
                $rows = $sheet['rows'] ?? [];
                $displayCols = max(1, (int) ($sheet['displayCols'] ?? 1));
                $displayRows = (int) ($sheet['displayRows'] ?? 0);
                $totalRows = (int) ($sheet['totalRows'] ?? $displayRows);
                $totalCols = (int) ($sheet['totalCols'] ?? $displayCols);
            @endphp

            <section class="zip-sheet">
                <div class="zip-sheet-head">
                    <strong>{{ $sheet['name'] ?? 'Planilha' }}</strong>
                    <span>Linhas: {{ $displayRows }} de {{ $totalRows }} - Colunas: {{ min($displayCols, $totalCols ?: $displayCols) }} de {{ $totalCols }}</span>
                </div>

                <div class="zip-sheet-table-wrap">
                    <table class="zip-sheet-table">
                        <thead>
                            <tr>
                                <th class="zip-sheet-rownum">#</th>
                                @for($col = 1; $col <= $displayCols; $col++)
                                    <th>{{ \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) }}</th>
                                @endfor
                            </tr>
                        </thead>
                        <tbody>
                            @if(empty($rows))
                                <tr>
                                    <td class="zip-sheet-rownum">1</td>
                                    @for($col = 1; $col <= $displayCols; $col++)
                                        <td></td>
                                    @endfor
                                </tr>
                            @else
                                @foreach($rows as $rowIndex => $row)
                                    <tr>
                                        <td class="zip-sheet-rownum">{{ $rowIndex + 1 }}</td>
                                        @for($col = 0; $col < $displayCols; $col++)
                                            <td>{{ $row[$col] ?? '' }}</td>
                                        @endfor
                                    </tr>
                                @endforeach
                            @endif
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach
    @endif

    @if($truncated)
        <div class="zip-sheet-note">
            Preview parcial: limite de linhas, colunas, planilhas ou tamanho de celulas atingido.
        </div>
    @endif
</div>
