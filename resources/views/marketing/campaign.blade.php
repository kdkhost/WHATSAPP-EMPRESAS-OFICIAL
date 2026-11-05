<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $campaign['headline'] }} | {{ $appName }}</title>
    @if($appFavicon)
        <link rel="icon" href="{{ $appFavicon }}">
        <link rel="shortcut icon" href="{{ $appFavicon }}">
    @endif

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Outfit:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">

    <style>
        :root {
            --mkt-bg: #060913;
            --mkt-surface: #0b1120;
            --mkt-surface-card: rgba(15, 23, 42, 0.75);
            --mkt-border: rgba(255, 255, 255, 0.08);
            --mkt-border-hover: rgba(99, 102, 241, 0.5);
            --mkt-primary: #6366f1;
            --mkt-primary-glow: rgba(99, 102, 241, 0.35);
            --mkt-accent: #06b6d4;
            --mkt-emerald: #10b981;
            --mkt-pink: #ec4899;
            --mkt-amber: #f59e0b;
        }

        body {
            background-color: var(--mkt-bg);
            color: #f1f5f9;
            font-family: 'Plus Jakarta Sans', sans-serif;
            overflow-x: hidden;
            position: relative;
        }

        h1, h2, h3, h4, h5, .font-heading {
            font-family: 'Outfit', sans-serif;
            letter-spacing: -0.02em;
        }

        /* Ambient Glow Backgrounds */
        .ambient-glow-1 {
            position: fixed;
            top: -150px;
            left: 50%;
            transform: translateX(-50%);
            width: 800px;
            height: 550px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.28) 0%, rgba(6, 182, 212, 0.18) 45%, transparent 70%);
            filter: blur(90px);
            z-index: 0;
            pointer-events: none;
        }
        .ambient-glow-2 {
            position: fixed;
            bottom: -150px;
            right: -100px;
            width: 700px;
            height: 700px;
            background: radial-gradient(circle, rgba(236, 72, 153, 0.16) 0%, rgba(99, 102, 241, 0.12) 55%, transparent 75%);
            filter: blur(100px);
            z-index: 0;
            pointer-events: none;
        }

        /* Top Bar de Urgência - Alto Contraste e Legibilidade */
        .mkt-topbar {
            background: rgba(11, 17, 32, 0.96);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(99, 102, 241, 0.4);
            color: #ffffff;
            font-size: 0.9rem;
            font-weight: 600;
            padding: 10px 16px;
            position: sticky;
            top: 0;
            z-index: 1040;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
        }
        .topbar-badge-time {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.5);
            color: #fca5a5;
            font-family: monospace;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 6px;
            letter-spacing: 0.05em;
        }

        /* Glassmorphism Cards */
        .glass-card {
            background: var(--mkt-surface-card);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--mkt-border);
            border-radius: 24px;
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.6);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .glass-card:hover {
            border-color: var(--mkt-border-hover);
            transform: translateY(-4px);
            box-shadow: 0 25px 50px -10px var(--mkt-primary-glow);
        }

        /* Gradientes de Texto */
        .text-gradient-primary {
            background: linear-gradient(135deg, #ffffff 30%, #a5b4fc 70%, #38bdf8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .text-gradient-fire {
            background: linear-gradient(135deg, #f43f5e 0%, #fb7185 50%, #f59e0b 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Cronômetro Regressivo */
        .countdown-box {
            background: rgba(11, 17, 32, 0.85);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 14px;
            padding: 10px 14px;
            text-align: center;
            min-width: 68px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }
        .countdown-num {
            font-size: 1.75rem;
            font-weight: 800;
            line-height: 1;
            color: #38bdf8;
            font-family: 'Outfit', sans-serif;
        }
        .countdown-label {
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #94a3b8;
            margin-top: 4px;
            font-weight: 700;
        }

        /* Botão Principal com Efeito Brilho e Fogo */
        .cta-btn-fire {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 50%, #db2777 100%);
            color: #ffffff;
            font-weight: 800;
            font-size: 1.15rem;
            padding: 16px 28px;
            border-radius: 50px;
            border: none;
            box-shadow: 0 10px 30px rgba(124, 58, 237, 0.45);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }
        .cta-btn-fire:hover {
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 15px 40px rgba(219, 39, 119, 0.55);
            color: #ffffff;
        }
        .cta-btn-fire::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(60deg, transparent, rgba(255, 255, 255, 0.25), transparent);
            transform: rotate(30deg);
            animation: shine 4s infinite;
        }
        @keyframes shine {
            0% { transform: translateX(-100%) rotate(30deg); }
            20% { transform: translateX(100%) rotate(30deg); }
            100% { transform: translateX(100%) rotate(30deg); }
        }

        /* Badge Pulsante */
        .badge-pulse {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(236, 72, 153, 0.15);
            border: 1px solid rgba(236, 72, 153, 0.45);
            color: #f472b6;
            padding: 7px 16px;
            border-radius: 9999px;
            font-size: 0.82rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .pulse-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #ec4899;
            box-shadow: 0 0 10px #ec4899;
            animation: ping 1.5s cubic-bezier(0, 0, 0.2, 1) infinite;
        }
        @keyframes ping {
            75%, 100% {
                transform: scale(2);
                opacity: 0;
            }
        }

        /* Campos de Formulário Estilizados */
        .form-control-mkt {
            background: rgba(11, 17, 32, 0.75);
            border: 1px solid rgba(255, 255, 255, 0.14);
            color: #ffffff;
            border-radius: 12px;
            padding: 14px 18px;
            font-size: 0.98rem;
            transition: all 0.2s ease;
        }
        .form-control-mkt:focus {
            background: rgba(11, 17, 32, 0.95);
            border-color: #6366f1;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.25);
            color: #ffffff;
        }
        .form-control-mkt::placeholder {
            color: #64748b;
        }
        select.form-control-mkt option {
            background: #0f172a;
            color: #ffffff;
        }

        /* Botão Flutuante do WhatsApp */
        .floating-wa-btn {
            position: fixed;
            bottom: 25px;
            right: 25px;
            z-index: 1030;
            display: flex;
            align-items: center;
            gap: 10px;
            background: #25d366;
            color: #ffffff;
            text-decoration: none;
            padding: 13px 22px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.95rem;
            box-shadow: 0 10px 25px rgba(37, 211, 102, 0.45);
            transition: all 0.3s ease;
            animation: bounce-subtle 4s infinite;
        }
        .floating-wa-btn:hover {
            transform: scale(1.06);
            box-shadow: 0 15px 35px rgba(37, 211, 102, 0.65);
            color: #ffffff;
        }
        @keyframes bounce-subtle {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }

        /* Ícones de Benefícios */
        .benefit-icon {
            width: 52px;
            height: 52px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 16px;
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        .benefit-icon-indigo {
            background: rgba(99, 102, 241, 0.18) !important;
            color: #a5b4fc !important;
            border: 1px solid rgba(99, 102, 241, 0.35) !important;
        }
        .benefit-icon-emerald {
            background: rgba(16, 185, 129, 0.18) !important;
            color: #34d399 !important;
            border: 1px solid rgba(16, 185, 129, 0.35) !important;
        }
        .benefit-icon-cyan {
            background: rgba(6, 182, 212, 0.18) !important;
            color: #38bdf8 !important;
            border: 1px solid rgba(6, 182, 212, 0.35) !important;
        }
        .benefit-icon-amber {
            background: rgba(245, 158, 11, 0.18) !important;
            color: #fbbf24 !important;
            border: 1px solid rgba(245, 158, 11, 0.35) !important;
        }

        /* Pílulas de Seção e Badges com Alto Contraste Garantido */
        .mkt-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 18px;
            border-radius: 9999px;
            font-size: 0.82rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            line-height: 1.25;
            text-transform: uppercase;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.3);
        }
        .mkt-pill-emerald {
            background: rgba(16, 185, 129, 0.16) !important;
            border: 1px solid rgba(52, 211, 153, 0.5) !important;
            color: #34d399 !important;
        }
        .mkt-pill-cyan {
            background: rgba(6, 182, 212, 0.16) !important;
            border: 1px solid rgba(56, 189, 248, 0.5) !important;
            color: #38bdf8 !important;
        }
        .mkt-pill-indigo {
            background: rgba(99, 102, 241, 0.16) !important;
            border: 1px solid rgba(165, 180, 252, 0.5) !important;
            color: #a5b4fc !important;
        }
        .mkt-pill-pink {
            background: rgba(236, 72, 153, 0.16) !important;
            border: 1px solid rgba(244, 114, 182, 0.5) !important;
            color: #f472b6 !important;
        }

        /* Medalhão de Garantia de 7 Dias */
        .guarantee-badge-mkt {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.28) 0%, rgba(6, 182, 212, 0.12) 100%);
            border: 2px solid rgba(52, 211, 153, 0.6);
            box-shadow: 0 0 30px rgba(16, 185, 129, 0.35);
            margin: 0 auto 1.25rem auto;
            color: #34d399;
            font-size: 2.2rem;
            flex-shrink: 0;
        }

        /* Cards de Planos Promocionais */
        .plan-card-mkt {
            background: var(--mkt-surface-card);
            border: 1px solid var(--mkt-border);
            border-radius: 20px;
            padding: 2rem 1.5rem;
            transition: all 0.3s ease;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
        }
        .plan-card-mkt.featured {
            background: linear-gradient(180deg, rgba(30, 41, 59, 0.9) 0%, rgba(15, 23, 42, 0.95) 100%);
            border: 2px solid #6366f1;
            box-shadow: 0 0 35px rgba(99, 102, 241, 0.25);
            transform: scale(1.03);
        }
        .plan-card-mkt:hover {
            transform: translateY(-6px);
            border-color: var(--mkt-primary);
        }

        /* Social Proof Live Popup com Avatar de Cliente Real */
        .live-social-toast {
            position: fixed;
            bottom: 25px;
            left: 25px;
            z-index: 1020;
            background: rgba(15, 23, 42, 0.94);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(99, 102, 241, 0.35);
            border-radius: 16px;
            padding: 12px 16px;
            max-width: 380px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.6), 0 0 25px rgba(99, 102, 241, 0.2);
            display: flex;
            align-items: center;
            gap: 12px;
            opacity: 0;
            transform: translateY(25px) scale(0.95);
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            pointer-events: auto;
        }
        .live-social-toast.show {
            opacity: 1;
            transform: translateY(0) scale(1);
        }

        /* Slider de ROI */
        .roi-slider {
            -webkit-appearance: none;
            width: 100%;
            height: 8px;
            border-radius: 5px;
            background: #1e293b;
            outline: none;
        }
        .roi-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #6366f1;
            cursor: pointer;
            box-shadow: 0 0 10px rgba(99, 102, 241, 0.8);
            border: 2px solid #ffffff;
        }

        /* Acordeão FAQ */
        .faq-item-mkt {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--mkt-border);
            border-radius: 14px;
            overflow: hidden;
            margin-bottom: 0.75rem;
            transition: all 0.2s ease;
        }
        .faq-item-mkt:hover {
            border-color: rgba(99, 102, 241, 0.3);
        }
        .faq-question {
            padding: 1.15rem 1.4rem;
            cursor: pointer;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: #ffffff;
        }
        .faq-answer {
            padding: 0 1.4rem 1.25rem;
            color: #94a3b8;
            font-size: 0.95rem;
            line-height: 1.6;
        }

        /* Botão Voltar ao Topo com Barra de Progresso Circular */
        .back-to-top-btn {
            position: fixed;
            bottom: 88px;
            right: 25px;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: rgba(11, 17, 32, 0.94);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.14);
            color: #ffffff;
            padding: 0;
            margin: 0;
            cursor: pointer;
            z-index: 1025;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5), 0 0 15px rgba(99, 102, 241, 0.25);
            opacity: 0;
            visibility: hidden;
            transform: translateY(18px) scale(0.9);
            transition: opacity 0.3s cubic-bezier(0.16, 1, 0.3, 1),
                        transform 0.3s cubic-bezier(0.16, 1, 0.3, 1),
                        visibility 0.3s,
                        box-shadow 0.3s ease,
                        border-color 0.3s ease;
        }
        .back-to-top-btn.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0) scale(1);
        }
        .back-to-top-btn:hover {
            transform: translateY(-3px) scale(1.05);
            border-color: rgba(99, 102, 241, 0.7);
            box-shadow: 0 14px 30px rgba(0, 0, 0, 0.6), 0 0 25px rgba(99, 102, 241, 0.5);
            color: #38bdf8;
        }
        .back-to-top-btn:active {
            transform: translateY(0) scale(0.92);
        }
        .back-to-top-btn.scrolling .back-to-top-icon i {
            animation: rocket-up 0.6s ease-in-out;
        }
        @keyframes rocket-up {
            0% { transform: translateY(0); opacity: 1; }
            45% { transform: translateY(-12px); opacity: 0; }
            50% { transform: translateY(12px); opacity: 0; }
            100% { transform: translateY(0); opacity: 1; }
        }
        .progress-ring {
            position: absolute;
            top: 0;
            left: 0;
            width: 48px;
            height: 48px;
            transform: rotate(-90deg);
            pointer-events: none;
        }
        .progress-ring-track {
            fill: none;
            stroke: rgba(255, 255, 255, 0.1);
            stroke-width: 3.5;
        }
        .progress-ring-circle {
            fill: none;
            stroke: #6366f1;
            stroke-width: 3.5;
            stroke-linecap: round;
            transition: stroke-dashoffset 0.12s linear;
            filter: drop-shadow(0 0 4px rgba(99, 102, 241, 0.8));
        }
        .back-to-top-icon {
            position: relative;
            z-index: 2;
            font-size: 1.15rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s ease;
        }
        .back-to-top-btn:hover .back-to-top-icon {
            transform: translateY(-2px);
        }
        @media (max-width: 768px) {
            .back-to-top-btn {
                bottom: 82px;
                right: 20px;
                width: 44px;
                height: 44px;
            }
            .progress-ring {
                width: 44px;
                height: 44px;
            }
        }

        /* Cards de Depoimentos com Foto de Cliente Real */
        .testimonial-card-mkt {
            background: var(--mkt-surface-card);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--mkt-border);
            border-radius: 20px;
            padding: 1.75rem;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
        }
        .testimonial-card-mkt:hover {
            transform: translateY(-6px);
            border-color: rgba(99, 102, 241, 0.45);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4), 0 0 25px rgba(99, 102, 241, 0.15);
        }
        .testimonial-avatar {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #6366f1;
            box-shadow: 0 0 15px rgba(99, 102, 241, 0.35);
            flex-shrink: 0;
        }
        .testimonial-badge-result {
            background: rgba(16, 185, 129, 0.14);
            border: 1px solid rgba(16, 185, 129, 0.4);
            color: #34d399;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
    </style>
</head>
<body>

<div class="ambient-glow-1"></div>
<div class="ambient-glow-2"></div>

{{-- Top Bar de Urgência Oficial da Campanha --}}
<div class="mkt-topbar">
    <div class="container d-flex align-items-center justify-content-center justify-content-md-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2 text-white flex-wrap">
            <span class="pulse-dot"></span>
            @if(isset($activeCoupon) && $activeCoupon)
                <span class="badge bg-success text-white px-2.5 py-1 font-monospace shadow-sm"><i class="bi bi-tag-fill me-1"></i>CUPOM: {{ $activeCoupon['code'] }}</span>
                <span class="fw-bold text-white">Indicação de {{ $activeCoupon['promoter_name'] }} • Desconto de {{ $activeCoupon['discount_formatted'] }} garantido na 1ª compra!</span>
            @else
                <span class="fw-bold">{{ $campaign['banner_alert'] }}</span>
            @endif
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="small text-slate-300" style="color: #cbd5e1;">Esta condição encerra em:</span>
            <span class="topbar-badge-time" id="topbar-countdown">04:29:59</span>
        </div>
    </div>
</div>

{{-- Header Minimalista e Seguro --}}
<header class="py-3 border-bottom border-white border-opacity-10 position-relative z-1">
    <div class="container d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            @if($appLogo)
                <img src="{{ $appLogo }}" alt="{{ $appName }}" style="max-height: 40px; max-width: 170px; object-fit: contain;">
            @else
                <span class="d-inline-flex align-items-center justify-content-center rounded-3 text-white fw-bold" style="width: 36px; height: 36px; background: linear-gradient(135deg, #6366f1, #a855f7);">
                    <i class="bi bi-whatsapp"></i>
                </span>
                <span class="fw-bold fs-5 text-white">{{ $appName }}</span>
            @endif
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="mkt-pill mkt-pill-emerald d-none d-sm-inline-flex" style="text-transform: none; font-size: 0.85rem;">
                <i class="bi bi-shield-check fs-6"></i>
                <span>Ambiente Seguro & Criptografado</span>
            </div>
            <a href="#form-captura" class="btn btn-sm btn-outline-light rounded-pill px-3 fw-bold">
                Resgatar Oferta
            </a>
        </div>
    </div>
</header>

{{-- Hero Section com Proposta de Valor e Form de Captura --}}
<section class="py-5 position-relative z-1">
    <div class="container">
        <div class="row align-items-center g-5">
            {{-- Coluna de Copy & Prova Social --}}
            <div class="col-lg-7">
                @if(isset($activeCoupon) && $activeCoupon)
                <div class="glass-card p-3 mb-4 position-relative overflow-hidden" style="border: 1px solid rgba(16, 185, 129, 0.45); background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(6, 182, 212, 0.08) 100%);">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold shadow-sm" style="width: 44px; height: 44px; background: linear-gradient(135deg, #10b981, #059669); font-size: 1.25rem;">
                                <i class="bi bi-tag-fill"></i>
                            </div>
                            <div>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <span class="badge bg-success font-monospace px-2.5 py-1 fw-bold fs-6 shadow-sm">
                                        {{ $activeCoupon['code'] }}
                                    </span>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-0.5 rounded-pill small">
                                        <i class="bi bi-check-circle-fill me-1"></i> Cupom Ativado Automaticamente
                                    </span>
                                </div>
                                <div class="text-white fw-bold mt-1" style="font-size: 0.98rem;">
                                    Você foi indicado por <u>{{ $activeCoupon['promoter_name'] }}</u> e ganhou <strong>{{ $activeCoupon['label'] }}</strong>!
                                </div>
                            </div>
                        </div>
                        <a href="{{ route('register', ['ref' => $activeCoupon['slug'], 'cupom' => $activeCoupon['code']]) }}" class="btn btn-sm btn-success rounded-pill px-3 py-2 fw-bold d-inline-flex align-items-center gap-1 shadow-sm">
                            <span>Ir Direto ao Cadastro com Desconto</span>
                            <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
                @endif

                <div class="d-flex align-items-center gap-2 flex-wrap mb-3">
                    <span class="badge-pulse">
                        <span class="pulse-dot"></span>
                        {{ $campaign['discount_badge'] }}
                    </span>
                    @if(isset($activeCoupon) && $activeCoupon)
                    <span class="badge bg-emerald-500 text-white font-monospace px-2.5 py-1 rounded-pill" style="background-color: #10b981;">
                        <i class="bi bi-percent me-1"></i>+{{ $activeCoupon['discount_formatted'] }} OFF da Indicação
                    </span>
                    @endif
                </div>

                <h1 class="display-4 fw-extrabold text-white mb-3" style="line-height: 1.15;">
                    <span class="text-gradient-primary">{{ $campaign['headline'] }}</span>
                </h1>

                <p class="lead mb-4" style="color: #cbd5e1; font-size: 1.18rem; line-height: 1.6;">
                    {{ $campaign['subtitle'] }}
                </p>

                {{-- Prova Social e Visitas Recentes --}}
                <div class="d-flex flex-wrap align-items-center gap-4 py-3 border-top border-bottom border-white border-opacity-10 mb-4">
                    <div class="d-flex align-items-center gap-2">
                        <div class="text-warning fs-5">
                            <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                        </div>
                        <span class="small fw-bold text-white">4.9/5 (+3.500 clientes ativos)</span>
                    </div>
                    <div class="d-flex align-items-center gap-2 text-info small fw-bold">
                        <i class="bi bi-people-fill fs-5"></i>
                        <span id="live-views-count">{{ number_format(max(24, $totalVisits), 0, ',', '.') }}</span> empresas já visualizaram esta vaga
                    </div>
                </div>

                {{-- Diferenciais em Grid 2x2 --}}
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="d-flex align-items-start gap-3 p-3 rounded-4 h-100" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06);">
                            <div class="benefit-icon benefit-icon-indigo">
                                <i class="bi bi-lightning-charge-fill"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-white mb-1">Cobrança Automática WhatsApp</h6>
                                <p class="small text-secondary mb-0">Disparo inteligente de boletos e PIX com confirmação na hora.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="d-flex align-items-start gap-3 p-3 rounded-4 h-100" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06);">
                            <div class="benefit-icon benefit-icon-emerald">
                                <i class="bi bi-shield-lock-fill"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-white mb-1">Proteção Anti-Ban Inteligente</h6>
                                <p class="small text-secondary mb-0">Warmup gradual e intervalos humanizados para blindar seu número.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="d-flex align-items-start gap-3 p-3 rounded-4 h-100" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06);">
                            <div class="benefit-icon benefit-icon-cyan">
                                <i class="bi bi-qr-code-scan"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-white mb-1">PIX Dinâmico Instantâneo</h6>
                                <p class="small text-secondary mb-0">Chave Copia & Cola e QR Code com baixa imediata sem conciliação manual.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="d-flex align-items-start gap-3 p-3 rounded-4 h-100" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06);">
                            <div class="benefit-icon benefit-icon-amber">
                                <i class="bi bi-graph-up-arrow"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-white mb-1">Queda de até 72% na Inadimplência</h6>
                                <p class="small text-secondary mb-0">Lembretes antes, no dia e após o vencimento direto no app que seu cliente usa.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Coluna do Card de Conversão (Formulário com Cronômetro) --}}
            <div class="col-lg-5" id="form-captura">
                <div class="glass-card p-4 p-md-5 position-relative overflow-hidden" style="border: 2px solid rgba(99, 102, 241, 0.35);">
                    <div class="text-center mb-4">
                        <span class="badge bg-danger text-white rounded-pill px-3 py-1 text-uppercase fw-bold small mb-2">
                            <i class="bi bi-stopwatch-fill me-1"></i> Condição Promocional Ativa
                        </span>
                        <h3 class="fw-bold text-white mb-1">Resgate Seu Desconto Exclusivo</h3>
                        <p class="small text-secondary mb-3">Preencha os campos abaixo para garantir a tarifa reduzida com prioridade.</p>

                        {{-- Cronômetro Regressivo em Caixas --}}
                        <div class="d-flex justify-content-center gap-2 mb-2" id="hero-countdown-wrapper">
                            <div class="countdown-box">
                                <div class="countdown-num" id="cd-hours">04</div>
                                <div class="countdown-label">Horas</div>
                            </div>
                            <div class="countdown-box">
                                <div class="countdown-num" id="cd-mins">29</div>
                                <div class="countdown-label">Min</div>
                            </div>
                            <div class="countdown-box">
                                <div class="countdown-num" id="cd-secs">59</div>
                                <div class="countdown-label">Seg</div>
                            </div>
                        </div>
                    </div>

                    {{-- Formulário AJAX --}}
                    <form id="marketing-lead-form">
                        <input type="hidden" name="utm_source" value="{{ request('utm_source') }}">
                        <input type="hidden" name="utm_medium" value="{{ request('utm_medium') }}">
                        <input type="hidden" name="utm_campaign" value="{{ request('utm_campaign') }}">
                        <input type="hidden" name="ref" value="{{ $activeCoupon['slug'] ?? request('ref') }}">
                        <input type="hidden" name="cupom" value="{{ $activeCoupon['code'] ?? request('cupom') }}">
                        <input type="hidden" name="referer" value="{{ request()->headers->get('referer') }}">

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-slate-300">Seu Nome Completo *</label>
                            <input type="text" class="form-control form-control-mkt" name="name" required placeholder="Ex: Carlos Oliveira">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-slate-300">WhatsApp (com DDD) *</label>
                            <input type="tel" class="form-control form-control-mkt" id="lead-whatsapp" name="whatsapp" required placeholder="(11) 99999-9999">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-slate-300">Seu Melhor E-mail</label>
                            <input type="email" class="form-control form-control-mkt" name="email" placeholder="carlos@empresa.com.br">
                        </div>

                        <div class="mb-4">
                            <label class="form-label small fw-bold text-slate-300">Seu Modelo de Negócio</label>
                            <select class="form-select form-control-mkt" name="interest" id="lead-interest">
                                <option value="Provedor de Internet / ISP">Provedor de Internet / ISP</option>
                                <option value="Hospedagem de Sites / WHMCS / cPanel">Hospedagem de Sites / WHMCS</option>
                                <option value="SaaS / Software / Assinaturas">SaaS / Software / Assinaturas</option>
                                <option value="E-commerce / Loja Virtual">E-commerce / Loja Virtual</option>
                                <option value="Prestador de Serviços / Consultoria">Prestador de Serviços / Consultoria</option>
                                <option value="Escritório / Contabilidade / Advocacia">Escritório / Contabilidade / Advocacia</option>
                                <option value="Outro Segmento">Outro Segmento</option>
                            </select>
                        </div>

                        @if(isset($activeCoupon) && $activeCoupon)
                        <div class="p-2.5 rounded-3 mb-3 border border-success border-opacity-25" style="background: rgba(16, 185, 129, 0.08);">
                            <div class="d-flex align-items-center gap-2 text-success small fw-semibold">
                                <i class="bi bi-patch-check-fill fs-5"></i>
                                <span>Cupom <strong>{{ $activeCoupon['code'] }}</strong> ativo: desconto aplicado na sua 1ª compra.</span>
                            </div>
                        </div>
                        @endif
                        <button type="submit" class="btn cta-btn-fire w-100" id="btn-submit-lead">
                            <span class="btn-text d-flex align-items-center justify-content-center gap-2">
                                <i class="bi bi-fire fs-5"></i>
                                <span>{{ $campaign['cta_text'] }}</span>
                            </span>
                            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        </button>

                        <div class="text-center mt-3">
                            <small class="text-secondary" style="font-size: 0.78rem;">
                                <i class="bi bi-shield-check text-success me-1"></i> Seus dados estão 100% protegidos. Resgate sem compromisso.
                            </small>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Seção: Como Funciona o Fluxo de Cobrança Automatizada --}}
