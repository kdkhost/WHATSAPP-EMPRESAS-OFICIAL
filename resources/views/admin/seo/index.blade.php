@extends('admin.layouts.app')
@section('title', 'SEO e Rastreamento')

@section('content')
<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="mb-1"><i class="bi bi-search me-2"></i>SEO e Rastreamento</h3>
            <p class="text-muted mb-0 small">Meta tags, Open Graph, Analytics e pixels de rastreamento.</p>
        </div>
    </div>

    <form id="frm-seo">
        @csrf
        <div class="row g-4">

            {{-- CARD 1: Meta Tags --}}
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <span><i class="bi bi-code-slash me-1"></i> Meta Tags Globais</span>
                        <button type="submit" class="btn btn-primary btn-sm" id="btn-save-seo">
                            <i class="bi bi-check2-circle me-1"></i>Salvar
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">

                            {{-- Título --}}
                            <div class="col-12">
                                <label class="form-label">Título Padrão (meta title) <span class="text-danger">*</span></label>
                                <input type="text" name="title" id="seo-title" class="form-control"
                                       maxlength="120" value="{{ $seo['title'] }}"
                                       placeholder="Sistema de Cobrança WhatsApp - Automatize seus recebimentos">
                                <div class="d-flex justify-content-between mt-1">
                                    <small class="text-muted">Exibido na aba do navegador e nos resultados do Google.</small>
                                    <small class="text-muted"><span id="title-count">{{ strlen($seo['title'] ?? '') }}</span>/120</small>
                                </div>
                                <div class="progress mt-1" style="height:3px;">
                                    <div class="progress-bar" id="title-bar" style="width:{{ min(100, (strlen($seo['title'] ?? '') / 60) * 100) }}%"></div>
                                </div>
                            </div>

                            {{-- Descrição --}}
                            <div class="col-12">
                                <label class="form-label">Descrição (meta description)</label>
                                <textarea name="description" id="seo-desc" class="form-control" rows="3"
                                          maxlength="255" placeholder="Envie cobranças automatizadas via WhatsApp. Integração com gateways de pagamento, templates personalizáveis e relatórios em tempo real.">{{ $seo['description'] }}</textarea>
                                <div class="d-flex justify-content-between mt-1">
                                    <small class="text-muted">Recomendado: 150-160 caracteres para melhor exibição no Google.</small>
                                    <small class="text-muted"><span id="desc-count">{{ strlen($seo['description'] ?? '') }}</span>/255</small>
                                </div>
                                <div class="progress mt-1" style="height:3px;">
                                    <div class="progress-bar" id="desc-bar" style="width:{{ min(100, (strlen($seo['description'] ?? '') / 160) * 100) }}%"></div>
                                </div>
                            </div>

                            {{-- Palavras-chave --}}
                            <div class="col-md-6">
                                <label class="form-label">Palavras-chave</label>
                                <input type="text" name="keywords" class="form-control"
                                       value="{{ $seo['keywords'] }}"
                                       placeholder="cobrança, whatsapp, pagamento, fatura, boleto">
                                <small class="text-muted">Separadas por vírgula. Usado em meta keywords.</small>
                            </div>

                            {{-- Hashtags --}}
                            <div class="col-md-6">
                                <label class="form-label">Hashtags (redes sociais)</label>
                                <input type="text" name="hashtags" class="form-control"
                                       value="{{ $seo['hashtags'] }}"
                                       placeholder="#cobranca #whatsapp #pagamento">
                                <small class="text-muted">Usadas em compartilhamentos sociais.</small>
                            </div>

                            {{-- Open Graph Image --}}
                            <div class="col-12">
                                <label class="form-label">Imagem Open Graph (og:image)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-image"></i></span>
                                    <input type="text" name="og_image" id="seo-og-image" class="form-control"
                                           value="{{ $seo['og_image'] }}"
                                           placeholder="https://seudominio.com/img/og-image.jpg">
                                    <button type="button" class="btn btn-outline-secondary" id="btn-upload-og" title="Enviar imagem">
                                        <i class="bi bi-cloud-arrow-up"></i>
                                    </button>
                                </div>
                                <small class="text-muted">Imagem exibida ao compartilhar links no WhatsApp, Facebook, Twitter. Recomendado: 1200x630px.</small>

                                {{-- Zona de upload premium com clique, drag-drop e progresso --}}
                                <div class="og-upload-zone mt-2" id="og-dropzone">
                                    {{-- Input file oculto --}}
                                    <input type="file" id="og-file-input" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml" style="display:none;">

                                    {{-- Estado: vazio --}}
                                    <div id="og-state-empty" class="{{ $seo['og_image'] ? 'd-none' : '' }}">
                                        <div class="og-upload-icon">
                                            <i class="bi bi-cloud-arrow-up-fill"></i>
                                        </div>
                                        <p class="og-upload-title">Clique ou arraste uma imagem aqui</p>
                                        <p class="og-upload-sub">JPG, PNG, WebP, SVG · Máx 10MB · Recomendado 1200×630px</p>
                                        <button type="button" class="og-upload-btn" id="og-btn-select">
                                            <i class="bi bi-folder2-open me-1"></i> Selecionar arquivo
                                        </button>
                                    </div>

                                    {{-- Estado: progresso --}}
                                    <div id="og-state-progress" class="d-none">
                                        <div class="og-progress-wrap">
                                            <div class="og-progress-icon">
                                                <i class="bi bi-arrow-up-circle-fill"></i>
                                            </div>
                                            <p class="og-upload-title mb-2" id="og-progress-filename">Enviando...</p>
                                            <div class="og-progress-bar-wrap">
                                                <div class="og-progress-bar" id="og-progress-bar"></div>
                                            </div>
                                            <div class="d-flex justify-content-between mt-1">
                                                <small id="og-progress-pct">0%</small>
                                                <small id="og-progress-eta">Calculando...</small>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Estado: preview --}}
                                    <div id="og-state-preview" class="{{ $seo['og_image'] ? '' : 'd-none' }}">
                                        <img src="{{ $seo['og_image'] }}" alt="OG Preview" id="og-preview-img" class="og-preview-img">
                                        <div class="og-preview-overlay">
                                            <button type="button" class="og-preview-change" id="og-btn-change">
                                                <i class="bi bi-pencil-fill me-1"></i> Trocar imagem
                                            </button>
                                            <button type="button" class="og-preview-remove" id="og-btn-remove">
                                                <i class="bi bi-trash-fill"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            {{-- CARD 2: Preview Google --}}
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <span><i class="bi bi-google me-1"></i> Preview no Google</span>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">Como seu site aparece nos resultados de busca:</p>

                        <div class="border rounded p-3 bg-body-tertiary">
                            <div class="small" style="font-family:Arial,sans-serif;">
                                <div class="text-primary fw-medium" id="preview-title" style="font-size:1.1em;">
                                    {{ $seo['title'] ?: 'Título do seu site' }}
                                </div>
                                <div class="text-success small" style="font-size:0.85em;">
                                    {{ url('/') }}
                                </div>
                                <div class="text-muted" id="preview-desc" style="font-size:0.9em;">
                                    {{ $seo['description'] ?: 'Descrição do seu site aparece aqui nos resultados de busca...' }}
                                </div>
                            </div>
                        </div>

                        <hr>

                        {{-- Score SEO --}}
                        <h6 class="small fw-semibold text-muted text-uppercase mb-2">Pontuação SEO</h6>
                        <div class="d-flex flex-column gap-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="small">Título</span>
                                <span class="badge bg-{{ strlen($seo['title'] ?? '') >= 30 && strlen($seo['title'] ?? '') <= 60 ? 'success' : (strlen($seo['title'] ?? '') > 0 ? 'warning' : 'danger') }}" id="score-title">
                                    {{ strlen($seo['title'] ?? '') >= 30 && strlen($seo['title'] ?? '') <= 60 ? 'Ótimo' : (strlen($seo['title'] ?? '') > 0 ? 'Ajustar' : 'Ausente') }}
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="small">Descrição</span>
                                <span class="badge bg-{{ strlen($seo['description'] ?? '') >= 120 && strlen($seo['description'] ?? '') <= 160 ? 'success' : (strlen($seo['description'] ?? '') > 0 ? 'warning' : 'danger') }}" id="score-desc">
                                    {{ strlen($seo['description'] ?? '') >= 120 && strlen($seo['description'] ?? '') <= 160 ? 'Ótimo' : (strlen($seo['description'] ?? '') > 0 ? 'Ajustar' : 'Ausente') }}
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="small">Imagem OG</span>
                                <span class="badge bg-{{ $seo['og_image'] ? 'success' : 'danger' }}">
                                    {{ $seo['og_image'] ? 'Configurada' : 'Ausente' }}
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="small">Palavras-chave</span>
                                <span class="badge bg-{{ $seo['keywords'] ? 'success' : 'warning' }}">
                                    {{ $seo['keywords'] ? 'Definidas' : 'Ausentes' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Links úteis --}}
                <div class="card mt-3">
                    <div class="card-header">
                        <span><i class="bi bi-link-45deg me-1"></i> Arquivos SEO</span>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <a href="{{ url('/sitemap.xml') }}" target="_blank" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-0">
                                <span><i class="bi bi-file-code me-2 text-primary"></i>sitemap.xml</span>
                                <i class="bi bi-box-arrow-up-right small text-muted"></i>
                            </a>
                            <a href="{{ url('/robots.txt') }}" target="_blank" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-0">
                                <span><i class="bi bi-file-text me-2 text-success"></i>robots.txt</span>
                                <i class="bi bi-box-arrow-up-right small text-muted"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- CARD 3: Pixels e Rastreamento --}}
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <span><i class="bi bi-broadcast me-1"></i> Pixels e Rastreamento</span>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Google Analytics (GA4)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-bar-chart-line"></i></span>
                                    <input type="text" name="ga_id" class="form-control"
                                           value="{{ $seo['ga_id'] }}" placeholder="G-XXXXXXXXXX">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Google Ads</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-megaphone"></i></span>
                                    <input type="text" name="gads_id" class="form-control"
                                           value="{{ $seo['gads_id'] }}" placeholder="AW-XXXXXXXXXX">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Google Tag Manager</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-tags"></i></span>
                                    <input type="text" name="gtm_id" class="form-control"
                                           value="{{ $seo['gtm_id'] }}" placeholder="GTM-XXXXXXX">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Facebook Pixel</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-facebook"></i></span>
                                    <input type="text" name="fb_pixel" class="form-control"
                                           value="{{ $seo['fb_pixel'] }}" placeholder="123456789012345">
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 pt-3 border-top">
                            <h6 class="small fw-semibold text-muted text-uppercase mb-2">Status</h6>
                            <div class="d-flex flex-wrap gap-2">
                                <span class="badge bg-{{ $seo['ga_id'] ? 'success' : 'secondary' }}-subtle text-{{ $seo['ga_id'] ? 'success' : 'secondary' }}">
                                    <i class="bi bi-{{ $seo['ga_id'] ? 'check-circle' : 'x-circle' }} me-1"></i>GA4
                                </span>
                                <span class="badge bg-{{ $seo['gads_id'] ? 'success' : 'secondary' }}-subtle text-{{ $seo['gads_id'] ? 'success' : 'secondary' }}">
                                    <i class="bi bi-{{ $seo['gads_id'] ? 'check-circle' : 'x-circle' }} me-1"></i>Ads
                                </span>
                                <span class="badge bg-{{ $seo['gtm_id'] ? 'success' : 'secondary' }}-subtle text-{{ $seo['gtm_id'] ? 'success' : 'secondary' }}">
                                    <i class="bi bi-{{ $seo['gtm_id'] ? 'check-circle' : 'x-circle' }} me-1"></i>GTM
                                </span>
                                <span class="badge bg-{{ $seo['fb_pixel'] ? 'success' : 'secondary' }}-subtle text-{{ $seo['fb_pixel'] ? 'success' : 'secondary' }}">
                                    <i class="bi bi-{{ $seo['fb_pixel'] ? 'check-circle' : 'x-circle' }} me-1"></i>Facebook
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- CARD 4: Dicas SEO --}}
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">
                        <span><i class="bi bi-lightbulb me-1"></i> Boas Práticas SEO</span>
                    </div>
                    <div class="card-body">
                        <div class="d-flex flex-column gap-3 small">
                            <div class="d-flex gap-2">
                                <i class="bi bi-check-circle-fill text-success flex-shrink-0 mt-1"></i>
                                <span class="text-muted"><strong>Título:</strong> Entre 30-60 caracteres. Inclua a palavra-chave principal no início.</span>
                            </div>
                            <div class="d-flex gap-2">
                                <i class="bi bi-check-circle-fill text-success flex-shrink-0 mt-1"></i>
                                <span class="text-muted"><strong>Descrição:</strong> Entre 120-160 caracteres. Deve ser persuasiva e conter um CTA.</span>
                            </div>
                            <div class="d-flex gap-2">
                                <i class="bi bi-check-circle-fill text-success flex-shrink-0 mt-1"></i>
                                <span class="text-muted"><strong>Imagem OG:</strong> 1200x630px, formato JPG/PNG. Aparece ao compartilhar links.</span>
                            </div>
                            <div class="d-flex gap-2">
                                <i class="bi bi-check-circle-fill text-success flex-shrink-0 mt-1"></i>
                                <span class="text-muted"><strong>Palavras-chave:</strong> 5-10 termos relevantes, separados por vírgula.</span>
                            </div>
                            <div class="d-flex gap-2">
                                <i class="bi bi-exclamation-triangle text-warning flex-shrink-0 mt-1"></i>
                                <span class="text-muted"><strong>GTM vs GA4:</strong> Se usar Tag Manager, configure o GA4 dentro dele para evitar duplicação.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function() {
    const form        = document.getElementById('frm-seo');
    const titleInput  = document.getElementById('seo-title');
    const descInput   = document.getElementById('seo-desc');
    const ogInput     = document.getElementById('seo-og-image');
    const ogDropzone  = document.getElementById('og-dropzone');
    const ogFileInput = document.getElementById('og-file-input');
    const csrf        = document.querySelector('meta[name=csrf-token]').content;

    // ── Contadores e preview ao vivo ─────────────────────────────────────────
    titleInput.addEventListener('input', function() {
        const len = this.value.length;
        document.getElementById('title-count').textContent = len;
        const bar = document.getElementById('title-bar');
        bar.style.width = Math.min(100, (len / 60) * 100) + '%';
        bar.className = 'progress-bar ' + (len >= 30 && len <= 60 ? 'bg-success' : len > 60 ? 'bg-warning' : 'bg-primary');
        document.getElementById('preview-title').textContent = this.value || 'Título do seu site';
    });

    descInput.addEventListener('input', function() {
        const len = this.value.length;
        document.getElementById('desc-count').textContent = len;
        const bar = document.getElementById('desc-bar');
        bar.style.width = Math.min(100, (len / 160) * 100) + '%';
        bar.className = 'progress-bar ' + (len >= 120 && len <= 160 ? 'bg-success' : len > 160 ? 'bg-warning' : 'bg-primary');
        document.getElementById('preview-desc').textContent = this.value || 'Descrição do seu site aparece aqui nos resultados de busca...';
    });

    // Atualiza preview ao digitar URL manualmente
    ogInput.addEventListener('input', () => setPreviewState(ogInput.value));

    // ── Upload OG Image ───────────────────────────────────────────────────────

    // Abrir seletor ao clicar no botão do input-group
    document.getElementById('btn-upload-og').addEventListener('click', () => ogFileInput.click());

    // Abrir seletor ao clicar na zona ou no botão interno
    ogDropzone.addEventListener('click', (e) => {
        // Não abrir se clicar nos botões de trocar/remover
        if (e.target.closest('#og-btn-change, #og-btn-remove, #og-btn-select')) return;
        const stateEmpty = document.getElementById('og-state-empty');
        if (!stateEmpty.classList.contains('d-none')) ogFileInput.click();
    });
    document.getElementById('og-btn-select')?.addEventListener('click', (e) => {
        e.stopPropagation();
        ogFileInput.click();
    });
    document.getElementById('og-btn-change')?.addEventListener('click', (e) => {
        e.stopPropagation();
        ogFileInput.click();
    });
    document.getElementById('og-btn-remove')?.addEventListener('click', (e) => {
        e.stopPropagation();
        ogInput.value = '';
        setPreviewState('');
    });

    // Seleção via input file
    ogFileInput.addEventListener('change', function() {
        if (this.files[0]) uploadOgFile(this.files[0]);
        this.value = ''; // reset para permitir re-selecionar o mesmo arquivo
    });

    // Drag & Drop
    ogDropzone.addEventListener('dragover', (e) => {
        e.preventDefault();
        ogDropzone.classList.add('drag-over');
    });
    ogDropzone.addEventListener('dragleave', (e) => {
        if (!ogDropzone.contains(e.relatedTarget)) ogDropzone.classList.remove('drag-over');
    });
    ogDropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        ogDropzone.classList.remove('drag-over');
        const file = e.dataTransfer.files[0];
        if (file) uploadOgFile(file);
    });

    // ── Função de upload com progresso ───────────────────────────────────────
    function uploadOgFile(file) {
        if (!file.type.startsWith('image/')) {
            window.toast?.error('Apenas imagens são aceitas.');
            return;
        }
        if (file.size > 10 * 1024 * 1024) {
            window.toast?.error('Imagem excede 10 MB.');
            return;
        }

        setProgressState(file.name);

        const fd = new FormData();
        fd.append('file', file);

        const xhr = new XMLHttpRequest();
        const startTime = Date.now();

        xhr.upload.addEventListener('progress', (e) => {
            if (!e.lengthComputable) return;
            const pct = Math.round((e.loaded / e.total) * 100);
            const elapsed = (Date.now() - startTime) / 1000;
            const speed = e.loaded / elapsed; // bytes/s
            const remaining = (e.total - e.loaded) / speed;

            document.getElementById('og-progress-bar').style.width = pct + '%';
            document.getElementById('og-progress-pct').textContent = pct + '%';
            document.getElementById('og-progress-eta').textContent =
                remaining < 1 ? 'Quase lá...' :
                remaining < 60 ? Math.ceil(remaining) + 's restantes' :
                Math.ceil(remaining / 60) + 'min restantes';
        });

        xhr.addEventListener('load', () => {
            try {
                const data = JSON.parse(xhr.responseText);
                if (xhr.status >= 200 && xhr.status < 300 && data.ok && data.url) {
                    ogInput.value = data.url;
                    setPreviewState(data.url);
                    window.toast?.success('Imagem OG enviada com sucesso!');
                } else {
                    setEmptyState();
                    window.toast?.error(data.message || data.error || 'Falha no upload.');
                }
            } catch {
                setEmptyState();
                window.toast?.error('Resposta inválida do servidor.');
            }
        });

        xhr.addEventListener('error', () => {
            setEmptyState();
            window.toast?.error('Erro de conexão ao enviar imagem.');
        });

        xhr.open('POST', '{{ url("/admin/uploads/seo") }}');
        xhr.setRequestHeader('X-CSRF-TOKEN', csrf);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.send(fd);
    }

    // ── Gerenciar estados da zona ─────────────────────────────────────────────
    function setEmptyState() {
        document.getElementById('og-state-empty').classList.remove('d-none');
        document.getElementById('og-state-progress').classList.add('d-none');
        document.getElementById('og-state-preview').classList.add('d-none');
    }

    function setProgressState(filename) {
        document.getElementById('og-state-empty').classList.add('d-none');
        document.getElementById('og-state-progress').classList.remove('d-none');
        document.getElementById('og-state-preview').classList.add('d-none');
        document.getElementById('og-progress-filename').textContent = filename;
        document.getElementById('og-progress-bar').style.width = '0%';
        document.getElementById('og-progress-pct').textContent = '0%';
        document.getElementById('og-progress-eta').textContent = 'Calculando...';
    }

    function setPreviewState(url) {
        if (url) {
            document.getElementById('og-state-empty').classList.add('d-none');
            document.getElementById('og-state-progress').classList.add('d-none');
            document.getElementById('og-state-preview').classList.remove('d-none');
            document.getElementById('og-preview-img').src = url;
        } else {
            setEmptyState();
        }
    }

    // ── Salvar formulário ─────────────────────────────────────────────────────
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('btn-save-seo');
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Salvando...';
        try {
            const r = await fetch('{{ route("admin.seo.update") }}', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: new FormData(form),
            });
            const data = await r.json();
            if (r.ok && data.ok) {
                window.toast?.success('Configurações SEO salvas com sucesso!');
            } else {
                window.toast?.error(data.errors ? Object.values(data.errors).flat().join(', ') : 'Erro ao salvar.');
            }
        } catch {
            window.toast?.error('Erro de conexão.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = orig;
        }
    });
})();
</script>
@endpush

