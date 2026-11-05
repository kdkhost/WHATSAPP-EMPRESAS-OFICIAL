@php
    $routePrefix = $routePrefix ?? (request()->is('painel/*') || session('admin_assist_mode') ? 'tenant.gestao-financeira.' : 'admin.gestao-financeira.');
    $baseUrl = str_starts_with($routePrefix, 'admin') ? '/admin/gestao-financeira' : '/painel/gestao-financeira';
@endphp
@extends('gestao-financeira::layouts.master')

@section('title', 'Lançamentos')
@section('page_title', 'Controle de lançamentos')

@section('page_actions')
    <button type="button" class="btn btn-primary" id="btn-new-transaction">
        <i class="bi bi-plus-lg me-1"></i> Novo lançamento
    </button>
@endsection

@section('addon_content')

{{-- KPIs do período filtrado --}}
@php
    $allItems   = $transactions->getCollection();
    $totalRec   = $allItems->where('type','receita')->sum('amount');
    $totalDesp  = $allItems->where('type','despesa')->sum('amount');
    $totalPago  = $allItems->where('status','pago')->sum('amount');
    $totalPend  = $allItems->where('status','pendente')->sum('amount');
@endphp
<div class="row g-3 mb-4" id="kpi-row">
    @foreach([
        ['Receitas no período',  $totalRec,  'success', 'bi-arrow-down-circle'],
        ['Despesas no período',  $totalDesp, 'danger',  'bi-arrow-up-circle'],
        ['Total pago',           $totalPago, 'primary', 'bi-check-circle'],
        ['Total pendente',       $totalPend, 'warning', 'bi-clock'],
    ] as [$lbl,$val,$cor,$ico])
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-sm h-100 border-0" style="border-left:4px solid var(--bs-{{ $cor }}) !important; border-radius:14px;">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="stat-icon bg-{{ $cor }}-subtle text-{{ $cor }}" style="width:46px;height:46px;font-size:1.2rem;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="bi {{ $ico }}"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold text-uppercase" style="font-size:.7rem;letter-spacing:.06em;">{{ $lbl }}</div>
                    <div class="fw-bold fs-5 text-{{ $cor }}">R$ {{ number_format($val,2,',','.') }}</div>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- Filtros --}}
