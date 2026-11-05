@extends('admin.layouts.app')
@section('title', 'Popups')
@section('content')
<div class="container-fluid">

    {{-- ========== HEADER ========== --}}
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h3 class="mb-1"><i class="bi bi-window-stack me-2 text-primary"></i>Popups</h3>
            <p class="text-muted mb-0 small">Crie popups com gatilhos por tempo, scroll, exit-intent ou clique. Controle periodo de exibicao, CTA e visualize o resultado antes de publicar.</p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <button type="button" class="btn btn-primary btn-sm" id="btn-new-popup">
                <i class="bi bi-plus-lg me-1"></i> Novo popup
            </button>
        </div>
    </div>

    {{-- ========== KPIs ========== --}}
    <div class="row g-3 mb-4">
        <div class="col-md-6 col-lg-3">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-window-stack"></i></div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="text-uppercase small text-muted fw-semibold">Total</div>
                        <div class="fs-4 fw-bold" id="kpi-total">{{ $stats['total'] }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-success-subtle text-success"><i class="bi bi-eye"></i></div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="text-uppercase small text-muted fw-semibold">Ativos</div>
                        <div class="fs-4 fw-bold" id="kpi-active">{{ $stats['active'] }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-secondary-subtle text-secondary"><i class="bi bi-eye-slash"></i></div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="text-uppercase small text-muted fw-semibold">Inativos</div>
                        <div class="fs-4 fw-bold" id="kpi-inactive">{{ $stats['inactive'] }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-warning-subtle text-warning"><i class="bi bi-calendar-week"></i></div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="text-uppercase small text-muted fw-semibold">Agendados/Vencidos</div>
                        <div class="fs-4 fw-bold" id="kpi-scheduled">{{ $stats['scheduled'] }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ========== TABELA ========== --}}
    <div class="card shadow-sm">
        <div class="card-header">
            <strong><i class="bi bi-list-ul me-1 text-primary"></i> Lista de popups</strong>
            <div class="position-relative" style="max-width: 280px;">
                <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-2 text-muted small"></i>
                <input type="search" class="form-control form-control-sm ps-4" id="popup-search" placeholder="Buscar popup...">
            </div>
        </div>
        <div class="card-body p-0">
            <div>
                <table class="table table-bordered table-striped dt-responsive nowrap w-100 app-datatable" id="popups-table" data-export-title="Popups" style="width: 100% !important;">
                    <thead class="table-light">
                        <tr>
                            <th class="all" data-priority="1" style="width: 30%;">Nome</th>
                            <th class="min-tablet">Gatilho</th>
                            <th class="desktop">Período</th>
                            <th class="desktop">CTA</th>
                            <th class="min-tablet" data-priority="2">Status</th>
                            <th class="all text-end no-export" data-priority="1" style="width: 100px; min-width: 90px; max-width: 110px; white-space: nowrap;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($popups as $p)
                            @include('admin.popups.partials.row', ['p' => $p])
                        @empty
                            <tr id="empty-row">
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="bi bi-window-stack fs-1 d-block mb-2"></i>
                                    Nenhum popup cadastrado ainda.
                                    <div class="mt-3">
                                        <button type="button" class="btn btn-primary" onclick="document.getElementById('btn-new-popup').click()">
                                            <i class="bi bi-plus-lg me-1"></i> Criar primeiro popup
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- ========== MODAL: Popup ========== --}}
<div class="modal fade" id="popup-modal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <form id="frm-popup">
                @csrf
                <input type="hidden" name="id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-window-stack me-2"></i><span id="popup-modal-title">Novo popup</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        {{-- Coluna esquerda: campos --}}
                        <div class="col-lg-7">
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label class="form-label">Nome interno <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control" maxlength="120" required placeholder="Ex.: Black Friday 2025">
                                    <small class="text-muted">Identificacao interna - nao aparece para o visitante.</small>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Gatilho de exibicao</label>
                                    <select name="trigger" class="form-select" id="popup-trigger">
                                        <option value="time">Tempo na pagina</option>
                                        <option value="scroll">Scroll (rolagem)</option>
                                        <option value="exit">Exit intent (saida)</option>
                                        <option value="click">Click em elemento</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Valor do gatilho</label>
                                    <div class="input-group">
                                        <input type="number" name="trigger_value" class="form-control" min="0" value="5">
                                        <span class="input-group-text" id="trigger-unit">segundos</span>
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <label class="form-label">Imagem (opcional)</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-image"></i></span>
                                        <input type="text" name="image" class="form-control" placeholder="https://... ou /uploads/...">
                                        <button type="button" class="btn btn-outline-secondary" id="btn-upload-popup">
                                            <i class="bi bi-cloud-arrow-up"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <label class="form-label">Conteudo HTML</label>
                                    <textarea name="html" class="form-control font-monospace" rows="5" style="font-size: .85rem;" placeholder="<h3>Promocao especial!</h3>&#10;<p>Aproveite descontos exclusivos.</p>"></textarea>
                                    <small class="text-muted">HTML completo do conteudo. Use tags como h3, p, ul, strong, em.</small>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Texto do botao (CTA)</label>
                                    <input type="text" name="cta_label" class="form-control" maxlength="60" placeholder="Quero aproveitar">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Link do botao</label>
                                    <input type="text" name="cta_url" class="form-control" maxlength="255" placeholder="https://...">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Inicio (opcional)</label>
                                    <input type="date" name="starts_at" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Termino (opcional)</label>
                                    <input type="date" name="ends_at" class="form-control">
                                </div>

                                <div class="col-md-12">
                                    <div class="form-check form-switch mt-1">
                                        <input class="form-check-input" type="checkbox" name="active" value="1" id="popup-active" checked>
                                        <label class="form-check-label" for="popup-active">Popup ativo</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Coluna direita: preview --}}
                        <div class="col-lg-5">
                            <label class="form-label small text-uppercase fw-semibold text-muted">Preview ao vivo</label>
                            <div class="popup-preview-frame">
                                <div class="popup-preview-bg"></div>
                                <div class="popup-preview-card">
                                    <button type="button" class="popup-preview-close" tabindex="-1"><i class="bi bi-x"></i></button>
                                    <div class="popup-preview-img" id="prv-popup-img"></div>
                                    <div class="popup-preview-body">
                                        <div id="prv-popup-html">
                                            <h5 class="mb-2">Titulo do popup</h5>
                                            <p class="mb-0 small text-muted">O conteudo HTML aparecera aqui.</p>
                                        </div>
                                        <a class="btn btn-primary btn-sm mt-3" id="prv-popup-cta">Saiba mais</a>
                                    </div>
                                </div>
                            </div>
                            <div class="text-muted small mt-2">
                                <i class="bi bi-info-circle me-1"></i> Renderizacao final pode variar conforme o tema do site.
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-danger me-auto d-none" id="btn-delete-popup">
                        <i class="bi bi-trash me-1"></i> Excluir
                    </button>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i> Salvar popup</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('head')
