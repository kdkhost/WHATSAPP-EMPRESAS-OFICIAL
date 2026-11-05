@extends('admin.layouts.app')
@section('title', 'Gateway - SumUp')
@section('content')
<div class="container-fluid">

    {{-- ========== HEADER ========== --}}
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h3 class="mb-1"><i class="bi bi-credit-card-2-front me-2 text-primary"></i>SumUp</h3>
            <p class="text-muted mb-0 small">Pagamentos por cartao e link de cobranca com a SumUp. Configure OAuth2, API Key e merchant code para liberar checkout transparente.</p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <span class="badge bg-{{ ($config['enabled'] ?? false) ? 'success' : 'secondary' }} fs-6 py-2 px-3">
                <i class="bi bi-{{ ($config['enabled'] ?? false) ? 'check-circle-fill' : 'pause-circle-fill' }} me-1"></i>
                {{ ($config['enabled'] ?? false) ? 'ATIVO' : 'INATIVO' }}
            </span>
            @if($config['sandbox'] ?? false)
                <span class="badge bg-warning text-dark fs-6 py-2 px-3"><i class="bi bi-flask me-1"></i> SANDBOX</span>
            @else
                <span class="badge bg-info text-dark fs-6 py-2 px-3"><i class="bi bi-globe me-1"></i> PRODUCAO</span>
            @endif
        </div>
    </div>

    {{-- ========== KPI ROW ========== --}}
    <div class="row g-3 mb-4">
        <div class="col-md-6 col-lg-3">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-success-subtle text-success"><i class="bi bi-cash-coin"></i></div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="text-uppercase small text-muted fw-semibold">Receita SumUp</div>
                        <div class="fs-4 fw-bold text-truncate" id="kpi-revenue">R$ 0,00</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-receipt"></i></div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="text-uppercase small text-muted fw-semibold">Cobrancas Pagas</div>
                        <div class="fs-4 fw-bold" id="kpi-paid">0</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-warning-subtle text-warning"><i class="bi bi-hourglass-split"></i></div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="text-uppercase small text-muted fw-semibold">Pendentes</div>
                        <div class="fs-4 fw-bold" id="kpi-pending">0</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-info-subtle text-info"><i class="bi bi-graph-up-arrow"></i></div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="text-uppercase small text-muted fw-semibold">Ticket Medio</div>
                        <div class="fs-4 fw-bold text-truncate" id="kpi-avg">R$ 0,00</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <form id="frm-sumup" class="row g-4">
        @csrf

        {{-- ========== COLUNA ESQUERDA ========== --}}
        <div class="col-lg-8">

            {{-- Credenciais --}}
            <div class="card shadow-sm">
                <div class="card-header">
                    <strong><i class="bi bi-key me-1 text-primary"></i> Credenciais OAuth2</strong>
                    <a href="https://developer.sumup.com/dashboard" target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-box-arrow-up-right me-1"></i> Developer Dashboard
                    </a>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Client ID</label>
                            <input name="client_id" class="form-control" value="{{ $config['client_id'] ?? '' }}"
                                   placeholder="cc_classic_xxxxxxxxxxxx">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Client Secret</label>
                            <div class="input-group">
                                <input name="client_secret" type="password" class="form-control" id="sumup-secret"
                                       value="{{ $config['client_secret'] ?? '' }}" placeholder="cc_sk_classic_xxxxxxxxxx">
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePw('sumup-secret')" title="Mostrar/ocultar"><i class="bi bi-eye"></i></button>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">API Key (acesso direto)</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-shield-lock"></i></span>
                                <input name="api_key" type="password" class="form-control" id="sumup-apikey"
                                       value="{{ $config['api_key'] ?? '' }}" placeholder="sup_sk_xxxxxxxxxxxxxxxx">
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePw('sumup-apikey')" title="Mostrar/ocultar"><i class="bi bi-eye"></i></button>
                            </div>
                            <small class="text-muted">Use a API Key direta para integracao server-side. OAuth2 e necessario para acoes em nome do comerciante.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Merchant Code</label>
                            <input name="merchant_code" class="form-control" value="{{ $config['merchant_code'] ?? '' }}"
                                   placeholder="MXXXXXXX">
                            <small class="text-muted">Codigo do comerciante SumUp (formato MXXXXXXX).</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Redirect URI (OAuth)</label>
                            <input name="redirect_uri" class="form-control" value="{{ $config['redirect_uri'] ?? url('/admin/gateways/sumup/callback') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Webhook Secret</label>
                            <input name="webhook_secret" class="form-control" value="{{ $config['webhook_secret'] ?? '' }}">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Configuracoes operacionais --}}
            <div class="card shadow-sm mt-3">
                <div class="card-header">
                    <strong><i class="bi bi-gear me-1 text-secondary"></i> Configuracoes</strong>
                    <span class="text-muted small">Operacao</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Modo</label>
                            <select name="sandbox" class="form-select">
                                <option value="0" @selected(!($config['sandbox'] ?? false))>Producao</option>
                                <option value="1" @selected($config['sandbox'] ?? false)>Sandbox</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select name="enabled" class="form-select">
                                <option value="1" @selected($config['enabled'] ?? false)>Ativo</option>
                                <option value="0" @selected(!($config['enabled'] ?? false))>Inativo</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Moeda</label>
                            <select name="currency" class="form-select">
                                <option value="BRL" @selected(($config['currency'] ?? 'BRL') === 'BRL')>BRL (Real)</option>
                                <option value="EUR" @selected(($config['currency'] ?? '') === 'EUR')>EUR (Euro)</option>
                                <option value="USD" @selected(($config['currency'] ?? '') === 'USD')>USD (Dolar)</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Webhook URL (gerada automaticamente)</label>
                            <div class="input-group">
                                <input type="text" class="form-control" readonly
                                       value="{{ url('/webhooks/sumup/{token}') }}" id="sumup-webhook-url">
                                <button type="button" class="btn btn-outline-secondary" onclick="copyUrl('sumup-webhook-url')" title="Copiar"><i class="bi bi-clipboard"></i></button>
                            </div>
                            <small class="text-muted">Cada cobranca recebe um <code>{token}</code> proprio para confirmar o pagamento.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Descricao padrao</label>
                            <input name="default_description" class="form-control"
                                   value="{{ $config['default_description'] ?? 'Pagamento' }}" placeholder="Pagamento">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Validade do link (minutos)</label>
                            <input name="link_expiration" type="number" class="form-control" min="5" max="1440"
                                   value="{{ $config['link_expiration'] ?? 60 }}">
                            <small class="text-muted">Min 5, max 1440 (24h).</small>
                        </div>
                        <div class="col-md-6">
                            <input type="hidden" name="hosted_checkout_enabled" value="0">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="hosted_checkout_enabled" value="1" id="sumup-hosted"
                                       @checked($config['hosted_checkout_enabled'] ?? true)>
                                <label class="form-check-label" for="sumup-hosted">Hosted checkout</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <input type="hidden" name="credit_card_enabled" value="0">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="credit_card_enabled" value="1" id="sumup-card"
                                       @checked($config['credit_card_enabled'] ?? true)>
                                <label class="form-check-label" for="sumup-card">Cartao habilitado</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer text-end">
                    <button type="button" class="btn btn-outline-secondary btn-sm me-2" id="btn-test-sumup">
                        <i class="bi bi-plug me-1"></i> Testar Credenciais
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm me-2" id="btn-clear-sumup">
                        <i class="bi bi-trash me-1"></i> Limpar
                    </button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-check2-circle me-1"></i> Salvar
                    </button>
                </div>
            </div>
        </div>

        {{-- ========== COLUNA DIREITA ========== --}}
        <div class="col-lg-4">

            {{-- Recursos + taxas --}}
            <div class="card shadow-sm">
                <div class="card-header">
                    <strong><i class="bi bi-stars me-1 text-warning"></i> Recursos disponiveis</strong>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled small mb-3">
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Link de pagamento</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Cartao de credito e debito</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Checkout online (1x a 12x)</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Webhook de confirmacao</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Maquininha integrada</li>
                        <li class="mb-2"><i class="bi bi-clock-fill text-warning me-2"></i> PIX (em breve)</li>
                    </ul>
                    <hr>
                    <div class="row g-2 small">
                        <div class="col-6">
                            <div class="text-muted text-uppercase fw-semibold" style="font-size: .7rem;">Taxa Cartao</div>
                            <div class="fw-bold">1,65% a 4,99%</div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted text-uppercase fw-semibold" style="font-size: .7rem;">Mensalidade</div>
                            <div class="fw-bold text-success">Gratis</div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted text-uppercase fw-semibold" style="font-size: .7rem;">Liquidacao</div>
                            <div class="fw-bold">D+1 / D+30</div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted text-uppercase fw-semibold" style="font-size: .7rem;">Antecipacao</div>
                            <div class="fw-bold">Disponivel</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Autorizacao OAuth --}}
            <div class="card shadow-sm mt-3">
                <div class="card-header">
                    <strong><i class="bi bi-link-45deg me-1 text-info"></i> Autorizacao OAuth</strong>
                </div>
                <div class="card-body text-center">
                    <p class="small text-muted mb-3">Apos preencher Client ID e Redirect URI, autorize o app SumUp para gerar tokens de acesso em nome do comerciante.</p>
                    <a href="#" class="btn btn-outline-primary btn-sm" id="btn-oauth-sumup">
                        <i class="bi bi-box-arrow-up-right me-1"></i> Autorizar SumUp
                    </a>
                </div>
            </div>

            {{-- Ultimos pagamentos --}}
            <div class="card shadow-sm mt-3">
                <div class="card-header">
                    <strong><i class="bi bi-clock-history me-1 text-info"></i> Ultimos pagamentos</strong>
                    <a href="{{ url('/admin/invoices?gateway=sumup') }}" class="btn btn-sm btn-outline-secondary">Ver todos</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm m-0">
                            <tbody id="sumup-recent">
                                <tr><td class="text-center text-muted py-3"><i class="bi bi-arrow-repeat"></i> Carregando...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
