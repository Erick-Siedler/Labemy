<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Início')</title>
    @if($theme === '"light"')
    <link rel="stylesheet" href="{{ asset('main/home.css') }}">
    @else
    <link rel="stylesheet" href="{{ asset('main/home-dark.css') }}">
    @endif
    @stack('styles')
    <link rel="stylesheet" href="{{ asset('main/button-cleanup.css') }}">
</head>
@php
    $currentUser = $user ?? Auth::user();
    $notificationService = app(\App\Services\NotificationTenantService::class);
    $notificationsEnabled = $notificationService->shouldEnableForContext($currentUser);
@endphp
<body
    data-sidebar-role="{{ strtolower((string) ($user->role ?? '')) }}"
    data-sidebar-home-url="{{ (($user->plan ?? '') === 'solo') ? route('home-solo') : route('home') }}"
    data-sidebar-is-home="{{ request()->routeIs('home', 'home-solo', 'subuser-home') ? '1' : '0' }}"
    data-can-sidebar-delete="{{ (($user->role ?? '') === 'owner') ? '1' : '0' }}"
    data-can-sidebar-rename="{{ (($user->role ?? '') === 'owner') ? '1' : '0' }}"
    data-auth-user-id="{{ (int) (Auth::id() ?? 0) }}"
    data-reverb-app-key="{{ (string) config('broadcasting.connections.reverb.key') }}"
    data-reverb-host="{{ (string) config('broadcasting.connections.reverb.options.host') }}"
    data-reverb-port="{{ (int) config('broadcasting.connections.reverb.options.port') }}"
    data-reverb-scheme="{{ (string) config('broadcasting.connections.reverb.options.scheme') }}"
    data-active-tenant-id="{{ (int) session('active_tenant_id', 0) }}"
    data-notifications-enabled="{{ $notificationsEnabled ? '1' : '0' }}"
    data-notification-destroy-url="{{ route('not-destroy') }}"
    data-notification-destroy-all-url="{{ route('not-destroy-all') }}"
