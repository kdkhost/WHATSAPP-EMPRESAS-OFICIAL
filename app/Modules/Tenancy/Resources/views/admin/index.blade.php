@extends('admin.layouts.app')
@section('title', 'Planos e Tenants')
@section('content')
<div class="container-fluid">

    <x-ui.page-header
        title="Planos e Tenants"
        subtitle="Configure planos de assinatura, limites por plano e gerencie tenants ativos. Trial, ciclo de cobrança e recursos customizáveis."
        eyebrow="Superadmin"
        class="mb-4"
    >
        <x-slot:actions>
        <div class="d-flex gap-2 align-items-center justify-content-end">
            <a href="{{ route('admin.tenancy.tenants') }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-people me-1"></i> Ver tenants
            </a>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalPlan">
                <i class="bi bi-plus-lg me-1"></i> Novo plano
            </button>
        </div>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- ========== KPIs ========== --}}
    <div class="row g-3 mb-4">
        @php $cards = [
            ['Planos',     $stats['plans'],              'primary', 'bi-box'],
            ['Ativos',     $stats['tenants_active'],     'success', 'bi-check-circle'],
            ['Em teste',   $stats['tenants_trial'],      'info',    'bi-hourglass-split'],
            ['Suspensos',  $stats['tenants_suspended'],  'danger',  'bi-pause-circle'],
        ]; @endphp
        @foreach($cards as [$label, $value, $color, $icon])
        <div class="col-md-6 col-lg-3">
            <x-ui.stats-card :label="$label" :value="$value" :color="$color" :icon="$icon" class="h-100" />
        </div>
        @endforeach
    </div>

    {{-- ========== PLANOS ========== --}}
    <div class="card shadow-sm">
        <div class="card-header">
            <strong><i class="bi bi-box me-1 text-primary"></i> Planos disponíveis</strong>
            <small class="text-muted">{{ count($plans) }} {{ count($plans) === 1 ? 'plano' : 'planos' }}</small>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover m-0 align-middle">
                    <thead>
                        <tr>
                            <th>Plano</th>
                            <th class="text-end">Preço</th>
                            <th class="text-end">Instância extra</th>
                            <th>Ciclo</th>
                            <th class="text-center">Trial</th>
                            <th class="text-center">Limites</th>
                            <th class="text-center">Tenants</th>
                            <th class="text-end" style="width: 120px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($plans as $p)
                        <tr id="plan-{{ $p->id }}">
                            <td>
                                <strong class="d-block">{{ $p->name }}</strong>
                                @if($p->description)
                                    <small class="text-muted d-block text-truncate" style="max-width: 320px;">{{ $p->description }}</small>
                                @endif
                                <div class="mt-1 d-flex flex-wrap gap-1">
                                    @if($p->custom_gateway)<span class="badge bg-info-subtle text-info border border-info-subtle"><i class="bi bi-credit-card me-1"></i>Gateway próprio</span>@endif
                                    @if($p->custom_templates)<span class="badge bg-primary-subtle text-primary border border-primary-subtle"><i class="bi bi-file-text me-1"></i>Templates</span>@endif
                                    @if($p->installment_enabled)<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-calculator me-1"></i>Parcelamento</span>@endif
                                    @if($p->fee_passthrough)<span class="badge bg-warning-subtle text-warning border border-warning-subtle"><i class="bi bi-cash-coin me-1"></i>Repassa taxas</span>@endif
                                </div>
                            </td>
                            <td class="text-end">
                                <strong class="text-primary">{{ \App\Support\Format::money($p->price) }}</strong>
                            </td>
                            <td class="text-end">
                                <strong>{{ \App\Support\Format::money($p->extra_instance_price ?? 0) }}</strong>
                                <small class="text-muted d-block">recorrente</small>
                            </td>
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                                    {{ ['monthly' => 'Mensal', 'quarterly' => 'Trimestral', 'semiannual' => 'Semestral', 'annual' => 'Anual'][$p->billing_cycle] ?? $p->billing_cycle }}
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="small">{{ $p->trial_days }}</span>
                                <small class="text-muted d-block">dias</small>
                            </td>
                            <td class="text-center small">
                                <div><i class="bi bi-phone me-1"></i>{{ $p->max_instances }}</div>
                                <div class="text-muted">{{ $p->max_customers }} clientes</div>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-primary">{{ $p->tenants_count }}</span>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-primary" data-edit-plan="{{ $p->id }}" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" data-del-plan="{{ $p->id }}" title="Excluir">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">
                                <i class="bi bi-box fs-1 d-block mb-2"></i>
                                Nenhum plano criado.
                                <div class="mt-3">
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalPlan">
                                        <i class="bi bi-plus-lg me-1"></i> Criar primeiro plano
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

    <div class="card shadow-sm mt-4">
        <div class="card-header">
            <div>
                <strong><i class="bi bi-cash-coin me-1 text-success"></i> Preços de instâncias extras</strong>
                <small class="text-muted d-block">Tabela usada na compra recorrente de instâncias acima do limite do plano.</small>
            </div>
            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalExtraPrice">
                <i class="bi bi-plus-lg me-1"></i> Novo preço
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover m-0 align-middle">
                    <thead>
                        <tr>
                            <th>Regra</th>
                            <th>Plano</th>
                            <th>Quantidade extra</th>
                            <th class="text-end">Valor mensal</th>
                            <th>Status</th>
                            <th class="text-end" style="width:120px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($extraInstancePrices as $price)
                            <tr id="extra-price-{{ $price->id }}">
                                <td>
                                    <strong>{{ $price->name }}</strong>
                                    <small class="text-muted d-block">Ordem {{ $price->sort }}</small>
                                </td>
                                <td>{{ $price->plan?->name ?? 'Todos os planos' }}</td>
                                <td>
                                    {{ $price->min_quantity }}
                                    @if($price->max_quantity)
                                        até {{ $price->max_quantity }}
                                    @else
                                        ou mais
                                    @endif
                                </td>
                                <td class="text-end fw-semibold">{{ \App\Support\Format::money($price->price) }}</td>
                                <td>
                                    <span class="badge bg-{{ $price->active ? 'success' : 'secondary' }}-subtle text-{{ $price->active ? 'success' : 'secondary' }} border border-{{ $price->active ? 'success' : 'secondary' }}-subtle">
                                        {{ $price->active ? 'Ativo' : 'Inativo' }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary" data-edit-extra-price="{{ $price->id }}" title="Editar"><i class="bi bi-pencil"></i></button>
                                    <button class="btn btn-sm btn-outline-danger" data-del-extra-price="{{ $price->id }}" title="Excluir"><i class="bi bi-trash"></i></button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Nenhum preço específico cadastrado. O sistema usa o valor do plano como fallback.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- ========== MODAL: Plano ========== --}}
