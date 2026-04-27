@extends('layouts.header-side-not')

@php
    $pageTitle = $pageTitle ?? 'Projetos';
    $pageBreadcrumbHome = $pageBreadcrumbHome ?? 'Início';
    $pageBreadcrumbCurrent = $pageBreadcrumbCurrent ?? 'Projetos';
    $labs = $labs ?? collect();
    $notifications = $notifications ?? collect();
    $projects = $projects ?? collect();

    $project = $project ?? (isset($selectedProject) ? $selectedProject : null);
    $versions = $versions ?? collect();
    $latestVersion = $latestVersion ?? null;
    $versionStats = $versionStats ?? ['draft' => 0, 'submitted' => 0, 'approved' => 0, 'rejected' => 0];
    $projectFilesCount = $projectFilesCount ?? 0;
    $projectStorageMb = $projectStorageMb ?? 0;
    $maxUploadMb = $maxUploadMb ?? 0;

    $projectStatus = $project ? 'status-' . str_replace('_', '-', $project->status) : 'status-draft';
    $latestSubmittedAt = $latestVersion?->submitted_at ?? $project?->submitted_at;
    $latestApprovedAt = $latestVersion?->approved_at ?? $project?->approved_at;
    $submittedAtFormatted = $latestSubmittedAt
        ? \Carbon\Carbon::parse($latestSubmittedAt)->format('d/m/Y')
        : '-';
    $approvedAtFormatted = $latestApprovedAt
        ? \Carbon\Carbon::parse($latestApprovedAt)->format('d/m/Y')
        : '-';
    $hasProject = !is_null($project);
@endphp

@section('title', 'Projetos')

@if($theme === '"light"')
@push('styles')
<link rel="stylesheet" href="{{ asset('main/solo.css') }}">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
@endpush
@else
@push('styles')
<link rel="stylesheet" href="{{ asset('main/solo-dark.css') }}">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
@endpush
@endif

@section('overlays')
<div class="version-overlay {{ $errors->any() ? 'is-open' : '' }}" id="versionOverlay"></div>
@endsection

