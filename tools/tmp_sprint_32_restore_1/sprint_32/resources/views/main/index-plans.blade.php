@extends('layouts.master')

@section('title', 'Planos')

@push('styles')
<link rel="stylesheet" href="{{ asset('main/planos.css') }}">
@endpush

@section('content')

@auth
<div class="login-cont">
    <form action="{{ route('logout', 'light') }}" method="POST">
    @csrf
    <button type="submit">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-box-arrow-left" viewBox="0 0 16 16">
        <path fill-rule="evenodd" d="M6 12.5a.5.5 0 0 0 .5.5h8a.5.5 0 0 0 .5-.5v-9a.5.5 0 0 0-.5-.5h-8a.5.5 0 0 0-.5.5v2a.5.5 0 0 1-1 0v-2A1.5 1.5 0 0 1 6.5 2h8A1.5 1.5 0 0 1 16 3.5v9a1.5 1.5 0 0 1-1.5 1.5h-8A1.5 1.5 0 0 1 5 12.5v-2a.5.5 0 0 1 1 0z"/>
        <path fill-rule="evenodd" d="M.146 8.354a.5.5 0 0 1 0-.708l3-3a.5.5 0 1 1 .708.708L1.707 7.5H10.5a.5.5 0 0 1 0 1H1.707l2.147 2.146a.5.5 0 0 1-.708.708z"/>
        </svg>
        <span>Sair</span>
    </button>
    </form>
</div>
@endauth

@guest
<div class="login-cont">
    <a href="{{ route('login') }}">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M13.8 12H3"/>
        </svg>
        Entrar
    </a>
    <a href="{{ route('register') }}">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
            <circle cx="8.5" cy="7" r="4"/>
            <line x1="20" y1="8" x2="20" y2="14"/>
            <line x1="23" y1="11" x2="17" y2="11"/>
        </svg>
        Cadastre-se
    </a>
</div>
@endguest

@if(session('error'))
<div class="plan-alert plan-alert--error">
    {{ session('error') }}
</div>
@endif

@if(session('success'))
<div class="plan-alert plan-alert--success">
    {{ session('success') }}
</div>
@endif

@auth
@php
    $isTrialActive = (bool) ($trialActive ?? false);
    $isTrialUsed = (bool) ($trialUsed ?? (Auth::user()->trial_used ?? false));
    $hasPaidAccessFlag = (bool) ($hasPaidAccess ?? false);
    $displayPlan = strtoupper((string) ($currentPlan ?? Auth::user()->plan ?? 'free'));
@endphp
<div class="plan-banner">
    <strong>Plano atual: {{ $displayPlan }}</strong>
    @if($isTrialActive)
    <span>Trial ativo até {{ $trialEndsAt ?? "data não informada" }}.</span>
    <form action="{{ route('trial.cancel') }}" method="POST" style="margin-top: 8px;">
        @csrf
        <button type="submit" class="bt">Cancelar trial</button>
    </form>
    @elseif($isTrialUsed && !$hasPaidAccessFlag)
    <span>Você já utilizou o trial. Agora, apenas planos pagos estão disponíveis.</span>
    @else
    <span>Você pode trocar de plano a qualquer momento escolhendo outra opção abaixo.</span>
    @endif
</div>
@endauth

<div class="plan-banner">
    <strong>Trial de 7 dias</strong>
    <span>Disponível uma única vez para cada conta, exceto o Enterprise (em desenvolvimento).</span>
</div>

