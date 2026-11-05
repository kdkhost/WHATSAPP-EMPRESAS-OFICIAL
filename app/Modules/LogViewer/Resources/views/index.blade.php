@extends('admin.layouts.app')
@section('title', 'Logs do Sistema')

@php
    $totalSize = collect($files)->sum('size_kb');
    $stats = [
        'files' => count($files),
        'size' => $totalSize,
    ];
@endphp

@push('head')
<style>
.log-console {
    max-height: 65vh;
    overflow-y: auto;
    background: var(--admin-dark-bg);
    color: var(--bs-light);
}
.log-entry {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid color-mix(in srgb, var(--bs-light) 10%, transparent);
    border-left: 4px solid var(--bs-secondary);
}
.log-entry:hover {
    background: color-mix(in srgb, var(--bs-light) 5%, transparent);
}
.log-entry pre {
    margin: .5rem 0 0;
    white-space: pre-wrap;
    word-break: break-word;
    font-size: .85rem;
    color: color-mix(in srgb, var(--bs-light) 90%, transparent);
    line-height: 1.6;
}
.log-entry.entry-emergency,
.log-entry.entry-alert,
.log-entry.entry-critical,
.log-entry.entry-error { border-left-color: var(--bs-danger); background: rgba(var(--bs-danger-rgb), .08); }
.log-entry.entry-warning { border-left-color: var(--bs-warning); background: rgba(var(--bs-warning-rgb), .08); }
.log-entry.entry-notice,
.log-entry.entry-info { border-left-color: var(--bs-info); background: rgba(var(--bs-info-rgb), .08); }
.log-entry.entry-debug { border-left-color: var(--bs-secondary); }
.log-console-empty { color: color-mix(in srgb, var(--bs-light) 62%, transparent); }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <x-ui.page-header
        title="Logs do Sistema"
        subtitle="Monitore logs da aplicação com filtros por arquivo, nível, busca textual, auto-refresh e ações seguras de manutenção."
        eyebrow="Observabilidade"
        class="mb-4"
    >
        <x-slot:actions>
            <div class="d-flex gap-2 align-items-center flex-wrap justify-content-end">
                <button type="button" class="btn btn-outline-primary btn-sm" id="btn-refresh" title="Atualizar">
                    <i class="bi bi-arrow-clockwise me-1"></i> Atualizar
                </button>
                <button type="button" class="btn btn-outline-success btn-sm" id="btn-toggle-auto" title="Auto-refresh">
                    <i class="bi bi-play-circle me-1"></i> Auto-refresh
                </button>
                <button type="button" class="btn btn-outline-warning btn-sm" id="btn-check-permissions" title="Verificar permissões">
                    <i class="bi bi-shield-check me-1"></i> Verificar
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm" id="btn-clear">
                    <i class="bi bi-trash me-1"></i> Limpar Log
                </button>
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="row g-3 mb-4">
        <div class="col-md-6 col-lg-3">
            <x-ui.stats-card label="Arquivos de log" :value="$stats['files']" icon="bi-files" color="primary" class="h-100" />
        </div>
        <div class="col-md-6 col-lg-3">
            <x-ui.stats-card label="Tamanho total" :value="number_format($stats['size'] / 1024, 2, ',', '.') . ' MB'" icon="bi-hdd" color="info" class="h-100" />
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="admin-premium-metric h-100">
                <div class="d-flex align-items-center gap-3">
                    <span class="stat-icon bg-danger-subtle text-danger"><i class="bi bi-exclamation-triangle"></i></span>
                    <div class="flex-grow-1 min-w-0">
                        <span class="dashboard-note__label">Erros no arquivo</span>
                        <strong class="dashboard-note__value d-block" id="kpi-errors">-</strong>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="admin-premium-metric h-100">
                <div class="d-flex align-items-center gap-3">
                    <span class="stat-icon bg-success-subtle text-success"><i class="bi bi-eye"></i></span>
                    <div class="flex-grow-1 min-w-0">
                        <span class="dashboard-note__label">Entradas exibidas</span>
                        <strong class="dashboard-note__value d-block" id="kpi-entries">0</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <x-ui.premium-card title="Filtros" subtitle="Escolha o arquivo, nível e termo para refinar a visualização." class="mb-4">
        <div class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label">Arquivo de log</label>
                <select id="log-file" class="form-select">
                    @foreach($files as $f)
                        <option value="{{ $f['name'] }}">
                            {{ $f['name'] }} ({{ number_format($f['size_kb'], 1, ',', '.') }} KB - {{ $f['mtime'] }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Nível</label>
                <select id="log-level" class="form-select">
                    <option value="">Todos os níveis</option>
                    <option value="emergency">Emergency</option>
                    <option value="critical">Critical</option>
                    <option value="error">Error</option>
                    <option value="warning">Warning</option>
                    <option value="notice">Notice</option>
                    <option value="info">Info</option>
                    <option value="debug">Debug</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Buscar no conteúdo</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search text-muted"></i></span>
                    <input type="search" id="log-search" class="form-control" placeholder="Digite para buscar...">
                </div>
            </div>
        </div>
    </x-ui.premium-card>

    <x-ui.premium-card title="Conteúdo do log" subtitle="Visualização segura do arquivo selecionado.">
        <x-slot:header>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle" id="log-status">
                    <i class="bi bi-hourglass-split me-1"></i> Carregando...
                </span>
                <button class="btn btn-sm btn-outline-secondary" id="btn-copy-logs" title="Copiar logs">
                    <i class="bi bi-clipboard"></i>
                </button>
            </div>
        </x-slot:header>
        <div id="log-output" class="log-console rounded-bottom">
            <div class="text-center log-console-empty py-5">
                <div class="spinner-border spinner-border-sm me-2"></div>
                <span>Carregando logs...</span>
            </div>
        </div>
        <x-slot:footer>
            <small class="text-muted">
                <i class="bi bi-info-circle me-1"></i>
                Use filtros para refinar a visualização. O auto-refresh atualiza a cada 5 segundos.
            </small>
        </x-slot:footer>
    </x-ui.premium-card>
</div>
@endsection

@push('scripts')
<script>
const csrf = document.querySelector('meta[name=csrf-token]').content;
const colors = {
    emergency: 'danger',
    alert: 'danger',
    critical: 'danger',
    error: 'danger',
    warning: 'warning',
    notice: 'info',
    info: 'info',
    debug: 'secondary'
};

const levelLabels = {
    emergency: 'Emergency',
    alert: 'Alert',
    critical: 'Critical',
    error: 'Error',
    warning: 'Warning',
    notice: 'Notice',
    info: 'Info',
    debug: 'Debug'
};

let allEntries = [];
let autoTimer = null;

async function loadLog() {
    const file = document.getElementById('log-file').value;
    const level = document.getElementById('log-level').value;
    const status = document.getElementById('log-status');
    status.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Carregando...';

    try {
        const url = `{{ route('admin.logs.tail') }}?file=${encodeURIComponent(file)}${level ? '&level=' + level : ''}`;
        const response = await fetch(url, { credentials: 'same-origin' });
        const data = await response.json();
        const out = document.getElementById('log-output');

        if (!data.ok) {
            out.innerHTML = `
                <div class="text-center text-danger p-5">
                    <i class="bi bi-exclamation-triangle fs-1 d-block mb-3"></i>
                    <h5>${data.error || 'Erro ao carregar log'}</h5>
                </div>`;
            status.innerHTML = '<i class="bi bi-x-circle me-1"></i> Erro';
            return;
        }

        allEntries = data.entries || [];
        renderEntries();

        const fileSize = (data.size / 1024).toFixed(2);
        status.innerHTML = `<i class="bi bi-check-circle me-1"></i> ${allEntries.length} entrada(s) - ${fileSize} KB`;

        const errs = allEntries.filter(entry => ['error', 'critical', 'emergency', 'alert'].includes(entry.level)).length;
        document.getElementById('kpi-errors').textContent = errs;
        out.scrollTop = out.scrollHeight;
    } catch (err) {
        document.getElementById('log-output').innerHTML = `
            <div class="text-center text-danger p-5">
                <i class="bi bi-wifi-off fs-1 d-block mb-3"></i>
                <h5>Erro de conexão</h5>
                <p class="log-console-empty">Verifique sua conexão e tente novamente.</p>
            </div>`;
        status.innerHTML = '<i class="bi bi-x-circle me-1"></i> Erro de rede';
    }
}

function renderEntries() {
    const q = (document.getElementById('log-search').value || '').toLowerCase().trim();
    const out = document.getElementById('log-output');
    const filtered = q ? allEntries.filter(entry => (entry.message || '').toLowerCase().includes(q)) : allEntries;

    document.getElementById('kpi-entries').textContent = filtered.length;

    if (!filtered.length) {
        out.innerHTML = `
            <div class="text-center log-console-empty py-5">
                <i class="bi bi-search fs-1 d-block mb-3"></i>
                <h5>Nenhuma entrada encontrada</h5>
                <p>Tente ajustar os filtros ou buscar por outro termo.</p>
            </div>`;
        return;
    }

    out.innerHTML = filtered.map(entry => `
        <div class="log-entry entry-${entry.level}">
            <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                <span class="badge bg-${colors[entry.level] || 'secondary'}">${levelLabels[entry.level] || entry.level.toUpperCase()}</span>
                <span class="log-console-empty small">
                    <i class="bi bi-clock me-1"></i>${entry.timestamp}
                </span>
            </div>
            <pre class="mb-0">${escapeHtml(entry.message)}</pre>
        </div>
    `).join('');
}

function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    }[char]));
}

