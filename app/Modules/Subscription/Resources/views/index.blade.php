@extends('admin.layouts.app')
@section('title', 'Assinaturas Recorrentes')
@section('content')
<div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="mb-1"><i class="bi bi-arrow-repeat me-2"></i>Assinaturas Recorrentes</h3>
            <p class="text-muted mb-0 small">Gerencie planos, assinaturas ativas, MRR e churn.</p>
        </div>
        <button class="btn btn-primary btn-sm" onclick="openPlanModal()">
            <i class="bi bi-plus-lg me-1"></i>Novo Plano
        </button>
    </div>

    {{-- KPIs ========== --}}
    <div class="row g-3 mb-4">
        <div class="col-md-6 col-lg-3">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-people"></i></div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="text-uppercase small text-muted fw-semibold">Assinaturas Ativas</div>
                        <div class="fs-4 fw-bold" id="sub-active">0</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-success-subtle text-success"><i class="bi bi-currency-dollar"></i></div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="text-uppercase small text-muted fw-semibold">MRR (Receita Mensal)</div>
                        <div class="fs-4 fw-bold text-truncate" id="sub-mrr">R$ 0</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-warning-subtle text-warning"><i class="bi bi-hourglass-split"></i></div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="text-uppercase small text-muted fw-semibold">Pagamento Pendente</div>
                        <div class="fs-4 fw-bold" id="sub-pending">0</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-danger-subtle text-danger"><i class="bi bi-x-circle"></i></div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="text-uppercase small text-muted fw-semibold">Canceladas (mes)</div>
                        <div class="fs-4 fw-bold" id="sub-churn">0</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Planos --}}
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong><i class="bi bi-box me-1"></i> Planos</strong>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover m-0">
                <thead class="table-light">
                    <tr>
                        <th>Plano</th>
                        <th>Valor</th>
                        <th>Ciclo</th>
                        <th>Assinantes</th>
                        <th>Status</th>
                        <th class="text-end">Acoes</th>
                    </tr>
                </thead>
                <tbody id="plans-tbody">
                    <tr><td colspan="6" class="text-center text-muted p-4">
                        <i class="bi bi-box display-4 d-block mb-2 opacity-25"></i>
                        Nenhum plano cadastrado. Crie o primeiro plano para comecar.
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Assinaturas recentes --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong><i class="bi bi-people me-1"></i> Assinaturas Recentes</strong>
            <form class="d-flex gap-2">
                <input type="search" name="q" class="form-control form-control-sm" placeholder="Buscar cliente..." value="{{ request('q') }}">
                <select name="status" class="form-select form-select-sm" style="width:auto;">
                    <option value="">Todos</option>
                    <option value="active">Ativa</option>
                    <option value="pending">Pendente</option>
                    <option value="canceled">Cancelada</option>
                    <option value="expired">Expirada</option>
                </select>
                <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
            </form>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover m-0">
                <thead class="table-light">
                    <tr>
                        <th>Cliente</th>
                        <th>Plano</th>
                        <th>Valor</th>
                        <th>Inicio</th>
                        <th>Proxima cobranca</th>
                        <th>Status</th>
                        <th class="text-end">Acoes</th>
                    </tr>
                </thead>
                <tbody id="subscriptions-tbody">
                    <tr><td colspan="7" class="text-center text-muted p-4">
                        <i class="bi bi-arrow-repeat display-4 d-block mb-2 opacity-25"></i>
                        Nenhuma assinatura encontrada.
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Modal novo plano --}}
<div class="modal fade" id="modal-new-plan" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-box me-2"></i>Novo Plano</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="frm-new-plan">
                    <input type="hidden" name="plan_id" id="plan_id">
                    <div class="mb-3">
                        <label class="form-label">Nome do Plano</label>
                        <input name="name" id="plan_name" class="form-control" required placeholder="Ex: Plano Basico">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Valor (R$)</label>
                            <input name="price" id="plan_price" type="text" inputmode="decimal" data-mask="money" class="form-control" required placeholder="R$ 97,90">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ciclo</label>
                            <select name="cycle" id="plan_cycle" class="form-select" required>
                                <option value="monthly">Mensal (padrão)</option>
                                <option value="7">Semanal (7 dias)</option>
                                <option value="15">Quinzenal (15 dias)</option>
                                <option value="30">Mensal (30 dias)</option>
                                <option value="60">Bimestral (60 dias)</option>
                                <option value="90">Trimestral (90 dias)</option>
                                <option value="quarterly">Trimestral</option>
                                <option value="semiannual">Semestral</option>
                                <option value="annual">Anual</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label class="form-label">Descricao</label>
                        <textarea name="description" id="plan_description" class="form-control" rows="2" placeholder="Descricao do plano..."></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Trial (dias)</label>
                            <input name="trial_days" id="plan_trial_days" type="number" class="form-control" value="0" min="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Grace period (dias)</label>
                            <input name="grace_days" id="plan_grace_days" type="number" class="form-control" value="3" min="0">
                            <small class="text-muted">Dias apos vencimento antes de suspender</small>
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label class="form-label">Gateway de cobranca</label>
                        <select name="gateway" id="plan_gateway" class="form-select">
                            <option value="mercadopago">Mercado Pago</option>
                            <option value="efipro">Efi Pro (PIX)</option>
                            <option value="sumup">SumUp</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-save-plan">
                    <i class="bi bi-check2 me-1"></i> Criar Plano
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>
const csrf = document.querySelector('meta[name=csrf-token]').content;
const baseUrl = '{{ url("/admin/subscriptions") }}';
const money = (value) => Number.parseFloat(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

// Carregar dados
async function loadData() {
    try {
        const r = await fetch(`${baseUrl}/data`, {
            credentials: 'same-origin', headers: { 'Accept': 'application/json' },
        });
        const data = await r.json().catch(() => ({}));

        if (data.metrics) {
            document.getElementById('sub-active').textContent = data.metrics.active || 0;
            document.getElementById('sub-mrr').textContent = money(data.metrics.mrr || 0);
            document.getElementById('sub-pending').textContent = data.metrics.pending || 0;
            document.getElementById('sub-churn').textContent = data.metrics.churn || 0;
        }

        if (data.plans) {
            window.currentPlans = data.plans;
            const tbody = document.getElementById('plans-tbody');
            tbody.innerHTML = data.plans.map(p => `
                <tr>
                    <td><strong>${p.name}</strong></td>
                    <td>${money(p.price)}</td>
                    <td>${cycleName(p.cycle)}</td>
                    <td>${p.subscribers || 0}</td>
                    <td><span class="badge text-bg-${p.active ? 'success' : 'secondary'}">${p.active ? 'Ativo' : 'Inativo'}</span></td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-primary" onclick="editPlan(${p.id})"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deletePlan(${p.id})"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>
            `).join('');
        }

        if (data.subscriptions && data.subscriptions.length > 0) {
            const tbody = document.getElementById('subscriptions-tbody');
            const statusColors = { active: 'success', pending: 'warning', canceled: 'danger', expired: 'secondary' };
            tbody.innerHTML = data.subscriptions.map(s => `
                <tr>
                    <td>${s.customer_name || '-'}</td>
                    <td>${s.plan_name || '-'}</td>
                    <td>${money(s.price || 0)}</td>
                    <td>${s.started_at || '-'}</td>
                    <td>${s.next_billing || '-'}</td>
                    <td><span class="badge text-bg-${statusColors[s.status] || 'secondary'}">${s.status}</span></td>
                    <td class="text-end">
                        ${s.status === 'canceled' 
                            ? `<button class="btn btn-sm btn-outline-success" onclick="reactivateSub(${s.id})" title="Reativar"><i class="bi bi-arrow-counterclockwise"></i></button>`
                            : `<button class="btn btn-sm btn-outline-warning" onclick="cancelSub(${s.id})" title="Cancelar"><i class="bi bi-x-circle"></i></button>`
                        }
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteSub(${s.id})" title="Excluir"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>
            `).join('');
        }
    } catch (e) {
        console.log('Modulo de assinaturas: dados ainda nao disponiveis.');
    }
}

function cycleName(c) {
    const map = {
        monthly: 'Mensal',
        quarterly: 'Trimestral',
        semiannual: 'Semestral',
        annual: 'Anual',
        '7': 'Semanal (7 dias)',
        '15': 'Quinzenal (15 dias)',
        '30': 'Mensal (30 dias)',
        '60': 'Bimestral (60 dias)',
        '90': 'Trimestral (90 dias)'
    };
    return map[c] || c;
}

// Salvar plano
document.getElementById('btn-save-plan').addEventListener('click', async () => {
    const form = document.getElementById('frm-new-plan');
    const fd = new FormData(form);
    if (window.adminUI?.parseMoney) {
        fd.set('price', window.adminUI.parseMoney(fd.get('price')));
    }
    const planId = document.getElementById('plan_id').value;
    const url = planId ? `${baseUrl}/plans/${planId}` : `${baseUrl}/plans`;
    
    // Para Laravel aceitar PUT com FormData
    if (planId) {
        fd.append('_method', 'PUT');
    }

    const r = await fetch(url, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        body: fd,
    });
    if (r.ok) {
        window.toast.success(planId ? 'Plano atualizado!' : 'Plano criado com sucesso!');
        bootstrap.Modal.getInstance(document.getElementById('modal-new-plan')).hide();
        form.reset();
        loadData();
    } else {
        const err = await r.json().catch(() => ({}));
        window.toast.error(err.message || 'Erro ao salvar plano.');
    }
});

