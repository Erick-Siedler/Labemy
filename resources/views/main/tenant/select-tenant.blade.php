@extends('layouts.master')

@section('title', 'Selecionar Tenant')

@section('content')
<style>
    .tenant-select-page {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px;
        background: rgba(15, 23, 42, 0.45);
    }

    .tenant-select-card {
        width: 100%;
        max-width: 760px;
        border-radius: 16px;
        background: #ffffff;
        box-shadow: 0 30px 80px rgba(2, 6, 23, 0.35);
        padding: 24px;
    }

    .tenant-select-header h1 {
        font-size: 28px;
        color: #0f172a;
        margin-bottom: 8px;
    }

    .tenant-select-header p {
        color: #475569;
        margin-bottom: 18px;
    }

    .tenant-list {
        display: grid;
        gap: 12px;
    }

    .tenant-item {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 14px 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .tenant-item-main h2 {
        font-size: 17px;
        color: #0f172a;
        margin: 0 0 4px 0;
    }

    .tenant-item-main p {
        font-size: 13px;
        color: #64748b;
        margin: 0;
    }

    .tenant-item-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 8px;
    }

    .tenant-badge {
        border-radius: 999px;
        background: #f1f5f9;
        color: #334155;
        font-size: 12px;
        padding: 4px 10px;
        text-transform: lowercase;
    }

    .tenant-badge.owner {
        background: #ffedd5;
        color: #9a3412;
    }

    .tenant-item-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-shrink: 0;
    }

    .tenant-item-actions form {
        margin: 0;
    }

    .tenant-select-btn {
        background: #f97316;
        color: #ffffff;
        border: none;
        border-radius: 10px;
        padding: 10px 14px;
        font-weight: 600;
        cursor: pointer;
    }

    .tenant-select-btn[disabled] {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .tenant-leave-btn {
        background: #fff;
        color: #b42318;
        border: 1px solid #fecaca;
        border-radius: 10px;
        padding: 10px 14px;
        font-weight: 600;
        cursor: pointer;
    }

    .tenant-errors {
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #991b1b;
        border-radius: 10px;
        padding: 10px 12px;
        margin-bottom: 14px;
    }

    .tenant-errors ul {
        margin: 0;
        padding-left: 18px;
    }

    .tenant-success {
        background: #ecfdf3;
        border: 1px solid #abefc6;
        color: #067647;
        border-radius: 10px;
        padding: 10px 12px;
        margin-bottom: 14px;
    }
</style>

<div class="tenant-select-page">
    <div class="tenant-select-card">
        <div class="tenant-select-header">
            <h1>Escolha o tenant</h1>
            <p>Esta conta possui acesso a multiplos tenants. Selecione qual voce deseja acessar agora.</p>
        </div>

        @if($errors->any())
            <div class="tenant-errors">
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if(session('success'))
            <div class="tenant-success">{{ session('success') }}</div>
        @endif

        <div class="tenant-list">
            @foreach($tenants as $tenant)
                @php
                    $tenantRelations = $relationsByTenant->get($tenant->id, collect());
                    $isOwner = $ownedTenantIds->has($tenant->id);
                    $isActive = (int) $activeTenantId === (int) $tenant->id;
                @endphp

                <div class="tenant-item">
                    <div class="tenant-item-main">
                        <h2>{{ $tenant->name }}</h2>
                        <p>{{ $tenant->slug }} - plano {{ $tenant->plan }}</p>

                        <div class="tenant-item-badges">
                            @if($isOwner)
                                <span class="tenant-badge owner">owner</span>
                            @endif

                            @foreach($tenantRelations as $relation)
                                <span class="tenant-badge">{{ $relation->role }}</span>
                            @endforeach
                        </div>
                    </div>

                    <div class="tenant-item-actions">
                        <form method="POST" action="{{ route('tenant.select.store') }}">
                            @csrf
                            <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                            <button type="submit" class="tenant-select-btn" @if($isActive) disabled @endif>
                                {{ $isActive ? 'Selecionado' : 'Acessar' }}
                            </button>
                        </form>

                        @if(!$isOwner && $tenantRelations->isNotEmpty())
                            <form method="POST" action="{{ route('tenant.relation.revoke') }}" onsubmit="return confirm('Tem certeza que deseja sair deste tenant?');">
                                @csrf
                                <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                                <button type="submit" class="tenant-leave-btn">Sair</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