<div class="card shadow-sm mb-4" style="border-radius:16px;">
    <div class="card-body py-3">
        <form id="frm-filter" action="{{ $baseUrl }}/transacoes" method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Pesquisar</label>
                <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Descrição...">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold">Início</label>
                <input type="date" name="start_date" value="{{ $startDate }}" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold">Fim</label>
                <input type="date" name="end_date" value="{{ $endDate }}" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-1">
                <label class="form-label small fw-semibold">Tipo</label>
                <select name="type" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="receita" @selected(request('type')==='receita')>Receita</option>
                    <option value="despesa" @selected(request('type')==='despesa')>Despesa</option>
                </select>
            </div>
            <div class="col-6 col-md-1">
                <label class="form-label small fw-semibold">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="pago" @selected(request('status')==='pago')>Pago</option>
                    <option value="pendente" @selected(request('status')==='pendente')>Pendente</option>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label small fw-semibold">Conta</label>
                <select name="account_id" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    @foreach($accounts as $a)
                        <option value="{{ $a->id }}" @selected(request('account_id')==$a->id)>{{ $a->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label small fw-semibold">Categoria</label>
                <select name="category_id" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    @foreach($categories as $c)
                        <option value="{{ $c->id }}" @selected(request('category_id')==$c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="bi bi-funnel"></i></button>
                <a href="{{ $baseUrl }}/transacoes" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x"></i></a>
            </div>
        </form>
    </div>
</div>

{{-- Tabela --}}
<div class="card shadow-sm" style="border-radius:16px;" id="transactions-card">
    <div class="card-header d-flex align-items-center justify-content-between py-2">
        <strong><i class="bi bi-arrow-down-up me-1 text-primary"></i> Lançamentos</strong>
        <span class="badge bg-secondary-subtle text-secondary border">{{ $transactions->total() }} registros</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle m-0" id="table-transactions">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Descrição</th>
                        <th>Valor</th>
                        <th>Vencimento</th>
                        <th>Conta</th>
                        <th>Categoria</th>
                        <th>Status</th>
                        <th class="text-end pe-3">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $t)
                    <tr id="row-{{ $t->id }}" class="transaction-row" style="transition:.3s;">
                        <td class="ps-3">
                            <div class="fw-semibold">{{ $t->description }}</div>
                            @if($t->notes)
                                <div class="small text-muted text-truncate" style="max-width:220px;">{{ $t->notes }}</div>
                            @endif
                        </td>
                        <td class="fw-bold {{ $t->type === 'receita' ? 'text-success' : 'text-danger' }}">
                            {{ $t->type === 'receita' ? '+' : '−' }} R$ {{ number_format($t->amount,2,',','.') }}
                        </td>
                        <td>
                            <div class="small">{{ $t->due_date->format('d/m/Y') }}</div>
                            @if($t->payment_date)
                                <div class="small text-muted">Pago: {{ $t->payment_date->format('d/m/Y') }}</div>
                            @endif
                        </td>
                        <td class="small">{{ $t->account?->name ?? '—' }}</td>
                        <td>
                            @if($t->category)
                                <span class="badge" style="background:{{ $t->category->color }}22;color:{{ $t->category->color }};border:1px solid {{ $t->category->color }}55;">
                                    {{ $t->category->name }}
                                </span>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td>
                            @if($t->status === 'pago')
                                <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check-circle me-1"></i>Pago</span>
                            @else
                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle"><i class="bi bi-clock me-1"></i>Pendente</span>
                            @endif
                        </td>
                        <td class="text-end pe-3">
                            <div class="d-flex gap-1 justify-content-end">
                                @if($t->status === 'pendente')
                                    <button class="btn btn-sm btn-outline-success btn-pay" data-id="{{ $t->id }}" title="Marcar como pago">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                @endif
                                @if($t->attachment_path)
                                    <a href="{{ asset('storage/'.$t->attachment_path) }}" target="_blank" class="btn btn-sm btn-outline-info" title="Anexo">
                                        <i class="bi bi-paperclip"></i>
                                    </a>
                                @endif
                                <button class="btn btn-sm btn-outline-primary btn-edit-tx" data-id="{{ $t->id }}" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger btn-del-tx" data-id="{{ $t->id }}" title="Excluir">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                        Nenhum lançamento encontrado.
                        <div class="mt-3">
                            <button type="button" class="btn btn-primary btn-sm" id="btn-new-transaction-empty">
                                <i class="bi bi-plus-lg me-1"></i> Novo lançamento
                            </button>
                        </div>
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($transactions->hasPages())
    <div class="card-footer">{{ $transactions->links() }}</div>
    @endif
</div>

{{-- ========== MODAL: Criar / Editar ========== --}}
<div class="modal fade" id="modalTransaction" tabindex="-1" aria-labelledby="modalTransactionLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="frm-transaction" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="_method" id="tx-method" value="POST">
                <input type="hidden" name="_id" id="tx-id" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTransactionLabel"><i class="bi bi-plus-circle me-2 text-primary"></i>Novo lançamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        {{-- Tipo --}}
                        <div class="col-12">
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="type" id="tx-receita" value="receita" checked>
                                <label class="btn btn-outline-success" for="tx-receita"><i class="bi bi-arrow-down-circle me-1"></i>Receita</label>
                                <input type="radio" class="btn-check" name="type" id="tx-despesa" value="despesa">
                                <label class="btn btn-outline-danger" for="tx-despesa"><i class="bi bi-arrow-up-circle me-1"></i>Despesa</label>
                            </div>
                        </div>
                        {{-- Descrição --}}
                        <div class="col-12">
                            <label class="form-label fw-semibold">Descrição <span class="text-danger">*</span></label>
                            <input type="text" name="description" class="form-control" required placeholder="Ex.: recebimento projeto, aluguel, fornecedor...">
                        </div>
                        {{-- Valor + Situação --}}
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Valor <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">R$</span>
                                <input type="number" name="amount" step="0.01" min="0.01" class="form-control" required placeholder="0,00">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Vencimento <span class="text-danger">*</span></label>
                            <input type="date" name="due_date" class="form-control" required value="{{ now()->toDateString() }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Situação</label>
                            <select name="status" id="tx-status" class="form-select">
                                <option value="pendente">Pendente</option>
                                <option value="pago">Pago</option>
                            </select>
                        </div>
                        {{-- Data pagamento (aparece se pago) --}}
                        <div class="col-md-4" id="tx-payment-date-wrap">
                            <label class="form-label fw-semibold">Data de pagamento</label>
                            <input type="date" name="payment_date" id="tx-payment-date" class="form-control" value="{{ now()->toDateString() }}">
                        </div>
                        {{-- Conta + Categoria --}}
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Conta <span class="text-danger">*</span></label>
                            <select name="account_id" id="tx-account" class="form-select" required>
                                <option value="">Selecione</option>
                                @foreach($accounts as $a)
                                    <option value="{{ $a->id }}">{{ $a->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Categoria <span class="text-danger">*</span></label>
                            <select name="category_id" id="tx-category" class="form-select" required>
                                <option value="">Selecione</option>
                                @foreach($categories as $c)
                                    <option value="{{ $c->id }}" data-type="{{ $c->type }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        {{-- Observações --}}
                        <div class="col-12">
                            <label class="form-label fw-semibold">Observações</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Detalhes adicionais..."></textarea>
                        </div>
                        {{-- Anexo --}}
                        <div class="col-12">
                            <label class="form-label fw-semibold">Anexo / comprovante</label>
                            <input type="file" name="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                            <div class="form-text">PDF, JPG ou PNG até 4 MB.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="tx-submit">
                        <span class="spinner-border spinner-border-sm d-none me-1" id="tx-spinner"></span>
                        <i class="bi bi-check2-circle me-1" id="tx-icon"></i>
                        <span id="tx-btn-label">Salvar lançamento</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
(function(){
    const csrf   = document.querySelector('meta[name=csrf-token]')?.content ?? '';
    const base   = @json($baseUrl);
    const modalId = 'modalTransaction';
    const showModal = () => window.$ && typeof $.fn.modal === 'function' ? $('#'+modalId).modal('show') : bootstrap.Modal.getOrCreateInstance(document.getElementById(modalId)).show();
    const hideModal = () => window.$ && typeof $.fn.modal === 'function' ? $('#'+modalId).modal('hide') : bootstrap.Modal.getInstance(document.getElementById(modalId))?.hide();
    const frm    = document.getElementById('frm-transaction');

    /* ── helpers ────────────────────────────────────────── */
    const toast = (msg, type='success') => {
        if (window.toastr) { window.toastr[type](msg); return; }
        Swal.fire({ icon: type === 'error' ? 'error' : 'success', title: msg, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
    };

    const setLoading = (on) => {
        document.getElementById('tx-spinner').classList.toggle('d-none', !on);
        document.getElementById('tx-icon').classList.toggle('d-none', on);
        document.getElementById('tx-submit').disabled = on;
    };

    /* ── campo pagamento condicional ─────────────────────── */
    const txStatus = document.getElementById('tx-status');
    const payWrap  = document.getElementById('tx-payment-date-wrap');
    const syncPay  = () => payWrap.classList.toggle('d-none', txStatus.value !== 'pago');
    txStatus.addEventListener('change', syncPay);
    syncPay();

    /* ── categorias filtradas por tipo ──────────────────── */
    const txType = document.querySelectorAll('input[name="type"]');
    const txCat  = document.getElementById('tx-category');
    const syncCat = () => {
        const sel = [...txType].find(r=>r.checked)?.value ?? 'receita';
        [...txCat.options].forEach(o => {
            if(!o.value){ o.hidden=false; return; }
            o.hidden = o.dataset.type !== sel;
        });
        if(txCat.selectedOptions[0]?.hidden) txCat.value='';
    };
    txType.forEach(r => r.addEventListener('change', syncCat));

    /* ── abrir modal novo ───────────────────────────────── */
    const openNew = () => {
        frm.reset();
        document.getElementById('tx-id').value = '';
        document.getElementById('tx-method').value = 'POST';
        document.getElementById('modalTransactionLabel').innerHTML = '<i class="bi bi-plus-circle me-2 text-primary"></i>Novo lançamento';
        document.getElementById('tx-btn-label').textContent = 'Salvar lançamento';
        document.querySelector('input[name="due_date"]').value = new Date().toISOString().split('T')[0];
        document.getElementById('tx-payment-date').value = new Date().toISOString().split('T')[0];
        syncPay(); syncCat();
        showModal();
    };
    document.getElementById('btn-new-transaction')?.addEventListener('click', openNew);
    document.getElementById('btn-new-transaction-empty')?.addEventListener('click', openNew);

    /* ── editar ─────────────────────────────────────────── */
    document.querySelectorAll('.btn-edit-tx').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id = btn.dataset.id;
            const r  = await fetch(`${base}/transacoes/${id}/editar`, { headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'} });
            if(!r.ok){ toast('Erro ao carregar lançamento','error'); return; }
            const data = await r.json();
            if(!data.ok){ toast(data.message || 'Erro','error'); return; }
            const t = data.transaction;

            frm.reset();
            document.getElementById('tx-id').value = t.id;
            document.getElementById('tx-method').value = 'PUT';
            document.getElementById('modalTransactionLabel').innerHTML = '<i class="bi bi-pencil me-2 text-warning"></i>Editar lançamento';
            document.getElementById('tx-btn-label').textContent = 'Salvar alterações';

            // preenche campos
            const field = n => frm.querySelector(`[name="${n}"]`);
            const radio = v => frm.querySelector(`input[name="type"][value="${v}"]`);
            if(radio(t.type)) radio(t.type).checked = true;
            field('description').value = t.description ?? '';
            field('amount').value      = t.amount ?? '';
            field('due_date').value    = t.due_date ?? '';
            field('status').value      = t.status ?? 'pendente';
            document.getElementById('tx-payment-date').value = t.payment_date ?? '';
            document.getElementById('tx-account').value   = t.account_id ?? '';
            document.getElementById('tx-category').value  = t.category_id ?? '';
            field('notes').value = t.notes ?? '';

            syncPay(); syncCat();
            showModal();
        });
    });

    /* ── submit (criar / editar) ────────────────────────── */
    frm.addEventListener('submit', async e => {
        e.preventDefault();
        setLoading(true);
        const id     = document.getElementById('tx-id').value;
        const method = document.getElementById('tx-method').value;
        const url    = id ? `${base}/transacoes/${id}/atualizar` : `${base}/transacoes/salvar`;
        const fd     = new FormData(frm);
        // multipart POST com método override
        try {
            const r = await fetch(url, {
                method:'POST', credentials:'same-origin',
                headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'},
                body: fd
            });
            const d = await r.json();
            if(d.ok || r.ok){
                hideModal();
                toast(id ? 'Lançamento atualizado!' : 'Lançamento criado!');
                setTimeout(()=>location.reload(), 500);
            } else {
                toast(d.message || 'Erro ao salvar.','error');
            }
        } catch(err){ toast('Erro de rede.','error'); }
        setLoading(false);
    });

    /* ── pagar ──────────────────────────────────────────── */
    document.querySelectorAll('.btn-pay').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id  = btn.dataset.id;
            const row = document.getElementById(`row-${id}`);
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            btn.disabled  = true;
            try {
                const r = await fetch(`${base}/transacoes/${id}/pagar`, {
                    method:'POST', credentials:'same-origin',
                    headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}
                });
                const d = await r.json();
                if(d.ok || r.ok){
                    toast('Lançamento baixado com sucesso!');
                    // Anima linha
                    if(row){
                        row.style.opacity='.4';
                        row.style.transform='translateX(8px)';
                        setTimeout(()=>location.reload(), 600);
                    }
                } else {
                    toast(d.message||'Erro','error');
                    btn.innerHTML='<i class="bi bi-check-lg"></i>';
                    btn.disabled=false;
                }
            } catch(err){
                toast('Erro de rede.','error');
                btn.innerHTML='<i class="bi bi-check-lg"></i>';
                btn.disabled=false;
            }
        });
    });

    /* ── excluir ─────────────────────────────────────────── */
    document.querySelectorAll('.btn-del-tx').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id = btn.dataset.id;
            const confirmed = await Swal.fire({
                icon:'warning', title:'Excluir lançamento?',
                text:'Esta ação não pode ser desfeita.',
                showCancelButton:true,
                confirmButtonText:'Excluir', cancelButtonText:'Cancelar',
                confirmButtonColor:'#dc3545'
            });
            if(!confirmed.isConfirmed) return;
            btn.innerHTML='<span class="spinner-border spinner-border-sm"></span>';
            try {
                const r = await fetch(`${base}/transacoes/${id}/excluir`, {
                    method:'POST', credentials:'same-origin',
                    headers:{'X-CSRF-TOKEN':csrf,'X-HTTP-METHOD-OVERRIDE':'DELETE','Accept':'application/json','Content-Type':'application/json'},
                    body: JSON.stringify({_method:'DELETE'})
                });
                const d = await r.json();
                if(d.ok||r.ok){
                    const row = document.getElementById(`row-${id}`);
                    if(row){ row.style.opacity='0'; row.style.height='0'; setTimeout(()=>row.remove(),300); }
                    toast('Lançamento excluído!');
                } else { toast(d.message||'Erro','error'); }
            } catch(err){ toast('Erro de rede.','error'); }
        });
    });
})();
</script>
@endsection
