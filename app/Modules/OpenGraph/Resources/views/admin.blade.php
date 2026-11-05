@extends('admin.layouts.app')
@section('title', 'Open Graph')

@section('content')
<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="mb-1"><i class="bi bi-share me-2"></i>Open Graph</h3>
            <p class="text-muted mb-0 small">Configure as imagens geradas automaticamente para compartilhamento em redes sociais.</p>
        </div>
        <span class="badge bg-{{ $config['auto_generate'] ? 'success' : 'secondary' }} fs-6">
            <i class="bi bi-{{ $config['auto_generate'] ? 'check-circle' : 'x-circle' }} me-1"></i>
            {{ $config['auto_generate'] ? 'AUTO ATIVO' : 'MANUAL' }}
        </span>
    </div>

    <form id="og-form">
        @csrf
        <div class="row g-4">

            {{-- COLUNA ESQUERDA --}}
            <div class="col-lg-7">

                {{-- Card: Cores --}}
                <div class="card">
                    <div class="card-header">
                        <span><i class="bi bi-palette me-1"></i> Cores da Imagem OG</span>
                        <button type="submit" class="btn btn-primary btn-sm" id="btn-save">
                            <i class="bi bi-check2-circle me-1"></i>Salvar
                        </button>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">Defina as cores usadas na geração automática das imagens de compartilhamento (1200x630px).</p>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="text-center p-3 border rounded bg-body-tertiary">
                                    <input type="color" class="form-control form-control-color mx-auto mb-2" name="bg_color" id="og-bg"
                                           value="{{ $config['bg_color'] }}" style="width:56px;height:56px;border-radius:50%;padding:2px;">
                                    <div class="fw-semibold small">Fundo</div>
                                    <input type="text" class="form-control form-control-sm text-center mt-1" value="{{ $config['bg_color'] }}"
                                           data-sync="bg_color" maxlength="7" style="font-size:0.7rem;font-family:monospace;">
                                    <small class="text-muted" style="font-size:0.65rem;">Cor de fundo principal</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center p-3 border rounded bg-body-tertiary">
                                    <input type="color" class="form-control form-control-color mx-auto mb-2" name="text_color" id="og-text"
                                           value="{{ $config['text_color'] }}" style="width:56px;height:56px;border-radius:50%;padding:2px;">
                                    <div class="fw-semibold small">Texto</div>
                                    <input type="text" class="form-control form-control-sm text-center mt-1" value="{{ $config['text_color'] }}"
                                           data-sync="text_color" maxlength="7" style="font-size:0.7rem;font-family:monospace;">
                                    <small class="text-muted" style="font-size:0.65rem;">Título e subtítulo</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center p-3 border rounded bg-body-tertiary">
                                    <input type="color" class="form-control form-control-color mx-auto mb-2" name="accent_color" id="og-accent"
                                           value="{{ $config['accent_color'] }}" style="width:56px;height:56px;border-radius:50%;padding:2px;">
                                    <div class="fw-semibold small">Destaque</div>
                                    <input type="text" class="form-control form-control-sm text-center mt-1" value="{{ $config['accent_color'] }}"
                                           data-sync="accent_color" maxlength="7" style="font-size:0.7rem;font-family:monospace;">
                                    <small class="text-muted" style="font-size:0.65rem;">Barra, ícones, detalhes</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Card: Configurações --}}
                <div class="card mt-3">
                    <div class="card-header">
                        <span><i class="bi bi-gear me-1"></i> Configurações</span>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Cache (horas)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-clock"></i></span>
                                    <input type="number" class="form-control" name="cache_hours" value="{{ $config['cache_hours'] }}" min="1" max="720">
                                    <span class="input-group-text">horas</span>
                                </div>
                                <small class="text-muted">Tempo que a imagem fica em cache antes de regenerar.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Geração Automática</label>
                                <div class="d-flex align-items-center justify-content-between p-3 border rounded bg-body-tertiary">
                                    <div>
                                        <div class="fw-semibold small">Auto-gerar imagens</div>
                                        <small class="text-muted">Gera ao acessar a página</small>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="auto_generate" id="auto-gen" value="1"
                                               @checked($config['auto_generate']) style="width:2.5rem;height:1.25rem;">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-between">
                        <button type="button" class="btn btn-outline-danger btn-sm" id="btn-clear-cache">
                            <i class="bi bi-trash me-1"></i>Limpar Cache de Imagens
                        </button>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-check2-circle me-1"></i>Salvar Tudo
                        </button>
                    </div>
                </div>

                {{-- Card: Como Usar --}}
                <div class="card mt-3">
                    <div class="card-header">
                        <span><i class="bi bi-code-slash me-1"></i> Como Usar no Código</span>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="p-2 border rounded bg-body-tertiary">
                                    <div class="fw-semibold small mb-1"><i class="bi bi-file-code me-1"></i>Meta tags completas</div>
                                    <code class="small" style="font-size:0.7rem;">@@ogMeta('Titulo', 'Desc', 'slug')</code>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-2 border rounded bg-body-tertiary">
                                    <div class="fw-semibold small mb-1"><i class="bi bi-image me-1"></i>Apenas URL da imagem</div>
                                    <code class="small" style="font-size:0.7rem;">@@ogImage('Titulo', 'Sub')</code>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-2 border rounded bg-body-tertiary">
                                    <div class="fw-semibold small mb-1"><i class="bi bi-link-45deg me-1"></i>URL direta</div>
                                    <code class="small" style="font-size:0.7rem;">/og-image?title=Meu+Titulo</code>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- COLUNA DIREITA --}}
            <div class="col-lg-5">

                {{-- Preview --}}
                <div class="card">
                    <div class="card-header">
                        <span><i class="bi bi-eye me-1"></i> Preview da Imagem</span>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="btn-refresh-preview">
                            <i class="bi bi-arrow-clockwise me-1"></i>Atualizar
                        </button>
                    </div>
                    <div class="card-body text-center">
                        <div class="border rounded overflow-hidden mb-3">
                            <img id="og-preview" src="{{ route('admin.opengraph.preview', ['title' => config('app.name'), 'subtitle' => 'Sistema de cobranca via WhatsApp']) }}"
                                 class="img-fluid" alt="Preview OG" style="max-width:100%;">
                        </div>
                        <div class="d-flex justify-content-between small text-muted">
                            <span><i class="bi bi-aspect-ratio me-1"></i>1200 x 630px</span>
                            <span><i class="bi bi-file-image me-1"></i>PNG</span>
                        </div>
                    </div>
                </div>

                {{-- Preview Social --}}
                <div class="card mt-3">
                    <div class="card-header">
                        <span><i class="bi bi-phone me-1"></i> Como Aparece nas Redes</span>
                    </div>
                    <div class="card-body p-0">
                        {{-- WhatsApp preview --}}
                        <div class="p-3 border-bottom">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <i class="bi bi-whatsapp text-success"></i>
                                <small class="fw-semibold">WhatsApp</small>
                            </div>
                            <div class="border rounded overflow-hidden" style="max-width:280px;">
                                <div id="wa-preview-img" class="d-flex align-items-center justify-content-center" style="height:100px;background:{{ $config['bg_color'] }};">
                                    <span style="color:{{ $config['text_color'] }};font-weight:bold;font-size:0.8rem;">{{ config('app.name') }}</span>
                                </div>
                                <div class="p-2 bg-body-tertiary" style="font-size:0.7rem;">
                                    <div class="fw-semibold">{{ config('app.name') }}</div>
                                    <div class="text-muted">{{ url('/') }}</div>
                                </div>
                            </div>
                        </div>
                        {{-- Facebook preview --}}
                        <div class="p-3">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <i class="bi bi-facebook text-primary"></i>
                                <small class="fw-semibold">Facebook / LinkedIn</small>
                            </div>
                            <div class="border rounded overflow-hidden" style="max-width:320px;">
                                <div id="fb-preview-img" class="d-flex align-items-center justify-content-center" style="height:120px;background:{{ $config['bg_color'] }};">
                                    <span style="color:{{ $config['text_color'] }};font-weight:bold;font-size:0.9rem;">{{ config('app.name') }}</span>
                                </div>
                                <div class="p-2 bg-body-tertiary" style="font-size:0.7rem;">
                                    <div class="text-muted text-uppercase" style="font-size:0.6rem;">{{ parse_url(url('/'), PHP_URL_HOST) }}</div>
                                    <div class="fw-semibold">{{ config('app.name') }}</div>
                                    <div class="text-muted">Sistema de cobrança via WhatsApp</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Info --}}
                <div class="card mt-3">
                    <div class="card-header">
                        <span><i class="bi bi-info-circle me-1"></i> Sobre Open Graph</span>
                    </div>
                    <div class="card-body small text-muted">
                        <div class="d-flex gap-2 mb-2">
                            <i class="bi bi-check-circle-fill text-success flex-shrink-0"></i>
                            <span>Imagens OG são exibidas ao compartilhar links no WhatsApp, Facebook, Twitter e LinkedIn.</span>
                        </div>
                        <div class="d-flex gap-2 mb-2">
                            <i class="bi bi-check-circle-fill text-success flex-shrink-0"></i>
                            <span>Tamanho ideal: <strong>1200x630px</strong> (proporção 1.91:1).</span>
                        </div>
                        <div class="d-flex gap-2">
                            <i class="bi bi-check-circle-fill text-success flex-shrink-0"></i>
                            <span>O sistema gera automaticamente com título, subtítulo e cores configuradas.</span>
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
    var form = document.getElementById('og-form');
    var csrf = document.querySelector('meta[name=csrf-token]').content;

    // Sync color pickers
    form.querySelectorAll('input[type=color]').forEach(function(picker) {
        var text = form.querySelector('[data-sync="' + picker.name + '"]');
        if (!text) return;
        picker.addEventListener('input', function() { text.value = picker.value; updateMiniPreview(); });
        text.addEventListener('input', function() { if (/^#[0-9a-f]{6}$/i.test(text.value)) { picker.value = text.value; updateMiniPreview(); } });
    });

    function updateMiniPreview() {
        var bg = document.getElementById('og-bg').value;
        var txt = document.getElementById('og-text').value;
        document.getElementById('wa-preview-img').style.background = bg;
        document.getElementById('wa-preview-img').querySelector('span').style.color = txt;
        document.getElementById('fb-preview-img').style.background = bg;
        document.getElementById('fb-preview-img').querySelector('span').style.color = txt;
    }

    // Save
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        var btn = document.getElementById('btn-save');
        var orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Salvando...';

        var data = {};
        form.querySelectorAll('input[type=color]').forEach(function(el) { data[el.name] = el.value; });
        data.cache_hours = parseInt(form.querySelector('[name=cache_hours]').value);
        data.auto_generate = document.getElementById('auto-gen').checked;

        fetch('{{ route("admin.opengraph.update") }}', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(function(r) { return r.json(); })
        .then(function(d) { btn.disabled = false; btn.innerHTML = orig; if (d.ok) window.toast.success('Configurações OG salvas!'); })
        .catch(function() { btn.disabled = false; btn.innerHTML = orig; window.toast.error('Erro de conexão.'); });
    });

    // Clear cache
    document.getElementById('btn-clear-cache').addEventListener('click', function() {
        Swal.fire({
            title: 'Limpar Cache?',
            text: 'Limpar todas as imagens OG em cache?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sim, limpar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('{{ route("admin.opengraph.clear") }}', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
                })
                .then(function(r) { return r.json(); })
                .then(function(d) { if (d.ok) window.toast.success(d.message); });
            }
        });
    });

    // Refresh preview
    document.getElementById('btn-refresh-preview').addEventListener('click', function() {
        var img = document.getElementById('og-preview');
        img.src = '{{ route("admin.opengraph.preview") }}?title={{ urlencode(config("app.name")) }}&subtitle=Preview+atualizado&t=' + Date.now();
    });
})();
</script>
@endpush
