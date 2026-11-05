@extends(request()->is('painel*') ? 'tenancy::tenant.layout' : 'admin.layouts.app')

@section('title', 'Servidores aaPanel')

@section('content')
<div class="container-fluid py-3">

    @include('aapanel::partials.header', [
        'title' => 'Servidores aaPanel',
        'subtitle' => 'Gerencie e monitore a saúde dos servidores com sincronização em tempo real.'
    ])

    <!-- Barra de Ações Rápidas -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 bg-body p-3 rounded-3 shadow-sm border border-subtle">
        <div class="d-flex align-items-center gap-2">
            <span class="fw-semibold text-secondary small"><i class="bi bi-broadcast text-success me-1"></i> Status em Tempo Real:</span>
            <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1" id="live-indicator">
                <span class="pulse-dot bg-success me-1"></span> Ativo
            </span>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap ms-auto">
            <button type="button" class="btn btn-outline-primary btn-sm rounded-2" id="btn-sync-all">
                <i class="bi bi-arrow-repeat me-1" id="icon-sync-all"></i> Sincronizar Todos
            </button>
            
            <div class="form-check form-switch bg-body-tertiary rounded px-3 py-1.5 d-flex align-items-center mb-0 border border-subtle">
                <input class="form-check-input me-2 mt-0" type="checkbox" id="auto-refresh-toggle" checked>
                <label class="form-check-label small fw-semibold text-muted mb-0" for="auto-refresh-toggle">
                    Auto-refresh (15s)
                </label>
            </div>

            <button type="button" class="btn btn-primary btn-sm rounded-2" data-bs-toggle="modal" data-bs-target="#modalServer">
                <i class="bi bi-plus-lg me-1"></i> Adicionar Servidor
            </button>
        </div>
    </div>

    <!-- Grid de Servidores aaPanel -->
    <div class="row g-4" id="servers-grid">
        @forelse($servers as $s)
        @php
            $metrics = $s->meta['metrics'] ?? null;
            $cpuPct = floatval($metrics['cpu_percent'] ?? 0);
            $memPct = floatval($metrics['mem_percent'] ?? 0);
            $diskPct = floatval($metrics['disk_percent'] ?? 0);
            $isOnline = $s->status === 'online';
            $isOffline = $s->status === 'offline';
        @endphp
        <div class="col-md-6 col-lg-4" id="srv-{{ $s->id }}">
            <div class="card shadow-sm h-100 border border-subtle server-card position-relative {{ $isOnline ? 'card-online' : ($isOffline ? 'card-offline' : '') }}">
                
                <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center border-bottom border-subtle py-3">
                    <div class="min-w-0 me-2">
                        <div class="d-flex align-items-center gap-2">
                            <h6 class="mb-0 fw-bold text-body text-truncate">{{ $s->name }}</h6>
                            @if($s->use_ssl)
                            <span class="badge bg-body-secondary text-success border border-success-subtle px-1.5 py-0.5 small" title="SSL / HTTPS Ativado"><i class="bi bi-shield-lock"></i> SSL</span>
                            @endif
                        </div>
                        <code class="small text-muted d-block mt-0.5">{{ $s->host }}:{{ $s->port }}</code>
                    </div>
                    <span class="badge bg-{{ $isOnline ? 'success' : ($isOffline ? 'danger' : 'secondary') }} badge-status px-2.5 py-1.5 fw-semibold d-flex align-items-center gap-1">
                        <span class="pulse-dot bg-white me-1"></span>
                        <span class="status-label">{{ ucfirst($s->status) }}</span>
                    </span>
                </div>

                <div class="card-body d-flex flex-column p-3">
                    
                    <!-- Painel de Métricas -->
                    <div class="metrics-box p-3 bg-body-tertiary rounded-3 border border-subtle mb-3">
                        <div class="d-flex justify-content-between align-items-center small mb-1">
                            <span class="text-secondary fw-medium"><i class="bi bi-cpu text-primary me-1"></i> Processador (CPU)</span>
                            <span class="fw-bold cpu-val text-body">{{ $cpuPct }}% ({{ $metrics['cpu_cores'] ?? 1 }} Cores)</span>
                        </div>
                        <div class="progress mb-2.5" style="height: 7px;">
                            <div class="progress-bar bg-{{ $cpuPct > 80 ? 'danger' : ($cpuPct > 50 ? 'warning' : 'primary') }} cpu-bar" style="width: {{ $cpuPct }}%"></div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center small mb-1">
                            <span class="text-secondary fw-medium"><i class="bi bi-memory text-info me-1"></i> Memória RAM</span>
                            <span class="fw-bold mem-val text-body">{{ $metrics['mem_used'] ?? '0 MB' }} / {{ $metrics['mem_total'] ?? '0 MB' }} ({{ $memPct }}%)</span>
                        </div>
                        <div class="progress mb-2.5" style="height: 7px;">
                            <div class="progress-bar bg-{{ $memPct > 80 ? 'danger' : ($memPct > 50 ? 'warning' : 'info') }} mem-bar" style="width: {{ $memPct }}%"></div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center small mb-1">
                            <span class="text-secondary fw-medium"><i class="bi bi-hdd text-success me-1"></i> Armazenamento (Disco)</span>
                            <span class="fw-bold disk-val text-body">{{ $metrics['disk_used'] ?? '0 GB' }} / {{ $metrics['disk_total'] ?? '0 GB' }} ({{ $diskPct }}%)</span>
                        </div>
                        <div class="progress" style="height: 7px;">
                            <div class="progress-bar bg-{{ $diskPct > 85 ? 'danger' : ($diskPct > 60 ? 'warning' : 'success') }} disk-bar" style="width: {{ $diskPct }}%"></div>
                        </div>
                    </div>

                    <!-- Mensagem de Erro / Diagnóstico caso falhe -->
                    <div class="error-alert-box mb-3 p-2.5 bg-danger-subtle border border-danger-subtle text-danger rounded-2 small {{ $s->last_error ? '' : 'd-none' }}">
                        <div class="d-flex align-items-start gap-2">
                            <i class="bi bi-exclamation-triangle-fill fs-6 mt-0.5"></i>
                            <div class="min-w-0">
                                <strong class="d-block">Falha de Comunicação:</strong>
                                <span class="error-msg-text">{{ Str::limit($s->last_error ?? '', 120) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Chips com Dados do Servidor -->
                    <div class="d-flex gap-2 flex-wrap mb-3">
                        <span class="badge bg-body-secondary text-body border border-subtle px-2.5 py-1.5 count-sites shadow-2xs">
                            <i class="bi bi-globe text-primary me-1"></i> <strong>{{ $metrics['site_count'] ?? 0 }}</strong> Sites
                        </span>
                        <span class="badge bg-body-secondary text-body border border-subtle px-2.5 py-1.5 count-dbs shadow-2xs">
                            <i class="bi bi-database text-info me-1"></i> <strong>{{ $metrics['database_count'] ?? 0 }}</strong> DBs
                        </span>
                        <span class="badge bg-body-secondary text-body border border-subtle px-2.5 py-1.5 uptime-text shadow-2xs" title="Sistema / Uptime">
                            <i class="bi bi-clock-history text-secondary me-1"></i> {{ $metrics['uptime'] ?? 'Ativo' }}
                        </span>
                    </div>

                    <div class="small text-muted mb-3 last-check-text">
                        <i class="bi bi-check2-circle text-success me-1"></i> Checado: {{ $s->last_check_at ? $s->last_check_at->diffForHumans() : 'Nunca' }}
                    </div>

                    <div class="d-flex gap-1.5 flex-wrap mt-auto pt-2 border-top border-subtle">
                        <button class="btn btn-sm btn-outline-primary rounded-2 px-2.5" data-sync="{{ $s->id }}" title="Sincronizar métricas agora">
                            <i class="bi bi-arrow-repeat me-1"></i> Sincronizar
                        </button>
                        <a href="{{ request()->is('painel*') ? route('painel.aapanel.servers.sites.index', $s) : route('admin.aapanel.servers.sites.index', $s) }}" class="btn btn-sm btn-outline-success rounded-2 px-2.5" title="Gerenciar sites hospedados neste servidor">
                            <i class="bi bi-globe me-1"></i> Sites
                        </a>
                        <button class="btn btn-sm btn-outline-secondary rounded-2 px-2" data-edit="{{ $s->id }}" data-name="{{ $s->name }}" data-host="{{ $s->host }}" data-port="{{ $s->port }}" data-ssl="{{ $s->use_ssl }}" data-entry="{{ $s->security_entry }}" data-api-key="{{ $s->api_key }}" title="Editar servidor">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger rounded-2 px-2 ms-auto" data-del="{{ $s->id }}" title="Excluir servidor">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>

                </div>
            </div>
        </div>
        @empty
        <div class="col-12">
            <div class="card shadow-sm border border-subtle py-5 text-center bg-body rounded-3">
                <div class="card-body text-muted py-5">
                    <div class="avatar-lg bg-primary-subtle text-primary rounded-circle mx-auto d-flex align-items-center justify-content-center mb-3" style="width: 70px; height: 70px; font-size: 2rem;">
                        <i class="bi bi-server"></i>
                    </div>
                    <h5 class="fw-bold text-body">Nenhum servidor aaPanel cadastrado</h5>
                    <p class="small text-secondary mb-4 max-w-md mx-auto">Cadastre o seu servidor aaPanel preenchendo o Host, Porta e Chave de API para gerenciar sites e sincronizar métricas em tempo real.</p>
                    <button type="button" class="btn btn-primary rounded-2" data-bs-toggle="modal" data-bs-target="#modalServer">
                        <i class="bi bi-plus-lg me-1"></i> Cadastrar Novo Servidor
                    </button>
                </div>
            </div>
        </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $servers->links() }}</div>