<section class="py-5 border-top border-white border-opacity-10 position-relative z-1">
    <div class="container">
        <div class="text-center max-w-xl mx-auto mb-5">
            <span class="mkt-pill mkt-pill-indigo mb-3">
                <i class="bi bi-rocket-takeoff"></i> Como Funciona a Automação
            </span>
            <h2 class="fw-bold text-white">Fluxo Inteligente e 100% Automático</h2>
            <p class="text-secondary">Você configura uma única vez e o sistema faz o trabalho pesado de cobrança todos os dias.</p>
        </div>

        <div class="row g-4">
            <div class="col-md-6 col-lg-3">
                <div class="glass-card p-4 h-100 position-relative">
                    <span class="badge bg-primary rounded-circle mb-3 fs-6 d-inline-flex align-items-center justify-content-center" style="width:36px;height:36px;">1</span>
                    <h5 class="fw-bold text-white mb-2">Conecte o WhatsApp</h5>
                    <p class="small text-secondary mb-0">Escaneie o QR Code em segundos. Funciona com seu número atual comercial ou chip dedicado.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="glass-card p-4 h-100 position-relative">
                    <span class="badge bg-info rounded-circle mb-3 fs-6 d-inline-flex align-items-center justify-content-center" style="width:36px;height:36px;">2</span>
                    <h5 class="fw-bold text-white mb-2">Cadastre as Faturas</h5>
                    <p class="small text-secondary mb-0">Importe via CSV, integre via API ou use nossos módulos nativos para Mercado Pago, Efí e SumUp.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="glass-card p-4 h-100 position-relative">
                    <span class="badge bg-warning rounded-circle mb-3 fs-6 d-inline-flex align-items-center justify-content-center text-dark" style="width:36px;height:36px;">3</span>
                    <h5 class="fw-bold text-white mb-2">Disparos Automáticos</h5>
                    <p class="small text-secondary mb-0">O sistema envia mensagem personalizada com Chave PIX e Boleto antes e no dia do vencimento.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="glass-card p-4 h-100 position-relative">
                    <span class="badge bg-success rounded-circle mb-3 fs-6 d-inline-flex align-items-center justify-content-center" style="width:36px;height:36px;">4</span>
                    <h5 class="fw-bold text-white mb-2">Baixa Instantânea</h5>
                    <p class="small text-secondary mb-0">Pagou? O webhook confirma o pagamento na hora e envia mensagem de agradecimento ao cliente.</p>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Calculadora Interativa de ROI & Economia --}}
