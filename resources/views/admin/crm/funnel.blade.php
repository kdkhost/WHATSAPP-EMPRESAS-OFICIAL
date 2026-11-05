@extends('admin.layouts.app')
@section('title', 'Funil de Vendas')

@section('content')
<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="mb-1"><i class="bi bi-funnel me-2"></i>Funil de Vendas</h3>
            <p class="text-muted mb-0 small">Gerencie leads e oportunidades com drag-and-drop entre etapas.</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modal-add-lead">
                <i class="bi bi-plus-lg me-1"></i>Novo Lead
            </button>
            <button class="btn btn-outline-secondary btn-sm" onclick="loadFunnel()">
                <i class="bi bi-arrow-clockwise me-1"></i>Atualizar
            </button>
        </div>
    </div>

    {{-- KPIs --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body p-3 d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small text-uppercase">Leads Ativos</div>
                        <div class="fs-4 fw-bold text-primary" id="metric-leads">0</div>
                    </div>
                    <div class="stat-icon bg-primary text-white"><i class="bi bi-person-lines-fill"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body p-3 d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small text-uppercase">Em Negociação</div>
                        <div class="fs-4 fw-bold text-warning" id="metric-negotiation">0</div>
                    </div>
                    <div class="stat-icon bg-warning text-white"><i class="bi bi-chat-dots"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body p-3 d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small text-uppercase">Ganhos (mês)</div>
                        <div class="fs-4 fw-bold text-success" id="metric-won">0</div>
                    </div>
                    <div class="stat-icon bg-success text-white"><i class="bi bi-trophy"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body p-3 d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small text-uppercase">Taxa Conversão</div>
                        <div class="fs-4 fw-bold text-info" id="metric-conversion">0%</div>
                    </div>
                    <div class="stat-icon bg-info text-white"><i class="bi bi-percent"></i></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Funil Board --}}
    <div class="card">
        <div class="card-header">
            <span><i class="bi bi-kanban me-1"></i> Pipeline</span>
            <small class="text-muted">Arraste os cards entre as etapas</small>
        </div>
        <div class="card-body p-3">
            <div class="d-flex gap-3 overflow-auto pb-2" id="funnel-board" style="min-height:420px;">
                @php
                    $stages = [
                        ['id' => 'new', 'name' => 'Novo Lead', 'color' => 'primary', 'icon' => 'bi-person-plus'],
                        ['id' => 'contact', 'name' => 'Contato Feito', 'color' => 'info', 'icon' => 'bi-telephone'],
                        ['id' => 'proposal', 'name' => 'Proposta', 'color' => 'warning', 'icon' => 'bi-file-earmark-text'],
                        ['id' => 'negotiation', 'name' => 'Negociação', 'color' => 'secondary', 'icon' => 'bi-chat-dots'],
                        ['id' => 'won', 'name' => 'Ganho ✓', 'color' => 'success', 'icon' => 'bi-check-circle'],
                        ['id' => 'lost', 'name' => 'Perdido', 'color' => 'danger', 'icon' => 'bi-x-circle'],
                    ];
                @endphp
                @foreach($stages as $stage)
                <div class="funnel-column flex-shrink-0" style="width:220px;" data-stage="{{ $stage['id'] }}">
                    <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                        <span class="badge text-bg-{{ $stage['color'] }}">
                            <i class="bi {{ $stage['icon'] }} me-1"></i>{{ $stage['name'] }}
                        </span>
                        <span class="badge bg-body-tertiary text-body stage-count" data-stage="{{ $stage['id'] }}">0</span>
                    </div>
                    <div class="funnel-cards d-flex flex-column gap-2" data-stage="{{ $stage['id'] }}"
                         ondragover="event.preventDefault();this.classList.add('drag-over')"
                         ondragleave="this.classList.remove('drag-over')"
                         ondrop="dropCard(event, '{{ $stage['id'] }}')">
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

{{-- Modal novo lead --}}
<div class="modal fade" id="modal-add-lead" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Novo Lead</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="frm-add-lead">
                    <div class="mb-3">
                        <label class="form-label">Nome <span class="text-danger">*</span></label>
                        <input name="name" class="form-control" required placeholder="Nome do lead/cliente">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Telefone</label>
                            <input name="phone" class="form-control" data-mask="phone" placeholder="(11) 99999-9999">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">E-mail</label>
                            <input name="email" type="email" class="form-control" placeholder="email@exemplo.com">
                        </div>
                    </div>
                    <div class="row g-3 mt-0">
                        <div class="col-md-6">
                            <label class="form-label">Valor Estimado (R$)</label>
                            <div class="input-group">
                                <span class="input-group-text">R$</span>
                                <input name="value" type="text" inputmode="decimal" data-mask="decimal" class="form-control" placeholder="0,00">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tags</label>
                            <input name="tags" class="form-control" placeholder="vip, indicação">
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label class="form-label">Observações</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Notas sobre o lead..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-save-lead">
                    <i class="bi bi-check2 me-1"></i>Salvar Lead
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('head')
<style>
.funnel-cards {
    min-height: 380px;
    background: var(--bs-tertiary-bg);
    border-radius: 0.5rem;
    padding: 0.5rem;
    transition: all 0.2s;
}
.funnel-cards.drag-over { background: color-mix(in srgb, var(--bs-primary) 10%, var(--bs-tertiary-bg)); border: 2px dashed var(--bs-primary); }
.funnel-card { cursor: grab; transition: all 0.15s; }
.funnel-card:active { cursor: grabbing; opacity: 0.7; transform: rotate(2deg); }
.funnel-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,.1); }
</style>
@endpush

