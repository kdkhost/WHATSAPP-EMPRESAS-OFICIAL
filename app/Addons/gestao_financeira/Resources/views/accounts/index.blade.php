@php
    $isTenantView = request()->is('painel*');
    $routePrefix = $routePrefix ?? ($isTenantView ? 'tenant.gestao-financeira.' : 'admin.gestao-financeira.');
    $baseUrl = str_starts_with($routePrefix, 'admin') ? '/admin/gestao-financeira' : '/painel/gestao-financeira';
    $isOpenFinanceConfigured = $isOpenFinanceConfigured ?? app(\App\Addons\gestao_financeira\Services\OpenFinanceService::class)->isConfigured();
    $canManageOpenFinance = $canManageOpenFinance ?? (! $isTenantView && \Illuminate\Support\Facades\Auth::guard('admin')->check());
@endphp
@extends('gestao-financeira::layouts.master')

@push('head')
    <script src="https://cdn.pluggy.ai/pluggy-connect/latest/pluggy-connect.js"></script>
@endpush

@section('title', 'Contas')
@section('page_title', 'Contas e carteiras')

@section('page_actions')
    {{-- Open Finance: No painel do tenant, exibe APENAS o botão Conectar Open Finance (ou Desconectar se já tiver vínculo) --}}
    @if($isOpenFinanceConfigured)
        @if($openFinanceConnection)
            <button type="button" class="btn btn-outline-danger" id="btn-manage-open-finance" data-id="{{ $openFinanceConnection->id }}">
                <i class="bi bi-plug me-1"></i> Desconectar Open Finance
            </button>
        @else
            <button type="button" class="btn btn-outline-primary" id="btn-open-finance">
                <i class="bi bi-bank2 me-1"></i> Conectar Open Finance
            </button>
        @endif

        {{-- Botão de administração exibido EXCLUSIVAMENTE no painel do SuperAdmin (/admin/) --}}
        @if($canManageOpenFinance)
            <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#modalOpenFinanceHelp">
                <i class="bi bi-gear me-1"></i> Configurações Open Finance
            </button>
        @endif
    @elseif($canManageOpenFinance)
        {{-- Exibido APENAS no painel do SuperAdmin se o Open Finance ainda não tiver chaves configuradas --}}
        <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalOpenFinanceHelp">
            <i class="bi bi-gear me-1"></i> Configurar Open Finance <span class="badge bg-warning text-dark ms-1">Pendente</span>
        </button>
    @endif

    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAccount">
        <i class="bi bi-plus-lg me-1"></i> Nova conta
    </button>
@endsection

@section('addon_content')

{{-- KPIs --}}
<div class="row g-3 mb-4">
    @php $totalSaldo = $accounts->sum('current_balance'); @endphp
    @foreach([
        ['Saldo consolidado', $totalSaldo, 'primary', 'bi-wallet2'],
        ['Total de contas',   $accounts->count(), 'info', 'bi-bank'],
        ['Contas bancárias',  $accounts->where('type','banco')->count(), 'success', 'bi-building'],
        ['Carteiras digitais',$accounts->where('type','digital')->count(), 'warning', 'bi-phone'],
    ] as [$lbl,$val,$cor,$ico])
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-sm border-0 h-100" style="border-left:4px solid var(--bs-{{ $cor }}) !important;border-radius:14px;">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="stat-icon bg-{{ $cor }}-subtle text-{{ $cor }}" style="width:46px;height:46px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:1.2rem;">
                    <i class="bi {{ $ico }}"></i>
                </div>
                <div>
                    <div class="text-muted fw-semibold text-uppercase" style="font-size:.7rem;letter-spacing:.06em;">{{ $lbl }}</div>
                    <div class="fw-bold fs-5 text-{{ $cor }}">{{ is_float($val) || (is_numeric($val) && str_contains((string)$val,'.')) ? 'R$ '.number_format($val,2,',','.') : $val }}</div>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- Banner de Conexão Ativa Open Finance --}}
