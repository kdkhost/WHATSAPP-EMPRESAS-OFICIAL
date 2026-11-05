@extends('admin.layouts.app')
@section('title', 'Slides / Carrossel')
@section('content')
<div class="container-fluid">

    {{-- ========== HEADER ========== --}}
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h3 class="mb-1"><i class="bi bi-images me-2 text-primary"></i>Slides / Carrossel</h3>
            <p class="text-muted mb-0 small">Configure os banners do carrossel da home. Arraste para reordenar, ative/desative com 1 clique e use o preview ao vivo para conferir antes de publicar.</p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-preview-all">
                <i class="bi bi-play-circle me-1"></i> Preview do carrossel
            </button>
            <button type="button" class="btn btn-primary btn-sm" id="btn-new-slide">
                <i class="bi bi-plus-lg me-1"></i> Novo slide
            </button>
        </div>
    </div>

    {{-- ========== KPIs ========== --}}
    <div class="row g-3 mb-4">
        <div class="col-md-6 col-lg-3">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-images"></i></div>
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
                    <div class="stat-icon bg-info-subtle text-info"><i class="bi bi-cursor"></i></div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="text-uppercase small text-muted fw-semibold">Com CTA</div>
                        <div class="fs-4 fw-bold" id="kpi-cta">{{ $stats['with_cta'] }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ========== GRID DE SLIDES ========== --}}
    <div class="card shadow-sm">
        <div class="card-header">
            <strong><i class="bi bi-collection-play me-1 text-primary"></i> Lista de slides</strong>
            <small class="text-muted d-none d-md-block">Arraste o icone <i class="bi bi-grip-vertical"></i> para reordenar</small>
        </div>
        <div class="card-body">
            <div class="row g-3" id="slides-grid">
                @forelse($slides as $s)
                    @include('admin.slides.partials.card', ['s' => $s])
                @empty
                    <div class="col-12">
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-images fs-1"></i>
                            <p class="mt-2 mb-3">Nenhum slide cadastrado ainda.</p>
                            <button type="button" class="btn btn-primary" onclick="document.getElementById('btn-new-slide').click()">
                                <i class="bi bi-plus-lg me-1"></i> Criar primeiro slide
                            </button>
                        </div>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- ========== MODAL: Slide ========== --}}
<div class="modal fade" id="slide-modal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <form id="frm-slide">
                @csrf
                <input type="hidden" name="id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-image me-2"></i><span id="slide-modal-title">Novo slide</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        {{-- Coluna esquerda: campos --}}
                        <div class="col-lg-7">
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label class="form-label">Imagem do slide <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-image"></i></span>
                                        <input type="text" name="image" class="form-control" placeholder="https://... ou /uploads/..." required>
                                        <button type="button" class="btn btn-outline-secondary" id="btn-upload-slide">
                                            <i class="bi bi-cloud-arrow-up"></i>
                                        </button>
                                    </div>
                                    <small class="text-muted">Recomendado 1920x800px (proporcao 12:5).</small>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Titulo</label>
                                    <input type="text" name="title" class="form-control" maxlength="120" placeholder="Titulo principal exibido sobre a imagem">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Subtitulo</label>
                                    <input type="text" name="subtitle" class="form-control" maxlength="255" placeholder="Texto complementar exibido abaixo do titulo">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Texto do botao (CTA)</label>
                                    <input type="text" name="cta_label" class="form-control" maxlength="60" placeholder="Saiba mais">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Link do botao</label>
                                    <input type="text" name="cta_url" class="form-control" maxlength="255" placeholder="https://...">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Animacao</label>
                                    <select name="animation" class="form-select">
                                        <option value="fade">Fade (suave)</option>
                                        <option value="slide">Slide (lateral)</option>
                                        <option value="zoom">Zoom</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Status</label>
                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" type="checkbox" name="active" value="1" id="slide-active" checked>
                                        <label class="form-check-label" for="slide-active">Slide ativo</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Coluna direita: preview ao vivo --}}
                        <div class="col-lg-5">
                            <label class="form-label small text-uppercase fw-semibold text-muted">Preview ao vivo</label>
                            <div class="slide-preview" id="slide-preview">
                                <div class="slide-preview-img" id="prv-img"></div>
                                <div class="slide-preview-overlay">
                                    <h4 class="slide-prv-title" id="prv-title">Titulo do slide</h4>
                                    <p class="slide-prv-subtitle" id="prv-subtitle">Subtitulo descritivo</p>
                                    <a class="btn btn-primary btn-sm" id="prv-cta">Saiba mais</a>
                                </div>
                            </div>
                            <div class="text-muted small mt-2">
                                <i class="bi bi-info-circle me-1"></i> O resultado pode variar conforme o tema do frontend.
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-danger me-auto d-none" id="btn-delete-slide">
                        <i class="bi bi-trash me-1"></i> Excluir
                    </button>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i> Salvar slide</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ========== MODAL: Preview do carrossel ========== --}}
