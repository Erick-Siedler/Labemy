@extends('layouts.master')

@section('title', 'Registro')

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

                <h1>Bem vindo!</h1>
                <p>Crie sua conta para acessar o sistema e entrar no tenant convidado quando houver convite valido.</p>

                <div class="features-list">
                    <div class="feature-item">
                        <div class="check-icon"></div>
                        <span>Configuracao rapida</span>
                    </div>
                    <div class="feature-item">
                        <div class="check-icon"></div>
                        <span>Interface intuitiva</span>
                    </div>
                    <div class="feature-item">
                        <div class="check-icon"></div>
                        <span>Suporte dedicado</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="auth-form-container">
            <div class="auth-form-wrapper">
                <div class="form-header">
                    <h1>Criar Conta</h1>
                    <p>Preencha seus dados para comecar</p>
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

                @if(!empty($pendingInviteToken))
                    <div class="alert alert-success">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                            <circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="1.5"/>
                            <path d="M6 10L9 13L14 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        <span>Convite valido detectado. Ao concluir o cadastro, voce sera vinculado automaticamente.</span>
                    </div>
                @endif

                <form method="POST" action="{{ route('register-add') }}" class="auth-form">
                    @csrf
                    @if(!empty($pendingInviteToken))
                        <input type="hidden" name="invite_token" value="{{ $pendingInviteToken }}">
                    @endif

                    <div class="form-group">
                        <label for="name">Nome Completo</label>
                        <input type="text" id="name" name="name" class="form-control @error('name') error @enderror" placeholder="Seu nome completo" autocomplete="name" value="{{ old('name') }}" required autofocus>
                        @error('name')
                            <span class="error-message">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="email">E-mail</label>
                        <input type="email" id="email" name="email" class="form-control @error('email') error @enderror" placeholder="seu@email.com" autocomplete="email" value="{{ old('email') }}" required>
                        @error('email')
                            <span class="error-message">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="password">Senha</label>
                            <div class="password-wrapper">
                                <input type="password" id="password" name="password" class="form-control @error('password') error @enderror" autocomplete="new-password" placeholder="Minimo 8 caracteres" required>
                                <button type="button" class="toggle-password" onclick="togglePassword('password')">
                                    <svg id="eye-icon-password" width="20" height="20" viewBox="0 0 20 20" fill="none">
                                        <path d="M10 4C5 4 2 10 2 10s3 6 8 6 8-6 8-6-3-6-8-6z" stroke="currentColor" stroke-width="1.5"/>
                                        <circle cx="10" cy="10" r="2" stroke="currentColor" stroke-width="1.5"/>
                                    </svg>
                                </button>
                            </div>
                            @error('password')
                                <span class="error-message">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="password_confirmation">Confirmar Senha</label>
                            <div class="password-wrapper">
                                <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" autocomplete="new-password" placeholder="Repita a senha" required>
                                <button type="button" class="toggle-password" onclick="togglePassword('password_confirmation')">
                                    <svg id="eye-icon-password_confirmation" width="20" height="20" viewBox="0 0 20 20" fill="none">
                                        <path d="M10 4C5 4 2 10 2 10s3 6 8 6 8-6 8-6-3-6-8-6z" stroke="currentColor" stroke-width="1.5"/>
                                        <circle cx="10" cy="10" r="2" stroke="currentColor" stroke-width="1.5"/>
                                    </svg>
                                </button>
                            </div>
                            @error('password_confirmation')
                                <span class="error-message">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="checkbox-label terms">
                            <input type="checkbox" name="terms_of_use" required @checked(old('terms_of_use'))>
                            <span>Aceito os <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener noreferrer">Termos de Uso</a></span>
                        </label>
                        @error('terms_of_use')
                            <span class="error-message">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label class="checkbox-label terms">
                            <input type="checkbox" name="privacy_policy" required @checked(old('privacy_policy'))>
                            <span>Aceito a <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener noreferrer">Politica de Privacidade</a></span>
                        </label>
                        @error('privacy_policy')
                            <span class="error-message">{{ $message }}</span>
                        @enderror
                    </div>

                    <button type="submit" class="btn-submit">Criar Conta</button>

                    <div class="form-footer">
                        <p>Ja tem uma conta?
                            <a href="{{ route('login', !empty($pendingInviteToken) ? ['invite' => $pendingInviteToken] : []) }}">Entrar</a>
                        </p>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword(fieldId) {
    const passwordInput = document.getElementById(fieldId);
    const eyeIcon = document.getElementById('eye-icon-' + fieldId);

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
