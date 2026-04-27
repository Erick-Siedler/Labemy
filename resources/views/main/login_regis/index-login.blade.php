@extends('layouts.master')

@section('title', 'Entrar')

@push('styles')
<link rel="stylesheet" href="{{ asset('main/log_reg.css') }}">
@endpush

@section('content')

<div class="auth-container">
    <a href="{{ route('plans') }}" class="btn-back-to-plans">
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
            <path d="M12 16L6 10L12 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        Voltar para Planos
    </a>

    <div class="auth-wrapper">
        <div class="auth-info">
            <div class="info-content">
                <div class="logo">
                    <img src="{{ asset('imgs/logo-orange.png') }}" alt="Logo Academic Projects Tracking">
                </div>

                <h1>Bem-vindo de volta!</h1>
                <p>Acesse sua conta para gerenciar projetos e laboratorios academicos.</p>

                <div class="features-list">
                    <div class="feature-item">
                        <div class="check-icon"></div>
                        <span>Gerencie seus projetos</span>
                    </div>
                    <div class="feature-item">
                        <div class="check-icon"></div>
                        <span>Acompanhe versoes</span>
                    </div>
                    <div class="feature-item">
                        <div class="check-icon"></div>
                        <span>Colabore com sua equipe</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="auth-form-container">
            <div class="auth-form-wrapper">
                <div class="form-header">
                    <h1>Entrar</h1>
                    <p>Entre com suas credenciais</p>
                </div>

                @if(session('error'))
                    <div class="alert alert-error">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                            <circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="1.5"/>
                            <path d="M10 6V10M10 14H10.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        <span>{{ session('error') }}</span>
                    </div>
                @endif

                @if(session('success'))
                    <div class="alert alert-success">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                            <circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="1.5"/>
                            <path d="M6 10L9 13L14 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        <span>{{ session('success') }}</span>
                    </div>
                @endif

                @if(session('status'))
                    <div class="alert alert-success">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                            <circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="1.5"/>
                            <path d="M6 10L9 13L14 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                <form method="POST" action="{{ route('login-auth') }}" class="auth-form">
                    @csrf
                    @if(!empty($pendingInviteToken))
                        <input type="hidden" name="invite_token" value="{{ $pendingInviteToken }}">
                    @endif

                    <div class="form-group">
                        <label for="email">E-mail</label>
                        <input type="email" id="email" name="email" class="form-control @error('email') error @enderror" placeholder="seu@email.com" autocomplete="email" value="{{ old('email') }}" required autofocus>
                        @error('email')
                            <span class="error-message">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="password">Senha</label>
                        <div class="password-wrapper">
                            <input type="password" id="password" name="password" class="form-control @error('password') error @enderror" placeholder="Digite sua senha" autocomplete="current-password" required>
                            <button type="button" class="toggle-password" onclick="togglePassword()">
                                <svg id="eye-icon" width="20" height="20" viewBox="0 0 20 20" fill="none">
                                    <path d="M10 4C5 4 2 10 2 10s3 6 8 6 8-6 8-6-3-6-8-6z" stroke="currentColor" stroke-width="1.5"/>
                                    <circle cx="10" cy="10" r="2" stroke="currentColor" stroke-width="1.5"/>
                                </svg>
                            </button>
                        </div>
                        @error('password')
                            <span class="error-message">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-options">
                        <label class="checkbox-label">
                            <input type="checkbox" name="remember" @checked(old('remember'))>
                            <span>Lembrar de mim</span>
                        </label>
                        <a href="{{ route('password.request') }}" class="forgot-link">Esqueceu a senha?</a>
                    </div>

                    <button type="submit" class="btn-submit">Entrar</button>

                    <div class="form-footer">
                        <p>Nao tem uma conta?
                            <a href="{{ route('register', !empty($pendingInviteToken) ? ['invite' => $pendingInviteToken] : []) }}">Cadastre-se</a>
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword() {
    const passwordInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eye-icon');

    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        eyeIcon.innerHTML = '<path d="M2 2l16 16M10 4C5 4 2 10 2 10s1.5 3 4 4.5M14 14.5c2-1.5 4-4.5 4-4.5s-3-6-8-6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>';
    } else {
        passwordInput.type = 'password';
        eyeIcon.innerHTML = '<path d="M10 4C5 4 2 10 2 10s3 6 8 6 8-6 8-6-3-6-8-6z" stroke="currentColor" stroke-width="1.5"/><circle cx="10" cy="10" r="2" stroke="currentColor" stroke-width="1.5"/>';
    }
}
</script>

@endsection