<div class="modal fade" id="preview-modal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-play-circle me-2"></i> Preview do carrossel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div id="carouselPreview" class="carousel slide" data-bs-ride="carousel">
                    <div class="carousel-indicators" id="prv-indicators"></div>
                    <div class="carousel-inner" id="prv-inner"></div>
                    <button class="carousel-control-prev" type="button" data-bs-target="#carouselPreview" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon"></span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#carouselPreview" data-bs-slide="next">
                        <span class="carousel-control-next-icon"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('head')
<style>
.slide-card {
    border-radius: .65rem;
    overflow: hidden;
    transition: transform .15s ease, box-shadow .15s ease;
    cursor: pointer;
}
.slide-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(0,0,0,.12);
}
.slide-card.dragging { opacity: .4; }
.slide-card-img {
    aspect-ratio: 16/7;
    background-size: cover;
    background-position: center;
    background-color: var(--bs-tertiary-bg);
    position: relative;
}
.slide-card-img.empty::before {
    content: "\F2DA";
    font-family: "bootstrap-icons";
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--bs-secondary-color);
    font-size: 2.5rem;
}
.slide-drag-handle {
    position: absolute;
    top: .5rem; left: .5rem;
    background: rgba(0,0,0,.5);
    color: #fff;
    width: 28px; height: 28px;
    display: flex; align-items: center; justify-content: center;
    border-radius: .375rem;
    cursor: grab;
}
.slide-drag-handle:active { cursor: grabbing; }
.slide-position-badge {
    position: absolute;
    top: .5rem; right: .5rem;
    background: var(--bs-primary);
    color: #fff;
    width: 28px; height: 28px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 50%;
    font-size: .75rem;
    font-weight: 600;
}

/* Preview ao vivo (modal) */
.slide-preview {
    position: relative;
    aspect-ratio: 12/5;
    background-color: var(--bs-tertiary-bg);
    border-radius: .65rem;
    overflow: hidden;
    border: 1px solid var(--bs-border-color);
}
.slide-preview-img {
    position: absolute;
    inset: 0;
    background-size: cover;
    background-position: center;
    transition: opacity .3s ease;
}
.slide-preview-overlay {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    justify-content: center;
    padding: 1.25rem;
    background: linear-gradient(90deg, rgba(0,0,0,.55), rgba(0,0,0,.15));
    color: #fff;
}
.slide-prv-title { font-size: 1.25rem; font-weight: 700; margin: 0 0 .35rem; text-shadow: 0 2px 6px rgba(0,0,0,.5); }
.slide-prv-subtitle { font-size: .85rem; margin: 0 0 .75rem; text-shadow: 0 1px 3px rgba(0,0,0,.5); }

[data-bs-theme="dark"] .slide-card-img { background-color: rgba(255,255,255,.05); }
</style>
@endpush

@push('scripts')
<script>
const csrf = document.querySelector('meta[name=csrf-token]').content;

const slideModal   = new bootstrap.Modal(document.getElementById('slide-modal'));
const previewModal = new bootstrap.Modal(document.getElementById('preview-modal'));

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

function escapeHtml(s) {
    return (s || '').toString().replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;' }[c]));
}

function refreshPositions() {
    const cards = document.querySelectorAll('#slides-grid .slide-card');
    cards.forEach((c, i) => {
        const badge = c.querySelector('.slide-position-badge');
        if (badge) badge.textContent = i + 1;
    });
}

function recountKpis() {
    const cards = document.querySelectorAll('#slides-grid .slide-card');
    document.getElementById('kpi-total').textContent = cards.length;
    let active = 0, cta = 0;
    cards.forEach(c => {
        if (c.dataset.active === '1') active++;
        if (c.dataset.cta === '1') cta++;
    });
    document.getElementById('kpi-active').textContent = active;
    document.getElementById('kpi-inactive').textContent = cards.length - active;
    document.getElementById('kpi-cta').textContent = cta;
}

