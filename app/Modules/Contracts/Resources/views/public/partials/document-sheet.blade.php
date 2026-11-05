@php
    use App\Support\Format;

    $layout  = $layout  ?? $contract->layout_json ?? [];
    $signed  = $signed  ?? false;
    $bgType  = $backgroundType ?? ($contract->background_type ?: 'none');
    $bgUrl   = $backgroundUrl  ?? null;

    /*
     * Margens da área de texto dentro do timbrado.
     * pad_top / pad_bottom — espaço reservado para a arte do timbrado
     * (logos, faixas, bordas). Valor padrão = 130px.
     * Personalizável via layout_json: "page": { "pad_top": 140, "pad_bottom": 140 }
     */
    $page    = $layout['page'] ?? [];
    $padTop  = (int)($page['pad_top']    ?? 130);
    $padSide = (int)($page['pad_side']   ?? 52);
    $padBot  = (int)($page['pad_bottom'] ?? 130);

    // Formata documento: CPF ou CNPJ
    $rawDoc    = $signed
        ? ($contract->signer_document ?: $contract->customer?->document)
        : ($contract->customer?->document ?: '');
    $rawDigits = preg_replace('/\D/', '', (string) $rawDoc);
    $signerDocFmt = match(strlen($rawDigits)) {
        14      => Format::cnpj($rawDoc),
        11      => Format::cpf($rawDoc),
        default => $rawDoc,
    };
    $signerName  = $signed ? ($contract->signer_name  ?: $contract->customer?->name)  : ($contract->customer?->name  ?: '');
    $signerEmail = $signed ? ($contract->signer_email ?: $contract->customer?->email) : ($contract->customer?->email ?: '');
    $signerPhone = Format::phone($contract->customer?->whatsapp ?: $contract->customer?->phone ?: '');
    $signedAt    = $contract->signed_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i:s') ?? '—';

    $boxes = $layout['boxes'] ?? [];
    $layoutContent = $layout['content'] ?? $boxes['content'] ?? [];
    $layoutSignature = $layout['signature'] ?? $boxes['signature'] ?? [];
    $layoutSigner = $layout['signer'] ?? $boxes['signer'] ?? [];
    $layoutMetadata = $layout['metadata'] ?? $boxes['metadata'] ?? [];

    $tenant = $contract->tenant;
    $settingsManager = app(\App\Modules\Settings\Services\SettingsManager::class);
    $tenantWhiteLabel = $tenant?->whiteLabelSettings() ?? [];
    $tenantWhiteLabelEnabled = $tenant?->whiteLabelEnabled() ?? false;
    $companyName = $tenantWhiteLabelEnabled && !empty($tenantWhiteLabel['brand_name'])
        ? $tenantWhiteLabel['brand_name']
        : ($tenant?->company_name ?: $settingsManager->get('app.name', config('app.name')));
    $rawLogo = $tenantWhiteLabelEnabled && !empty($tenantWhiteLabel['logo_url'])
        ? $tenantWhiteLabel['logo_url']
        : ($settingsManager->get('app.logo') ?: null);
    $companyLogo = null;
    if ($rawLogo) {
        $companyLogo = (str_starts_with($rawLogo, 'http://') || str_starts_with($rawLogo, 'https://') || str_starts_with($rawLogo, 'data:'))
            ? $rawLogo
            : asset(ltrim($rawLogo, '/'));
    }

    $showContent = !isset($layoutContent['enabled']) || $layoutContent['enabled'] !== false;
    $showSignature = !isset($layoutSignature['enabled']) || $layoutSignature['enabled'] !== false;
    $showSigner = !isset($layoutSigner['enabled']) || $layoutSigner['enabled'] !== false;
    $showMetadata = !isset($layoutMetadata['enabled']) || $layoutMetadata['enabled'] !== false;
@endphp

