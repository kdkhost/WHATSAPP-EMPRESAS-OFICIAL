@extends('admin.layouts.app')
@section('title', 'PWA - Progressive Web App')

@push('head')
<style>
.pwa-page {
    --pwa-surface-soft: color-mix(in srgb, var(--bs-body-bg) 92%, var(--bs-primary) 8%);
    --pwa-border-soft: color-mix(in srgb, var(--bs-border-color) 75%, transparent);
}
.pwa-device-preview {
    width: 216px;
    border: 2px solid var(--bs-border-color);
    border-radius: 26px;
    overflow: hidden;
    background: var(--bs-body-bg);
    box-shadow: var(--app-shadow-premium);
}
.pwa-device-preview__status {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: .35rem .9rem;
    font-size: .64rem;
    color: #fff;
}
.pwa-device-preview__splash {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 190px;
    padding: 1rem;
}
.pwa-device-preview__home {
    padding: .75rem;
    text-align: center;
    background: var(--bs-tertiary-bg);
}
.pwa-device-preview__icon {
    width: 58px;
    height: 58px;
    border-radius: 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: .6rem;
}
.pwa-info-list {
    display: grid;
    gap: .8rem;
}
.pwa-info-item {
    display: flex;
    gap: .7rem;
    align-items: flex-start;
}
.pwa-info-item i {
    margin-top: .15rem;
}
</style>
@endpush