@if($openFinanceConnection && ($isOpenFinanceConfigured || $canManageOpenFinance))
<div class="card shadow-sm border-0 mb-4" style="border-radius:16px; border-left: 4px solid var(--bs-success) !important; background: rgba(var(--bs-success-rgb), 0.05);">
    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3 py-3">
        <div class="d-flex align-items-center gap-3">
            <div class="stat-icon bg-success-subtle text-success" style="width:48px;height:48px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.4rem;">
                <i class="bi bi-bank2"></i>
            </div>
            <div>
                <div class="d-flex align-items-center gap-2">
                    <h6 class="mb-0 fw-bold">{{ $openFinanceConnection->connector_name ?? 'Conta Bancária Vinculada' }}</h6>
                    <span class="badge bg-success text-white small"><i class="bi bi-check-circle me-1"></i>Conectado via Open Finance</span>
                </div>
                <small class="text-muted">Extratos e saldos bancários sincronizados automaticamente via Pluggy.</small>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#modalOpenFinanceHelp">
                <i class="bi bi-info-circle me-1"></i> Como funciona
            </button>
            <button type="button" class="btn btn-outline-danger btn-sm btn-disconnect-of" data-id="{{ $openFinanceConnection->id }}">
                <i class="bi bi-plug me-1"></i> Desconectar Banco
            </button>
        </div>
    </div>
</div>
@endif

{{-- Grid de contas --}}
<div class="row g-3 mb-4" id="accounts-grid">
    @forelse($accounts as $acc)
    @php
        $typeIcon = ['banco'=>'bi-building','digital'=>'bi-phone','dinheiro'=>'bi-cash-coin','cartao'=>'bi-credit-card','outro'=>'bi-wallet2'][$acc->type] ?? 'bi-wallet2';
        $typeCor  = ['banco'=>'primary','digital'=>'info','dinheiro'=>'warning','cartao'=>'secondary','outro'=>'secondary'][$acc->type] ?? 'secondary';
        $typeLabel= ['banco'=>'Bancária','digital'=>'Digital','dinheiro'=>'Físico','cartao'=>'Cartão','outro'=>'Outro'][$acc->type] ?? 'Outro';
    @endphp
    <div class="col-md-6 col-xl-4" id="account-card-{{ $acc->id }}">
        <div class="card shadow-sm border-0 h-100" style="border-radius:16px;transition:.3s;">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="stat-icon bg-{{ $typeCor }}-subtle text-{{ $typeCor }}" style="width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;">
                            <i class="bi {{ $typeIcon }}"></i>
                        </div>
                        <div>
                            <div class="fw-bold">{{ $acc->name }}</div>
                            <span class="badge bg-{{ $typeCor }}-subtle text-{{ $typeCor }} border border-{{ $typeCor }}-subtle small">{{ $typeLabel }}</span>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-1">
                        <button type="button" class="btn btn-sm btn-outline-primary px-2" title="Editar conta" onclick="editAccount({{ $acc->id }}, '{{ addslashes($acc->name) }}', '{{ $acc->type }}', '{{ addslashes($acc->description ?? '') }}', {{ $acc->transactions_count }})">
                            <i class="bi bi-pencil me-1"></i>Editar
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger px-2 btn-del-account" title="Excluir conta" data-id="{{ $acc->id }}" data-count="{{ $acc->transactions_count }}">
                            <i class="bi bi-trash me-1"></i>Excluir
                        </button>
                    </div>
                </div>
                <div class="mb-1">
                    <div class="text-muted small fw-semibold text-uppercase" style="font-size:.7rem;letter-spacing:.06em;">Saldo atual</div>
                    <div class="fw-bold fs-4 {{ $acc->current_balance >= 0 ? 'text-success' : 'text-danger' }}">
                        R$ {{ number_format($acc->current_balance,2,',','.') }}
                    </div>
                </div>
                @if($acc->description)
                    <div class="text-muted small mt-2 text-truncate">{{ $acc->description }}</div>
                @endif
                <div class="mt-2 pt-2 border-top d-flex justify-content-between align-items-center">
                    <small class="text-muted"><i class="bi bi-arrow-down-up me-1"></i>{{ $acc->transactions_count }} lançamentos</small>
                    <small class="text-muted">Inicial: R$ {{ number_format($acc->initial_balance,2,',','.') }}</small>
                </div>
            </div>
        </div>
    </div>
    @empty
    <div class="col-12">
        <div class="text-center py-5 text-muted">
            <i class="bi bi-bank fs-1 d-block mb-3 opacity-50"></i>
            <h6>Nenhuma conta cadastrada</h6>
            <p class="small">Cadastre contas e carteiras para controlar seus saldos.</p>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalAccount">
                <i class="bi bi-plus-lg me-1"></i> Criar primeira conta
            </button>
        </div>
    </div>
    @endforelse
