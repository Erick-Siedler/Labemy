@extends('layouts.master')

@section('title', 'Pagamento - ' . ucfirst($plan))

@push('styles')
<link rel="stylesheet" href="{{ asset('main/pagamento.css') }}">
@endpush

@section('content')

<div class="payment-container">
    <div class="payment-wrapper">
        <div class="plan-summary">
            <div class="summary-header">
                <h2>Resumo do Pedido</h2>
            </div>
            
            <div class="summary-content">
                <div class="plan-badge">
                    @if($plan === 'basic')
                        <span class="badge-text">Plano Básico</span>
                    @elseif($plan === 'pro')
                        <span class="badge-text">Plano Pro</span>
                    @else
                        <span class="badge-text">Plano Empresa</span>
                    @endif
                </div>

                <div class="plan-features">
                    <h3>O que está incluído:</h3>
                    @if($plan === 'basic')
                        <div class="feature-item">
                            <div class="check-small"></div>
                            <span>Até 100 projetos</span>
                        </div>
                        <div class="feature-item">
                            <div class="check-small"></div>
                            <span>Versionamento completo</span>
                        </div>
                        <div class="feature-item">
                            <div class="check-small"></div>
                            <span>Eventos acadêmicos</span>
                        </div>
                        <div class="feature-item">
                            <div class="check-small"></div>
                            <span>Painel do professor</span>
                        </div>
                    @elseif($plan === 'pro')
                        <div class="feature-item">
                            <div class="check-small"></div>
                            <span>Todas as funções básicas</span>
                        </div>
                        <div class="feature-item">
                            <div class="check-small"></div>
                            <span>Projetos ilimitados</span>
                        </div>
                        <div class="feature-item">
                            <div class="check-small"></div>
                            <span>Notificações por e-mail/sistema</span>
                        </div>
                        <div class="feature-item">
                            <div class="check-small"></div>
                            <span>Comparação entre versões</span>
                        </div>
                    @else
                        <div class="feature-item">
                            <div class="check-small"></div>
                            <span>Todas as funções pro</span>
                        </div>
                        <div class="feature-item">
                            <div class="check-small"></div>
                            <span>Logs exportáveis (PDF/CSV/EXCEL)</span>
                        </div>
                        <div class="feature-item">
                            <div class="check-small"></div>
                            <span>Backup automático</span>
                        </div>
                        <div class="feature-item">
                            <div class="check-small"></div>
                            <span>Armazenamento dedicado</span>
                        </div>
                    @endif
                </div>

                <div class="price-summary">
                    <div class="price-row">
                        <span>Subtotal</span>
                        <span>R$ {{ number_format($amount, 2, ',', '.') }}</span>
                    </div>
                    <div class="price-row total">
                        <span>Total</span>
                        <span>R$ {{ number_format($amount, 2, ',', '.') }}/mês</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="payment-form-container">
            <div class="form-header">
                <h1>Confirmar Assinatura</h1>
                <p>Informe seu e-mail para ativar o plano</p>
            </div>

            <form action="{{ route('payment.pay', $plan) }}" method="POST" class="payment-form">
                @csrf

                <div class="form-group">
                    <div class="email-cont">
                        <label for="email">E-mail</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        class="form-control @error('email') error @enderror" 
                        placeholder="seu@email.com"
                        value="{{ old('email', Auth::user()->email) }}"
                        required
                    >
                    @error('email')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                    </div>
                    
                    <div class="cpf-cont">
                        <label for="cpf">CPF/CNPJ</label>
                    <input 
                        type="text"
                        id="cpf"
                        name="cpf"
                        class="form-control @error('cpf') error @enderror"
                        placeholder="CPF ou CNPJ"
                        value="{{ old('cpf') }}"
                        required
                    >
                    @error('cpf')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                    </div>
                </div>

                <div class="info-box">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="1.5"/>
                        <path d="M10 6V10M10 14H10.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <p>Esta é uma finalização de compra ilustrativa. Após confirmar, você será direcionado para criar sua instituição.</p>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-submit">
                        <span>Confirmar e Continuar</span>
                        <span class="btn-amount">R$ {{ number_format($amount, 2, ',', '.') }}</span>
                    </button>
                    
                    <a href="{{ url('/') }}" class="btn-back">Voltar para planos</a>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