@push('scripts')
<script>
const csrf = document.querySelector('meta[name=csrf-token]').content;
const fmtMoney = value => window.adminUI?.formatMoney
    ? window.adminUI.formatMoney(value)
    : Number.parseFloat(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

async function loadFunnel() {
    try {
        const r = await fetch('{{ url("/admin/kanban/cards") }}?format=json', { credentials:'same-origin', headers:{'Accept':'application/json'} });
        const data = await r.json().catch(() => ({ columns:[], cards:[] }));

        const stageMap = { 'novo':'new','new':'new','lead':'new','contato':'contact','contact':'contact','proposta':'proposal','proposal':'proposal','negociacao':'negotiation','negotiation':'negotiation','ganho':'won','won':'won','fechado':'won','perdido':'lost','lost':'lost' };

        document.querySelectorAll('.funnel-cards').forEach(col => col.innerHTML = '');
        let counts = { new:0, contact:0, proposal:0, negotiation:0, won:0, lost:0 };

        if (data.cards && data.cards.length) {
            data.cards.forEach(card => {
                const colName = (card.column || card.stage || 'new').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
                const stage = stageMap[colName] || 'new';
                counts[stage]++;
                appendCard(stage, card);
            });
        }

        Object.keys(counts).forEach(s => {
            const el = document.querySelector(`.stage-count[data-stage="${s}"]`);
            if (el) el.textContent = counts[s];
        });

        document.getElementById('metric-leads').textContent = counts.new + counts.contact;
        document.getElementById('metric-negotiation').textContent = counts.proposal + counts.negotiation;
        document.getElementById('metric-won').textContent = counts.won;
        const total = Object.values(counts).reduce((a, b) => a + b, 0);
        document.getElementById('metric-conversion').textContent = total > 0 ? Math.round((counts.won / total) * 100) + '%' : '0%';
    } catch (e) { console.error('Funnel error:', e); }
}

function appendCard(stage, card) {
    const container = document.querySelector(`.funnel-cards[data-stage="${stage}"]`);
    if (!container) return;
    const div = document.createElement('div');
    div.className = 'card funnel-card border-0 shadow-sm';
    div.draggable = true;
    div.dataset.cardId = card.id;
    div.ondragstart = (e) => e.dataTransfer.setData('cardId', card.id);
    div.innerHTML = `<div class="card-body p-2">
        <div class="fw-semibold small">${card.title || card.name || 'Sem nome'}</div>
        ${card.value ? `<div class="text-success small fw-bold">${fmtMoney(card.value)}</div>` : ''}
        ${card.phone ? `<div class="text-muted small"><i class="bi bi-telephone me-1"></i>${card.phone}</div>` : ''}
        ${card.tags ? `<div class="mt-1">${card.tags.split(',').map(t => `<span class="badge bg-body-tertiary text-body border" style="font-size:0.6rem;">${t.trim()}</span>`).join(' ')}</div>` : ''}
    </div>`;
    container.appendChild(div);
}

async function dropCard(event, newStage) {
    event.preventDefault();
    event.currentTarget.classList.remove('drag-over');
    const cardId = event.dataTransfer.getData('cardId');
    if (!cardId) return;
    const r = await fetch('{{ route("admin.kanban.move") }}', {
        method:'POST', credentials:'same-origin',
        headers:{ 'X-CSRF-TOKEN':csrf, 'Content-Type':'application/json', 'Accept':'application/json' },
        body: JSON.stringify({ card_id:cardId, column:newStage }),
    });
    if (r.ok) { window.toast.success('Lead movido!'); loadFunnel(); }
    else window.toast.error('Erro ao mover.');
}

document.getElementById('btn-save-lead').addEventListener('click', async () => {
    const form = document.getElementById('frm-add-lead');
    const fd = new FormData(form);
    if (window.adminUI?.parseMoney && fd.has('value')) {
        fd.set('value', window.adminUI.parseMoney(fd.get('value')));
    }
    fd.append('slug', 'cards');
    fd.append('column', 'new');
    const btn = document.getElementById('btn-save-lead');
    btn.disabled = true;

    const r = await fetch('{{ route("admin.kanban.cards.store", "cards") }}', {
        method:'POST', credentials:'same-origin',
        headers:{ 'X-CSRF-TOKEN':csrf, 'Accept':'application/json' },
        body: fd,
    });
    btn.disabled = false;
    if (r.ok) {
        window.toast.success('Lead adicionado!');
        bootstrap.Modal.getInstance(document.getElementById('modal-add-lead')).hide();
        form.reset();
        loadFunnel();
    } else {
        const err = await r.json().catch(() => ({}));
        window.toast.error(err.message || 'Erro ao salvar.');
    }
});

loadFunnel();
</script>
@endpush