</div>

<!-- Modal Servidor -->
<div class="modal fade" id="modalServer" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border border-subtle shadow-lg rounded-3 bg-body">
            <form id="frm-srv">
                @csrf
                <input type="hidden" name="id" id="srv-id" value="">
                <div class="modal-header bg-body-tertiary border-bottom border-subtle py-3">
                    <h5 class="modal-title fw-bold text-body d-flex align-items-center gap-2">
                        <i class="bi bi-server text-primary"></i> Configurar Servidor aaPanel
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Nome do Servidor <span class="text-danger">*</span></label>
                            <input name="name" class="form-control" maxlength="120" required placeholder="Ex.: Servidor Principal - BR">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Host / IP <span class="text-danger">*</span></label>
                            <input name="host" class="form-control" required placeholder="192.168.1.100 ou painel.meudominio.com">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Porta API <span class="text-danger">*</span></label>
                            <input name="port" type="number" class="form-control" value="7800" min="1" max="65535" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Entrada de Segurança (Security Entry)</label>
                            <input name="security_entry" class="form-control" maxlength="60" placeholder="Ex.: a3c0960da">
                            <div class="form-text small">Caminho de segurança do aaPanel (ex: /a3c0960da/). Deixe vazio se não utilizar entrada de segurança.</div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">API Key <span class="text-danger">*</span></label>
                            <input name="api_key" type="password" class="form-control" required placeholder="Chave de API gerada no aaPanel">
                            <div class="form-text small">Obtenha em: aaPanel -> Settings -> API. Deixe vazio ao editar para manter a chave atual.</div>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch bg-body-tertiary p-2.5 rounded border border-subtle d-flex align-items-center">
                                <input class="form-check-input ms-0 me-2" type="checkbox" name="use_ssl" value="1" checked id="ssl-switch">
                                <label class="form-check-label fw-medium text-body mb-0" for="ssl-switch">
                                    <i class="bi bi-shield-check text-success me-1"></i> Usar Conexão SSL Segura (HTTPS)
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-body-tertiary border-top border-subtle py-3">
                    <button type="button" class="btn btn-outline-secondary rounded-2" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-outline-info rounded-2" id="btn-test-conn"><i class="bi bi-plug me-1"></i> Testar Conexão</button>
                    <button type="submit" class="btn btn-primary rounded-2 px-4"><i class="bi bi-check-lg me-1"></i> Salvar Servidor</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function showModal(el) {
    var inst = bootstrap.Modal.getInstance(el);
    if (!inst) { inst = new bootstrap.Modal(el); }
    inst.show();
}
function hideModal(el) {
    var inst = bootstrap.Modal.getInstance(el);
    if (inst) { inst.hide(); }
}