<style>
.popup-trigger-icon {
    width: 32px; height: 32px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: .9rem;
    flex-shrink: 0;
}

/* Preview ao vivo */
.popup-preview-frame {
    position: relative;
    aspect-ratio: 4/5;
    border: 1px solid var(--bs-border-color);
    border-radius: .65rem;
    overflow: hidden;
    background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
}
.popup-preview-bg { position: absolute; inset: 0; background: rgba(0,0,0,.35); }
.popup-preview-card {
    position: absolute; inset: 0;
    margin: auto;
    width: 80%;
    max-height: 85%;
    background: var(--bs-body-bg);
    border-radius: .65rem;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,.2);
    display: flex; flex-direction: column;
    top: 50%; left: 50%; transform: translate(-50%, -50%);
}
.popup-preview-close {
    position: absolute; top: 6px; right: 6px;
    width: 24px; height: 24px;
    border-radius: 50%;
    border: none;
    background: rgba(0,0,0,.5);
    color: #fff;
    z-index: 2;
    font-size: .8rem;
}
.popup-preview-img {
    aspect-ratio: 16/9;
    background-color: var(--bs-tertiary-bg);
    background-size: cover;
    background-position: center;
}
.popup-preview-img:empty { display: none; }
.popup-preview-body {
    padding: .85rem;
    overflow-y: auto;
    flex: 1;
    text-align: center;
}
.popup-preview-body h1, .popup-preview-body h2, .popup-preview-body h3,
.popup-preview-body h4, .popup-preview-body h5 { font-size: 1rem; margin: 0 0 .5rem; }
.popup-preview-body p { font-size: .8rem; margin: 0 0 .5rem; }

