@extends('admin.layouts.app')
@section('title', 'Gateway - Efi Pro')
@section('content')
<div class="container-fluid">

    {{-- ========== HEADER ========== --}}
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h3 class="mb-1"><i class="bi bi-bank me-2 text-primary"></i>Efi Pro</h3>
            <p class="text-muted mb-0 small">PIX, Boleto e Cartao com a antiga Gerencianet. Configure credenciais OAuth2, certificado PIX e chave de recebimento.</p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <span class="badge bg-{{ ($config['enabled'] ?? false) ? 'success' : 'secondary' }} fs-6 py-2 px-3">
                <i class="bi bi-{{ ($config['enabled'] ?? false) ? 'check-circle-fill' : 'pause-circle-fill' }} me-1"></i>
                {{ ($config['enabled'] ?? false) ? 'ATIVO' : 'INATIVO' }}
            </span>
            @if($config['sandbox'] ?? false)
                <span class="badge bg-warning text-dark fs-6 py-2 px-3"><i class="bi bi-flask me-1"></i> HOMOLOG</span>
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
                        <div class="text-uppercase small text-muted fw-semibold">Receita Efi</div>
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

    <form id="frm-efipro" class="row g-4">
        @csrf

        {{-- ========== COLUNA ESQUERDA ========== --}}
        <div class="col-lg-8">

            {{-- Credenciais OAuth2 --}}
            <div class="card shadow-sm">
                <div class="card-header">
                    <strong><i class="bi bi-key me-1 text-primary"></i> Credenciais OAuth2</strong>
                    <a href="https://dev.efipay.com.br/" target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-box-arrow-up-right me-1"></i> Painel Efi
                    </a>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Client ID</label>
                            <input name="client_id" class="form-control" value="{{ $config['client_id'] ?? '' }}"
                                   placeholder="Client_Id_xxxxxxxxxxxxxxxxxx">
                            <small class="text-muted">Gerado em: <em>API > Aplicacoes</em> no painel Efi.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Client Secret</label>
                            <div class="input-group">
                                <input name="client_secret" type="password" class="form-control" id="efi-secret"
                                       value="{{ $config['client_secret'] ?? '' }}" placeholder="Client_Secret_xxxxxxxx">
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePw('efi-secret')" title="Mostrar/ocultar"><i class="bi bi-eye"></i></button>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold"><i class="bi bi-shield-lock me-1"></i> Certificado PIX (.pem ou .p12)</label>
                            @php
                                $certPath = $config['certificate_path'] ?? '';
                                $certExists = $certPath && file_exists($certPath);
                                $certOriginalName = $config['certificate_original_name'] ?? ($certPath ? basename($certPath) : '');
                            @endphp
                            <div class="p-3 border rounded-3 bg-light-subtle">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                                    <div>
                                        @if($certExists)
                                            <span class="badge bg-success-subtle text-success fs-6 py-2 px-3">
                                                <i class="bi bi-patch-check-fill me-1"></i> Certificado Ativo: <strong>{{ $certOriginalName }}</strong>
                                            </span>
                                            <div class="small text-muted mt-1 font-monospace">{{ $certPath }}</div>
                                        @else
                                            <span class="badge bg-warning-subtle text-warning-emphasis fs-6 py-2 px-3">
                                                <i class="bi bi-exclamation-triangle-fill me-1"></i> Nenhum certificado configurado
                                            </span>
                                            <div class="small text-muted mt-1">Obrigatorio para emissao e conciliacao de PIX na Efi. Suporta .pem e .p12.</div>
                                        @endif
                                    </div>
                                    <div class="d-flex gap-2">
                                        <input type="file" id="efi-cert-file" class="d-none" accept=".pem,.p12,.crt,.cer">
                                        <button type="button" class="btn btn-outline-primary btn-sm" id="btn-trigger-upload-cert">
                                            <i class="bi bi-cloud-arrow-up me-1"></i> {{ $certExists ? 'Substituir Certificado' : 'Fazer Upload (.pem / .p12)' }}
                                        </button>
                                        @if($certExists)
                                            <button type="button" class="btn btn-outline-danger btn-sm" id="btn-delete-cert">
                                                <i class="bi bi-trash me-1"></i> Remover
                                            </button>
                                        @endif
                                    </div>
                                </div>
                                <div class="collapse mt-2" id="collapse-cert-path">
                                    <label class="form-label small text-muted">Caminho no servidor (armazenado automaticamente apos upload):</label>
                                    <input name="certificate_path" id="input-cert-path" class="form-control form-control-sm font-monospace"
                                           value="{{ $certPath }}" placeholder="/home/.../certs/efipro_admin.pem">
                                </div>
                                <div class="text-end">
                                    <a href="#collapse-cert-path" data-bs-toggle="collapse" class="small text-muted text-decoration-none">
                                        <i class="bi bi-sliders me-1"></i> Exibir caminho salvo
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Identificador da conta</label>
                            <input name="account_id" class="form-control" value="{{ $config['account_id'] ?? '' }}"
                                   placeholder="Necessario para checkout/cartao">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Chave PIX --}}
            <div class="card shadow-sm mt-3">
                <div class="card-header">
                    <strong><i class="bi bi-lightning-charge me-1 text-warning"></i> Chave PIX</strong>
                    <span class="text-muted small">Recebimento</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Chave PIX</label>
                            <input name="pix_key" class="form-control" value="{{ $config['pix_key'] ?? '' }}"
                                   placeholder="email@empresa.com, CNPJ, telefone ou chave aleatoria">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tipo da Chave</label>
                            <select name="pix_key_type" class="form-select">
                                <option value="email" @selected(($config['pix_key_type'] ?? '') === 'email')>E-mail</option>
                                <option value="cpf" @selected(($config['pix_key_type'] ?? '') === 'cpf')>CPF</option>
                                <option value="cnpj" @selected(($config['pix_key_type'] ?? '') === 'cnpj')>CNPJ</option>
                                <option value="phone" @selected(($config['pix_key_type'] ?? '') === 'phone')>Telefone</option>
                                <option value="random" @selected(($config['pix_key_type'] ?? '') === 'random')>Aleatoria (EVP)</option>
                            </select>
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
                                <option value="1" @selected($config['sandbox'] ?? false)>Homologacao</option>
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
                            <label class="form-label">Expiracao PIX (segundos)</label>
                            <input name="pix_expiration" type="number" class="form-control" min="300" max="86400"
                                   value="{{ $config['pix_expiration'] ?? 3600 }}">
                            <small class="text-muted">Min 300, max 86400 (24h).</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Webhook URL (gerada automaticamente)</label>
                            <div class="input-group">
                                <input type="text" class="form-control" readonly
                                       value="{{ url('/webhooks/efipro/{token}') }}" id="efi-webhook-url">
                                <button type="button" class="btn btn-outline-secondary" onclick="copyUrl('efi-webhook-url')" title="Copiar"><i class="bi bi-clipboard"></i></button>
                            </div>
                            <small class="text-muted">Cada cobranca recebe um <code>{token}</code> proprio. Configure no painel Efi se necessario.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Webhook Secret</label>
                            <input name="webhook_secret" class="form-control" value="{{ $config['webhook_secret'] ?? '' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Parcelas maximas (cartao)</label>
                            <input name="installments_max" type="number" class="form-control" min="1" max="24"
                                   value="{{ $config['installments_max'] ?? 1 }}">
                        </div>
                        <div class="col-md-6">
                            <input type="hidden" name="boleto_enabled" value="0">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="boleto_enabled" value="1" id="efi-boleto"
                                       @checked($config['boleto_enabled'] ?? false)>
                                <label class="form-check-label" for="efi-boleto">
                                    <i class="bi bi-upc-scan me-1"></i> Habilitar Boleto
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <input type="hidden" name="card_enabled" value="0">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="card_enabled" value="1" id="efi-card"
                                       @checked($config['card_enabled'] ?? false)>
                                <label class="form-check-label" for="efi-card">
                                    <i class="bi bi-credit-card-2-front me-1"></i> Habilitar Cartao
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer text-end">
                    <button type="button" class="btn btn-outline-secondary btn-sm me-2" id="btn-test-efi">
                        <i class="bi bi-plug me-1"></i> Testar Credenciais
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm me-2" id="btn-clear-efi">
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

            {{-- Recursos --}}
            <div class="card shadow-sm">
                <div class="card-header">
                    <strong><i class="bi bi-stars me-1 text-warning"></i> Recursos disponiveis</strong>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled small mb-3">
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> PIX com QR Code dinamico</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> PIX copia e cola</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Boleto registrado bancario</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Cartao de credito (1x a 12x)</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Webhook de confirmacao</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Conciliacao automatica</li>
                    </ul>
                    <hr>
                    <div class="row g-2 small">
                        <div class="col-6">
                            <div class="text-muted text-uppercase fw-semibold" style="font-size: .7rem;">Taxa PIX</div>
                            <div class="fw-bold">Gratis<small class="text-muted"> ate R$ 1.000/mes</small></div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted text-uppercase fw-semibold" style="font-size: .7rem;">Boleto</div>
                            <div class="fw-bold">R$ 2,50<small class="text-muted"> /titulo</small></div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted text-uppercase fw-semibold" style="font-size: .7rem;">Cartao</div>
                            <div class="fw-bold">2,49% a 4,99%</div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted text-uppercase fw-semibold" style="font-size: .7rem;">Liquidacao</div>
                            <div class="fw-bold">D+0 PIX | D+2 Bol</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Aviso certificado --}}
            <div class="card shadow-sm mt-3 border-warning border-2">
                <div class="card-body">
                    <h6 class="text-warning mb-2"><i class="bi bi-exclamation-triangle-fill me-1"></i> Importante</h6>
                    <p class="small mb-2">Para PIX funcionar, e obrigatorio:</p>
                    <ol class="small ps-3 mb-0">
                        <li>Gerar certificado no <strong>painel Efi</strong></li>
                        <li>Enviar <code>.pem</code> ao servidor</li>
                        <li>Definir o caminho absoluto acima</li>
                        <li>Cadastrar a chave PIX na Efi</li>
                    </ol>
                </div>
            </div>

            {{-- Ultimos pagamentos --}}
            <div class="card shadow-sm mt-3">
                <div class="card-header">
                    <strong><i class="bi bi-clock-history me-1 text-info"></i> Ultimos pagamentos</strong>
                    <a href="{{ url('/admin/invoices?gateway=efipro') }}" class="btn btn-sm btn-outline-secondary">Ver todos</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm m-0">
                            <tbody id="efi-recent">
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
document.getElementById('frm-efipro').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData(e.target);
    try {
        const r = await fetch('{{ url("/admin/gateways/efipro") }}', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: fd,
        });
        const data = await r.json().catch(() => ({}));
        if (r.ok) {
            window.toast.success('Configuracoes da Efi Pro salvas!');
            setTimeout(() => location.reload(), 800);
        } else {
            window.toast.error(data.message || 'Erro ao salvar.');
        }
    } catch (err) {
        window.toast.error('Erro de rede.');
    }
});