const csrf = document.querySelector('meta[name=csrf-token]').content;
const headers = { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' };
const baseUrl = "{{ request()->is('painel*') ? url('/painel/aapanel/servers') : url('/admin/aapanel/servers') }}";

function updateServerCard(srvId, data) {
    const wrapper = document.getElementById(`srv-${srvId}`);
    if (!wrapper) return;
    const card = wrapper.querySelector('.server-card');
    if (!card) return;

    // Atualiza borda e classe do card
    const isOnline = data.status === 'online';
    const isOffline = data.status === 'offline';
    card.className = `card shadow-sm h-100 border border-subtle server-card position-relative ${isOnline ? 'card-online' : (isOffline ? 'card-offline' : '')}`;

    const badgeStatus = card.querySelector('.badge-status');
    const statusLabel = card.querySelector('.status-label');
    if (badgeStatus) {
        badgeStatus.className = `badge bg-${isOnline ? 'success' : (isOffline ? 'danger' : 'secondary')} badge-status px-2.5 py-1.5 fw-semibold d-flex align-items-center gap-1`;
        if (statusLabel) statusLabel.textContent = isOnline ? 'Online' : (isOffline ? 'Offline' : 'Desconhecido');
    }

    const lastCheck = card.querySelector('.last-check-text');
    if (lastCheck) {
        lastCheck.innerHTML = `<i class="bi bi-check2-circle text-success me-1"></i> Checado: ${data.last_check_at || 'Agora'}`;
    }

    // Alerta de Erro
    const errorBox = card.querySelector('.error-alert-box');
    const errorMsgText = card.querySelector('.error-msg-text');
    if (errorBox) {
        if (data.last_error && isOffline) {
            errorBox.classList.remove('d-none');
            if (errorMsgText) errorMsgText.textContent = data.last_error;
        } else {
            errorBox.classList.add('d-none');
        }
    }

    if (data.metrics) {
        const m = data.metrics;
        const cpuPct = parseFloat(m.cpu_percent || 0);
        const memPct = parseFloat(m.mem_percent || 0);
        const diskPct = parseFloat(m.disk_percent || 0);

        const cpuVal = card.querySelector('.cpu-val');
        const cpuBar = card.querySelector('.cpu-bar');
        if (cpuVal) cpuVal.textContent = `${cpuPct}% (${m.cpu_cores || 1} Cores)`;
        if (cpuBar) {
            cpuBar.style.width = `${cpuPct}%`;
            cpuBar.className = `progress-bar bg-${cpuPct > 80 ? 'danger' : (cpuPct > 50 ? 'warning' : 'primary')} cpu-bar`;
        }

        const memVal = card.querySelector('.mem-val');
        const memBar = card.querySelector('.mem-bar');
        if (memVal) memVal.textContent = `${m.mem_used || '0 MB'} / ${m.mem_total || '0 MB'} (${memPct}%)`;
        if (memBar) {
            memBar.style.width = `${memPct}%`;
            memBar.className = `progress-bar bg-${memPct > 80 ? 'danger' : (memPct > 50 ? 'warning' : 'info')} mem-bar`;
        }

        const diskVal = card.querySelector('.disk-val');
        const diskBar = card.querySelector('.disk-bar');
        if (diskVal) diskVal.textContent = `${m.disk_used || '0 GB'} / ${m.disk_total || '0 GB'} (${diskPct}%)`;
        if (diskBar) {
            diskBar.style.width = `${diskPct}%`;
            diskBar.className = `progress-bar bg-${diskPct > 85 ? 'danger' : (diskPct > 60 ? 'warning' : 'success')} disk-bar`;
        }

        const sitesCount = card.querySelector('.count-sites');
        if (sitesCount) sitesCount.innerHTML = `<i class="bi bi-globe text-primary me-1"></i> <strong>${m.site_count || 0}</strong> Sites`;

        const dbsCount = card.querySelector('.count-dbs');
        if (dbsCount) dbsCount.innerHTML = `<i class="bi bi-database text-info me-1"></i> <strong>${m.database_count || 0}</strong> DBs`;

        const uptimeText = card.querySelector('.uptime-text');
        if (uptimeText) uptimeText.innerHTML = `<i class="bi bi-clock-history text-secondary me-1"></i> ${m.uptime || 'Ativo'}`;
    }
}

async function syncSingleServer(id, btn) {
    let orig = '';
    if (btn) {
        orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Sincronizando...';
    }
    try {
        const r = await fetch(`${baseUrl}/${id}/metrics`, { method: 'POST', credentials: 'same-origin', headers });
        const d = await r.json();
        if (d.ok) {
            updateServerCard(id, d);
            if (btn) window.toast.success(d.message || 'Métricas sincronizadas com sucesso!');
        } else {
            if (d.server) updateServerCard(id, d.server);
            if (btn) window.toast.error(d.message || 'Falha ao conectar no servidor.');
        }
    } catch {
        if (btn) window.toast.error('Erro de conexão ao sincronizar servidor.');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = orig;
        }
    }
}