<div class="modal fade" id="modalPlan" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="frm-plan">
                @csrf
                <input type="hidden" name="id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-box me-2"></i><span id="plan-modal-title">Novo plano</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Nome <span class="text-danger">*</span></label>
                            <input name="name" class="form-control" maxlength="120" required placeholder="Ex.: Profissional">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Preço</label>
                            <div class="input-group">
                                <span class="input-group-text">R$</span>
                                <input name="price" type="text" inputmode="decimal" data-mask="decimal" class="form-control" required placeholder="97,90">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Instância extra</label>
                            <div class="input-group">
                                <span class="input-group-text">R$</span>
                                <input name="extra_instance_price" type="text" inputmode="decimal" data-mask="decimal" class="form-control" placeholder="39,90">
                            </div>
                            <small class="text-muted">Valor mensal recorrente por instância acima do limite.</small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Ciclo</label>
                            <select name="billing_cycle" class="form-select">
                                <option value="monthly">Mensal</option>
                                <option value="quarterly">Trimestral</option>
                                <option value="semiannual">Semestral</option>
                                <option value="annual">Anual</option>
                            </select>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label">Descrição</label>
                            <textarea name="description" class="form-control" rows="2" maxlength="500" placeholder="Breve descrição do plano"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Badge (Etiqueta)</label>
                            <input name="badge" class="form-control" maxlength="50" placeholder="Ex.: Mais Popular">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="highlight" value="1" id="ph">
                                <label class="form-check-label text-warning fw-bold" for="ph"><i class="bi bi-star-fill"></i> Destaque</label>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Itens da Tabela de Preços (Features)</label>
                            <textarea name="features" class="form-control" rows="4" placeholder="Um item por linha. Ex:&#10;Relatórios Completos&#10;Suporte VIP"></textarea>
                            <small class="text-muted">Estes itens aparecerão como os diferenciais (✓) na tabela de preços do site público.</small>
                        </div>

                        <div class="col-12">
                            <h6 class="text-uppercase small text-muted fw-semibold mt-2 mb-0"><i class="bi bi-sliders me-1"></i> Limites</h6>
                            <hr class="mt-2">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Trial (dias)</label>
                            <input name="trial_days" type="number" min="0" max="90" class="form-control" value="7">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Máx. instâncias</label>
                            <input name="max_instances" type="number" min="1" class="form-control" value="1">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Max clientes</label>
                            <input name="max_customers" type="number" min="1" class="form-control" value="100">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Max faturas/mes</label>
                            <input name="max_invoices_month" type="number" min="0" class="form-control" value="500">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Max msgs/mes</label>
                            <input name="max_messages_month" type="number" min="0" class="form-control" value="1000">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Max contratos/mês</label>
                            <input name="max_contracts_month" type="number" min="0" class="form-control" placeholder="Ilimitado">
                            <small class="text-muted">Vazio ou 0 = ilimitado</small>
                        </div>

                        <div class="col-12">
                            <h6 class="text-uppercase small text-muted fw-semibold mt-3 mb-0"><i class="bi bi-toggles me-1"></i> Features</h6>
                            <hr class="mt-2">
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="custom_gateway" value="1" id="cg">
                                <label class="form-check-label" for="cg">Gateway proprio</label>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="custom_templates" value="1" checked id="ct">
                                <label class="form-check-label" for="ct">Templates proprios</label>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="installment_enabled" value="1" id="ie">
                                <label class="form-check-label" for="ie">Parcelamento</label>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="fee_passthrough" value="1" id="fp">
                                <label class="form-check-label" for="fp">Repasse de taxas</label>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="contracts_module" value="1" id="cm">
                                <label class="form-check-label" for="cm"><i class="bi bi-file-earmark-text me-1 text-primary"></i>Módulo Contratos</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2-circle me-1"></i> Salvar plano
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalExtraPrice" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="frm-extra-price">
                @csrf
                <input type="hidden" name="id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-cash-coin me-2"></i><span id="extra-price-title">Novo preço de instância extra</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nome da regra</label>
                            <input name="name" class="form-control" maxlength="120" required placeholder="Ex.: Instância extra padrão">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Plano</label>
                            <select name="plan_id" class="form-select">
                                <option value="">Todos os planos</option>
                                @foreach($plans as $plan)
                                    <option value="{{ $plan->id }}">{{ $plan->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Minimo</label>
                            <input name="min_quantity" type="number" min="1" max="999" value="1" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Maximo</label>
                            <input name="max_quantity" type="number" min="1" max="999" class="form-control" placeholder="Sem limite">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Valor mensal</label>
                            <div class="input-group">
                                <span class="input-group-text">R$</span>
                                <input name="price" class="form-control" data-mask="decimal" inputmode="decimal" required placeholder="39,90">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Ordem</label>
                            <input name="sort" type="number" min="0" max="9999" class="form-control" value="0">
                        </div>
                        <input type="hidden" name="billing_cycle" value="monthly">
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="active" value="1" id="extra-active" checked>
                                <label class="form-check-label" for="extra-active">Regra ativa</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-check2-circle me-1"></i>Salvar preço</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const csrf = document.querySelector('meta[name=csrf-token]').content;
const planModal = new bootstrap.Modal(document.getElementById('modalPlan'));
const extraPriceModal = new bootstrap.Modal(document.getElementById('modalExtraPrice'));
let extraPrices = @json($extraInstancePrices->values());

document.getElementById('frm-plan').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData(e.target);
    ['custom_gateway','custom_templates','installment_enabled','fee_passthrough','highlight','contracts_module'].forEach(k => {
        if (!fd.has(k)) fd.set(k, '0');
    });
    
    const isEdit = !!fd.get('id');
    const url = isEdit ? `{{ url('/admin/tenancy/plans') }}/${fd.get('id')}` : '{{ route("admin.tenancy.plans.store") }}';
    
    // Se for put, adiciona o campo _method
    if (isEdit) fd.append('_method', 'PUT');

    try {
        const r = await fetch(url, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: fd
        });
        const d = await r.json();
        if (r.ok) { window.toast.success('Plano salvo!'); setTimeout(() => location.reload(), 600); }
        else window.toast.error(d.message || 'Erro ao salvar.');
    } catch (err) { window.toast.error('Erro de rede.'); }
});

