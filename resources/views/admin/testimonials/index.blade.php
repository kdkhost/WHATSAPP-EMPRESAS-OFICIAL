@extends('admin.layouts.app')
@section('title', 'Depoimentos')
@section('content')
<div class="container-fluid">

    {{-- ========== HEADER ========== --}}
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h3 class="mb-1"><i class="bi bi-chat-quote me-2 text-primary"></i>Depoimentos</h3>
            <p class="text-muted mb-0 small">Gerencie depoimentos de clientes para exibir no site. Arraste para reordenar, ative/desative com 1 clique e importe direto do Google Business.</p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <button type="button" class="btn btn-primary btn-sm" id="btn-new-testimonial">
                <i class="bi bi-plus-lg me-1"></i> Novo depoimento
            </button>
        </div>
    </div>

    {{-- ========== KPIs ========== --}}
    <div class="row g-3 mb-4">
        <div class="col-md-6 col-lg-3">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-chat-quote"></i></div>
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
                    <div class="stat-icon bg-warning-subtle text-warning"><i class="bi bi-star-fill"></i></div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="text-uppercase small text-muted fw-semibold">Nota Media</div>
                        <div class="fs-4 fw-bold" id="kpi-avg">{{ number_format($stats['avg'], 1, ',', '.') }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-info-subtle text-info"><i class="bi bi-google"></i></div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="text-uppercase small text-muted fw-semibold">Do Google</div>
                        <div class="fs-4 fw-bold" id="kpi-google">{{ $stats['google'] }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ========== GRID ========== --}}
    <div class="card shadow-sm">
        <div class="card-header">
            <strong><i class="bi bi-grid me-1 text-primary"></i> Lista de depoimentos</strong>
            <div class="d-flex align-items-center gap-2">
                <div class="btn-group btn-group-sm" role="group" id="filter-buttons">
                    <button type="button" class="btn btn-outline-secondary active" data-filter="all">Todos</button>
                    <button type="button" class="btn btn-outline-secondary" data-filter="active">Ativos</button>
                    <button type="button" class="btn btn-outline-secondary" data-filter="inactive">Inativos</button>
                </div>
                <div class="position-relative" style="max-width: 220px;">
                    <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-2 text-muted small"></i>
                    <input type="search" class="form-control form-control-sm ps-4" id="testimonial-search" placeholder="Buscar...">
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3" id="testimonials-grid">
                @forelse($testimonials as $t)
                    @include('admin.testimonials.partials.card', ['t' => $t])
                @empty
                    <div class="col-12">
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-chat-quote fs-1"></i>
                            <p class="mt-2 mb-3">Nenhum depoimento cadastrado.</p>
                            <button type="button" class="btn btn-primary" onclick="document.getElementById('btn-new-testimonial').click()">
                                <i class="bi bi-plus-lg me-1"></i> Adicionar primeiro depoimento
                            </button>
                        </div>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- ========== MODAL: Depoimento ========== --}}
<div class="modal fade" id="testimonial-modal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="frm-testimonial">
                @csrf
                <input type="hidden" name="id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-chat-quote me-2"></i><span id="testimonial-modal-title">Novo depoimento</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-12 d-flex align-items-center gap-3">
                            <div id="avatar-preview" class="testimonial-avatar-preview">
                                <i class="bi bi-person"></i>
                            </div>
                            <div class="flex-grow-1">
                                <label class="form-label">URL do avatar</label>
                                <div class="input-group">
                                    <input type="text" name="avatar" class="form-control" placeholder="https://...">
                                    <button type="button" class="btn btn-outline-secondary" id="btn-upload-avatar">
                                        <i class="bi bi-cloud-arrow-up"></i>
                                    </button>
                                </div>
                                <small class="text-muted">Quadrado, recomendado 200x200px.</small>
                            </div>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label">Nome <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" maxlength="120" required placeholder="Joao da Silva">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Origem</label>
                            <select name="source" class="form-select">
                                <option value="manual">Manual</option>
                                <option value="google">Google Business</option>
                            </select>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Cargo / Empresa</label>
                            <input type="text" name="role" class="form-control" maxlength="80" placeholder="CEO da Empresa XYZ">
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Avaliacao</label>
                            <div class="rating-input" id="rating-input">
                                @for($i = 1; $i <= 5; $i++)
                                    <i class="bi bi-star-fill rating-star" data-value="{{ $i }}"></i>
                                @endfor
                                <input type="hidden" name="rating" value="5">
                                <span class="ms-2 small text-muted" id="rating-label">5 estrelas</span>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Depoimento <span class="text-danger">*</span></label>
                            <textarea name="content" class="form-control" rows="4" maxlength="500" required placeholder="Otimo atendimento, produto de qualidade..."></textarea>
                            <div class="d-flex justify-content-between mt-1">
                                <small class="text-muted">Minimo 10, maximo 500 caracteres.</small>
                                <small class="text-muted"><span id="content-counter">0</span>/500</small>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="active" value="1" id="testimonial-active" checked>
                                <label class="form-check-label" for="testimonial-active">Depoimento ativo (visivel no site)</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-danger me-auto d-none" id="btn-delete-testimonial">
                        <i class="bi bi-trash me-1"></i> Excluir
                    </button>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i> Salvar depoimento</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('head')
