@extends('layouts.master')

@section('title', 'Redefinir Senha')

@push('styles')
<link rel="stylesheet" href="{{ asset('main/log_reg.css') }}">
@endpush

@section('content')

<div class="auth-container">
    <a href="{{ route('login') }}" class="btn-back-to-plans">
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
            <path d="M12 16L6 10L12 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        Voltar para Login
    </a>

    <div class="auth-wrapper">
        <div class="auth-info">
            <div class="info-content">
                <div class="logo">
                    <img src="{{ asset('imgs/logo-orange.png') }}" alt="Logo Academic Projects Tracking">
                </div>

                <h1>Defina uma nova senha</h1>
                <p>Escolha uma senha forte para manter sua conta protegida.</p>

                <div class="features-list">
                    <div class="feature-item">
                        <div class="check-icon"></div>
                        <span>Senha minima de 8 caracteres</span>
                    </div>
                    <div class="feature-item">
                        <div class="check-icon"></div>
                        <span>Token unico de redefinicao</span>
                    </div>
                    <div class="feature-item">
                        <div class="check-icon"></div>
                        <span>Confirmacao imediata do reset</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="auth-form-container">
            <div class="auth-form-wrapper">
                <div class="form-header">
                    <h1>Redefinir Senha</h1>
                    <p>Preencha os dados para atualizar sua senha</p>
                </div>

                <form method="POST" action="{{ route('password.update') }}" class="auth-form">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">

                    <div class="form-group">
                        <label for="email">E-mail</label>
                        <input type="email" id="email" name="email" class="form-control @error('email') error @enderror" placeholder="seu@email.com" autocomplete="email" value="{{ old('email', $email) }}" required autofocus>
                        @error('email')
                            <span class="error-message">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="password">Nova Senha</label>
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
                        <label for="password_confirmation">Confirmar Nova Senha</label>
                        <div class="password-wrapper">
                            <input type="password" id="password_confirmation" name="password_confirmation" class="form-control @error('password_confirmation') error @enderror" autocomplete="new-password" placeholder="Repita a senha" required>
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

                    <button type="submit" class="btn-submit">Atualizar senha</button>

                    <div class="form-footer">
                        <p>Voltar para
                            <a href="{{ route('login') }}">login</a>
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