async function syncAllServers(showNotification = true) {
    const syncBtn = document.getElementById('btn-sync-all');
    const syncIcon = document.getElementById('icon-sync-all');
    if (syncIcon) syncIcon.classList.add('spin-pulse');
    if (syncBtn) syncBtn.disabled = true;

    try {
        const r = await fetch(`${baseUrl}/sync-all`, { method: 'POST', credentials: 'same-origin', headers });
        const d = await r.json();
        if (d.ok && Array.isArray(d.servers)) {
            d.servers.forEach(srv => updateServerCard(srv.id, srv));
            if (showNotification) window.toast.success(d.message || 'Todos os servidores foram sincronizados!');
        } else if (showNotification) {
            window.toast.error(d.message || 'Erro ao sincronizar servidores.');
        }
    } catch {
        if (showNotification) window.toast.error('Erro de comunicação ao sincronizar em tempo real.');
    } finally {
        if (syncIcon) syncIcon.classList.remove('spin-pulse');
        if (syncBtn) syncBtn.disabled = false;
    }
}

// Auto-refresh interval (a cada 15 segundos)
let autoRefreshTimer = null;
function setupAutoRefresh() {
    const toggle = document.getElementById('auto-refresh-toggle');
    if (autoRefreshTimer) clearInterval(autoRefreshTimer);

    if (toggle && toggle.checked) {
        autoRefreshTimer = setInterval(() => {
            syncAllServers(false);
        }, 15000);
    }
}