>
    @php
        $labs = $labs ?? collect();
        $notifications = $notifications ?? collect();
        $notifications = $notificationService->filterVisibleCollection($notifications, $currentUser);
        $renderedNotificationIds = $notifications->pluck('id')->implode(',');
    @endphp
    @yield('overlays')

    <div class="side-menu" id="sidebar">
        <div class="header-side-menu">
            <img src="{{ asset('imgs/logo.png') }}">
            <button class="toggle-sidebar-btn" id="toggle-sidebar" title="Fechar sidebar (Ctrl+B)">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                    <path fill-rule="evenodd" d="M12 8a.5.5 0 0 1-.5.5H5.707l2.147 2.146a.5.5 0 0 1-.708.708l-3-3a.5.5 0 0 1 0-.708l3-3a.5.5 0 1 1 .708.708L5.707 7.5H11.5a.5.5 0 0 1 .5.5"/>
                </svg>
            </button>
        </div>
        <div class="body-side-menu">
            @php
                $isSoloSidebar = $user->plan === 'solo';
                $sidebarProjects = $projects ?? collect();
                $soloGroupId = $soloGroupId ?? (isset($soloGroup) ? $soloGroup->id : null);
                $soloLabId = $soloLabId ?? (isset($soloLab) ? $soloLab->id : null);
                $canCreateSubfolder = $canCreateSubfolder ?? ($user->role === 'owner');
            @endphp
            <div class="menu-modes" data-sidebar-mode-toggle role="tablist" aria-label="Modo do menu lateral">
                <button
                    type="button"
                    class="menu-mode-btn is-active"
                    data-sidebar-mode="create"
                    role="tab"
                    aria-selected="true"
                >Criação</button>
                <button
                    type="button"
                    class="menu-mode-btn"
                    data-sidebar-mode="navigate"
                    role="tab"
                    aria-selected="false"
                >Navegação</button>
            </div>
            <div class="side-menu-nav">
            <div class="side-menu-create-pane" data-sidebar-pane="create">
            @if($isSoloSidebar)
                <div class="group-tag toggle solo-projects">
                    <div class="right">
                        <svg fill="#ffffff" viewBox="0 0 256 256" id="Flat" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path d="M224,64H154.667l-27.7334-20.7998A16.10323,16.10323,0,0,0,117.333,40H72A16.01833,16.01833,0,0,0,56,56V72H40A16.01833,16.01833,0,0,0,24,88V200a16.01833,16.01833,0,0,0,16,16H192.88867A15.12831,15.12831,0,0,0,208,200.88867V184h16.88867A15.12831,15.12831,0,0,0,240,168.88867V80A16.01833,16.01833,0,0,0,224,64Zm0,104H208V112a16.01833,16.01833,0,0,0-16-16H122.667L94.93359,75.2002A16.10323,16.10323,0,0,0,85.333,72H72V56h45.333l27.7334,20.7998A16.10323,16.10323,0,0,0,154.667,80H224Z"></path> </g></svg>
                        <a class="group-link" href="{{ route('home-solo') }}">Projetos</a>
                    </div>
                    <div class="left">
                        <a class="add-project-bt" data-group-id="{{ $soloGroupId }}" data-lab-id="{{ $soloLabId }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-plus-square" viewBox="0 0 16 16">
                            <path d="M14 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1zM2 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2z"/>
                            <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4"/>
                            </svg>
                        </a>
                    </div>
                </div>
                <div class="group-content collapsed">
                    @forelse ($sidebarProjects as $projectItem)
                    @php
                        $projectSubfolders = ($projectItem->subfolders ?? collect())->sortBy('order_index')->values();
                    @endphp
                    <div class="proj toggle">
                        <div class="right">
                            @if ($projectSubfolders->isEmpty())
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-journal" viewBox="0 0 16 16">
                                <path d="M3 0h10a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2v-1h1v1a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1H3a1 1 0 0 0-1 1v1H1V2a2 2 0 0 1 2-2"/>
                                <path d="M1 5v-.5a.5.5 0 0 1 1 0V5h.5a.5.5 0 0 1 0 1h-2a.5.5 0 0 1 0-1zm0 3v-.5a.5.5 0 0 1 1 0V8h.5a.5.5 0 0 1 0 1h-2a.5.5 0 0 1 0-1zm0 3v-.5a.5.5 0 0 1 1 0v.5h.5a.5.5 0 0 1 0 1h-2a.5.5 0 0 1 0-1z"/>
                                </svg>
                            @else
                                <svg class="arrow" xmlns="http://www.w3.org/2000/svg" fill="currentColor" class="bi bi-caret-down-fill" viewBox="0 0 16 16">
                                <path d="M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z"/>
                                </svg>
                            @endif
                            <a class="project-link rename-target" data-rename-type="project" data-rename-id="{{ $projectItem->id }}" data-rename-value="{{ $projectItem->title }}" href="{{ route('home-solo', ['project' => $projectItem->id]) }}">{{ $projectItem->title }}</a>
                        </div>
                        <div class="left">
                            @if($canCreateSubfolder)
                            <a class="add-subfolder-bt" data-project-id="{{ $projectItem->id }}">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-plus-square" viewBox="0 0 16 16">
                                <path d="M14 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1zM2 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2z"/>
                                <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4"/>
                                </svg>
                            </a>
                            @endif
                        </div>
                    </div>
                    <div class="project-content collapsed">
                        @foreach ($projectSubfolders as $subfolderItem)
                        <span class="subfolder">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" class="bi bi-folder-fill" viewBox="0 0 16 16">
                            <path d="M9.828 3h3.982a2 2 0 0 1 1.992 2.181l-.637 7A2 2 0 0 1 13.174 14H2.825a2 2 0 0 1-1.991-1.819l-.637-7a2 2 0 0 1 .342-1.31L.5 3a2 2 0 0 1 2-2h3.672a2 2 0 0 1 1.414.586l.828.828A2 2 0 0 0 9.828 3m-8.322.12q.322-.119.684-.12h5.396l-.707-.707A1 1 0 0 0 6.172 2H2.5a1 1 0 0 0-1 .981z"/>
                            </svg>
                            <a class="subfolder-link rename-target" data-rename-type="subfolder" data-rename-id="{{ $subfolderItem->id }}" data-rename-value="{{ $subfolderItem->name }}" href="{{ route('subfolder-index', $subfolderItem->id) }}">{{ $subfolderItem->name }}</a>
                        </span>
                        @endforeach
                        <span class="subfolder requirements-subfolder">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                                <path fill-rule="evenodd" d="M10.854 7.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7.5 9.793l2.646-2.647a.5.5 0 0 1 .708 0"/>
                                <path d="M4 1.5H3a2 2 0 0 0-2 2V14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V3.5a2 2 0 0 0-2-2h-1v1h1a1 1 0 0 1 1 1V14a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V3.5a1 1 0 0 1 1-1h1z"/>
                                <path d="M9.5 1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-3a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 1 .5-.5zm-3-1A1.5 1.5 0 0 0 5 1.5v1A1.5 1.5 0 0 0 6.5 4h3A1.5 1.5 0 0 0 11 2.5v-1A1.5 1.5 0 0 0 9.5 0z"/>
                            </svg>
                            <a class="requirements-link" href="{{ route('requirements.index', ['project' => $projectItem->id, 'rf_page' => 1, 'rnf_global_page' => 1, 'rnf_linked_page' => 1]) }}">Requisitos</a>
                        </span>
                    </div>
                    @empty
                        <span class="empty">Nenhum projeto criado</span>
                    @endforelse
                </div>
            @else
            @if(!$labs->isEmpty())
                @foreach ($labs as $labItem)
                    @if ($labItem->groups->isEmpty())
                        <div class="lab-tag">
                            <div class="right">
                                <svg  class="glass" fill="#ffffff" version="1.1" id="Capa_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 31.166 31.166" xml:space="preserve" stroke="#ffffff" stroke-width="0.00031166" transform="matrix(1, 0, 0, 1, 0, 0)"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round" stroke="#CCCCCC" stroke-width="0.062332"></g><g id="SVGRepo_iconCarrier"> <g> <g> <path d="M28.055,24.561l-7.717-11.044V3.442c0.575-0.197,0.99-0.744,0.99-1.386V1.464C21.329,0.657,20.673,0,19.866,0h-8.523 c-0.807,0-1.464,0.657-1.464,1.464v0.593c0,0.642,0.416,1.189,0.992,1.386v10l-7.76,11.118c-0.898,1.289-1.006,2.955-0.28,4.348 c0.727,1.393,2.154,2.258,3.725,2.258h18.056c1.571,0,2.999-0.866,3.725-2.259C29.062,27.514,28.954,25.848,28.055,24.561z M17.505,3.048v11.21c0,0.097,0.029,0.191,0.085,0.27l2.028,2.904h-8.077l0.906-1.298h3.135c0.261,0,0.472-0.211,0.472-0.473 c0-0.261-0.211-0.472-0.472-0.472h-2.476l0.512-0.733c0.055-0.08,0.084-0.173,0.084-0.271v-0.294h1.879 c0.261,0,0.472-0.211,0.472-0.472c0-0.261-0.211-0.472-0.472-0.472h-1.879v-1.299h1.879c0.261,0,0.472-0.211,0.472-0.472 c0-0.261-0.211-0.472-0.472-0.472h-1.879V9.405h1.879c0.261,0,0.472-0.211,0.472-0.472c0-0.261-0.211-0.473-0.472-0.473h-1.879 V7.162h1.879c0.261,0,0.472-0.211,0.472-0.472c0-0.261-0.211-0.472-0.472-0.472h-1.879v-3.17H17.505z M25.825,27.598 c-0.236,0.453-0.702,0.734-1.213,0.734H6.556c-0.511,0-0.976-0.282-1.212-0.734c-0.237-0.453-0.202-0.994,0.09-1.414l5.448-7.807 h9.396l5.454,7.805C26.025,26.602,26.06,27.145,25.825,27.598z"></path> <path d="M15.583,19.676h-3.272c-0.261,0-0.472,0.211-0.472,0.473c0,0.261,0.211,0.472,0.472,0.472h3.272 c0.261,0,0.472-0.211,0.472-0.472C16.056,19.887,15.845,19.676,15.583,19.676z"></path> <circle cx="10.113" cy="25.402" r="1.726"></circle> <circle cx="17.574" cy="22.321" r="0.512"></circle> <circle cx="20.977" cy="25.302" r="0.904"></circle> <circle cx="14.723" cy="25.174" r="0.776"></circle> </g> </g> </g></svg>
                                <a class="lab-link rename-target" data-rename-type="lab" data-rename-id="{{ $labItem->id }}" data-rename-value="{{ $labItem->name }}" href="{{ route('lab.index', $labItem->id) }}">{{ $labItem->name }}</a>
                            </div>
                            
                            <div class="left">
                                <a class="add-group-bt" data-lab-id="{{ $labItem->id }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-plus-square" viewBox="0 0 16 16">
                                    <path d="M14 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1zM2 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2z"/>
                                    <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4"/>
                                    </svg>
                                </a>
                            </div>
                        </div>
                    @else
                        <div class="lab-tag toggle">
                            <div class="right">
                                <svg class="arrow" xmlns="http://www.w3.org/2000/svg" fill="currentColor" class="bi bi-caret-down-fill" viewBox="0 0 16 16">
                                <path d="M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z"/>
                                </svg>
                                <a class="lab-link rename-target" data-rename-type="lab" data-rename-id="{{ $labItem->id }}" data-rename-value="{{ $labItem->name }}" href="{{ route('lab.index', $labItem->id) }}">{{ $labItem->name }}</a>
                            </div>

                            <div class="left">
                                <a class="add-group-bt" data-lab-id="{{ $labItem->id }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-plus-square" viewBox="0 0 16 16">
                                    <path d="M14 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1zM2 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2z"/>
                                    <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4"/>
                                    </svg>
                                </a>
                            </div>
                        </div>
                    @endif
                    <div class="lab-content collapsed">
                        @foreach ($labItem->groups as $groupItem)
                        @if ($groupItem->projects->isEmpty())
                        <div class="group-tag toggle">
                            <div class="right">
                                <svg class="group" fill="#ffffff" height="200px" width="200px" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 24 24" xml:space="preserve"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <g id="group"> <path d="M24,15.9c0-2.8-1.5-5-3.7-6.1C21.3,8.8,22,7.5,22,6c0-2.8-2.2-5-5-5c-2.1,0-3.8,1.2-4.6,3c0,0,0,0,0,0c-0.1,0-0.3,0-0.4,0 c-0.1,0-0.3,0-0.4,0c0,0,0,0,0,0C10.8,2.2,9.1,1,7,1C4.2,1,2,3.2,2,6c0,1.5,0.7,2.8,1.7,3.8C1.5,10.9,0,13.2,0,15.9V20h5v3h14v-3h5 V15.9z M17,3c1.7,0,3,1.3,3,3c0,1.6-1.3,3-3,3c0-1.9-1.1-3.5-2.7-4.4c0,0,0,0,0,0C14.8,3.6,15.8,3,17,3z M13.4,4.2 C13.4,4.2,13.4,4.2,13.4,4.2C13.4,4.2,13.4,4.2,13.4,4.2z M15,9c0,1.7-1.3,3-3,3s-3-1.3-3-3s1.3-3,3-3S15,7.3,15,9z M10.6,4.2 C10.6,4.2,10.6,4.2,10.6,4.2C10.6,4.2,10.6,4.2,10.6,4.2z M7,3c1.2,0,2.2,0.6,2.7,1.6C8.1,5.5,7,7.1,7,9C5.3,9,4,7.7,4,6S5.3,3,7,3 z M5.1,18H2v-2.1C2,13.1,4.1,11,7,11v0c0,0,0,0,0,0c0.1,0,0.2,0,0.3,0c0,0,0,0,0,0c0.3,0.7,0.8,1.3,1.3,1.8 C6.7,13.8,5.4,15.7,5.1,18z M17,21H7v-2.1c0-2.8,2.2-4.9,5-4.9c2.9,0,5,2.1,5,4.9V21z M22,18h-3.1c-0.3-2.3-1.7-4.2-3.7-5.2 c0.6-0.5,1-1.1,1.3-1.8c0.1,0,0.2,0,0.4,0v0c2.9,0,5,2.1,5,4.9V18z"></path> </g> </g></svg>
                                <a class="group-link rename-target" data-rename-type="group" data-rename-id="{{ $groupItem->id }}" data-rename-value="{{ $groupItem->name }}" href="{{ route('group.index', $groupItem->id) }}">{{ $groupItem->name }}</a>
                            </div>
                            <div class="left">
                                <a class="add-project-bt" data-group-id="{{ $groupItem->id }}" data-lab-id="{{ $labItem->id }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-plus-square" viewBox="0 0 16 16">
                                    <path d="M14 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1zM2 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2z"/>
                                    <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4"/>
                                    </svg>
                                </a>
                            </div>
                        </div>
                        @else
                        <div class="group-tag toggle">
                            <div class="right">
                                <svg class="arrow" xmlns="http://www.w3.org/2000/svg" fill="currentColor" class="bi bi-caret-down-fill" viewBox="0 0 16 16">
                                <path d="M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z"/>
                                </svg>
                                <a class="group-link rename-target" data-rename-type="group" data-rename-id="{{ $groupItem->id }}" data-rename-value="{{ $groupItem->name }}" href="{{ route('group.index', $groupItem->id) }}">{{ $groupItem->name }}</a>
                            </div>
                            <div class="left">
                                <a class="add-project-bt" data-group-id="{{ $groupItem->id }}" data-lab-id="{{ $labItem->id }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-plus-square" viewBox="0 0 16 16">
                                    <path d="M14 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1zM2 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2z"/>
                                    <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4"/>
                                    </svg>
                                </a>
                            </div>
                        </div>
                        @endif
                        <div class="group-content collapsed">
                            @foreach ($groupItem->projects as $projectItem)
                            @php
                                $projectSubfolders = ($projectItem->subfolders ?? collect())->sortBy('order_index')->values();
                            @endphp
                            <div class="proj toggle">
                                @if ($projectSubfolders->isEmpty())
                                    <div class="right">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-journal" viewBox="0 0 16 16">
                                        <path d="M3 0h10a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2v-1h1v1a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1H3a1 1 0 0 0-1 1v1H1V2a2 2 0 0 1 2-2"/>
                                        <path d="M1 5v-.5a.5.5 0 0 1 1 0V5h.5a.5.5 0 0 1 0 1h-2a.5.5 0 0 1 0-1zm0 3v-.5a.5.5 0 0 1 1 0V8h.5a.5.5 0 0 1 0 1h-2a.5.5 0 0 1 0-1zm0 3v-.5a.5.5 0 0 1 1 0v.5h.5a.5.5 0 0 1 0 1h-2a.5.5 0 0 1 0-1z"/>
                                        </svg>
                                        <a class="project-link rename-target" data-rename-type="project" data-rename-id="{{ $projectItem->id }}" data-rename-value="{{ $projectItem->title }}" href="{{ route('project.index', $projectItem->id) }}">{{ $projectItem->title }}</a>
                                    </div>
                                @else
                                    <div class="right">
                                        <svg class="arrow" xmlns="http://www.w3.org/2000/svg" fill="currentColor" class="bi bi-caret-down-fill" viewBox="0 0 16 16">
                                        <path d="M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z"/>
                                        </svg>
                                        <a class="project-link rename-target" data-rename-type="project" data-rename-id="{{ $projectItem->id }}" data-rename-value="{{ $projectItem->title }}" href="{{ route('project.index', $projectItem->id) }}">{{ $projectItem->title }}</a>
                                    </div>
                                @endif
                                <div class="left">
                                    @if($canCreateSubfolder)
                                    <a class="add-subfolder-bt" data-project-id="{{ $projectItem->id }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-plus-square" viewBox="0 0 16 16">
                                        <path d="M14 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1zM2 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2z"/>
                                        <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4"/>
                                        </svg>
                                    </a>
                                    @endif
                                </div>
                            </div>
                            <div class="project-content collapsed">
                                @foreach ($projectSubfolders as $subfolderItem)
                                <span class="subfolder">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" class="bi bi-folder-fill" viewBox="0 0 16 16">
                                    <path d="M9.828 3h3.982a2 2 0 0 1 1.992 2.181l-.637 7A2 2 0 0 1 13.174 14H2.825a2 2 0 0 1-1.991-1.819l-.637-7a2 2 0 0 1 .342-1.31L.5 3a2 2 0 0 1 2-2h3.672a2 2 0 0 1 1.414.586l.828.828A2 2 0 0 0 9.828 3m-8.322.12q.322-.119.684-.12h5.396l-.707-.707A1 1 0 0 0 6.172 2H2.5a1 1 0 0 0-1 .981z"/>
                                    </svg>
                                    <a class="subfolder-link rename-target" data-rename-type="subfolder" data-rename-id="{{ $subfolderItem->id }}" data-rename-value="{{ $subfolderItem->name }}" href="{{ route('subfolder-index', $subfolderItem->id) }}">{{ $subfolderItem->name }}</a>
                                </span>
                                @endforeach
                                <span class="subfolder requirements-subfolder">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M10.854 7.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7.5 9.793l2.646-2.647a.5.5 0 0 1 .708 0"/>
                                        <path d="M4 1.5H3a2 2 0 0 0-2 2V14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V3.5a2 2 0 0 0-2-2h-1v1h1a1 1 0 0 1 1 1V14a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V3.5a1 1 0 0 1 1-1h1z"/>
                                        <path d="M9.5 1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-3a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 1 .5-.5zm-3-1A1.5 1.5 0 0 0 5 1.5v1A1.5 1.5 0 0 0 6.5 4h3A1.5 1.5 0 0 0 11 2.5v-1A1.5 1.5 0 0 0 9.5 0z"/>
                                    </svg>
                                    <a class="requirements-link" href="{{ route('requirements.index', ['project' => $projectItem->id, 'rf_page' => 1, 'rnf_global_page' => 1, 'rnf_linked_page' => 1]) }}">Requisitos</a>
                                </span>
                            </div>
                            @endforeach
                        </div>
                        @endforeach
                    </div>
                @endforeach
            @else
                <span class="empty">Nenhum laboratório criado</span>
            @endif
            @endif
            </div>
            <div class="side-menu-navigate-pane" data-sidebar-pane="navigate" hidden>
                <div class="side-nav-list" data-owner-tablist data-sidebar-nav-list role="tablist" aria-label="Navegação por seções"></div>
                <p class="side-nav-empty" data-sidebar-nav-empty hidden>Nenhum atalho disponível nesta tela.</p>
            </div>
            </div>
            
            @if(!$isSoloSidebar)
            <div class="bt">
                <a class="add-bt-lab">
                    <svg viewBox="0 0 20 20" fill="currentColor">
                        <path d="M10 2a1 1 0 011 1v6h6a1 1 0 110 2h-6v6a1 1 0 11-2 0v-6H3a1 1 0 110-2h6V3a1 1 0 011-1z"/>
                    </svg>
                    <span>Novo laboratório</span>
                </a>
            </div>
            @endif
        </div>
    </div>

    <div class="sidebar-resizer" id="sidebar-resizer"></div>

    <div class="container-data">
        <header>
            <div class="header-left">
                <h2>{{ $pageTitle ?? 'Painel' }}</h2>
                <div class="home-over">
                    @if($isSoloSidebar)
                    <span class="home"><a href="{{ route('home-solo') }}">{{ $pageBreadcrumbHome ?? 'Início' }}</a></span>
                    @else
                    <span class="home"><a href="{{ route('home') }}">{{ $pageBreadcrumbHome ?? 'Início' }}</a></span>
                    @endif
                    <span class="bar">/</span>
                    <span class="over">{{ $pageBreadcrumbCurrent ?? 'Visão geral' }}</span>
                </div>
            </div>
            <div class="header-right">
                
                @if($notificationsEnabled)
                <div class="bell notifications" id="notifBell">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" class="bi bi-bell" viewBox="0 0 16 16">
                    <path d="M8 16a2 2 0 0 0 2-2H6a2 2 0 0 0 2 2M8 1.918l-.797.161A4 4 0 0 0 4 6c0 .628-.134 2.197-.459 3.742-.16.767-.376 1.566-.663 2.258h10.244c-.287-.692-.502-1.49-.663-2.258C12.134 8.197 12 6.628 12 6a4 4 0 0 0-3.203-3.92zM14.22 12c.223.447.481.801.78 1H1c.299-.199.557-.553.78-1C2.68 10.2 3 6.88 3 6c0-2.42 1.72-4.44 4.005-4.901a1 1 0 1 1 1.99 0A5 5 0 0 1 13 6c0 .88.32 4.2 1.22 6"/>
                    </svg>
                    @if ($notifications->count() > 0)
                    <span class="badge">{{ $notifications->count() }}</span>
                    @endif
                </div>
                @endif
                @if(($pageTitle ?? '') !== 'Painel')
                    @if(isset($lab) && ($pageTitle ?? '') === $lab->name)
                    <form action="{{ route('lab-update') }}" method="POST" id="labStatusForm">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="lab_id" value="{{ $lab->id }}">

                        <select id="selStatus" name="status" data-current="{{ $lab->status }}">
                            <option value="draft" {{ $lab->status === 'draft' ? 'selected' : '' }}>Rascunho</option>
                            <option value="active" {{ $lab->status === 'active' ? 'selected' : '' }}>Ativo</option>
                            <option value="closed" {{ $lab->status === 'closed' ? 'selected' : '' }}>Fechado</option>
                            <option value="archived" {{ $lab->status === 'archived' ? 'selected' : '' }}>Arquivado</option>
                        </select>
                    </form>
                    @elseif(isset($group) && ($pageTitle ?? '') === $group->name)
                    <form action="{{ route('group-update') }}" method="POST" id="groupStatusForm">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="group_id" value="{{ $group->id }}">

                        <select id="groupStatusSelect" name="status" data-current="{{ $group->status }}">
                            <option value="active" {{ $group->status === 'active' ? 'selected' : '' }}>Ativo</option>
                            <option value="inactive" {{ $group->status === 'inactive' ? 'selected' : '' }}>Inativo</option>
                            <option value="archived" {{ $group->status === 'archived' ? 'selected' : '' }}>Arquivado</option>
                        </select>
                    </form>
                    @elseif(isset($project) && ($pageTitle ?? '') === $project->title)
                    <form action="{{ route('project-update') }}" method="POST" id="projectStatusForm">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="project_id" value="{{ $project->id }}">

                        <select id="projectStatusSelect" name="status" data-current="{{ $project->status }}">
                            <option value="draft" {{ $project->status === 'draft' ? 'selected' : '' }}>Rascunho</option>
                            <option value="in_progress" {{ $project->status === 'in_progress' ? 'selected' : '' }}>Em andamento</option>
                            <option value="approved" {{ $project->status === 'approved' ? 'selected' : '' }}>Aprovado</option>
                            <option value="rejected" {{ $project->status === 'rejected' ? 'selected' : '' }}>Rejeitado</option>
                            <option value="archived" {{ $project->status === 'archived' ? 'selected' : '' }}>Arquivado</option>
                        </select>
                    </form>
                    @endif
                @endif
                <div class="user-cont" id="userCont">
                    <div class="user-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-fill" viewBox="0 0 16 16">
                        <path d="M3 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6"/>
                        </svg>
                    </div>
                    
                    <span>{{ $user->name ?? Auth::user()?->name ?? Auth::user()?->name ?? 'Usuario' }}</span>

                    <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" fill="currentColor" class="bi bi-caret-down-fill" viewBox="0 0 16 16">
                    <path d="M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z"/>
                    </svg>
                    <div class="user-dropdown" id="user-dropdown" hidden>
                    <ul>
                        <li>
                            <a href="{{ route('settings') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-fill" viewBox="0 0 16 16">
                            <path d="M3 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6"/>
                            </svg>    
                            Perfil
                            </a>
                        </li>

                        <li>
                            <form action="{{ route('logout') }}" method="POST">
                                @csrf
                                <button type="submit" class="logout-btn">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-box-arrow-left" viewBox="0 0 16 16">
                                    <path fill-rule="evenodd" d="M6 12.5a.5.5 0 0 0 .5.5h8a.5.5 0 0 0 .5-.5v-9a.5.5 0 0 0-.5-.5h-8A.5.5 0 0 0 6 3.5v2a.5.5 0 0 1-1 0v-2A1.5 1.5 0 0 1 6.5 2h8A1.5 1.5 0 0 1 16 3.5v9a1.5 1.5 0 0 1-1.5 1.5h-8A1.5 1.5 0 0 1 5 12.5v-2a.5.5 0 0 1 1 0z"/>
                                    <path fill-rule="evenodd" d="M.146 8.354a.5.5 0 0 1 0-.708l3-3a.5.5 0 1 1 .708.708L1.707 7.5H10.5a.5.5 0 0 1 0 1H1.707l2.147 2.146a.5.5 0 0 1-.708.708z"/>
                                    </svg>
                                    Sair
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
                </div>
                
            </div>
        </header>

        @yield('content')
    </div>

    @if($notificationsEnabled)
    <div class="not-menu" id="notMenu">
        <div class="header-not">
            <div class="header-not-left">
                <h2>Notificações</h2>
                @if ($notifications->count() > 0)
                    <span class="not-count">{{ $notifications->count() }}</span>
                @endif
            </div>
            <div class="header-not-actions">
                @if ($notifications->count() > 0)
                <form action="{{ route('not-destroy-all') }}" method="POST" class="not-clear-form" onsubmit="return confirm('Remover todas as notificacoes?');">
                    @csrf
                    <input type="hidden" name="ids" value="{{ $renderedNotificationIds }}">
                    <button type="submit" class="not-clear-btn">Limpar todas</button>
                </form>
                @endif
                <button type="button" class="close-not-menu" id="closeNotMenu">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
        </div>
        <div class="body-not">
            <div class="not-nav">
                @forelse ($notifications as $notification)
                    <div class="not-card" data-notification-id="{{ $notification->id }}">
                        <div class="not-text">{{ $notification->description }}</div>
                        <form action="{{ route('not-destroy') }}" method="POST">
                            @csrf
                            <input type="hidden" name="id" value="{{ $notification->id }}">
                            <button type="submit" class="not-delete" title="Excluir">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-eye" viewBox="0 0 16 16">
                                <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/>
                                <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/>
                                </svg>
                            </button>
                        </form>
                    </div>
                @empty
                    <div class="not-empty">Nenhuma notificação no momento.</div>
                @endforelse
            </div>
        </div>
    </div>
    @endif

    @yield('modals')

    <script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>
    <script src="{{ asset('script/home.js') }}"></script>
    @stack('scripts')
</body>
</html>
