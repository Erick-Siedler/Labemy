<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Convite para aluno</title>
</head>
<body style="font-family: Arial, sans-serif; background: #f5f5f5; padding: 24px;">
    <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 8px; padding: 24px;">
        <h2 style="margin: 0 0 12px 0; color: #333;">Convite para entrar na instituição {{ $tenant->name }}</h2>
        <p style="margin: 0 0 12px 0; color: #555;">
            Você foi convidado(a) para entrar como aluno.
        </p>
        <p style="margin: 0 0 12px 0; color: #555;">
            Laboratório: <strong>{{ $lab->name }}</strong><br>
            Grupo: <strong>{{ $group->name }}</strong>
        </p>
        <p style="margin: 0 0 20px 0; color: #555;">
            Clique no botão abaixo para completar o cadastro. O link expira em 24 horas.
        </p>
        <p style="margin: 0 0 24px 0;">
            <a href="{{ $registerUrl }}" style="display: inline-block; background: #ff8c00; color: #fff; text-decoration: none; padding: 10px 16px; border-radius: 6px;">
                Criar cadastro de aluno
            </a>
        </p>
        <p style="margin: 0; color: #999; font-size: 12px;">
            Se você não esperava este convite, pode ignorar este e-mail.
        </p>
    </div>
</body>
</html>