document.getElementById('auto-refresh-toggle')?.addEventListener('change', setupAutoRefresh);
setupAutoRefresh();

document.getElementById('btn-sync-all')?.addEventListener('click', () => syncAllServers(true));

document.addEventListener('click', e => {
    const btnSync = e.target.closest('[data-sync]');
    if (btnSync) {
        syncSingleServer(btnSync.dataset.sync, btnSync);
    }
});

function submitForm(url, form, btn) {
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Salvando...';
    const fd = new FormData(form);
    if (!fd.has('use_ssl')) fd.set('use_ssl', '0');
    fetch(url, { method: 'POST', credentials: 'same-origin', headers, body: fd })
        .then(r => r.json())
        .then(async d => {
            if (d.ok) {
                window.toast.success(d.message);
                const id = document.getElementById('srv-id').value;
                if (!id && d.server_id) {
                    try {
                        await fetch(`${baseUrl}/${d.server_id}/metrics`, { method: 'POST', credentials: 'same-origin', headers });
                    } catch {}
                }
                setTimeout(() => location.reload(), 800);
            }
            else window.toast.error(d.message || 'Falha.');
        })
        .catch(() => window.toast.error('Erro de rede.'))
        .finally(() => { btn.disabled = false; btn.innerHTML = orig; });
}