function openPlanModal() {
    document.getElementById('plan_id').value = '';
    document.getElementById('frm-new-plan').reset();
    document.querySelector('#modal-new-plan .modal-title').innerHTML = '<i class="bi bi-box me-2"></i>Novo Plano';
    new bootstrap.Modal(document.getElementById('modal-new-plan')).show();
}

function editPlan(id) {
    const plan = window.currentPlans?.find(p => p.id === id);
    if (!plan) return;
    
    document.getElementById('plan_id').value = plan.id;
    document.getElementById('plan_name').value = plan.name || '';
    document.getElementById('plan_price').value = plan.price ? (Number(plan.price).toFixed(2).replace('.', ',')) : '0,00';
    document.getElementById('plan_cycle').value = plan.cycle || 'monthly';
    document.getElementById('plan_description').value = plan.description || '';
    document.getElementById('plan_trial_days').value = plan.trial_days || 0;
    document.getElementById('plan_grace_days').value = plan.grace_days || 3;
    document.getElementById('plan_gateway').value = plan.gateway || 'mercadopago';
    
    document.querySelector('#modal-new-plan .modal-title').innerHTML = '<i class="bi bi-pencil me-2"></i>Editar Plano';
    new bootstrap.Modal(document.getElementById('modal-new-plan')).show();
}