// Limpa form ao fechar modal
document.getElementById('modalPlan').addEventListener('hidden.bs.modal', function () {
    document.getElementById('frm-plan').reset();
    document.querySelector('input[name="id"]').value = '';
    document.getElementById('plan-modal-title').textContent = 'Novo plano';
});

// Editar plano
let allPlans = [];
fetch('{{ url("/admin/tenancy/plans") }}').then(r => r.json()).then(data => allPlans = data);

document.querySelectorAll('[data-edit-plan]').forEach(btn => btn.onclick = () => {
    const id = btn.dataset.editPlan;
    const p = allPlans.find(x => x.id == id);
    if (!p) return;
    
    const f = document.getElementById('frm-plan');
    f.reset();
    f.elements['id'].value = p.id;
    f.elements['name'].value = p.name;
    f.elements['price'].value = window.adminUI?.masks?.decimal
        ? window.adminUI.masks.decimal(String(p.price || '0').replace('.', ','))
        : p.price;
    f.elements['extra_instance_price'].value = window.adminUI?.masks?.decimal
        ? window.adminUI.masks.decimal(String(p.extra_instance_price || '0').replace('.', ','))
        : (p.extra_instance_price || 0);
    f.elements['billing_cycle'].value = p.billing_cycle;
    f.elements['description'].value = p.description || '';
    f.elements['badge'].value = p.badge || '';
    f.elements['trial_days'].value = p.trial_days;
    f.elements['max_instances'].value = p.max_instances;
    f.elements['max_customers'].value = p.max_customers;
    f.elements['max_invoices_month'].value = p.max_invoices_month;
    f.elements['max_messages_month'].value = p.max_messages_month;
    f.elements['max_contracts_month'].value = p.max_contracts_month || '';
    
    if (p.features) {
        try {
            const arr = typeof p.features === 'string' ? JSON.parse(p.features) : p.features;
            // Filtra 'contracts' do textarea — ele é controlado pelo checkbox
            const displayArr = Array.isArray(arr) ? arr.filter(f => f.toLowerCase() !== 'contracts') : [];
            f.elements['features'].value = displayArr.join('\n');
            // Marca o checkbox se 'contracts' estiver no array
            document.getElementById('cm').checked = Array.isArray(arr) && arr.some(v => v.toLowerCase() === 'contracts');
        } catch(e) { document.getElementById('cm').checked = false; }
    } else {
        document.getElementById('cm').checked = false;
    }

    document.getElementById('cg').checked = !!p.custom_gateway;
    document.getElementById('ct').checked = !!p.custom_templates;
    document.getElementById('ie').checked = !!p.installment_enabled;
    document.getElementById('fp').checked = !!p.fee_passthrough;
    document.getElementById('ph').checked = !!p.highlight;
    
    document.getElementById('plan-modal-title').textContent = 'Editar plano: ' + p.name;
    planModal.show();
});

