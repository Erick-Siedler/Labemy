@php
    $paginationState = $paginationState ?? [
        'rf_page' => max(1, (int) request('rf_page', 1)),
        'rnf_global_page' => max(1, (int) request('rnf_global_page', 1)),
        'rnf_linked_page' => max(1, (int) request('rnf_linked_page', 1)),
    ];
@endphp
<input type="hidden" name="rf_page" value="{{ $paginationState['rf_page'] }}">
<input type="hidden" name="rnf_global_page" value="{{ $paginationState['rnf_global_page'] }}">
<input type="hidden" name="rnf_linked_page" value="{{ $paginationState['rnf_linked_page'] }}">