@php
    $rawDescription = html_entity_decode((string) ($invoice->description ?: 'Cobrança de Serviços'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $descriptionHtml = preg_replace('/<\s*script[^>]*>.*?<\s*\/\s*script\s*>/is', '', $rawDescription);
    $descriptionHtml = preg_replace('/\bon\w+\s*=\s*["\'][^"\']*["\']/i', '', $descriptionHtml);
    $descriptionHtml = preg_replace('/\bon\w+\s*=\s*[^\s>]*/i', '', $descriptionHtml);
    $descriptionHtml = preg_replace('/javascript\s*:/i', '', $descriptionHtml);
    $descriptionHtml = strip_tags($descriptionHtml, '<p><br><strong><b><em><i><u><s><ul><ol><li><a><h1><h2><h3><h4><blockquote><table><thead><tbody><tr><th><td><span><div>');
    $descriptionHtml = preg_replace_callback('/<([a-z][a-z0-9]*)([^>]*)>/i', function ($matches) {
        $tag = strtolower($matches[1]);
        $attrs = $matches[2];
        if ($tag === 'a') {
            preg_match('/href\s*=\s*["\']([^"\']*)["\']/i', $attrs, $href);
            $cleanHref = isset($href[1]) ? ' href="' . e($href[1]) . '" target="_blank" rel="noopener noreferrer"' : '';
            return '<a' . $cleanHref . '>';
        }
        return '<' . $tag . '>';
    }, $descriptionHtml);
    $descriptionPlain = trim((string) \Illuminate\Support\Str::of(strip_tags($descriptionHtml))->squish());
    if ($descriptionPlain !== '' && $descriptionHtml === strip_tags($descriptionHtml)) {
        $descriptionHtml = nl2br(e($descriptionPlain));
    }
@endphp
<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Fatura #{{ $invoice->number ?? $invoice->id }} - {{ $companyName }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* ============================================================
           DESIGN SYSTEM — Tema Dark Consistente com Painel Admin
           ============================================================ */
        :root {
            --bg-main:       #020617;
            --bg-card:       #0b1020;
            --bg-card-sub:   #0f172a;
            --bg-inset:      #050a18;
            --border:        #1e293b;
            --border-subtle: rgba(255, 255, 255, 0.08);
            --primary:       #3b82f6;
            --primary-light: rgba(59, 130, 246, 0.15);
            --success:       #10b981;
            --success-light: rgba(16, 185, 129, 0.12);
            --warning:       #f59e0b;
            --warning-light: rgba(245, 158, 11, 0.14);
            --danger:        #ef4444;
            --danger-light:  rgba(239, 68, 68, 0.14);
            /* Textos — hierarquia nítida para alta legibilidade */
            --text-h:        #ffffff;
            --text-body:     #f1f5f9;
            --text-label:    #cbd5e1;
            --text-caption:  #94a3b8;
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            background: var(--bg-main);
            color: var(--text-body);
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
            font-size: 15px;
            line-height: 1.6;
            min-height: 100vh;
            padding-bottom: 60px;
        }

        /* ── Container ── */
        .checkout-wrap {
            max-width: 820px;
            margin: 0 auto;
            padding: 32px 16px;
        }

        /* ── Card principal ── */
        .checkout-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 20px;
            box-shadow: 0 24px 64px rgba(0, 0, 0, 0.65), 0 0 0 1px rgba(255,255,255,0.05) inset;
            overflow: hidden;
        }

        /* ── Header ── */
        .checkout-header {
            background: linear-gradient(135deg, #0e172e 0%, #070d1e 100%);
            border-bottom: 1px solid var(--border);
            padding: 24px 28px;
        }

        .company-name {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-h);
            margin: 0;
            line-height: 1.2;
        }

        .invoice-ref {
            font-size: 0.82rem;
            color: var(--text-caption);
            letter-spacing: 0.03em;
            margin: 0;
        }

        /* ── Status badges ── */
        .badge-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            border-radius: 30px;
            font-size: 0.82rem;
            font-weight: 600;
            letter-spacing: 0.02em;
        }
        .badge-status.pending  { background: var(--warning-light); color: #fbbf24; border: 1px solid rgba(245,158,11,0.35); }
        .badge-status.paid     { background: var(--success-light);  color: #34d399; border: 1px solid rgba(16,185,129,0.35); }
        .badge-status.overdue  { background: var(--danger-light);   color: #f87171; border: 1px solid rgba(239,68,68,0.35); }

        /* ── Body padding ── */
        .checkout-body { padding: 28px; }

        /* ── Section label ── */
        .section-label {
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--text-label);
            text-transform: uppercase;
            letter-spacing: 0.09em;
            margin-bottom: 12px;
        }

        /* ── Banner de crédito WHMCS ── */
        .credit-banner {
            background: linear-gradient(135deg, rgba(16,185,129,0.12) 0%, rgba(16,185,129,0.06) 100%);
            border: 1px solid rgba(16, 185, 129, 0.35);
            border-radius: 14px;
            padding: 18px 20px;
            margin-bottom: 24px;
        }

        .credit-banner-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.92rem;
            font-weight: 700;
            color: #34d399;
            margin-bottom: 14px;
        }

        .credit-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }

        .credit-grid-item {
            background: #0f172a;
            border: 1px solid #1e293b;
            border-radius: 10px;
            padding: 12px 14px;
        }

        .credit-grid-item .clabel {
            font-size: 0.74rem;
            font-weight: 700;
            color: var(--text-label);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            display: block;
            margin-bottom: 5px;
        }

        .credit-grid-item .cvalue {
            font-size: 1.15rem;
            font-weight: 800;
            line-height: 1.2;
        }

        .cvalue.original { color: var(--text-h); }
        .cvalue.deducted { color: #34d399; }
        .cvalue.pending  { color: #38bdf8; }

        /* Impede quebra do valor monetário nos itens do grid */
        .credit-grid-item .cvalue { white-space: nowrap; }

        .credit-late-row {
            margin-top: 14px;
            padding-top: 12px;
            border-top: 1px solid rgba(255,255,255,0.10);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            font-size: 0.85rem;
            color: var(--text-label);
            flex-wrap: nowrap;
        }

        .credit-late-row .clr-label {
            flex: 1 1 auto;
            min-width: 0;
        }

        .credit-late-row .clr-val {
            flex-shrink: 0;
            white-space: nowrap;
        }

        /* ── Info card (detalhes + valor) ── */
        .info-card {
            background: var(--bg-card-sub);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 20px;
            height: 100%;
        }

        .info-card .ic-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-label);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            display: block;
            margin-bottom: 8px;
        }

        .info-card .ic-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text-h);
            margin-bottom: 12px;
        }

        .info-card .ic-description {
            font-size: 0.95rem;
            color: var(--text-body);
            line-height: 1.6;
            margin-bottom: 14px;
        }

        .info-card .ic-description :is(p, ul, ol) {
            margin-bottom: 8px;
        }

        .info-card .ic-description :is(p, ul, ol):last-child {
            margin-bottom: 0;
        }

        .info-card .ic-description ul,
        .info-card .ic-description ol {
            padding-left: 20px;
            margin-top: 4px;
        }

        .info-card .ic-description li {
            margin-bottom: 4px;
        }

        .info-card .ic-description strong,
        .info-card .ic-description b {
            color: var(--text-h);
            font-weight: 700;
        }

        .info-card .ic-row {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.88rem;
            color: var(--text-body);
            margin-top: 6px;
        }

        .info-card .ic-row i {
            color: #38bdf8;
            font-size: 0.95rem;
            flex-shrink: 0;
        }

        .info-card .ic-row strong {
            color: var(--text-caption);
            font-weight: 600;
            margin-right: 4px;
        }

        .info-card .ic-row span {
            color: var(--text-h);
            font-weight: 600;
        }

        .amount-display {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 8px;
            margin-bottom: 6px;
        }

        .amount-display .ad-label {
            font-size: 0.82rem;
            color: var(--text-caption);
            font-weight: 600;
            flex-shrink: 1;
        }

        .amount-display .ad-value {
            font-size: 0.95rem;
            color: var(--text-h);
            font-weight: 700;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .amount-big {
            font-size: 2.1rem;
            font-weight: 800;
            color: #38bdf8;
            line-height: 1.1;
            text-shadow: 0 0 20px rgba(56, 189, 248, 0.20);
            white-space: nowrap;
        }

        .amount-due-label {
            font-size: 0.80rem;
            font-weight: 600;
            color: var(--text-label);
            margin-bottom: 4px;
        }

        /* ── Método de pagamento ── */
        .method-tab-btn {
            border: 2px solid var(--border);
            background: var(--bg-card-sub);
            color: var(--text-label);
            border-radius: 14px;
            padding: 16px 12px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.22s ease;
            cursor: pointer;
            text-align: center;
            width: 100%;
        }

        .method-tab-btn .method-icon {
            font-size: 1.8rem;
            display: block;
            margin-bottom: 6px;
        }

        .method-tab-btn .method-badge {
            display: inline-block;
            margin-top: 5px;
            font-size: 0.74rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 20px;
        }

        .method-badge.free  { background: rgba(16,185,129,0.18); color: #34d399; }
        .method-badge.fee   { background: rgba(59,130,246,0.18);  color: #93c5fd; }

        .method-tab-btn:hover {
            border-color: rgba(59,130,246,0.6);
            background: #13203c;
            color: var(--text-h);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.35);
        }

        .method-tab-btn.active {
            border-color: var(--primary);
            background: rgba(59, 130, 246, 0.15);
            color: #ffffff;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.25);
        }

        /* ── Painel PIX ── */
        .pix-panel {
            background: linear-gradient(135deg, rgba(16,185,129,0.08) 0%, rgba(16,185,129,0.02) 100%);
            border: 1px solid rgba(16,185,129,0.30);
            border-radius: 14px;
            padding: 28px 20px;
            text-align: center;
        }

        .pix-waiting {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.92rem;
            font-weight: 700;
            color: #34d399;
            margin-bottom: 20px;
        }

        .pulse-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--success);
            box-shadow: 0 0 0 0 rgba(16,185,129,0.7);
            animation: pulse-green 1.8s infinite;
            flex-shrink: 0;
        }

        @keyframes pulse-green {
            0%   { transform: scale(0.95); box-shadow: 0 0 0 0   rgba(16,185,129,0.7); }
            70%  { transform: scale(1);    box-shadow: 0 0 0 10px rgba(16,185,129,0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0   rgba(16,185,129,0); }
        }

        .copy-box-wrap { max-width: 520px; margin: 0 auto; }

        .copy-box-label {
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--text-label);
            text-transform: uppercase;
            letter-spacing: 0.07em;
            display: block;
            margin-bottom: 8px;
            text-align: left;
        }

        .copy-box {
            background: var(--bg-inset);
            border: 1px dashed rgba(56, 189, 248, 0.35);
            border-radius: 10px;
            padding: 14px 16px;
            font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
            word-break: break-all;
            color: #38bdf8;
            font-size: 0.86rem;
            max-height: 90px;
            overflow-y: auto;
            text-align: left;
            line-height: 1.5;
            margin-bottom: 12px;
        }

        .pix-tip {
            font-size: 0.84rem;
            color: var(--text-caption);
            margin-top: 16px;
        }

        /* ── Painel Cartão ── */
        .card-panel {
            background: linear-gradient(135deg, rgba(59,130,246,0.08) 0%, rgba(59,130,246,0.02) 100%);
            border: 1px solid rgba(59,130,246,0.30);
            border-radius: 14px;
            padding: 28px 20px;
        }

        .card-panel-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text-h);
            margin-bottom: 4px;
        }

        .card-panel-sub {
            font-size: 0.85rem;
            color: var(--text-caption);
            margin-bottom: 20px;
        }

        .fee-table {
            background: var(--bg-card-sub);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px 18px;
            max-width: 480px;
        }

        .fee-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 10px;
            font-size: 0.88rem;
            color: var(--text-body);
            margin-bottom: 8px;
        }

        .fee-row:last-child { margin-bottom: 0; }

        .fee-row .fr-label {
            color: var(--text-caption);
            font-weight: 500;
            flex: 1 1 auto;
            min-width: 0;
        }
        .fee-row .fr-val {
            font-weight: 600;
            color: var(--text-h);
            white-space: nowrap;
            flex-shrink: 0;
        }
        .fee-row .fr-warn  { color: #fbbf24; font-weight: 700; white-space: nowrap; }
        .fee-row.total-row {
            padding-top: 12px;
            border-top: 1px solid var(--border);
            margin-top: 8px;
        }
        .fee-row.total-row .fr-label { color: var(--text-h); font-weight: 700; font-size: 0.95rem; }
        .fee-row.total-row .fr-val   { color: #38bdf8; font-size: 1.45rem; font-weight: 800; white-space: nowrap; }

        /* ── Formulário de Cartão ── */
        .card-form-group {
            margin-bottom: 16px;
        }

        .card-form-group label {
            display: block;
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--text-label);
            text-transform: uppercase;
            letter-spacing: 0.07em;
            margin-bottom: 6px;
        }

        .card-form-group input,
        .card-form-group select {
            width: 100%;
            background: var(--bg-inset);
            border: 1.5px solid var(--border);
            border-radius: 10px;
            padding: 12px 14px;
            color: var(--text-h);
            font-size: 0.95rem;
            font-family: 'Inter', sans-serif;
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
            appearance: none;
            -webkit-appearance: none;
        }

        .card-form-group input::placeholder {
            color: var(--text-caption);
            opacity: 0.7;
        }

        .card-form-group input:focus,
        .card-form-group select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.20);
        }

        .card-form-group input.error,
        .card-form-group select.error {
            border-color: var(--danger);
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.18);
        }

        .card-form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .card-brand-display {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.82rem;
            color: var(--text-caption);
            margin-top: 6px;
            height: 20px;
        }

        .card-brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 700;
            background: rgba(59, 130, 246, 0.18);
            color: #93c5fd;
        }

        .card-secure-row {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.78rem;
            color: var(--text-caption);
            margin-top: 8px;
            justify-content: center;
        }

        .card-secure-row i { color: #34d399; }

        select option {
            background: #0b1120;
            color: var(--text-h);
        }

        @media (max-width: 540px) {
            .card-form-row {
                grid-template-columns: 1fr;
            }
        }

        /* ── Rodapé ── */
        .checkout-footer {
            background: #070d1e;
            border-top: 1px solid var(--border);
            padding: 18px 28px;
        }

        .footer-contact {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px;
            font-size: 0.85rem;
            color: var(--text-caption);
        }

        .footer-contact span { display: inline-flex; align-items: center; gap: 6px; }
        .footer-contact strong { color: var(--text-label); font-weight: 600; }

        /* ── Paid state ── */
        .paid-icon { font-size: 72px; color: #34d399; margin-bottom: 16px; display: block; }
        .paid-summary {
            background: var(--bg-card-sub);
            border: 1px solid var(--border-subtle);
            border-radius: 14px;
            padding: 18px 24px;
            max-width: 400px;
            margin: 0 auto 20px;
        }
        .paid-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.87rem;
            margin-bottom: 8px;
            gap: 12px;
        }
        .paid-row:last-child { margin-bottom: 0; }
        .paid-row .pr-label { color: var(--text-label); }
        .paid-row .pr-val   { color: var(--text-h); font-weight: 600; }
        .paid-row .pr-val.success { color: #34d399; font-size: 1rem; font-weight: 700; }

        /* ── Botões ── */
        .btn-pix {
            background: linear-gradient(135deg, #059669, #10b981);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 12px 20px;
            font-size: 0.95rem;
            font-weight: 700;
            width: 100%;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 15px rgba(16,185,129,0.30);
        }
        .btn-pix:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(16,185,129,0.40); }

        .btn-card {
            background: linear-gradient(135deg, #1d4ed8, #3b82f6);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 14px 20px;
            font-size: 0.97rem;
            font-weight: 700;
            width: 100%;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 15px rgba(59,130,246,0.30);
        }
        .btn-card:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(59,130,246,0.40); }
        .btn-card:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

        /* ── Overdue date ── */
        .date-overdue { color: #f87171 !important; }

        /* ── Responsive Mobile ── */
        .company-logo {
            max-height: 42px;
            max-width: 140px;
            border-radius: 8px;
            object-fit: contain;
        }

        .company-logo-placeholder {
            width: 42px;
            height: 42px;
            background: rgba(59,130,246,0.15);
            border: 1px solid rgba(59,130,246,0.3);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            color: #60a5fa;
            font-size: 1.2rem;
        }

        /* ═══ MOBILE ≤ 768px ═══ */
        @media (max-width: 768px) {
            body { padding-bottom: 30px; }

            .checkout-wrap { padding: 14px 10px; }
            .checkout-card { border-radius: 16px; }

            /* Header */
            .checkout-header {
                padding: 16px 14px;
                flex-direction: column;
                align-items: stretch !important;
                gap: 12px !important;
            }
            .header-badge-wrap { display: flex; width: 100%; }
            .badge-status {
                width: 100%;
                justify-content: center;
                padding: 9px 14px;
                font-size: 0.88rem;
            }

            /* Body */
            .checkout-body { padding: 16px 12px; }

            /* Banner de crédito */
            .credit-banner { padding: 14px 12px; margin-bottom: 18px; }
            .credit-banner-title { font-size: 0.88rem; }
            .credit-grid { grid-template-columns: 1fr; gap: 8px; }
            .credit-grid-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 10px 12px;
            }
            .credit-grid-item .clabel { margin-bottom: 0; font-size: 0.74rem; flex: 1 1 auto; }
            .credit-grid-item .cvalue { font-size: 1.0rem; white-space: nowrap; flex-shrink: 0; }
            /* Linha de multa/juros — label encurta, valor não quebra */
            .credit-late-row {
                flex-wrap: nowrap;
                gap: 10px;
                font-size: 0.80rem;
                padding-top: 10px;
                margin-top: 10px;
            }
            .credit-late-row .clr-label { flex: 1 1 auto; min-width: 0; }
            .credit-late-row .clr-val   { flex-shrink: 0; white-space: nowrap; font-size: 0.88rem; }

            /* Info cards */
            .info-card { padding: 14px 12px; }
            .info-card .ic-row { font-size: 0.84rem; }

            /* Valor a pagar */
            .amount-display { gap: 6px; }
            .amount-display .ad-value { white-space: nowrap; }
            .amount-big { font-size: 1.65rem; }
            .amount-due-label { font-size: 0.76rem; }

            /* Tabs de método */
            .method-tab-btn {
                padding: 11px 6px;
                font-size: 0.82rem;
                border-radius: 12px;
            }
            .method-tab-btn .method-icon { font-size: 1.4rem; margin-bottom: 3px; }
            .method-tab-btn .method-badge { font-size: 0.66rem; padding: 2px 6px; margin-top: 3px; }

            /* Painéis */
            .pix-panel, .card-panel { padding: 18px 12px; border-radius: 12px; }
            .card-panel-title { font-size: 0.98rem; }
            .card-panel-sub { font-size: 0.82rem; }

            /* Cópia Pix */
            .copy-box { font-size: 0.76rem; padding: 10px 10px; max-height: 80px; }

            /* Botões */
            .btn-pix, .btn-card {
                padding: 14px 12px;
                font-size: 0.92rem;
                min-height: 48px;
                max-width: 100%;
            }

            /* Tabela de taxas — label encurta, valor protegido */
            .fee-table { padding: 12px; }
            .fee-row {
                font-size: 0.81rem;
                gap: 8px;
                flex-wrap: nowrap;
            }
            .fee-row .fr-label {
                flex: 1 1 auto;
                min-width: 0;
                word-break: break-word;
            }
            .fee-row .fr-val,
            .fee-row .fr-warn { white-space: nowrap; flex-shrink: 0; }
            .fee-row.total-row .fr-label { font-size: 0.88rem; }
            .fee-row.total-row .fr-val   { font-size: 1.18rem; }

            /* Formulário de cartão */
            .card-form-group { margin-bottom: 14px; }
            .card-form-group input,
            .card-form-group select { font-size: 0.92rem; padding: 11px 12px; }
            .card-form-group label { font-size: 0.74rem; }

            /* Rodapé */
            .checkout-footer { padding: 14px 12px; }
            .footer-contact {
                flex-direction: column;
                align-items: center;
                gap: 8px;
                text-align: center;
                font-size: 0.82rem;
            }
        }

        /* ═══ MOBILE PEQUENO ≤ 480px ═══ */
        @media (max-width: 480px) {
            .checkout-wrap { padding: 10px 6px; }
            .checkout-body { padding: 14px 10px; }
            .checkout-header { padding: 14px 12px; }

            .company-name { font-size: 0.96rem; }
            .invoice-ref  { font-size: 0.78rem; }

            .amount-big { font-size: 1.45rem; }

            /* Em telas bem pequenas, tabs empilham verticalmente */
            .method-tab-btn { font-size: 0.80rem; }
            .method-tab-btn .method-icon { font-size: 1.25rem; }

            /* Formulário de cartão: 1 coluna */
            .card-form-row { grid-template-columns: 1fr; gap: 0; }

            .fee-row.total-row .fr-val { font-size: 1.05rem; }

            #qr-img, #dynamic-qr-img { max-width: 170px !important; }
        }
    </style>
</head>
<body>

<div class="checkout-wrap">
    <div class="checkout-card">

        {{-- ════════════ HEADER ════════════ --}}
        <div class="checkout-header d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-3">
                @if($companyLogo)
                    <img src="{{ $companyLogo }}" alt="{{ $companyName }}" class="company-logo">
                @else
                    <div class="company-logo-placeholder">
                        <i class="bi bi-building"></i>
                    </div>
                @endif
                <div>
                    <p class="company-name">{{ $companyName }}</p>
                    <p class="invoice-ref"><i class="bi bi-receipt me-1"></i>Fatura #{{ $invoice->number ?? $invoice->id }}</p>
                </div>
            </div>

            <div class="header-badge-wrap">
                @if($isPaid)
                    <span class="badge-status paid"><i class="bi bi-check-circle-fill"></i>Fatura Paga</span>
                @elseif($isOverdue)
                    <span class="badge-status overdue"><i class="bi bi-exclamation-triangle-fill"></i>Vencida</span>
                @else
                    <span class="badge-status pending"><i class="bi bi-clock-fill"></i>Aguardando Pagamento</span>
                @endif
            </div>
        </div>

        {{-- ════════════ BODY ════════════ --}}
        <div class="checkout-body">

            {{-- Paga --}}
            @if($isPaid)
                <div class="text-center py-4">
                    <i class="bi bi-check-circle-fill paid-icon"></i>
                    <h3 style="color:var(--text-h);font-weight:800;margin-bottom:8px;">Pagamento Confirmado!</h3>
                    <p style="color:var(--text-label);margin-bottom:24px;">Esta fatura já foi quitada com sucesso.</p>
                    <div class="paid-summary">
                        <div class="paid-row">
                            <span class="pr-label">Valor Pago:</span>
                            <span class="pr-val success">R$ {{ number_format($totalPix, 2, ',', '.') }}</span>
                        </div>
                        <div class="paid-row">
                            <span class="pr-label">Data da Baixa:</span>
                            <span class="pr-val">{{ $invoice->paid_at?->format('d/m/Y H:i') ?? '—' }}</span>
                        </div>
                        <div class="paid-row">
                            <span class="pr-label">Cliente:</span>
                            <span class="pr-val">{{ $customerName }}</span>
                        </div>
                    </div>
                    <a href="javascript:window.print()" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-printer me-1"></i> Imprimir Comprovante
                    </a>
                </div>

            @else

                {{-- ── Banner Crédito WHMCS ── --}}
                @if($whmcsCreditApplied > 0)
                <div class="credit-banner">
                    <div class="credit-banner-title">
                        <i class="bi bi-patch-check-fill"></i>
                        Crédito Aplicado nesta Fatura
                    </div>
                    <div class="credit-grid">
                        @if($whmcsSubtotal > 0)
                        <div class="credit-grid-item">
                            <span class="clabel">Valor Original</span>
                            <span class="cvalue original">R$ {{ number_format($whmcsSubtotal, 2, ',', '.') }}</span>
                        </div>
                        @endif
                        <div class="credit-grid-item">
                            <span class="clabel">Crédito Deduzido</span>
                            <span class="cvalue deducted">− R$ {{ number_format($whmcsCreditApplied, 2, ',', '.') }}</span>
                        </div>
                        <div class="credit-grid-item">
                            <span class="clabel">Saldo Pendente</span>
                            <span class="cvalue pending">R$ {{ number_format($baseAmount, 2, ',', '.') }}</span>
                        </div>
                    </div>
                    @if($multa > 0 || $juros > 0)
                    <div class="credit-late-row">
                        <span class="clr-label">+ Multa e juros por atraso sobre o saldo</span>
                        <span class="clr-val" style="color:#fbbf24;font-weight:700;">R$ {{ number_format($multa + $juros, 2, ',', '.') }}</span>
                    </div>
                    @endif
                </div>
                @endif

                {{-- ── Resumo da Cobrança ── --}}
                <div class="row g-3 mb-4">
                    <div class="col-md-7">
                        <div class="info-card">
                            <span class="ic-label"><i class="bi bi-file-text me-1"></i>Detalhes do Serviço</span>
                            <div class="ic-description">
                                {!! $descriptionHtml ?: '<span class="text-secondary">Cobrança de Serviços</span>' !!}
                            </div>
                            <div class="ic-row">
                                <i class="bi bi-person-circle"></i>
                                <strong>Cliente:</strong>
                                <span>{{ $customerName }}</span>
                            </div>
                            @if($customerDoc)
                            <div class="ic-row">
                                <i class="bi bi-card-heading"></i>
                                <strong>Documento:</strong>
                                <span>{{ $customerDoc }}</span>
                            </div>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="info-card d-flex flex-column justify-content-center">
                            <div class="amount-display">
                                <span class="ad-label">Vencimento</span>
                                <span class="ad-value {{ $isOverdue ? 'date-overdue' : '' }}">
                                    {{ $invoice->due_date?->format('d/m/Y') ?? 'À vista' }}
                                </span>
                            </div>
                            <div style="margin-top:12px;">
                                <div class="amount-due-label">Valor a Pagar</div>
                                <div class="amount-big">R$ {{ number_format($totalPix, 2, ',', '.') }}</div>
                            </div>
                            @if($multa > 0 || $juros > 0)
                            <div style="margin-top:8px;font-size:0.78rem;color:#f87171;display:flex;align-items:center;gap:5px;">
                                <i class="bi bi-info-circle-fill"></i>
                                <span>Inclui juros/multa por atraso</span>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- ── Escolha de Pagamento ── --}}
                <div class="mb-4">
                    <div class="section-label"><i class="bi bi-credit-card me-1"></i>Escolha a Forma de Pagamento</div>
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="method-tab-btn active" id="tab-btn-pix" onclick="selectPaymentMethod('pix')">
                                <i class="bi bi-qr-code method-icon" style="color:#34d399;"></i>
                                <div>PIX Instantâneo</div>
                                <span class="method-badge free">Sem taxas extras</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="method-tab-btn" id="tab-btn-card" onclick="selectPaymentMethod('card')">
                                <i class="bi bi-credit-card method-icon" style="color:#60a5fa;"></i>
                                <div>Cartão de Crédito</div>
                                @if($feePercent > 0)
                                    <span class="method-badge fee">+{{ $feePercent }}% de taxa</span>
                                @else
                                    <span class="method-badge fee">Crédito online</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ── Painel PIX ── --}}
                <div id="panel-pix" class="payment-panel pix-panel">
                    <div class="pix-waiting">
                        <span class="pulse-dot"></span>
                        Aguardando pagamento via Pix...
                    </div>

                    <div class="mb-3" id="pix-qr-container">
                        @if($pixQrImage)
                            @php
                                $qrSrc = $pixQrImage;
                                if ($qrSrc && !str_starts_with($qrSrc, 'http') && !str_starts_with($qrSrc, 'data:')) {
                                    $qrSrc = 'data:image/png;base64,' . $qrSrc;
                                }
                            @endphp
                            <img src="{{ $qrSrc }}" alt="QR Code Pix" id="qr-img"
                                 style="max-width:220px;background:#fff;padding:10px;border-radius:14px;box-shadow:0 4px 20px rgba(0,0,0,0.4);">
                        @else
                            <div style="padding: 20px 0;">
                                <div class="spinner-border" role="status" id="pix-loading-spinner"
                                     style="color:var(--success);width:2.5rem;height:2.5rem;">
                                    <span class="visually-hidden">Gerando Pix...</span>
                                </div>
                            </div>
                            <img src="" id="dynamic-qr-img" alt="QR Code Pix" class="d-none"
                                 style="max-width:220px;background:#fff;padding:10px;border-radius:14px;box-shadow:0 4px 20px rgba(0,0,0,0.4);margin:0 auto;">
                        @endif
                    </div>

                    <div class="copy-box-wrap">
                        <span class="copy-box-label">Código Pix — Copia e Cola</span>
                        <div class="copy-box" id="pix-copia-cola-text">{{ $pixPayload ?: 'Gerando chave de pagamento...' }}</div>
                        <button type="button" class="btn-pix" id="btn-copy-pix" onclick="copyPixCode()">
                            <i class="bi bi-clipboard-check me-2"></i>Copiar Código Pix
                        </button>
                    </div>

                    <div class="pix-tip">
                        <i class="bi bi-shield-check" style="color:var(--success);margin-right:4px;"></i>
                        Abra o app do seu banco, escolha <strong style="color:var(--text-body);">Pix Copia e Cola</strong> ou <strong style="color:var(--text-body);">Pagar com QR Code</strong>.
                    </div>
                </div>

                {{-- ── Painel Cartão ── --}}
                <div id="panel-card" class="payment-panel card-panel d-none">
                    <div class="card-panel-title">
                        <i class="bi bi-credit-card me-2" style="color:#60a5fa;"></i>Pagamento com Cartão de Crédito
                    </div>
                    <div class="card-panel-sub">Preencha os dados do seu cartão abaixo. O pagamento é processado de forma segura sem sair desta página.</div>

                    {{-- Resumo de Valores --}}
                    <div class="fee-table mb-4">
                        <div class="fee-row">
                            <span class="fr-label">Subtotal da Fatura:</span>
                            <span class="fr-val">R$ {{ number_format($totalPix, 2, ',', '.') }}</span>
                        </div>
                        @if($cardFeeAmount > 0)
                        <div class="fee-row">
                            <span class="fr-label">Taxa de Processamento ({{ $feePercent }}%):</span>
                            <span class="fr-val fr-warn">+ R$ {{ number_format($cardFeeAmount, 2, ',', '.') }}</span>
                        </div>
                        @endif
                        <div class="fee-row total-row">
                            <span class="fr-label">Total a Pagar no Cartão:</span>
                            <span class="fr-val">R$ {{ number_format($totalCard, 2, ',', '.') }}</span>
                        </div>
                    </div>

                    {{-- Formulário Transparente --}}
                    <form id="card-form" onsubmit="return false;" autocomplete="off" novalidate>

                        {{-- Número do Cartão --}}
                        <div class="card-form-group">
                            <label for="card_number"><i class="bi bi-credit-card me-1"></i>Número do Cartão</label>
                            <input type="text" id="card_number" name="card_number"
                                   inputmode="numeric" maxlength="19"
                                   placeholder="0000  0000  0000  0000"
                                   autocomplete="cc-number">
                            <div class="card-brand-display" id="card-brand-display">
                                <i class="bi bi-shield-lock" style="color:#94a3b8;"></i>
                                <span id="card-brand-text" style="color:var(--text-caption);">Visa, Mastercard, Elo, Hipercard, Amex</span>
                            </div>
                        </div>

                        {{-- Nome do Titular --}}
                        <div class="card-form-group">
                            <label for="cardholder_name"><i class="bi bi-person me-1"></i>Nome do Titular (como no cartão)</label>
                            <input type="text" id="cardholder_name" name="cardholder_name"
                                   placeholder="NOME SOBRENOME"
                                   maxlength="80"
                                   autocomplete="cc-name"
                                   style="text-transform:uppercase;">
                        </div>

                        {{-- Validade e CVV --}}
                        <div class="card-form-row">
                            <div class="card-form-group">
                                <label for="expiration"><i class="bi bi-calendar3 me-1"></i>Validade</label>
                                <input type="text" id="expiration" name="expiration"
                                       inputmode="numeric" maxlength="5"
                                       placeholder="MM/AA"
                                       autocomplete="cc-exp">
                            </div>
                            <div class="card-form-group">
                                <label for="security_code"><i class="bi bi-lock me-1"></i>CVV</label>
                                <input type="text" id="security_code" name="security_code"
                                       inputmode="numeric" maxlength="4"
                                       placeholder="000"
                                       autocomplete="cc-csc">
                            </div>
                        </div>

                        {{-- CPF/CNPJ do Titular --}}
                        <div class="card-form-row">
                            <div class="card-form-group">
                                <label for="doc_type"><i class="bi bi-card-heading me-1"></i>Tipo de Documento</label>
                                <select id="doc_type" name="doc_type">
                                    <option value="CPF">CPF</option>
                                    <option value="CNPJ">CNPJ</option>
                                </select>
                            </div>
                            <div class="card-form-group">
                                <label for="doc_number"><i class="bi bi-hash me-1"></i>Número do Documento</label>
                                <input type="text" id="doc_number" name="doc_number"
                                       inputmode="numeric" maxlength="18"
                                       placeholder="000.000.000-00">
                            </div>
                        </div>

                        {{-- Parcelas --}}
                        <div class="card-form-group">
                            <label for="installments"><i class="bi bi-list-ol me-1"></i>Parcelas</label>
                            <select id="installments" name="installments">
                                <option value="1">1x de R$ {{ number_format($totalCard, 2, ',', '.') }} (sem juros)</option>
                            </select>
                        </div>

                        {{-- Botão Pagar --}}
                        <div class="mt-4">
                            <button type="button" class="btn-card" id="btn-pay-card" onclick="startCardPayment()">
                                <i class="bi bi-lock-fill me-2"></i>
                                Pagar R$ {{ number_format($totalCard, 2, ',', '.') }} com Cartão
                            </button>
                            <div class="card-secure-row mt-3">
                                <i class="bi bi-shield-check"></i>
                                <span>Pagamento 100% seguro e criptografado &bull; Sem redirecionamento</span>
                            </div>
                        </div>

                    </form>
                </div>

            @endif
        </div>

        {{-- ════════════ FOOTER ════════════ --}}
        <div class="checkout-footer">
            <div class="footer-contact">
                @if($companyWhatsapp)
                    <span><i class="bi bi-whatsapp" style="color:#25d366;"></i><strong>WhatsApp:</strong> {{ \App\Support\Format::phone($companyWhatsapp) }}</span>
                @endif
                @if($companyPhone)
                    <span><i class="bi bi-telephone" style="color:#60a5fa;"></i><strong>Telefone:</strong> {{ \App\Support\Format::phone($companyPhone) }}</span>
                @endif
                @if($companyEmail)
                    <span><i class="bi bi-envelope" style="color:#67e8f9;"></i><strong>E-mail:</strong> {{ $companyEmail }}</span>
                @endif
            </div>
        </div>

    </div>{{-- /checkout-card --}}
</div>{{-- /checkout-wrap --}}

<script>
const token = '{{ $token }}';
const csrf  = document.querySelector('meta[name="csrf-token"]').content;
let pollingInterval = null;

// Toast SweetAlert2
const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 2500,
    timerProgressBar: true
});

// ── Alternar painel de pagamento ──
function selectPaymentMethod(method) {
    const btnPix  = document.getElementById('tab-btn-pix');
    const btnCard = document.getElementById('tab-btn-card');
    const pnlPix  = document.getElementById('panel-pix');
    const pnlCard = document.getElementById('panel-card');

    if (method === 'pix') {
        btnPix.classList.add('active');
        btnCard.classList.remove('active');
        pnlPix.classList.remove('d-none');
        pnlCard.classList.add('d-none');
    } else {
        btnCard.classList.add('active');
        btnPix.classList.remove('active');
        pnlCard.classList.remove('d-none');
        pnlPix.classList.add('d-none');
    }
}

// ── Copiar Pix ──
function copyPixCode() {
    const textEl = document.getElementById('pix-copia-cola-text');
    const code   = textEl.textContent.trim();
    if (!code || code.includes('Gerando')) {
        Toast.fire({ icon: 'warning', title: 'Aguarde o Pix ser gerado' });
        return;
    }
    navigator.clipboard.writeText(code).then(() => {
        Toast.fire({ icon: 'success', title: '✅ Código Pix copiado!' });
    }).catch(() => {
        const ta = document.createElement('textarea');
        ta.value = code;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        Toast.fire({ icon: 'success', title: '✅ Código Pix copiado!' });
    });
}

// ── Máscaras de Cartão ──
function maskCardNumber(input) {
    let val = input.value.replace(/\D/g, '').substring(0, 16);
    val = val.replace(/(\d{4})(?=\d)/g, '$1  ');
    input.value = val;
    detectCardBrand(val.replace(/\s/g, ''));
}

function maskExpiration(input) {
    let val = input.value.replace(/\D/g, '').substring(0, 4);
    if (val.length >= 3) val = val.substring(0, 2) + '/' + val.substring(2);
    input.value = val;
}

function maskDocument(input) {
    const type = document.getElementById('doc_type').value;
    let val = input.value.replace(/\D/g, '');
    if (type === 'CNPJ') {
        val = val.substring(0, 14);
        val = val.replace(/(\d{2})(\d)/, '$1.$2')
                 .replace(/(\d{3})(\d)/, '$1.$2')
                 .replace(/(\d{3})(\d)/, '$1/$2')
                 .replace(/(\d{4})(\d)/, '$1-$2');
    } else {
        val = val.substring(0, 11);
        val = val.replace(/(\d{3})(\d)/, '$1.$2')
                 .replace(/(\d{3})(\d)/, '$1.$2')
                 .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
    }
    input.value = val;
}

function detectCardBrand(num) {
    const brandEl = document.getElementById('card-brand-text');
    if (!brandEl) return;
    let brand = '';
    if (/^4/.test(num)) brand = '💳 Visa';
    else if (/^(5[1-5]|2[2-7])/.test(num)) brand = '💳 Mastercard';
    else if (/^(4011|438935|451416|4576|504175|506699|5067|5090|627780|636297|636368)/.test(num)) brand = '💳 Elo';
    else if (/^3[47]/.test(num)) brand = '💳 American Express';
    else if (/^(606282|3841)/.test(num)) brand = '💳 Hipercard';

    if (brand) {
        brandEl.innerHTML = `<span class="card-brand-badge"><i class="bi bi-check-circle-fill"></i>${brand}</span>`;
    } else {
        brandEl.textContent = 'Visa, Mastercard, Elo, Hipercard, Amex';
        brandEl.style.color = 'var(--text-caption)';
    }
}

// Inicializa os event listeners das máscaras
document.addEventListener('DOMContentLoaded', () => {
    const cardNum = document.getElementById('card_number');
    const expiry  = document.getElementById('expiration');
    const docNum  = document.getElementById('doc_number');
    const docType = document.getElementById('doc_type');
    const cvv     = document.getElementById('security_code');
    const holder  = document.getElementById('cardholder_name');

    if (cardNum) {
        cardNum.addEventListener('input', () => maskCardNumber(cardNum));
        cardNum.addEventListener('keypress', e => { if (!/\d/.test(e.key) && e.key !== 'Backspace') e.preventDefault(); });
    }
    if (expiry) expiry.addEventListener('input', () => maskExpiration(expiry));
    if (docNum) docNum.addEventListener('input', () => maskDocument(docNum));
    if (docType) docType.addEventListener('change', () => maskDocument(docNum));
    if (cvv) cvv.addEventListener('keypress', e => { if (!/\d/.test(e.key) && e.key !== 'Backspace') e.preventDefault(); });
    if (holder) holder.addEventListener('input', () => { holder.value = holder.value.toUpperCase(); });
});

// ── Pagamento com Cartão (Checkout Transparente) ──
async function startCardPayment() {
    const btn = document.getElementById('btn-pay-card');

    // Coleta dados do formulário
    const cardNumRaw    = (document.getElementById('card_number')?.value || '').replace(/\s/g, '');
    const holderName    = (document.getElementById('cardholder_name')?.value || '').trim();
    const expiryVal     = (document.getElementById('expiration')?.value || '').trim();
    const cvv           = (document.getElementById('security_code')?.value || '').trim();
    const docType       = (document.getElementById('doc_type')?.value || 'CPF');
    const docNumRaw     = (document.getElementById('doc_number')?.value || '').replace(/\D/g, '');
    const installments  = parseInt(document.getElementById('installments')?.value || '1', 10);

    // Parse validade MM/AA
    const expiryParts    = expiryVal.split('/');
    const expMonth       = parseInt(expiryParts[0] || '0', 10);
    const expYearShort   = parseInt(expiryParts[1] || '0', 10);
    const expYear        = expYearShort < 100 ? 2000 + expYearShort : expYearShort;

    // Validação local básica
    const clearError = id => document.getElementById(id)?.classList.remove('error');
    const setError   = (id, msg) => {
        document.getElementById(id)?.classList.add('error');
        Toast.fire({ icon: 'warning', title: msg });
    };
    ['card_number','cardholder_name','expiration','security_code','doc_number'].forEach(clearError);

    if (cardNumRaw.length < 13 || cardNumRaw.length > 19) { setError('card_number', 'Número do cartão inválido.'); return; }
    if (!holderName)  { setError('cardholder_name', 'Informe o nome do titular.'); return; }
    if (expMonth < 1 || expMonth > 12 || expYear < new Date().getFullYear()) { setError('expiration', 'Data de validade inválida.'); return; }
    if (cvv.length < 3 || cvv.length > 4) { setError('security_code', 'CVV inválido.'); return; }
    if (!docNumRaw) { setError('doc_number', 'Informe o CPF/CNPJ do titular.'); return; }

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processando pagamento...';

    try {
        const response = await fetch(`{{ url('/fatura') }}/${token}/cartao`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                card_number:        cardNumRaw,
                cardholder_name:    holderName,
                expiration_month:   expMonth,
                expiration_year:    expYear,
                security_code:      cvv,
                doc_type:           docType,
                doc_number:         docNumRaw,
                installments:       installments
            })
        });

        const data = await response.json();

        if (response.ok && data.ok) {
            if (data.status === 'approved') {
                Swal.fire({
                    icon: 'success',
                    title: '🎉 Pagamento Aprovado!',
                    text: 'Seu pagamento foi processado com sucesso.',
                    timer: 2500,
                    showConfirmButton: false,
                    background: '#0a1628',
                    color: '#e2e8f0'
                }).then(() => window.location.reload());
            } else {
                // Pagamento em análise
                Swal.fire({
                    icon: 'info',
                    title: '⏳ Pagamento em Análise',
                    text: data.message || 'Seu pagamento está sendo analisado. Você será notificado em breve.',
                    confirmButtonColor: '#3b82f6',
                    background: '#0a1628',
                    color: '#e2e8f0'
                });
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-lock-fill me-2"></i>Pagar com Cartão';
            }
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Pagamento Recusado',
                text: data.error || 'Não foi possível processar o cartão. Verifique os dados e tente novamente.',
                confirmButtonColor: '#3b82f6',
                background: '#0a1628',
                color: '#e2e8f0'
            });
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-lock-fill me-2"></i>Pagar com Cartão';
        }
    } catch (e) {
        Swal.fire({
            icon: 'error',
            title: 'Erro de Conexão',
            text: 'Falha ao conectar ao servidor. Verifique sua internet e tente novamente.',
            confirmButtonColor: '#3b82f6',
            background: '#0a1628',
            color: '#e2e8f0'
        });
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-lock-fill me-2"></i>Pagar com Cartão';
    }
}

// ── Gerar PIX sob demanda ──
let pixRetries = 0;
const PIX_MAX_RETRIES = 4;

async function ensurePixLoaded() {
    const textEl = document.getElementById('pix-copia-cola-text');
    if (!textEl) return;

    const currentText = textEl.textContent.trim();
    if (currentText && !currentText.includes('Gerando') && !currentText.includes('Erro') && !currentText.includes('Reconectando') && !currentText.includes('Tentando')) return;

    try {
        const res  = await fetch(`{{ url('/fatura') }}/${token}/pix`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
        });
        const data = await res.json();

        if (data.ok && data.pix_copia_cola) {
            textEl.textContent = data.pix_copia_cola;
            document.getElementById('pix-loading-spinner')?.classList.add('d-none');
            const qrImg = document.getElementById('dynamic-qr-img') || document.getElementById('qr-img');
            if (qrImg) {
                let qrSrc = data.qrcode_url || '';
                if (qrSrc && !qrSrc.startsWith('http') && !qrSrc.startsWith('data:')) qrSrc = 'data:image/png;base64,' + qrSrc;
                if (!qrSrc) qrSrc = `https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${encodeURIComponent(data.pix_copia_cola)}`;
                qrImg.src = qrSrc;
                qrImg.classList.remove('d-none');
            }
            pixRetries = 0;
        } else if (data.error) {
            pixRetries++;
            if (pixRetries < PIX_MAX_RETRIES) {
                const delay = pixRetries * 3000;
                textEl.textContent = `Tentando novamente em ${delay/1000}s... (${pixRetries}/${PIX_MAX_RETRIES})`;
                setTimeout(ensurePixLoaded, delay);
            } else {
                textEl.textContent = 'Não foi possível gerar o código Pix.';
                document.getElementById('pix-loading-spinner')?.classList.add('d-none');
                const retryBtn = document.createElement('button');
                retryBtn.className = 'btn-pix mt-3';
                retryBtn.style.cssText = 'max-width:260px;margin:0 auto;';
                retryBtn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i>Tentar novamente';
                retryBtn.onclick = () => { pixRetries = 0; textEl.textContent = 'Gerando chave de pagamento...'; ensurePixLoaded(); retryBtn.remove(); };
                textEl.parentNode.insertBefore(retryBtn, textEl.nextSibling);
                Toast.fire({ icon: 'error', title: data.error || 'Falha ao gerar Pix' });
            }
        } else {
            pixRetries++;
            if (pixRetries < PIX_MAX_RETRIES) setTimeout(ensurePixLoaded, 3000);
            else {
                textEl.textContent = 'Não foi possível obter o código Pix.';
                document.getElementById('pix-loading-spinner')?.classList.add('d-none');
            }
        }
    } catch (e) {
        pixRetries++;
        if (pixRetries < PIX_MAX_RETRIES) {
            textEl.textContent = `Reconectando... (tentativa ${pixRetries}/${PIX_MAX_RETRIES})`;
            setTimeout(ensurePixLoaded, 4000);
        } else {
            textEl.textContent = 'Erro de conexão. Recarregue a página.';
            document.getElementById('pix-loading-spinner')?.classList.add('d-none');
        }
    }
}

// ── Polling de status (retorno automático) ──
function startStatusPolling() {
    @if(!$isPaid)
        pollingInterval = setInterval(async () => {
            try {
                const res  = await fetch(`{{ url('/fatura') }}/${token}/status`, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.ok && data.status === 'paid') {
                    clearInterval(pollingInterval);
                    Swal.fire({
                        icon: 'success',
                        title: '🎉 Pagamento Confirmado!',
                        text: 'Seu pagamento foi aprovado com sucesso.',
                        timer: 2200,
                        showConfirmButton: false,
                        background: '#0f1b38',
                        color: '#e2e8f0'
                    }).then(() => window.location.reload());
                }
            } catch (e) {}
        }, 3500);
    @endif
}

// O listener DOMContentLoaded das máscaras já está acima (após detectCardBrand)
// Aqui apenas inicializamos o PIX e o polling
document.addEventListener('DOMContentLoaded', () => {
    ensurePixLoaded();
    startStatusPolling();
});
</script>

</body>
</html>