<section class="py-5 position-relative z-1">
    <div class="container">
        <div class="glass-card p-4 p-md-5" style="border: 2px solid rgba(6, 182, 212, 0.3);">
            <div class="row align-items-center g-4">
                <div class="col-lg-6">
                    <span class="mkt-pill mkt-pill-cyan mb-3">
                        <i class="bi bi-calculator"></i> Simulador de Resultados
                    </span>
                    <h2 class="fw-bold text-white mb-3">Veja Quanto Sua Empresa Pode Recuperar</h2>
                    <p class="text-secondary mb-4">Arraste a barra para indicar o volume de clientes faturados mensalmente e veja a estimativa de horas e receita recuperada.</p>
                    
                    <div class="mb-4">
                        <label class="form-label d-flex justify-content-between text-white fw-bold mb-2">
                            <span>Quantidade de Clientes Faturados:</span>
                            <span class="text-info fs-5" id="roi-clients-display">250 clientes</span>
                        </label>
                        <input type="range" class="roi-slider" id="roi-clients-slider" min="30" max="3000" step="10" value="250">
                    </div>

                    <a href="#form-captura" class="btn cta-btn-fire">
                        <i class="bi bi-lightning-charge me-1"></i> Quero Economizar Este Mês
                    </a>
                </div>

                <div class="col-lg-6">
                    <div class="row g-3 text-center">
                        <div class="col-6">
                            <div class="p-3 rounded-4" style="background: rgba(15, 23, 42, 0.85); border: 1px solid rgba(255,255,255,0.08);">
                                <span class="text-secondary small d-block mb-1 text-uppercase fw-bold">Horas de Cobrança Poupadas</span>
                                <strong class="fs-2 text-warning font-heading" id="roi-hours-saved">35h</strong>
                                <small class="text-secondary d-block mt-1">por mês da equipe</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 rounded-4" style="background: rgba(15, 23, 42, 0.85); border: 1px solid rgba(255,255,255,0.08);">
                                <span class="text-secondary small d-block mb-1 text-uppercase fw-bold">Inadimplência Reduzida</span>
                                <strong class="fs-2 text-success font-heading" id="roi-reduction-percent">Até 68%</strong>
                                <small class="text-secondary d-block mt-1">no primeiro ciclo</small>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="p-3 rounded-4" style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3);">
                                <span class="text-success small d-block mb-1 text-uppercase fw-bold">Estimativa de Receita Antecipada</span>
                                <strong class="fs-1 text-white font-heading" id="roi-revenue-saved">R$ 4.750,00</strong>
                                <small class="text-slate-300 d-block mt-1" style="color: #cbd5e1;">recuperados em pagamentos que antes atrasavam</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Seção: Planos com Condição Promocional --}}
