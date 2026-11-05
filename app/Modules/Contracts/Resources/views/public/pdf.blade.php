<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>{{ $contract->title ?: 'Contrato ' . $contract->number }}</title>
<style>
@page {
    margin: 0;
    size: 794px 1123px;
}
* { box-sizing: border-box; }
body {
    font-family: DejaVu Sans, "Segoe UI", Arial, sans-serif;
    font-size: 13px;
    line-height: 1.7;
    color: #162033;
    margin: 0;
    padding: 0;
    width: 794px;
    height: 1123px;
    position: relative;
    background: #fff;
}
.document-bg {
    position: absolute;
    left: 0;
    top: 0;
    width: 794px;
    height: 1123px;
    z-index: 0;
}
.document-bg img {
    width: 100%;
    height: 100%;
    object-fit: fill;
}
.document-layer {
    position: relative;
    z-index: 1;
    width: 794px;
    height: 1123px;
}
.cs-header {
    text-align: center;
    margin-bottom: 24px;
    padding-bottom: 14px;
    border-bottom: 2px solid #e2e8f0;
}
.cs-header-category {
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: #64748b;
    margin-bottom: 4px;
}
.cs-header-title {
    margin: 0;
    font-size: 19px;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: #0f172a;
    line-height: 1.3;
}
.cs-header-desc {
    font-size: 11.5px;
    color: #64748b;
    margin-top: 4px;
    font-style: italic;
}
.cs-abs-box {
    position: absolute;
    overflow: visible;
}
.cs-block-label {
    margin-bottom: 6px;
    font-size: 10px;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: #64748b;
}
.cs-sig-placeholder {
    height: 80px;
    border: 1.5px dashed #cbd5e1;
    border-radius: 8px;
    text-align: center;
    line-height: 80px;
    color: #94a3b8;
    font-size: 12px;
}
.cs-sig-img {
    max-width: 100%;
    max-height: 80px;
    display: block;
}
.cs-signer-stack strong {
    display: block;
    font-size: 13px;
    color: #0f172a;
}
.cs-signer-stack span {
    display: block;
    font-size: 11.5px;
    color: #475467;
}
</style>
</head>
<body>
@php
    use App\Support\Format;

    $layout = $contract->layout_json ?: [];
    $boxes = $layout['boxes'] ?? [];
    $hasStrictBg = in_array($contract->background_type, ['image', 'pdf']) && !empty($backgroundUrl);
    $contentLen = strlen(strip_tags((string) $contract->content_html));
    $isFluid = ($layout['mode'] ?? 'fluid') !== 'fixed' || $contentLen > 700 || empty($boxes);
    $fluidMargins = $layout['fluidMargins'] ?? ['top' => 40, 'right' => 40, 'bottom' => 40, 'left' => 40];

    $layoutContent = $layout['content'] ?? $boxes['content'] ?? [];
    $layoutSignature = $layout['signature'] ?? $boxes['signature'] ?? [];
    $layoutSigner = $layout['signer'] ?? $boxes['signer'] ?? [];
    $layoutMetadata = $layout['metadata'] ?? $boxes['metadata'] ?? [];

    $showContent = !isset($layoutContent['enabled']) || $layoutContent['enabled'] !== false;
    $showSignature = !isset($layoutSignature['enabled']) || $layoutSignature['enabled'] !== false;
    $showSigner = !isset($layoutSigner['enabled']) || $layoutSigner['enabled'] !== false;
    $showMetadata = !isset($layoutMetadata['enabled']) || $layoutMetadata['enabled'] !== false;

    $signed = $contract->status === 'signed';
    $rawDoc = $signed ? ($contract->signer_document ?: $contract->customer?->document) : ($contract->customer?->document ?: '');
    $rawDigits = preg_replace('/\D/', '', (string) $rawDoc);
    $signerDocFmt = match(strlen($rawDigits)) {
        14 => Format::cnpj($rawDoc),
        11 => Format::cpf($rawDoc),
        default => $rawDoc,
    };
    $signerName = $signed ? ($contract->signer_name ?: $contract->customer?->name) : ($contract->customer?->name ?: '');
    $signerEmail = $signed ? ($contract->signer_email ?: $contract->customer?->email) : ($contract->customer?->email ?: '');
    $signerPhone = Format::phone($contract->customer?->whatsapp ?: $contract->customer?->phone ?: '');
    $signedAt = $contract->signed_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i:s') ?? '—';

    $hasDocTitle = str_contains($contract->content_html, '<h1') || str_contains($contract->content_html, '<h2') || str_contains($contract->content_html, 'cs-header');
    $contractTitleText = $contract->title ?: ($contract->template?->name ?: 'Contrato de Prestação de Serviços');
    $contractCategoryText = $contract->template?->category ?: 'Instrumento Particular de Contrato';
    $contractReasonText = $contract->template?->description ?: null;