@section('content')
<div class="container-info solo-dashboard">
    <div class="project-hero" data-animate>
        <div>
            <h2>{{ $hasProject ? $project->title : 'Nenhum projeto selecionado' }}</h2>
            <p class="project-sub">
                @if($hasProject)
                    ID {{ $project->id }}
                @else
                    Crie um projeto para começar.
                @endif
            </p>
        </div>
        <div class="project-actions">
            <button type="button" title="Painel" class="btn-secondary" data-panel-target="dashboard">
                @if($theme === '"light"' || $theme === '"automatic"')
                <svg viewBox="0 0 25 25" fill="none" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path d="M14.5 19.5V12.5M10.5 12.5V5.5M5.5 12.5H19.5M5.5 19.5H19.5V5.5H5.5V19.5Z" stroke="#333" stroke-width="1.2"></path> </g></svg>
                @else
                <svg viewBox="0 0 25 25" fill="none" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path d="M14.5 19.5V12.5M10.5 12.5V5.5M5.5 12.5H19.5M5.5 19.5H19.5V5.5H5.5V19.5Z" stroke="#ffffff" stroke-width="1.2"></path> </g></svg>
                @endif
                
            </button>
            <button type="button" title="Versões" class="btn-secondary" data-panel-target="versions">
                @if($theme === '"light"')
                <svg viewBox="0 0 24 24" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" fill="#3f51b5"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <title>version_fill</title> <g id="页面-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd"> <g id="System" transform="translate(-1488.000000, -48.000000)" fill-rule="nonzero"> <g id="version_fill" transform="translate(1488.000000, 48.000000)"> <path d="M24,0 L24,24 L0,24 L0,0 L24,0 Z M12.5934901,23.257841 L12.5819402,23.2595131 L12.5108777,23.2950439 L12.4918791,23.2987469 L12.4918791,23.2987469 L12.4767152,23.2950439 L12.4056548,23.2595131 C12.3958229,23.2563662 12.3870493,23.2590235 12.3821421,23.2649074 L12.3780323,23.275831 L12.360941,23.7031097 L12.3658947,23.7234994 L12.3769048,23.7357139 L12.4804777,23.8096931 L12.4953491,23.8136134 L12.4953491,23.8136134 L12.5071152,23.8096931 L12.6106902,23.7357139 L12.6232938,23.7196733 L12.6232938,23.7196733 L12.6266527,23.7031097 L12.609561,23.275831 C12.6075724,23.2657013 12.6010112,23.2592993 12.5934901,23.257841 L12.5934901,23.257841 Z M12.8583906,23.1452862 L12.8445485,23.1473072 L12.6598443,23.2396597 L12.6498822,23.2499052 L12.6498822,23.2499052 L12.6471943,23.2611114 L12.6650943,23.6906389 L12.6699349,23.7034178 L12.6699349,23.7034178 L12.678386,23.7104931 L12.8793402,23.8032389 C12.8914285,23.8068999 12.9022333,23.8029875 12.9078286,23.7952264 L12.9118235,23.7811639 L12.8776777,23.1665331 C12.8752882,23.1545897 12.8674102,23.1470016 12.8583906,23.1452862 L12.8583906,23.1452862 Z M12.1430473,23.1473072 C12.1332178,23.1423925 12.1221763,23.1452606 12.1156365,23.1525954 L12.1099173,23.1665331 L12.0757714,23.7811639 C12.0751323,23.7926639 12.0828099,23.8018602 12.0926481,23.8045676 L12.108256,23.8032389 L12.3092106,23.7104931 L12.3186497,23.7024347 L12.3186497,23.7024347 L12.3225043,23.6906389 L12.340401,23.2611114 L12.337245,23.2485176 L12.337245,23.2485176 L12.3277531,23.2396597 L12.1430473,23.1473072 Z" id="MingCute" fill-rule="nonzero"> </path> <path d="M20.2451,14.75 C21.18,15.3637 21.1371,16.7868 20.1165,17.3263 L12.9348,21.1225 C12.35,21.4316 11.6503,21.4316 11.0655,21.1225 L3.88381,17.3263 C2.86325,16.7868 2.82034,15.3638 3.75508,14.75 L3.817995,14.7892 L3.817995,14.7892 L11.0654,18.6222 C11.6502,18.9313 12.35,18.9313 12.9347,18.6222 L20.1164,14.826 C20.1612,14.8023 20.2041,14.7769 20.2451,14.75 Z M20.2451,10.75 C21.1393522,11.3370174 21.138982,12.6645822 20.2440771,13.2510586 L20.1165,13.3263 L12.9348,17.1225 C12.4031636,17.4035 11.7765686,17.4290455 11.227667,17.1991364 L11.0655,17.1225 L3.88381,13.3263 C2.86325,12.7868 2.82034,11.3638 3.75508,10.75 L3.817995,10.7892 L3.817995,10.7892 L11.0654,14.6222 C11.5970364,14.9032 12.223714,14.9287455 12.7725555,14.6988364 L12.9347,14.6222 L20.1164,10.826 C20.1612,10.8023 20.2041,10.7769 20.2451,10.75 Z M12.9347,2.87782 L20.1164,6.67404 C21.1818,7.23718 21.1818,8.76316 20.1164,9.32629 L12.9347,13.1225 C12.35,13.4316 11.6502,13.4316 11.0654,13.1225 L3.88373,9.32629 C2.81838,8.76315 2.81838,7.23718 3.88373,6.67404 L11.0654,2.87782 C11.6502,2.56872 12.35,2.56872 12.9347,2.87782 Z" id="形状" fill="#333"> </path> </g> </g> </g> </g></svg>
                @else
                <svg viewBox="0 0 24 24" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" fill="#3f51b5"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <title>version_fill</title> <g id="页面-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd"> <g id="System" transform="translate(-1488.000000, -48.000000)" fill-rule="nonzero"> <g id="version_fill" transform="translate(1488.000000, 48.000000)"> <path d="M24,0 L24,24 L0,24 L0,0 L24,0 Z M12.5934901,23.257841 L12.5819402,23.2595131 L12.5108777,23.2950439 L12.4918791,23.2987469 L12.4918791,23.2987469 L12.4767152,23.2950439 L12.4056548,23.2595131 C12.3958229,23.2563662 12.3870493,23.2590235 12.3821421,23.2649074 L12.3780323,23.275831 L12.360941,23.7031097 L12.3658947,23.7234994 L12.3769048,23.7357139 L12.4804777,23.8096931 L12.4953491,23.8136134 L12.4953491,23.8136134 L12.5071152,23.8096931 L12.6106902,23.7357139 L12.6232938,23.7196733 L12.6232938,23.7196733 L12.6266527,23.7031097 L12.609561,23.275831 C12.6075724,23.2657013 12.6010112,23.2592993 12.5934901,23.257841 L12.5934901,23.257841 Z M12.8583906,23.1452862 L12.8445485,23.1473072 L12.6598443,23.2396597 L12.6498822,23.2499052 L12.6498822,23.2499052 L12.6471943,23.2611114 L12.6650943,23.6906389 L12.6699349,23.7034178 L12.6699349,23.7034178 L12.678386,23.7104931 L12.8793402,23.8032389 C12.8914285,23.8068999 12.9022333,23.8029875 12.9078286,23.7952264 L12.9118235,23.7811639 L12.8776777,23.1665331 C12.8752882,23.1545897 12.8674102,23.1470016 12.8583906,23.1452862 L12.8583906,23.1452862 Z M12.1430473,23.1473072 C12.1332178,23.1423925 12.1221763,23.1452606 12.1156365,23.1525954 L12.1099173,23.1665331 L12.0757714,23.7811639 C12.0751323,23.7926639 12.0828099,23.8018602 12.0926481,23.8045676 L12.108256,23.8032389 L12.3092106,23.7104931 L12.3186497,23.7024347 L12.3186497,23.7024347 L12.3225043,23.6906389 L12.340401,23.2611114 L12.337245,23.2485176 L12.337245,23.2485176 L12.3277531,23.2396597 L12.1430473,23.1473072 Z" id="MingCute" fill-rule="nonzero"> </path> <path d="M20.2451,14.75 C21.18,15.3637 21.1371,16.7868 20.1165,17.3263 L12.9348,21.1225 C12.35,21.4316 11.6503,21.4316 11.0655,21.1225 L3.88381,17.3263 C2.86325,16.7868 2.82034,15.3638 3.75508,14.75 L3.817995,14.7892 L3.817995,14.7892 L11.0654,18.6222 C11.6502,18.9313 12.35,18.9313 12.9347,18.6222 L20.1164,14.826 C20.1612,14.8023 20.2041,14.7769 20.2451,14.75 Z M20.2451,10.75 C21.1393522,11.3370174 21.138982,12.6645822 20.2440771,13.2510586 L20.1165,13.3263 L12.9348,17.1225 C12.4031636,17.4035 11.7765686,17.4290455 11.227667,17.1991364 L11.0655,17.1225 L3.88381,13.3263 C2.86325,12.7868 2.82034,11.3638 3.75508,10.75 L3.817995,10.7892 L3.817995,10.7892 L11.0654,14.6222 C11.5970364,14.9032 12.223714,14.9287455 12.7725555,14.6988364 L12.9347,14.6222 L20.1164,10.826 C20.1612,10.8023 20.2041,10.7769 20.2451,10.75 Z M12.9347,2.87782 L20.1164,6.67404 C21.1818,7.23718 21.1818,8.76316 20.1164,9.32629 L12.9347,13.1225 C12.35,13.4316 11.6502,13.4316 11.0654,13.1225 L3.88373,9.32629 C2.81838,8.76315 2.81838,7.23718 3.88373,6.67404 L11.0654,2.87782 C11.6502,2.56872 12.35,2.56872 12.9347,2.87782 Z" id="形状" fill="#ffffff"> </path> </g> </g> </g> </g></svg>
                @endif
            </button>
        </div>
    </div>

    <div class="project-stack" data-panel-stack>
        <section class="project-panel panel-dashboard" data-panel="dashboard">
            <div class="summary-cards" data-animate>
                <div class="stat-card">
                    @if(!$hasProject)
                    <div class="icon yellow">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-earmark-fill" viewBox="0 0 16 16">
                            <path d="M4 0h5.293A1 1 0 0 1 10 .293L13.707 4a1 1 0 0 1 .293.707V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2m5.5 1.5v2a1 1 0 0 0 1 1h2z"/>
                        </svg>
                    </div>
                    <div class="info">
                        <h3>Status atual</h3>
                        <span class="status-badge {{ $projectStatus }}">Sem projeto</span>
                    </div>
                    @elseif ($project->status == 'approved')
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
                        <svg viewBox="0 0 24 24" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" fill="#3f51b5"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <g id="页面-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd"> <g id="System" transform="translate(-1488.000000, -48.000000)" fill-rule="nonzero"> <g id="version_fill" transform="translate(1488.000000, 48.000000)"> <path d="M24,0 L24,24 L0,24 L0,0 L24,0 Z M12.5934901,23.257841 L12.5819402,23.2595131 L12.5108777,23.2950439 L12.4918791,23.2987469 L12.4918791,23.2987469 L12.4767152,23.2950439 L12.4056548,23.2595131 C12.3958229,23.2563662 12.3870493,23.2590235 12.3821421,23.2649074 L12.3780323,23.275831 L12.360941,23.7031097 L12.3658947,23.7234994 L12.3769048,23.7357139 L12.4804777,23.8096931 L12.4953491,23.8136134 L12.4953491,23.8136134 L12.5071152,23.8096931 L12.6106902,23.7357139 L12.6232938,23.7196733 L12.6232938,23.7196733 L12.6266527,23.7031097 L12.609561,23.275831 C12.6075724,23.2657013 12.6010112,23.2592993 12.5934901,23.257841 L12.5934901,23.257841 Z M12.8583906,23.1452862 L12.8445485,23.1473072 L12.6598443,23.2396597 L12.6498822,23.2499052 L12.6498822,23.2499052 L12.6471943,23.2611114 L12.6650943,23.6906389 L12.6699349,23.7034178 L12.6699349,23.7034178 L12.678386,23.7104931 L12.8793402,23.8032389 C12.8914285,23.8068999 12.9022333,23.8029875 12.9078286,23.7952264 L12.9118235,23.7811639 L12.8776777,23.1665331 C12.8752882,23.1545897 12.8674102,23.1470016 12.8583906,23.1452862 L12.8583906,23.1452862 Z M12.1430473,23.1473072 C12.1332178,23.1423925 12.1221763,23.1452606 12.1156365,23.1525954 L12.1099173,23.1665331 L12.0757714,23.7811639 C12.0751323,23.7926639 12.0828099,23.8018602 12.0926481,23.8045676 L12.108256,23.8032389 L12.3092106,23.7104931 L12.3186497,23.7024347 L12.3186497,23.7024347 L12.3225043,23.6906389 L12.340401,23.2611114 L12.337245,23.2485176 L12.337245,23.2485176 L12.3277531,23.2396597 L12.1430473,23.1473072 Z" id="MingCute" fill-rule="nonzero"> </path> <path d="M20.2451,14.75 C21.18,15.3637 21.1371,16.7868 20.1165,17.3263 L12.9348,21.1225 C12.35,21.4316 11.6503,21.4316 11.0655,21.1225 L3.88381,17.3263 C2.86325,16.7868 2.82034,15.3638 3.75508,14.75 L3.817995,14.7892 L3.817995,14.7892 L11.0654,18.6222 C11.6502,18.9313 12.35,18.9313 12.9347,18.6222 L20.1164,14.826 C20.1612,14.8023 20.2041,14.7769 20.2451,14.75 Z M20.2451,10.75 C21.1393522,11.3370174 21.138982,12.6645822 20.2440771,13.2510586 L20.1165,13.3263 L12.9348,17.1225 C12.4031636,17.4035 11.7765686,17.4290455 11.227667,17.1991364 L11.0655,17.1225 L3.88381,13.3263 C2.86325,12.7868 2.82034,11.3638 3.75508,10.75 L3.817995,10.7892 L3.817995,10.7892 L11.0654,14.6222 C11.5970364,14.9032 12.223714,14.9287455 12.7725555,14.6988364 L12.9347,14.6222 L20.1164,10.826 C20.1612,10.8023 20.2041,10.7769 20.2451,10.75 Z M12.9347,2.87782 L20.1164,6.67404 C21.1818,7.23718 21.1818,8.76316 20.1164,9.32629 L12.9347,13.1225 C12.35,13.4316 11.6502,13.4316 11.0654,13.1225 L3.88373,9.32629 C2.81838,8.76315 2.81838,7.23718 3.88373,6.67404 L11.0654,2.87782 C11.6502,2.56872 12.35,2.56872 12.9347,2.87782 Z" id="形状" fill="#8396ff"> </path> </g> </g> </g> </g></svg>
                    </div> 
                    <div class="info">
                        <h3>Versão atual</h3>
                        <h4>{{ $hasProject ? ($project->current_version ?? ($latestVersion->version_number ?? '-')) : '-' }}</h4>
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
                        <h3>Última submissão</h3>
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
                        <h3>Última aprovação</h3>
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
                        <h4>{{ $hasProject ? $projectFilesCount : 0 }}</h4>
                    </div>
                </div> 
            </div>
            <div class="info-grid" data-animate>
                <div class="info-card">
                    <h3>Resumo de versões</h3>
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
                    <h3>Descrição</h3>
                    <p class="project-description">
                        {{ $hasProject ? ($project->description ?: 'Sem descrição.') : 'Sem projeto selecionado.' }}
                    </p>
                </div>
            </div>
        </section>

        <section class="project-panel panel-versions is-active" data-panel="versions">
            <div class="versions-section versions-section--wide" data-animate>
                <div class="section-header">
                    <div>
                        <h3>Versões do projeto</h3>
                        <p class="section-sub">
                            @if($hasProject)
                                Total: {{ $versions->count() }}
                            @else
                                Selecione ou crie um projeto.
                            @endif
                        </p>
                    </div>
                    @if($hasProject)
                        <button type="button" class="btn-secondary" id="openVersionFormBtnSecondary">Nova versão</button>
                    @endif
                </div>

                @if(!$hasProject)
                    <div class="empty-state">Crie um projeto para adicionar versões.</div>
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
                                        <div class="board-column">
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
                        <div class="version-detail-overlay" data-version-detail-overlay></div>
                    </div>
                @else
                    <div class="empty-state">Sem versões cadastradas.</div>
                @endif
            </div>
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script src="{{ asset('script/proj.js') }}"></script>
@endpush

@section('modals')
@if($hasProject)
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
            <h3>Nova versão</h3>
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