const csrf = document.querySelector('meta[name=csrf-token]').content;

function togglePw(id) {
    const el = document.getElementById(id);
    el.type = el.type === 'password' ? 'text' : 'password';
}

function copyUrl(id) {
    navigator.clipboard.writeText(document.getElementById(id).value);
    window.toast.success('URL copiada!');
}

const fmtMoney = v => 'R$ ' + parseFloat(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

// Salvar
document.getElementById('frm-sumup').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData(e.target);
    try {
        const r = await fetch('{{ url("/admin/gateways/sumup") }}', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: fd,
        });
        const data = await r.json().catch(() => ({}));
        if (r.ok) {
            window.toast.success('Configuracoes da SumUp salvas!');
            setTimeout(() => location.reload(), 800);
        } else {
            window.toast.error(data.message || 'Erro ao salvar.');
        }
    } catch (err) {
        window.toast.error('Erro de rede.');
    }
});

// Testar credenciais
document.getElementById('btn-test-sumup').addEventListener('click', async () => {
    window.toast.info('Testando credenciais...');
    try {
        const r = await fetch('{{ url("/admin/gateways/sumup/test") }}', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        });
        const data = await r.json().catch(() => ({}));
        if (r.ok && data.ok) window.toast.success('Credenciais validas! Merchant: ' + (data.merchant || ''));
        else window.toast.error(data.message || 'Falha na autenticacao.');
    } catch (err) {
        window.toast.error('Erro de rede.');
    }
});