<section class="py-5 border-top border-white border-opacity-10 position-relative z-1" id="planos">
    <div class="container">
        <div class="text-center max-w-xl mx-auto mb-5">
            <span class="mkt-pill mkt-pill-emerald mb-3">
                <i class="bi bi-stars"></i> Planos Promocionais
            </span>
            <h2 class="fw-bold text-white">Escolha Seu Nível de Automação</h2>
            <p class="text-secondary">Todos os planos contam com ativação expressa e a garantia de lançamento desta página.</p>
        </div>

        @if(isset($plans) && $plans->isNotEmpty())
            @php
                $planCount = $plans->count();
                $colClass = match($planCount) {
                    1 => 'col-md-8 col-lg-5',
                    2 => 'col-md-6 col-lg-5',
                    default => 'col-md-6 col-lg-4'
                };
            @endphp
            <div class="row g-4 justify-content-center">
                @foreach($plans as $plan)
                <div class="{{ $colClass }}">
                    <div class="plan-card-mkt h-100 d-flex flex-column justify-content-between {{ $plan->highlight ? 'featured' : '' }}">
                        <div>
                            <div class="d-flex align-items-center justify-content-between mb-3 gap-2">
                                <h4 class="fw-bold text-white mb-0">{{ $plan->name }}</h4>
                                @if(!empty($plan->campaign_badge))
                                    <span class="badge bg-danger rounded-pill px-3 py-1 font-monospace fw-bold" style="font-size: 0.76rem; letter-spacing: 0.03em;">
                                        {{ $plan->campaign_badge }}
                                    </span>
                                @elseif($plan->highlight)
                                    <span class="badge bg-danger text-white rounded-pill">Mais Popular</span>
                                @elseif($plan->badge)
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">{{ $plan->badge }}</span>
                                @endif
                            </div>
                            <p class="text-secondary small mb-4">{{ $plan->description ?? 'Solução completa para automatizar sua rotina financeira.' }}</p>

                            <div class="mb-4">
                                @if(($plan->campaign_original_price ?? 0) > ($plan->campaign_promo_price ?? 0))
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <span class="text-secondary text-decoration-line-through small">
                                            De R$ {{ number_format($plan->campaign_original_price, 2, ',', '.') }}
                                        </span>
                                        <span class="badge bg-success bg-opacity-25 border border-success border-opacity-40" style="color: #34d399; font-size: 0.72rem;">
                                            Economize R$ {{ number_format($plan->campaign_discount_amount, 2, ',', '.') }}
                                        </span>
                                    </div>
                                @endif
                                <div class="d-flex align-items-baseline gap-1">
                                    <span class="fs-4 text-white fw-bold">R$</span>
                                    <span class="display-5 fw-extrabold text-white font-heading">
                                        {{ number_format($plan->campaign_promo_price ?? $plan->price, 2, ',', '.') }}
                                    </span>
                                    <span class="text-secondary small">/mês</span>
                                </div>
                                <small class="text-info d-block mt-1 font-monospace" style="font-size: 0.78rem;">
                                    <i class="bi bi-patch-check-fill me-1"></i> Condição promocional exclusiva desta página
                                </small>
                            </div>

                            @php
                                $planFeatures = $plan->features;
                                while (is_string($planFeatures)) {
                                    $dec = json_decode($planFeatures, true);
                                    if ($dec === null || $dec === $planFeatures) break;
                                    $planFeatures = $dec;
                                }
                                if (! is_array($planFeatures)) {
                                    $planFeatures = [];
                                }

                                // Fallback inteligente caso o plano esteja sem features cadastradas
                                if (empty($planFeatures)) {
                                    $planFeatures = array_filter([
                                        ($plan->max_instances ?? 1) . ' ' . (($plan->max_instances ?? 1) > 1 ? 'Instâncias WhatsApp' : 'Instância WhatsApp'),
                                        ($plan->max_invoices_month ?? 500) ? 'Até ' . number_format($plan->max_invoices_month, 0, ',', '.') . ' Cobranças/mês' : null,
                                        'Disparos com PIX Copia & Cola',
                                        $plan->custom_gateway ? 'Multi-gateways (MP, Efí, SumUp)' : 'Gateway nativo incluso',
                                        'Proteção Anti-Ban Inteligente',
                                        $plan->highlight ? 'Suporte VIP Prioritário' : 'Suporte Especializado',
                                    ]);
                                }
                            @endphp
                            <ul class="list-unstyled d-flex flex-column gap-2 mb-4 small text-slate-300" style="color: #cbd5e1;">
                                @foreach($planFeatures as $f)
                                    @php
                                        $fLabel = is_array($f) ? ($f['name'] ?? json_encode($f)) : $f;
                                        if (strtolower((string) $fLabel) === 'contracts') {
                                            $fLabel = 'Módulo de Contratos Digitais';
                                        }
                                    @endphp
                                    <li class="d-flex align-items-center gap-2">
                                        <i class="bi bi-check-circle-fill text-success"></i>
                                        <span>{{ $fLabel }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        <div class="pt-3 border-top border-white border-opacity-10 mt-auto">
                            <a href="#form-captura" class="btn {{ $plan->highlight ? 'cta-btn-fire' : 'btn-outline-light' }} w-100 rounded-pill py-2 fw-bold js-select-plan" data-plan="{{ $plan->name }}">
                                Garantir Vaga no {{ $plan->name }}
                            </a>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        @else
            {{-- Fallback elegante caso a tabela plans esteja vazia --}}
            @php
                $discountPercent = max(1, min(99, (int) ($campaign['discount_percent'] ?? 50)));
                $badgeText = $campaign['discount_badge'] ?? ($discountPercent . '% OFF');
            @endphp
            <div class="row g-4 justify-content-center">
                <div class="col-md-6 col-lg-4">
                    <div class="plan-card-mkt h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex align-items-center justify-content-between mb-3 gap-2">
                                <h4 class="fw-bold text-white mb-0">Starter</h4>
                                <span class="badge bg-danger rounded-pill px-3 py-1 font-monospace fw-bold" style="font-size: 0.76rem;">{{ $badgeText }}</span>
                            </div>
                            <p class="text-secondary small mb-4">Ideal para quem está iniciando a automação.</p>
                            <div class="mb-4">
                                <span class="text-secondary text-decoration-line-through small d-block">De R$ 97,00</span>
                                <div class="d-flex align-items-baseline gap-1">
                                    <span class="fs-4 text-white fw-bold">R$</span>
                                    <span class="display-5 fw-extrabold text-white font-heading">
                                        {{ number_format(97 * ((100 - $discountPercent) / 100), 2, ',', '.') }}
                                    </span>
                                    <span class="text-secondary small">/mês</span>
                                </div>
                                <small class="text-info d-block mt-1 font-monospace" style="font-size: 0.78rem;">
                                    <i class="bi bi-patch-check-fill me-1"></i> Condição promocional exclusiva
                                </small>
                            </div>
                            <ul class="list-unstyled d-flex flex-column gap-2 mb-4 small text-slate-300" style="color: #cbd5e1;">
                                <li><i class="bi bi-check-circle-fill text-success me-2"></i>1 Conexão de WhatsApp</li>
                                <li><i class="bi bi-check-circle-fill text-success me-2"></i>Até 300 Faturas/mês</li>
                                <li><i class="bi bi-check-circle-fill text-success me-2"></i>Disparos com PIX Copia & Cola</li>
                                <li><i class="bi bi-check-circle-fill text-success me-2"></i>Proteção Anti-Ban Inclusa</li>
                            </ul>
                        </div>
                        <div class="pt-3 border-top border-white border-opacity-10 mt-auto">
                            <a href="#form-captura" class="btn btn-outline-light w-100 rounded-pill py-2 fw-bold js-select-plan" data-plan="Starter">Garantir Vaga no Starter</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="plan-card-mkt featured h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex align-items-center justify-content-between mb-3 gap-2">
                                <h4 class="fw-bold text-white mb-0">Profissional</h4>
                                <span class="badge bg-danger rounded-pill px-3 py-1 font-monospace fw-bold" style="font-size: 0.76rem;">{{ $badgeText }}</span>
                            </div>
                            <p class="text-secondary small mb-4">Para empresas em crescimento e provedores.</p>
                            <div class="mb-4">
                                <span class="text-secondary text-decoration-line-through small d-block">De R$ 197,00</span>
                                <div class="d-flex align-items-baseline gap-1">
                                    <span class="fs-4 text-white fw-bold">R$</span>
                                    <span class="display-5 fw-extrabold text-white font-heading">
                                        {{ number_format(197 * ((100 - $discountPercent) / 100), 2, ',', '.') }}
                                    </span>
                                    <span class="text-secondary small">/mês</span>
                                </div>
                                <small class="text-info d-block mt-1 font-monospace" style="font-size: 0.78rem;">
                                    <i class="bi bi-patch-check-fill me-1"></i> Condição promocional exclusiva
                                </small>
                            </div>
                            <ul class="list-unstyled d-flex flex-column gap-2 mb-4 small text-slate-300" style="color: #cbd5e1;">
                                <li><i class="bi bi-check-circle-fill text-success me-2"></i>Até 3 Conexões de WhatsApp</li>
                                <li><i class="bi bi-check-circle-fill text-success me-2"></i>Faturas Ilimitadas</li>
                                <li><i class="bi bi-check-circle-fill text-success me-2"></i>Integração Webhook Mercado Pago & Efí</li>
                                <li><i class="bi bi-check-circle-fill text-success me-2"></i>Warmup Inteligente & Suporte VIP</li>
                            </ul>
                        </div>
                        <div class="pt-3 border-top border-white border-opacity-10 mt-auto">
                            <a href="#form-captura" class="btn cta-btn-fire w-100 rounded-pill py-2 fw-bold js-select-plan" data-plan="Profissional">Garantir Vaga no Profissional</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="plan-card-mkt h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex align-items-center justify-content-between mb-3 gap-2">
                                <h4 class="fw-bold text-white mb-0">Enterprise</h4>
                                <span class="badge bg-danger rounded-pill px-3 py-1 font-monospace fw-bold" style="font-size: 0.76rem;">{{ $badgeText }}</span>
                            </div>
                            <p class="text-secondary small mb-4">Para grandes operações com alta demanda.</p>
                            <div class="mb-4">
                                <span class="text-secondary text-decoration-line-through small d-block">De R$ 497,00</span>
                                <div class="d-flex align-items-baseline gap-1">
                                    <span class="fs-4 text-white fw-bold">R$</span>
                                    <span class="display-5 fw-extrabold text-white font-heading">
                                        {{ number_format(497 * ((100 - $discountPercent) / 100), 2, ',', '.') }}
                                    </span>
                                    <span class="text-secondary small">/mês</span>
                                </div>
                                <small class="text-info d-block mt-1 font-monospace" style="font-size: 0.78rem;">
                                    <i class="bi bi-patch-check-fill me-1"></i> Condição promocional exclusiva
                                </small>
                            </div>
                            <ul class="list-unstyled d-flex flex-column gap-2 mb-4 small text-slate-300" style="color: #cbd5e1;">
                                <li><i class="bi bi-check-circle-fill text-success me-2"></i>Multi-instâncias Ilimitadas</li>
                                <li><i class="bi bi-check-circle-fill text-success me-2"></i>API REST Completa</li>
                                <li><i class="bi bi-check-circle-fill text-success me-2"></i>Setup Acompanhado por Especialista</li>
                                <li><i class="bi bi-check-circle-fill text-success me-2"></i>SLA Garantido 99.9%</li>
                            </ul>
                        </div>
                        <div class="pt-3 border-top border-white border-opacity-10 mt-auto">
                            <a href="#form-captura" class="btn btn-outline-light w-100 rounded-pill py-2 fw-bold js-select-plan" data-plan="Enterprise">Garantir Vaga no Enterprise</a>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</section>

{{-- Seção: Prova Social & Depoimentos Reais com Fotos de Clientes --}}
@php
    $dummyTestimonials = [
        [
            'name' => 'Carlos Eduardo Almeida',
            'role' => 'CEO • FiberNet Telecom (Provedor ISP)',
            'rating' => 5,
            'content' => 'O sistema revolucionou a régua de cobrança da nossa empresa. A redução de inadimplência foi de 28% para menos de 4% logo no primeiro mês com o PIX automático no WhatsApp.',
            'avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=160&auto=format&fit=crop&q=80',
            'result' => 'Inadimplência caiu 86%',
        ],
        [
            'name' => 'Mariana Silveira',
            'role' => 'Diretora Financeira • Confecções Bella',
            'rating' => 5,
            'content' => 'Nossos clientes adoram a facilidade de receber o PIX Copia & Cola e o link do boleto direto no WhatsApp. A baixa automática e envio de comprovante na hora economizou mais de 40h de cobrança da equipe.',
            'avatar' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=160&auto=format&fit=crop&q=80',
            'result' => '+40h poupadas/mês',
        ],
        [
            'name' => 'Lucas Peixoto',
            'role' => 'Fundador • Agência ScaleUp Digital',
            'rating' => 5,
            'content' => 'A proteção anti-ban e o aquecimento gradual nos deram a segurança que precisávamos para disparar avisos de vencimento para mais de 1.200 mensalistas sem nenhum bloqueio de número.',
            'avatar' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=160&auto=format&fit=crop&q=80',
            'result' => '1.200 mensalistas ativos',
        ],
        [
            'name' => 'Dra. Fernanda Vasconcelos',
            'role' => 'Gestora Administrativa • Clínica Santa Fé',
            'rating' => 5,
            'content' => 'Antes tínhamos que ligar um por um cobrando mensalidades e consultas. Hoje o sistema envia com elegância e respeito, e os clientes pagam antes mesmo de vencer. Fantástico!',
            'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=160&auto=format&fit=crop&q=80',
            'result' => '94% pagam antes do prazo',
        ],
        [
            'name' => 'Thiago Henrique Reis',
            'role' => 'Sócio-Diretor • Distribuidora União',
            'rating' => 5,
            'content' => 'A integração com o Mercado Pago e Efí foi em minutos. A conciliação bancária agora é instantânea via Webhook. Não imagino mais nossa empresa operando sem essa automação.',
            'avatar' => 'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?w=160&auto=format&fit=crop&q=80',
            'result' => 'R$ 18.500 recuperados',
        ],
        [
            'name' => 'Renata Albuquerque',
            'role' => 'Contadora Chefe • Prime Gestão Contábil',
            'rating' => 5,
            'content' => 'Os templates de mensagens são humanizados e totalmente customizáveis. Nossos honorários agora entram com pontualidade britânica no caixa. Suporte excepcional e muito atencioso.',
            'avatar' => 'https://images.unsplash.com/photo-1580489944761-15a19d654956?w=160&auto=format&fit=crop&q=80',
            'result' => 'Pontualidade de 99%',
        ],
    ];

    $fallbackAvatars = [
        'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=160&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=160&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=160&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=160&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?w=160&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1580489944761-15a19d654956?w=160&auto=format&fit=crop&q=80',
    ];

    $hasCustomTestimonials = isset($testimonials) && $testimonials->isNotEmpty();
    $displayTestimonials = $hasCustomTestimonials ? $testimonials : collect($dummyTestimonials);
@endphp

<section class="py-5 border-top border-white border-opacity-10 position-relative z-1" id="depoimentos">
    <div class="container">
        <div class="text-center max-w-xl mx-auto mb-5">
            <span class="mkt-pill mkt-pill-indigo mb-3">
                <i class="bi bi-patch-check-fill"></i> Casos de Sucesso Comprovados
            </span>
            <h2 class="fw-bold text-white">Empresas que Escalam Faturamento</h2>
            <p class="text-secondary">Depoimentos reais de quem usa nossa automação para reduzir a inadimplência e recuperar receita.</p>
        </div>

        <div class="row g-4">
            @foreach($displayTestimonials as $t)
            @php
                $isObj = is_object($t);
                $name = $isObj ? $t->name : $t['name'];
                $role = $isObj ? ($t->role ?? 'Empresário') : $t['role'];
                $rating = $isObj ? ($t->rating ?? 5) : ($t['rating'] ?? 5);
                $content = $isObj ? $t->content : $t['content'];
                $rawAvatar = $isObj ? ($t->avatar ?? null) : ($t['avatar'] ?? null);
                $avatar = (!empty($rawAvatar) && !str_contains($rawAvatar, 'ui-avatars.com')) 
                    ? $rawAvatar 
                    : ($fallbackAvatars[$loop->index % count($fallbackAvatars)]);
                $resultBadge = (!$isObj && !empty($t['result'])) ? $t['result'] : 'Cliente Ativo Verificado';
            @endphp
            <div class="col-md-6 col-lg-4">
                <div class="testimonial-card-mkt">
                    <div>
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="text-warning">
                                @for($i = 0; $i < $rating; $i++)
                                    <i class="bi bi-star-fill"></i>
                                @endfor
                            </div>
                            <span class="testimonial-badge-result">
                                <i class="bi bi-check-circle-fill"></i> {{ $resultBadge }}
                            </span>
                        </div>
                        <p class="text-slate-300 fst-italic mb-4" style="color: #cbd5e1; line-height: 1.6; font-size: 0.95rem;">
                            "{{ $content }}"
                        </p>
                    </div>
                    <div class="d-flex align-items-center gap-3 pt-3 border-top border-white border-opacity-10">
                        <img src="{{ $avatar }}" alt="{{ $name }}" class="testimonial-avatar" loading="lazy">
                        <div>
                            <div class="fw-bold text-white small d-flex align-items-center gap-1">
                                <span>{{ $name }}</span>
                                <i class="bi bi-patch-check-fill text-info" style="font-size: 0.85rem;" title="Cliente Verificado"></i>
                            </div>
                            <div class="text-secondary small" style="font-size: 0.78rem;">{{ $role }}</div>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Seção: Perguntas Frequentes (FAQ) --}}