@endphp

@if(!empty($backgroundUrl))
<div class="document-bg">
    <img src="{{ $backgroundUrl }}" alt="">
</div>
@endif

<div class="document-layer">
    @if($isFluid)
    <div style="padding: {{ $fluidMargins['top'] ?? 40 }}px {{ $fluidMargins['right'] ?? 40 }}px {{ $fluidMargins['bottom'] ?? 40 }}px {{ $fluidMargins['left'] ?? 40 }}px;">
        @if(!$hasDocTitle && $contractTitleText)
        <div class="cs-header">
            <div class="cs-header-category">{{ $contractCategoryText }} &bull; {{ $contract->number }}</div>
            <h1 class="cs-header-title">{{ $contractTitleText }}</h1>
            @if($contractReasonText)
                <div class="cs-header-desc">Finalidade / Motivo: {{ $contractReasonText }}</div>
            @endif
        </div>
        @endif

        @if($showContent)
        <div style="font-family: {{ $layoutContent['fontFamily'] ?? 'inherit' }}; font-size: {{ $layoutContent['fontSize'] ?? 13 }}px; color: {{ $layoutContent['color'] ?? '#000000' }}; margin-bottom: 24px;">
            {!! $contract->content_html !!}
        </div>
        @endif

        <table width="100%" style="margin-top: 20px; border-top: 1px solid #cbd5e1; padding-top: 15px;">
            <tr>
                @if($showSignature)
                <td width="50%" valign="top" style="font-family: {{ $layoutSignature['fontFamily'] ?? 'inherit' }}; font-size: {{ $layoutSignature['fontSize'] ?? 12 }}px; color: {{ $layoutSignature['color'] ?? '#000000' }};">
                    <div class="cs-block-label">Assinatura do cliente</div>
                    <div class="cs-sig-placeholder" style="min-height: 75px; max-height: 110px; width: 100%; border: 1.5px dashed #cbd5e1; border-radius: 8px; text-align: center; overflow: hidden !important; padding: 4px; box-sizing: border-box; background: #f8fafc;">
                        @if($signed && $contract->signature_image)
                            <img src="{{ $contract->signature_image }}" alt="Assinatura" class="cs-sig-img" style="max-width: 100%; max-height: 95px; width: auto; height: auto; object-fit: contain !important; display: block; margin: 0 auto;">
                        @else
                            <div style="font-size: 11px; color: #94a3b8; padding-top: 24px;">Assinatura será registrada aqui</div>
                        @endif
                    </div>
                </td>
                @endif
                @if($showSigner)
                <td width="50%" valign="top" style="font-family: {{ $layoutSigner['fontFamily'] ?? 'inherit' }}; font-size: {{ $layoutSigner['fontSize'] ?? 12 }}px; color: {{ $layoutSigner['color'] ?? '#000000' }};">
                    <div class="cs-block-label">Dados do signatário</div>
                    <div class="cs-signer-stack">
                        @if($signerName) <strong>{{ $signerName }}</strong> @endif
                        @if($signerDocFmt) <span>{{ $signerDocFmt }}</span> @endif
                        @if($signerPhone) <span>{{ $signerPhone }}</span> @endif
                        @if($signerEmail) <span>{{ $signerEmail }}</span> @endif
                    </div>
                </td>
                @endif
            </tr>
        </table>

        @if($showMetadata)
        <div style="margin-top: 20px; padding: 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; font-family: {{ $layoutMetadata['fontFamily'] ?? 'inherit' }}; font-size: {{ $layoutMetadata['fontSize'] ?? 11 }}px; color: {{ $layoutMetadata['color'] ?? '#000000' }};">
            <div class="cs-block-label">Validação e rastreabilidade</div>
            <table width="100%" style="font-size: 11px;">
                <tr>
                    <td><strong>Status:</strong> {{ $signed ? 'Assinado com sucesso' : 'Aguardando assinatura' }}</td>
                    <td><strong>Data/Hora:</strong> {{ $signed ? $signedAt : '—' }}</td>
                    <td><strong>IP:</strong> {{ $signed ? ($contract->signer_ip ?: '—') : '—' }}</td>
                    <td><strong>Token:</strong> {{ substr($contract->access_token, 0, 24) }}…</td>
                </tr>
            </table>
        </div>
        @endif
    </div>
    @else
    {{-- MODO FIXO --}}
    @if($showContent)
    <div class="cs-abs-box" style="left: {{ $layoutContent['x'] ?? 0 }}px; top: {{ $layoutContent['y'] ?? 0 }}px; width: {{ $layoutContent['w'] ?? 794 }}px; height: {{ $layoutContent['h'] ?? 500 }}px; font-family: {{ $layoutContent['fontFamily'] ?? 'inherit' }}; font-size: {{ $layoutContent['fontSize'] ?? 13 }}px; color: {{ $layoutContent['color'] ?? '#000000' }};">
        @if(!$hasDocTitle && $contractTitleText)
        <div class="cs-header" style="margin-bottom: 18px; padding-bottom: 10px;">
            <div class="cs-header-category">{{ $contractCategoryText }} &bull; {{ $contract->number }}</div>
            <h1 class="cs-header-title" style="font-size: 18px;">{{ $contractTitleText }}</h1>
            @if($contractReasonText)
                <div class="cs-header-desc">{{ $contractReasonText }}</div>
            @endif
        </div>
        @endif
        {!! $contract->content_html !!}
    </div>
    @endif

    @if($showSignature)
    <div class="cs-abs-box cs-sig-box" style="left: {{ $layoutSignature['x'] ?? 0 }}px; top: {{ $layoutSignature['y'] ?? 0 }}px; width: {{ $layoutSignature['w'] ?? 300 }}px; height: {{ $layoutSignature['h'] ?? 150 }}px; font-family: {{ $layoutSignature['fontFamily'] ?? 'inherit' }}; font-size: {{ $layoutSignature['fontSize'] ?? 12 }}px; color: {{ $layoutSignature['color'] ?? '#000000' }}; overflow: hidden !important; box-sizing: border-box;">
        <div class="cs-block-label">Assinatura do cliente</div>
        <div class="cs-sig-placeholder" style="width: 100%; height: calc(100% - 24px); max-height: calc(100% - 24px); border: 1.5px dashed #cbd5e1; border-radius: 8px; display: flex; align-items: center; justify-content: center; overflow: hidden !important; padding: 4px; box-sizing: border-box; text-align: center; background: #f8fafc;">
            @if($signed && $contract->signature_image)
                <img src="{{ $contract->signature_image }}" alt="Assinatura" class="cs-sig-img" style="max-width: 100%; max-height: 100%; width: auto; height: auto; object-fit: contain !important; display: inline-block;">
            @else
                <span style="font-size: 11px; color: #94a3b8; line-height: normal;">Assinatura será registrada aqui</span>
            @endif
        </div>
    </div>
    @endif

    @if($showSigner)
    <div class="cs-abs-box" style="left: {{ $layoutSigner['x'] ?? 0 }}px; top: {{ $layoutSigner['y'] ?? 0 }}px; width: {{ $layoutSigner['w'] ?? 300 }}px; min-height: {{ max((int)($layoutSigner['h'] ?? 50), 85) }}px; font-family: {{ $layoutSigner['fontFamily'] ?? 'inherit' }}; font-size: {{ $layoutSigner['fontSize'] ?? 11.5 }}px; color: {{ $layoutSigner['color'] ?? '#000000' }}; overflow: visible;">
        <div class="cs-block-label">Dados do signatário</div>
        <div class="cs-signer-stack">
            @if($signerName) <strong>{{ $signerName }}</strong> @endif
            @if($signerDocFmt) <span>{{ $signerDocFmt }}</span> @endif
            @if($signerPhone) <span>{{ $signerPhone }}</span> @endif
            @if($signerEmail) <span>{{ $signerEmail }}</span> @endif
        </div>
    </div>
    @endif

    @if($showMetadata)
    <div class="cs-abs-box" style="left: {{ $layoutMetadata['x'] ?? 0 }}px; top: {{ $layoutMetadata['y'] ?? 0 }}px; width: {{ $layoutMetadata['w'] ?? 600 }}px; height: {{ $layoutMetadata['h'] ?? 150 }}px; font-family: {{ $layoutMetadata['fontFamily'] ?? 'inherit' }}; font-size: {{ $layoutMetadata['fontSize'] ?? 11 }}px; color: {{ $layoutMetadata['color'] ?? '#000000' }};">
        <div class="cs-block-label">Validação e rastreabilidade</div>
        <div style="font-size: 11px;">
            <div><strong>Status:</strong> {{ $signed ? 'Assinado com sucesso' : 'Aguardando assinatura' }}</div>
            <div><strong>Data/Hora:</strong> {{ $signed ? $signedAt : '—' }}</div>
            <div><strong>IP:</strong> {{ $signed ? ($contract->signer_ip ?: '—') : '—' }}</div>
            <div><strong>Token:</strong> {{ substr($contract->access_token, 0, 24) }}…</div>
        </div>
    </div>
    @endif
    @endif
</div>
</body>
</html>
