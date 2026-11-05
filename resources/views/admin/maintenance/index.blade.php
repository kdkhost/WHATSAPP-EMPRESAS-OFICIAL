@extends('admin.layouts.app')
@section('title', 'Modo de Manutencao')

@section('content')
<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="mb-1"><i class="bi bi-tools me-2"></i>Modo de Manutenção</h3>
            <p class="text-muted mb-0 small">Ative o modo de manutenção para bloquear o acesso público durante atualizações.</p>
        </div>
        <span class="badge bg-{{ $settings['enabled'] ? 'danger' : 'success' }} fs-6" id="status-badge">
            <i class="bi bi-{{ $settings['enabled'] ? 'exclamation-triangle' : 'check-circle' }} me-1"></i>
            {{ $settings['enabled'] ? 'MANUTENÇÃO ATIVA' : 'SISTEMA ONLINE' }}
        </span>
    </div>

    <div class="row g-4">

        {{-- CARD 1: Configurações --}}
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">
                    <span><i class="bi bi-gear me-1"></i> Configurações de Manutenção</span>
                    <button type="submit" form="frm-maintenance" class="btn btn-primary btn-sm" id="btn-save">
                        <i class="bi bi-check2-circle me-1"></i>Salvar
                    </button>
                </div>
                <div class="card-body">
                    <form id="frm-maintenance">
                        @csrf
                        <div class="row g-3">

                            {{-- Toggle principal --}}
                            <div class="col-12">
                                <div class="d-flex align-items-center justify-content-between p-3 border rounded {{ $settings['enabled'] ? 'border-danger bg-danger bg-opacity-10' : 'bg-body-tertiary' }}" id="toggle-wrapper">
                                    <div>
                                        <h6 class="mb-1"><i class="bi bi-power me-1"></i> Ativar Modo de Manutenção</h6>
                                        <small class="text-muted">Quando ativo, visitantes veem a página de manutenção. Admins e dispositivos confiáveis continuam com acesso.</small>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="enabled" id="enabled" value="1"
                                               @checked($settings['enabled']) style="width:3rem;height:1.5rem;">
                                    </div>
                                </div>
                            </div>

                            {{-- Título --}}
                            <div class="col-md-6">
                                <label class="form-label">Título da Página</label>
                                <input type="text" name="title" id="maint-title" class="form-control"
                                       value="{{ $settings['title'] }}" placeholder="Em manutenção" maxlength="120">
                                <small class="text-muted">Título exibido na página de manutenção.</small>
                            </div>

                            {{-- Reativar em --}}
                            <div class="col-md-6">
                                <label class="form-label">Previsão de Retorno</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-calendar-event"></i></span>
                                    <input type="datetime-local" name="until_at" class="form-control"
                                           value="{{ $settings['until_at'] }}">
                                </div>
                                <small class="text-muted">Quando o sistema voltará automaticamente (opcional).</small>
                            </div>

                            {{-- Mensagem --}}
                            <div class="col-12">
                                <label class="form-label">Mensagem para Visitantes</label>
                                <textarea name="message" id="maint-message" class="form-control" rows="4"
                                          placeholder="Estamos realizando melhorias no sistema. Voltaremos em breve!">{{ $settings['message'] }}</textarea>
                                <small class="text-muted">Mensagem exibida na página de manutenção para os visitantes.</small>
                            </div>

                        </div>
                    </form>
                </div>
                <div class="card-footer d-flex justify-content-end">
                    <button type="submit" form="frm-maintenance" class="btn btn-primary">
                        <i class="bi bi-check2-circle me-1"></i>Salvar Configurações
                    </button>
                </div>
            </div>

            {{-- Preview --}}
            <div class="card mt-3">
                <div class="card-header">
                    <span><i class="bi bi-eye me-1"></i> Preview da Página de Manutenção</span>
                </div>
                <div class="card-body p-0">
                    <div id="preview-container" class="position-relative text-center" style="min-height:220px;overflow:hidden;border-radius:0 0 var(--app-radius) var(--app-radius);">
                        {{-- Background image --}}
                        <div id="preview-bg" style="position:absolute;inset:0;background-size:cover;background-position:center;background-image:url('{{ $settings['background_image'] }}');"></div>
                        {{-- Overlay --}}
                        <div id="preview-overlay" style="position:absolute;inset:0;background:{{ $settings['overlay_color'] }};opacity:{{ ($settings['background_opacity'] ?? 50) / 100 }};"></div>
                        {{-- Content --}}
                        <div class="position-relative d-flex align-items-center justify-content-center" style="min-height:220px;z-index:1;">
                            <div id="preview-content" style="color:{{ $settings['text_color'] }};">
                                <i class="bi bi-tools display-4 mb-2 d-block"></i>
                                <h4 class="fw-bold" id="preview-title">{{ $settings['title'] ?: 'Em manutenção' }}</h4>
                                <p id="preview-message">{{ $settings['message'] ?: 'Estamos realizando melhorias...' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Background Config --}}
            <div class="card mt-3">
                <div class="card-header">
                    <span><i class="bi bi-image me-1"></i> Background da Página Pública</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        {{-- URL da imagem --}}
                        <div class="col-12">
                            <label class="form-label">Imagem de Fundo (URL)</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-image"></i></span>
                                <input type="text" name="background_image" id="bg-image-url" class="form-control" form="frm-maintenance"
                                       value="{{ $settings['background_image'] }}"
                                       placeholder="https://images.unsplash.com/photo-...">
                            </div>
                            <small class="text-muted">URL de uma imagem de alta resolução. Recomendado: 1920x1080px.</small>
                        </div>

                        {{-- Upload --}}
                        <div class="col-12">
                            <div data-upload data-url="{{ route('admin.uploads.store', 'maintenance') }}" data-max-mb="10" id="bg-upload-zone">
                                <i class="bi bi-cloud-arrow-up fs-3 text-primary"></i>
                                <p class="mb-0 small text-muted">Ou arraste uma imagem aqui</p>
                            </div>
                            <div data-upload-progress class="mt-2"></div>
                        </div>

                        {{-- Opacidade do overlay --}}
                        <div class="col-12">
                            <label class="form-label">Opacidade do Overlay: <strong id="opacity-value">{{ $settings['background_opacity'] ?? 50 }}%</strong></label>
                            <input type="range" class="form-range" id="opacity-slider" name="background_opacity" form="frm-maintenance"
                                   min="0" max="100" step="5" value="{{ $settings['background_opacity'] ?? 50 }}">
                            <div class="d-flex justify-content-between small text-muted">
                                <span>0% (sem overlay)</span>
                                <span>50%</span>
                                <span>100% (totalmente escuro)</span>
                            </div>
                        </div>

                        {{-- Cor do overlay --}}
                        <div class="col-md-6">
                            <label class="form-label">Cor do Overlay</label>
                            <div class="input-group">
                                <input type="color" name="overlay_color" id="overlay-color" class="form-control form-control-color" form="frm-maintenance"
                                       value="{{ $settings['overlay_color'] ?? '#000000' }}">
                                <input type="text" class="form-control" id="overlay-color-hex"
                                       value="{{ $settings['overlay_color'] ?? '#000000' }}" maxlength="7">
                            </div>
                        </div>

                        {{-- Cor do texto --}}
                        <div class="col-md-6">
                            <label class="form-label">Cor do Texto</label>
                            <div class="input-group">
                                <input type="color" name="text_color" id="text-color" class="form-control form-control-color" form="frm-maintenance"
                                       value="{{ $settings['text_color'] ?? '#ffffff' }}">
                                <input type="text" class="form-control" id="text-color-hex"
                                       value="{{ $settings['text_color'] ?? '#ffffff' }}" maxlength="7">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- SIDEBAR --}}
        <div class="col-lg-5">

            {{-- Dispositivos Confiáveis --}}
            <div class="card">
                <div class="card-header">
                    <span><i class="bi bi-shield-check me-1"></i> Dispositivos Confiáveis</span>
                    <span class="badge bg-primary">{{ count($devices) }}</span>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">Dispositivos que continuam com acesso durante a manutenção.</p>

                    {{-- Formulário adicionar --}}
                    <form id="frm-device" class="row g-2 mb-3">
                        @csrf
                        <div class="col-md-4">
                            <input class="form-control form-control-sm" name="label" placeholder="Apelido" required>
                        </div>
                        <div class="col-md-4">
                            <input class="form-control form-control-sm" name="ip" placeholder="IP (ex: 192.168.1.1)">
                        </div>
                        <div class="col-md-4">
                            <button class="btn btn-sm btn-success w-100">
                                <i class="bi bi-plus-lg me-1"></i>Adicionar
                            </button>
                        </div>
                    </form>

                    {{-- Lista de dispositivos --}}
                    @if(count($devices))
                    <div class="table-responsive">
                        <table class="table table-sm table-hover m-0">
                            <thead class="table-light">
                                <tr><th>Apelido</th><th>IP</th><th>MAC</th><th></th></tr>
                            </thead>
                            <tbody>
                                @foreach($devices as $d)
                                <tr id="dev-{{ $d->id }}">
                                    <td><strong>{{ $d->label }}</strong></td>
                                    <td><code class="small">{{ $d->ip }}</code></td>
                                    <td><code class="small">{{ $d->mac ?: '-' }}</code></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-danger" data-del-device="{{ $d->id }}" title="Remover">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="text-center py-3 text-muted">
                        <i class="bi bi-shield display-6 d-block mb-2"></i>
                        <small>Nenhum dispositivo cadastrado.<br>Adicione seu IP para manter acesso durante a manutenção.</small>
                    </div>
                    @endif

                    {{-- Seu IP atual --}}
                    <div class="mt-3 p-2 bg-body-tertiary rounded d-flex justify-content-between align-items-center">
                        <small class="text-muted"><i class="bi bi-globe me-1"></i>Seu IP atual:</small>
                        <code class="small">{{ request()->ip() }}</code>
                    </div>
                </div>
            </div>

            {{-- Informações --}}
            <div class="card mt-3">
                <div class="card-header">
                    <span><i class="bi bi-info-circle me-1"></i> Como Funciona</span>
                </div>
                <div class="card-body">
                    <div class="small text-muted">
                        <div class="d-flex gap-2 mb-2">
                            <i class="bi bi-1-circle-fill text-primary flex-shrink-0"></i>
                            <span>Ao ativar, todos os visitantes veem a página de manutenção.</span>
                        </div>
                        <div class="d-flex gap-2 mb-2">
                            <i class="bi bi-2-circle-fill text-primary flex-shrink-0"></i>
                            <span>Administradores logados continuam com acesso normal ao painel.</span>
                        </div>
                        <div class="d-flex gap-2 mb-2">
                            <i class="bi bi-3-circle-fill text-primary flex-shrink-0"></i>
                            <span>Dispositivos confiáveis (por IP) também mantêm acesso.</span>
                        </div>
                        <div class="d-flex gap-2">
                            <i class="bi bi-4-circle-fill text-primary flex-shrink-0"></i>
                            <span>Se definir previsão de retorno, o sistema desativa automaticamente.</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Atalho rápido --}}
            <div class="card mt-3 border-warning">
                <div class="card-body d-flex gap-2 align-items-center">
                    <i class="bi bi-lightning-charge text-warning fs-4"></i>
                    <div class="small">
                        <strong>Atalho rápido:</strong>
                        <span class="text-muted">Para ativar/desativar rapidamente, use o toggle acima e clique Salvar.</span>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