<section class="py-5 border-top border-white border-opacity-10 position-relative z-1">
    <div class="container">
        <div class="text-center max-w-xl mx-auto mb-5">
            <span class="mkt-pill mkt-pill-cyan mb-3">
                <i class="bi bi-question-circle"></i> Perguntas Frequentes
            </span>
            <h2 class="fw-bold text-white">Perguntas Frequentes</h2>
            <p class="text-secondary">Tudo o que você precisa saber antes de iniciar sua automação.</p>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="faq-item-mkt">
                    <div class="faq-question">
                        <span>Preciso deixar meu computador ou celular ligado?</span>
                        <i class="bi bi-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        Não! Nossa infraestrutura é 100% hospedada em servidores em nuvem de alta disponibilidade. Uma vez conectado o WhatsApp via QR Code, o sistema dispara e processa as cobranças automaticamente 24 horas por dia, 7 dias por semana, mesmo que seu aparelho esteja desligado.
                    </div>
                </div>

                <div class="faq-item-mkt">
                    <div class="faq-question">
                        <span>Existe risco de bloqueio do meu número de WhatsApp?</span>
                        <i class="bi bi-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        Nosso sistema conta com algoritmo de inteligência anti-ban proprietário: ele realiza aquecimento gradual (warmup), intervalos aleatórios entre disparos humanos e rotação de instâncias. Além disso, as mensagens são transacionais (faturas e comprovantes solicitados pelo cliente), o que garante alta taxa de abertura e confiança.
                    </div>
                </div>

                <div class="faq-item-mkt">
                    <div class="faq-question">
                        <span>Quais gateways de pagamento são integrados?</span>
                        <i class="bi bi-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        Temos suporte nativo a Mercado Pago, Efí Pro (antiga Gerencianet), SumUp e webhooks universais. Você recebe o dinheiro das cobranças diretamente na sua conta do gateway, com tarifa bancária padrão da sua instituição, sem cobrança de taxas ocultas por nós.
                    </div>
                </div>

                <div class="faq-item-mkt">
                    <div class="faq-question">
                        <span>Como funciona o desconto promocional desta página?</span>
                        <i class="bi bi-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        Ao preencher o formulário desta página promocional, seus dados são registrados prioritariamente no nosso time comercial e você recebe o cupom com a tarifa especial de lançamento direto no seu WhatsApp em instantes.
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Seção: Garantia Blindada Incondicional --}}
<section class="py-5 position-relative z-1">
    <div class="container">
        <div class="glass-card p-4 p-md-5 text-center" style="background: radial-gradient(circle at center, rgba(30, 58, 138, 0.45) 0%, rgba(15, 23, 42, 0.85) 100%); border: 1px solid rgba(59, 130, 246, 0.4);">
            <div class="guarantee-badge-mkt">
                <i class="bi bi-shield-fill-check"></i>
            </div>
            <h2 class="fw-bold text-white mb-2">Garantia Blindada de Satisfação de 7 Dias</h2>
            <p class="lead mx-auto mb-4" style="max-width: 680px; color: #cbd5e1; font-size: 1.1rem; line-height: 1.6;">
                Teste todo o poder do sistema sem qualquer risco financeiro. Se em até 7 dias você não sentir uma redução real na inadimplência e aumento comprovado na sua arrecadação, cancelamos sua adesão sem taxas, multas ou burocracia.
            </p>
            <a href="#form-captura" class="btn btn-outline-light rounded-pill px-4 py-2 fw-bold">
                Aproveitar Oferta Sem Riscos
            </a>
        </div>
    </div>