// Upload do Certificado
const btnTriggerUpload = document.getElementById('btn-trigger-upload-cert');
const fileInput = document.getElementById('efi-cert-file');
const btnDeleteCert = document.getElementById('btn-delete-cert');

if (btnTriggerUpload && fileInput) {
    btnTriggerUpload.addEventListener('click', () => fileInput.click());

    fileInput.addEventListener('change', async () => {
        if (!fileInput.files.length) return;
        const file = fileInput.files[0];
        const isP12 = file.name.toLowerCase().endsWith('.p12');
        let passphrase = '';

        if (isP12) {
            const { value: pass, isDismissed } = await Swal.fire({
                title: 'Senha do Certificado (.p12)',
                text: 'Se o certificado tiver senha de proteção, digite-a abaixo:',
                input: 'password',
                inputPlaceholder: 'Senha (deixe em branco se não houver)',
                showCancelButton: true,
                confirmButtonText: 'Continuar Envio',
                cancelButtonText: 'Cancelar',
            });
            if (isDismissed) {
                fileInput.value = '';
                return;
            }
            passphrase = pass || '';
        }

        window.toast.info('Enviando e validando certificado...');
        const fd = new FormData();
        fd.append('certificate', file);
        if (passphrase) fd.append('passphrase', passphrase);

        try {
            const r = await fetch('{{ route("admin.gateways.efipro.certificate") }}', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: fd,
            });
            const data = await r.json().catch(() => ({}));
            if (r.ok && data.ok) {
                await Swal.fire({
                    icon: 'success',
                    title: 'Certificado Configurado!',
                    text: data.message || 'Certificado salvo com sucesso.',
                });
                location.reload();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Falha no Upload',
                    text: data.message || data.error || 'Não foi possível processar o certificado.',
                });
            }
        } catch (err) {
            window.toast.error('Erro de conexão ao enviar o certificado.');
        } finally {
            fileInput.value = '';
        }
    });
}