</div>

{{-- Modal --}}
<div class="modal fade" id="modalAccount" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="frm-account">
                @csrf
                <input type="hidden" name="_id" id="acc-id">
                <div class="modal-header">
                    <h5 class="modal-title" id="acc-modal-title"><i class="bi bi-bank me-2 text-primary"></i>Nova conta</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold">Nome <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="Ex.: Banco do Brasil, Nubank, Caixa...">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Tipo</label>
                        <select name="type" class="form-select" required>
                            <option value="banco">Conta bancária</option>
                            <option value="digital">Carteira digital</option>
                            <option value="dinheiro">Dinheiro físico</option>
                            <option value="cartao">Cartão de crédito</option>
                            <option value="outro">Outro</option>
                        </select>
                    </div>
                    <div class="col-md-6" id="acc-initial-wrap">
                        <label class="form-label fw-semibold">Saldo inicial</label>
                        <div class="input-group">
                            <span class="input-group-text">R$</span>
                            <input type="number" name="initial_balance" step="0.01" class="form-control" value="0.00" placeholder="0,00">
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Observações</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Banco, agência, chave PIX..."></textarea>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between align-items-center">
                    <button type="button" class="btn btn-outline-danger d-none" id="acc-btn-delete">
                        <i class="bi bi-trash me-1"></i> Excluir conta
                    </button>
                    <div class="d-flex gap-2 ms-auto">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="acc-submit">
                            <span class="spinner-border spinner-border-sm d-none me-1" id="acc-spinner"></span>
                            <i class="bi bi-check2-circle me-1" id="acc-icon"></i>
                            <span id="acc-btn-label">Criar conta</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal de Ajuda & Configurações do Open Finance --}}