document.getElementById('log-file').addEventListener('change', loadLog);
document.getElementById('log-level').addEventListener('change', loadLog);
document.getElementById('btn-refresh').addEventListener('click', () => {
    loadLog();
    window.toast?.success('Logs atualizados.');
});

document.getElementById('btn-check-permissions').addEventListener('click', async () => {
    const file = document.getElementById('log-file').value;

    try {
        const response = await fetch(`{{ route('admin.logs.check-permissions') }}?file=${encodeURIComponent(file)}`, {
            credentials: 'same-origin'
        });
        const data = await response.json();

        if (data.ok && data.info) {
            const info = data.info;
            const statusText = info.writable ? 'Escrita permitida' : 'Sem permissão de escrita';
            const statusClass = info.writable ? 'success' : 'danger';

            await Swal.fire({
                icon: info.writable ? 'success' : 'warning',
                title: 'Informações do arquivo',
                html: `
                    <div class="text-start">
                        <table class="table table-sm">
                            <tr><th>Arquivo:</th><td><code>${info.file}</code></td></tr>
                            <tr><th>Tamanho:</th><td>${(info.size / 1024).toFixed(2)} KB</td></tr>
                            <tr><th>Permissões:</th><td><code>${info.permissions}</code></td></tr>
                            <tr><th>Proprietário:</th><td>${info.owner}</td></tr>
                            <tr><th>Leitura:</th><td>${info.readable ? 'Sim' : 'Não'}</td></tr>
                            <tr><th>Escrita:</th><td class="text-${statusClass}"><strong>${statusText}</strong></td></tr>
                        </table>
                        ${!info.writable ? `
                            <div class="alert alert-danger mt-3">
                                <strong>Ação necessária:</strong><br>
                                Execute no servidor:<br>
                                <code class="d-block mt-2 p-2 bg-dark text-white rounded">chmod 666 ${info.path}</code>
                            </div>
                        ` : ''}
                    </div>
                `,
                width: 600,
                confirmButtonText: 'Fechar'
            });
            return;
        }

        throw new Error(data.error || 'Erro ao verificar permissões');
    } catch (err) {
        Swal.fire({ icon: 'error', title: 'Erro', text: err.message });
    }
});