if (btnDeleteCert) {
    btnDeleteCert.addEventListener('click', async () => {
        const confirmed = await Swal.fire({
            title: 'Remover Certificado Efi?',
            text: 'As transações PIX não funcionarão sem o certificado.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sim, remover',
            cancelButtonText: 'Cancelar',
        });
        if (!confirmed.isConfirmed) return;

        try {
            const r = await fetch('{{ route("admin.gateways.efipro.certificate.delete") }}', {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            });
            const data = await r.json().catch(() => ({}));
            if (r.ok && data.ok) {
                window.toast.success('Certificado removido.');
                setTimeout(() => location.reload(), 500);
            } else {
                window.toast.error(data.message || 'Erro ao remover certificado.');
            }
        } catch (err) {
            window.toast.error('Erro de conexão ao remover certificado.');
        }
    });
}

// Testar credenciais
document.getElementById('btn-test-efi').addEventListener('click', async () => {
    window.toast.info('Testando credenciais...');
    try {
        const r = await fetch('{{ url("/admin/gateways/efipro/test") }}', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        });
        const data = await r.json().catch(() => ({}));
        if (r.ok && data.ok) window.toast.success('Credenciais validas!');
        else window.toast.error(data.message || 'Falha na autenticacao.');
    } catch (err) {
        window.toast.error('Erro de rede.');
    }
});

