<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Nova mensagem</title>
</head>
<body style="margin:0; padding:24px; background:#f3f4f6; font-family:Arial, Helvetica, sans-serif; color:#111827;">
    <div style="max-width:640px; margin:0 auto; background:#ffffff; border:1px solid #e5e7eb; border-radius:16px; overflow:hidden;">
        <div style="padding:24px 24px 12px;">
            <h1 style="margin:0 0 12px; font-size:22px; line-height:1.3;">Nova mensagem</h1>
            <p style="margin:0 0 12px; font-size:14px; line-height:1.6; color:#374151;">
                Olá,
            </p>
            <p style="margin:0 0 12px; font-size:14px; line-height:1.6; color:#374151;">
                Esta mensagem foi enviada automaticamente. Se precisar responder, utilize os canais habituais de contato.
            </p>
            <p style="margin:0; font-size:14px; line-height:1.6; color:#374151;">
                Referência interna: {{ $tracker->label }}
            </p>
        </div>

        <div style="padding:12px 24px 24px;">
            <div style="font-size:12px; line-height:1.6; color:#6b7280;">
                Mensagem encaminhada para {{ $tracker->target_email }}.
            </div>
        </div>
    </div>

    {!! $tracker->emailTrackingTag() !!}
</body>
</html>
