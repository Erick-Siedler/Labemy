@extends('layouts.master')

@section('title', 'Politica de Privacidade')

@push('styles')
<link rel="stylesheet" href="{{ asset('main/legal.css') }}">
@endpush

@section('content')
<main class="legal-page">
    <article class="legal-card">
        <header class="legal-header">
            <h1 class="legal-title">Politica de Privacidade</h1>
            <a href="{{ route('register') }}" class="legal-back">Voltar ao cadastro</a>
        </header>

        <p class="legal-updated">Ultima atualizacao: 10/03/2026</p>

        <section class="legal-section">
            <h2>1. Dados coletados</h2>
            <p>Coletamos dados fornecidos no cadastro (nome, e-mail e informacoes de perfil) e dados tecnicos minimos para autenticacao e seguranca da conta.</p>
        </section>

        <section class="legal-section">
            <h2>2. Finalidade de uso</h2>
            <ul>
                <li>Permitir autenticacao e uso das funcionalidades da plataforma.</li>
                <li>Registrar atividades relacionadas a seguranca e operacao do sistema.</li>
                <li>Melhorar experiencia, suporte e estabilidade do servico.</li>
            </ul>
        </section>

        <section class="legal-section">
            <h2>3. Compartilhamento</h2>
            <p>Os dados nao sao vendidos. O compartilhamento ocorre somente quando necessario para operacao tecnica, obrigacao legal ou solicitacao formal da instituicao responsavel.</p>
        </section>

        <section class="legal-section">
            <h2>4. Retencao e seguranca</h2>
            <p>Adotamos medidas tecnicas e administrativas para proteger dados pessoais. Registros sao mantidos pelo periodo necessario para cumprir finalidades legitimas e obrigacoes legais.</p>
        </section>

        <section class="legal-section">
            <h2>5. Direitos do titular</h2>
            <p>Voce pode solicitar acesso, correcao e atualizacao de dados pessoais conforme a legislacao aplicavel, pelos canais de atendimento da administracao responsavel.</p>
        </section>
    </article>
</main>
@endsection