@if($isOpenFinanceConfigured || $canManageOpenFinance)
<div class="modal fade" id="modalOpenFinanceHelp" tabindex="-1" aria-labelledby="modalOpenFinanceHelpTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow" style="border-radius:20px;">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title fw-bold" id="modalOpenFinanceHelpTitle">
                        <i class="bi bi-bank2 me-2 text-primary"></i>Integração Open Finance
                    </h5>
                    <p class="text-muted small mb-0">Conexão bancária automática homologada pelo Banco Central do Brasil.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body pt-3">
                @if($canManageOpenFinance)
                <ul class="nav nav-pills nav-fill mb-4" id="pills-tab-of" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link {{ $isOpenFinanceConfigured ? 'active' : '' }} fw-semibold" id="pills-tenant-tab" data-bs-toggle="pill" data-bs-target="#pills-tenant" type="button" role="tab">
                            <i class="bi bi-person-check me-1"></i> Para Você (Cliente)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link {{ ! $isOpenFinanceConfigured ? 'active' : '' }} fw-semibold" id="pills-admin-tab" data-bs-toggle="pill" data-bs-target="#pills-admin" type="button" role="tab">
                            <i class="bi bi-shield-lock me-1"></i> Administrativo (Dono do SaaS) @if(! $isOpenFinanceConfigured)<span class="badge bg-danger ms-1">Configurar</span>@endif
                        </button>
                    </li>
                </ul>
                @endif

                <div class="tab-content" id="pills-tabContent-of">
                    {{-- Aba Cliente --}}
                    <div class="tab-pane fade {{ ($isOpenFinanceConfigured || ! $canManageOpenFinance) ? 'show active' : '' }}" id="pills-tenant" role="tabpanel">
                        <div class="card border-0 bg-body-tertiary mb-3 p-3" style="border-radius:14px;">
                            <h6 class="fw-bold text-primary mb-2"><i class="bi bi-lightning-charge me-1"></i> Como conectar seu banco:</h6>
                            <ol class="small mb-0 ps-3 text-secondary lh-lg">
                                <li>Clique no botão <strong>Conectar Open Finance</strong> no topo da página de Contas.</li>
                                <li>Na janela segura da <strong>Pluggy</strong>, escolha a sua instituição bancária (Bradesco, Nubank, Itaú, Banco do Brasil, Santander, Inter, Mercado Pago, etc.).</li>
                                <li>Faça a autenticação segura diretamente com o seu banco (leitura de QR Code ou aplicativo).</li>
                                <li>Após autorizar, o saldo atual e os lançamentos serão importados automaticamente para o sistema!</li>
                            </ol>
                        </div>

                        <div class="card border-0 bg-body-tertiary p-3" style="border-radius:14px;">
                            <h6 class="fw-bold text-danger mb-2"><i class="bi bi-plug me-1"></i> Como desconectar seu banco:</h6>
                            <p class="small text-secondary mb-0">
                                Para desativar a qualquer momento, basta clicar no botão vermelho <strong>Desconectar Open Finance</strong> no topo ou no banner de status desta tela. Ao confirmar, o vínculo bancário é imediatamente encerrado e a sincronização é interrompida.
                            </p>
                        </div>
                    </div>

                    @if($canManageOpenFinance)
                    {{-- Aba Admin (Apenas para o Administrador do SaaS) --}}
                    <div class="tab-pane fade {{ ! $isOpenFinanceConfigured ? 'show active' : '' }}" id="pills-admin" role="tabpanel">
                        <div class="alert alert-primary border-0 d-flex align-items-start gap-2 mb-3" style="border-radius:14px;">
                            <i class="bi bi-info-circle-fill fs-5 mt-1"></i>
                            <div class="small">
                                <strong>Configuração Centralizada do SaaS:</strong> O Open Finance utiliza a infraestrutura da <strong>Pluggy.ai</strong>. Ao cadastrar suas chaves de API uma única vez aqui, todos os clientes/tenants da sua plataforma poderão conectar seus bancos.
                            </div>
                        </div>

                        <div class="card border-0 bg-body-tertiary mb-3 p-3" style="border-radius:14px;">
                            <h6 class="fw-bold text-dark mb-2"><i class="bi bi-1-circle me-1 text-primary"></i> Passo a Passo para Obter Credenciais:</h6>
                            <ol class="small mb-0 ps-3 text-secondary lh-lg">
                                <li>Crie uma conta gratuita em <a href="https://dashboard.pluggy.ai" target="_blank" class="fw-bold text-decoration-none">dashboard.pluggy.ai <i class="bi bi-box-arrow-up-right small"></i></a>.</li>
                                <li>Acesse o menu <strong>API Keys / Chaves de API</strong> no painel da Pluggy.</li>
                                <li>Copie o seu <strong>Client ID</strong> e o <strong>Client Secret</strong> gerados.</li>
                                <li>No menu <strong>Webhooks</strong> da Pluggy, cadastre a seguinte URL para sincronização automática:</li>
                            </ol>
                            <div class="input-group mt-2">
                                <input type="text" class="form-control font-monospace small" readonly value="{{ url('api/gestao-financeira/open-finance-webhook') }}" id="input-of-webhook">
                                <button class="btn btn-outline-secondary btn-sm" type="button" onclick="navigator.clipboard.writeText(document.getElementById('input-of-webhook').value); toast('URL do Webhook copiada!');">
                                    <i class="bi bi-clipboard me-1"></i> Copiar
                                </button>
                            </div>
                        </div>

                        {{-- Formulário de Credenciais Globais do Admin --}}
                        <div class="card border border-primary-subtle p-3" style="border-radius:14px;">
                            <h6 class="fw-bold text-primary mb-3"><i class="bi bi-sliders me-1"></i> Salvar Credenciais da Pluggy no SaaS:</h6>
                            <form id="frm-of-admin-config" autocomplete="off">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold small">Pluggy Client ID <span class="text-danger">*</span></label>
                                        <input type="text" name="client_id" id="of-client-id" class="form-control" required autocomplete="off" placeholder="Ex.: 5d0f99aa-7cd2-...">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold small">Pluggy Client Secret <span class="text-danger">*</span></label>
                                        <input type="password" name="client_secret" id="of-client-secret" class="form-control" required autocomplete="new-password" placeholder="Ex.: L8RnRoZiP...">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-semibold small">Ambiente / Base URL</label>
                                        <select name="base_url" id="of-base-url" class="form-select">
                                            <option value="https://api.pluggy.ai">Produção (https://api.pluggy.ai)</option>
                                        </select>
                                    </div>
                                    <div class="col-12 d-flex justify-content-between align-items-center mt-3">
                                        <span id="of-config-status" class="small text-muted">Carregando status...</span>
                                        <button type="submit" class="btn btn-primary" id="btn-save-of-config">
                                            <span class="spinner-border spinner-border-sm d-none me-1" id="spinner-of-save"></span>
                                            <i class="bi bi-check2-circle me-1" id="icon-of-save"></i> Salvar no SaaS
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>
@endif

@endsection

@section('scripts')
<script>
(function(){
    const csrf  = document.querySelector('meta[name=csrf-token]')?.content ?? '';
    const base  = @json($baseUrl);
    const modalId = 'modalAccount';
    const showModal = () => window.$ && typeof $.fn.modal === 'function' ? $('#'+modalId).modal('show') : bootstrap.Modal.getOrCreateInstance(document.getElementById(modalId)).show();
    const hideModal = () => window.$ && typeof $.fn.modal === 'function' ? $('#'+modalId).modal('hide') : bootstrap.Modal.getInstance(document.getElementById(modalId))?.hide();
    const frm   = document.getElementById('frm-account');
    const accBtnDelete = document.getElementById('acc-btn-delete');

    const setLoading = on => {
        document.getElementById('acc-spinner').classList.toggle('d-none',!on);
        document.getElementById('acc-icon').classList.toggle('d-none',on);
        document.getElementById('acc-submit').disabled = on;
    };
    const toast = (msg, type='success') => {
        if (window.toastr) { window.toastr[type](msg); return; }
        Swal.fire({ icon: type === 'error' ? 'error' : 'success', title: msg, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
    };

    // Reset ao abrir novo
    document.getElementById('modalAccount').addEventListener('show.bs.modal', function(e){
        if(!e.relatedTarget) return;
        frm.reset();
        document.getElementById('acc-id').value = '';
        document.getElementById('acc-modal-title').innerHTML = '<i class="bi bi-bank me-2 text-primary"></i>Nova conta';
        document.getElementById('acc-btn-label').textContent = 'Criar conta';
        document.getElementById('acc-initial-wrap').style.display = '';
        if (accBtnDelete) accBtnDelete.classList.add('d-none');
    });

    // Excluir conta
    window.deleteAccount = async (id, name = '', count = 0) => {
        if (count > 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Não é possível excluir',
                text: `Esta conta possui ${count} lançamento(s) vinculado(s). Para excluí-la, transfira ou exclua os lançamentos antes.`,
                confirmButtonText: 'Entendi'
            });
            return;
        }

        const ok = await Swal.fire({
            icon: 'warning',
            title: name ? `Excluir conta "${name}"?` : 'Excluir conta?',
            text: 'Esta ação não poderá ser desfeita.',
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-trash me-1"></i> Sim, excluir',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545',
            focusCancel: true
        });

        if (!ok.isConfirmed) return;

        try {
            const r = await fetch(`${base}/contas/${id}/excluir`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'X-HTTP-METHOD-OVERRIDE': 'DELETE',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ _method: 'DELETE' })
            });
            const d = await r.json();
            if (d.ok || r.ok) {
                hideModal();
                const card = document.getElementById(`account-card-${id}`);
                if (card) {
                    card.style.transition = 'all .3s ease';
                    card.style.opacity = '0';
                    card.style.transform = 'scale(.95)';
                    setTimeout(() => {
                        card.remove();
                        const grid = document.getElementById('accounts-grid');
                        if (grid && grid.children.length === 0) location.reload();
                    }, 300);
                } else {
                    setTimeout(() => location.reload(), 400);
                }
                toast(d.message || 'Conta excluída com sucesso!');
            } else {
                toast(d.message || 'Erro ao excluir conta.', 'error');
            }
        } catch (err) {
            toast('Erro de rede ao excluir conta.', 'error');
        }
    };

    // Editar
    window.editAccount = (id, name, type, description, count = 0) => {
        frm.reset();
        document.getElementById('acc-id').value = id;
        document.getElementById('acc-modal-title').innerHTML = '<i class="bi bi-pencil me-2 text-warning"></i>Editar conta';
        document.getElementById('acc-btn-label').textContent = 'Salvar alterações';
        document.getElementById('acc-initial-wrap').style.display = 'none';
        frm.elements['name'].value = name;
        frm.elements['type'].value = type;
        frm.elements['description'].value = description;

        if (accBtnDelete) {
            accBtnDelete.classList.remove('d-none');
            accBtnDelete.onclick = () => deleteAccount(id, name, count);
        }

        showModal();
    };

    // Submit da conta
    frm.addEventListener('submit', async e => {
        e.preventDefault();
        setLoading(true);
        const id  = document.getElementById('acc-id').value;
        const url = id ? `${base}/contas/${id}/atualizar` : `${base}/contas/salvar`;
        const fd  = new FormData(frm);
        try {
            const r = await fetch(url, {
                method:'POST', credentials:'same-origin',
                headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}, body:fd
            });
            const d = await r.json();
            if(d.ok||r.ok){ hideModal(); toast(id?'Conta atualizada!':'Conta criada!'); setTimeout(()=>location.reload(),500); }
            else toast(d.message||'Erro ao salvar.','error');
        } catch(err){ toast('Erro de rede.','error'); }
        setLoading(false);
    });

    // Excluir conta pelo botão do card
    document.querySelectorAll('.btn-del-account').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.id;
            const name = btn.closest('.card')?.querySelector('.fw-bold')?.textContent?.trim() || '';
            const count = parseInt(btn.dataset.count || '0');
            deleteAccount(id, name, count);
        });
    });

    // Desconectar Open Finance
    const disconnectOpenFinance = async (id) => {
        const ok = await Swal.fire({
            title: 'Desconectar Open Finance?',
            text: 'Deseja desconectar sua conta bancária e limpar a sincronização automática?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: '<i class="bi bi-plug me-1"></i> Sim, desconectar',
            cancelButtonText: 'Cancelar',
            focusCancel: true
        });

        if (!ok.isConfirmed) return;

        try {
            const r = await fetch(`${base}/open-finance/${id}/excluir`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'X-HTTP-METHOD-OVERRIDE': 'DELETE',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({_method: 'DELETE'})
            });
            const d = await r.json();
            if (d.ok || r.ok) {
                toast(d.message || 'Conexão Open Finance removida com sucesso!');
                setTimeout(() => location.reload(), 1200);
            } else {
                toast(d.message || 'Erro ao desconectar.', 'error');
            }
        } catch(e) {
            toast('Erro de rede ao desconectar.', 'error');
        }
    };

    const btnManageOf = document.getElementById('btn-manage-open-finance');
    if (btnManageOf) {
        btnManageOf.addEventListener('click', () => disconnectOpenFinance(btnManageOf.dataset.id));
    }
    document.querySelectorAll('.btn-disconnect-of').forEach(btn => {
        btn.addEventListener('click', () => disconnectOpenFinance(btn.dataset.id));
    });

    // Helper para carregar o SDK da Pluggy sob demanda
    const ensurePluggyLoaded = () => {
        return new Promise((resolve, reject) => {
            if (typeof window.PluggyConnect === 'function') {
                return resolve(window.PluggyConnect);
            }

            // Polling rápido de 2000ms caso o script já esteja em download
            let checkCount = 0;
            const timer = setInterval(() => {
                checkCount++;
                if (typeof window.PluggyConnect === 'function') {
                    clearInterval(timer);
                    return resolve(window.PluggyConnect);
                }
                if (checkCount >= 20) { // 2 segundos
                    clearInterval(timer);
                    injectScript();
                }
            }, 100);

            function injectScript() {
                if (typeof window.PluggyConnect === 'function') return resolve(window.PluggyConnect);
                
                // Remove scripts anteriores travados ou 404
                document.querySelectorAll('script[src*="pluggy-connect"]').forEach(el => el.remove());

                const s = document.createElement('script');
                s.src = 'https://cdn.pluggy.ai/pluggy-connect/latest/pluggy-connect.js';
                s.async = true;
                s.onload = () => {
                    if (typeof window.PluggyConnect === 'function') {
                        resolve(window.PluggyConnect);
                    } else {
                        reject(new Error('Widget carregado, mas objeto PluggyConnect não foi encontrado.'));
                    }
                };
                s.onerror = () => {
                    // Fallback para versão estática v2.11.0 se latest falhar
                    const fb = document.createElement('script');
                    fb.src = 'https://cdn.pluggy.ai/pluggy-connect/v2.11.0/pluggy-connect.js';
                    fb.async = true;
                    fb.onload = () => {
                        if (typeof window.PluggyConnect === 'function') resolve(window.PluggyConnect);
                        else reject(new Error('Widget fallback carregado sem objeto PluggyConnect.'));
                    };
                    fb.onerror = () => reject(new Error('Falha ao carregar CDN do Pluggy Connect. Verifique conexões de rede ou bloqueadores.'));
                    document.head.appendChild(fb);
                };
                document.head.appendChild(s);
            }
        });
    };

    // Conectar Open Finance (Pluggy Widget)
    const btnOpenFinance = document.getElementById('btn-open-finance');
    if (btnOpenFinance) {
        btnOpenFinance.addEventListener('click', async () => {
            const originalHtml = btnOpenFinance.innerHTML;
            btnOpenFinance.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Conectando...';
            btnOpenFinance.disabled = true;

            try {
                // 1. Garante que o SDK oficial do Pluggy está carregado
                await ensurePluggyLoaded();

                // 2. Solicita o Connect Token gerado pelo backend
                const r = await fetch(`${base}/open-finance/connect-token`, {
                    headers: {'Accept': 'application/json'}
                });
                const d = await r.json();

                if (!d.ok || !d.accessToken) {
                    btnOpenFinance.innerHTML = originalHtml;
                    btnOpenFinance.disabled = false;
                    if (d.not_configured) {
                        if (d.can_manage) {
                            Swal.fire({
                                icon: 'info',
                                title: 'Open Finance Não Configurado',
                                text: d.message,
                                confirmButtonText: '<i class="bi bi-gear me-1"></i> Configurar Agora',
                                showCancelButton: true,
                                cancelButtonText: 'Fechar'
                            }).then(res => {
                                if (res.isConfirmed) {
                                    const modalHelpEl = document.getElementById('modalOpenFinanceHelp');
                                    if (modalHelpEl) bootstrap.Modal.getOrCreateInstance(modalHelpEl).show();
                                }
                            });
                        } else {
                            Swal.fire({
                                icon: 'info',
                                title: 'Open Finance Indisponível',
                                text: 'A integração de Open Finance ainda não foi ativada na plataforma pelo suporte do sistema.',
                                confirmButtonText: 'Entendi'
                            });
                        }
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro de Conexão',
                            text: d.message || 'Erro ao inicializar conexão Open Finance com o banco.',
                        });
                    }
                    return;
                }

                // 3. Inicializa o Widget oficial do Pluggy Connect
                const pluggyConnect = new window.PluggyConnect({
                    connectToken: d.accessToken,
                    includeSandbox: true,
                    onSuccess: async (itemData) => {
                        toast('Banco conectado! Vinculando ao sistema...', 'info');
                        try {
                            const rb = await fetch(`${base}/open-finance/callback`, {
                                method: 'POST',
                                headers: {'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json'},
                                body: JSON.stringify({ item_id: itemData.item.id })
                            });
                            const db = await rb.json();
                            if (db.ok) {
                                await Swal.fire({
                                    icon: 'success',
                                    title: 'Conta Conectada!',
                                    text: 'Sua conta bancária foi integrada com sucesso. As movimentações serão sincronizadas automaticamente.',
                                    confirmButtonText: 'Ótimo'
                                });
                                location.reload();
                            } else {
                                toast(db.message || 'Erro ao vincular conta', 'error');
                            }
                        } catch (e) {
                            toast('Erro ao contatar backend para vínculo', 'error');
                        }
                    },
                    onError: (error) => {
                        console.error('Pluggy Error:', error);
                        toast('Operação de conexão cancelada ou finalizada com erro.', 'warning');
                    },
                    onClose: () => {
                        btnOpenFinance.innerHTML = originalHtml;
                        btnOpenFinance.disabled = false;
                    }
                });

                pluggyConnect.init();

            } catch(err) {
                console.error('Erro detalhado ao inicializar Pluggy Connect:', err);
                Swal.fire({
                    icon: 'error',
                    title: 'Falha ao Abrir Conexão',
                    text: err.message || 'Ocorreu uma falha ao carregar a janela segura da Pluggy. Verifique sua conexão e tente novamente.',
                });
                btnOpenFinance.innerHTML = originalHtml;
                btnOpenFinance.disabled = false;
            }
        });
    }

    // Carregar configurações do Open Finance no modal (Apenas para SuperAdmin do SaaS)
    const modalHelp = document.getElementById('modalOpenFinanceHelp');
    if (modalHelp) {
        modalHelp.addEventListener('show.bs.modal', async () => {
            try {
                const r = await fetch(`${base}/open-finance/config`, {
                    headers: {'Accept': 'application/json'}
                });
                const d = await r.json();
                if (d.ok && d.can_manage) {
                    const statusEl = document.getElementById('of-config-status');
                    if (statusEl) {
                        statusEl.innerHTML = d.is_configured
                            ? '<span class="text-success fw-semibold"><i class="bi bi-check-circle-fill me-1"></i>Credenciais configuradas e ativas no SaaS</span>'
                            : '<span class="text-warning fw-semibold"><i class="bi bi-exclamation-triangle-fill me-1"></i>Credenciais pendentes de configuração</span>';
                    }
                    const cid = document.getElementById('of-client-id');
                    if (cid) {
                        if (d.client_id) cid.value = d.client_id;
                        else if (d.client_id_masked) cid.placeholder = `Atualmente: ${d.client_id_masked}`;
                    }
                    const csec = document.getElementById('of-client-secret');
                    if (csec && d.client_secret) {
                        csec.value = d.client_secret;
                    }
                }
            } catch(e) {}
        });
    }

    // Salvar configurações administrativas do Open Finance
    const frmOfConfig = document.getElementById('frm-of-admin-config');
    if (frmOfConfig) {
        frmOfConfig.addEventListener('submit', async e => {
            e.preventDefault();
            const btn = document.getElementById('btn-save-of-config');
            const spinner = document.getElementById('spinner-of-save');
            const icon = document.getElementById('icon-of-save');

            btn.disabled = true;
            spinner.classList.remove('d-none');
            icon.classList.add('d-none');

            const fd = new FormData(frmOfConfig);
            const data = Object.fromEntries(fd.entries());

            try {
                const r = await fetch(`${base}/open-finance/config`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(data)
                });
                const d = await r.json();
                if (d.ok || r.ok) {
                    await Swal.fire({
                        icon: 'success',
                        title: 'Configurações Salvas!',
                        text: d.message || 'Open Finance configurado com sucesso no SaaS!',
                        confirmButtonText: 'Ótimo!'
                    });
                    bootstrap.Modal.getInstance(modalHelp)?.hide();
                    location.reload();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Falha ao Salvar',
                        text: d.message || 'Verifique os dados e tente novamente.'
                    });
                }
            } catch(e) {
                toast('Erro de rede ao salvar configurações.', 'error');
            }

            btn.disabled = false;
            spinner.classList.add('d-none');
            icon.classList.remove('d-none');
        });
    }
})();
</script>
@endsection
