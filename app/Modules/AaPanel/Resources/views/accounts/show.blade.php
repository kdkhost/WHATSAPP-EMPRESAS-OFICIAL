@extends(request()->is('painel*') ? 'tenancy::tenant.layout' : 'admin.layouts.app')

@section('title', 'Conta aaPanel - '.$account->domain)

@push('styles')
<style>
/* Forçar alinhamento rigoroso à esquerda para tabelas de arquivos e abas */
#files-content,
#files-content .table-responsive,
#files-content table,
#files-content th,
#files-content td,
#tab-files table,
#tab-files th,
#tab-files td {
    text-align: left !important;
}

#files-content th:first-child,
#files-content td:first-child,
#tab-files th:first-child,
#tab-files td:first-child {
    text-align: left !important;
    padding-left: 1.25rem !important;
}

#files-content th:last-child,
#files-content td:last-child,
#tab-files th:last-child,
#tab-files td:last-child {
    text-align: right !important;
    padding-right: 1.25rem !important;
}

#files-content a,
#files-content span,
#files-content div {
    text-align: left !important;
}
</style>
@endpush

@section('content')
<div class="container-fluid py-3">

    @include('aapanel::partials.header', [
        'title' => 'Conta: ' . $account->domain,
        'subtitle' => 'Gerenciamento completo de arquivos, bancos de dados, SSL, PHP, FTP e backups da hospedagem.'
    ])

    <!-- Barra de Controle e Status da Conta -->
    <div class="card shadow-sm mb-4 rounded-3">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar-lg bg-primary-subtle text-primary rounded-3 d-flex align-items-center justify-content-center p-3 fs-2 shadow-2xs">
                        <i class="bi bi-globe"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h4 class="mb-0 fw-bold">{{ $account->domain }}</h4>
                            <a href="http://{{ $account->domain }}" target="_blank" class="btn btn-sm btn-outline-secondary py-0 px-2" title="Abrir Site em Nova Aba">
                                <i class="bi bi-box-arrow-up-right small"></i>
                            </a>
                            <span class="badge bg-{{ $account->status === 'active' ? 'success' : ($account->status === 'suspended' ? 'warning' : ($account->status === 'terminated' ? 'danger' : 'secondary')) }} px-2.5 py-1 fw-semibold">
                                {{ ucfirst($account->status) }}
                            </span>
                            {!! $account->ssl_enabled ? '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><i class="bi bi-shield-check me-1"></i> SSL Ativo</span>' : '<span class="badge bg-secondary-subtle text-secondary border px-2 py-1"><i class="bi bi-shield-x me-1"></i> Sem SSL</span>' !!}
                        </div>
                        <p class="text-muted small mb-0">
                            <i class="bi bi-server me-1"></i> Servidor: <strong>{{ $account->server?->name ?? 'N/A' }}</strong> (<code>{{ $account->server?->host ?? 'N/A' }}</code>) &bull; 
                            <i class="bi bi-layers me-1"></i> Plano: <strong>{{ $account->plan?->name ?? 'Padrão' }}</strong>
                        </p>
                    </div>
                </div>

                <div class="d-flex gap-2 flex-wrap ms-auto">
                    <a href="{{ request()->is('painel*') ? route('painel.aapanel.accounts.index') : route('admin.aapanel.accounts.index') }}" class="btn btn-outline-secondary btn-sm rounded-2">
                        <i class="bi bi-arrow-left me-1"></i> Voltar para Contas
                    </a>

                    @if($account->status === 'active')
                    <button class="btn btn-warning btn-sm rounded-2" data-suspend="{{ $account->id }}">
                        <i class="bi bi-pause-fill me-1"></i> Suspender
                    </button>
                    @elseif($account->status === 'suspended')
                    <button class="btn btn-success btn-sm rounded-2" data-unsuspend="{{ $account->id }}">
                        <i class="bi bi-play-fill me-1"></i> Reativar
                    </button>
                    @endif

                    @if(in_array($account->status, ['active','suspended','pending']))
                    <button class="btn btn-outline-danger btn-sm rounded-2" data-terminate="{{ $account->id }}">
                        <i class="bi bi-trash me-1"></i> Terminar
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Cards de Credenciais e Acesso Rápido -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-transparent border-bottom py-3">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-info-circle text-primary me-2"></i>Informações Gerais da Conta</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-borderless align-middle m-0 small">
                            <tr><td class="text-muted w-35">Usuário do Sistema:</td><td><code>{{ $account->username ?? '-' }}</code></td></tr>
                            <tr><td class="text-muted">ID do Site aaPanel:</td><td><code>{{ $account->site_id ?? '-' }}</code></td></tr>
                            <tr><td class="text-muted">Caminho / Document Root:</td><td><code>/www/wwwroot/{{ $account->domain }}</code></td></tr>
                            <tr><td class="text-muted">Provisionado em:</td><td>{{ $account->provisioned_at ? $account->provisioned_at->format('d/m/Y H:i:s') : 'Pendente' }}</td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-transparent border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-key text-info me-2"></i>Acessos & Utilitários</h6>
                    <button class="btn btn-sm btn-outline-primary py-0 px-2" data-clear-cache="{{ $account->id }}" title="Limpar Cache do Nginx/Site">
                        <i class="bi bi-arrow-clockwise me-1"></i> Limpar Cache
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-borderless align-middle m-0 small">
                            <tr><td class="text-muted w-35">Usuário FTP:</td><td><code>{{ $account->ftp_username ?? '-' }}</code></td></tr>
                            <tr><td class="text-muted">Banco de Dados:</td><td><code>{{ $account->database_name ?? '-' }}</code></td></tr>
                            <tr><td class="text-muted">Usuário Banco:</td><td><code>{{ $account->database_username ?? '-' }}</code></td></tr>
                        </table>
                    </div>
                    @if(!$account->ssl_enabled && $account->status === 'active')
                    <div class="mt-3 pt-2 border-top">
                        <button class="btn btn-sm btn-success w-100 rounded-2" data-ssl="{{ $account->id }}">
                            <i class="bi bi-shield-check me-1"></i> Gerar e Aplicar Certificado SSL (Let's Encrypt)
                        </button>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Navegação em Abas do Módulo da Conta -->
    <div class="card shadow-sm">
        <div class="card-header bg-transparent border-bottom p-2">
            <ul class="nav nav-tabs card-header-tabs custom-account-tabs border-0" id="accountTabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active fw-medium" data-bs-toggle="tab" data-bs-target="#tab-logs">
                        <i class="bi bi-journal-text me-1 text-primary"></i> Logs de Operações
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-medium" data-bs-toggle="tab" data-bs-target="#tab-ssl" onclick="loadSsl()">
                        <i class="bi bi-shield-lock me-1 text-success"></i> SSL / HTTPS
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-medium" data-bs-toggle="tab" data-bs-target="#tab-php" onclick="loadPhp()">
                        <i class="bi bi-filetype-php me-1 text-info"></i> PHP & Config
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-medium" data-bs-toggle="tab" data-bs-target="#tab-databases" onclick="loadDatabases()">
                        <i class="bi bi-database me-1 text-warning"></i> Bancos de Dados
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-medium" data-bs-toggle="tab" data-bs-target="#tab-files" onclick="loadFiles()">
                        <i class="bi bi-folder2-open me-1 text-primary"></i> Gerenciador de Arquivos
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-medium" data-bs-toggle="tab" data-bs-target="#tab-ftps" onclick="loadFtps()">
                        <i class="bi bi-hdd-network me-1 text-secondary"></i> FTP
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-medium" data-bs-toggle="tab" data-bs-target="#tab-backups" onclick="loadBackups()">
                        <i class="bi bi-cloud-arrow-down me-1 text-purple"></i> Backups
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-medium" data-bs-toggle="tab" data-bs-target="#tab-crons" onclick="loadCrons()">
                        <i class="bi bi-clock me-1 text-dark"></i> Crons
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-medium" data-bs-toggle="tab" data-bs-target="#tab-redirects" onclick="loadRedirects()">
                        <i class="bi bi-arrow-return-right me-1 text-danger"></i> Redirects
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-medium" data-bs-toggle="tab" data-bs-target="#tab-logs-server" onclick="loadLogs()">
                        <i class="bi bi-terminal me-1 text-muted"></i> Logs Servidor
                    </button>
                </li>
            </ul>
        </div>

        <div class="card-body p-4">
            <div class="tab-content" id="accountTabsContent">
                
                <!-- Tab Logs -->
                <div class="tab-pane fade show active" id="tab-logs">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle m-0 small">
                            <thead class="bg-light">
                                <tr><th>Operação</th><th>Status</th><th>Endpoint</th><th>Mensagem</th><th class="text-end">Data</th></tr>
                            </thead>
                            <tbody>
                                @forelse($account->logs as $log)
                                <tr>
                                    <td><span class="badge bg-secondary font-monospace">{{ $log->operation }}</span></td>
                                    <td><span class="badge bg-{{ $log->status === 'success' ? 'success' : 'danger' }}">{{ ucfirst($log->status) }}</span></td>
                                    <td class="text-muted font-monospace">{{ Str::limit($log->endpoint, 40) }}</td>
                                    <td>{{ Str::limit($log->message, 80) }}</td>
                                    <td class="text-end text-muted">{{ $log->created_at->diffForHumans() }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">Nenhum log registrado para esta conta.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="tab-pane fade" id="tab-ssl"><div id="ssl-content" class="text-muted py-3 text-center"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Carregando status do SSL...</div></div>
                <div class="tab-pane fade" id="tab-php"><div id="php-content" class="text-muted py-3 text-center"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Carregando configurações do PHP...</div></div>
                <div class="tab-pane fade" id="tab-databases"><div id="databases-content" class="text-muted py-3 text-center"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Carregando bancos de dados...</div></div>
                <div class="tab-pane fade" id="tab-files"><div id="files-content" class="text-start w-100 p-0 m-0" style="text-align: left !important;"><div class="text-start text-muted py-4 ps-3"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Carregando arquivos do servidor...</div></div></div>
                <div class="tab-pane fade" id="tab-ftps"><div id="ftps-content" class="text-muted py-3 text-center"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Carregando usuários FTP...</div></div>
                <div class="tab-pane fade" id="tab-backups"><div id="backups-content" class="text-muted py-3 text-center"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Carregando backups do site...</div></div>
                <div class="tab-pane fade" id="tab-crons"><div id="crons-content" class="text-muted py-3 text-center"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Carregando tarefas agendadas...</div></div>
                <div class="tab-pane fade" id="tab-redirects"><div id="redirects-content" class="text-muted py-3 text-center"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Carregando redirecionamentos...</div></div>
                <div class="tab-pane fade" id="tab-logs-server"><div id="logs-content" class="text-muted py-3 text-center"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Carregando logs do servidor...</div></div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const csrf = document.querySelector('meta[name=csrf-token]').content;
const headers = { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' };
const accountId = {{ $account->id }};
const baseUrl = "{{ request()->is('painel*') ? url('/painel/aapanel/accounts') : url('/admin/aapanel/accounts') }}";

function actionBtn(btn, promiseFn) {
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Aguarde...';
    promiseFn().finally(() => { btn.disabled = false; btn.innerHTML = orig; });
}

function apiFetch(method, path, body=null) {
    const opts = { method, credentials: 'same-origin', headers };
    if (body) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
    return fetch(baseUrl + '/' + accountId + path, opts).then(r => r.json());
}

['suspend','unsuspend'].forEach(action => {
    document.querySelectorAll(`[data-${action}]`).forEach(b => b.addEventListener('click', () => {
        const isSuspend = (action === 'suspend');
        const title = isSuspend ? 'Suspender site?' : 'Reativar site?';
        const text = isSuspend ? 'O site deixará de responder temporariamente até ser reativado.' : 'O site voltará a responder na internet.';
        const confirmBtnText = isSuspend ? 'Sim, Suspender' : 'Sim, Reativar';
        const confirmColor = isSuspend ? '#ffc107' : '#198754';

        Swal.fire({
            icon: isSuspend ? 'warning' : 'question',
            title: title,
            text: text,
            showCancelButton: true,
            confirmButtonText: confirmBtnText,
            cancelButtonText: 'Cancelar',
            confirmButtonColor: confirmColor,
        }).then(async (res) => {
            if (!res.isConfirmed) return;
            actionBtn(b, async () => {
                try {
                    const d = await apiFetch('POST', `/${action}`);
                    if (d.ok) {
                        window.toast.success(d.message || (isSuspend ? 'Site suspenso com sucesso!' : 'Site reativado com sucesso!'));
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        window.toast.error(d.message || 'Falha ao executar operação.');
                    }
                } catch { window.toast.error('Erro de conexão.'); }
            });
        });
    }));
});

document.querySelectorAll('[data-terminate]').forEach(b => b.addEventListener('click', () => {
    Swal.fire({ icon: 'warning', title: 'Terminar conta?', text: 'Todos os dados e arquivos do site serão removidos permanentemente.', showCancelButton: true, confirmButtonText: 'Sim, Terminar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545' })
    .then(async res => {
        if (!res.isConfirmed) return;
        actionBtn(b, async () => {
            try {
                const d = await apiFetch('DELETE', '/terminate');
                if (d.ok) {
                    window.toast.success('Conta terminada com sucesso.');
                    setTimeout(() => { window.location.href = baseUrl; }, 1000);
                } else {
                    window.toast.error(d.message || 'Falha.');
                }
            } catch { window.toast.error('Erro de rede.'); }
        });
    });
}));

document.querySelectorAll('[data-ssl]').forEach(b => b.addEventListener('click', () => {
    actionBtn(b, async () => {
        try {
            const d = await apiFetch('POST', '/ssl');
            if (d.ok) {
                window.toast.success('Certificado SSL aplicado com sucesso.');
                if (typeof loadSsl === 'function') loadSsl();
            } else {
                window.toast.error(d.message || 'Falha ao aplicar SSL.');
            }
        } catch { window.toast.error('Erro de rede.'); }
    });
}));

document.querySelectorAll('[data-clear-cache]').forEach(b => b.addEventListener('click', () => {
    actionBtn(b, async () => {
        try { const d = await apiFetch('POST', '/clear-cache'); d.ok ? window.toast.success('Cache do servidor limpo com sucesso.') : window.toast.error(d.message || 'Falha.'); }
        catch { window.toast.error('Erro de rede.'); }
    });
}));

let isEditingFile = false;
const tabCache = {};

function cacheKey(tabId, extra='') { return accountId + ':' + tabId + (extra ? ':' + extra : ''); }

function renderTable(data, columns, emptyMsg='Nenhum dado encontrado.') {
    if (!Array.isArray(data) || data.length === 0) {
        return `<div class="text-center text-muted py-4"><i class="bi bi-inbox fs-2 d-block mb-2 text-secondary opacity-50"></i>${emptyMsg}</div>`;
    }
    let html = '<div class="table-responsive text-start w-100 p-0 m-0"><table class="table table-hover align-middle m-0 small w-100 text-start" style="text-align:left !important; table-layout:fixed; width:100% !important;">';
    html += '<thead class="table-light text-start"><tr>';
    columns.forEach((c, i) => {
        const isFirst = (i === 0);
        const isLast = (i === columns.length - 1);
        const align = isLast ? 'right' : 'left';
        const width = c.width ? `width:${c.width};` : (isLast ? 'width:140px;' : '');
        const pad = isFirst ? 'padding-left:1.25rem !important;' : (isLast ? 'padding-right:1.25rem !important;' : 'padding-left:0.75rem !important;');
        html += `<th class="${isLast ? 'text-end' : 'text-start'}" style="text-align:${align} !important; ${pad} ${width}">${c.label}</th>`;
    });
    html += '</tr></thead><tbody class="text-start">';

    data.forEach(row => {
        html += '<tr class="text-start">';
        columns.forEach((c, i) => {
            const isFirst = (i === 0);
            const isLast = (i === columns.length - 1);
            const align = isLast ? 'right' : 'left';
            const pad = isFirst ? 'padding-left:1.25rem !important;' : (isLast ? 'padding-right:1.25rem !important;' : 'padding-left:0.75rem !important;');
            html += `<td class="${isLast ? 'text-end' : 'text-start'}" style="text-align:${align} !important; ${pad}">${c.fn(row)}</td>`;
        });
        html += '</tr>';
    });
    html += '</tbody></table></div>';
    return html;
}

function smartTable(el, data, columns, emptyMsg='Nenhum dado encontrado.') {
    if (!Array.isArray(data) || data.length === 0) {
        el.innerHTML = `<div class="text-center text-muted py-4"><i class="bi bi-inbox fs-2 d-block mb-2 text-secondary opacity-50"></i>${emptyMsg}</div>`;
        return;
    }
    el.innerHTML = renderTable(data, columns, emptyMsg);
}

function renderError(msg) { 
    return `<div class="alert alert-danger d-flex align-items-center gap-2 text-start"><i class="bi bi-exclamation-triangle-fill"></i><div>${msg}</div></div>`; 
}

// ================= TAB SSL =================
async function loadSsl(silent=false) {
    const el = document.getElementById('ssl-content');
    if (!silent) el.innerHTML = '<div class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Consultando status do SSL no servidor...</div>';
    try {
        const d = await apiFetch('GET', '/ssl-status');
        if (!d.ok) { if (!silent) el.innerHTML = renderError(d.message || 'Falha ao carregar SSL.'); return; }
        const info = d.data?.data || d.data || {};
        const key = cacheKey('ssl');
        const infoJson = JSON.stringify(info);
        if (silent && tabCache[key] === infoJson) return;
        tabCache[key] = infoJson;

        let html = '<div class="card border-0 bg-light p-3 rounded-3 mb-3"><div class="row g-3">';
        html += `<div class="col-md-6"><strong>Status SSL:</strong> ${info.status !== false ? '<span class="badge bg-success ms-2">Ativo</span>' : '<span class="badge bg-secondary ms-2">Inativo</span>'}</div>`;
        html += `<div class="col-md-6"><strong>Forçar HTTPS:</strong> ${info.httpTohttps ? '<span class="badge bg-success ms-2">Ativado</span>' : '<span class="badge bg-secondary ms-2">Desativado</span>'}</div>`;
        if (info.cert_data) {
            html += `<div class="col-md-6"><strong>Emissor:</strong> <code>${info.cert_data.issuer||'-'}</code></div>`;
            html += `<div class="col-md-6"><strong>Válido até:</strong> <code>${info.cert_data.notAfter||'-'}</code></div>`;
        }
        html += '</div></div>';

        html += '<div class="d-flex gap-2 flex-wrap">';
        html += `<button class="btn btn-sm btn-success rounded-2" onclick="actionBtn(this,async()=>{const d=await apiFetch('POST','/ssl');d.ok?window.toast.success('SSL gerado com sucesso!'):window.toast.error(d.message||'Falha.');if(d.ok)loadSsl();})"><i class="bi bi-shield-check me-1"></i> Gerar/Aplicar SSL</button>`;
        html += `<button class="btn btn-sm btn-primary rounded-2" onclick="actionBtn(this,async()=>{const d=await apiFetch('POST','/renew-ssl');d.ok?window.toast.success('SSL renovado!'):window.toast.error(d.message||'Falha.');if(d.ok)loadSsl();})"><i class="bi bi-arrow-repeat me-1"></i> Renovar Certificado</button>`;
        html += `<button class="btn btn-sm btn-warning rounded-2" onclick="actionBtn(this,async()=>{const d=await apiFetch('POST','/force-https');d.ok?window.toast.success('Configuração alterada!'):window.toast.error(d.message||'Falha.');if(d.ok)loadSsl();})"><i class="bi bi-lock-fill me-1"></i> Alternar Forçar HTTPS</button>`;
        html += `<button class="btn btn-sm btn-outline-danger rounded-2 ms-auto" onclick="actionBtn(this,async()=>{const d=await apiFetch('POST','/delete-ssl');d.ok?window.toast.success('SSL removido.'):window.toast.error(d.message||'Falha.');if(d.ok)loadSsl();})"><i class="bi bi-trash me-1"></i> Remover SSL</button>`;
        html += '</div>';
        el.innerHTML = html;
    } catch { if (!silent) el.innerHTML = renderError('Erro de rede ao conectar.'); }
}

// ================= TAB PHP =================
async function loadPhp(silent=false) {
    const el = document.getElementById('php-content');
    if (!silent) el.innerHTML = '<div class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Consultando versão do PHP e parâmetros...</div>';
    try {
        const [ver, cfg] = await Promise.all([
            apiFetch('GET', '/php-version'),
            apiFetch('GET', '/php-config')
        ]);

        if (!ver.ok && !cfg.ok) {
            if (!silent) el.innerHTML = renderError(ver.message || cfg.message || 'Falha ao consultar configurações de PHP no servidor.');
            return;
        }

        const current = ver.data?.data?.version ?? ver.data?.version ?? ver.data?.phpversion ?? ver.data?.data?.phpversion ?? 'Padrão';
        const cfgData = cfg.data?.data || cfg.data || {};
        const key = cacheKey('php');
        const payloadJson = JSON.stringify({version: current, cfg: cfgData});
        if (silent && tabCache[key] === payloadJson) return;
        tabCache[key] = payloadJson;

        let html = '<div class="card border-0 bg-light p-3 rounded-3 mb-3 text-start">';
        html += `<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 pb-3 border-bottom">`;
        html += `<div><strong>Versão do PHP Atual:</strong> <span class="badge bg-info-subtle text-info border border-info-subtle font-monospace ms-2 fs-6">PHP ${current}</span></div>`;
        html += `<div class="d-flex gap-2">`;
        html += `<button class="btn btn-sm btn-outline-success rounded-2" onclick="restartPhpFpm('${current}')" title="Reiniciar PHP-FPM"><i class="bi bi-arrow-clockwise me-1"></i> Reiniciar PHP-FPM</button>`;
        html += `<button class="btn btn-sm btn-outline-primary rounded-2" onclick="togglePhpSelector(this)"><i class="bi bi-arrow-left-right me-1"></i> Alterar Versão</button>`;
        html += `</div></div>`;

        html += `<div id="php-selector" style="display:none" class="mb-3 p-3 bg-body-tertiary rounded border"><label class="form-label small fw-semibold">Selecione a Versão do PHP:</label><div class="input-group input-group-sm" style="max-width:350px">`;
        html += `<select class="form-select" id="php-version-select"><option value="">Carregando versões instaladas...</option></select>`;
        html += `<button class="btn btn-success" onclick="changePhpVersion()">Salvar Alteração</button></div></div>`;
        loadPhpVersions();

        if (cfg.ok && Object.keys(cfgData).length > 0) {
            html += '<h6 class="fw-bold mb-2 text-start"><i class="bi bi-sliders me-1 text-primary"></i> Parâmetros PHP Ativos:</h6><div class="row g-2 text-start mb-3">';
            ['upload_max_filesize','post_max_size','memory_limit','max_execution_time','max_input_time'].forEach(k => {
                if (cfgData[k]) {
                    html += `<div class="col-md-4"><div class="p-2 bg-body-tertiary rounded border d-flex justify-content-between align-items-center"><span class="small text-muted">${k}:</span> <code class="fw-semibold">${cfgData[k]||'-'}</code></div></div>`;
                }
            });
            html += '</div>';
        }

        // Card de Extensões do PHP
        html += `<div class="card border p-3 rounded-3 mb-3">`;
        html += `<div class="d-flex justify-content-between align-items-center mb-2">`;
        html += `<h6 class="fw-bold m-0"><i class="bi bi-puzzle me-1 text-warning"></i> Extensões & Recursos do PHP:</h6>`;
        html += `<span class="badge bg-light text-secondary border small">PHP ${current}</span>`;
        html += `</div>`;
        html += `<div id="php-extensions-container" class="py-2"><div class="spinner-border spinner-border-sm text-primary me-2"></div><span class="text-muted small">Carregando extensões ativas...</span></div>`;
        html += `</div>`;

        // Visualizador de Logs do PHP-FPM
        html += `<div class="card border p-3 rounded-3 mb-1">`;
        html += `<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">`;
        html += `<div class="d-flex align-items-center gap-2">`;
        html += `<h6 class="fw-bold m-0"><i class="bi bi-file-earmark-text me-1 text-danger"></i> Logs do PHP-FPM:</h6>`;
        html += `<div class="btn-group btn-group-sm" role="group">`;
        html += `<button type="button" class="btn btn-outline-secondary active" id="btn-log-fpm" onclick="switchPhpLogType('fpm')">php-fpm.log</button>`;
        html += `<button type="button" class="btn btn-outline-secondary" id="btn-log-slow" onclick="switchPhpLogType('slow')">slow.log</button>`;
        html += `</div>`;
        html += `</div>`;
        html += `<button class="btn btn-sm btn-outline-secondary rounded-2" onclick="refreshPhpLogs()"><i class="bi bi-arrow-clockwise me-1"></i> Atualizar Logs</button>`;
        html += `</div>`;
        html += `<pre id="php-logs-terminal" class="bg-dark text-light p-3 rounded-2 small m-0" style="height: 240px; overflow-y: auto; font-family: monospace; font-size: 11px; white-space: pre-wrap; word-break: break-all; background-color: #1a1e24 !important;">Carregando logs...</pre>`;
        html += `</div>`;

        html += '</div>';
        el.innerHTML = html;

        // Carrega extensões e logs assincronamente
        loadPhpExtensions();
        loadPhpLogs('fpm');
    } catch { if (!silent) el.innerHTML = renderError('Erro ao consultar PHP.'); }
}

function togglePhpSelector(btn) {
    const sel = document.getElementById('php-selector');
    sel.style.display = sel.style.display === 'none' ? 'block' : 'none';
    btn.innerHTML = sel.style.display === 'none' ? '<i class="bi bi-arrow-left-right me-1"></i> Alterar Versão' : '<i class="bi bi-x me-1"></i> Cancelar';
}

async function loadPhpVersions() {
    try {
        const r = await fetch('{{ route('admin.aapanel.servers.php-versions', $account->server) }}', { credentials: 'same-origin', headers });
        const d = await r.json();
        const select = document.getElementById('php-version-select');
        if (d.ok && Array.isArray(d.data)) {
            select.innerHTML = d.data.map(v => `<option value="${v.version}">${v.name || 'PHP '+v.version}</option>`).join('');
        } else {
            select.innerHTML = '<option value="84">PHP 8.4</option><option value="82">PHP 8.2</option><option value="81">PHP 8.1</option><option value="56">PHP 5.6</option>';
        }
    } catch { document.getElementById('php-version-select').innerHTML = '<option value="84">PHP 8.4</option><option value="82">PHP 8.2</option>'; }
}

async function changePhpVersion() {
    const select = document.getElementById('php-version-select');
    const version = select.value;
    if (!version) return;
    try { const d = await apiFetch('POST', '/php-version', {version}); d.ok?window.toast.success('Versão do PHP alterada com sucesso!'):window.toast.error(d.message||'Falha.'); if(d.ok)loadPhp(); }
    catch { window.toast.error('Erro de conexão.'); }
}

window.restartPhpFpm = async function(version) {
    const res = await Swal.fire({
        icon: 'question',
        title: 'Reiniciar PHP-FPM?',
        text: `Deseja reiniciar os workers do PHP-FPM ${version}? Isso restabelece as conexões sem derrubar o servidor.`,
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-arrow-clockwise me-1"></i> Sim, Reiniciar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#198754'
    });
    if (!res.isConfirmed) return;

    window.toast.info('Reiniciando serviço PHP-FPM...');
    try {
        const d = await apiFetch('POST', '/php/restart');
        if (d.ok) {
            window.toast.success(d.message || 'PHP-FPM reiniciado com sucesso!');
            loadPhpLogs('fpm');
        } else {
            window.toast.error(d.message || 'Falha ao reiniciar PHP-FPM.');
        }
    } catch {
        window.toast.error('Erro de conexão ao reiniciar PHP-FPM.');
    }
};

async function loadPhpExtensions() {
    const container = document.getElementById('php-extensions-container');
    if (!container) return;
    try {
        const d = await apiFetch('GET', '/php/extensions');
        if (!d.ok) {
            container.innerHTML = '<span class="text-muted small">Não foi possível carregar as extensões detalhadas.</span>';
            return;
        }

        const list = d.extensions || [];
        let html = '<div class="row g-2">';
        list.forEach(ext => {
            html += `<div class="col-6 col-md-3 col-lg-2">`;
            html += `<div class="p-2 border rounded text-center bg-light-subtle d-flex flex-column justify-content-center align-items-center" style="min-height:55px;">`;
            html += `<div class="fw-semibold small">${ext.name}</div>`;
            html += `<span class="badge ${ext.active ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary'} mt-1" style="font-size:10px;">${ext.active ? 'Ativo' : 'Desativado'}</span>`;
            html += `</div></div>`;
        });
        html += '</div>';

        if (d.disabled_functions && d.disabled_functions.length > 0) {
            html += `<div class="mt-3 pt-2 border-top">`;
            html += `<span class="small fw-semibold text-muted me-2"><i class="bi bi-shield-x text-danger"></i> Funções Bloqueadas (disable_functions):</span>`;
            html += `<div class="d-inline-flex flex-wrap gap-1 mt-1">`;
            d.disabled_functions.slice(0, 15).forEach(fn => {
                html += `<code class="badge bg-light text-danger border small" style="font-size:10px;">${fn}</code>`;
            });
            if (d.disabled_functions.length > 15) {
                html += `<span class="small text-muted">+${d.disabled_functions.length - 15} outras</span>`;
            }
            html += `</div></div>`;
        }

        container.innerHTML = html;
    } catch {
        container.innerHTML = '<span class="text-muted small">Erro ao consultar extensões.</span>';
    }
}

let activePhpLogType = 'fpm';

window.switchPhpLogType = function(type) {
    activePhpLogType = type;
    document.getElementById('btn-log-fpm')?.classList.toggle('active', type === 'fpm');
    document.getElementById('btn-log-slow')?.classList.toggle('active', type === 'slow');
    loadPhpLogs(type);
};

window.refreshPhpLogs = function() {
    loadPhpLogs(activePhpLogType);
};

async function loadPhpLogs(type = 'fpm') {
    const term = document.getElementById('php-logs-terminal');
    if (!term) return;
    term.textContent = `Carregando ${type === 'slow' ? 'slow.log' : 'php-fpm.log'}...`;
    try {
        const d = await apiFetch('GET', `/php/logs?type=${type}`);
        if (d.ok && d.data) {
            term.textContent = d.data.trim() || `[Arquivo de log vazio para PHP ${d.version}]`;
            term.scrollTop = term.scrollHeight;
        } else {
            term.textContent = `Nenhum registro encontrado no log (${type === 'slow' ? 'slow.log' : 'php-fpm.log'}).`;
        }
    } catch {
        term.textContent = 'Erro ao consultar arquivo de logs do PHP.';
    }
}

// ================= TAB BANCOS =================
async function loadDatabases(silent=false) {
    const el = document.getElementById('databases-content');
    if (!silent) el.innerHTML = '<div class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Carregando bancos de dados...</div>';
    try {
        const d = await apiFetch('GET', '/databases');
        if (!d.ok) { if (!silent) el.innerHTML = renderError(d.message||'Falha ao carregar bancos de dados.'); return; }
        const rows = d.data?.data || d.data || [];
        const key = cacheKey('databases');
        const rowsJson = JSON.stringify(rows);
        if (silent && tabCache[key] === rowsJson) return;
        tabCache[key] = rowsJson;

        let html = '<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">';
        html += `<span class="fw-semibold text-secondary small"><i class="bi bi-database me-1 text-warning"></i> Bancos de Dados Vinculados à Conta</span>`;
        html += `<div class="d-flex gap-2">`;
        html += `<button class="btn btn-sm btn-outline-secondary rounded-2" onclick="syncDatabasesManual()"><i class="bi bi-arrow-repeat me-1"></i> Sincronizar Bancos</button>`;
        html += `<button class="btn btn-sm btn-primary rounded-2" onclick="openPhpMyAdmin()"><i class="bi bi-box-arrow-up-right me-1"></i> Abrir phpMyAdmin</button>`;
        html += `</div></div>`;

        html += renderTable(rows, [
            {label:'Nome do Banco', fn:r=>`<code>${r.name}</code>`},
            {label:'Usuário', fn:r=>`<code>${r.username||r.user||'-'}</code>`},
            {label:'Vinculado ao Domínio', fn:r=>`<span class="badge bg-light text-dark border">${r.ps||r.accept||'127.0.0.1'}</span>`},
            {label:'Backups', fn:r=>`<span class="badge bg-secondary-subtle text-secondary">${r.backup_count||0} backups</span>`},
            {label:'Ações', fn:r=>`<button class="btn btn-sm btn-outline-primary me-1" onclick="openPhpMyAdmin()" title="Abrir phpMyAdmin"><i class="bi bi-box-arrow-up-right me-1"></i> phpMyAdmin</button><button class="btn btn-sm btn-outline-success me-1" onclick="createDbBackup()" title="Backup do Banco"><i class="bi bi-cloud-upload"></i></button><button class="btn btn-sm btn-outline-danger" onclick="destroyDb('${r.name}','${r.username}')" title="Excluir Banco"><i class="bi bi-trash"></i></button>`}
        ], 'Nenhum banco de dados configurado para esta conta. Clique em "Sincronizar Bancos" para localizar bancos existentes no servidor.');

        el.innerHTML = html;
    } catch { if (!silent) el.innerHTML = renderError('Erro ao carregar bancos de dados.'); }
}

window.syncDatabasesManual = async function() {
    window.toast.info('Sincronizando bancos de dados com o aaPanel...');
    try {
        const d = await apiFetch('POST', '/databases/sync');
        if (d.ok) {
            window.toast.success(d.message || 'Bancos de dados sincronizados!');
            loadDatabases();
        } else {
            window.toast.error(d.message || 'Nenhum banco localizado para sincronização.');
            loadDatabases();
        }
    } catch {
        window.toast.error('Erro de conexão ao sincronizar bancos.');
    }
};

window.openPhpMyAdmin = async function() {
    try {
        const d = await apiFetch('GET', '/phpmyadmin');
        const data = d.data || {};
        const pmaUrl = data.pma_url || d.url || 'https://{{ preg_replace('#^https?://#', '', rtrim($account->server->host ?? '', '/')) }}:887';
        const panelUrl = data.panel_url || '{{ $account->server->baseUrl() ?? '' }}/database';
        const dbName = data.db_name || '{{ $account->database_name }}';
        const dbUser = data.db_user || '{{ $account->database_username }}';

        Swal.fire({
            title: 'Acessar phpMyAdmin',
            icon: 'info',
            html: `
                <div class="text-start small mb-3">
                    <p class="text-muted mb-2">Utilize as credenciais abaixo para conectar-se ao phpMyAdmin:</p>
                    <div class="p-3 bg-light rounded border mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><strong>Servidor:</strong> <code>127.0.0.1</code></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><strong>Banco:</strong> <code>${dbName || 'sql_pesquisa'}</code></span>
                            <button class="btn btn-xs btn-outline-secondary py-0 px-2" onclick="navigator.clipboard.writeText('${dbName || 'sql_pesquisa'}'); window.toast.success('Banco copiado!')"><i class="bi bi-clipboard"></i> Copiar</button>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span><strong>Usuário:</strong> <code>${dbUser || 'sql_pesquisa'}</code></span>
                            <button class="btn btn-xs btn-outline-secondary py-0 px-2" onclick="navigator.clipboard.writeText('${dbUser || 'sql_pesquisa'}'); window.toast.success('Usuário copiado!')"><i class="bi bi-clipboard"></i> Copiar</button>
                        </div>
                    </div>
                    <div class="d-grid gap-2">
                        <a href="${pmaUrl}" target="_blank" class="btn btn-success fw-semibold">
                            <i class="bi bi-box-arrow-up-right me-1"></i> Abrir phpMyAdmin Direto
                        </a>
                        <a href="${panelUrl}" target="_blank" class="btn btn-outline-primary fw-semibold">
                            <i class="bi bi-server me-1"></i> Acessar pelo Painel aaPanel
                        </a>
                    </div>
                </div>
            `,
            showConfirmButton: false,
            showCloseButton: true
        });
    } catch {
        window.toast.error('Erro de conexão ao consultar phpMyAdmin.');
    }
};

window.destroyDb = async function(name, dbUser) {
    const res = await Swal.fire({ icon: 'warning', title: 'Remover banco de dados?', text: `Remover o banco ${name}? Esta ação não pode ser desfeita.`, showCancelButton: true, confirmButtonText: 'Remover', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545' });
    if (!res.isConfirmed) return;
    try { const d = await apiFetch('DELETE', '/databases', {name, db_user: dbUser}); d.ok?window.toast.success('Banco removido.'):window.toast.error(d.message||'Falha.'); if(d.ok)loadDatabases(); }
    catch { window.toast.error('Erro de rede.'); }
};

// ================= TAB FTP =================
async function loadFtps(silent=false) {
    const el = document.getElementById('ftps-content');
    if (!silent) el.innerHTML = '<div class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Carregando usuários FTP...</div>';
    try {
        const d = await apiFetch('GET', '/ftps');
        if (!d.ok) { if (!silent) el.innerHTML = renderError(d.message||'Falha ao carregar FTP.'); return; }
        const rows = d.data?.data || d.data || [];
        const key = cacheKey('ftps');
        const rowsJson = JSON.stringify(rows);
        if (silent && tabCache[key] === rowsJson) return;
        tabCache[key] = rowsJson;

        let html = '<div class="d-flex justify-content-between align-items-center mb-3">';
        html += `<span class="fw-semibold text-secondary small">Usuários FTP da Conta</span>`;
        html += `<button class="btn btn-sm btn-success rounded-2" onclick="createFtpPrompt()"><i class="bi bi-plus-lg me-1"></i> Novo Usuário FTP</button>`;
        html += '</div>';

        html += renderTable(rows, [
            {label:'Usuário FTP', fn:r=>`<code>${r.name}</code>`},
            {label:'Diretório Base', fn:r=>`<code>${r.path}</code>`},
            {label:'Ações', fn:r=>`<button class="btn btn-sm btn-outline-warning me-1" onclick="changeFtpPw('${r.id}')" title="Alterar Senha"><i class="bi bi-key me-1"></i> Senha</button><button class="btn btn-sm btn-outline-danger" onclick="destroyFtp('${r.id}')" title="Excluir FTP"><i class="bi bi-trash"></i></button>`}
        ], 'Nenhum usuário FTP configurado.');

        el.innerHTML = html;
    } catch { if (!silent) el.innerHTML = renderError('Erro ao carregar FTP.'); }
}

window.destroyFtp = async function(id) {
    const res = await Swal.fire({ icon: 'warning', title: 'Remover usuário FTP?', showCancelButton: true, confirmButtonText: 'Remover', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545' });
    if (!res.isConfirmed) return;
    try { const d = await apiFetch('DELETE', '/ftps', {id}); d.ok?window.toast.success('FTP removido.'):window.toast.error(d.message||'Falha.'); if(d.ok)loadFtps(); }
    catch { window.toast.error('Erro de rede.'); }
};

window.changeFtpPw = async function(id) {
    const res = await Swal.fire({
        icon: 'info', title: 'Alterar Senha FTP',
        input: 'password', inputLabel: 'Nova senha (mínimo 6 caracteres):',
        inputAttributes: { minlength: 6, autocapitalize: 'off', autocorrect: 'off' },
        showCancelButton: true, confirmButtonText: 'Salvar Senha', cancelButtonText: 'Cancelar',
        confirmButtonColor: '#198754',
        inputValidator: (value) => { if (!value || value.length < 6) return 'A senha deve ter no mínimo 6 caracteres.'; }
    });
    if (!res.isConfirmed) return;
    try { const d = await apiFetch('POST', '/ftps/password', {id, password: res.value}); d.ok?window.toast.success('Senha FTP alterada com sucesso!'):window.toast.error(d.message||'Falha.'); }
    catch { window.toast.error('Erro de conexão.'); }
};

window.createFtpPrompt = async function() {
    const res = await Swal.fire({
        icon: 'info', title: 'Criar Novo Usuário FTP',
        html:
            '<div class="text-start mb-2"><label class="form-label small font-semibold">Nome de Usuário:</label><input id="swal-ftp-user" class="swal2-input mt-0" placeholder="Ex.: ftp_queendosvinhos"></div>' +
            '<div class="text-start"><label class="form-label small font-semibold">Senha:</label><input id="swal-ftp-pass" class="swal2-input mt-0" type="password" placeholder="Senha segura"></div>',
        showCancelButton: true, confirmButtonText: 'Criar FTP', cancelButtonText: 'Cancelar',
        confirmButtonColor: '#198754',
        preConfirm: () => {
            const user = document.getElementById('swal-ftp-user').value;
            const pass = document.getElementById('swal-ftp-pass').value;
            if (!user) return Swal.showValidationMessage('Informe o nome de usuário.');
            if (!pass || pass.length < 6) return Swal.showValidationMessage('A senha deve ter no mínimo 6 caracteres.');
            return { user, pass };
        }
    });
    if (!res.isConfirmed) return;
    try { const d = await apiFetch('POST', '/ftps', {username: res.value.user, password: res.value.pass}); d.ok?window.toast.success('Usuário FTP criado!'):window.toast.error(d.message||'Falha.'); if(d.ok)loadFtps(); }
    catch { window.toast.error('Erro de conexão.'); }
};

// ================= TAB BACKUPS =================
async function loadBackups(silent=false) {
    const el = document.getElementById('backups-content');
    if (!silent) el.innerHTML = '<div class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Carregando lista de backups...</div>';
    try {
        const d = await apiFetch('GET', '/backups');
        if (!d.ok) { if (!silent) el.innerHTML = renderError(d.message||'Falha ao carregar backups.'); return; }
        const rows = d.data || [];
        const key = cacheKey('backups');
        const rowsJson = JSON.stringify(rows);
        if (silent && tabCache[key] === rowsJson) return;
        tabCache[key] = rowsJson;

        let html = '<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">';
        html += `<span class="fw-semibold text-secondary small"><i class="bi bi-cloud-arrow-down me-1 text-purple"></i> Backups Disponíveis (${rows.length})</span>`;
        html += `<div class="d-flex gap-2">`;
        html += `<button class="btn btn-sm btn-primary rounded-2" onclick="createSiteBackup()"><i class="bi bi-hdd-stack me-1"></i> Backup do Site</button>`;
        html += `<button class="btn btn-sm btn-warning rounded-2 text-dark" onclick="createDbBackup()"><i class="bi bi-database-check me-1"></i> Backup do Banco</button>`;
        html += `<button class="btn btn-sm btn-outline-secondary rounded-2" onclick="loadBackups()"><i class="bi bi-arrow-clockwise"></i></button>`;
        html += `</div></div>`;

        html += renderTable(rows, [
            {label:'Tipo', fn:r=>`<span class="badge ${r.type==='database'?'bg-warning-subtle text-warning border border-warning-subtle':'bg-primary-subtle text-primary border border-primary-subtle'}">${r.type_label||(r.type==='database'?'Banco de Dados':'Website')}</span>`},
            {label:'Arquivo de Backup', fn:r=>`<code>${r.name}</code>`},
            {label:'Tamanho', fn:r=>`<span class="fw-semibold">${r.size||'-'}</span>`},
            {label:'Data do Backup', fn:r=>r.addtime||r.time||'-'},
            {label:'Ações', fn:r=>`<a class="btn btn-sm btn-outline-info me-1" href="${baseUrl+'/'+accountId+'/backups/download?file_name='+encodeURIComponent(r.filename||r.name)}" target="_blank" title="Download Backup"><i class="bi bi-download me-1"></i> Baixar</a><button class="btn btn-sm btn-outline-danger" onclick="destroyBackup(${r.id},'${r.type}')" title="Excluir Backup"><i class="bi bi-trash"></i></button>`}
        ], 'Nenhum backup encontrado para esta conta. Utilize os botões acima para gerar um novo backup.');

        el.innerHTML = html;
    } catch { if (!silent) el.innerHTML = renderError('Erro ao carregar backups.'); }
}

window.createSiteBackup = async function() {
    const res = await Swal.fire({
        icon: 'question',
        title: 'Gerar Backup do Website?',
        text: 'Será gerado um arquivo .zip compactando todo o diretório /wwwroot da conta.',
        showCancelButton: true,
        confirmButtonText: 'Iniciar Backup',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#0d6efd'
    });
    if (!res.isConfirmed) return;

    window.toast.info('Gerando backup do site no servidor...');
    try {
        const d = await apiFetch('POST', '/backups', {type: 'site'});
        d.ok ? window.toast.success(d.message || 'Backup do site gerado com sucesso!') : window.toast.error(d.message || 'Falha.');
        if (d.ok) loadBackups();
    } catch {
        window.toast.error('Erro de conexão ao gerar backup.');
    }
};

window.createDbBackup = async function() {
    const res = await Swal.fire({
        icon: 'question',
        title: 'Gerar Backup do Banco de Dados?',
        text: 'Será gerado um dump SQL compactado (.sql.gz) do banco de dados vinculado à conta.',
        showCancelButton: true,
        confirmButtonText: 'Iniciar Backup',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#ffc107'
    });
    if (!res.isConfirmed) return;

    window.toast.info('Gerando dump do banco de dados...');
    try {
        const d = await apiFetch('POST', '/backups', {type: 'database'});
        d.ok ? window.toast.success(d.message || 'Backup do banco gerado com sucesso!') : window.toast.error(d.message || 'Falha.');
        if (d.ok) loadBackups();
    } catch {
        window.toast.error('Erro de conexão ao gerar backup do banco.');
    }
};

window.destroyBackup = async function(id, type) {
    const res = await Swal.fire({
        icon: 'warning',
        title: 'Remover arquivo de backup?',
        text: 'Esta ação removerá definitivamente o arquivo de backup do servidor.',
        showCancelButton: true,
        confirmButtonText: 'Remover',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545'
    });
    if (!res.isConfirmed) return;
    try {
        const d = await apiFetch('DELETE', '/backups', {id, type});
        d.ok ? window.toast.success(d.message || 'Backup removido com sucesso!') : window.toast.error(d.message || 'Falha.');
        if (d.ok) loadBackups();
    } catch {
        window.toast.error('Erro de conexão ao remover backup.');
    }
};

// ================= TAB ARQUIVOS (FILE MANAGER) =================
let currentPath = '/www/wwwroot/{{ $account->domain }}';

async function loadFiles(path='/www/wwwroot/{{ $account->domain }}', silent=false) {
    if (silent && (isEditingFile || Swal.isVisible())) return;

    path = (path || '/www/wwwroot/{{ $account->domain }}').trim().replace(/\/+$/, '');
    if (!path.startsWith('/www/wwwroot')) path = '/www/wwwroot/' + path.replace(/^\/+/, '');
    currentPath = path;
    const el = document.getElementById('files-content');
    el.className = 'text-start w-100 p-0 m-0';
    if (!silent) el.innerHTML = '<div class="text-start text-muted py-4 ps-2"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Carregando arquivos do diretório...</div>';
    try {
        const url = baseUrl + '/' + accountId + '/files?path=' + encodeURIComponent(path) + '&_t=' + Date.now();
        const r = await fetch(url, { credentials: 'same-origin', headers });
        const d = await r.json();
        if (!d.ok) { 
            if (!silent) {
                let errHtml = renderError(d.message || 'Falha ao carregar diretório.');
                errHtml += `<div class="mt-3 text-start"><button class="btn btn-sm btn-primary rounded-2" onclick="createDirPrompt('${path}')"><i class="bi bi-folder-plus me-1"></i> Criar Pasta do Site no Servidor (${path})</button></div>`;
                el.innerHTML = errHtml; 
            }
            return; 
        }
        const rows = d.data?.DATA || d.data?.data || d.data || [];
        const key = cacheKey('files', path);
        const rowsJson = JSON.stringify(rows);
        if (silent && tabCache[key] === rowsJson) return;
        tabCache[key] = rowsJson;

        let html = `<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 p-2 bg-light rounded border text-start w-100">`;
        html += `<div class="d-flex align-items-center gap-2"><i class="bi bi-folder-symlink text-primary fs-5"></i><code class="fw-bold text-dark">${path}</code></div>`;
        html += `<div class="d-flex gap-2">`;
        if (path !== '/www/wwwroot/{{ $account->domain }}' && path !== '/www/wwwroot' && path !== '/') {
            const parent = path.substring(0, path.lastIndexOf('/')) || '/www/wwwroot/{{ $account->domain }}';
            html += `<button class="btn btn-sm btn-outline-secondary rounded-2" onclick="loadFiles('${parent}')"><i class="bi bi-arrow-up me-1"></i> Subir Pasta</button>`;
        }
        html += `<button class="btn btn-sm btn-outline-secondary rounded-2" onclick="loadFiles('${path}', false)" title="Atualizar Lista"><i class="bi bi-arrow-clockwise me-1"></i> Atualizar</button>`;
        html += `<button class="btn btn-sm btn-success rounded-2" onclick="createFilePrompt()"><i class="bi bi-file-earmark-plus me-1"></i> Novo Arquivo</button>`;
        html += `<button class="btn btn-sm btn-primary rounded-2" onclick="createDirPrompt()"><i class="bi bi-folder-plus me-1"></i> Nova Pasta</button>`;
        html += `</div></div>`;

        html += renderTable(rows, [
            {label:'Nome', width:'52%', fn:r=>{
                const isDir = r.type==='dir'||r.isdir;
                const cleanName = (r.name || '').replace(/\/+$/, '');
                const itemPath = path + '/' + cleanName;
                return isDir 
                    ? `<div class="d-flex align-items-center justify-content-start text-start w-100" style="text-align:left !important; justify-content:flex-start !important;">
                         <a href="javascript:void(0)" class="text-primary text-decoration-none fw-semibold text-start d-inline-flex align-items-center py-1 text-truncate" style="text-align:left !important; max-width:100%;" onclick="loadFiles('${itemPath}')">
                             <i class="bi bi-folder-fill text-warning me-2 fs-5 flex-shrink-0"></i>
                             <span class="text-truncate" style="text-align:left !important;">${cleanName}/</span>
                         </a>
                       </div>`
                    : `<div class="d-flex align-items-center justify-content-start text-start w-100" style="text-align:left !important; justify-content:flex-start !important;">
                         <span class="text-dark text-start d-inline-flex align-items-center py-1 text-truncate" style="text-align:left !important; max-width:100%;">
                             <i class="bi bi-file-earmark-code text-secondary me-2 fs-5 flex-shrink-0"></i>
                             <span class="text-truncate" style="text-align:left !important;">${cleanName}</span>
                         </span>
                       </div>`;
            }},
            {label:'Tamanho', width:'14%', fn:r=>`<code class="text-dark bg-light px-2 py-1 rounded border">${r.size||'-'}</code>`},
            {label:'Última Modificação', width:'20%', fn:r=>`<span class="text-muted small">${r.mtime||r.time||'-'}</span>`},
            {label:'Ações', width:'14%', fn:r=>{
                const isDir = r.type==='dir'||r.isdir;
                const cleanName = (r.name || '').replace(/\/+$/, '');
                const fullPath = path + '/' + cleanName;
                let btns = '';
                if (!isDir) btns += `<button class="btn btn-sm btn-outline-primary me-1" onclick="editFile('${fullPath}')" title="Editar Conteúdo"><i class="bi bi-pencil me-1"></i> Editar</button>`;
                btns += `<button class="btn btn-sm btn-outline-danger" onclick="destroyFile('${fullPath}')" title="Excluir"><i class="bi bi-trash"></i></button>`;
                return btns;
            }}
        ], 'Diretório vazio.');

        el.innerHTML = html;
    } catch { if (!silent) el.innerHTML = renderError('Erro ao ler diretório.'); }
}

window.destroyFile = async function(filePath) {
    const res = await Swal.fire({ icon: 'warning', title: 'Remover item?', text: filePath, showCancelButton: true, confirmButtonText: 'Remover', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545' });
    if (!res.isConfirmed) return;
    try { const d = await apiFetch('DELETE', '/files', {path: filePath}); d.ok?window.toast.success('Item removido.'):window.toast.error(d.message||'Falha.'); if(d.ok)loadFiles(currentPath); }
    catch { window.toast.error('Erro de conexão.'); }
};

window.createFilePrompt = async function() {
    const res = await Swal.fire({
        icon: 'info', title: 'Criar Novo Arquivo',
        input: 'text', inputLabel: 'Nome do arquivo (ex.: index.php):',
        showCancelButton: true, confirmButtonText: 'Criar Arquivo', cancelButtonText: 'Cancelar',
        confirmButtonColor: '#198754',
        inputValidator: (value) => { if (!value) return 'Informe o nome do arquivo.'; }
    });
    if (!res.isConfirmed) return;
    const fullPath = currentPath + '/' + res.value;
    try { const d = await apiFetch('POST', '/files/create', {path: fullPath}); d.ok?window.toast.success('Arquivo criado!'):window.toast.error(d.message||'Falha.'); if(d.ok)loadFiles(currentPath); }
    catch { window.toast.error('Erro de conexão.'); }
};

window.createDirPrompt = async function() {
    const res = await Swal.fire({
        icon: 'info', title: 'Criar Nova Pasta',
        input: 'text', inputLabel: 'Nome da pasta:',
        showCancelButton: true, confirmButtonText: 'Criar Pasta', cancelButtonText: 'Cancelar',
        confirmButtonColor: '#0d6efd',
        inputValidator: (value) => { if (!value) return 'Informe o nome da pasta.'; }
    });
    if (!res.isConfirmed) return;
    const fullPath = currentPath + '/' + res.value;
    try { const d = await apiFetch('POST', '/files/directory', {path: fullPath}); d.ok?window.toast.success('Pasta criada!'):window.toast.error(d.message||'Falha.'); if(d.ok)loadFiles(currentPath); }
    catch { window.toast.error('Erro de conexão.'); }
};

window.editFile = async function(filePath) {
    isEditingFile = true;
    try {
        const d = await apiFetch('GET', '/files/content?path=' + encodeURIComponent(filePath));
        if (!d.ok) { 
            isEditingFile = false;
            window.toast.error(d.message||'Falha ao abrir arquivo.'); 
            return; 
        }
        const content = (typeof d.data === 'string') ? d.data : (d.data?.data || '');
        const fileName = filePath.split('/').pop();
        const res = await Swal.fire({
            icon: 'info', 
            title: '<i class="bi bi-code-square me-2 text-primary"></i>Editar: ' + fileName,
            width: '900px',
            html: `<div class="text-start mb-2"><code class="small text-muted">${filePath}</code></div><textarea id="swal-file-content" class="swal2-textarea font-monospace small m-0 w-100 text-start" style="height:400px;background:#0d1117;color:#c9d1d9;border:1px solid #30363d;border-radius:6px;padding:12px;font-size:13px;line-height:1.5;resize:vertical" spellcheck="false">${content.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')}</textarea>`,
            showCancelButton: true, 
            confirmButtonText: '<i class="bi bi-check-circle me-1"></i> Salvar Alterações', 
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#198754',
            cancelButtonColor: '#6c757d',
            allowOutsideClick: false,
            didClose: () => { isEditingFile = false; },
            preConfirm: () => {
                const el = document.getElementById('swal-file-content');
                return el ? el.value : '';
            }
        });

        if (!res.isConfirmed) {
            isEditingFile = false;
            return;
        }

        window.toast.info('Salvando arquivo...');
        const save = await apiFetch('POST', '/files/content', {path: filePath, content: res.value});
        if (save && save.ok) {
            window.toast.success('Arquivo salvo com sucesso!');
            loadFiles(currentPath);
        } else {
            window.toast.error(save?.message || 'Falha ao salvar arquivo.');
        }
        isEditingFile = false;
    } catch { 
        isEditingFile = false;
        window.toast.error('Erro de conexão ao editar.'); 
    }
};

// ================= TAB LOGS SERVIDOR =================
async function loadLogs(silent=false) {
    const el = document.getElementById('logs-content');
    if (!silent) el.innerHTML = '<div class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Consultando logs de acesso e erros do servidor...</div>';
    try {
        const [acc, err] = await Promise.all([apiFetch('GET','/access-log?lines=50'), apiFetch('GET','/error-log')]);
        const accContent = acc.data?.data || acc.data || 'Nenhum log de acesso registrado.';
        const errContent = err.data?.data || err.data || 'Nenhum log de erro registrado.';
        const key = cacheKey('logs');
        const payloadJson = JSON.stringify({acc: accContent, err: errContent});
        if (silent && tabCache[key] === payloadJson) return;
        tabCache[key] = payloadJson;

        let html = '<div class="row g-3">';
        html += '<div class="col-md-6"><div class="card shadow-sm border"><div class="card-header bg-light py-2"><strong>Access Log (Últimas 50 linhas)</strong></div><div class="card-body p-2"><pre class="small bg-dark text-light p-3 rounded font-monospace m-0" style="max-height:350px;overflow:auto">'+accContent+'</pre></div></div></div>';
        html += '<div class="col-md-6"><div class="card shadow-sm border"><div class="card-header bg-light py-2"><strong>Error Log</strong></div><div class="card-body p-2"><pre class="small bg-dark text-danger p-3 rounded font-monospace m-0" style="max-height:350px;overflow:auto">'+errContent+'</pre></div></div></div>';
        html += '</div>';
        el.innerHTML = html;
    } catch { if (!silent) el.innerHTML = renderError('Erro ao consultar logs do servidor.'); }
}

// ================= TAB CRONS =================
async function loadCrons(silent=false) {
    const el = document.getElementById('crons-content');
    if (!silent) el.innerHTML = '<div class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Carregando tarefas agendadas...</div>';
    try {
        const d = await apiFetch('GET', '/crons');
        if (!d.ok) { if (!silent) el.innerHTML = renderError(d.message||'Falha ao carregar crons.'); return; }
        const rows = d.data?.data || d.data || [];
        const key = cacheKey('crons');
        const rowsJson = JSON.stringify(rows);
        if (silent && tabCache[key] === rowsJson) return;
        tabCache[key] = rowsJson;

        smartTable(el, rows, [
            {label:'Nome da Tarefa', fn:r=>`<code>${r.name}</code>`},
            {label:'Tipo', fn:r=>`<span class="badge bg-light text-dark border">${r.type||'Shell'}</span>`},
            {label:'Horário Agendado', fn:r=>`<code>${r.where_hour||'00'}:${r.where_minute||'00'}</code>`},
            {label:'Ações', fn:r=>`<button class="btn btn-sm btn-outline-danger" onclick="destroyCron('${r.id}')" title="Excluir Cron"><i class="bi bi-trash"></i></button>`}
        ], 'Nenhuma tarefa cron agendada.');
    } catch { if (!silent) el.innerHTML = renderError('Erro ao carregar crons.'); }
}

window.destroyCron = async function(id) {
    const res = await Swal.fire({ icon: 'warning', title: 'Remover agendamento cron?', showCancelButton: true, confirmButtonText: 'Remover', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545' });
    if (!res.isConfirmed) return;
    try { const d = await apiFetch('DELETE', '/crons', {id}); d.ok?window.toast.success('Cron removido.'):window.toast.error(d.message||'Falha.'); if(d.ok)loadCrons(); }
    catch { window.toast.error('Erro de conexão.'); }
};

// ================= TAB REDIRECTS =================
async function loadRedirects(silent=false) {
    const el = document.getElementById('redirects-content');
    if (!silent) el.innerHTML = '<div class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Carregando redirecionamentos...</div>';
    try {
        const d = await apiFetch('GET', '/redirects');
        if (!d.ok) { if (!silent) el.innerHTML = renderError(d.message||'Falha ao carregar redirecionamentos.'); return; }
        const rows = d.data?.data || d.data || [];
        const key = cacheKey('redirects');
        const rowsJson = JSON.stringify(rows);
        if (silent && tabCache[key] === rowsJson) return;
        tabCache[key] = rowsJson;

        smartTable(el, rows, [
            {label:'Domínio', fn:r=>r.domain},
            {label:'Origem', fn:r=>`<code>${r.src||'/'}</code>`},
            {label:'Destino', fn:r=>`<code>${r.target||'-'}</code>`},
            {label:'Tipo', fn:r=>`<span class="badge bg-info-subtle text-info border border-info-subtle">${r.type===301?'301 Redirecionamento Permanente':'302 Temporário'}</span>`},
            {label:'Ações', fn:r=>`<button class="btn btn-sm btn-outline-danger" onclick="destroyRedirect('${r.redirectname||r.name}')" title="Excluir"><i class="bi bi-trash"></i></button>`}
        ], 'Nenhum redirecionamento configurado.');
    } catch { if (!silent) el.innerHTML = renderError('Erro ao carregar redirecionamentos.'); }
}

window.destroyRedirect = async function(name) {
    const res = await Swal.fire({ icon: 'warning', title: 'Remover redirecionamento?', text: name, showCancelButton: true, confirmButtonText: 'Remover', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545' });
    if (!res.isConfirmed) return;
    try { const d = await apiFetch('DELETE', '/redirects', {redirect_name: name}); d.ok?window.toast.success('Redirecionamento removido.'):window.toast.error(d.message||'Falha.'); if(d.ok)loadRedirects(); }
    catch { window.toast.error('Erro de conexão.'); }
};

// Atualizações ocorrem estritamente sob demanda manual do usuário (sem refresh automático ou polling)
</script>
<style>
.custom-account-tabs .nav-link {
    color: #475569;
    border: none;
    border-bottom: 2px solid transparent;
    padding: 0.75rem 1rem;
    font-size: 0.88rem;
    transition: all 0.2s ease-in-out;
}
.custom-account-tabs .nav-link:hover:not(.active) {
    color: #0f172a;
    border-bottom-color: #cbd5e1;
}
.custom-account-tabs .nav-link.active {
    color: #0d6efd !important;
    border-bottom: 2px solid #0d6efd !important;
    background: transparent !important;
}
.shadow-2xs {
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

/* Forçar alinhamento à esquerda absoluto para todas as tabelas e células do módulo */
#accountTabsContent table,
#accountTabsContent table th,
#accountTabsContent table td,
#accountTabsContent .table,
#accountTabsContent .table th,
#accountTabsContent .table td,
#accountTabsContent .table-responsive table th,
#accountTabsContent .table-responsive table td,
.table-files,
.table-files th,
.table-files td {
    text-align: left !important;
}

#accountTabsContent table th:last-child,
#accountTabsContent table td:last-child,
#accountTabsContent .table th:last-child,
#accountTabsContent .table td:last-child,
.table-files th:last-child,
.table-files td:last-child {
    text-align: right !important;
}

#accountTabsContent .btn-link,
.table-files .btn-link {
    text-align: left !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: flex-start !important;
}

.table-files td > span,
.table-files td > div,
.table-files td > code {
    display: inline-block !important;
    text-align: left !important;
}
</style>
@endpush
