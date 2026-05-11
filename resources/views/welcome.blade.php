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

        /* ── INSTAGRAM ── */
        .insta-section {
            position: relative; z-index: 1;
            padding: 0 24px 80px;
        }
        .insta-card {
            max-width: 780px;
            margin: 0 auto;
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid rgba(188,24,136,.35);
            background: linear-gradient(135deg,
                rgba(240,148,51,.06) 0%,
                rgba(220,39,67,.08) 40%,
                rgba(188,24,136,.08) 70%,
                rgba(131,58,180,.06) 100%);
            padding: 52px 44px;
            text-align: center;
            position: relative;
        }
        .insta-card::before {
            content: '';
            position: absolute; inset: 0;
            background-image:
                linear-gradient(rgba(188,24,136,.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(188,24,136,.04) 1px, transparent 1px);
            background-size: 40px 40px;
            pointer-events: none;
        }
        .insta-logo-wrap {
            display: inline-flex;
            align-items: center; justify-content: center;
            width: 72px; height: 72px;
            border-radius: 20px;
            background: linear-gradient(135deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%);
            margin: 0 auto 24px;
            box-shadow: 0 8px 32px rgba(188,24,136,.35);
            position: relative;
        }
        .insta-eyebrow {
            font-size: 12px; font-weight: 700;
            letter-spacing: .16em; text-transform: uppercase;
            color: #e1306c;
            margin-bottom: 12px;
            position: relative;
        }
        .insta-card h2 {
            font-family: 'Rajdhani', sans-serif;
            font-size: clamp(24px, 4vw, 40px);
            font-weight: 700;
            color: #fff;
            margin-bottom: 14px;
            line-height: 1.15;
            position: relative;
        }
        .insta-card h2 span {
            background: linear-gradient(135deg, #f09433, #dc2743, #bc1888);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .insta-card p {
            font-size: 16px; color: #94a3b8;
            line-height: 1.7;
            max-width: 500px;
            margin: 0 auto 32px;
            position: relative;
        }
        .btn-instagram {
            display: inline-flex; align-items: center; gap: 12px;
            padding: 15px 36px;
            background: linear-gradient(135deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%);
            color: #fff;
            font-size: 16px; font-weight: 700;
            border-radius: 12px;
            text-decoration: none;
            border: none;
            transition: transform .2s, box-shadow .2s, filter .2s;
            box-shadow: 0 6px 28px rgba(188,24,136,.35);
            position: relative;
        }
        .btn-instagram:hover {
            transform: translateY(-2px) scale(1.03);
            box-shadow: 0 12px 40px rgba(188,24,136,.55);
            filter: brightness(1.08);
        }
        .insta-handle {
            display: block;
            margin-top: 18px;
            font-size: 13px;
            color: rgba(255,255,255,.35);
            letter-spacing: .06em;
            position: relative;
        }
        /* Instagram icon no nav */
        .nav-insta {
            display: inline-flex; align-items: center; justify-content: center;
            width: 38px; height: 38px;
            border-radius: 10px;
            background: linear-gradient(135deg, #f09433 0%, #dc2743 50%, #bc1888 100%);
            color: #fff;
            text-decoration: none;
            transition: transform .2s, box-shadow .2s;
            box-shadow: 0 3px 12px rgba(188,24,136,.3);
            flex-shrink: 0;
        }
        .nav-insta:hover {
            transform: translateY(-1px) scale(1.08);
            box-shadow: 0 6px 20px rgba(188,24,136,.5);
        }

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
        .footer-insta {
            display: inline-flex; align-items: center; gap: 7px;
            color: #e1306c;
            text-decoration: none;
            font-size: 13px; font-weight: 500;
            transition: opacity .2s;
        }
        .footer-insta:hover { opacity: .75; }

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
        $setting = \App\Models\PixelModuleSetting::current();
        $manutencaoAtiva = $setting->manutencao_ativa;
        $manutencaoPrevista = $setting->manutencao_prevista;
    @endphp

    <div class="glow-1"></div>
    <div class="glow-2"></div>

    <!-- NAV -->
    <nav>
        <div class="nav-logo">
            <img src="/images/siard-logo.png" alt="SIARD">
            <span class="nav-brand">SI<span>A</span>RD</span>
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
            <a href="https://www.instagram.com/siard.sistema/" target="_blank" rel="noopener noreferrer" class="nav-insta" title="Siga o SIARD no Instagram">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="white" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
            </a>
            <a href="/admin" class="nav-cta">
                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>
                Acessar Sistema
            </a>
        </div>
    </nav>

    <!-- BANNER MANUTENÇÃO -->
    @if($manutencaoAtiva)
    <div style="position:relative;z-index:99;background:linear-gradient(90deg,#92400e,#b45309);border-bottom:1px solid #d97706;padding:12px 24px;display:flex;align-items:center;justify-content:center;gap:12px;flex-wrap:wrap;text-align:center;margin-top:72px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="#fef3c7" style="flex-shrink:0">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
        </svg>
        <span style="color:#fef3c7;font-size:14px;font-weight:600;letter-spacing:.02em;">
            Atualização do sistema prevista
            @if($manutencaoPrevista)
                para {{ $manutencaoPrevista->setTimezone('America/Sao_Paulo')->format('d/m/Y \à\s H:i') }}.
            @else
                em breve.
            @endif
            O sistema poderá ficar indisponível temporariamente.
        </span>
    </div>
    @endif

    <!-- HERO -->
    <section class="hero" @if($manutencaoAtiva) style="padding-top:80px;" @endif>
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

    <!-- INSTAGRAM -->
    <div class="insta-section">
        <div class="insta-card">
            <div class="insta-logo-wrap">
                <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" fill="white" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
            </div>
            <div class="insta-eyebrow">📲 Siga o SIARD</div>
            <h2>A investigação digital<br>tem um <span>perfil</span> no Instagram</h2>
            <p>
                Dicas exclusivas, novidades sobre os módulos, casos reais (anonimizados) e muito mais.
                Faça parte da comunidade que está revolucionando a investigação telemática no Brasil. 🇧🇷
            </p>
            <a href="https://www.instagram.com/siard.sistema/" target="_blank" rel="noopener noreferrer" class="btn-instagram">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="white" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                Seguir @siard.sistema
            </a>
            <span class="insta-handle">instagram.com/siard.sistema</span>
        </div>
    </div>

    <!-- FOOTER -->
    <footer>
        <div class="footer-brand">SI<span>A</span>RD</div>
        <div>Sistema Integrado de Análise e Rastreamento Digital</div>
        <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;justify-content:center;">
            <a href="https://www.instagram.com/siard.sistema/" target="_blank" rel="noopener noreferrer" class="footer-insta">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                @siard.sistema
            </a>
            <span>© {{ date('Y') }} SIARD. Todos os direitos reservados.</span>
        </div>
    </footer>
</body>
</html>