@push('head')
<style>
/* ── Zona de Upload OG Premium ─────────────────────────────────────────────── */
.og-upload-zone {
    position: relative;
    border: 2px dashed var(--bs-border-color);
    border-radius: .75rem;
    min-height: 160px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: border-color .2s, background .2s, box-shadow .2s;
    overflow: hidden;
    background: var(--bs-body-bg);
}
.og-upload-zone:hover,
.og-upload-zone.drag-over {
    border-color: var(--bs-primary);
    background: color-mix(in srgb, var(--bs-primary) 8%, var(--bs-body-bg));
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--bs-primary) 12%, transparent);
}
.og-upload-zone.drag-over {
    border-style: solid;
}

/* Estado vazio */
#og-state-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: .5rem;
    padding: 2rem 1.5rem;
    text-align: center;
    width: 100%;
}
.og-upload-icon {
    font-size: 2.5rem;
    color: var(--bs-primary);
    opacity: .7;
    line-height: 1;
    margin-bottom: .25rem;
}
.og-upload-title {
    font-size: .95rem;
    font-weight: 600;
    color: var(--bs-body-color);
    margin: 0;
}
.og-upload-sub {
    font-size: .78rem;
    color: var(--bs-secondary-color);
    margin: 0;
}
.og-upload-btn {
    margin-top: .5rem;
    padding: .4rem 1.1rem;
    font-size: .82rem;
    font-weight: 600;
    border-radius: .5rem;
    border: 1.5px solid var(--bs-primary);
    background: transparent;
    color: var(--bs-primary);
    cursor: pointer;
    transition: background .15s, color .15s;
}
.og-upload-btn:hover {
    background: var(--bs-primary);
    color: #fff;
}

