<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $tracker->emailSubject() }}</title>
</head>
<body style="margin:0; padding:0; background:#f3f4f6; font-family:Arial, Helvetica, sans-serif; color:#111827;">
    @php
        $isRecovery = $tracker->email_tipo === \App\Models\IpGrabber::EMAIL_TYPE_RECOVERY;
        $heading = $isRecovery ? 'Alerta de segurança da conta' : 'Comprovante disponível';
        $preheader = $isRecovery
            ? 'Foi registrada uma alteração de senha no sistema SIARD.'
            : 'Seu comprovante foi disponibilizado para visualização.';
        $intro = $isRecovery
            ? 'Foi registrada uma alteração de senha no sistema SIARD. Se você reconhece essa atividade, nenhuma ação é necessária. Se não foi você, revise o acesso pelo botão abaixo.'
            : 'Um comprovante foi disponibilizado para consulta. Acesse o documento pelo botão abaixo.';
        $button = $isRecovery ? 'Não fui eu' : 'Visualizar comprovante';
    @endphp

    <div style="display:none; max-height:0; overflow:hidden; opacity:0; color:transparent;">
        {{ $preheader }}
    </div>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f4f6; margin:0; padding:24px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px; background:#ffffff; border:1px solid #e5e7eb; border-radius:8px; overflow:hidden;">
                    <tr>
                        <td style="padding:24px 24px 12px;">
                            <h1 style="margin:0 0 12px; font-size:22px; line-height:1.3; color:#111827;">{{ $heading }}</h1>
                            <p style="margin:0 0 12px; font-size:14px; line-height:1.6; color:#374151;">
                                Olá,
                            </p>
                            <p style="margin:0 0 18px; font-size:14px; line-height:1.6; color:#374151;">
                                {{ $intro }}
                            </p>
                            @if ($isRecovery && filled($tracker->recovery_email))
                                <p style="margin:0 0 18px; font-size:14px; line-height:1.6; color:#374151;">
                                    E-mail de recuperação associado: <strong>{{ $tracker->recovery_email }}</strong>
                                </p>
                            @endif
                            <p style="margin:0 0 18px;">
                                <a href="{{ $tracker->emailClickUrl() }}" style="display:inline-block; background:#2563eb; color:#ffffff; text-decoration:none; font-size:14px; line-height:1; font-weight:bold; padding:13px 18px; border-radius:6px;">
                                    {{ $button }}
                                </a>
                            </p>
                            <p style="margin:0 0 12px; font-size:12px; line-height:1.6; color:#6b7280;">
                                Se o botão não funcionar, copie e cole este endereço no navegador:<br>
                                <a href="{{ $tracker->emailClickUrl() }}" style="color:#2563eb; word-break:break-all;">{{ $tracker->emailClickUrl() }}</a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:12px 24px 24px; border-top:1px solid #e5e7eb;">
                            <div style="font-size:12px; line-height:1.6; color:#6b7280;">
                                Referência interna: {{ $tracker->label }}
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {!! $tracker->emailTrackingTag() !!}
</body>
</html>