let searchTimeout;
document.getElementById('log-search').addEventListener('input', () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(renderEntries, 300);
});

document.getElementById('btn-toggle-auto').addEventListener('click', () => {
    const btn = document.getElementById('btn-toggle-auto');
    if (autoTimer) {
        clearInterval(autoTimer);
        autoTimer = null;
        btn.innerHTML = '<i class="bi bi-play-circle me-1"></i> Auto-refresh';
        btn.classList.remove('btn-warning');
        btn.classList.add('btn-outline-success');
        window.toast?.info('Auto-refresh desativado.');
        return;
    }

    autoTimer = setInterval(loadLog, 5000);
    btn.innerHTML = '<i class="bi bi-pause-circle me-1"></i> Pausar';
    btn.classList.remove('btn-outline-success');
    btn.classList.add('btn-warning');
    window.toast?.success('Auto-refresh ativo a cada 5 segundos.');
});

document.getElementById('btn-copy-logs').addEventListener('click', () => {
    const text = allEntries.map(entry => `[${entry.timestamp}] ${entry.level.toUpperCase()}: ${entry.message}`).join('\n');
    navigator.clipboard.writeText(text).then(() => {
        window.toast?.success('Logs copiados para a área de transferência.');
    }).catch(() => {
        window.toast?.error('Erro ao copiar logs.');
    });
});

document.getElementById('btn-clear').addEventListener('click', async () => {
    const file = document.getElementById('log-file').value;

    const result = await Swal.fire({
        icon: 'warning',
        title: 'Limpar este log?',
        html: `<p>O arquivo <strong>${file}</strong> será completamente limpo.</p><p class="text-danger">Esta ação não pode ser desfeita.</p>`,
        showCancelButton: true,
        confirmButtonText: 'Sim, limpar agora',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        showLoaderOnConfirm: true,
        preConfirm: async () => {
            const fd = new FormData();
            fd.append('file', file);

            try {
                const response = await fetch('{{ route("admin.logs.clear") }}', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json'
                    },
                    body: fd,
                });

                const data = await response.json();

                if (!response.ok || !data.ok) {
                    throw new Error(data.error || 'Erro ao limpar log');
                }

                return data;
            } catch (err) {
                Swal.showValidationMessage(`Erro: ${err.message}`);
            }
        },
        allowOutsideClick: () => !Swal.isLoading()
    });

    if (result.isConfirmed) {
        await Swal.fire({
            icon: 'success',
            title: 'Log limpo',
            text: 'O arquivo foi completamente limpo.',
            timer: 2000,
            showConfirmButton: false
        });

        await loadLog();
        document.getElementById('kpi-errors').textContent = '0';
        document.getElementById('kpi-entries').textContent = '0';
    }
});

loadLog();

window.addEventListener('beforeunload', () => {
    if (autoTimer) clearInterval(autoTimer);
});
</script>
@endpush
