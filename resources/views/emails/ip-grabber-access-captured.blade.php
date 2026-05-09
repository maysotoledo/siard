<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $subject }}</title>
</head>
<body style="margin:0; padding:24px; background:#f3f4f6; font-family:Arial, Helvetica, sans-serif; color:#0f172a;">
    <div style="max-width:760px; margin:0 auto; background:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 8px 24px rgba(15, 23, 42, 0.08);">
        <div style="padding:24px 24px 12px; text-align:center;">
            <h1 style="margin:0; font-size:22px; color:#0f172a;">{{ $title }}</h1>
            <p style="margin:8px 0 0; font-size:14px; color:#475569;">{{ $openingLine }}</p>
        </div>

        <div style="padding:12px 24px 24px;">
            <table role="presentation" style="width:100%; border-collapse:collapse; font-size:14px;">
                <tbody>
                    @foreach ($details as $label => $value)
                        <tr>
                            <td style="padding:10px 0; border-bottom:1px solid #e2e8f0; width:180px; color:#64748b; vertical-align:top;">
                                {{ $label }}
                            </td>
                            <td style="padding:10px 0; border-bottom:1px solid #e2e8f0; color:#0f172a; vertical-align:top; word-break:break-word;">
                                {{ $value }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <p style="margin:20px 0 0; font-size:13px; color:#64748b;">
                Consulte o histórico do IP Grabber no sistema para acompanhar dados enviados depois do carregamento da página, como IP local/WebRTC, resolução e GPS autorizado.
            </p>
        </div>
    </div>
</body>
</html>