/* ---------- preview ao vivo no modal de edicao ---------- */
function updateLivePreview() {
    const f = document.getElementById('frm-slide');
    document.getElementById('prv-img').style.backgroundImage = f.image.value ? `url('${f.image.value}')` : '';
    document.getElementById('prv-title').textContent = f.title.value || 'Titulo do slide';
    document.getElementById('prv-subtitle').textContent = f.subtitle.value || 'Subtitulo descritivo';
    const cta = document.getElementById('prv-cta');
    if (f.cta_label.value) {
        cta.textContent = f.cta_label.value;
        cta.style.display = '';
        cta.href = f.cta_url.value || '#';
    } else {
        cta.style.display = 'none';
    }
}
['image','title','subtitle','cta_label','cta_url'].forEach(name => {
    document.querySelector(`#frm-slide [name=${name}]`).addEventListener('input', updateLivePreview);
});

/* ---------- abrir modal: novo / editar ---------- */
function openCreator() {
    const f = document.getElementById('frm-slide');
    f.reset();
    f.id.value = '';
    f.active.checked = true;
    document.getElementById('slide-modal-title').textContent = 'Novo slide';
    document.getElementById('btn-delete-slide').classList.add('d-none');
    updateLivePreview();
    slideModal.show();
}

function openEditor(card) {
    const f = document.getElementById('frm-slide');
    f.reset();
    f.id.value = card.dataset.id;
    f.title.value = card.dataset.title || '';
    f.subtitle.value = card.dataset.subtitle || '';
    f.image.value = card.dataset.image || '';
    f.cta_label.value = card.dataset.ctaLabel || '';
    f.cta_url.value = card.dataset.ctaUrl || '';
    f.animation.value = card.dataset.animation || 'fade';
    f.active.checked = card.dataset.active === '1';
    document.getElementById('slide-modal-title').textContent = 'Editar slide';
    document.getElementById('btn-delete-slide').classList.remove('d-none');
    updateLivePreview();
    slideModal.show();
}

document.getElementById('btn-new-slide').addEventListener('click', openCreator);
document.getElementById('slides-grid').addEventListener('click', e => {
    const card = e.target.closest('.slide-card');
    if (!card) return;
    if (e.target.closest('.slide-drag-handle, .slide-toggle-btn, .slide-card-actions a, .slide-card-actions button')) return;
    openEditor(card);
});

/* ---------- salvar / excluir ---------- */
document.getElementById('frm-slide').addEventListener('submit', async e => {
    e.preventDefault();
    const f = e.target;
    const id = f.id.value;
    const payload = {
        title: f.title.value,
        subtitle: f.subtitle.value,
        image: f.image.value,
        cta_label: f.cta_label.value,
        cta_url: f.cta_url.value,
        animation: f.animation.value,
        active: f.active.checked ? 1 : 0,
    };
    try {
        if (id) {
            await api(`{{ url('/admin/settings/slides') }}/${id}`, { method: 'PUT', body: payload });
            window.toast.success('Slide atualizado!');
        } else {
            await api('{{ route("admin.settings.slides.store") }}', { method: 'POST', body: payload });
            window.toast.success('Slide criado!');
        }
        slideModal.hide();
        setTimeout(() => location.reload(), 600);
    } catch (err) {
        window.toast.error('Erro: ' + err.message);
    }
});

document.getElementById('btn-delete-slide').addEventListener('click', async () => {
    const id = document.getElementById('frm-slide').id.value;
    if (!id) return;
    const ok = await Swal.fire({ icon:'warning', title:'Excluir slide?', text:'Esta acao nao pode ser desfeita.', showCancelButton:true, confirmButtonText:'Excluir', cancelButtonText:'Cancelar', confirmButtonColor:'#dc3545' });
    if (!ok.isConfirmed) return;
    try {
        await api(`{{ url('/admin/settings/slides') }}/${id}`, { method: 'DELETE' });
        document.getElementById(`slide-${id}`)?.remove();
        slideModal.hide();
        refreshPositions();
        recountKpis();
        window.toast.success('Slide excluido!');
    } catch (err) {
        window.toast.error('Erro: ' + err.message);
    }
});