async function deletePlan(id) {
    const confirm = await Swal.fire({
        title: 'Excluir plano?', text: 'Assinaturas ativas serao canceladas.',
        icon: 'warning', showCancelButton: true,
        confirmButtonText: 'Sim, excluir', cancelButtonText: 'Cancelar',
    });
    if (!confirm.isConfirmed) return;
    const r = await fetch(`${baseUrl}/plans/${id}`, {
        method: 'DELETE', credentials: 'same-origin',
        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
    });
    if (r.ok) { window.toast.success('Plano excluido.'); loadData(); }
    else window.toast.error('Erro ao excluir.');
}

async function cancelSub(id) {
    const confirm = await Swal.fire({
        title: 'Cancelar assinatura?', text: 'O cliente nao sera mais cobrado.',
        icon: 'warning', showCancelButton: true,
        confirmButtonText: 'Sim, cancelar', cancelButtonText: 'Voltar',
    });
    if (!confirm.isConfirmed) return;
    const r = await fetch(`${baseUrl}/${id}/cancel`, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
    });
    if (r.ok) { window.toast.success('Assinatura cancelada.'); loadData(); }
    else window.toast.error('Erro ao cancelar.');
}

async function reactivateSub(id) {
    const confirm = await Swal.fire({
        title: 'Reativar assinatura?', text: 'A assinatura voltara a ficar ativa.',
        icon: 'question', showCancelButton: true,
        confirmButtonText: 'Sim, reativar', cancelButtonText: 'Voltar',
    });
    if (!confirm.isConfirmed) return;
    const r = await fetch(`${baseUrl}/${id}/reactivate`, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
    });
    if (r.ok) { window.toast.success('Assinatura reativada.'); loadData(); }
    else window.toast.error('Erro ao reativar.');
}

async function deleteSub(id) {
    const confirm = await Swal.fire({
        title: 'Excluir assinatura?', text: 'Isso removera a assinatura completamente do sistema.',
        icon: 'warning', showCancelButton: true,
        confirmButtonText: 'Sim, excluir', cancelButtonText: 'Voltar',
    });
    if (!confirm.isConfirmed) return;
    const r = await fetch(`${baseUrl}/${id}`, {
        method: 'DELETE', credentials: 'same-origin',
        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
    });
    if (r.ok) { window.toast.success('Assinatura excluida.'); loadData(); }
    else window.toast.error('Erro ao excluir.');
}

loadData();
</script>
@endpush