document.getElementById('frm-srv')?.addEventListener('submit', e => {
    e.preventDefault();
    const id = document.getElementById('srv-id').value;
    const url = id ? `${baseUrl}/${id}` : baseUrl;
    if (id) {
        let methodInput = e.target.querySelector('input[name="_method"]');
        if (!methodInput) {
            methodInput = document.createElement('input');
            methodInput.type = 'hidden';
            methodInput.name = '_method';
            e.target.appendChild(methodInput);
        }
        methodInput.value = 'PUT';
    }
    submitForm(url, e.target, e.target.querySelector('button[type=submit]'));
});

document.querySelectorAll('[data-edit]').forEach(b => b.addEventListener('click', () => {
    const d = b.dataset;
    document.getElementById('srv-id').value = d.edit;
    const f = document.getElementById('frm-srv');
    f.querySelector('[name=name]').value = d.name;
    f.querySelector('[name=host]').value = d.host;
    f.querySelector('[name=port]').value = d.port;
    f.querySelector('[name=security_entry]').value = d.entry || '';
    f.querySelector('[name=use_ssl]').checked = d.ssl === '1';
    f.querySelector('[name=api_key]').value = d.apiKey || '';
    f.querySelector('[name=api_key]').required = false;
    showModal(document.getElementById('modalServer'));
}));

document.querySelectorAll('[data-del]').forEach(b => b.addEventListener('click', () => {
    Swal.fire({
        icon: 'warning', title: 'Remover servidor?',
        text: 'Esta ação não pode ser desfeita.',
        showCancelButton: true, confirmButtonText: 'Remover', cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545'
    }).then(async res => {
        if (!res.isConfirmed) return;
        try {
            const r = await fetch(`${baseUrl}/${b.dataset.del}`, { method: 'DELETE', credentials: 'same-origin', headers });
            const d = await r.json();
            d.ok ? window.toast.success('Servidor removido.') : window.toast.error(d.message || 'Falha.');
            if (d.ok) document.getElementById(`srv-${b.dataset.del}`)?.remove();
        } catch { window.toast.error('Erro de rede.'); }
    });
}));

document.getElementById('btn-test-conn')?.addEventListener('click', async () => {
    const btn = document.getElementById('btn-test-conn');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Testando...';

    const f = document.getElementById('frm-srv');
    const fd = new FormData(f);
    if (!fd.has('use_ssl')) fd.set('use_ssl', '0');

    try {
        const testUrl = "{{ request()->is('painel*') ? route('painel.aapanel.servers.test-connection') : route('admin.aapanel.servers.test-connection') }}";
        const r = await fetch(testUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers,
            body: fd
        });
        const d = await r.json();
        if (d.ok) {
            window.toast.success(d.message || 'Conexão OK! URL: ' + (d.url_tested || ''));
        } else {
            let msg = d.message || 'Falha na conexão.';
            if (d.url_tested) msg += ' URL: ' + d.url_tested;
            window.toast.error(msg);
        }
    } catch {
        window.toast.error('Erro de rede ao testar conexão.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = orig;
    }
});
</script>

<style>
.server-card {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    border-top: 4px solid #6c757d !important;
}
.server-card.card-online {
    border-top: 4px solid #198754 !important;
}
.server-card.card-offline {
    border-top: 4px solid #dc3545 !important;
}
.server-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.08) !important;
}
.pulse-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
    box-shadow: 0 0 0 0 rgba(25, 135, 84, 0.7);
    animation: pulse 1.6s infinite;
}
@keyframes pulse {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(25, 135, 84, 0.7); }
    70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(25, 135, 84, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(25, 135, 84, 0); }
}
.spin-pulse {
    animation: spin 1.2s linear infinite;
    display: inline-block;
}
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>
@endpush