[data-bs-theme="dark"] .popup-preview-frame { background: linear-gradient(135deg, #2a2d31 0%, #1a1d21 100%); }
</style>
@endpush

@push('scripts')
<script>
const csrf = document.querySelector('meta[name=csrf-token]').content;
const popupModal = new bootstrap.Modal(document.getElementById('popup-modal'));

/* ---------- helpers ---------- */
async function api(url, opts = {}) {
    const headers = { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', ...(opts.headers || {}) };
    if (opts.body && typeof opts.body === 'object' && !(opts.body instanceof FormData)) {
        headers['Content-Type'] = 'application/json';
        opts.body = JSON.stringify(opts.body);
    }
    const r = await fetch(url, { credentials: 'same-origin', ...opts, headers });
    const data = await r.json().catch(() => ({}));
    if (!r.ok) throw new Error(data.message || 'Erro');
    return data;
}

function recountKpis() {
    const rows = document.querySelectorAll('#popups-table tbody tr[data-id]');
    let active = 0;
    rows.forEach(r => { if (r.dataset.active === '1') active++; });
    document.getElementById('kpi-total').textContent = rows.length;
    document.getElementById('kpi-active').textContent = active;
    document.getElementById('kpi-inactive').textContent = rows.length - active;
}

const triggerUnits = { time: 'segundos', scroll: 'pixels', exit: '— (sem valor)', click: '#id ou .classe' };
function updateTriggerUnit() {
    const t = document.getElementById('popup-trigger').value;
    document.getElementById('trigger-unit').textContent = triggerUnits[t] || '';
    const valueInput = document.querySelector('#frm-popup [name=trigger_value]');
    if (t === 'exit') { valueInput.value = 0; valueInput.disabled = true; } else { valueInput.disabled = false; }
}
document.getElementById('popup-trigger').addEventListener('change', updateTriggerUnit);

/* ---------- preview ao vivo ---------- */
function updateLivePreview() {
    const f = document.getElementById('frm-popup');
    document.getElementById('prv-popup-img').style.backgroundImage = f.image.value ? `url('${f.image.value}')` : '';
    const html = f.html.value.trim();
    document.getElementById('prv-popup-html').innerHTML = html || '<h5 class="mb-2">Titulo do popup</h5><p class="mb-0 small text-muted">O conteudo HTML aparecera aqui.</p>';
    const cta = document.getElementById('prv-popup-cta');
    if (f.cta_label.value) {
        cta.textContent = f.cta_label.value;
        cta.style.display = '';
        cta.href = f.cta_url.value || '#';
    } else {
        cta.style.display = 'none';
    }
}
['image','html','cta_label','cta_url'].forEach(name => {
    document.querySelector(`#frm-popup [name=${name}]`).addEventListener('input', updateLivePreview);
});

/* ---------- abrir modal: novo / editar ---------- */
function openCreator() {
    const f = document.getElementById('frm-popup');
    f.reset();
    f.id.value = '';
    f.active.checked = true;
    f.trigger.value = 'time';
    f.trigger_value.value = 5;
    document.getElementById('popup-modal-title').textContent = 'Novo popup';
    document.getElementById('btn-delete-popup').classList.add('d-none');
    updateTriggerUnit();
    updateLivePreview();
    popupModal.show();
}

function openEditor(row) {
    const f = document.getElementById('frm-popup');
    f.reset();
    f.id.value = row.dataset.id;
    f.name.value = row.dataset.name || '';
    f.trigger.value = row.dataset.trigger || 'time';
    f.trigger_value.value = row.dataset.triggerValue || 0;
    f.image.value = row.dataset.image || '';
    f.html.value = row.dataset.html || '';
    f.cta_label.value = row.dataset.ctaLabel || '';
    f.cta_url.value = row.dataset.ctaUrl || '';
    f.starts_at.value = row.dataset.startsAt || '';
    f.ends_at.value = row.dataset.endsAt || '';
    f.active.checked = row.dataset.active === '1';
    document.getElementById('popup-modal-title').textContent = 'Editar popup';
    document.getElementById('btn-delete-popup').classList.remove('d-none');
    updateTriggerUnit();
    updateLivePreview();
    popupModal.show();
}

document.getElementById('btn-new-popup').addEventListener('click', openCreator);

document.getElementById('popups-table').addEventListener('click', e => {
    if (e.target.closest('[data-action]')) return;
    const row = e.target.closest('tr[data-id]');
    if (!row) return;
    openEditor(row);
});

/* ---------- salvar / excluir ---------- */
document.getElementById('frm-popup').addEventListener('submit', async e => {
    e.preventDefault();
    const f = e.target;
    const id = f.id.value;
    const payload = {
        name: f.name.value,
        trigger: f.trigger.value,
        trigger_value: parseInt(f.trigger_value.value || 0),
        image: f.image.value,
        html: f.html.value,
        cta_label: f.cta_label.value,
        cta_url: f.cta_url.value,
        starts_at: f.starts_at.value || null,
        ends_at: f.ends_at.value || null,
        active: f.active.checked ? 1 : 0,
    };
    try {
        if (id) {
            await api(`{{ url('/admin/settings/popups') }}/${id}`, { method: 'PUT', body: payload });
            window.toast.success('Popup atualizado!');
        } else {
            await api('{{ route("admin.settings.popups.store") }}', { method: 'POST', body: payload });
            window.toast.success('Popup criado!');
        }
        popupModal.hide();
        setTimeout(() => location.reload(), 600);
    } catch (err) {
        window.toast.error('Erro: ' + err.message);
    }
});

document.getElementById('btn-delete-popup').addEventListener('click', async () => {
    const id = document.getElementById('frm-popup').id.value;
    if (!id) return;
    const ok = await Swal.fire({ icon:'warning', title:'Excluir popup?', text:'Esta acao nao pode ser desfeita.', showCancelButton:true, confirmButtonText:'Excluir', cancelButtonText:'Cancelar', confirmButtonColor:'#dc3545' });
    if (!ok.isConfirmed) return;
    try {
        await api(`{{ url('/admin/settings/popups') }}/${id}`, { method: 'DELETE' });
        document.querySelector(`tr[data-id="${id}"]`)?.remove();
        popupModal.hide();
        recountKpis();
        window.toast.success('Popup excluido!');
    } catch (err) {
        window.toast.error('Erro: ' + err.message);
    }
});

/* ---------- toggle ativo (action botao) ---------- */
document.getElementById('popups-table').addEventListener('click', async e => {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;
    e.stopPropagation();
    const row = btn.closest('tr[data-id]');
    const id = row.dataset.id;
    const action = btn.dataset.action;

    if (action === 'toggle') {
        try {
            const data = await api(`{{ url('/admin/settings/popups') }}/${id}/toggle`, { method: 'POST' });
            row.dataset.active = data.active ? '1' : '0';
            const badge = row.querySelector('.popup-status-badge');
            if (data.active) {
                badge.className = 'popup-status-badge badge bg-success';
                badge.innerHTML = '<i class="bi bi-eye me-1"></i> Ativo';
                btn.innerHTML = '<i class="bi bi-pause-fill"></i>';
                btn.title = 'Desativar';
            } else {
                badge.className = 'popup-status-badge badge bg-secondary';
                badge.innerHTML = '<i class="bi bi-eye-slash me-1"></i> Inativo';
                btn.innerHTML = '<i class="bi bi-play-fill"></i>';
                btn.title = 'Ativar';
            }
            recountKpis();
            window.toast.success(data.active ? 'Popup ativado!' : 'Popup desativado!');
        } catch (err) {
            window.toast.error('Erro: ' + err.message);
        }
    } else if (action === 'delete') {
        const ok = await Swal.fire({ icon:'warning', title:'Excluir popup?', text:'Esta acao nao pode ser desfeita.', showCancelButton:true, confirmButtonText:'Excluir', cancelButtonText:'Cancelar', confirmButtonColor:'#dc3545' });
        if (!ok.isConfirmed) return;
        try {
            await api(`{{ url('/admin/settings/popups') }}/${id}`, { method: 'DELETE' });
            row.remove();
            recountKpis();
            window.toast.success('Popup excluido!');
        } catch (err) {
            window.toast.error('Erro: ' + err.message);
        }
    }
});

/* ---------- busca instantanea ---------- */
document.getElementById('popup-search').addEventListener('input', e => {
    const q = e.target.value.toLowerCase().trim();
    document.querySelectorAll('#popups-table tbody tr[data-id]').forEach(r => {
        const text = r.textContent.toLowerCase();
        r.style.display = !q || text.includes(q) ? '' : 'none';
    });
});

/* ---------- upload imagem ---------- */
document.getElementById('btn-upload-popup').addEventListener('click', () => {
    const f = document.querySelector('#frm-popup [name=image]');
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.onchange = async () => {
        if (!input.files[0]) return;
        const fd = new FormData();
        fd.append('file', input.files[0]);
        try {
            const r = await fetch('{{ url("/admin/uploads/popups") }}', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: fd,
            });
            const data = await r.json();
            if (r.ok && data.url) {
                f.value = data.url;
                updateLivePreview();
                window.toast.success('Imagem enviada!');
            } else {
                window.toast.error(data.message || 'Falha no upload.');
            }
        } catch (err) {
            window.toast.error('Erro de rede.');
        }
    };
    input.click();
});
</script>
@endpush