<style>
.testimonial-card {
    border-radius: .65rem;
    transition: transform .15s, box-shadow .15s;
    cursor: pointer;
    overflow: hidden;
    height: 100%;
}
.testimonial-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(0,0,0,.10);
}
.testimonial-card.dragging { opacity: .4; }
.testimonial-card .card-body { display: flex; flex-direction: column; }
.testimonial-avatar {
    width: 48px; height: 48px;
    border-radius: 50%;
    object-fit: cover;
    background: var(--bs-tertiary-bg);
    display: flex; align-items: center; justify-content: center;
    font-weight: 600;
    color: var(--bs-secondary-color);
    flex-shrink: 0;
}
.testimonial-rating .bi { color: #ffc107; font-size: .9rem; }
.testimonial-rating .bi-star { color: var(--bs-secondary-color); opacity: .35; }
.testimonial-content {
    font-size: .85rem;
    line-height: 1.5;
    color: var(--bs-body-color);
    quotes: "\201C" "\201D" "\2018" "\2019";
    position: relative;
    padding-left: 1rem;
}
.testimonial-content::before {
    content: open-quote;
    position: absolute;
    left: -.1rem; top: -.5rem;
    font-size: 2rem;
    color: var(--bs-primary);
    opacity: .35;
    font-family: Georgia, serif;
    line-height: 1;
}
.testimonial-drag-handle {
    cursor: grab;
    color: var(--bs-secondary-color);
    opacity: .5;
}
.testimonial-drag-handle:hover { opacity: 1; }
.testimonial-drag-handle:active { cursor: grabbing; }

/* Rating input no modal */
.rating-input { display: flex; align-items: center; gap: .25rem; }
.rating-input .rating-star {
    cursor: pointer;
    font-size: 1.5rem;
    color: var(--bs-secondary-color);
    opacity: .35;
    transition: all .12s;
}
.rating-input .rating-star.active { color: #ffc107; opacity: 1; }
.rating-input .rating-star:hover { transform: scale(1.15); }

/* Avatar preview no modal */
.testimonial-avatar-preview {
    width: 64px; height: 64px;
    border-radius: 50%;
    background: var(--bs-tertiary-bg);
    border: 2px solid var(--bs-border-color);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem;
    color: var(--bs-secondary-color);
    background-size: cover;
    background-position: center;
    flex-shrink: 0;
    overflow: hidden;
}
.testimonial-avatar-preview.has-image { color: transparent; }
</style>
@endpush

@push('scripts')
<script>
const csrf = document.querySelector('meta[name=csrf-token]').content;
const testimonialModal = new bootstrap.Modal(document.getElementById('testimonial-modal'));

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
    const cards = document.querySelectorAll('#testimonials-grid .testimonial-card[data-id]');
    let active = 0, sumRating = 0, google = 0;
    cards.forEach(c => {
        if (c.dataset.active === '1') active++;
        sumRating += parseInt(c.dataset.rating || 0);
        if (c.dataset.source === 'google') google++;
    });
    const avg = cards.length ? (sumRating / cards.length) : 0;
    document.getElementById('kpi-total').textContent = cards.length;
    document.getElementById('kpi-active').textContent = active;
    document.getElementById('kpi-avg').textContent = avg.toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
    document.getElementById('kpi-google').textContent = google;
}

/* ---------- rating input ---------- */
function setRating(val) {
    document.querySelector('#frm-testimonial [name=rating]').value = val;
    document.getElementById('rating-label').textContent = val + (val === 1 ? ' estrela' : ' estrelas');
    document.querySelectorAll('#rating-input .rating-star').forEach(s => {
        s.classList.toggle('active', parseInt(s.dataset.value) <= val);
    });
}
document.querySelectorAll('#rating-input .rating-star').forEach(star => {
    star.addEventListener('click', () => setRating(parseInt(star.dataset.value)));
    star.addEventListener('mouseenter', () => {
        const v = parseInt(star.dataset.value);
        document.querySelectorAll('#rating-input .rating-star').forEach(s => {
            s.classList.toggle('active', parseInt(s.dataset.value) <= v);
        });
    });
});
document.getElementById('rating-input').addEventListener('mouseleave', () => {
    setRating(parseInt(document.querySelector('#frm-testimonial [name=rating]').value));
});

/* ---------- contador de caracteres ---------- */
const contentTextarea = document.querySelector('#frm-testimonial [name=content]');
contentTextarea.addEventListener('input', () => {
    document.getElementById('content-counter').textContent = contentTextarea.value.length;
});

/* ---------- avatar preview ---------- */
const avatarInput = document.querySelector('#frm-testimonial [name=avatar]');
const avatarPreview = document.getElementById('avatar-preview');
avatarInput.addEventListener('input', () => {
    if (avatarInput.value) {
        avatarPreview.style.backgroundImage = `url('${avatarInput.value}')`;
        avatarPreview.classList.add('has-image');
    } else {
        avatarPreview.style.backgroundImage = '';
        avatarPreview.classList.remove('has-image');
    }
});

/* ---------- modal: novo / editar ---------- */
function openCreator() {
    const f = document.getElementById('frm-testimonial');
    f.reset();
    f.id.value = '';
    f.active.checked = true;
    f.source.value = 'manual';
    setRating(5);
    contentTextarea.dispatchEvent(new Event('input'));
    avatarInput.dispatchEvent(new Event('input'));
    document.getElementById('testimonial-modal-title').textContent = 'Novo depoimento';
    document.getElementById('btn-delete-testimonial').classList.add('d-none');
    testimonialModal.show();
}

function openEditor(card) {
    const f = document.getElementById('frm-testimonial');
    f.reset();
    f.id.value = card.dataset.id;
    f.name.value = card.dataset.name || '';
    f.role.value = card.dataset.role || '';
    f.avatar.value = card.dataset.avatar || '';
    f.content.value = card.dataset.content || '';
    f.source.value = card.dataset.source || 'manual';
    f.active.checked = card.dataset.active === '1';
    setRating(parseInt(card.dataset.rating || 5));
    contentTextarea.dispatchEvent(new Event('input'));
    avatarInput.dispatchEvent(new Event('input'));
    document.getElementById('testimonial-modal-title').textContent = 'Editar depoimento';
    document.getElementById('btn-delete-testimonial').classList.remove('d-none');
    testimonialModal.show();
}

document.getElementById('btn-new-testimonial').addEventListener('click', openCreator);

document.getElementById('testimonials-grid').addEventListener('click', e => {
    if (e.target.closest('[data-action], .testimonial-drag-handle')) return;
    const card = e.target.closest('.testimonial-card[data-id]');
    if (card) openEditor(card);
});

/* ---------- salvar / excluir ---------- */
document.getElementById('frm-testimonial').addEventListener('submit', async e => {
    e.preventDefault();
    const f = e.target;
    const id = f.id.value;
    const payload = {
        name: f.name.value,
        role: f.role.value,
        avatar: f.avatar.value,
        rating: parseInt(f.rating.value),
        content: f.content.value,
        source: f.source.value,
        active: f.active.checked ? 1 : 0,
    };
    try {
        if (id) {
            await api(`{{ url('/admin/settings/testimonials') }}/${id}`, { method: 'PUT', body: payload });
            window.toast.success('Depoimento atualizado!');
        } else {
            await api('{{ route("admin.settings.testimonials.store") }}', { method: 'POST', body: payload });
            window.toast.success('Depoimento criado!');
        }
        testimonialModal.hide();
        setTimeout(() => location.reload(), 600);
    } catch (err) {
        window.toast.error('Erro: ' + err.message);
    }
});

document.getElementById('btn-delete-testimonial').addEventListener('click', async () => {
    const id = document.getElementById('frm-testimonial').id.value;
    if (!id) return;
    const ok = await Swal.fire({ icon:'warning', title:'Excluir depoimento?', text:'Esta acao nao pode ser desfeita.', showCancelButton:true, confirmButtonText:'Excluir', cancelButtonText:'Cancelar', confirmButtonColor:'#dc3545' });
    if (!ok.isConfirmed) return;
    try {
        await api(`{{ url('/admin/settings/testimonials') }}/${id}`, { method: 'DELETE' });
        document.querySelector(`.testimonial-card[data-id="${id}"]`)?.closest('.col-md-6, .col-lg-4')?.remove();
        testimonialModal.hide();
        recountKpis();
        window.toast.success('Depoimento excluido!');
    } catch (err) {
        window.toast.error('Erro: ' + err.message);
    }
});

/* ---------- toggle / delete inline ---------- */
document.getElementById('testimonials-grid').addEventListener('click', async e => {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;
    e.stopPropagation();
    const card = btn.closest('.testimonial-card[data-id]');
    const id = card.dataset.id;
    const action = btn.dataset.action;

    if (action === 'toggle') {
        try {
            const data = await api(`{{ url('/admin/settings/testimonials') }}/${id}/toggle`, { method: 'POST' });
            card.dataset.active = data.active ? '1' : '0';
            const badge = card.querySelector('.testimonial-status-badge');
            if (data.active) {
                badge.className = 'testimonial-status-badge badge bg-success';
                badge.innerHTML = '<i class="bi bi-eye me-1"></i> Ativo';
                btn.innerHTML = '<i class="bi bi-pause-fill"></i>';
                btn.title = 'Desativar';
            } else {
                badge.className = 'testimonial-status-badge badge bg-secondary';
                badge.innerHTML = '<i class="bi bi-eye-slash me-1"></i> Inativo';
                btn.innerHTML = '<i class="bi bi-play-fill"></i>';
                btn.title = 'Ativar';
            }
            applyFilters();
            recountKpis();
            window.toast.success(data.active ? 'Depoimento ativado!' : 'Desativado!');
        } catch (err) {
            window.toast.error('Erro: ' + err.message);
        }
    } else if (action === 'delete') {
        const ok = await Swal.fire({ icon:'warning', title:'Excluir depoimento?', text:'Esta acao nao pode ser desfeita.', showCancelButton:true, confirmButtonText:'Excluir', cancelButtonText:'Cancelar', confirmButtonColor:'#dc3545' });
        if (!ok.isConfirmed) return;
        try {
            await api(`{{ url('/admin/settings/testimonials') }}/${id}`, { method: 'DELETE' });
            card.closest('.col-md-6, .col-lg-4')?.remove();
            recountKpis();
            window.toast.success('Depoimento excluido!');
        } catch (err) {
            window.toast.error('Erro: ' + err.message);
        }
    }
});

/* ---------- drag & drop reorder ---------- */
const grid = document.getElementById('testimonials-grid');
let dragging = null;

function bindDrag(card) {
    const handle = card.querySelector('.testimonial-drag-handle');
    if (!handle) return;
    handle.addEventListener('mousedown', () => card.draggable = true);
    handle.addEventListener('mouseup', () => card.draggable = false);
    card.addEventListener('dragstart', e => {
        dragging = card.closest('.col-md-6, .col-lg-4');
        card.classList.add('dragging');
    });
    card.addEventListener('dragend', async () => {
        card.classList.remove('dragging');
        card.draggable = false;
        const order = Array.from(grid.querySelectorAll('.testimonial-card[data-id]')).map(c => c.dataset.id);
        try {
            await api('{{ route("admin.settings.testimonials.reorder") }}', { method: 'POST', body: { order } });
            window.toast.success('Ordem salva!');
        } catch (err) {
            window.toast.error('Erro ao salvar ordem.');
        }
        dragging = null;
    });
}

grid.addEventListener('dragover', e => {
    e.preventDefault();
    if (!dragging) return;
    const targetCol = e.target.closest('.col-md-6, .col-lg-4');
    if (!targetCol || targetCol === dragging) return;
    const rect = targetCol.getBoundingClientRect();
    const before = (e.clientY - rect.top) / rect.height < 0.5;
    targetCol.parentNode.insertBefore(dragging, before ? targetCol : targetCol.nextSibling);
});

document.querySelectorAll('#testimonials-grid .testimonial-card').forEach(bindDrag);

/* ---------- filtros / busca ---------- */
let currentFilter = 'all';
let currentQuery = '';

function applyFilters() {
    document.querySelectorAll('#testimonials-grid .testimonial-card[data-id]').forEach(c => {
        const col = c.closest('.col-md-6, .col-lg-4');
        const matchFilter = currentFilter === 'all'
            || (currentFilter === 'active' && c.dataset.active === '1')
            || (currentFilter === 'inactive' && c.dataset.active === '0');
        const text = c.textContent.toLowerCase();
        const matchSearch = !currentQuery || text.includes(currentQuery);
        col.style.display = matchFilter && matchSearch ? '' : 'none';
    });
}

document.querySelectorAll('#filter-buttons [data-filter]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('#filter-buttons [data-filter]').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        currentFilter = btn.dataset.filter;
        applyFilters();
    });
});

document.getElementById('testimonial-search').addEventListener('input', e => {
    currentQuery = e.target.value.toLowerCase().trim();
    applyFilters();
});

/* ---------- upload avatar ---------- */
document.getElementById('btn-upload-avatar').addEventListener('click', () => {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.onchange = async () => {
        if (!input.files[0]) return;
        const fd = new FormData();
        fd.append('file', input.files[0]);
        try {
            const r = await fetch('{{ url("/admin/uploads/avatars") }}', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: fd,
            });
            const data = await r.json();
            if (r.ok && data.url) {
                avatarInput.value = data.url;
                avatarInput.dispatchEvent(new Event('input'));
                window.toast.success('Avatar enviado!');
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