document.querySelectorAll('[data-del-plan]').forEach(btn => btn.onclick = async () => {
    const ok = await Swal.fire({
        icon: 'warning', title: 'Excluir plano?',
        text: 'Tenants ativos impedirão a exclusão.',
        showCancelButton: true, confirmButtonText: 'Excluir', cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545'
    });
    if (!ok.isConfirmed) return;
    try {
        const r = await fetch(`{{ url('/admin/tenancy/plans') }}/${btn.dataset.delPlan}`, {
            method: 'DELETE', credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
        });
        const d = await r.json();
        if (r.ok) { window.toast.success('Plano excluído!'); btn.closest('tr')?.remove(); }
        else window.toast.error(d.error || 'Erro ao excluir.');
    } catch (err) { window.toast.error('Erro de rede.'); }
});

document.getElementById('modalExtraPrice').addEventListener('hidden.bs.modal', () => {
    document.getElementById('frm-extra-price').reset();
    document.querySelector('#frm-extra-price [name="id"]').value = '';
    document.getElementById('extra-price-title').textContent = 'Novo preço de instância extra';
});

document.getElementById('frm-extra-price').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData(e.target);
    if (!fd.has('active')) fd.set('active', '0');
    const isEdit = !!fd.get('id');
    const url = isEdit
        ? `{{ url('/admin/tenancy/extra-instance-prices') }}/${fd.get('id')}`
        : '{{ route("admin.tenancy.extra-instance-prices.store") }}';
    if (isEdit) fd.append('_method', 'PUT');

    try {
        const r = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: fd
        });
        const d = await r.json();
        if (r.ok && d.ok) {
            window.toast.success('Preço salvo.');
            setTimeout(() => location.reload(), 600);
        } else {
            window.toast.error(d.error || d.message || 'Erro ao salvar preço.');
        }
    } catch (err) {
        window.toast.error('Erro de rede.');
    }
});

