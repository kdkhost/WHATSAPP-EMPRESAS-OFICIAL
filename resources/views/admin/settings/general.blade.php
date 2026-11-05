@extends('admin.layouts.app')
@section('title', 'Configurações Gerais')

@section('content')
<div class="container-fluid">

    {{-- Page Header --}}
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="mb-1"><i class="bi bi-gear me-2"></i>Configurações Gerais</h3>
            <p class="text-muted mb-0 small">Identidade visual, logo, favicon e textos do sistema.</p>
        </div>
    </div>

    <form id="frm-settings" enctype="multipart/form-data">
        @csrf
        <div class="row g-4">

            {{-- ============================================================
                 CARD 1: Identidade do Sistema
                 ============================================================ --}}
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title mb-0"><i class="bi bi-building me-2"></i>Identidade do Sistema</h3>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            {{-- Nome do Sistema --}}
                            <div class="col-md-8">
                                <label class="form-label">Nome do Sistema <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" required
                                       value="{{ $settings['name'] }}" placeholder="Meu Sistema">
                                <small class="text-muted">Exibido no título da página e no navbar.</small>
                            </div>

                            {{-- Cor Primária --}}
                            <div class="col-md-4">
                                <label class="form-label">Cor Primária</label>
                                <div class="input-group">
                                    <input type="color" id="color-picker" class="form-control form-control-color"
                                           name="primary_color" value="{{ $settings['primary_color'] }}">
                                    <input type="text" id="color-hex" class="form-control"
                                           value="{{ $settings['primary_color'] }}" maxlength="7" placeholder="#0d6efd">
                                </div>
                                <small class="text-muted">Cor principal dos botões e links.</small>
                            </div>

                            {{-- Logo URL --}}
                            <div class="col-md-6">
                                <label class="form-label">Logo (URL)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-image"></i></span>
                                    <input type="text" name="logo" id="logo-url" class="form-control"
                                           value="{{ $settings['logo'] }}" placeholder="https://... ou /assets/img/logo.png">
                                </div>
                            </div>

                            {{-- Favicon URL --}}
                            <div class="col-md-6">
                                <label class="form-label">Favicon (URL)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-star"></i></span>
                                    <input type="text" name="favicon" id="favicon-url" class="form-control"
                                           value="{{ $settings['favicon'] }}" placeholder="https://... ou /assets/img/favicon.ico">
                                </div>
                            </div>

                            {{-- Upload Drag-Drop Logo --}}
                            <div class="col-md-6">
                                <label class="form-label">Upload Logo</label>
                                <div class="upload-zone" id="logo-drop-zone" data-target="logo-url">
                                    <div class="upload-zone-content">
                                        <i class="bi bi-cloud-arrow-up fs-2 text-primary"></i>
                                        <p class="mb-0 small text-muted">Arraste a logo aqui ou <span class="text-primary fw-semibold">clique para selecionar</span></p>
                                        <small class="text-muted">PNG, JPG, SVG, WebP — Máx 2MB</small>
                                    </div>
                                    <input type="file" class="upload-zone-input" accept="image/*" data-field="logo-url">
                                </div>
                                @if($settings['logo'])
                                <div class="mt-2 d-flex align-items-center gap-2">
                                    <img src="{{ $settings['logo'] }}" alt="Logo atual" class="rounded border"
                                         style="max-height:40px;max-width:120px;" id="logo-preview">
                                    <small class="text-muted">Logo atual</small>
                                </div>
                                @else
                                <div class="mt-2 d-flex align-items-center gap-2" style="display:none !important;" id="logo-preview-wrap">
                                    <img src="" alt="Logo" class="rounded border" style="max-height:40px;max-width:120px;" id="logo-preview">
                                    <small class="text-muted">Logo atual</small>
                                </div>
                                @endif
                            </div>

                            {{-- Upload Drag-Drop Favicon --}}
                            <div class="col-md-6">
                                <label class="form-label">Upload Favicon</label>
                                <div class="upload-zone" id="favicon-drop-zone" data-target="favicon-url">
                                    <div class="upload-zone-content">
                                        <i class="bi bi-cloud-arrow-up fs-2 text-primary"></i>
                                        <p class="mb-0 small text-muted">Arraste o favicon aqui ou <span class="text-primary fw-semibold">clique para selecionar</span></p>
                                        <small class="text-muted">ICO, PNG, SVG — Máx 1MB</small>
                                    </div>
                                    <input type="file" class="upload-zone-input" accept="image/*,.ico" data-field="favicon-url">
                                </div>
                                @if($settings['favicon'])
                                <div class="mt-2 d-flex align-items-center gap-2">
                                    <img src="{{ $settings['favicon'] }}" alt="Favicon atual" class="rounded border"
                                         style="max-height:32px;max-width:32px;" id="favicon-preview">
                                    <small class="text-muted">Favicon atual</small>
                                </div>
                                @else
                                <div class="mt-2 d-flex align-items-center gap-2" style="display:none !important;" id="favicon-preview-wrap">
                                    <img src="" alt="Favicon" class="rounded border" style="max-height:32px;max-width:32px;" id="favicon-preview">
                                    <small class="text-muted">Favicon atual</small>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ============================================================
                 CARD 2: Preview (sidebar)
                 ============================================================ --}}
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title mb-0"><i class="bi bi-eye me-2"></i>Preview</h3>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">Visualize como a logo e favicon aparecem no navbar.</p>

                        {{-- Mini Navbar Preview --}}
                        <div class="border rounded p-3 bg-body-tertiary">
                            <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                                <img src="{{ $settings['logo'] ?: '/assets/img/logo-placeholder.png' }}"
                                     alt="Preview Logo" id="preview-logo"
                                     style="max-height:28px;max-width:100px;" class="rounded">
                                <span class="fw-semibold small" id="preview-name">{{ $settings['name'] }}</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <img src="{{ $settings['favicon'] ?: '/assets/img/favicon-placeholder.png' }}"
                                     alt="Preview Favicon" id="preview-favicon"
                                     style="width:16px;height:16px;" class="rounded">
                                <span class="small text-muted">Favicon na aba do navegador</span>
                            </div>
                        </div>

                        {{-- Color Preview --}}
                        <div class="mt-3">
                            <label class="form-label small">Cor primária aplicada:</label>
                            <div class="d-flex gap-2 align-items-center">
                                <div id="preview-color-swatch" class="rounded"
                                     style="width:36px;height:36px;background:{{ $settings['primary_color'] }};"></div>
                                <div>
                                    <button type="button" class="btn btn-sm" id="preview-btn"
                                            style="background:{{ $settings['primary_color'] }};color:#fff;">Botão exemplo</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Dicas --}}
                <div class="card mt-3">
                    <div class="card-header">
                        <h3 class="card-title mb-0"><i class="bi bi-lightbulb me-2"></i>Dicas</h3>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled small text-muted mb-0">
                            <li class="mb-2"><i class="bi bi-check-circle text-success me-1"></i> Logo recomendada: 200x50px, fundo transparente (PNG/SVG)</li>
                            <li class="mb-2"><i class="bi bi-check-circle text-success me-1"></i> Favicon: 32x32px ou 64x64px (ICO/PNG)</li>
                            <li class="mb-2"><i class="bi bi-check-circle text-success me-1"></i> Cor primária: use cores com bom contraste</li>
                            <li><i class="bi bi-check-circle text-success me-1"></i> Alterações são aplicadas imediatamente após salvar</li>
                        </ul>
                    </div>
                </div>
            </div>

            {{-- ============================================================
                 CARD 3: Rodapé e Textos
                 ============================================================ --}}
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title mb-0"><i class="bi bi-card-text me-2"></i>Rodapé e Textos</h3>
                        <button type="submit" class="btn btn-primary btn-sm" id="btn-save">
                            <i class="bi bi-check2-circle me-1"></i>Salvar Configurações
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">Texto do Rodapé</label>
                                <input type="text" name="footer_text" class="form-control"
                                       value="{{ $settings['footer_text'] }}"
                                       placeholder="© 2024 Minha Empresa. Todos os direitos reservados.">
                                <small class="text-muted">Exibido no rodapé de todas as páginas do painel.</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Preview do Rodapé</label>
                                <div class="border rounded p-2 bg-body-tertiary small text-muted" id="preview-footer">
                                    {{ $settings['footer_text'] ?: '© ' . date('Y') . ' Sistema' }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check2-circle me-1"></i>Salvar Configurações
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </form>
</div>
@endsection

@push('head')
<style>
.upload-zone {
    border: 2px dashed var(--bs-border-color);
    border-radius: var(--app-radius, 0.5rem);
    padding: 1.25rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
    background: var(--bs-body-bg);
}
.upload-zone:hover,
.upload-zone.dragover {
    border-color: var(--bs-primary);
    background: color-mix(in srgb, var(--bs-primary) 5%, transparent);
}
.upload-zone-input {
    position: absolute;
    inset: 0;
    opacity: 0;
    cursor: pointer;
}
</style>
@endpush

@push('scripts')
<script>
(function() {
    const form = document.getElementById('frm-settings');
    const colorPicker = document.getElementById('color-picker');
    const colorHex = document.getElementById('color-hex');

    // Sync color picker <-> hex input
    colorPicker.addEventListener('input', function() {
        colorHex.value = this.value;
        updateColorPreview(this.value);
    });
    colorHex.addEventListener('input', function() {
        if (/^#[0-9a-fA-F]{6}$/.test(this.value)) {
            colorPicker.value = this.value;
            updateColorPreview(this.value);
        }
    });

    function updateColorPreview(color) {
        document.getElementById('preview-color-swatch').style.background = color;
        document.getElementById('preview-btn').style.background = color;
    }

    // Live preview for name
    const nameInput = form.querySelector('[name="name"]');
    nameInput.addEventListener('input', function() {
        document.getElementById('preview-name').textContent = this.value || 'Sistema';
    });

    // Live preview for footer
    const footerInput = form.querySelector('[name="footer_text"]');
    footerInput.addEventListener('input', function() {
        document.getElementById('preview-footer').textContent = this.value || '© {{ date("Y") }} Sistema';
    });

    // Upload zones - drag & drop + click
    document.querySelectorAll('.upload-zone').forEach(function(zone) {
        const input = zone.querySelector('.upload-zone-input');
        const targetId = zone.dataset.target;

        zone.addEventListener('dragover', function(e) {
            e.preventDefault();
            zone.classList.add('dragover');
        });
        zone.addEventListener('dragleave', function() {
            zone.classList.remove('dragover');
        });
        zone.addEventListener('drop', function(e) {
            e.preventDefault();
            zone.classList.remove('dragover');
            if (e.dataTransfer.files.length) {
                handleFileUpload(e.dataTransfer.files[0], targetId);
            }
        });
        input.addEventListener('change', function() {
            if (this.files.length) {
                handleFileUpload(this.files[0], targetId);
            }
        });
    });

    function handleFileUpload(file, targetId) {
        const fd = new FormData();
        fd.append('file', file);
        fd.append('_token', document.querySelector('meta[name=csrf-token]').content);
        fd.append('replace_url', document.getElementById(targetId)?.value || '');

        const zone = document.querySelector('[data-target="' + targetId + '"]');
        zone.querySelector('.upload-zone-content').innerHTML = '<div class="spinner-border spinner-border-sm text-primary"></div><p class="mb-0 small mt-1">Enviando...</p>';

        fetch('{{ route("admin.uploads.store", "branding") }}', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
            body: fd
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok && data.url) {
                document.getElementById(targetId).value = data.url;
                // Update preview
                if (targetId === 'logo-url') {
                    document.getElementById('preview-logo').src = data.url;
                    const lp = document.getElementById('logo-preview');
                    if (lp) { lp.src = data.url; lp.style.display = ''; }
                    const lpw = document.getElementById('logo-preview-wrap');
                    if (lpw) lpw.style.removeProperty('display');
                } else {
                    document.getElementById('preview-favicon').src = data.url;
                    const fp = document.getElementById('favicon-preview');
                    if (fp) { fp.src = data.url; fp.style.display = ''; }
                    const fpw = document.getElementById('favicon-preview-wrap');
                    if (fpw) fpw.style.removeProperty('display');
                }
                window.toast.success('Arquivo enviado com sucesso!');
            } else {
                window.toast.error(data.message || data.error || 'Erro no upload');
            }
            resetZone(zone);
        })
        .catch(function() {
            window.toast.error('Erro ao enviar arquivo');
            resetZone(zone);
        });
    }

    function resetZone(zone) {
        const isLogo = zone.id === 'logo-drop-zone';
        zone.querySelector('.upload-zone-content').innerHTML =
            '<i class="bi bi-cloud-arrow-up fs-2 text-primary"></i>' +
            '<p class="mb-0 small text-muted">Arraste ' + (isLogo ? 'a logo' : 'o favicon') + ' aqui ou <span class="text-primary fw-semibold">clique para selecionar</span></p>' +
            '<small class="text-muted">' + (isLogo ? 'PNG, JPG, SVG, WebP — Máx 2MB' : 'ICO, PNG, SVG — Máx 1MB') + '</small>';
    }

    // Form submit via AJAX
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('btn-save');
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Salvando...';

        try {
            const fd = new FormData(form);
            const r = await fetch('{{ route("admin.settings.general.update") }}', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'Accept': 'application/json'
                },
                body: fd
            });

            const data = await r.json();
            if (r.ok && data.ok) {
                window.toast.success('Configurações gerais salvas com sucesso!');
            } else {
                const errors = data.errors ? Object.values(data.errors).flat().join(', ') : 'Erro ao salvar';
                window.toast.error(errors);
            }
        } catch (err) {
            window.toast.error('Erro de conexão. Tente novamente.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    });
})();
</script>
@endpush
