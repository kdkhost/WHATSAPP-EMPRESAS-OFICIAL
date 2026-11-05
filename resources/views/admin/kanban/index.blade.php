@extends('admin.layouts.app')
@section('title', 'Kanban: '.$board->name)
@section('content')
<div class="container-fluid">

    {{-- ========== HEADER ========== --}}
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h3 class="mb-1"><i class="bi {{ $meta['icon'] ?? 'bi-kanban' }} me-2 text-primary"></i>{{ $meta['name'] ?? $board->name }}</h3>
            <p class="text-muted mb-0 small">{{ $meta['desc'] ?? 'Organize cartoes em colunas. Arraste para mover, clique para editar.' }}</p>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <span class="badge bg-light text-dark border fs-6 py-2 px-3"><i class="bi bi-collection me-1"></i> {{ $board->slug }}</span>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-add-column">
                <i class="bi bi-plus-circle me-1"></i> Nova coluna
            </button>
            <button type="button" class="btn btn-primary btn-sm" id="btn-add-card-quick" {{ $columns->isEmpty() ? 'disabled' : '' }}>
                <i class="bi bi-plus-lg me-1"></i> Novo card
            </button>
        </div>
    </div>

    {{-- ========== KPIs ========== --}}
    <div class="row g-3 mb-4">
        <div class="col-md-6 col-lg-3">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-columns-gap"></i></div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="text-uppercase small text-muted fw-semibold">Colunas</div>
                        <div class="fs-4 fw-bold" id="kpi-columns">{{ $stats['columns'] }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-success-subtle text-success"><i class="bi bi-card-list"></i></div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="text-uppercase small text-muted fw-semibold">Cards</div>
                        <div class="fs-4 fw-bold" id="kpi-cards">{{ $stats['cards'] }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-info-subtle text-info"><i class="bi bi-image"></i></div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="text-uppercase small text-muted fw-semibold">Com imagem</div>
                        <div class="fs-4 fw-bold" id="kpi-images">{{ $stats['with_image'] }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-warning-subtle text-warning"><i class="bi bi-clock-history"></i></div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="text-uppercase small text-muted fw-semibold">Ultima atualizacao</div>
                        <div class="fs-6 fw-semibold text-truncate">
                            {{ $stats['last_update'] ? \Illuminate\Support\Carbon::parse($stats['last_update'])->diffForHumans() : '—' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ========== BOARD ========== --}}
    <div class="card shadow-sm">
        <div class="card-header">
            <strong><i class="bi bi-kanban me-1 text-primary"></i> Quadro</strong>
            <div class="text-muted small d-none d-md-block">Arraste cards entre colunas para reordenar</div>
        </div>
        <div class="card-body p-3" style="overflow-x: auto;">
            <div class="kanban-premium" id="board" data-slug="{{ $board->slug }}">
                @forelse($columns as $col)
                <div class="kanban-col" data-column-id="{{ $col->id }}" data-column-name="{{ $col->name }}" data-column-color="{{ $col->color }}">
                    <div class="kanban-col-header">
                        <div class="d-flex align-items-center gap-2 flex-grow-1 min-w-0">
                            <span class="kanban-col-dot bg-{{ $col->color }}"></span>
                            <span class="kanban-col-title text-truncate">{{ $col->name }}</span>
                            <span class="badge bg-light text-dark border ms-1" data-col-count>{{ $cards->where('column_id', $col->id)->count() }}</span>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-link text-muted p-1" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="#" data-add-card="{{ $col->id }}"><i class="bi bi-plus-lg me-2"></i> Novo card</a></li>
                                <li><a class="dropdown-item" href="#" data-edit-column="{{ $col->id }}"><i class="bi bi-pencil me-2"></i> Editar coluna</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="#" data-delete-column="{{ $col->id }}"><i class="bi bi-trash me-2"></i> Excluir coluna</a></li>
                            </ul>
                        </div>
                    </div>

                    <div class="kanban-col-body" data-cards-container>
                        @foreach($cards->where('column_id', $col->id)->sortBy('sort') as $card)
                            @include('admin.kanban.partials.card', ['card' => $card])
                        @endforeach
                    </div>

                    <button type="button" class="kanban-col-add" data-add-card="{{ $col->id }}">
                        <i class="bi bi-plus-lg me-1"></i> Adicionar card
                    </button>
                </div>
                @empty
                <div class="text-center w-100 py-5">
                    <i class="bi bi-columns-gap fs-1 text-muted"></i>
                    <p class="text-muted mt-2 mb-3">Nenhuma coluna criada ainda neste quadro.</p>
                    <button type="button" class="btn btn-primary" id="btn-create-first-col">
                        <i class="bi bi-plus-circle me-1"></i> Criar primeira coluna
                    </button>
                </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- ========== MODAL: Card ========== --}}
<div class="modal fade" id="card-modal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="frm-card">
                @csrf
                <input type="hidden" name="id">
                <input type="hidden" name="column_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-card-text me-2"></i><span id="card-modal-title">Novo card</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Titulo <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" maxlength="120" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Imagem (URL ou caminho)</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-image"></i></span>
                                <input type="text" name="image" class="form-control" placeholder="https://... ou /uploads/...">
                                <button type="button" class="btn btn-outline-secondary" id="btn-upload-card-image">
                                    <i class="bi bi-cloud-arrow-up"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Conteudo</label>
                            <textarea name="content" class="form-control" rows="5" placeholder="Descricao, observacoes, links..."></textarea>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Coluna</label>
                            <select name="column_id_select" class="form-select" id="card-modal-column">
                                @foreach($columns as $col)
                                    <option value="{{ $col->id }}">{{ $col->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-danger me-auto d-none" id="btn-delete-card">
                        <i class="bi bi-trash me-1"></i> Excluir
                    </button>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i> Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ========== MODAL: Coluna ========== --}}
<div class="modal fade" id="col-modal" tabindex="-1">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <form id="frm-col">
                @csrf
                <input type="hidden" name="id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-columns-gap me-2"></i><span id="col-modal-title">Nova coluna</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nome <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" maxlength="80" required>
                    </div>
                    <div class="mb-1">
                        <label class="form-label d-block">Cor</label>
                        <div class="d-flex gap-2 flex-wrap" id="col-color-picker">
                            @foreach(['primary' => 'Azul', 'secondary' => 'Cinza', 'success' => 'Verde', 'danger' => 'Vermelho', 'warning' => 'Amarelo', 'info' => 'Ciano', 'dark' => 'Escuro'] as $c => $lbl)
                                <label class="kanban-color-opt" title="{{ $lbl }}">
                                    <input type="radio" name="color" value="{{ $c }}" {{ $c === 'primary' ? 'checked' : '' }}>
                                    <span class="kanban-color-swatch bg-{{ $c }}"></span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i> Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('head')
<style>
.kanban-premium {
    display: flex;
    gap: 1rem;
    align-items: flex-start;
    min-height: 320px;
}
.kanban-col {
    min-width: 300px;
    flex: 0 0 300px;
    background: var(--bs-tertiary-bg);
    border: 1px solid var(--bs-border-color);
    border-radius: .65rem;
    display: flex;
    flex-direction: column;
    max-height: 75vh;
}
.kanban-col-header {
    display: flex;
    align-items: center;
    gap: .25rem;
    padding: .65rem .75rem;
    border-bottom: 1px solid var(--bs-border-color);
}
.kanban-col-dot {
    width: 10px; height: 10px; border-radius: 50%;
    flex-shrink: 0;
}
.kanban-col-title {
    font-size: .82rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .03em;
}
.kanban-col-body {
    padding: .65rem;
    overflow-y: auto;
    min-height: 60px;
    flex: 1;
}
.kanban-col-body.drag-over {
    background: color-mix(in srgb, var(--bs-primary) 8%, transparent);
}
.kanban-card-premium {
    background: var(--bs-body-bg);
    border: 1px solid var(--bs-border-color);
    border-left: 3px solid var(--bs-primary);
    border-radius: .5rem;
    padding: .65rem .75rem;
    margin-bottom: .5rem;
    cursor: grab;
    transition: box-shadow .15s ease, transform .15s ease;
}
.kanban-card-premium:hover {
    box-shadow: 0 4px 10px rgba(0,0,0,.08);
    transform: translateY(-1px);
}
.kanban-card-premium.dragging {
    opacity: .4;
    cursor: grabbing;
}
.kanban-card-premium img {
    width: 100%;
    border-radius: .375rem;
    margin-bottom: .4rem;
    max-height: 130px;
    object-fit: cover;
}
.kanban-card-title {
    font-weight: 600;
    font-size: .85rem;
    line-height: 1.25;
    display: block;
    margin-bottom: .15rem;
}
.kanban-card-content {
    font-size: .75rem;
    color: var(--bs-secondary-color);
    line-height: 1.35;
}
.kanban-col-add {
    margin: .5rem;
    padding: .5rem;
    border: 1px dashed var(--bs-border-color);
    background: transparent;
    color: var(--bs-secondary-color);
    border-radius: .4rem;
    font-size: .8rem;
    transition: all .15s ease;
}
.kanban-col-add:hover {
    background: var(--bs-body-bg);
    color: var(--bs-primary);
    border-color: var(--bs-primary);
}

/* Color picker (modal) */
.kanban-color-opt { cursor: pointer; }
.kanban-color-opt input { display: none; }
.kanban-color-swatch {
    display: inline-block; width: 28px; height: 28px;
    border-radius: 50%; border: 2px solid transparent;
    transition: all .15s;
}
.kanban-color-opt input:checked + .kanban-color-swatch {
    border-color: var(--bs-body-color);
    transform: scale(1.15);
    box-shadow: 0 2px 6px rgba(0,0,0,.2);
}

[data-bs-theme="dark"] .kanban-col { background: rgba(255,255,255,.03); }
[data-bs-theme="dark"] .kanban-card-premium { background: var(--bs-tertiary-bg); }
</style>
@endpush

@push('scripts')
<script>
const csrf = document.querySelector('meta[name=csrf-token]').content;
const slug = '{{ $board->slug }}';

const cardModal = new bootstrap.Modal(document.getElementById('card-modal'));
const colModal  = new bootstrap.Modal(document.getElementById('col-modal'));

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
    return (s || '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));
}

function renderCardHtml(card) {
    const img = card.image ? `<img src="${escapeHtml(card.image)}" alt="">` : '';
    const desc = card.content ? `<div class="kanban-card-content">${escapeHtml(card.content.replace(/<[^>]+>/g, '').slice(0, 100))}</div>` : '';
    return `
        <div class="kanban-card-premium" draggable="true" data-card-id="${card.id}">
            ${img}
            <strong class="kanban-card-title">${escapeHtml(card.title)}</strong>
            ${desc}
        </div>`;
}

function recountCols() {
    document.querySelectorAll('.kanban-col').forEach(col => {
        const n = col.querySelectorAll('[data-cards-container] > .kanban-card-premium').length;
        const badge = col.querySelector('[data-col-count]');
        if (badge) badge.textContent = n;
    });
    document.getElementById('kpi-cards').textContent = document.querySelectorAll('.kanban-card-premium').length;
    document.getElementById('kpi-columns').textContent = document.querySelectorAll('.kanban-col').length;
    document.getElementById('kpi-images').textContent = document.querySelectorAll('.kanban-card-premium img').length;
}

/* ---------- drag & drop ---------- */
let dragging = null;
function bindCardDrag(card) {
    card.addEventListener('dragstart', e => {
        dragging = card;
        card.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
    });
    card.addEventListener('dragend', () => {
        card.classList.remove('dragging');
        dragging = null;
    });
    card.addEventListener('click', () => openCardEditor(card.dataset.cardId));
}

function bindContainer(container) {
    container.addEventListener('dragover', e => {
        e.preventDefault();
        container.classList.add('drag-over');
    });
    container.addEventListener('dragleave', () => container.classList.remove('drag-over'));
    container.addEventListener('drop', async e => {
        e.preventDefault();
        container.classList.remove('drag-over');
        if (!dragging) return;
        container.appendChild(dragging);
        const columnId = container.closest('.kanban-col').dataset.columnId;
        const cardId = dragging.dataset.cardId;
        const sort = Array.from(container.children).indexOf(dragging);
        try {
            await api('{{ route("admin.kanban.move") }}', { method: 'POST', body: { card_id: +cardId, column_id: +columnId, sort } });
            recountCols();
            window.toast.success('Card movido!');
        } catch (err) {
            window.toast.error('Erro ao mover: ' + err.message);
        }
    });
}

document.querySelectorAll('.kanban-card-premium').forEach(bindCardDrag);
document.querySelectorAll('[data-cards-container]').forEach(bindContainer);

/* ---------- abrir modal de card (novo / editar) ---------- */
function openCardCreator(columnId) {
    const f = document.getElementById('frm-card');
    f.reset();
    f.querySelector('[name=id]').value = '';
    f.querySelector('[name=column_id]').value = columnId;
    f.querySelector('[name=column_id_select]').value = columnId;
    document.getElementById('card-modal-title').textContent = 'Novo card';
    document.getElementById('btn-delete-card').classList.add('d-none');
    cardModal.show();
}

async function openCardEditor(cardId) {
    const cardEl = document.querySelector(`.kanban-card-premium[data-card-id="${cardId}"]`);
    if (!cardEl) return;
    const f = document.getElementById('frm-card');
    f.reset();
    f.querySelector('[name=id]').value = cardId;
    f.querySelector('[name=title]').value = cardEl.querySelector('.kanban-card-title')?.textContent || '';
    f.querySelector('[name=image]').value = cardEl.querySelector('img')?.src || '';
    f.querySelector('[name=content]').value = cardEl.dataset.content || cardEl.querySelector('.kanban-card-content')?.textContent || '';
    const colId = cardEl.closest('.kanban-col').dataset.columnId;
    f.querySelector('[name=column_id]').value = colId;
    f.querySelector('[name=column_id_select]').value = colId;
    document.getElementById('card-modal-title').textContent = 'Editar card';
    document.getElementById('btn-delete-card').classList.remove('d-none');
    cardModal.show();
}

document.querySelectorAll('[data-add-card]').forEach(btn => {
    btn.addEventListener('click', e => {
        e.preventDefault();
        openCardCreator(btn.dataset.addCard);
    });
});

document.getElementById('btn-add-card-quick')?.addEventListener('click', () => {
    const firstCol = document.querySelector('.kanban-col');
    if (firstCol) openCardCreator(firstCol.dataset.columnId);
});

/* ---------- salvar card ---------- */
document.getElementById('frm-card').addEventListener('submit', async e => {
    e.preventDefault();
    const f = e.target;
    const id = f.querySelector('[name=id]').value;
    const payload = {
        title: f.querySelector('[name=title]').value,
        content: f.querySelector('[name=content]').value,
        image: f.querySelector('[name=image]').value,
        column_id: +f.querySelector('[name=column_id_select]').value,
    };

    try {
        if (id) {
            const data = await api(`/admin/kanban/cards/${id}`, { method: 'PATCH', body: payload });
            const oldEl = document.querySelector(`.kanban-card-premium[data-card-id="${id}"]`);
            if (oldEl) oldEl.remove();
            const targetCol = document.querySelector(`.kanban-col[data-column-id="${payload.column_id}"] [data-cards-container]`);
            targetCol.insertAdjacentHTML('beforeend', renderCardHtml(data.card));
            const newEl = targetCol.lastElementChild;
            newEl.dataset.content = data.card.content || '';
            bindCardDrag(newEl);
            window.toast.success('Card atualizado!');
        } else {
            const colId = f.querySelector('[name=column_id]').value;
            const data = await api(`/admin/kanban/${slug}/cards`, { method: 'POST', body: payload });
            const targetCol = document.querySelector(`.kanban-col[data-column-id="${data.card.column_id}"] [data-cards-container]`);
            targetCol.insertAdjacentHTML('beforeend', renderCardHtml(data.card));
            const newEl = targetCol.lastElementChild;
            newEl.dataset.content = data.card.content || '';
            bindCardDrag(newEl);
            window.toast.success('Card criado!');
        }
        recountCols();
        cardModal.hide();
    } catch (err) {
        window.toast.error('Erro: ' + err.message);
    }
});

/* ---------- excluir card ---------- */
document.getElementById('btn-delete-card').addEventListener('click', async () => {
    const id = document.getElementById('frm-card').querySelector('[name=id]').value;
    if (!id) return;
    const ok = await Swal.fire({ icon: 'warning', title: 'Excluir card?', text: 'Esta acao nao pode ser desfeita.', showCancelButton: true, confirmButtonText: 'Excluir', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545' });
    if (!ok.isConfirmed) return;
    try {
        await api(`/admin/kanban/cards/${id}`, { method: 'DELETE' });
        document.querySelector(`.kanban-card-premium[data-card-id="${id}"]`)?.remove();
        recountCols();
        cardModal.hide();
        window.toast.success('Card excluido!');
    } catch (err) {
        window.toast.error('Erro: ' + err.message);
    }
});

/* ---------- colunas: novo / editar ---------- */
function openColCreator() {
    const f = document.getElementById('frm-col');
    f.reset();
    f.querySelector('[name=id]').value = '';
    f.querySelector('[name=color][value=primary]').checked = true;
    document.getElementById('col-modal-title').textContent = 'Nova coluna';
    colModal.show();
}

function openColEditor(id) {
    const colEl = document.querySelector(`.kanban-col[data-column-id="${id}"]`);
    if (!colEl) return;
    const f = document.getElementById('frm-col');
    f.reset();
    f.querySelector('[name=id]').value = id;
    f.querySelector('[name=name]').value = colEl.dataset.columnName;
    const color = colEl.dataset.columnColor || 'primary';
    const radio = f.querySelector(`[name=color][value="${color}"]`);
    if (radio) radio.checked = true;
    document.getElementById('col-modal-title').textContent = 'Editar coluna';
    colModal.show();
}

document.getElementById('btn-add-column')?.addEventListener('click', openColCreator);
document.getElementById('btn-create-first-col')?.addEventListener('click', openColCreator);

document.querySelectorAll('[data-edit-column]').forEach(btn => {
    btn.addEventListener('click', e => { e.preventDefault(); openColEditor(btn.dataset.editColumn); });
});

document.querySelectorAll('[data-delete-column]').forEach(btn => {
    btn.addEventListener('click', async e => {
        e.preventDefault();
        const id = btn.dataset.deleteColumn;
        const ok = await Swal.fire({ icon: 'warning', title: 'Excluir coluna?', text: 'Todos os cards desta coluna serao removidos.', showCancelButton: true, confirmButtonText: 'Excluir', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545' });
        if (!ok.isConfirmed) return;
        try {
            await api(`/admin/kanban/columns/${id}`, { method: 'DELETE' });
            document.querySelector(`.kanban-col[data-column-id="${id}"]`)?.remove();
            recountCols();
            window.toast.success('Coluna removida!');
        } catch (err) {
            window.toast.error('Erro: ' + err.message);
        }
    });
});

document.getElementById('frm-col').addEventListener('submit', async e => {
    e.preventDefault();
    const f = e.target;
    const id = f.querySelector('[name=id]').value;
    const payload = {
        name: f.querySelector('[name=name]').value,
        color: f.querySelector('[name=color]:checked').value,
    };
    try {
        if (id) {
            await api(`/admin/kanban/columns/${id}`, { method: 'PATCH', body: payload });
            window.toast.success('Coluna atualizada!');
        } else {
            await api(`/admin/kanban/${slug}/columns`, { method: 'POST', body: payload });
            window.toast.success('Coluna criada!');
        }
        colModal.hide();
        setTimeout(() => location.reload(), 600);
    } catch (err) {
        window.toast.error('Erro: ' + err.message);
    }
});

/* ---------- upload de imagem do card ---------- */
document.getElementById('btn-upload-card-image').addEventListener('click', async () => {
    if (!window.openUploadModal) {
        window.toast.error('Modulo de upload nao disponivel.');
        return;
    }
    window.openUploadModal({
        context: 'kanban',
        onUpload: (url) => {
            document.getElementById('frm-card').querySelector('[name=image]').value = url;
        },
    });
});
</script>
@endpush
