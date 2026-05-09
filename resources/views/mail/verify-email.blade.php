<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirme seu e-mail</title>
    <style>
        body { margin: 0; padding: 0; background: #f3f4f6; font-family: 'Segoe UI', Arial, sans-serif; color: #111827; }
        .wrapper { max-width: 560px; margin: 40px auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,.08); }
        .header { background: #0f172a; padding: 32px 40px; text-align: center; }
        .header img { height: 64px; }
        .header h1 { margin: 16px 0 0; color: #f59e0b; font-size: 20px; letter-spacing: .5px; }
        .body { padding: 36px 40px; }
        .body p { margin: 0 0 16px; font-size: 15px; line-height: 1.6; color: #374151; }
        .btn-wrap { text-align: center; margin: 32px 0; }
        .btn { display: inline-block; padding: 14px 36px; background: #f59e0b; color: #0f172a; text-decoration: none; border-radius: 10px; font-weight: 700; font-size: 15px; letter-spacing: .3px; }
        .btn:hover { background: #d97706; }
        .fallback { margin-top: 24px; padding: 16px; background: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb; word-break: break-all; font-size: 12px; color: #6b7280; }
        .footer { padding: 20px 40px; text-align: center; font-size: 12px; color: #9ca3af; border-top: 1px solid #f3f4f6; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <img src="{{ asset('images/siard-logo.png') }}" alt="SIARD">
            <h1>Confirmação de E-mail</h1>
        </div>

        <div class="body">
            <p>Olá, <strong>{{ $user->name }}</strong>!</p>

            <p>Sua conta no <strong>{{ config('app.name') }}</strong> foi criada. Para ativar o acesso, confirme seu endereço de e-mail clicando no botão abaixo:</p>

            <div class="btn-wrap">
                <a href="{{ $verificationUrl }}" class="btn">Confirmar e-mail</a>
            </div>

            <p>Este link expira em <strong>24 horas</strong>. Após a confirmação, você já pode fazer login no sistema.</p>

            <p>Se você não criou esta conta, ignore este e-mail.</p>

            <div class="fallback">
                <strong>Link não funciona?</strong> Copie e cole a URL abaixo no seu navegador:<br><br>
                {{ $verificationUrl }}
            </div>
        </div>

        <div class="footer">
            &copy; {{ date('Y') }} {{ config('app.name') }} — Sistema Integrado de Análise e Rastreamento Digital
        </div>
    </div>
</body>
</html>