@section('content')
<div class="container-fluid pwa-page">
    <section class="admin-premium-hero mb-4">
        <div>
            <span class="admin-premium-hero__eyebrow">Aplicativo instalavel</span>
            <h1 class="admin-premium-hero__title">Identidade, manifesto e experiencia do PWA em um fluxo mais claro.</h1>
            <a href="{{ route('admin.pwa.menu.index') }}" class="btn btn-outline-primary btn-sm mt-2">
                <i class="bi bi-list me-1"></i> Configurar menu do footer
            </a>
            <p class="admin-premium-hero__text">
                Esta area foi reorganizada para separar identidade do app, comportamento de instalacao, icones e preview real, sem espalhar controles importantes pela pagina.
            </p>
        </div>
        <div class="dashboard-hero-notes">
            <div class="dashboard-note">
                <span class="dashboard-note__label">Manifesto</span>
                <span class="dashboard-note__value">Gerado dinamicamente pelo painel</span>
            </div>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Instalacao</span>
                <span class="dashboard-note__value">Prompt configuravel por toggle</span>
            </div>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Push</span>
                <span class="dashboard-note__value">Habilitacao central no administrativo</span>
            </div>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Preview</span>
                <span class="dashboard-note__value">Leitura visual imediata no mesmo fluxo</span>
            </div>
        </div>
    </section>

    <form id="frm-pwa">
        @csrf
        <div class="row g-4">
            <div class="col-xl-8">
                <div class="premium-panel">
                    <div class="premium-panel__header">
                        <div>
                            <span class="premium-panel__kicker">Identidade principal</span>
                            <h2 class="premium-panel__title">Manifesto e comportamento</h2>
                            <p class="premium-panel__subtitle mb-0">Defina como o app sera exibido, instalado e aberto no dispositivo do usuario.</p>
                        </div>
                        <button type="submit" class="btn btn-primary" id="btn-save-pwa">
                            <i class="bi bi-check2-circle me-1"></i> Salvar configuracoes
                        </button>
                    </div>
                    <div class="premium-panel__body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">Nome do app <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="pwa-name" class="form-control" required maxlength="120" value="{{ $pwa['name'] }}" placeholder="Sistema de Cobranca WhatsApp">
                                <small class="text-muted">Nome completo exibido na instalacao do aplicativo.</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Nome curto <span class="text-danger">*</span></label>
                                <input type="text" name="short_name" id="pwa-short" class="form-control" required maxlength="30" value="{{ $pwa['short_name'] }}" placeholder="Cobranca">
                                <div class="d-flex justify-content-between mt-1">
                                    <small class="text-muted">Rotulo do icone.</small>
                                    <small class="text-muted"><span id="short-count">{{ strlen($pwa['short_name']) }}</span>/12</small>
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Descricao</label>
                                <textarea name="description" class="form-control" rows="3" maxlength="255" placeholder="Envie cobrancas automatizadas via WhatsApp direto do seu celular.">{{ $pwa['description'] }}</textarea>
                                <small class="text-muted">Texto curto para instalacao e descricao do app.</small>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Cor do tema</label>
                                <div class="input-group">
                                    <input type="color" name="theme_color" id="pwa-theme-color" class="form-control form-control-color" value="{{ $pwa['theme_color'] }}">
                                    <input type="text" class="form-control" id="theme-color-hex" value="{{ $pwa['theme_color'] }}" maxlength="7">
                                </div>
                                <small class="text-muted">Barra superior e identidade visual.</small>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Cor de fundo</label>
                                <div class="input-group">
                                    <input type="color" name="background_color" id="pwa-bg-color" class="form-control form-control-color" value="{{ $pwa['background_color'] }}">
                                    <input type="text" class="form-control" id="bg-color-hex" value="{{ $pwa['background_color'] }}" maxlength="7">
                                </div>
                                <small class="text-muted">Splash screen de abertura.</small>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Start URL <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-link-45deg"></i></span>
                                    <input type="text" name="start_url" class="form-control" required value="{{ $pwa['start_url'] }}" placeholder="/?source=pwa">
                                </div>
                                <small class="text-muted">Endereco inicial ao abrir o app instalado.</small>
                            </div>

                            <div class="col-md-6">
                                <div class="d-flex align-items-center justify-content-between p-3 border rounded bg-body-tertiary">
                                    <div>
                                        <h6 class="mb-0 small fw-semibold">Prompt de instalacao</h6>
                                        <small class="text-muted">Exibe chamada de instalacao para visitantes.</small>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input type="hidden" name="install_prompt" value="0">
                                        <input class="form-check-input" type="checkbox" name="install_prompt" id="install-prompt" value="1" @checked($pwa['install_prompt']) style="width:2.5rem;height:1.25rem;">
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="d-flex align-items-center justify-content-between p-3 border rounded bg-body-tertiary">
                                    <div>
                                        <h6 class="mb-0 small fw-semibold">Notificacoes push</h6>
                                        <small class="text-muted">Habilita envio de alertas push no app.</small>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input type="hidden" name="push_enabled" value="0">
                                        <input class="form-check-input" type="checkbox" name="push_enabled" id="push-enabled" value="1" @checked($pwa['push_enabled']) style="width:2.5rem;height:1.25rem;">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card mb-3">
                    <div class="card-header">
                        <div>
                            <h3 class="dashboard-panel__title mb-0">Preview do app</h3>
                            <span class="dashboard-panel__subtitle">Leitura rapida de como a identidade aparece no celular.</span>
                        </div>
                    </div>
                    <div class="card-body text-center">
                        <div class="pwa-device-preview mx-auto">
                            <div id="preview-statusbar" class="pwa-device-preview__status" style="background:{{ $pwa['theme_color'] }};">
                                <span>9:41</span>
                                <span><i class="bi bi-wifi"></i> <i class="bi bi-battery-full"></i></span>
                            </div>
                            <div id="preview-splash" class="pwa-device-preview__splash" style="background:{{ $pwa['background_color'] }};">
                                <div class="pwa-device-preview__icon" style="background:{{ $pwa['theme_color'] }};">
                                    <i class="bi bi-whatsapp text-white fs-4"></i>
                                </div>
                                <span class="fw-semibold small" id="preview-app-name">{{ $pwa['short_name'] }}</span>
                            </div>
                            <div class="pwa-device-preview__home">
                                <div class="d-inline-flex align-items-center gap-2 rounded-3 border px-2 py-1 small">
                                    <i class="bi bi-whatsapp text-success"></i>
                                    <span id="preview-icon-label">{{ Str::limit($pwa['short_name'], 12) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">
                        <div>
                            <h3 class="dashboard-panel__title mb-0">Arquivos do PWA</h3>
                            <span class="dashboard-panel__subtitle">Assets e endpoints tecnicos do aplicativo instalavel.</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="border rounded p-3 bg-body-tertiary">
                            <label class="form-label fw-semibold">Icone principal do app</label>
                            <input type="file" class="form-control" id="pwa-icon-file" accept=".png,.jpg,.jpeg,.webp,.svg">
                            <small class="text-muted">Aceita PNG/JPG/WEBP/SVG. O sistema gera automaticamente 72, 96, 128, 144, 152, 192, 384 e 512.</small>
                            <div class="d-grid mt-2">
                                <button type="button" class="btn btn-outline-primary btn-sm" id="btn-upload-pwa-icon">
                                    <i class="bi bi-cloud-arrow-up me-1"></i> Salvar icones PWA
                                </button>
                            </div>
                        </div>
                        <div id="pwa-upload-result" class="small text-muted mt-2"></div>

                        <div class="mt-3">
                            <h6 class="small fw-semibold text-muted text-uppercase mb-2">Tamanhos necessarios</h6>
                            <div class="d-flex flex-wrap gap-1">
                                @foreach([72, 96, 128, 144, 192, 384, 512] as $size)
                                    <span class="badge bg-body-tertiary text-body border">{{ $size }}px</span>
                                @endforeach
                            </div>
                        </div>

                        <div class="d-flex gap-2 mt-3">
                            <a href="{{ url('/manifest.json') }}" target="_blank" class="btn btn-outline-primary btn-sm flex-fill">
                                <i class="bi bi-file-code me-1"></i> Manifest
                            </a>
                            <a href="{{ url('/sw.js') }}" target="_blank" class="btn btn-outline-secondary btn-sm flex-fill">
                                <i class="bi bi-gear me-1"></i> Service Worker
                            </a>
                        </div>
                        <div class="d-grid mt-2">
                            <button type="button" class="btn btn-outline-warning btn-sm" id="btn-refresh-pwa-cache">
                                <i class="bi bi-arrow-repeat me-1"></i> Atualizar versao de cache PWA
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3 class="dashboard-panel__title mb-0">Boas praticas</h3>
                            <span class="dashboard-panel__subtitle">Pontos que ajudam a manter o PWA consistente no uso real.</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="pwa-info-list small text-muted">
                            <div class="pwa-info-item">
                                <i class="bi bi-check-circle-fill text-success"></i>
                                <span>O app pode ser instalado na tela inicial sem depender de loja.</span>
                            </div>
                            <div class="pwa-info-item">
                                <i class="bi bi-check-circle-fill text-success"></i>
                                <span>O service worker permite cache e abertura mais rapida.</span>
                            </div>
                            <div class="pwa-info-item">
                                <i class="bi bi-check-circle-fill text-success"></i>
                                <span>As notificacoes push mantem o usuario dentro do fluxo operacional.</span>
                            </div>
                            <div class="pwa-info-item">
                                <i class="bi bi-check-circle-fill text-success"></i>
                                <span>O manifest e reconstruido dinamicamente com base nessas configuracoes.</span>
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
(function () {
    const form = document.getElementById('frm-pwa');

    function syncColor(pickerId, hexId) {
        const picker = document.getElementById(pickerId);
        const hex = document.getElementById(hexId);
        picker.addEventListener('input', () => {
            hex.value = picker.value;
            updatePreview();
        });
        hex.addEventListener('input', () => {
            if (/^#[0-9a-fA-F]{6}$/.test(hex.value)) {
                picker.value = hex.value;
                updatePreview();
            }
        });
    }

    syncColor('pwa-theme-color', 'theme-color-hex');
    syncColor('pwa-bg-color', 'bg-color-hex');

    const shortInput = document.querySelector('[name="short_name"]');
    const shortCount = document.getElementById('short-count');

    shortInput.addEventListener('input', function () {
        shortCount.textContent = this.value.length;
        updatePreview();
    });

    function updatePreview() {
        const themeColor = document.getElementById('pwa-theme-color').value;
        const bgColor = document.getElementById('pwa-bg-color').value;
        const shortName = shortInput.value || 'App';

        document.getElementById('preview-statusbar').style.background = themeColor;
        document.getElementById('preview-splash').style.background = bgColor;
        document.querySelector('.pwa-device-preview__icon').style.background = themeColor;
        document.getElementById('preview-app-name').textContent = shortName;
        document.getElementById('preview-icon-label').textContent = shortName.substring(0, 12);
    }

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        const button = document.getElementById('btn-save-pwa');
        const originalHtml = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Salvando...';

        try {
            const formData = new FormData(form);
            const response = await fetch('{{ route("admin.pwa.update") }}', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'Accept': 'application/json'
                },
                body: formData,
            });
            const data = await response.json();
            if (response.ok && data.ok) {
                window.toast.success('Configuracoes PWA salvas com sucesso!');
            } else {
                window.toast.error(data.errors ? Object.values(data.errors).flat().join(', ') : 'Erro ao salvar.');
            }
        } catch (error) {
            window.toast.error('Erro de conexao.');
        } finally {
            button.disabled = false;
            button.innerHTML = originalHtml;
        }
    });

    document.getElementById('btn-upload-pwa-icon')?.addEventListener('click', async () => {
        const input = document.getElementById('pwa-icon-file');
        const result = document.getElementById('pwa-upload-result');
        if (!(input instanceof HTMLInputElement) || !input.files?.length) {
            window.toast.error('Selecione um arquivo de icone.');
            return;
        }
        const fd = new FormData();
        fd.append('icon', input.files[0]);
        try {
            const response = await fetch('{{ route("admin.pwa.icons.upload") }}', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'Accept': 'application/json'
                },
                body: fd,
            });
            const data = await response.json();
            if (!response.ok || !data.ok) {
                throw new Error(data.error || 'Falha ao salvar icones PWA.');
            }
            result.textContent = 'Icones gerados e manifesto atualizado.';
            window.toast.success(data.message || 'Icones PWA salvos.');
        } catch (error) {
            window.toast.error(error instanceof Error ? error.message : 'Erro ao enviar icones.');
        }
    });

    document.getElementById('btn-refresh-pwa-cache')?.addEventListener('click', async () => {
        try {
            const response = await fetch('{{ route("admin.pwa.cache.refresh") }}', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'Accept': 'application/json'
                },
            });
            const data = await response.json();
            if (!response.ok || !data.ok) {
                throw new Error(data.error || 'Falha ao atualizar cache.');
            }
            window.toast.success(data.message || 'Cache PWA atualizado.');
        } catch (error) {
            window.toast.error(error instanceof Error ? error.message : 'Erro ao atualizar cache PWA.');
        }
    });
})();
</script>
@endpush