@once
<style id="cs-sheet-style" class="contract-sheet-base-style">
/* =============================================================
   FOLHA A4 COM TIMBRADO
   Timbrado como <img> esticada via JS para cobrir a altura real
   ============================================================= */
.cs-doc-wrap {
    --cs-brand : var(--brand, var(--bs-primary, #0f766e));
    --cs-ink   : var(--ink, #162033);
    --cs-muted : var(--muted, #667085);
    --cs-bdr   : rgba(15,23,42,.09);
    font-family: Inter,"Segoe UI",system-ui,-apple-system,sans-serif;
    font-size  : 13.5px;
    line-height: 1.75;
    color      : var(--cs-ink);
}

/* ── PÁGINA A4 ─────────────────────────────────────────────── */
.cs-page {
    position      : relative;
    width         : 794px;
    min-height    : 1123px;        /* tamanho padrão A4 */
    margin        : 0 auto;
    border-radius : 6px;
    border        : 1px solid var(--cs-bdr);
    box-shadow    : 0 8px 32px rgba(15,23,42,.12);
    background    : #fff;
    overflow      : visible;       /* não corta conteúdo longo */
}

/* Timbrado — <img> esticada para cobrir toda a altura real */
.cs-timbrado {
    position      : absolute;
    inset         : 0;
    width         : 100%;
    height        : 100%;
    object-fit    : fill;          /* preenche largura E altura sem cortar */
    object-position: top left;
    pointer-events: none;
    z-index       : 0;
    border-radius : 6px;
    display       : block;
}

/* Canvas para timbrado PDF */
.cs-bg-canvas {
    position      : absolute;
    inset         : 0;
    width         : 100%;
    height        : 100%;
    max-height    : 100%;
    pointer-events: none;
    z-index       : 0;
    background    : #fff;
    opacity       : .15;
    transition    : opacity .3s;
    border-radius : inherit;
}
.cs-bg-canvas.ready { opacity: 1; }
.cs-pdf-hint {
    position      : absolute;
    top: 10px; right: 12px;
    z-index       : 10;
    padding       : 4px 10px;
    border-radius : 999px;
    background    : rgba(15,23,42,.65);
    color         : #fff;
    font-size     : 11px;
    font-weight   : 700;
    pointer-events: none;
}

/* ── Camada de conteúdo — sobre o timbrado ──────────────── */
.cs-page-layer {
    position       : relative;
    z-index        : 2;
    padding        : 0;
    width          : 794px;
    height         : 1123px;
    box-sizing     : border-box;
    display        : block;
}

/* ── CAIXAS DE LAYOUT ABSOLUTAS ───────────────────────────── */
.cs-abs-box {
    position: absolute;
    box-sizing: border-box;
    overflow: hidden;
}
.cs-abs-box.cs-signer-box,
.cs-abs-box.cs-sig-box,
.cs-abs-box.cs-meta-box {
    overflow: visible !important;
}

/* ── CORPO E TIPOGRAFIA ───────────────────────────────────── */
.cs-body h1,.cs-body h2,.cs-body h3,.cs-body h4 {
    margin: 0 0 8px; color:var(--cs-ink); font-weight:700;
}
.cs-body p { margin:0 0 12px; text-align:justify; }
.cs-body ul,.cs-body ol { padding-left:20px; margin:0 0 12px; }

/* ── ASSINATURAS E METADADOS ──────────────────────────────── */
.cs-block {
    display: flex;
    flex-direction: column;
    height: 100%;
}
.cs-block-label {
    margin-bottom : 6px;
    font-size     : 10px;
    font-weight   : 800;
    text-transform: uppercase;
    letter-spacing: .1em;
    color         : var(--cs-muted);
}
.cs-sig-placeholder {
    min-height:64px; display:flex; align-items:center; justify-content:center;
    border:1.5px dashed rgba(15,23,42,.18); border-radius:10px;
    font-size:12px; color:var(--cs-muted); background:rgba(248,250,252,.8);
}
.cs-sig-img { max-width:100%; max-height:80px; display:block; object-fit:contain; }
.cs-signer-stack { display:flex; flex-direction:column; gap:2px; font-size:12px; line-height:1.35; word-break:break-all; }
.cs-signer-stack strong { font-weight:700; color:var(--cs-ink); font-size:12.5px; }
.cs-signer-stack span   { color:#475467; font-size:11.5px; }
.cs-meta-grid span { font-size:12px; font-weight:700; color:var(--cs-ink); word-break:break-all; }

/* ── CONTROLE MOBILE DE LOGO QUANDO TIMBRADO ESTIVER OCULTO ── */
.cs-header-logo-mobile-only {
    display: none;
}

/* ── RESPONSIVIDADE ULTRA AJUSTADA: MOBILE E DISPOSITIVOS MÓVEIS (SEM SCROLL) ── */
@media (max-width: 991.98px) {
    .cs-doc-wrap {
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        overflow: visible !important;
        overflow-x: hidden !important;
        padding: 0 !important;
        margin: 0 !important;
        box-sizing: border-box !important;
    }
    .cs-page {
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        min-height: auto !important;
        height: auto !important;
        margin: 0 !important;
        padding: 0 !important;
        border-radius: 0 !important;
        border: none !important;
        box-shadow: none !important;
        background: transparent !important;
        overflow: visible !important;
        transform: none !important;
        box-sizing: border-box !important;
    }
    .cs-timbrado,
    .cs-bg-canvas,
    .cs-pdf-hint {
        display: none !important;
    }
    .cs-header-logo-mobile-only {
        display: block !important;
    }
    .cs-page-layer {
        position: static !important;
        width: 100% !important;
        min-width: 0 !important;
        max-width: 100% !important;
        height: auto !important;
        min-height: auto !important;
        display: flex !important;
        flex-direction: column !important;
        gap: 16px !important;
        padding: 0 !important;
        box-sizing: border-box !important;
    }
    .cs-abs-box {
        position: static !important;
        left: auto !important;
        top: auto !important;
        right: auto !important;
        bottom: auto !important;
        width: 100% !important;
        min-width: 0 !important;
        max-width: 100% !important;
        height: auto !important;
        min-height: auto !important;
        max-height: none !important;
        overflow: visible !important;
        margin: 0 0 14px 0 !important;
        box-sizing: border-box !important;
    }
    .cs-fluid-layer {
        padding: 0 !important;
        width: 100% !important;
        min-width: 0 !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
    }
    .cs-contract-header {
        margin-bottom: 20px !important;
        padding-bottom: 14px !important;
    }
    .cs-contract-header h1 {
        font-size: 18px !important;
        line-height: 1.35 !important;
    }
    .cs-body {
        font-size: 15px !important;
        line-height: 1.8 !important;
        color: #1e293b !important;
        word-break: break-word !important;
        overflow-wrap: break-word !important;
        text-align: left !important;
    }
    .cs-body p {
        text-align: left !important;
        margin-bottom: 12px !important;
        word-break: break-word !important;
        overflow-wrap: break-word !important;
    }
    .cs-body table {
        width: 100% !important;
        max-width: 100% !important;
        display: block !important;
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch !important;
    }
    .cs-body img {
        max-width: 100% !important;
        height: auto !important;
    }
    .cs-block {
        width: 100% !important;
        height: auto !important;
        min-height: auto !important;
    }
    .cs-sig-placeholder {
        min-height: 75px !important;
    }
}
@media print {
    .cs-page { width:210mm; border:0; box-shadow:none; border-radius:0; page-break-after:always; }
}
</style>

<script>
/* ── Renderizador de timbrado PDF ── */
(function(){
    var SRC='https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.min.js';
    var WRK='https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.worker.min.js';
    var loading=null;
    function loadLib(){
        if(window.pdfjsLib){window.pdfjsLib.GlobalWorkerOptions.workerSrc=WRK;return Promise.resolve(window.pdfjsLib);}
        if(loading) return loading;
        loading=new Promise(function(res,rej){
            var s=document.createElement('script');s.src=SRC;s.async=true;
            s.onload=function(){window.pdfjsLib.GlobalWorkerOptions.workerSrc=WRK;res(window.pdfjsLib);};
            s.onerror=rej;document.head.appendChild(s);
        });
        return loading;
    }
    async function render(canvas){
        if(!canvas||canvas.dataset.done==='1')return;
        if(window.innerWidth < 992){canvas.style.display='none';return;}
        canvas.dataset.done='1';
        try{
            var lib=await loadLib();
            var page=canvas.closest('.cs-page');
            var W=Math.max(1,Math.round(page?page.getBoundingClientRect().width:794));
            var H=Math.max(1,Math.round(page?page.getBoundingClientRect().height:1123));
            var r=window.devicePixelRatio||1;
            var bgSrc = canvas.dataset.contractPdfBg || canvas.dataset.bg;
            var doc=await lib.getDocument(bgSrc).promise;
            var pg=await doc.getPage(1);
            var vp0=pg.getViewport({scale:1});
            var sc=Math.max(W/vp0.width,H/vp0.height);
            var vp=pg.getViewport({scale:sc});
            var ctx=canvas.getContext('2d');
            canvas.width=Math.round(W*r);canvas.height=Math.round(H*r);
            canvas.style.width='100%';canvas.style.height='100%';
            ctx.setTransform(r,0,0,r,0,0);
            ctx.fillStyle='#fff';ctx.fillRect(0,0,W,H);
            ctx.translate((W-vp.width)/2,(H-vp.height)/2);
            await pg.render({canvasContext:ctx,viewport:vp}).promise;
            canvas.classList.add('ready');
            if(page) { var h=page.querySelector('.cs-pdf-hint'); if(h) h.remove(); }
        }catch(e){
            canvas.dataset.done='0';
            canvas.style.display = 'none';
            console.warn('PDF bg',e);
            if(page) { var h=page.querySelector('.cs-pdf-hint'); if(h) h.remove(); }
        }
    }
    function init(){ 
        document.querySelectorAll('canvas[data-bg], canvas[data-contract-pdf-bg]').forEach(render);
        setTimeout(function(){ document.querySelectorAll('.cs-pdf-hint').forEach(function(h){ h.remove(); }); }, 2500);
    }
    if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',init); else init();
    window.addEventListener('resize',function(){ document.querySelectorAll('canvas[data-bg], canvas[data-contract-pdf-bg]').forEach(function(c){c.dataset.done='0';render(c);}); });
})();
</script>
@endonce

{{-- ====================================================
     PÁGINA A4 — timbrado como img esticada via JS
     Conteúdo com padding respeitando arte do timbrado
     ==================================================== --}}
<div class="cs-doc-wrap">
    <div class="cs-page">

        {{-- Timbrado imagem — img esticada por JS para cobrir a altura real --}}
        @if($bgType === 'image' && $bgUrl)
            <img src="{{ $bgUrl }}"
                 alt="Papel timbrado"
                 class="cs-timbrado">
        @elseif($bgType === 'pdf' && $bgUrl)
            <canvas class="cs-bg-canvas contract-sheet-pdf-canvas" data-bg="{{ $bgUrl }}" data-contract-pdf-bg="{{ $bgUrl }}"></canvas>
            <span class="cs-pdf-hint">Carregando timbrado…</span>
        @endif

        @php
            $hasStrictBg = in_array($bgType, ['image', 'pdf']) && !empty($bgUrl);
            $contentLen = strlen(strip_tags((string) $contract->content_html));
            // Modo fluido contínuo é padrão e obrigatório para qualquer contrato de serviços ou texto longo
            // para NUNCA cortar cláusulas contratuais nem dados do signatário.
            $isFluid = ($layout['mode'] ?? 'fluid') !== 'fixed' || $contentLen > 700 || empty($boxes);
            $fluidMargins = $layout['fluidMargins'] ?? ['top' => 40, 'right' => 40, 'bottom' => 40, 'left' => 40];
            $hasDocTitle = str_contains($contract->content_html, '<h1') || str_contains($contract->content_html, '<h2') || str_contains($contract->content_html, 'cs-contract-header');
            $contractTitleText = $contract->title ?: ($contract->template?->name ?: 'Contrato de Prestação de Serviços');
            $contractCategoryText = $contract->template?->category ?: 'Instrumento Particular de Contrato';
            $contractReasonText = $contract->template?->description ?: null;
        @endphp

        @if($isFluid)
        {{-- MODO FLUIDO (CONTÍNUO / MÚLTIPLAS PÁGINAS) --}}
        <div class="cs-fluid-layer" style="position:relative; z-index:2; padding: {{ $fluidMargins['top'] ?? 40 }}px {{ $fluidMargins['right'] ?? 40 }}px {{ $fluidMargins['bottom'] ?? 40 }}px {{ $fluidMargins['left'] ?? 40 }}px;">
            @if(!$hasDocTitle && $contractTitleText)
            <div class="cs-contract-header" style="text-align: center; margin-bottom: 24px; padding-bottom: 14px; border-bottom: 2px solid rgba(15,23,42,.1);">
                @if($companyLogo)
                    <div class="cs-header-logo-wrap {{ $bgType === 'image' ? 'cs-header-logo-mobile-only' : '' }}" style="margin-bottom: 12px;">
                        <img src="{{ $companyLogo }}" alt="{{ $companyName }}" style="max-height: 52px; max-width: 220px; object-fit: contain;">
                    </div>
                @endif
                <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .12em; color: #64748b; margin-bottom: 4px;">
                    {{ $companyName }} &bull; {{ $contractCategoryText }} &bull; {{ $contract->number }}
                </div>
                <h1 style="margin: 0; font-size: 20px; font-weight: 800; text-transform: uppercase; letter-spacing: .03em; color: #0f172a; line-height: 1.3;">
                    {{ $contractTitleText }}
                </h1>
                @if($contractReasonText)
                    <div style="font-size: 12px; color: #64748b; margin-top: 4px; font-style: italic;">
                        Finalidade / Objeto: {{ $contractReasonText }}
                    </div>
                @endif
            </div>
            @endif

            @if($showContent)
            <div class="cs-body mb-4" style="font-family: {{ $layoutContent['fontFamily'] ?? 'inherit' }}; font-size: {{ $layoutContent['fontSize'] ?? 14 }}px; color: {{ $layoutContent['color'] ?? 'inherit' }};">
                {!! $contract->content_html !!}
            </div>
            @endif

            <div class="row g-3 mt-4 pt-3 border-top">
                @if($showSignature)
                <div class="col-md-6">
                    <div class="cs-block" style="font-family: {{ $layoutSignature['fontFamily'] ?? 'inherit' }}; font-size: {{ $layoutSignature['fontSize'] ?? 13 }}px; color: {{ $layoutSignature['color'] ?? 'inherit' }};">
                        <div class="cs-block-label">Assinatura do cliente</div>
                        <div class="cs-sig-placeholder" style="min-height: 80px; max-height: 120px; overflow: hidden !important; display: flex; align-items: center; justify-content: center; box-sizing: border-box; padding: 6px;">
                            @if($signed && $contract->signature_image)
                                <img src="{{ $contract->signature_image }}" alt="Assinatura" class="cs-sig-img" style="max-width: 100%; max-height: 100%; width: auto; height: auto; object-fit: contain !important; display: block; margin: auto;">
                            @else
                                <span>Assinatura será registrada aqui</span>
                            @endif
                        </div>
                    </div>
                </div>
                @endif

                @if($showSigner)
                <div class="col-md-6">
                    <div class="cs-block" style="font-family: {{ $layoutSigner['fontFamily'] ?? 'inherit' }}; font-size: {{ $layoutSigner['fontSize'] ?? 13 }}px; color: {{ $layoutSigner['color'] ?? 'inherit' }};">
                        <div class="cs-block-label">Dados do signatário</div>
                        <div class="cs-signer-stack">
                            @if($signerName)   <strong>{{ $signerName }}</strong>  @endif
                            @if($signerDocFmt) <span>{{ $signerDocFmt }}</span>    @endif
                            @if($signerPhone)  <span>{{ $signerPhone }}</span>     @endif
                            @if($signerEmail)  <span>{{ $signerEmail }}</span>     @endif
                        </div>
                    </div>
                </div>
                @endif

                @if($showMetadata)
                <div class="col-12 mt-3">
                    <div class="cs-block p-2 rounded bg-light border" style="font-family: {{ $layoutMetadata['fontFamily'] ?? 'inherit' }}; font-size: {{ $layoutMetadata['fontSize'] ?? 11 }}px; color: {{ $layoutMetadata['color'] ?? 'inherit' }};">
                        <div class="cs-block-label">Validação e rastreabilidade</div>
                        <div class="d-flex flex-wrap gap-3 small">
                            <div><strong>Status:</strong> {{ $signed ? 'Assinado com sucesso' : 'Aguardando assinatura' }}</div>
                            <div><strong>Data/Hora:</strong> {{ $signed ? $signedAt : '—' }}</div>
                            <div><strong>IP:</strong> {{ $signed ? ($contract->signer_ip ?: '—') : '—' }}</div>
                            <div><strong>Token:</strong> <code>{{ substr($contract->access_token, 0, 26) }}…</code></div>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
        @else
        {{-- MODO FIXO (POSICIONAMENTO CIRÚRGICO) --}}
        <div class="cs-page-layer">
            
            @if($showContent)
            <div class="cs-abs-box cs-body" style="left: {{ $layoutContent['x'] ?? 0 }}px; top: {{ $layoutContent['y'] ?? 0 }}px; width: {{ $layoutContent['w'] ?? 794 }}px; height: {{ $layoutContent['h'] ?? 500 }}px; font-family: {{ $layoutContent['fontFamily'] ?? 'inherit' }}; font-size: {{ $layoutContent['fontSize'] ?? 14 }}px; color: {{ $layoutContent['color'] ?? 'inherit' }};">
                @if(!$hasDocTitle && $contractTitleText)
                <div class="cs-contract-header" style="text-align: center; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid rgba(15,23,42,.1);">
                    @if($companyLogo)
                        <div class="cs-header-logo-wrap {{ $bgType === 'image' ? 'cs-header-logo-mobile-only' : '' }}" style="margin-bottom: 10px;">
                            <img src="{{ $companyLogo }}" alt="{{ $companyName }}" style="max-height: 48px; max-width: 200px; object-fit: contain;">
                        </div>
                    @endif
                    <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .12em; color: #64748b; margin-bottom: 4px;">
                        {{ $companyName }} &bull; {{ $contractCategoryText }} &bull; {{ $contract->number }}
                    </div>
                    <h1 style="margin: 0; font-size: 19px; font-weight: 800; text-transform: uppercase; letter-spacing: .03em; color: #0f172a; line-height: 1.3;">
                        {{ $contractTitleText }}
                    </h1>
                    @if($contractReasonText)
                        <div style="font-size: 11.5px; color: #64748b; margin-top: 3px; font-style: italic;">
                            Finalidade / Objeto: {{ $contractReasonText }}
                        </div>
                    @endif
                </div>
                @endif
                {!! $contract->content_html !!}
            </div>
            @endif

            @if($showSignature)
            <div class="cs-abs-box cs-block cs-sig-box" style="left: {{ $layoutSignature['x'] ?? 0 }}px; top: {{ $layoutSignature['y'] ?? 0 }}px; width: {{ $layoutSignature['w'] ?? 300 }}px; height: {{ $layoutSignature['h'] ?? 150 }}px; font-family: {{ $layoutSignature['fontFamily'] ?? 'inherit' }}; font-size: {{ $layoutSignature['fontSize'] ?? 13 }}px; color: {{ $layoutSignature['color'] ?? 'inherit' }}; overflow: hidden !important; box-sizing: border-box;">
                <div class="cs-block-label">Assinatura do cliente</div>
                <div class="cs-sig-placeholder" style="flex: 1; width: 100%; height: calc(100% - 24px); max-height: calc(100% - 24px); overflow: hidden !important; display: flex; align-items: center; justify-content: center; box-sizing: border-box; padding: 4px;">
                    @if($signed && $contract->signature_image)
                        <img src="{{ $contract->signature_image }}" alt="Assinatura" class="cs-sig-img" style="max-width: 100%; max-height: 100%; width: auto; height: auto; object-fit: contain !important; display: block; margin: auto;">
                    @else
                        <span>Assinatura será registrada aqui</span>
                    @endif
                </div>
            </div>
            @endif

            @if($showSigner)
            <div class="cs-abs-box cs-block cs-signer-box" style="left: {{ $layoutSigner['x'] ?? 0 }}px; top: {{ $layoutSigner['y'] ?? 0 }}px; width: {{ $layoutSigner['w'] ?? 300 }}px; min-height: {{ max((int)($layoutSigner['h'] ?? 50), 85) }}px; font-family: {{ $layoutSigner['fontFamily'] ?? 'inherit' }}; font-size: {{ $layoutSigner['fontSize'] ?? 12 }}px; color: {{ $layoutSigner['color'] ?? 'inherit' }}; overflow: visible !important;">
                <div class="cs-block-label">Dados do signatário</div>
                <div class="cs-signer-stack">
                    @if($signerName)   <strong>{{ $signerName }}</strong>  @endif
                    @if($signerDocFmt) <span>{{ $signerDocFmt }}</span>    @endif
                    @if($signerPhone)  <span>{{ $signerPhone }}</span>     @endif
                    @if($signerEmail)  <span>{{ $signerEmail }}</span>     @endif
                </div>
            </div>
            @endif

            @if($showMetadata)
            <div class="cs-abs-box cs-block cs-meta-box" style="left: {{ $layoutMetadata['x'] ?? 0 }}px; top: {{ $layoutMetadata['y'] ?? 0 }}px; width: {{ $layoutMetadata['w'] ?? 600 }}px; min-height: {{ max((int)($layoutMetadata['h'] ?? 45), 45) }}px; font-family: {{ $layoutMetadata['fontFamily'] ?? 'inherit' }}; font-size: {{ $layoutMetadata['fontSize'] ?? 11 }}px; color: {{ $layoutMetadata['color'] ?? 'inherit' }}; overflow: visible !important;">
                <div class="cs-block-label">Validação e rastreabilidade</div>
                <div style="font-size: 11px;">
                    <div><strong>Status:</strong> {{ $signed ? 'Assinado com sucesso' : 'Aguardando assinatura' }}</div>
                    <div><strong>Data/Hora:</strong> {{ $signed ? $signedAt : '—' }}</div>
                    <div><strong>IP:</strong> {{ $signed ? ($contract->signer_ip ?: '—') : '—' }}</div>
                    <div><strong>Token:</strong> <code>{{ substr($contract->access_token, 0, 26) }}…</code></div>
                </div>
            </div>
            @endif

        </div>{{-- /cs-page-layer --}}
        @endif
    </div>{{-- /cs-page --}}
</div>{{-- /cs-doc-wrap --}}
