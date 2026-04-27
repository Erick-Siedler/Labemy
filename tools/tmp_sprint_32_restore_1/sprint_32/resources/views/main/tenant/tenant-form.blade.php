@extends('layouts.master')

@section('title', 'Criar instituição - ' . $plan->plan)

@push('styles')
<link rel="stylesheet" href="{{ asset('main/tenant-form.css') }}">
@endpush

@section('content')

<div class="tenant-container">
    <div class="progress-bar">
        <div class="progress-step active" data-step="1">
            <div class="step-number">1</div>
            <div class="step-label">Informações</div>
        </div>
        <div class="progress-line"></div>
        <div class="progress-step" data-step="2">
            <div class="step-number">2</div>
            <div class="step-label">Configurações</div>
        </div>
        <div class="progress-line"></div>
        <div class="progress-step" data-step="3">
            <div class="step-number">3</div>
            <div class="step-label">Confirmação</div>
        </div>
    </div>

    <form method="POST" action="{{ route('tenant-store', $token)}}" class="tenant-form">
        @csrf

        <div class="form-step active" data-step="1">
            <div class="form-title">
                <h1>Informações da Instituição</h1>
                <p>Preencha os dados básicos da sua instituição</p>
            </div>
            
            <div class="form-body">
                <div class="form-group">
                    <label for="name">Nome da instituição</label>
                    <input 
                        type="text" 
                        id="name" 
                        name="name" 
                        class="form-control @error('name') error @enderror" 
                        placeholder="Ex: Universidade Federal de São Paulo"
                        value="{{ old('name') }}"
                        required
                    >
                    @error('name')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="slug">Identificador</label>
                    <input 
                        type="text" 
                        id="slug" 
                        name="slug" 
                        class="form-control @error('slug') error @enderror" 
                        placeholder="Ex: unifesp"
                        value="{{ old('slug') }}"
                        required
                    >
                    <small>Será usado na URL: seu-identificador.plataforma.com</small>
                    @error('slug')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="type">Tipo de Instituição</label>
                    <select 
                        id="type" 
                        name="type" 
                        class="form-control @error('type') error @enderror"
                        required
                    >
                        <option value="" selected disabled>Selecione o tipo</option>
                        <option value="school" {{ old('type') == 'school' ? 'selected' : '' }}>Escola</option>
                        <option value="college" {{ old('type') == 'college' ? 'selected' : '' }}>Faculdade</option>
                        <option value="technical_school" {{ old('type') == 'technical_school' ? 'selected' : '' }}>Escola Técnica</option>
                        <option value="company" {{ old('type') == 'company' ? 'selected' : '' }}>Empresa</option>
                        <option value="other" {{ old('type') == 'other' ? 'selected' : '' }}>Outro</option>
                    </select>
                    @error('type')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="form-actions">
                <button type="button" class="btn-next">Próximo</button>
            </div>
        </div>

        <div class="form-step" data-step="2">
            <div class="form-title">
                <h1>Configurações da instituição</h1>
                <p>Personalize as configurações da sua instituição</p>
            </div>
            
            <div class="form-body">
                <div class="settings-section">
                    <h3>Limites e Capacidades</h3>
                    <div class="form-row">
                        <div class="form-group">
                        @if ($plan->plan === 'pro')
                        <label for="max_labs">Máximo de laboratórios</label>
                            <input 
                                type="number" 
                                id="max_labs" 
                                name="settings[max_labs]" 
                                class="form-control @error('settings.max_labs') error @enderror" 
                                value="{{ old('settings.max_labs', 5) }}"
                                min="1"
                            >
                            @error('settings.max_labs')
                                <span class="error-message">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="max_users">Máximo de usuários</label>
                            <input 
                                type="number" 
                                id="max_users" 
                                name="settings[max_users]" 
                                class="form-control @error('settings.max_users') error @enderror" 
                                value="{{ old('settings.max_users', 1) }}"
                                min="1"
                            >
                            @error('settings.max_users')
                                <span class="error-message">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="max_groups">Máximo de grupos</label>
                            <input 
                                type="number" 
                                id="max_groups" 
                                name="settings[max_groups]" 
                                class="form-control @error('settings.max_groups') error @enderror" 
                                value="{{ old('settings.max_groups', 20) }}"
                                min="1"
                                @if ($plan->plan === 'basic') max="20" @endif
                            >
                            @error('settings.max_groups')
                                <span class="error-message">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="max_projects">Máximo de Projetos</label>
                            <input 
                                type="number" 
                                id="max_projects" 
                                name="settings[max_projects]" 
                                class="form-control @error('settings.max_projects') error @enderror" 
                                value="{{ old('settings.max_projects', 100) }}"
                                min="1"
                            >
                            @error('settings.max_projects')
                                <span class="error-message">{{ $message }}</span>
                            @enderror
                            
                        </div>
                    </div>
                    <div class="form-row alone">
                        

                        <div class="form-group">
                            <label for="max_storage_mb">Armazenamento máximo (MB)</label>
                            <input 
                                type="number" 
                                id="max_storage_mb" 
                                name="settings[max_storage_mb]" 
                                class="form-control @error('settings.max_storage_mb') error @enderror" 
                                value="{{ old('settings.max_storage_mb', 10000) }}"
                                min="10000"
                                max="500000"
                                step="100000"
                            >
                            @error('settings.max_storage_mb')
                                <span class="error-message">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                @else
                        <label for="max_labs">Máximo de laboratórios</label>
                                <input 
                                    type="number" 
                                    id="max_labs" 
                                    name="settings[max_labs]" 
                                    class="form-control @error('settings.max_labs') error @enderror" 
                                    value="{{ old('settings.max_labs', 5) }}"
                                    min="1"
                                >
                                @error('settings.max_labs')
                                    <span class="error-message">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="max_users">Máximo de Usuários</label>
                                <input 
                                    type="number" 
                                    id="max_users" 
                                    name="settings[max_users]" 
                                    class="form-control @error('settings.max_users') error @enderror" 
                                    value="{{ old('settings.max_users', 1) }}"
                                    min="1"
                                >
                                @error('settings.max_users')
                                    <span class="error-message">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="max_groups">Máximo de grupos</label>
                            <input 
                                type="number" 
                                id="max_groups" 
                                name="settings[max_groups]" 
                                class="form-control @error('settings.max_groups') error @enderror" 
                                value="{{ old('settings.max_groups', 20) }}"
                                min="1"
                                @if ($plan->plan === 'basic') max="20" @endif
                            >
                            @error('settings.max_groups')
                                <span class="error-message">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                                <label for="max_projects">Máximo de Projetos</label>
                                <input 
                                    type="number" 
                                    id="max_projects" 
                                    name="settings[max_projects]" 
                                    class="form-control @error('settings.max_projects') error @enderror" 
                                    value="{{ old('settings.max_projects', 100) }}"
                                    min="1"
                                >
                                @error('settings.max_projects')
                                    <span class="error-message">{{ $message }}</span>
                                @enderror
                            </div>
                    </div>
                    <div class="form-row alone">
                        

                            <div class="form-group">

                                <label for="max_storage_mb">Armazenamento máximo (MB)</label>
                                <input 
                                    type="number" 
                                    id="max_storage_mb" 
                                    name="settings[max_storage_mb]" 
                                    class="form-control @error('settings.max_storage_mb') error @enderror" 
                                    value="{{ old('settings.max_storage_mb', 100000) }}"
                                    min="100000"
                                    max="2000000"
                                    step="100000"
                                >
                                @error('settings.max_storage_mb')
                                    <span class="error-message">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>
                @endif

            </div>

            <div class="form-actions">
                <button type="button" class="btn-back">Voltar</button>
                <button type="button" class="btn-next">Próximo</button>
            </div>
        </div>

        <div class="form-step" data-step="3">
            <div class="form-title">
                <h1>Confirmar Informações</h1>
                <p>Revise os dados antes de finalizar</p>
            </div>
            
            <div class="form-body">
                <div class="summary-box">
                    <h3>Resumo</h3>
                    <div class="summary-item">
                        <strong>Nome:</strong>
                        <span id="summary-name">-</span>
                    </div>
                    <div class="summary-item">
                        <strong>Identificador:</strong>
                        <span id="summary-slug">-</span>
                    </div>
                    <div class="summary-item">
                        <strong>Tipo:</strong>
                        <span id="summary-type">-</span>
                    </div>
                    <div class="summary-item">
                        <strong>Máximo de laboratórios:</strong>
                        <span id="summary-max-labs">-</span>
                    </div>
                    <div class="summary-item">
                        <strong>Máximo de grupos:</strong>
                        <span id="summary-max-groups">-</span>
                    </div>
                    <div class="summary-item">
                        <strong>Máximo de usuários:</strong>
                        <span id="summary-max-users">-</span>
                    </div>
                    <div class="summary-item">
                        <strong>Máximo de projetos:</strong>
                        <span id="summary-max-projects">-</span>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="button" class="btn-back">Voltar</button>
                <button type="submit" class="btn-submit">Criar instituição</button>
            </div>
        </div>
    </form>