document.getElementById('btn-clear-efi').addEventListener('click', async () => {
    const confirmed = await Swal.fire({
        title: 'Limpar configuracao da Efi Pro?',
        text: 'As credenciais e parametros salvos serao removidos.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Limpar',
        cancelButtonText: 'Cancelar',
    });

    if (!confirmed.isConfirmed) return;

    const r = await fetch('{{ route("admin.gateways.efipro.delete") }}', {
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

// KPIs + ultimos pagamentos
async function loadEfiData() {
    try {
        const r = await fetch('{{ url("/admin/gateways/efipro/recent") }}', {
            credentials: 'same-origin', headers: { 'Accept': 'application/json' },
        });
        const data = await r.json().catch(() => ({ payments: [], stats: {} }));

        const s = data.stats || {};
        document.getElementById('kpi-revenue').textContent = fmtMoney(s.revenue);
        document.getElementById('kpi-paid').textContent = (s.paid_count || 0).toLocaleString('pt-BR');
        document.getElementById('kpi-pending').textContent = (s.pending_count || 0).toLocaleString('pt-BR');
        document.getElementById('kpi-avg').textContent = fmtMoney(s.avg_ticket);

        const tbody = document.getElementById('efi-recent');
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
        document.getElementById('efi-recent').innerHTML = '<tr><td class="text-center text-danger py-3">Erro ao carregar.</td></tr>';
    }
}
loadEfiData();
</script>
@endpush
