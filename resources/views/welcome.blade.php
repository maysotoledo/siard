<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SIARD — Sistema Integrado de Análise e Rastreamento Digital</title>
    <meta name="description" content="Plataforma profissional de investigação digital: IP Grabber, Rastreamento por Pixel, Análise de Logs e Identidade Digital.">
    <link rel="icon" type="image/png" href="/images/siard-logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Rajdhani:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --blue:   #2563eb;
            --blue-l: #3b82f6;
            --cyan:   #06b6d4;
            --dark:   #060b14;
            --dark2:  #0c1220;
            --dark3:  #111827;
            --border: rgba(59,130,246,.18);
            --text:   #e2e8f0;
            --muted:  #94a3b8;
        }

        html { scroll-behavior: smooth; }

        body {
            background: var(--dark);
            color: var(--text);
            font-family: 'Inter', sans-serif;
            overflow-x: hidden;
        }

        /* ── BACKGROUND GRID ── */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(rgba(37,99,235,.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(37,99,235,.04) 1px, transparent 1px);
            background-size: 60px 60px;
            pointer-events: none;
            z-index: 0;
        }

        /* ── GLOW ORBS ── */
        .glow-1 {
            position: fixed;
            width: 600px; height: 600px;
            top: -150px; left: -150px;
            background: radial-gradient(circle, rgba(37,99,235,.15) 0%, transparent 70%);
            pointer-events: none; z-index: 0;
        }
        .glow-2 {
            position: fixed;
            width: 500px; height: 500px;
            bottom: -100px; right: -100px;
            background: radial-gradient(circle, rgba(6,182,212,.10) 0%, transparent 70%);
            pointer-events: none; z-index: 0;
        }

        /* ── NAV ── */
        nav {
            position: fixed; top: 0; left: 0; right: 0;
            z-index: 100;
            padding: 0 40px;
            height: 72px;
            display: flex; align-items: center; justify-content: space-between;
            background: rgba(6,11,20,.80);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border);
        }
        .nav-logo {
            display: flex; align-items: center; gap: 12px;
        }
        .nav-logo img { height: 36px; }
        .nav-brand {
            font-family: 'Rajdhani', sans-serif;
            font-size: 22px; font-weight: 700;
            letter-spacing: .12em;
            color: #fff;
        }
        .nav-brand span { color: var(--blue-l); }
        .nav-cta {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 9px 24px;
            background: var(--blue);
            color: #fff;
            font-size: 14px; font-weight: 600;
            border-radius: 8px;
            text-decoration: none;
            transition: background .2s, transform .15s;
            border: 1px solid rgba(255,255,255,.12);
        }
        .nav-cta:hover { background: var(--blue-l); transform: translateY(-1px); }

        /* ── HERO ── */
        .hero {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            text-align: center;
            padding: 120px 24px 80px;
        }
        .hero-badge {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 6px 16px;
            background: rgba(37,99,235,.12);
            border: 1px solid rgba(37,99,235,.35);
            border-radius: 100px;
            font-size: 12px; font-weight: 600;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--blue-l);
            margin-bottom: 28px;
        }
        .hero-badge::before {
            content: '';
            width: 6px; height: 6px;
            background: var(--blue-l);
            border-radius: 50%;
            animation: pulse-dot 2s ease-in-out infinite;
        }
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50%       { opacity: .4; transform: scale(.7); }
        }
        .hero-logo {
            width: 110px;
            margin: 0 auto 28px;
            filter: drop-shadow(0 0 24px rgba(37,99,235,.5));
            animation: float 4s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50%       { transform: translateY(-10px); }
        }
        .hero h1 {
            font-family: 'Rajdhani', sans-serif;
            font-size: clamp(42px, 7vw, 88px);
            font-weight: 700;
            letter-spacing: .08em;
            line-height: 1;
            color: #fff;
            margin-bottom: 8px;
        }
        .hero h1 span {
            background: linear-gradient(135deg, var(--blue-l), var(--cyan));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero-sub {
            font-size: 13px; font-weight: 600;
            letter-spacing: .22em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 28px;
        }
        .hero-desc {
            max-width: 560px;
            font-size: 17px;
            line-height: 1.7;
            color: #94a3b8;
            margin: 0 auto 44px;
        }
        .hero-desc strong { color: #cbd5e1; }
        .hero-actions {
            display: flex; align-items: center; gap: 16px;
            flex-wrap: wrap; justify-content: center;
        }
        .btn-primary {
            display: inline-flex; align-items: center; gap: 10px;
            padding: 15px 36px;
            background: linear-gradient(135deg, var(--blue), #1d4ed8);
            color: #fff;
            font-size: 16px; font-weight: 700;
            border-radius: 10px;
            text-decoration: none;
            border: 1px solid rgba(255,255,255,.15);
            transition: transform .2s, box-shadow .2s;
            box-shadow: 0 0 0 0 rgba(37,99,235,0);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(37,99,235,.45);
        }
        .btn-primary svg { flex-shrink: 0; }
        .btn-ghost {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 14px 28px;
            color: var(--muted);
            font-size: 15px; font-weight: 500;
            border-radius: 10px;
            border: 1px solid var(--border);
            text-decoration: none;
            transition: color .2s, border-color .2s;
        }
        .btn-ghost:hover { color: #fff; border-color: rgba(59,130,246,.45); }
        /* ── STATS ── */
        .stats {
            position: relative; z-index: 1;
            display: flex; justify-content: center;
            flex-wrap: wrap; gap: 0;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            background: rgba(12,18,32,.6);
        }
        .stat {
            flex: 1; min-width: 160px;
            text-align: center;
            padding: 32px 24px;
            border-right: 1px solid var(--border);
        }
        .stat:last-child { border-right: none; }
        .stat-value {
            font-family: 'Rajdhani', sans-serif;
            font-size: 36px; font-weight: 700;
            color: #fff;
            line-height: 1;
            margin-bottom: 6px;
        }
        .stat-value span { color: var(--blue-l); }
        .stat-label { font-size: 12px; color: var(--muted); letter-spacing: .06em; text-transform: uppercase; }

        /* ── FEATURES ── */
        .section {
            position: relative; z-index: 1;
            padding: 96px 24px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .section-label {
            display: inline-flex; align-items: center; gap: 8px;
            font-size: 12px; font-weight: 700;
            letter-spacing: .14em; text-transform: uppercase;
            color: var(--blue-l);
            margin-bottom: 14px;
        }
        .section-label::before {
            content: '';
            display: block; width: 24px; height: 2px;
            background: var(--blue-l);
        }
        .section-title {
            font-family: 'Rajdhani', sans-serif;
            font-size: clamp(28px, 4vw, 44px);
            font-weight: 700;
            color: #fff;
            margin-bottom: 16px;
            line-height: 1.1;
        }
        .section-title span {
            background: linear-gradient(135deg, var(--blue-l), var(--cyan));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .section-desc {
            font-size: 16px; color: var(--muted);
            line-height: 1.7;
            max-width: 520px;
            margin-bottom: 56px;
        }

        /* ── MODULE CARDS ── */
        .modules-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }
        .module-card {
            background: rgba(12,18,32,.8);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 28px;
            transition: border-color .25s, transform .25s, box-shadow .25s;
            position: relative;
            overflow: hidden;
        }
        .module-card::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0; height: 2px;
            background: linear-gradient(90deg, transparent, var(--blue-l), transparent);
            opacity: 0;
            transition: opacity .3s;
        }
        .module-card:hover {
            border-color: rgba(59,130,246,.4);
            transform: translateY(-4px);
            box-shadow: 0 16px 48px rgba(37,99,235,.12);
        }
        .module-card:hover::before { opacity: 1; }
        .module-icon {
            width: 48px; height: 48px;
            border-radius: 12px;
            background: rgba(37,99,235,.15);
            border: 1px solid rgba(37,99,235,.25);
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 18px;
            font-size: 22px;
        }
        .module-name {
            font-size: 16px; font-weight: 700;
            color: #fff;
            margin-bottom: 8px;
            letter-spacing: .02em;
        }
        .module-desc {
            font-size: 13.5px;
            color: var(--muted);
            line-height: 1.65;
        }
        .module-tag {
            display: inline-block;
            margin-top: 14px;
            padding: 3px 10px;
            background: rgba(37,99,235,.12);
            border: 1px solid rgba(37,99,235,.25);
            border-radius: 100px;
            font-size: 11px; font-weight: 600;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: var(--blue-l);
        }

        /* ── HOW IT WORKS ── */
        .steps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 2px;
            background: var(--border);
            border: 1px solid var(--border);
            border-radius: 20px;
            overflow: hidden;
        }
        .step {
            background: var(--dark2);
            padding: 36px 28px;
        }
        .step-num {
            font-family: 'Rajdhani', sans-serif;
            font-size: 48px; font-weight: 700;
            line-height: 1;
            color: rgba(37,99,235,.2);
            margin-bottom: 16px;
        }
        .step-title {
            font-size: 15px; font-weight: 700;
            color: #fff; margin-bottom: 8px;
        }
        .step-desc { font-size: 13px; color: var(--muted); line-height: 1.6; }

        /* ── CTA BANNER ── */
        .cta-wrap { position: relative; z-index: 1; padding: 0 24px 96px; }
        .cta-banner {
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid rgba(37,99,235,.35);
            background: linear-gradient(135deg, rgba(37,99,235,.18) 0%, rgba(6,182,212,.08) 100%);
            padding: 72px 48px;
            text-align: center;
            position: relative;
        }
        .cta-banner::before {
            content: '';
            position: absolute; inset: 0;
            background-image:
                linear-gradient(rgba(37,99,235,.06) 1px, transparent 1px),
                linear-gradient(90deg, rgba(37,99,235,.06) 1px, transparent 1px);
            background-size: 40px 40px;
            pointer-events: none;
        }
        .cta-banner h2 {
            font-family: 'Rajdhani', sans-serif;
            font-size: clamp(28px, 4vw, 48px);
            font-weight: 700;
            color: #fff;
            margin-bottom: 14px;
            position: relative;
        }
        .cta-banner p {
            font-size: 16px; color: #94a3b8;
            margin-bottom: 36px; position: relative;
            max-width: 480px; margin-left: auto; margin-right: auto;
        }
        .cta-pricing {
            max-width: 680px;
            margin: -12px auto 34px;
            color: #cbd5e1;
            font-size: 15px;
            line-height: 1.7;
            position: relative;
        }
        .cta-pricing strong { color: #fff; }
        .cta-banner .btn-primary { position: relative; font-size: 17px; padding: 17px 44px; }

        /* ── FOOTER ── */
        footer {
            position: relative; z-index: 1;
            border-top: 1px solid var(--border);
            padding: 32px 40px;
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 16px;
            font-size: 13px; color: var(--muted);
        }
        .footer-brand {
            font-family: 'Rajdhani', sans-serif;
            font-size: 16px; font-weight: 700;
            letter-spacing: .1em;
            color: #fff;
        }
        .footer-brand span { color: var(--blue-l); }

        /* ── RESPONSIVE ── */
        @media (max-width: 640px) {
            nav { padding: 0 20px; }
            .nav-brand { font-size: 17px; }
            .stat { min-width: 140px; }
            .cta-banner { padding: 44px 24px; }
            footer { justify-content: center; text-align: center; }
        }
    </style>
</head>
<body>
    @php
        $monthlyAmount = 'R$ ' . number_format((float) config('services.mercado_pago.pixel_tracker_amount', 29.90), 2, ',', '.');
    @endphp

    <div class="glow-1"></div>
    <div class="glow-2"></div>

    <!-- NAV -->
    <nav>
        <div class="nav-logo">
            <img src="/images/siard-logo.png" alt="SIARD">
            <span class="nav-brand">SI<span>A</span>RD</span>
        </div>
        <a href="/admin" class="nav-cta">
            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>
            Acessar Sistema
        </a>
    </nav>

    <!-- HERO -->
    <section class="hero">
        <div class="hero-badge">Plataforma de Investigação Digital</div>
        <img class="hero-logo" src="/images/siard-logo.png" alt="SIARD Logo">
        <h1>SI<span>A</span>RD</h1>
        <p class="hero-sub">Sistema Integrado de Análise e Rastreamento Digital</p>
        <p class="hero-desc">
            Ferramentas profissionais para <strong>investigação telemática</strong>: rastreamento de IP, análise de logs de plataformas digitais e captura de identidade do alvo — tudo em um único painel.
        </p>
        <div class="hero-actions">
            <a href="/admin" class="btn-primary">
                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                Acessar o Sistema
            </a>
            <a href="#modulos" class="btn-ghost">
                Ver módulos
                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            </a>
        </div>
    </section>

    <!-- STATS -->
    <div class="stats">
        <div class="stat">
            <div class="stat-value">8<span>+</span></div>
            <div class="stat-label">Módulos ativos</div>
        </div>
        <div class="stat">
            <div class="stat-value"><span>{{ $monthlyAmount }}</span></div>
            <div class="stat-label">Mensalidade</div>
        </div>
        <div class="stat">
            <div class="stat-value">5<span> dias</span></div>
            <div class="stat-label">Teste grátis para novos membros</div>
        </div>
        <div class="stat">
            <div class="stat-value"><span>&lt;</span>5s</div>
            <div class="stat-label">Ativação pós-pagamento</div>
        </div>
    </div>

    <!-- MODULES -->
    <div class="section" id="modulos">
        <div class="section-label">Módulos</div>
        <h2 class="section-title">Tudo que você precisa para <span>investigar</span></h2>
        <p class="section-desc">Cada módulo foi desenvolvido para uma fase específica da investigação digital, do rastreamento à análise forense.</p>

        <div class="modules-grid">
            <div class="module-card">
                <div class="module-icon">🎯</div>
                <div class="module-name">IP Grabber</div>
                <div class="module-desc">Gere links invisíveis com preview personalizado para WhatsApp e Telegram. Capture IP, GPS e identidade digital do alvo ao clicar.</div>
                <div class="module-tag">Rastreamento</div>
            </div>
            <div class="module-card">
                <div class="module-icon">📧</div>
                <div class="module-name">Email Tracker</div>
                <div class="module-desc">Pixel invisível embutido em e-mails. Detecta abertura, IP de leitura, localização aproximada e horário — sem interação do destinatário.</div>
                <div class="module-tag">Rastreamento</div>
            </div>
            <div class="module-card">
                <div class="module-icon">🪪</div>
                <div class="module-name">Identidade Digital</div>
                <div class="module-desc">Captura automática de nome, e-mail, telefone e contas logadas no navegador via autofill e detecção cross-site.</div>
                <div class="module-tag">Inteligência</div>
            </div>
            <div class="module-card">
                <div class="module-icon">💬</div>
                <div class="module-name">Análise de Log WhatsApp</div>
                <div class="module-desc">Upload do log exportado pelo WhatsApp. Extrai contatos, linha do tempo de mensagens, IPs de acesso e padrões comportamentais.</div>
                <div class="module-tag">Análise de Log</div>
            </div>
            <div class="module-card">
                <div class="module-icon">📸</div>
                <div class="module-name">Análise de Log Instagram</div>
                <div class="module-desc">Processa o pacote de dados exportado pelo Instagram. Identifica dispositivos, localizações de acesso e atividade da conta.</div>
                <div class="module-tag">Análise de Log</div>
            </div>
            <div class="module-card">
                <div class="module-icon">🔍</div>
                <div class="module-name">Análise de Log Google</div>
                <div class="module-desc">Importa o Google Takeout. Mapeia histórico de atividade, pesquisas, localizações, dispositivos e padrões de uso por período.</div>
                <div class="module-tag">Análise de Log</div>
            </div>
            <div class="module-card">
                <div class="module-icon">🍎</div>
                <div class="module-name">Análise de Log Apple</div>
                <div class="module-desc">Processa a exportação Apple ID / iCloud. Extrai informações de dispositivos, apps utilizados, backups e histórico de localização.</div>
                <div class="module-tag">Análise de Log</div>
            </div>
            <div class="module-card">
                <div class="module-icon">🌐</div>
                <div class="module-name">IP Lookup & Geolocalização</div>
                <div class="module-desc">Consulta avançada de IPs: provedor, ASN, cidade, UF, tipo de conexão (móvel/residencial) e histórico de aparições.</div>
                <div class="module-tag">Inteligência</div>
            </div>
        </div>
    </div>

    <!-- HOW IT WORKS -->
    <div class="section">
        <div class="section-label">Como funciona</div>
        <h2 class="section-title">Simples, rápido e <span>seguro</span></h2>
        <p class="section-desc">Do acesso ao relatório em poucos minutos, sem configuração técnica.</p>

        <div class="steps">
            <div class="step">
                <div class="step-num">01</div>
                <div class="step-title">Cadastro e ativação</div>
                <div class="step-desc">Crie sua conta e valide o e-mail para receber 5 dias de acesso liberado. Após esse período, a mensalidade de {{ $monthlyAmount }} pode ser renovada via Pix com liberação automática pela confirmação do Mercado Pago.</div>
            </div>
            <div class="step">
                <div class="step-num">02</div>
                <div class="step-title">Selecione o módulo</div>
                <div class="step-desc">Escolha a ferramenta ideal: gere um link rastreável, envie um e-mail com pixel ou faça upload do log da plataforma.</div>
            </div>
            <div class="step">
                <div class="step-num">03</div>
                <div class="step-title">Execute a análise</div>
                <div class="step-desc">O sistema processa os dados automaticamente. Para logs, a IA estrutura contatos, IPs e linha do tempo em tempo real.</div>
            </div>
            <div class="step">
                <div class="step-num">04</div>
                <div class="step-title">Exporte o relatório</div>
                <div class="step-desc">Resultados em painéis visuais com exportação para PDF — prontos para relatórios técnicos e procedimentos oficiais.</div>
            </div>
        </div>
    </div>

    <!-- CTA BANNER -->
    <div class="cta-wrap">
        <div class="cta-banner">
            <h2>Pronto para começar?</h2>
            <p>Acesse o sistema agora e tenha as ferramentas de investigação digital mais completas do mercado.</p>
            <div class="cta-pricing">
                Crie sua conta e valide o e-mail para receber <strong>5 dias de acesso liberado</strong>. Após o período inicial, a mensalidade é de <strong>{{ $monthlyAmount }}</strong>.
            </div>
            <a href="/admin" class="btn-primary">
                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                Acessar o SIARD
            </a>
        </div>
    </div>

    <!-- FOOTER -->
    <footer>
        <div class="footer-brand">SI<span>A</span>RD</div>
        <div>Sistema Integrado de Análise e Rastreamento Digital</div>
        <div>© {{ date('Y') }} SIARD. Todos os direitos reservados.</div>
    </footer>
</body>
</html>
