@php
    $layout = auth()->check() ? 'layouts.header-side-not-sub' : 'layouts.header-side-not';
    $backUrl = null;
    if (auth()->check()) {
        $subUser = auth()->user();
        if (($subUser?->role ?? 'student') !== 'student' && !empty($version->subfolder_id)) {
            $backUrl = route('subfolder-index', $version->subfolder_id);
        } else {
            $backUrl = route('subuser-home', ['project' => $version->project_id]);
        }
    } else {
        $backUrl = Auth::user()?->plan === 'solo'
            ? route('home-solo', ['project' => $version->project_id])
            : (!empty($version->subfolder_id)
                ? route('subfolder-index', $version->subfolder_id)
                : route('project.index', $version->project_id));
    }
@endphp

@extends($layout)

@section('title', 'Arquivos da Versão')

@push('styles')
<link rel="stylesheet" href="{{ asset('main/version-browser.css') }}">
@endpush

@section('content')
<div class="container-info version-browser" data-version-browser data-view-url="{{ route('versions.view', $version->id) }}" data-raw-url="{{ route('versions.raw', $version->id) }}">
    <div class="version-browser-header" data-animate>
        <div>
            <h2>Arquivos da versão {{ $version->version_number }}</h2>
            <p class="version-browser-sub">{{ $version->title }}</p>
        </div>
        <div class="version-browser-actions">
            @if($backUrl)
                <a class="btn-secondary" href="{{ $backUrl }}">Voltar ao projeto</a>
            @endif
        </div>
    </div>

    <div class="version-browser-grid" data-animate>
        <aside class="version-browser-card zip-tree-card">
            <div class="zip-card-header">
                <h3>Estrutura do ZIP</h3>
                <span class="zip-card-sub">{{ $version->title }}</span>
            </div>
            <div class="zip-tree" data-zip-tree>
                @if(!empty($tree['children']))
                    @include('main.project.partials._zip_tree', ['nodes' => $tree['children'], 'version' => $version])
                @else
                    <div class="zip-tree-empty">Nenhum arquivo encontrado neste ZIP.</div>
                @endif
            </div>
        </aside>

        <section class="version-browser-card zip-viewer-card">
            <div class="zip-card-header zip-viewer-header">
                <div>
                    <h3>Visualização</h3>
                    <div class="zip-breadcrumb" data-viewer-breadcrumb>Selecione um arquivo</div>
                </div>
                <div class="zip-viewer-actions">
                    <a class="btn-secondary is-hidden" data-viewer-download href="#">Download arquivo</a>
                </div>
            </div>
            <div class="zip-viewer-body" data-viewer-body>
                <div class="zip-viewer-empty">
                    Selecione um arquivo na árvore para visualizar.
                </div>
            </div>
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('script/version-browser.js') }}"></script>
@endpush

