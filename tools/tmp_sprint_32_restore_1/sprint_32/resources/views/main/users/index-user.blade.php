@extends(($user->role ?? null) === 'owner' ? 'layouts.header-side-not' : 'layouts.header-side-not-sub')

@section('title', 'Configurações')

@if($theme === '"light"' || $theme === '"automatic"')
@push('styles')
    <link rel="stylesheet" href="{{ asset('main/user.css') }}">
@endpush
@else
@push('styles')
    <link rel="stylesheet" href="{{ asset('main/user-dark.css') }}">
@endpush
@endif

@section('content')
@php
    $userPreferences = Auth::user()->preferences ?? [];
    $userNotifications = Auth::user()->notifications ?? [];
    $timezoneValue = old('settings.timezone', $userPreferences['timezone'] ?? 'America/Sao_Paulo');
    $roleLabels = [
        'owner' => 'Proprietário',
        'teacher' => 'Professor',
        'assistant' => 'Assistente',
        'student' => 'Aluno',
    ];
@endphp
<div class="user-settings">
    <div class="settings-header">
        <div>
            <h3>Configuracoes de Conta</h3>
            <p>Gerencie seu perfil, seguranca e preferencias em um so lugar.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="settings-alert success">
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="settings-alert error">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="user-hero">
        <div class="user-card">
            @if($user->profile_photo_path == '')
            <div class="user-avatar" style="background: #1e1e1e;">
                
                <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M3 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6"/>
                </svg>
            </div>
            @else
            <div class="user-avatar" style="background-image: url('{{ asset('storage/' . $user->profile_photo_path) }}');">
            </div>
            @endif
            <div class="user-meta">
                <h4>{{ Auth::user()->name }}</h4>
                <p>{{ Auth::user()->email }}</p>
                <div class="user-tags">
                    <span class="tag">{{ $roleLabels[Auth::user()->role] ?? 'Usuário' }}</span>
                    <span class="tag light">ID #{{ Auth::user()->id }}</span>
                </div>
            </div>
        </div>

        <div class="summary-card">
            <h4>Resumo</h4>
            <div class="summary-list">
                <div class="summary-item">
                    <span>Conta criada</span>
                    <strong>
                        {{ Auth::user()->created_at ? Auth::user()->created_at->format('d/m/Y') : '-' }}
                    </strong>
                </div>
                <div class="summary-item">
                    <span>Email verificado</span>
                    <strong>{{ Auth::user()->email_verified_at ? 'Sim' : 'Nao' }}</strong>
                </div>
                <div class="summary-item">
                    <span>Status</span>
                    <strong>Ativo</strong>
                </div>
            </div>
            <p class="summary-hint">Mantenha seus dados atualizados para uma experiencia melhor.</p>
        </div>
    </div>

    <nav class="settings-nav">
        <a href="#perfil">Perfil</a>
        <a href="#seguranca">Seguranca</a>
        <a href="#preferencias">Preferencias</a>
        <a href="#notificacoes">Notificacoes</a>
    </nav>

    <div class="settings-grid">
        <section id="perfil" class="settings-card">
            <div class="card-header">
                <div class="card-title">
                    <span class="card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M3 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6"/>
                        </svg>
                    </span>
                    <div>
                        <h3>Perfil</h3>
                        <p>Atualize nome, email e informacoes pessoais.</p>
                    </div>
                </div>
                <span class="card-chip">Basico</span>
            </div>

            <form action="{{ route('profile-edit') }}" method="POST" enctype="multipart/form-data" class="settings-form">
                @csrf
                <input type="hidden" name="section" value="profile">
                <div class="form-grid">
                    <div class="form-field">
                        <label for="name">Nome</label>
                        <input id="name" name="name" type="text" value="{{ old('name', Auth::user()->name) }}" required>
                        @error('name')
                            <span class="error-message">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="form-field">
                        <label for="email">Email</label>
                        <input id="email" name="email" type="email" value="{{ old('email', Auth::user()->email) }}" required>
                        @error('email')
                            <span class="error-message">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="form-field">
                        <label for="phone">Telefone</label>
                        <input id="phone" name="phone" type="tel" placeholder="(00) 00000-0000" value="{{ old('phone', Auth::user()->phone) }}">
                        @error('phone')
                            <span class="error-message">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="form-field">
                        <label for="institution">Instituição</label>
                        <input id="institution" name="institution" type="text" placeholder="Sua instituição" value="{{ old('institution', Auth::user()->institution) }}">
                        @error('institution')
                            <span class="error-message">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="form-field span-2">
                        <label for="bio">Bio</label>
                        <textarea id="bio" name="bio" rows="3" placeholder="Conte um pouco sobre voce">{{ old('bio', Auth::user()->bio) }}</textarea>
                        @error('bio')
                            <span class="error-message">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="form-field span-2">
                        <label for="avatar">Foto de perfil</label>
                        <input id="avatar" name="avatar" type="file" accept="image/*">
                        @error('avatar')
                            <span class="error-message">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Salvar alteracoes</button>
                    <button type="reset" class="btn-secondary">Cancelar</button>
                </div>
            </form>
        </section>

        <section id="seguranca" class="settings-card">
            <div class="card-header">
                <div class="card-title">
                    <span class="card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M8 0c-.69 0-1.3.105-1.823.3A4.992 4.992 0 0 0 3 5v3.086a1.5 1.5 0 0 0 .44 1.06l4.136 4.137a1.5 1.5 0 0 0 2.121 0l4.136-4.137a1.5 1.5 0 0 0 .44-1.06V5a4.992 4.992 0 0 0-3.177-4.7A5.935 5.935 0 0 0 8 0z"/>
                            <path d="M5.5 7a2.5 2.5 0 1 1 5 0 2.5 2.5 0 0 1-5 0z"/>
                        </svg>
                    </span>
                    <div>
                        <h3>Seguranca</h3>
                        <p>Atualize sua senha e revise sessoes ativas.</p>
                    </div>
                </div>
                <span class="card-chip warning">Protecao</span>
            </div>

            <form action="{{ route('settings-edit') }}" method="POST" class="settings-form">
                @csrf
                <input type="hidden" name="section" value="security">
                <div class="form-grid">
                    <div class="form-field">
                        <label for="current_password">Senha atual</label>
                        <input id="current_password" name="current_password" type="password" autocomplete="current-password">
                        @error('current_password')
                            <span class="error-message">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="form-field">
                        <label for="new_password">Nova senha</label>
                        <input id="new_password" name="new_password" type="password" autocomplete="new-password">
                        @error('new_password')
                            <span class="error-message">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="form-field span-2">
                        <label for="new_password_confirmation">Confirmar nova senha</label>
                        <input id="new_password_confirmation" name="new_password_confirmation" type="password" autocomplete="new-password">
                        @error('new_password_confirmation')
                            <span class="error-message">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="form-field span-2">
                        <label class="switch-control">
                            <input type="checkbox" name="logout_other_sessions" value="1">
                            <span class="switch-ui"></span>
                            <span class="switch-text">Encerrar sessoes em outros dispositivos</span>
                        </label>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Atualizar senha</button>
                    <button type="button" class="btn-secondary">Ativar 2FA</button>
                </div>
            </form>
        </section>

        <section id="preferencias" class="settings-card">
            <div class="card-header">
                <div class="card-title">
                    <span class="card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M8 4.754a3.246 3.246 0 1 0 0 6.492 3.246 3.246 0 0 0 0-6.492z"/>
                            <path d="M9.796 1.343c-.527-1.79-3.065-1.79-3.592 0l-.094.319a1.9 1.9 0 0 1-2.757 1.11l-.292-.16c-1.63-.886-3.437.92-2.55 2.55l.159.292a1.9 1.9 0 0 1-1.11 2.757l-.319.094c-1.79.527-1.79 3.065 0 3.592l.319.094a1.9 1.9 0 0 1 1.11 2.757l-.16.292c-.886 1.63.92 3.437 2.55 2.55l.292-.159a1.9 1.9 0 0 1 2.757 1.11l.094.319c.527 1.79 3.065 1.79 3.592 0l.094-.319a1.9 1.9 0 0 1 2.757-1.11l.292.16c1.63.886 3.437-.92 2.55-2.55l-.159-.292a1.9 1.9 0 0 1 1.11-2.757l.319-.094c1.79-.527 1.79-3.065 0-3.592l-.319-.094a1.9 1.9 0 0 1-1.11-2.757l.16-.292c.886-1.63-.92-3.437-2.55-2.55l-.292.159a1.9 1.9 0 0 1-2.757-1.11zM8 12.75A4.75 4.75 0 1 1 8 3.25a4.75 4.75 0 0 1 0 9.5z"/>
                        </svg>
                    </span>
                    <div>
                        <h3>Preferencias</h3>
                        <p>Escolha tema, idioma e comportamento do sistema.</p>
                    </div>
                </div>
                <span class="card-chip">Personalizacao</span>
            </div>

            <form action="{{ route('settings-edit') }}" method="POST" class="settings-form">
                @csrf
                <input type="hidden" name="section" value="preferences">
                <div class="form-grid">
                    <div class="form-field">
                        <label for="theme">Tema</label>
                        <select id="theme" name="settings[theme]">
                            <option value="light" {{ old('settings.theme', $userPreferences['theme'] ?? 'light') == 'light' ? 'selected' : '' }}>Claro</option>
                            <option value="dark" {{ old('settings.theme', $userPreferences['theme'] ?? 'light') == 'dark' ? 'selected' : '' }}>Escuro</option>
                            <option value="auto" {{ old('settings.theme', $userPreferences['theme'] ?? 'light') == 'auto' ? 'selected' : '' }}>Automatico</option>
                        </select>
                        @error('settings.theme')
                            <span class="error-message">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="form-field">
                        <label for="language">Idioma</label>
                        <select id="language" name="settings[language]">
                            <option value="pt-BR" {{ old('settings.language', $userPreferences['language'] ?? 'pt-BR') == 'pt-BR' ? 'selected' : '' }}>Portugues (Brasil)</option>
                            <option value="en" {{ old('settings.language', $userPreferences['language'] ?? 'pt-BR') == 'en' ? 'selected' : '' }}>English</option>
                            <option value="es" {{ old('settings.language', $userPreferences['language'] ?? 'pt-BR') == 'es' ? 'selected' : '' }}>Espanol</option>
                        </select>
                        @error('settings.language')
                            <span class="error-message">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="form-field">
                        <label for="timezone">Fuso horario</label>
                        <select id="timezone" name="settings[timezone]">
                            <option value="America/Sao_Paulo" {{ $timezoneValue === 'America/Sao_Paulo' ? 'selected' : '' }}>America/Sao_Paulo</option>
                            <option value="UTC" {{ $timezoneValue === 'UTC' ? 'selected' : '' }}>UTC</option>
                        </select>
                        @error('settings.timezone')
                            <span class="error-message">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="form-field">
                        <label class="switch-control">
                            <input type="checkbox" name="settings[compact]" value="1" @checked(old('settings.compact', $userPreferences['compact'] ?? true))>
                            <span class="switch-ui"></span>
                            <span class="switch-text">Modo compacto</span>
                        </label>
                        @error('settings.compact')
                            <span class="error-message">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Salvar preferencias</button>
                    <button type="reset" class="btn-secondary">Restaurar</button>
                </div>
            </form>
        </section>

        <section id="notificacoes" class="settings-card">
            <div class="card-header">
                <div class="card-title">
                    <span class="card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M8 16a2 2 0 0 0 2-2H6a2 2 0 0 0 2 2M8 1.918l-.797.161A4 4 0 0 0 4 6c0 .628-.134 2.197-.459 3.742-.16.767-.376 1.566-.663 2.258h10.244c-.287-.692-.502-1.49-.663-2.258C12.134 8.197 12 6.628 12 6a4 4 0 0 0-3.203-3.92z"/>
                        </svg>
                    </span>
                    <div>
                        <h3>Notificacoes</h3>
                        <p>Escolha como deseja receber alertas do sistema.</p>
                    </div>
                </div>
                <span class="card-chip">Alertas</span>
            </div>

            <form action="{{ route('settings-edit') }}" method="POST" class="settings-form">
                @csrf
                <input type="hidden" name="section" value="notifications">
                <div class="switch-list">
                    <label class="switch-control">
                        <input type="checkbox" name="notifications[email]" value="1" @checked(old('notifications.email', $userNotifications['email'] ?? true))>
                        <span class="switch-ui"></span>
                        <span class="switch-text">Email de atualizacoes e convites</span>
                    </label>
                    <label class="switch-control">
                        <input type="checkbox" name="notifications[calendar]" value="1" @checked(old('notifications.calendar', $userNotifications['calendar'] ?? true))>
                        <span class="switch-ui"></span>
                        <span class="switch-text">Lembretes de eventos do calendario</span>
                    </label>
                    <label class="switch-control">
                        <input type="checkbox" name="notifications[projects]" value="1" @checked(old('notifications.projects', $userNotifications['projects'] ?? false))>
                        <span class="switch-ui"></span>
                        <span class="switch-text">Alteracoes em projetos e grupos</span>
                    </label>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Salvar notificacoes</button>
                </div>
            </form>
        </section>
    </div>
</div>
@endsection