<div class="plan-container">
    @for ($i = 1;$i < 4; $i++)
        <div class="plan-card">
        <div class="card-top">
            @if ($i === 1)
                <h1>Plano Solo</h1>
            @elseif ($i === 2)
                <h1>Plano Pro</h1>
            @else
                <span class="plan-badge plan-badge--dev">Em desenvolvimento</span>
                <h1>Plano Empresa</h1>
            @endif  
        </div>
        <div class="card-bottom">
            @if ($i === 1)
            <div class="price">
                    <span class="price-symbol">R$</span>
                    <span class="price-number">99,99</span>
                    <span class="price-month">/mês</span>
            </div>
            <div class="features">
                <span class="check-feat"><div class="check"></div>Trial de 7 dias</span>
                <span class="check-feat"><div class="check"></div>Até 100 projetos</span>
                <span class="check-feat"><div class="check"></div>Versionamento completo</span>
                <span class="check-feat"><div class="check"></div>Eventos acadêmicos</span>
                <span class="check-feat"><div class="check"></div>Painel de usuário</span>
            </div>
            <a class="bt" href="{{route('payment.checkout', 'solo')}}">Comece Agora</a>
            <div class="link-struc">
                <div class="line"></div>
                <a>Saiba Mais</a>
                <div class="line"></div>
            </div>
            
            <div class="card-features" id="feat-{{$i}}">
                 <div class="card-features-header">
                    <span class="card-features-title">Recursos Completos</span>
                    <button class="close-features" aria-label="Fechar">&times;</button>
                </div>
                <div class="features">
                    <span class="check-feat"><div class="check"></div>Até 1 usuário</span>
                    <span class="check-feat"><div class="check"></div>Gráficos informativos</span>
                    <span class="check-feat"><div class="check"></div>Planilhas prontas de organização</span>
                    <span class="check-feat"><div class="check"></div>Até 100 projetos</span>
                    <span class="check-feat"><div class="check"></div>Versionamento completo</span>
                    <span class="check-feat"><div class="check"></div>Envio de ZIP (até 200 MB por arquivo)</span>
                    <span class="check-feat"><div class="check"></div>Sistema de alertas</span>
                    <span class="check-feat"><div class="check"></div>Calendário de eventos</span>
                    <span class="check-feat"><div class="check"></div>Comentários e feedback por versão</span>
                    <span class="check-feat"><div class="check"></div>Até 4 visualizadores</span>
                    <span class="check-feat"><div class="check"></div>Exportação de projetos (ZIP)</span>
                    <span class="check-feat"><div class="check"></div>Painel simples de usuário</span>
                </div>
            </div>

            @elseif ($i === 2)
            <div class="price">
                <span class="price-symbol">R$</span>
                <span class="price-number">599,99</span>
                <span class="price-month">/mês</span>
            </div>
            <div class="features">
                <span class="check-feat"><div class="check"></div>Trial de 7 dias</span>
                <span class="check-feat"><div class="check"></div>Todas as funções solo</span>
                <span class="check-feat"><div class="check"></div>Projetos ilimitados</span>
                <span class="check-feat"><div class="check"></div>Notificações por e-mail/sistema</span>
                <span class="check-feat"><div class="check"></div>Comparação entre versões</span>
            </div>
            <a class="bt" href="{{route('payment.checkout', 'pro')}}">Comece Agora</a>
            <div class="link-struc">
                <div class="line"></div>
                <a>Saiba Mais</a>
                <div class="line"></div>
            </div>

            <div class="card-features" id="feat-{{$i}}">
                 <div class="card-features-header">
                    <span class="card-features-title">Recursos Completos</span>
                    <button class="close-features" aria-label="Fechar">&times;</button>
                </div>
                <div class="features">
                    <span class="check-feat"><div class="check"></div>Todas as funções solo</span>
                    <span class="check-feat"><div class="check"></div>Até 1 instituição</span>
                    <span class="check-feat"><div class="check"></div>Laboratórios ilimitados</span>
                    <span class="check-feat"><div class="check"></div>Grupos ilimitados</span>
                    <span class="check-feat"><div class="check"></div>Projetos ilimitados</span>
                    <span class="check-feat"><div class="check"></div>Envio de ZIP (máx. 1 GB)</span>
                    <span class="check-feat"><div class="check"></div>Notificações por e-mail e sistema</span>
                    <span class="check-feat"><div class="check"></div>Comparação entre versões (diff visual)</span>
                    <span class="check-feat"><div class="check"></div>Permissões avançadas (professor, assistente)</span>
                    <span class="check-feat"><div class="check"></div>Painel avançado do professor</span>
                    <span class="check-feat"><div class="check"></div>Relatórios detalhados</span>
                    <span class="check-feat"><div class="check"></div>Suporte prioritário</span>
                </div>
            </div>

            @else
            <div class="price">
                <span class="price-symbol">R$</span>
                <span class="price-number">999,99</span>
                <span class="price-month">/mês</span>
            </div>
            <div class="features">
                <span class="check-feat"><div class="check"></div>Todas as funções pro</span>
                <span class="check-feat"><div class="check"></div>Suporte técnico 24/7</span>
                <span class="check-feat"><div class="check"></div>Backup automático</span>
                <span class="check-feat"><div class="check"></div>Armazenamento dedicado</span>
            </div>
            <a class="bt" href="#" data-dev aria-disabled="true">Comece Agora</a>
            <div class="link-struc">
                <div class="line"></div>
                <a>Saiba Mais</a>
                <div class="line"></div>
            </div>

            <div class="card-features" id="feat-{{$i}}">
                 <div class="card-features-header">
                    <span class="card-features-title">Recursos Completos</span>
                    <button class="close-features" aria-label="Fechar">&times;</button>
                </div>
                <div class="features">
                    <span class="check-feat"><div class="check"></div>Multi-instituição avançada</span>
                    <span class="check-feat"><div class="check"></div>Múltiplos campi / unidades</span>
                    <span class="check-feat"><div class="check"></div>Envio de ZIP (máx. 2 GB)</span>
                    <span class="check-feat"><div class="check"></div>Logs de ações exportáveis (PDF/CSV/Excel)</span>
                    <span class="check-feat"><div class="check"></div>Backup automático diário</span>
                    <span class="check-feat"><div class="check"></div>Armazenamento dedicado (500 GB - 2 TB)</span>
                    <span class="check-feat"><div class="check"></div>API de integração</span>
                    <span class="check-feat"><div class="check"></div>SSO (login único)</span>
                    <span class="check-feat"><div class="check"></div>Marca branca (personalização da marca)</span>
                    <span class="check-feat"><div class="check"></div>Suporte técnico 24/7</span>
                    <span class="check-feat"><div class="check"></div>Gerente de conta dedicado</span>
                    <span class="check-feat"><div class="check"></div>SLA garantido</span>
                </div>
            </div>
            @endif
        </div>
        </div>
    @endfor
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const saibaMaisLinks = document.querySelectorAll('.link-struc a');
        const closeButtons = document.querySelectorAll('.close-features');
        const devButtons = document.querySelectorAll('[data-dev]');
        
        saibaMaisLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                const card = this.closest('.plan-card');
                const cardFeatures = card.querySelector('.card-features');
                
                cardFeatures.classList.add('active');
            });
        });
        
        closeButtons.forEach(button => {
            button.addEventListener('click', function() {
                const cardFeatures = this.closest('.card-features');
                cardFeatures.classList.remove('active');
            });
        });
        
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('card-features')) {
                e.target.classList.remove('active');
            }
        });

        devButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                alert('Em desenvolvimento');
            });
        });
    });
</script>

@endsection