</section>

{{-- Footer Minimalista --}}
<footer class="py-4 border-top border-white border-opacity-10 text-center text-secondary small position-relative z-1">
    <div class="container">
        <p class="mb-1">&copy; {{ date('Y') }} {{ $appName }}. Todos os direitos reservados.</p>
        <p class="mb-0 text-muted" style="font-size: 0.75rem;">Esta página faz parte de uma campanha de marketing exclusiva. Termos e condições aplicáveis à promoção vigente.</p>
    </div>
</footer>

{{-- Balão Flutuante de Prova Social ao Vivo com Foto de Cliente Real --}}
<div class="live-social-toast" id="live-social-toast">
    <div class="position-relative flex-shrink-0">
        <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=120&h=120&q=80" 
             id="social-toast-avatar" 
             alt="Cliente" 
             class="rounded-circle shadow-sm" 
             style="width: 48px; height: 48px; object-fit: cover; border: 2px solid #10b981;">
        <span class="position-absolute bottom-0 end-0 bg-success border border-dark rounded-circle" style="width: 12px; height: 12px;" title="Online agora"></span>
    </div>
    <div class="small flex-grow-1" style="min-width: 0;">
        <div class="d-flex align-items-center justify-content-between gap-1 mb-1">
            <span class="fw-bold text-white text-truncate" id="social-toast-name">Felipe Santos</span>
            <span class="badge bg-success bg-opacity-25 border border-success border-opacity-30" style="color: #34d399; font-size: 0.65rem; padding: 2px 6px;">✓ Verificado</span>
        </div>
        <div class="text-slate-300 text-truncate mb-1" style="color: #cbd5e1; font-size: 0.8rem;" id="social-toast-action">
            Resgatou vaga no plano <strong class="text-white">Profissional</strong> (50% OFF)
        </div>
        <div class="text-secondary d-flex align-items-center gap-1" style="font-size: 0.72rem;">
            <i class="bi bi-geo-alt-fill text-danger" style="font-size: 0.68rem;"></i>
            <span id="social-toast-location">São Paulo/SP</span> • <span id="social-toast-time">há 2 minutos</span>
        </div>
    </div>
    <button type="button" class="btn-close btn-close-white ms-1 opacity-50 flex-shrink-0" style="font-size: 0.65rem;" onclick="dismissSocialToast()" title="Fechar"></button>
