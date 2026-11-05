@extends('admin.layouts.app')
@section('title', 'Gateway - Mercado Pago')
@section('content')
<div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="mb-1"><i class="bi bi-credit-card me-2"></i>Mercado Pago</h3>
            <p class="text-muted mb-0 small">Configure credenciais, modo (produção/sandbox) e webhook do Mercado Pago.</p>
        </div>
        <span class="badge bg-{{ ($config['enabled'] ?? false) ? 'success' : 'secondary' }} fs-6">
            <i class="bi bi-{{ ($config['enabled'] ?? false) ? 'check-circle' : 'x-circle' }} me-1"></i>
            {{ ($config['enabled'] ?? false) ? 'ATIVO' : 'INATIVO' }}
        </span>
    </div>

    <form id="frm-mercadopago" class="row g-4">
        @csrf
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><strong><i class="bi bi-key me-1"></i> Credenciais</strong></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Access Token</label>
                            <div class="input-group">
                                <input name="access_token" type="password" class="form-control" id="mp-token"
                                       value="{{ $config['access_token'] ?? '' }}" placeholder="APP_USR-...">
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePw('mp-token')"><i class="bi bi-eye"></i></button>
                            </div>
                            <small class="text-muted">Token de producao ou sandbox. Encontre em: <a href="https://www.mercadopago.com.br/developers/panel/app" target="_blank">Painel do Desenvolvedor</a></small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Public Key</label>
                            <input name="public_key" class="form-control" value="{{ $config['public_key'] ?? '' }}" placeholder="APP_USR-...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Client ID</label>
                            <input name="client_id" class="form-control" value="{{ $config['client_id'] ?? '' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Client Secret</label>
                            <input name="client_secret" type="password" class="form-control" value="{{ $config['client_secret'] ?? '' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Webhook Secret</label>
                            <input name="webhook_secret" class="form-control" value="{{ $config['webhook_secret'] ?? '' }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header"><strong><i class="bi bi-gear me-1"></i> Configuracoes</strong></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Modo</label>
                            <select name="sandbox" class="form-select">
                                <option value="0" @selected(!($config['sandbox'] ?? false))>Producao</option>
                                <option value="1" @selected($config['sandbox'] ?? false)>Sandbox (Testes)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="enabled" class="form-select">
                                <option value="1" @selected($config['enabled'] ?? false)>Ativo</option>
                                <option value="0" @selected(!($config['enabled'] ?? false))>Inativo</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Webhook URL (automatica)</label>
                            <div class="input-group">
                                <input type="text" class="form-control bg-light" readonly
                                       value="{{ url('/webhooks/mercadopago/{token}') }}" id="mp-webhook-url">
                                <button type="button" class="btn btn-outline-secondary" onclick="copyWebhook()"><i class="bi bi-clipboard"></i></button>
                            </div>
                            <small class="text-muted">Cada fatura gera um token unico. Nao precisa configurar no painel do MP.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Dias para expirar pagamento</label>
                            <input name="expiration_days" type="number" class="form-control" min="1" max="30"
                                   value="{{ $config['expiration_days'] ?? 3 }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Descricao padrao</label>
                            <input name="default_description" class="form-control"
                                   value="{{ $config['default_description'] ?? 'Cobranca' }}" placeholder="Cobranca">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Parcelas maximas</label>
                            <input name="installments_max" type="number" class="form-control" min="1" max="24"
                                   value="{{ $config['installments_max'] ?? 12 }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Soft descriptor</label>
                            <input name="soft_descriptor" class="form-control"
                                   value="{{ $config['soft_descriptor'] ?? '' }}" maxlength="22" placeholder="SUAEMPRESA">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">PIX</label>
                            <select name="pix_enabled" class="form-select">
                                <option value="1" @selected($config['pix_enabled'] ?? true)>Ativo</option>
                                <option value="0" @selected(isset($config['pix_enabled']) && ! $config['pix_enabled'])>Inativo</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Cartao de credito</label>
                            <select name="credit_card_enabled" class="form-select">
                                <option value="1" @selected($config['credit_card_enabled'] ?? true)>Ativo</option>
                                <option value="0" @selected(isset($config['credit_card_enabled']) && ! $config['credit_card_enabled'])>Inativo</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Boleto</label>
                            <select name="boleto_enabled" class="form-select">
                                <option value="1" @selected($config['boleto_enabled'] ?? true)>Ativo</option>
                                <option value="0" @selected(isset($config['boleto_enabled']) && ! $config['boleto_enabled'])>Inativo</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header"><strong><i class="bi bi-calendar-x me-1"></i> Multas e Juros por Atraso</strong></div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        <i class="bi bi-info-circle me-1"></i>
                        Aplicados automaticamente quando a fatura não tiver multa/juros registrados e estiver vencida.
                        Deixe em <strong>0</strong> para não cobrar acréscimos.
                    </p>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Multa por atraso (%)</label>
                            <div class="input-group">
                                <input name="late_fee_pct" type="number" class="form-control" min="0" max="20" step="0.01"
                                       value="{{ $config['late_fee_pct'] ?? 0 }}" placeholder="Ex: 2">
                                <span class="input-group-text">%</span>
                            </div>
                            <small class="text-muted">Cobrada uma única vez (ex: 2% = multa padrão)</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Juros ao dia (%)</label>
                            <div class="input-group">
                                <input name="late_interest_pct_day" type="number" class="form-control" min="0" max="5" step="0.001"
                                       value="{{ $config['late_interest_pct_day'] ?? 0 }}" placeholder="Ex: 0.033">
                                <span class="input-group-text">%/dia</span>
                            </div>
                            <small class="text-muted">0,033% ao dia = 1% ao mês</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Carência (dias)</label>
                            <div class="input-group">
                                <input name="late_fee_after_days" type="number" class="form-control" min="0" max="60"
                                       value="{{ $config['late_fee_after_days'] ?? 1 }}" placeholder="1">
                                <span class="input-group-text">dias</span>
                            </div>
                            <small class="text-muted">Cobra multa/juros somente após N dias de atraso</small>
                        </div>
                    </div>
                    <div class="alert alert-info mt-3 small py-2 mb-0">
                        <i class="bi bi-calculator me-1"></i>
                        <strong>Exemplo:</strong> Fatura de R$ 100, 10 dias de atraso, multa 2%, juros 0,033%/dia:
                        multa = R$ 2,00 + juros = R$ 0,33 → <strong>Total PIX = R$ 102,33</strong>
                    </div>
                </div>
                <div class="card-footer text-end">
                    <button type="button" class="btn btn-outline-secondary btn-sm me-2" id="btn-test-mp">
                        <i class="bi bi-plug me-1"></i> Testar Credenciais
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm me-2" id="btn-clear-mp">
                        <i class="bi bi-trash me-1"></i> Limpar
                    </button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-check2-circle me-1"></i> Salvar
                    </button>
                </div>
            </div>
        </div>


        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><strong><i class="bi bi-info-circle me-1"></i> Informacoes</strong></div>
                <div class="card-body">
                    <ul class="list-unstyled small">
                        <li class="mb-2"><i class="bi bi-check-circle text-success me-1"></i> PIX (instantaneo)</li>
                        <li class="mb-2"><i class="bi bi-check-circle text-success me-1"></i> Cartao de credito</li>
                        <li class="mb-2"><i class="bi bi-check-circle text-success me-1"></i> Boleto bancario</li>
                        <li class="mb-2"><i class="bi bi-check-circle text-success me-1"></i> Checkout transparente</li>
                        <li class="mb-2"><i class="bi bi-check-circle text-success me-1"></i> Webhook automatico por fatura</li>
                    </ul>
                    <hr>
                    <p class="small text-muted mb-0">
                        <strong>Taxa:</strong> 4,98% cartao | 0,99% PIX | 3,49% boleto<br>
                        <strong>Prazo:</strong> D+1 (PIX) | D+14 (cartao) | D+2 (boleto)
                    </p>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header"><strong><i class="bi bi-graph-up me-1"></i> Ultimos pagamentos</strong></div>
                <div class="card-body p-0">
                    <table class="table table-sm m-0">
                        <tbody id="mp-recent">
                            <tr><td class="text-center text-muted p-3">Carregando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