/* ---------- toggle ativo ---------- */
document.getElementById('slides-grid').addEventListener('click', async e => {
    const btn = e.target.closest('.slide-toggle-btn');
    if (!btn) return;
    e.stopPropagation();
    const card = btn.closest('.slide-card');
    const id = card.dataset.id;
    try {
        const data = await api(`{{ url('/admin/settings/slides') }}/${id}/toggle`, { method: 'POST' });
        card.dataset.active = data.active ? '1' : '0';
        const badge = card.querySelector('.slide-status-badge');
        if (data.active) {
            badge.className = 'slide-status-badge badge bg-success';
            badge.innerHTML = '<i class="bi bi-eye me-1"></i> Ativo';
            btn.innerHTML = '<i class="bi bi-pause-fill"></i>';
            btn.title = 'Desativar';
        } else {
            badge.className = 'slide-status-badge badge bg-secondary';
            badge.innerHTML = '<i class="bi bi-eye-slash me-1"></i> Inativo';
            btn.innerHTML = '<i class="bi bi-play-fill"></i>';
            btn.title = 'Ativar';
        }
        recountKpis();
        window.toast.success(data.active ? 'Slide ativado!' : 'Slide desativado!');
    } catch (err) {
        window.toast.error('Erro: ' + err.message);
    }
});

/* ---------- drag & drop reordenar ---------- */
const grid = document.getElementById('slides-grid');
let draggingCard = null;

function bindDrag(card) {
    const handle = card.querySelector('.slide-drag-handle');
    if (!handle) return;
    handle.addEventListener('mousedown', () => card.draggable = true);
    handle.addEventListener('mouseup', () => card.draggable = false);
    card.addEventListener('dragstart', () => { draggingCard = card; card.classList.add('dragging'); });
    card.addEventListener('dragend', async () => {
        card.classList.remove('dragging');
        card.draggable = false;
        draggingCard = null;
        refreshPositions();
        const order = Array.from(grid.querySelectorAll('.slide-card')).map(c => c.dataset.id);
        try {
            await api('{{ route("admin.settings.slides.reorder") }}', { method: 'POST', body: { order } });
            window.toast.success('Ordem salva!');
        } catch (err) {
            window.toast.error('Erro ao salvar ordem.');
        }
    });
}

grid.addEventListener('dragover', e => {
    e.preventDefault();
    if (!draggingCard) return;
    const target = e.target.closest('.slide-card');
    if (!target || target === draggingCard) return;
    const rect = target.getBoundingClientRect();
    const before = (e.clientY - rect.top) / rect.height < 0.5;
    target.parentNode.insertBefore(draggingCard, before ? target : target.nextSibling);
});

document.querySelectorAll('#slides-grid .slide-card').forEach(bindDrag);

/* ---------- preview do carrossel ---------- */
document.getElementById('btn-preview-all').addEventListener('click', () => {
    const cards = Array.from(document.querySelectorAll('#slides-grid .slide-card[data-active="1"]'));
    const inner = document.getElementById('prv-inner');
    const indicators = document.getElementById('prv-indicators');
    inner.innerHTML = '';
    indicators.innerHTML = '';
    if (!cards.length) {
        window.toast.info('Nenhum slide ativo para preview.');
        return;
    }
    cards.forEach((c, i) => {
        const img = c.dataset.image;
        const title = c.dataset.title;
        const sub = c.dataset.subtitle;
        const ctaL = c.dataset.ctaLabel;
        const ctaU = c.dataset.ctaUrl;
        inner.insertAdjacentHTML('beforeend', `
            <div class="carousel-item ${i === 0 ? 'active' : ''}">
                <div style="aspect-ratio:12/5;background:url('${escapeHtml(img)}') center/cover;"></div>
                <div class="carousel-caption text-start" style="background:linear-gradient(90deg,rgba(0,0,0,.55),transparent);left:5%;right:auto;width:90%;text-align:left;">
                    ${title ? `<h2 class="fw-bold">${escapeHtml(title)}</h2>` : ''}
                    ${sub ? `<p class="lead">${escapeHtml(sub)}</p>` : ''}
                    ${ctaL ? `<a class="btn btn-primary" href="${escapeHtml(ctaU || '#')}" target="_blank">${escapeHtml(ctaL)}</a>` : ''}
                </div>
            </div>`);
        indicators.insertAdjacentHTML('beforeend', `
            <button type="button" data-bs-target="#carouselPreview" data-bs-slide-to="${i}" ${i === 0 ? 'class="active"' : ''}></button>`);
    });
    previewModal.show();
});

/* ---------- upload ---------- */
document.getElementById('btn-upload-slide').addEventListener('click', async () => {
    const f = document.querySelector('#frm-slide [name=image]');
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.onchange = async () => {
        if (!input.files[0]) return;
        const fd = new FormData();
        fd.append('file', input.files[0]);
        try {
            const r = await fetch('{{ url("/admin/uploads/slides") }}', {
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