(function() {
    const form = document.getElementById('frm-maintenance');
    const enabledToggle = document.getElementById('enabled');
    const toggleWrapper = document.getElementById('toggle-wrapper');
    const statusBadge = document.getElementById('status-badge');
    const titleInput = document.getElementById('maint-title');
    const messageInput = document.getElementById('maint-message');
    const previewTitle = document.getElementById('preview-title');
    const previewMessage = document.getElementById('preview-message');

    // Toggle visual feedback
    enabledToggle.addEventListener('change', function() {
        if (this.checked) {
            toggleWrapper.classList.add('border-danger', 'bg-danger', 'bg-opacity-10');
            toggleWrapper.classList.remove('bg-body-tertiary');
            statusBadge.className = 'badge bg-danger fs-6';
            statusBadge.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>MANUTENÇÃO ATIVA';
        } else {
            toggleWrapper.classList.remove('border-danger', 'bg-danger', 'bg-opacity-10');
            toggleWrapper.classList.add('bg-body-tertiary');
            statusBadge.className = 'badge bg-success fs-6';
            statusBadge.innerHTML = '<i class="bi bi-check-circle me-1"></i>SISTEMA ONLINE';
        }
    });

    // Live preview
    titleInput.addEventListener('input', function() {
        previewTitle.textContent = this.value || 'Em manutenção';
    });
    messageInput.addEventListener('input', function() {
        previewMessage.textContent = this.value || 'Estamos realizando melhorias...';
    });

    // Live preview - background
    const bgImageInput = document.getElementById('bg-image-url');
    const opacitySlider = document.getElementById('opacity-slider');
    const opacityValue = document.getElementById('opacity-value');
    const overlayColor = document.getElementById('overlay-color');
    const overlayColorHex = document.getElementById('overlay-color-hex');
    const textColor = document.getElementById('text-color');
    const textColorHex = document.getElementById('text-color-hex');
    const previewBg = document.getElementById('preview-bg');
    const previewOverlay = document.getElementById('preview-overlay');
    const previewContent = document.getElementById('preview-content');

    bgImageInput.addEventListener('input', function() {
        previewBg.style.backgroundImage = this.value ? "url('" + this.value + "')" : 'none';
    });

    opacitySlider.addEventListener('input', function() {
        opacityValue.textContent = this.value + '%';
        previewOverlay.style.opacity = this.value / 100;
    });

    overlayColor.addEventListener('input', function() {
        overlayColorHex.value = this.value;
        previewOverlay.style.background = this.value;
    });
    overlayColorHex.addEventListener('input', function() {
        if (/^#[0-9a-fA-F]{6}$/.test(this.value)) { overlayColor.value = this.value; previewOverlay.style.background = this.value; }
    });

    textColor.addEventListener('input', function() {
        textColorHex.value = this.value;
        previewContent.style.color = this.value;
    });
    textColorHex.addEventListener('input', function() {
        if (/^#[0-9a-fA-F]{6}$/.test(this.value)) { textColor.value = this.value; previewContent.style.color = this.value; }
    });

    // Upload zone - set URL after upload
    const uploadZone = document.getElementById('bg-upload-zone');
    if (uploadZone) {
        uploadZone.addEventListener('upload:done', function(e) {
            try {
                const data = JSON.parse(e.detail.response);
                if (data.url) {
                    bgImageInput.value = data.url;
                    previewBg.style.backgroundImage = "url('" + data.url + "')";
                }
            } catch(err) {}
        });
    }

    // Save maintenance settings
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('btn-save');
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Salvando...';

        try {
            const fd = new FormData(form);
            fd.set('enabled', enabledToggle.checked ? '1' : '0');

            const r = await fetch('{{ route("admin.maintenance.update") }}', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                body: fd,
            });
            const data = await r.json();
            if (r.ok && data.ok) {
                window.toast.success(enabledToggle.checked ? 'Manutenção ATIVADA!' : 'Sistema voltou ao NORMAL!');
            } else {
                window.toast.error(data.errors ? Object.values(data.errors).flat().join(', ') : 'Erro ao salvar.');
            }
        } catch (err) {
            window.toast.error('Erro de conexão.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    });

    // Add device
    document.getElementById('frm-device').addEventListener('submit', async function(e) {
        e.preventDefault();
        const fd = new FormData(this);
        const btn = this.querySelector('button');
        btn.disabled = true;

        try {
            const r = await fetch('{{ route("admin.maintenance.devices.store") }}', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                body: fd,
            });
            if (r.ok) {
                window.toast.success('Dispositivo adicionado!');
                setTimeout(() => location.reload(), 500);
            } else {
                const data = await r.json();
                window.toast.error(data.errors ? Object.values(data.errors).flat().join(', ') : 'Erro ao adicionar.');
            }
        } catch (err) {
            window.toast.error('Erro de conexão.');
        }
        btn.disabled = false;
    });

    // Delete device
    document.querySelectorAll('[data-del-device]').forEach(function(btn) {
        btn.addEventListener('click', async function() {
            const id = this.dataset.delDevice;
            const ok = await Swal.fire({
                icon: 'warning',
                title: 'Remover dispositivo?',
                text: 'Este dispositivo deixara de furar a pagina de manutencao.',
                showCancelButton: true,
                confirmButtonText: 'Remover',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#64748b',
                background: getComputedStyle(document.body).getPropertyValue('--bs-body-bg'),
                color: getComputedStyle(document.body).getPropertyValue('--bs-body-color'),
            });
            if (!ok.isConfirmed) return;

            const r = await fetch('{{ url("/admin/maintenance/devices") }}/' + id, {
                method: 'DELETE', credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
            });
            if (r.ok) {
                document.getElementById('dev-' + id).remove();
                window.toast.success('Dispositivo removido.');
            }
        });
    });
})();
</script>
@endpush
