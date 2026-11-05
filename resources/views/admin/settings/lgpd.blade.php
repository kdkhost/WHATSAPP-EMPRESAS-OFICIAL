@extends('admin.layouts.app')
@section('title', 'LGPD - Privacidade')

@section('content')
<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="mb-1"><i class="bi bi-shield-check me-2"></i>LGPD - Política de Privacidade</h3>
            <p class="text-muted mb-0 small">Configure o banner de consentimento, formato, cores e política de privacidade.</p>
        </div>
        <span class="badge bg-{{ $lgpd['enabled'] ? 'success' : 'secondary' }} fs-6" id="status-main-badge">
            <i class="bi bi-{{ $lgpd['enabled'] ? 'check-circle' : 'x-circle' }} me-1"></i>
            {{ $lgpd['enabled'] ? 'BANNER ATIVO' : 'BANNER INATIVO' }}
        </span>
    </div>

    <form id="frm-lgpd">
        @csrf
        <div class="row g-4">

            {{-- COLUNA ESQUERDA --}}
            <div class="col-lg-7">

                {{-- Card Conteúdo --}}
                <div class="card">
                    <div class="card-header">
                        <span><i class="bi bi-chat-square-text me-1"></i> Conteúdo do Banner</span>
                        <button type="submit" class="btn btn-primary btn-sm" id="btn-save-lgpd">
                            <i class="bi bi-check2-circle me-1"></i>Salvar
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            {{-- Toggle --}}
                            <div class="col-12">
                                <div class="d-flex align-items-center justify-content-between p-3 border rounded {{ $lgpd['enabled'] ? 'border-success bg-success bg-opacity-10' : 'bg-body-tertiary' }}" id="toggle-wrapper">
                                    <div>
                                        <h6 class="mb-1"><i class="bi bi-power me-1"></i> Exibir Banner LGPD</h6>
                                        <small class="text-muted">Novos visitantes veem o banner de consentimento.</small>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="enabled" id="lgpd-enabled" value="1"
                                               @checked($lgpd['enabled']) style="width:3rem;height:1.5rem;">
                                    </div>
                                </div>
                            </div>

                            {{-- Texto --}}
                            <div class="col-12">
                                <label class="form-label">Texto do Banner <span class="text-danger">*</span></label>
                                <textarea name="text" id="lgpd-text" class="form-control" rows="3" required maxlength="500"
                                          placeholder="Utilizamos cookies e tecnologias semelhantes para melhorar sua experiência...">{{ $lgpd['text'] }}</textarea>
                                <div class="d-flex justify-content-between mt-1">
                                    <small class="text-muted">Mensagem principal.</small>
                                    <small class="text-muted"><span id="char-count">{{ strlen($lgpd['text'] ?? '') }}</span>/500</small>
                                </div>
                            </div>

                            {{-- URL + Botões --}}
                            <div class="col-12">
                                <label class="form-label">URL da Política de Privacidade <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-link-45deg"></i></span>
                                    <input type="text" name="policy_url" class="form-control" required
                                           value="{{ $lgpd['policy_url'] }}" placeholder="/politica-de-privacidade">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Botão Aceitar</label>
                                <input type="text" name="btn_accept" id="lgpd-btn-accept" class="form-control" required
                                       maxlength="30" value="{{ $lgpd['btn_accept'] }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Botão Recusar</label>
                                <input type="text" name="btn_decline" id="lgpd-btn-decline" class="form-control" required
                                       maxlength="30" value="{{ $lgpd['btn_decline'] }}">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Card Formato --}}
                <div class="card mt-3">
                    <div class="card-header">
                        <span><i class="bi bi-layout-split me-1"></i> Formato do Banner</span>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">Escolha como o banner será exibido para os visitantes:</p>
                        <div class="row g-3">
                            @php
                                $formats = [
                                    'footer' => ['label' => 'Banner no Rodapé', 'icon' => 'bi-layout-sidebar-reverse', 'desc' => 'Barra fixa no rodapé da página'],
                                    'top' => ['label' => 'Banner no Topo', 'icon' => 'bi-layout-sidebar', 'desc' => 'Barra fixa no topo da página'],
                                    'popup' => ['label' => 'Popup Central', 'icon' => 'bi-window-stack', 'desc' => 'Modal centralizado com overlay'],
                                    'central' => ['label' => 'Card Flutuante', 'icon' => 'bi-pip', 'desc' => 'Card flutuante no canto inferior'],
                                ];
                            @endphp
                            @foreach($formats as $key => $fmt)
                            <div class="col-md-6">
                                <label class="d-block">
                                    <input type="radio" name="format" value="{{ $key }}" class="btn-check format-radio"
                                           id="fmt-{{ $key }}" @checked(($lgpd['format'] ?? 'footer') === $key)>
                                    <div class="border rounded p-3 text-center format-option {{ ($lgpd['format'] ?? 'footer') === $key ? 'border-primary bg-primary bg-opacity-10' : '' }}" data-format="{{ $key }}">
                                        <i class="bi {{ $fmt['icon'] }} fs-3 d-block mb-1 text-primary"></i>
                                        <strong class="small d-block">{{ $fmt['label'] }}</strong>
                                        <small class="text-muted">{{ $fmt['desc'] }}</small>
                                    </div>
                                </label>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Card Cores --}}
                <div class="card mt-3">
                    <div class="card-header">
                        <span><i class="bi bi-palette me-1"></i> Cores do Banner</span>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">Personalize as cores. O padrão segue o tema do sistema.</p>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label small">Fundo</label>
                                <div class="input-group input-group-sm">
                                    <input type="color" name="bg_color" id="lgpd-bg-color" class="form-control form-control-color"
                                           value="{{ $lgpd['bg_color'] ?? '#1a1a2e' }}">
                                    <input type="text" class="form-control" id="bg-color-hex"
                                           value="{{ $lgpd['bg_color'] ?? '#1a1a2e' }}" maxlength="7">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Texto</label>
                                <div class="input-group input-group-sm">
                                    <input type="color" name="text_color" id="lgpd-text-color" class="form-control form-control-color"
                                           value="{{ $lgpd['text_color'] ?? '#ffffff' }}">
                                    <input type="text" class="form-control" id="text-color-hex"
                                           value="{{ $lgpd['text_color'] ?? '#ffffff' }}" maxlength="7">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Botão (fundo)</label>
                                <div class="input-group input-group-sm">
                                    <input type="color" name="btn_bg_color" id="lgpd-btn-bg" class="form-control form-control-color"
                                           value="{{ $lgpd['btn_bg_color'] ?? '#198754' }}">
                                    <input type="text" class="form-control" id="btn-bg-hex"
                                           value="{{ $lgpd['btn_bg_color'] ?? '#198754' }}" maxlength="7">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Botão (texto)</label>
                                <div class="input-group input-group-sm">
                                    <input type="color" name="btn_text_color" id="lgpd-btn-text" class="form-control form-control-color"
                                           value="{{ $lgpd['btn_text_color'] ?? '#ffffff' }}">
                                    <input type="text" class="form-control" id="btn-text-hex"
                                           value="{{ $lgpd['btn_text_color'] ?? '#ffffff' }}" maxlength="7">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check2-circle me-1"></i>Salvar Tudo
                        </button>
                    </div>
                </div>
            </div>

            {{-- COLUNA DIREITA --}}
            <div class="col-lg-5">

                {{-- Preview --}}
                <div class="card">
                    <div class="card-header">
                        <span><i class="bi bi-eye me-1"></i> Preview ao Vivo</span>
                    </div>
                    <div class="card-body p-0">
                        {{-- Mini browser --}}
                        <div class="border-bottom bg-body-tertiary p-2 d-flex align-items-center gap-2">
                            <div class="d-flex gap-1">
                                <span class="rounded-circle bg-danger" style="width:8px;height:8px;"></span>
                                <span class="rounded-circle bg-warning" style="width:8px;height:8px;"></span>
                                <span class="rounded-circle bg-success" style="width:8px;height:8px;"></span>
                            </div>
                            <div class="flex-grow-1 bg-body rounded px-2 py-1 text-muted text-center" style="font-size:0.6rem;">
                                {{ url('/') }}
                            </div>
                        </div>
                        {{-- Page --}}
                        <div class="position-relative" style="min-height:280px;background:var(--bs-body-bg);overflow:hidden;">
                            <div class="p-3 text-center" style="padding-top:30px!important;color:var(--bs-body-color);">
                                <i class="bi bi-layout-text-window-reverse display-6 d-block mb-2 opacity-25"></i>
                                <small class="opacity-50">Conteúdo do site</small>
                            </div>

                            {{-- Banner Footer --}}
                            <div id="preview-footer" class="position-absolute bottom-0 start-0 end-0 p-3 {{ ($lgpd['format'] ?? 'footer') !== 'footer' ? 'd-none' : '' }}"
                                 style="background:{{ $lgpd['bg_color'] ?? '#1a1a2e' }};font-size:0.7rem;">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-shield-lock" style="color:{{ $lgpd['text_color'] ?? '#fff' }};font-size:0.9rem;"></i>
                                    <span class="flex-grow-1" id="pv-footer-text" style="color:{{ $lgpd['text_color'] ?? '#fff' }};">{{ $lgpd['text'] }}</span>
                                    <button class="btn btn-sm" id="pv-footer-btn" style="background:{{ $lgpd['btn_bg_color'] ?? '#198754' }};color:{{ $lgpd['btn_text_color'] ?? '#fff' }};font-size:0.6rem;padding:0.15rem 0.4rem;">{{ $lgpd['btn_accept'] }}</button>
                                    <button class="btn btn-sm btn-outline-light" style="font-size:0.6rem;padding:0.15rem 0.4rem;color:{{ $lgpd['text_color'] ?? '#fff' }};border-color:{{ $lgpd['text_color'] ?? '#fff' }};">{{ $lgpd['btn_decline'] }}</button>
                                </div>
                            </div>

                            {{-- Banner Top --}}
                            <div id="preview-top" class="position-absolute top-0 start-0 end-0 p-3 {{ ($lgpd['format'] ?? 'footer') !== 'top' ? 'd-none' : '' }}"
                                 style="background:{{ $lgpd['bg_color'] ?? '#1a1a2e' }};font-size:0.7rem;">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-shield-lock" style="color:{{ $lgpd['text_color'] ?? '#fff' }};font-size:0.9rem;"></i>
                                    <span class="flex-grow-1" id="pv-top-text" style="color:{{ $lgpd['text_color'] ?? '#fff' }};">{{ $lgpd['text'] }}</span>
                                    <button class="btn btn-sm" id="pv-top-btn" style="background:{{ $lgpd['btn_bg_color'] ?? '#198754' }};color:{{ $lgpd['btn_text_color'] ?? '#fff' }};font-size:0.6rem;padding:0.15rem 0.4rem;">{{ $lgpd['btn_accept'] }}</button>
                                </div>
                            </div>

                            {{-- Popup --}}
                            <div id="preview-popup" class="position-absolute inset-0 d-flex align-items-center justify-content-center {{ ($lgpd['format'] ?? 'footer') !== 'popup' ? 'd-none' : '' }}"
                                 style="background:rgba(0,0,0,0.5);top:0;left:0;right:0;bottom:0;">
                                <div class="rounded p-3 text-center" style="background:{{ $lgpd['bg_color'] ?? '#1a1a2e' }};max-width:85%;font-size:0.7rem;">
                                    <i class="bi bi-shield-lock d-block mb-1" style="color:{{ $lgpd['text_color'] ?? '#fff' }};font-size:1.2rem;"></i>
                                    <p class="mb-2" id="pv-popup-text" style="color:{{ $lgpd['text_color'] ?? '#fff' }};">{{ $lgpd['text'] }}</p>
                                    <button class="btn btn-sm" id="pv-popup-btn" style="background:{{ $lgpd['btn_bg_color'] ?? '#198754' }};color:{{ $lgpd['btn_text_color'] ?? '#fff' }};font-size:0.6rem;">{{ $lgpd['btn_accept'] }}</button>
                                </div>
                            </div>

                            {{-- Card Flutuante --}}
                            <div id="preview-central" class="position-absolute {{ ($lgpd['format'] ?? 'footer') !== 'central' ? 'd-none' : '' }}"
                                 style="bottom:10px;right:10px;width:60%;font-size:0.65rem;">
                                <div class="rounded shadow p-2" style="background:{{ $lgpd['bg_color'] ?? '#1a1a2e' }};">
                                    <p class="mb-1" id="pv-central-text" style="color:{{ $lgpd['text_color'] ?? '#fff' }};">{{ Str::limit($lgpd['text'], 80) }}</p>
                                    <div class="d-flex gap-1">
                                        <button class="btn btn-sm" id="pv-central-btn" style="background:{{ $lgpd['btn_bg_color'] ?? '#198754' }};color:{{ $lgpd['btn_text_color'] ?? '#fff' }};font-size:0.55rem;padding:0.1rem 0.3rem;">{{ $lgpd['btn_accept'] }}</button>
                                        <button class="btn btn-sm btn-outline-light" style="font-size:0.55rem;padding:0.1rem 0.3rem;color:{{ $lgpd['text_color'] ?? '#fff' }};">{{ $lgpd['btn_decline'] }}</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Checklist --}}
                <div class="card mt-3">
                    <div class="card-header">
                        <span><i class="bi bi-clipboard-check me-1"></i> Checklist</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <div class="list-group-item d-flex align-items-center gap-2 px-3 py-2">
                                <i class="bi bi-{{ $lgpd['enabled'] ? 'check-circle-fill text-success' : 'x-circle text-danger' }}"></i>
                                <span class="small">Banner ativo</span>
                            </div>
                            <div class="list-group-item d-flex align-items-center gap-2 px-3 py-2">
                                <i class="bi bi-{{ $lgpd['text'] ? 'check-circle-fill text-success' : 'x-circle text-danger' }}"></i>
                                <span class="small">Texto configurado</span>
                            </div>
                            <div class="list-group-item d-flex align-items-center gap-2 px-3 py-2">
                                <i class="bi bi-{{ $lgpd['policy_url'] ? 'check-circle-fill text-success' : 'x-circle text-danger' }}"></i>
                                <span class="small">Política de privacidade</span>
                            </div>
                            <div class="list-group-item d-flex align-items-center gap-2 px-3 py-2">
                                <i class="bi bi-check-circle-fill text-success"></i>
                                <span class="small">Aceitar/Recusar disponíveis</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Info --}}
                <div class="card mt-3">
                    <div class="card-header">
                        <span><i class="bi bi-info-circle me-1"></i> LGPD</span>
                    </div>
                    <div class="card-body small text-muted">
                        <p class="mb-2"><strong>Lei 13.709/2018</strong> — Multas de até 2% do faturamento (máx R$ 50 milhões).</p>
                        <a href="https://www.gov.br/cidadania/pt-br/acesso-a-informacao/lgpd" target="_blank" class="btn btn-outline-primary btn-sm w-100">
                            <i class="bi bi-book me-1"></i> Documentação Oficial
                        </a>
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
    const form = document.getElementById('frm-lgpd');
    const enabledToggle = document.getElementById('lgpd-enabled');
    const toggleWrapper = document.getElementById('toggle-wrapper');
    const statusBadge = document.getElementById('status-main-badge');

    // Toggle visual
    enabledToggle.addEventListener('change', function() {
        if (this.checked) {
            toggleWrapper.classList.add('border-success', 'bg-success', 'bg-opacity-10');
            toggleWrapper.classList.remove('bg-body-tertiary');
            statusBadge.className = 'badge bg-success fs-6';
            statusBadge.innerHTML = '<i class="bi bi-check-circle me-1"></i>BANNER ATIVO';
        } else {
            toggleWrapper.classList.remove('border-success', 'bg-success', 'bg-opacity-10');
            toggleWrapper.classList.add('bg-body-tertiary');
            statusBadge.className = 'badge bg-secondary fs-6';
            statusBadge.innerHTML = '<i class="bi bi-x-circle me-1"></i>BANNER INATIVO';
        }
    });

    // Char counter
    document.getElementById('lgpd-text').addEventListener('input', function() {
        document.getElementById('char-count').textContent = this.value.length;
        updatePreviewText(this.value);
    });

    // Format selection
    document.querySelectorAll('.format-radio').forEach(function(radio) {
        radio.addEventListener('change', function() {
            document.querySelectorAll('.format-option').forEach(o => o.classList.remove('border-primary', 'bg-primary', 'bg-opacity-10'));
            this.nextElementSibling.classList.add('border-primary', 'bg-primary', 'bg-opacity-10');
            showPreviewFormat(this.value);
        });
    });

    function showPreviewFormat(fmt) {
        ['footer', 'top', 'popup', 'central'].forEach(f => {
            document.getElementById('preview-' + f).classList.toggle('d-none', f !== fmt);
        });
    }

    // Color sync helpers
    function syncColors(pickerId, hexId, callback) {
        const picker = document.getElementById(pickerId);
        const hex = document.getElementById(hexId);
        picker.addEventListener('input', () => { hex.value = picker.value; callback(); });
        hex.addEventListener('input', () => { if (/^#[0-9a-fA-F]{6}$/.test(hex.value)) { picker.value = hex.value; callback(); } });
    }

    function updatePreviewColors() {
        const bg = document.getElementById('lgpd-bg-color').value;
        const txt = document.getElementById('lgpd-text-color').value;
        const btnBg = document.getElementById('lgpd-btn-bg').value;
        const btnTxt = document.getElementById('lgpd-btn-text').value;

        ['footer', 'top', 'popup', 'central'].forEach(f => {
            const el = document.getElementById('preview-' + f);
            const inner = el.querySelector('[style*="background"]') || el;
            if (f === 'popup') {
                el.querySelector('.rounded').style.background = bg;
            } else if (f === 'central') {
                el.querySelector('.rounded').style.background = bg;
            } else {
                el.style.background = bg;
            }
        });
        document.querySelectorAll('[id^="pv-"][id$="-text"]').forEach(el => el.style.color = txt);
        document.querySelectorAll('[id^="pv-"][id$="-btn"]').forEach(el => { el.style.background = btnBg; el.style.color = btnTxt; });
    }

    function updatePreviewText(text) {
        document.querySelectorAll('[id^="pv-"][id$="-text"]').forEach(el => el.textContent = text || 'Texto...');
    }

    syncColors('lgpd-bg-color', 'bg-color-hex', updatePreviewColors);
    syncColors('lgpd-text-color', 'text-color-hex', updatePreviewColors);
    syncColors('lgpd-btn-bg', 'btn-bg-hex', updatePreviewColors);
    syncColors('lgpd-btn-text', 'btn-text-hex', updatePreviewColors);

    // Button text preview
    document.getElementById('lgpd-btn-accept').addEventListener('input', function() {
        document.querySelectorAll('[id^="pv-"][id$="-btn"]').forEach(el => el.textContent = this.value || 'Aceitar');
    });

    // Form submit
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('btn-save-lgpd');
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Salvando...';

        try {
            const fd = new FormData(form);
            fd.set('enabled', enabledToggle.checked ? '1' : '0');

            const r = await fetch('{{ route("admin.settings.lgpd.update") }}', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                body: fd,
            });
            const data = await r.json();
            if (r.ok && data.ok) window.toast.success('Configurações LGPD salvas!');
            else window.toast.error(data.errors ? Object.values(data.errors).flat().join(', ') : 'Erro ao salvar.');
        } catch (err) { window.toast.error('Erro de conexão.'); }
        finally { btn.disabled = false; btn.innerHTML = orig; }
    });
})();
</script>
@endpush