/* Estado progresso */
#og-state-progress {
    width: 100%;
    padding: 1.5rem 2rem;
}
.og-progress-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: .4rem;
    width: 100%;
}
.og-progress-icon {
    font-size: 2rem;
    color: var(--bs-primary);
    animation: og-pulse 1s ease-in-out infinite;
}
@keyframes og-pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50%       { opacity: .6; transform: scale(1.1); }
}
.og-progress-bar-wrap {
    width: 100%;
    height: 8px;
    background: var(--bs-border-color);
    border-radius: 99px;
    overflow: hidden;
}
.og-progress-bar {
    height: 100%;
    width: 0%;
    background: linear-gradient(90deg, var(--bs-primary), color-mix(in srgb, var(--bs-primary) 60%, #fff));
    border-radius: 99px;
    transition: width .2s ease;
}

/* Estado preview */
#og-state-preview {
    position: relative;
    width: 100%;
    min-height: 160px;
}
.og-preview-img {
    width: 100%;
    max-height: 220px;
    object-fit: cover;
    display: block;
    border-radius: .65rem;
}
.og-preview-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,.45);
    border-radius: .65rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .75rem;
    opacity: 0;
    transition: opacity .2s;
}
#og-state-preview:hover .og-preview-overlay {
    opacity: 1;
}
.og-preview-change,
.og-preview-remove {
    padding: .45rem 1rem;
    border-radius: .5rem;
    border: none;
    font-size: .82rem;
    font-weight: 600;
    cursor: pointer;
    transition: transform .15s;
}
.og-preview-change:hover,
.og-preview-remove:hover {
    transform: scale(1.05);
}
.og-preview-change {
    background: #fff;
    color: #333;
}
.og-preview-remove {
    background: #dc3545;
    color: #fff;
}
</style>
@endpush
