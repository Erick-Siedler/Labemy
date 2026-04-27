<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de aluno</title>
    <style>
        body { margin: 0; font-family: Arial, sans-serif; background: #f5f5f5; }
        .page { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
        .card { background: #fff; width: 100%; max-width: 520px; border-radius: 12px; padding: 24px; box-shadow: 0 12px 40px rgba(0,0,0,0.12); }
        h1 { margin: 0 0 12px 0; font-size: 22px; color: #333; }
        p { color: #666; margin: 0 0 16px 0; }
        label { display: block; font-size: 13px; color: #444; margin-bottom: 6px; }
        input { width:95%; padding: 10px 12px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 14px; }
        input[readonly] { background: #f7f7f7; }
        button { width: 100%; padding: 12px 14px; border: none; border-radius: 8px; background: #ff8c00; color: #fff; font-weight: 600; cursor: pointer; }
        .alert { padding: 10px 12px; border-radius: 8px; margin-bottom: 14px; }
        .alert-error { background: #ffeaea; color: #a40000; }
        .alert-success { background: #e8f7ec; color: #1f7a3a; }
        ul { padding-left: 18px; margin: 0; }
        .error-message { display: block; color: #a40000; font-size: 12px; margin-top: -8px; margin-bottom: 12px; }
    </style>
</head>
<body>
    <div class="page">
        <div class="card">
            <h1>Cadastro de aluno</h1>

            @if(!empty($invalid))
                <div class="alert alert-error">{{ $message ?? 'Convite inválido.' }}</div>
            @elseif(!empty($success))
                <div class="alert alert-success">{{ $message ?? 'Cadastro concluído.' }}</div>
            @else
                @if ($errors->any())
                    <div class="alert alert-error">
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <p>Complete seus dados para entrar como aluno.</p>

                <form method="POST" action="{{ route('subuser-register-store', ['token' => $token]) }}">
                    @csrf
                    <label for="name">Nome completo</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" required>
                    @error('name')
                        <span class="error-message">{{ $message }}</span>
                    @enderror

                    <label for="email">E-mail</label>
                    <input id="email" name="email" type="email" value="{{ $invite->email }}" readonly>
                    @error('email')
                        <span class="error-message">{{ $message }}</span>
                    @enderror

                    <label for="password">Senha</label>
                    <input id="password" name="password" type="password" value="{{ old('password') }}" required>
                    @error('password')
                        <span class="error-message">{{ $message }}</span>
                    @enderror

                    <label for="password_confirmation">Confirmar senha</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required>
                    @error('password_confirmation')
                        <span class="error-message">{{ $message }}</span>
                    @enderror

                    <button type="submit">Finalizar cadastro</button>
                </form>
            @endif
        </div>
    </div>
</body>
</html>
