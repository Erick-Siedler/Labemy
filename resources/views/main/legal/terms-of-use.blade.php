@extends('layouts.master')

@section('title', 'Termos de Uso')

@push('styles')
<link rel="stylesheet" href="{{ asset('main/legal.css') }}">
@endpush

@section('content')
<main class="legal-page">
    <article class="legal-card">
        <header class="legal-header">
            <h1 class="legal-title">Termos de Uso</h1>
            <a href="{{ route('register') }}" class="legal-back">Voltar ao cadastro</a>
        </header>

        <p class="legal-updated">Ultima atualizacao: 10/03/2026</p>

        <section class="legal-section">
            <h2>1. Aceitacao</h2>
            <p>Ao criar uma conta no Academic Projects Tracking, voce concorda com estes termos e com as regras operacionais da plataforma.</p>
        </section>

        <section class="legal-section">
            <h2>2. Uso da plataforma</h2>
            <ul>
                <li>O usuario deve manter dados de acesso seguros e atualizados.</li>
                <li>O uso deve respeitar a legislacao vigente e politicas institucionais aplicaveis.</li>
                <li>E proibido usar o sistema para conteudo ilicito, ofensivo ou que viole direitos de terceiros.</li>
            </ul>
        </section>

        <section class="legal-section">
            <h2>3. Conta e responsabilidade</h2>
            <p>Cada usuario e responsavel pelas acoes realizadas em sua conta. O sistema pode suspender acessos em caso de abuso, fraude ou risco de seguranca.</p>
        </section>

        <section class="legal-section">
            <h2>4. Disponibilidade e alteracoes</h2>
            <p>A plataforma pode passar por manutencoes e evolucoes. Estes termos podem ser atualizados periodicamente, com nova data de revisao publicada nesta pagina.</p>
        </section>

        <section class="legal-section">
            <h2>5. Contato</h2>
            <p>Para duvidas sobre estes termos, utilize os canais de suporte cadastrados pela sua instituicao ou administracao do tenant.</p>
        </section>
    </article>
</main>
@endsection
