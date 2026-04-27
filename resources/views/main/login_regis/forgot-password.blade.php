@extends('layouts.master')

@section('title', 'Recuperar Senha')

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

                <h1>Recupere seu acesso</h1>
                <p>Informe seu e-mail para receber o link de redefinicao de senha.</p>

                <div class="features-list">
                    <div class="feature-item">
                        <div class="check-icon"></div>
                        <span>Link temporario e seguro</span>
                    </div>
                    <div class="feature-item">
                        <div class="check-icon"></div>
                        <span>Reset rapido da senha</span>
                    </div>
                    <div class="feature-item">
                        <div class="check-icon"></div>
                        <span>Acesso restabelecido em poucos minutos</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="auth-form-container">
            <div class="auth-form-wrapper">
                <div class="form-header">
                    <h1>Esqueci Minha Senha</h1>
                    <p>Digite seu e-mail para receber as instrucoes</p>
                </div>

                @if(session('status'))
                    <div class="alert alert-success">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                            <circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="1.5"/>
                            <path d="M6 10L9 13L14 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                <form method="POST" action="{{ route('password.email') }}" class="auth-form">
                    @csrf
                    <div class="form-group">
                        <label for="email">E-mail</label>
                        <input type="email" id="email" name="email" class="form-control @error('email') error @enderror" placeholder="seu@email.com" autocomplete="email" value="{{ old('email') }}" required autofocus>
                        @error('email')
                            <span class="error-message">{{ $message }}</span>
                        @enderror
                    </div>

                    <button type="submit" class="btn-submit">Enviar link de redefinicao</button>

                    <div class="form-footer">
                        <p>Lembrou a senha?
                            <a href="{{ route('login') }}">Entrar</a>
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection
