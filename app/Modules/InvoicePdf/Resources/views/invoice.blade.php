@php
    $settings = app(\App\Modules\Settings\Services\SettingsManager::class);
    $appName = $settings->get('app.name', config('app.name'));
    $appLogo = $settings->get('app.logo');
    $primary = $settings->get('theme.primary', '#0d6efd');
    $secondary = $settings->get('theme.secondary', '#6610f2');
    $rawDescription = html_entity_decode((string) $invoice->description, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $descriptionHtml = preg_replace('/<\s*script[^>]*>.*?<\s*\/\s*script\s*>/is', '', $rawDescription);
    $descriptionHtml = preg_replace('/\bon\w+\s*=\s*["\'][^"\']*["\']/i', '', $descriptionHtml);
    $descriptionHtml = preg_replace('/\bon\w+\s*=\s*[^\s>]*/i', '', $descriptionHtml);
    $descriptionHtml = preg_replace('/javascript\s*:/i', '', $descriptionHtml);
    $descriptionHtml = strip_tags($descriptionHtml, '<p><br><strong><b><em><i><u><s><ul><ol><li><blockquote><span><div>');
    $descriptionHtml = preg_replace('/<([a-z][a-z0-9]*)\b[^>]*>/i', '<$1>', $descriptionHtml);
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Fatura {{ $invoice->number }}</title>
<style>
* { box-sizing: border-box; }
body { font-family: DejaVu Sans, Arial, sans-serif; margin: 0; padding: 28px; color: #1f2937; font-size: 14px; background: #f8fafc; }
.sheet { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 28px; }
.header { display: table; width: 100%; border-bottom: 2px solid {{ $primary }}; padding-bottom: 18px; margin-bottom: 26px; }
.header-left, .header-right { display: table-cell; vertical-align: middle; }
.header-right { text-align: right; }
.brand-logo { max-width: 200px; max-height: 58px; }
.brand-name { font-size: 28px; font-weight: 700; color: {{ $primary }}; }
.meta-label { font-size: 11px; letter-spacing: 0.08em; text-transform: uppercase; color: #64748b; }
.invoice-number { font-size: 26px; font-weight: 800; color: #0f172a; margin-top: 4px; }
.section { margin-bottom: 24px; }
.section-title { background: #eff6ff; color: {{ $primary }}; padding: 10px 14px; border-radius: 10px; font-weight: 700; text-transform: uppercase; font-size: 12px; letter-spacing: 0.06em; }
.row { display: table; width: 100%; padding: 11px 0; border-bottom: 1px solid #e5e7eb; }
.label, .value { display: table-cell; vertical-align: top; }
.label { width: 32%; font-weight: 700; color: #475569; }
.value { width: 68%; color: #0f172a; }
.status { display: inline-block; padding: 5px 12px; border-radius: 999px; background: #e2e8f0; font-size: 11px; font-weight: 700; text-transform: uppercase; color: #334155; }
.amount-box { background: linear-gradient(135deg, {{ $primary }}, {{ $secondary }}); color: #ffffff; padding: 28px; border-radius: 18px; text-align: center; }
.amount-label { font-size: 13px; opacity: 0.88; letter-spacing: 0.08em; text-transform: uppercase; }
.amount-value { font-size: 38px; font-weight: 800; margin: 10px 0 14px; }
.btn-pay { display: inline-block; background: #ffffff; color: {{ $primary }}; padding: 12px 24px; border-radius: 999px; text-decoration: none; font-weight: 700; }
.footer { margin-top: 28px; padding-top: 18px; border-top: 1px solid #e5e7eb; text-align: center; color: #64748b; font-size: 12px; line-height: 1.7; }
.no-print { text-align: center; margin-bottom: 20px; }
.no-print button { background: {{ $primary }}; color: #fff; border: none; padding: 10px 20px; border-radius: 999px; cursor: pointer; font-weight: 700; }
@media print { .no-print { display: none; } body { padding: 0; background: #fff; } .sheet { border: none; border-radius: 0; } }
</style>
</head>
<body>

<div class="no-print">
    <button onclick="window.print()">Imprimir / Salvar como PDF</button>
</div>

<div class="sheet">
    <div class="header">
        <div class="header-left">
            @if($appLogo)
                <img src="{{ $appLogo }}" alt="{{ $appName }}" class="brand-logo">
            @else
                <div class="brand-name">{{ $appName }}</div>
            @endif
        </div>
        <div class="header-right">
            <div class="meta-label">Fatura</div>
            <div class="invoice-number">#{{ $invoice->number }}</div>
            <div style="margin-top:6px; color:#64748b;">Emitida em {{ \App\Support\Format::date($invoice->created_at) }}</div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Cliente</div>
        <div class="row"><div class="label">Nome</div><div class="value">{{ $invoice->customer?->name ?? '-' }}</div></div>
        @if($invoice->customer?->document)
        <div class="row"><div class="label">Documento</div><div class="value">{{ \App\Support\Format::cpf($invoice->customer->document) ?: \App\Support\Format::cnpj($invoice->customer->document) }}</div></div>
        @endif
        @if($invoice->customer?->email)
        <div class="row"><div class="label">E-mail</div><div class="value">{{ $invoice->customer->email }}</div></div>
        @endif
        @if($invoice->customer?->whatsapp)
        <div class="row"><div class="label">WhatsApp</div><div class="value">{{ \App\Support\Format::phone($invoice->customer->whatsapp) }}</div></div>
        @endif
    </div>

    <div class="section">
        <div class="section-title">Detalhes da cobranca</div>
        <div class="row"><div class="label">Descricao</div><div class="value">{!! $descriptionHtml ?: '-' !!}</div></div>
        <div class="row"><div class="label">Vencimento</div><div class="value">{{ \App\Support\Format::date($invoice->due_date) }}</div></div>
        <div class="row"><div class="label">Status</div><div class="value"><span class="status">{{ $invoice->status }}</span></div></div>
    </div>

    <div class="amount-box">
        <div class="amount-label">Valor a pagar</div>
        <div class="amount-value">{{ \App\Support\Format::money($invoice->amount) }}</div>
        @if($invoice->payment_url)
        <a href="{{ $invoice->payment_url }}" class="btn-pay" target="_blank">Pagar agora</a>
        @endif
    </div>

    <div class="footer">
        <strong>{{ $appName }}</strong><br>
        Documento gerado eletronicamente em {{ \App\Support\Format::datetime(now()) }}
    </div>
</div>

</body>
</html>