</div>

@endsection

<script>
    document.addEventListener('DOMContentLoaded', function() {
        let currentStep = 1;
        const totalSteps = 3;

        document.querySelectorAll('.btn-next').forEach(btn => {
            btn.addEventListener('click', function() {
                if (currentStep < totalSteps) {
                    goToStep(currentStep + 1);
                }
            });
        });

        document.querySelectorAll('.btn-back').forEach(btn => {
            btn.addEventListener('click', function() {
                if (currentStep > 1) {
                    goToStep(currentStep - 1);
                }
            });
        });

        function goToStep(step) {

            document.querySelector(`.form-step[data-step="${currentStep}"]`).classList.remove('active');
            document.querySelector(`.progress-step[data-step="${currentStep}"]`).classList.remove('active');

            currentStep = step;
            document.querySelector(`.form-step[data-step="${currentStep}"]`).classList.add('active');
            document.querySelector(`.progress-step[data-step="${currentStep}"]`).classList.add('active');

            if (currentStep === 3) {
                updateSummary();
            }

            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function updateSummary() {
            document.getElementById('summary-name').textContent = document.getElementById('name').value || '-';
            document.getElementById('summary-slug').textContent = document.getElementById('slug').value || '-';
            
            const typeSelect = document.getElementById('type');
            document.getElementById('summary-type').textContent = typeSelect.options[typeSelect.selectedIndex]?.text || '-';
            
            document.getElementById('summary-max-labs').textContent = document.getElementById('max_labs').value || '-';
            document.getElementById('summary-max-groups').textContent = document.getElementById('max_groups').value || '-';
            document.getElementById('summary-max-users').textContent = document.getElementById('max_users').value || '-';
            document.getElementById('summary-max-projects').textContent = document.getElementById('max_projects').value || '-';
        }

        document.getElementById('name').addEventListener('input', function(e) {
            const slug = e.target.value
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
            document.getElementById('slug').value = slug;
        });
    });
</script>