@push('scripts')
<script>
const csrf = document.querySelector('meta[name=csrf-token]').content;
const fmtMoney = value => window.adminUI?.formatMoney
    ? window.adminUI.formatMoney(value)
    : Number.parseFloat(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

function togglePw(id) {
    const el = document.getElementById(id);
    el.type = el.type === 'password' ? 'text' : 'password';
}

function copyWebhook() {
    navigator.clipboard.writeText(document.getElementById('mp-webhook-url').value);
    window.toast.success('URL copiada!');
}

// Salvar
document.getElementById('frm-mercadopago').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const r = await fetch('{{ url("/admin/gateways/mercadopago") }}', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        body: fd,
    });
    if (r.ok) window.toast.success('Configuracoes do Mercado Pago salvas!');
    else {
        const err = await r.json().catch(() => ({}));
        window.toast.error(err.message || 'Erro ao salvar.');
    }
});

// Testar credenciais
document.getElementById('btn-test-mp').addEventListener('click', async () => {
    window.toast.info('Testando credenciais...');
    const r = await fetch('{{ url("/admin/gateways/mercadopago/test") }}', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
    });
    const data = await r.json().catch(() => ({}));
    if (r.ok && data.ok) window.toast.success('Credenciais validas! Conta: ' + (data.email || ''));
    else window.toast.error(data.message || 'Credenciais invalidas.');
});

document.getElementById('btn-clear-mp').addEventListener('click', async () => {
    const confirmed = await Swal.fire({
        title: 'Limpar configuracao do gateway?',
        text: 'As credenciais salvas do Mercado Pago serao removidas.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Limpar',
        cancelButtonText: 'Cancelar',
    });

    if (!confirmed.isConfirmed) return;

    const r = await fetch('{{ route("admin.gateways.mercadopago.delete") }}', {
        method: 'DELETE',
        credentials: 'same-origin',
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

// Carregar pagamentos recentes
(async () => {
    const r = await fetch('{{ url("/admin/gateways/mercadopago/recent") }}', {
        credentials: 'same-origin', headers: { 'Accept': 'application/json' },
    });
    const data = await r.json().catch(() => ({ payments: [] }));
    const tbody = document.getElementById('mp-recent');
    if (!data.payments || data.payments.length === 0) {
        tbody.innerHTML = '<tr><td class="text-center text-muted p-3">Nenhum pagamento recente.</td></tr>';
        return;
    }
    tbody.innerHTML = data.payments.map(p => `
        <tr>
            <td class="small">${p.number || '#' + p.id}</td>
            <td class="small">${fmtMoney(p.amount)}</td>
            <td><span class="badge text-bg-${p.status === 'paid' ? 'success' : 'warning'}">${p.status}</span></td>
        </tr>
    `).join('');
})();
</script>
@endpush