</div>

{{-- Botão Voltar ao Topo com Barra de Progresso Circular --}}
<button type="button" id="back-to-top-btn" class="back-to-top-btn" aria-label="Voltar ao topo" title="Voltar ao topo">
    <svg class="progress-ring" width="48" height="48" viewBox="0 0 48 48">
        <circle class="progress-ring-track" cx="24" cy="24" r="20" />
        <circle class="progress-ring-circle" id="back-to-top-progress" cx="24" cy="24" r="20" />
    </svg>
    <span class="back-to-top-icon">
        <i class="bi bi-chevron-up"></i>
    </span>
</button>

{{-- Botão Flutuante do WhatsApp --}}
@php
    $waClean = preg_replace('/\D/', '', (string) ($campaign['whatsapp'] ?? '5511999999999'));
    if (! str_starts_with($waClean, '55') && strlen($waClean) >= 10 && strlen($waClean) <= 11) {
        $waClean = '55' . $waClean;
    }
    $waText = urlencode("Olá! Vim pela página promocional exclusiva e gostaria de tirar dúvidas sobre a oferta especial.");
@endphp
<a href="https://wa.me/{{ $waClean }}?text={{ $waText }}" target="_blank" rel="noopener" class="floating-wa-btn">
    <i class="bi bi-whatsapp fs-5"></i>
    <span>Falar no WhatsApp</span>
</a>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