document.getElementById('btn-clear-sumup').addEventListener('click', async () => {
    const confirmed = await Swal.fire({
        title: 'Limpar configuracao da SumUp?',
        text: 'As credenciais e parametros salvos serao removidos.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Limpar',
        cancelButtonText: 'Cancelar',
    });

    if (!confirmed.isConfirmed) return;

    const r = await fetch('{{ route("admin.gateways.sumup.delete") }}', {
        method: 'DELETE', credentials: 'same-origin',
        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
    });
    const data = await r.json().catch(() => ({}));
    if (r.ok && data.ok) {
        window.toast.success('Configuracao removida.');
        setTimeout(() => location.reload(), 500);
        return;
    }

    window.toast.error(data.message || 'Nao foi possivel limpar a configuracao.');
});

// OAuth link
document.getElementById('btn-oauth-sumup').addEventListener('click', e => {
    e.preventDefault();
    const clientId = document.querySelector('[name=client_id]').value;
    const redirectUri = document.querySelector('[name=redirect_uri]').value;
    if (!clientId) { window.toast.error('Preencha o Client ID primeiro.'); return; }
    const url = `https://api.sumup.com/authorize?response_type=code&client_id=${encodeURIComponent(clientId)}&redirect_uri=${encodeURIComponent(redirectUri)}&scope=payments`;
    window.open(url, '_blank');
});

// KPIs + ultimos pagamentos
async function loadSumupData() {
    try {
        const r = await fetch('{{ url("/admin/gateways/sumup/recent") }}', {
            credentials: 'same-origin', headers: { 'Accept': 'application/json' },
        });
        const data = await r.json().catch(() => ({ payments: [], stats: {} }));

        const s = data.stats || {};
        document.getElementById('kpi-revenue').textContent = fmtMoney(s.revenue);
        document.getElementById('kpi-paid').textContent = (s.paid_count || 0).toLocaleString('pt-BR');
        document.getElementById('kpi-pending').textContent = (s.pending_count || 0).toLocaleString('pt-BR');
        document.getElementById('kpi-avg').textContent = fmtMoney(s.avg_ticket);

        const tbody = document.getElementById('sumup-recent');
        if (!data.payments || data.payments.length === 0) {
            tbody.innerHTML = '<tr><td class="text-center text-muted py-3">Nenhum pagamento registrado.</td></tr>';
            return;
        }
        const badge = st => {
            const map = { paid: 'success', pending: 'warning', overdue: 'danger', canceled: 'secondary', sent: 'info', viewed: 'info' };
            return `<span class="badge text-bg-${map[st] || 'secondary'}">${st}</span>`;
        };
        tbody.innerHTML = data.payments.map(p => `
            <tr>
                <td class="small fw-semibold">${p.number || '#' + p.id}</td>
                <td class="small text-end">${fmtMoney(p.amount)}</td>
                <td class="text-end">${badge(p.status)}</td>
            </tr>
        `).join('');
    } catch (err) {
        document.getElementById('sumup-recent').innerHTML = '<tr><td class="text-center text-danger py-3">Erro ao carregar.</td></tr>';
    }
}
loadSumupData();
</script>
@endpush