document.querySelectorAll('[data-edit-extra-price]').forEach(btn => btn.onclick = () => {
    const price = extraPrices.find(item => String(item.id) === String(btn.dataset.editExtraPrice));
    if (!price) return;
    const form = document.getElementById('frm-extra-price');
    form.reset();
    form.elements.id.value = price.id;
    form.elements.name.value = price.name || '';
    form.elements.plan_id.value = price.plan_id || '';
    form.elements.min_quantity.value = price.min_quantity || 1;
    form.elements.max_quantity.value = price.max_quantity || '';
    form.elements.price.value = window.adminUI?.masks?.decimal
        ? window.adminUI.masks.decimal(String(price.price || '0').replace('.', ','))
        : (price.price || 0);
    form.elements.sort.value = price.sort || 0;
    form.elements.active.checked = !!price.active;
    document.getElementById('extra-price-title').textContent = 'Editar preço: ' + price.name;
    extraPriceModal.show();
});

document.querySelectorAll('[data-del-extra-price]').forEach(btn => btn.onclick = async () => {
    const ok = await Swal.fire({
        icon: 'warning',
        title: 'Excluir preço?',
        text: 'Novas compras deixarao de usar esta regra.',
        showCancelButton: true,
        confirmButtonText: 'Excluir',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545'
    });
    if (!ok.isConfirmed) return;
    try {
        const r = await fetch(`{{ url('/admin/tenancy/extra-instance-prices') }}/${btn.dataset.delExtraPrice}`, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
        });
        const d = await r.json();
        if (r.ok && d.ok) {
            document.getElementById(`extra-price-${btn.dataset.delExtraPrice}`)?.remove();
            window.toast.success('Preço excluído.');
        } else {
            window.toast.error(d.error || d.message || 'Erro ao excluir.');
        }
    } catch (err) {
        window.toast.error('Erro de rede.');
    }
});
</script>
@endpush