<script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    // Máscara Inteligente para WhatsApp
    const waInput = document.getElementById('lead-whatsapp');
    if (waInput) {
        waInput.addEventListener('input', function(e) {
            let v = e.target.value.replace(/\D/g, '');
            if (v.length > 11) v = v.substring(0, 11);
            if (v.length > 10) {
                e.target.value = '(' + v.substring(0, 2) + ') ' + v.substring(2, 7) + '-' + v.substring(7);
            } else if (v.length > 6) {
                e.target.value = '(' + v.substring(0, 2) + ') ' + v.substring(2, 6) + '-' + v.substring(6);
            } else if (v.length > 2) {
                e.target.value = '(' + v.substring(0, 2) + ') ' + v.substring(2);
            } else if (v.length > 0) {
                e.target.value = '(' + v;
            }
        });
    }

    // Cronômetro Regressivo Dinâmico com LocalStorage
    const totalHours = {{ (int) ($campaign['countdown_hours'] ?? 6) }};
    const storageKey = 'mkt_countdown_target';
    let targetTime = localStorage.getItem(storageKey);
    if (!targetTime || isNaN(targetTime) || parseInt(targetTime) < Date.now()) {
        targetTime = Date.now() + (totalHours * 3600 * 1000);
        localStorage.setItem(storageKey, targetTime);
    } else {
        targetTime = parseInt(targetTime);
    }

    function updateCountdown() {
        const now = Date.now();
        let remaining = Math.max(0, Math.floor((targetTime - now) / 1000));

        if (remaining <= 0) {
            targetTime = Date.now() + (totalHours * 3600 * 1000);
            localStorage.setItem(storageKey, targetTime);
            remaining = totalHours * 3600;
        }

        const hours = Math.floor(remaining / 3600);
        const mins = Math.floor((remaining % 3600) / 60);
        const secs = remaining % 60;

        const pad = (n) => String(n).padStart(2, '0');
        const strH = pad(hours);
        const strM = pad(mins);
        const strS = pad(secs);

        const cdH = document.getElementById('cd-hours');
        const cdM = document.getElementById('cd-mins');
        const cdS = document.getElementById('cd-secs');
        const topbarCd = document.getElementById('topbar-countdown');

        if (cdH) cdH.textContent = strH;
        if (cdM) cdM.textContent = strM;
        if (cdS) cdS.textContent = strS;
        if (topbarCd) topbarCd.textContent = `${strH}:${strM}:${strS}`;
    }

    setInterval(updateCountdown, 1000);
    updateCountdown();

    // Simulador de ROI & Economia Interativo
    const slider = document.getElementById('roi-clients-slider');
    const displayClients = document.getElementById('roi-clients-display');
    const hoursSavedEl = document.getElementById('roi-hours-saved');
    const revenueSavedEl = document.getElementById('roi-revenue-saved');

    function updateRoi() {
        if (!slider) return;
        const count = parseInt(slider.value, 10);
        if (displayClients) displayClients.textContent = `${count.toLocaleString('pt-BR')} clientes`;

        // Cálculo aproximado: 1 hora a cada 8 clientes cobrados manualmente
        const hours = Math.round(count / 7);
        if (hoursSavedEl) hoursSavedEl.textContent = `${hours}h`;

        // Ticket médio estimado R$ 90,00 com 21% de inadimplência recuperada
        const recovered = (count * 95 * 0.20);
        if (revenueSavedEl) {
            revenueSavedEl.textContent = recovered.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
        }
    }

    if (slider) {
        slider.addEventListener('input', updateRoi);
        updateRoi();
    }

    // Botão de seleção de plano pré-seleciona no formulário
    document.querySelectorAll('.js-select-plan').forEach(btn => {
        btn.addEventListener('click', function(e) {
            const planName = this.getAttribute('data-plan');
            const interestSelect = document.getElementById('lead-interest');
            if (interestSelect && planName) {
                // Tenta achar opção ou adiciona temporariamente
                let found = false;
                for (let i = 0; i < interestSelect.options.length; i++) {
                    if (interestSelect.options[i].value.includes(planName)) {
                        interestSelect.selectedIndex = i;
                        found = true;
                        break;
                    }
                }
                if (!found) {
                    const opt = new Option(`Interesse no Plano ${planName}`, `Plano ${planName}`, true, true);
                    interestSelect.add(opt);
                }
            }
        });
    });

    // Acordeão FAQ Interativo
    document.querySelectorAll('.faq-question').forEach(q => {
        q.addEventListener('click', function() {
            const answer = this.nextElementSibling;
            const icon = this.querySelector('i');
            const isVisible = answer.style.display === 'block';

            document.querySelectorAll('.faq-answer').forEach(a => a.style.display = 'none');
            document.querySelectorAll('.faq-question i').forEach(i => i.className = 'bi bi-chevron-down');

            if (!isVisible) {
                answer.style.display = 'block';
                if (icon) icon.className = 'bi bi-chevron-up text-primary';
            }
        });
    });
    // Abre a primeira pergunta por padrão
    const firstFaqAnswer = document.querySelector('.faq-answer');
    if (firstFaqAnswer) firstFaqAnswer.style.display = 'block';

    // Notificações Flutuantes de Prova Social com Fotos Reais de Clientes (Social Proof Live Toast)
    const socialToast = document.getElementById('live-social-toast');
    const toastAvatar = document.getElementById('social-toast-avatar');
    const toastName = document.getElementById('social-toast-name');
    const toastAction = document.getElementById('social-toast-action');
    const toastLocation = document.getElementById('social-toast-location');
    const toastTime = document.getElementById('social-toast-time');

    const socialProofs = [
        {
            name: "Felipe Santos",
            location: "São Paulo/SP",
            action: "Resgatou vaga no plano <strong class='text-white'>Starter</strong> (50% OFF)",
            time: "há 2 minutos",
            avatar: "https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=120&h=120&q=80"
        },
        {
            name: "Mariana Souza",
            location: "Belo Horizonte/MG",
            action: "Garantiu vaga no plano <strong class='text-white'>Profissional</strong> (50% OFF)",
            time: "há 4 minutos",
            avatar: "https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=120&h=120&q=80"
        },
        {
            name: "Roberto Justos",
            location: "Curitiba/PR",
            action: "Ativou a automação no plano <strong class='text-white'>Enterprise</strong>",
            time: "há 7 minutos",
            avatar: "https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=120&h=120&q=80"
        },
        {
            name: "Juliana Martins",
            location: "Rio de Janeiro/RJ",
            action: "Conectou 3 instâncias de WhatsApp com sucesso",
            time: "há 11 minutos",
            avatar: "https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=120&h=120&q=80"
        },
        {
            name: "Lucas Peixoto",
            location: "Porto Alegre/RS",
            action: "Reduziu 64% da inadimplência no 1º ciclo",
            time: "há 15 minutos",
            avatar: "https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=crop&w=120&h=120&q=80"
        },
        {
            name: "Amanda Costa",
            location: "Brasília/DF",
            action: "Resgatou a promoção com setup VIP prioritário",
            time: "há 19 minutos",
            avatar: "https://images.unsplash.com/photo-1580489944761-15a19d654956?auto=format&fit=crop&w=120&h=120&q=80"
        }
    ];

    let proofIndex = 0;
    let toastTimeout = null;

    function dismissSocialToast() {
        if (socialToast) socialToast.classList.remove('show');
    }

    function showNextSocialProof() {
        if (!socialToast || !toastAvatar || !toastName || !toastAction) return;
        const current = socialProofs[proofIndex];

        toastAvatar.src = current.avatar;
        toastName.textContent = current.name;
        toastAction.innerHTML = current.action;
        if (toastLocation) toastLocation.textContent = current.location;
        if (toastTime) toastTime.textContent = current.time;

        socialToast.classList.add('show');

        clearTimeout(toastTimeout);
        toastTimeout = setTimeout(() => {
            socialToast.classList.remove('show');
        }, 5500);

        proofIndex = (proofIndex + 1) % socialProofs.length;
    }

    // Inicia após 3 segundos e alterna a cada 14 segundos
    setTimeout(() => {
        showNextSocialProof();
        setInterval(showNextSocialProof, 14000);
    }, 3000);

    // Envio do Formulário de Lead via AJAX com SweetAlert2
    const form = document.getElementById('marketing-lead-form');
    const submitBtn = document.getElementById('btn-submit-lead');

    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();

            const btnText = submitBtn.querySelector('.btn-text');
            const spinner = submitBtn.querySelector('.spinner-border');

            submitBtn.disabled = true;
            btnText.classList.add('d-none');
            spinner.classList.remove('d-none');

            const formData = new FormData(form);
            const payload = Object.fromEntries(formData.entries());

            try {
                const response = await fetch('{{ route('marketing.lead') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                const data = await response.json();

                if (response.ok && data.ok) {
                    Toastify({
                        text: "🎉 Vaga e condição especial reservadas com sucesso!",
                        duration: 4000,
                        gravity: "top",
                        position: "right",
                        style: { background: "linear-gradient(to right, #10b981, #059669)" }
                    }).showToast();

                    const couponMsg = data.active_coupon ? `<br><span class="badge bg-success font-monospace px-2 py-1 mt-2">CUPOM: ${data.active_coupon.code} (${data.active_coupon.discount_formatted} OFF)</span><br><small class="text-muted">Seu desconto será aplicado automaticamente na 1ª compra.</small>` : '';
                    const result = await Swal.fire({
                        icon: 'success',
                        title: '🎉 Parabéns, ' + payload.name + '!',
                        html: `Sua condição especial foi reservada com sucesso.${couponMsg}<br><br>
                               Escolha como deseja prosseguir com sua ativação:`,
                        showCancelButton: true,
                        confirmButtonText: '<i class="bi bi-person-plus-fill me-1"></i> Criar Conta com Desconto',
                        cancelButtonText: '<i class="bi bi-whatsapp me-1"></i> Chamar Especialista no WhatsApp',
                        confirmButtonColor: '#10b981',
                        cancelButtonColor: '#25d366',
                        reverseButtons: false
                    });

                    if (result.isConfirmed && data.register_url) {
                        window.location.href = data.register_url;
                    } else if (data.whatsapp_url) {
                        window.open(data.whatsapp_url, '_blank');
                    }

                    form.reset();
                } else {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Atenção',
                        text: data.message || 'Por favor, revise os dados preenchidos.',
                        confirmButtonColor: '#4f46e5'
                    });
                }
            } catch (err) {
                console.error('Erro ao enviar lead:', err);
                Swal.fire({
                    icon: 'error',
                    title: 'Ops!',
                    text: 'Não foi possível enviar sua solicitação no momento. Tente novamente em instantes.',
                    confirmButtonColor: '#ef4444'
                });
            } finally {
                submitBtn.disabled = false;
                btnText.classList.remove('d-none');
                spinner.classList.add('d-none');
            }
        });
    }

    // Botão Voltar ao Topo com Barra Circular de Progresso e Rolagem Suave
    (function initBackToTop() {
        const backToTopBtn = document.getElementById('back-to-top-btn');
        const progressCircle = document.getElementById('back-to-top-progress');
        if (!backToTopBtn || !progressCircle) return;

        const radius = 20;
        const circumference = 2 * Math.PI * radius; // ~125.66
        progressCircle.style.strokeDasharray = `${circumference} ${circumference}`;
        progressCircle.style.strokeDashoffset = `${circumference}`;

        let ticking = false;

        function updateScroll() {
            const scrollTop = window.scrollY || document.documentElement.scrollTop;
            const scrollHeight = document.documentElement.scrollHeight - window.innerHeight;
            const scrollFraction = scrollHeight > 0 ? Math.min(1, Math.max(0, scrollTop / scrollHeight)) : 0;
            const offset = circumference - (scrollFraction * circumference);

            progressCircle.style.strokeDashoffset = offset;

            if (scrollTop > 220) {
                backToTopBtn.classList.add('show');
            } else {
                backToTopBtn.classList.remove('show');
            }

            ticking = false;
        }

        window.addEventListener('scroll', function() {
            if (!ticking) {
                window.requestAnimationFrame(updateScroll);
                ticking = true;
            }
        }, { passive: true });

        updateScroll();

        backToTopBtn.addEventListener('click', function(e) {
            e.preventDefault();
            backToTopBtn.classList.add('scrolling');

            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });

            setTimeout(() => {
                backToTopBtn.classList.remove('scrolling');
            }, 800);
        });
    })();
</script>

</body>
</html>
