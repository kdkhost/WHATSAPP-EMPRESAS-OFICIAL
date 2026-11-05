@extends('admin.layouts.app')
@section('title', 'Sistema - Cron Jobs')

@php
    $heartbeatSec = (int) config('modules.cron.heartbeat_seconds', 60);
    $externalToken = \App\Services\CronManager::resolvedExternalToken();
    $cronUrl = url('/cron') . '?token=' . $externalToken;
    $lastCheckTs = (int) ($stats['last_check'] ?? 0);
    $nowTs = time();
    $diffSec = $lastCheckTs > 0 ? ($nowTs - $lastCheckTs) : 999999;
    if ($lastCheckTs === 0) {
        $cronStatus = ['label' => 'Nunca executado', 'color' => 'danger', 'icon' => 'bi-x-circle-fill'];
    } elseif ($diffSec <= 300) {
        $cronStatus = ['label' => 'Ativo e Operacional', 'color' => 'success', 'icon' => 'bi-check-circle-fill'];
    } elseif ($diffSec <= 900) {
        $cronStatus = ['label' => 'Operacional (< 15m)', 'color' => 'info', 'icon' => 'bi-clock-history'];
    } elseif ($diffSec <= 1800) {
        $cronStatus = ['label' => 'Atrasado (> 15m)', 'color' => 'warning', 'icon' => 'bi-exclamation-triangle-fill'];
    } else {
        $cronStatus = ['label' => 'Parado / Sem Sinal', 'color' => 'danger', 'icon' => 'bi-pause-circle-fill'];
    }
    $artisanPath = base_path('artisan');
    $phpBinary = file_exists('/opt/cpanel/ea-php84/root/usr/bin/php') ? '/opt/cpanel/ea-php84/root/usr/bin/php' : 'php';
    $cmdSchedule = "* * * * * {$phpBinary} {$artisanPath} schedule:run >> /dev/null 2>&1";
    $cmdCronRun  = "* * * * * {$phpBinary} {$artisanPath} cron:run >> /dev/null 2>&1";
    $cmdCurl     = "* * * * * curl -s \"{$cronUrl}\" >/dev/null 2>&1";
@endphp

@push('head')
<style>
.cron-page {
    --cron-surface-soft: color-mix(in srgb, var(--bs-body-bg) 92%, var(--bs-primary) 8%);
    --cron-border-soft: color-mix(in srgb, var(--bs-border-color) 75%, transparent);
}
.cron-kpi {
    height: 100%;
}
.cron-kpi .card-body {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
}
.cron-kpi__label {
    display: block;
    font-size: .74rem;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--bs-secondary-color);
    margin-bottom: .45rem;
}
.cron-kpi__value {
    display: block;
    font-size: 1.5rem;
    font-weight: 700;
    line-height: 1.05;
}
.cron-table thead th {
    background: var(--bs-tertiary-bg);
    color: var(--bs-secondary-color);
    font-size: .74rem;
    letter-spacing: .06em;
    vertical-align: middle;
}
.cron-table tbody td {
    vertical-align: middle;
}
.cron-filters {
    display: flex;
    align-items: center;
    gap: .75rem;
    flex-wrap: wrap;
}
.cron-side-list {
    display: grid;
    gap: .85rem;
}
.cron-side-item {
    padding: .9rem 1rem;
    border: 1px solid var(--cron-border-soft);
    border-radius: 14px;
    background: var(--cron-surface-soft);
}
.cron-empty {
    display: grid;
    place-items: center;
    min-height: 220px;
    text-align: center;
    color: var(--bs-secondary-color);
}
@media (max-width: 991.98px) {
    .cron-filters {
        width: 100%;
    }
    .cron-filters .btn-group,
    .cron-filters .position-relative,
    .cron-filters .form-control {
        width: 100%;
        max-width: none !important;
    }
}
</style>
@endpush

@section('content')
<div class="container-fluid cron-page">
    <section class="admin-premium-hero mb-4">
        <div>
            <span class="admin-premium-hero__eyebrow">Automacao interna</span>
            <h1 class="admin-premium-hero__title">Cron jobs com leitura mais clara de fila, saude e acionamento manual.</h1>
            <p class="admin-premium-hero__text">
                Esta tela agora separa monitoramento, filtros, heartbeat e acionamento externo sem misturar operacao cotidiana com detalhes tecnicos do agendador.
            </p>
        </div>
        <div class="dashboard-hero-notes">
            <div class="dashboard-note">
                <span class="dashboard-note__label">Status do Agendador</span>
                <span class="dashboard-note__value text-{{ $cronStatus['color'] }} d-flex align-items-center gap-1">
                    <i class="bi {{ $cronStatus['icon'] }}"></i> {{ $cronStatus['label'] }}
                </span>
            </div>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Heartbeat minimo</span>
                <span class="dashboard-note__value">{{ $heartbeatSec }}s</span>
            </div>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Total de jobs</span>
                <span class="dashboard-note__value">{{ $stats['total'] }}</span>
            </div>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Ultima verificacao</span>
                <span class="dashboard-note__value" id="label-last-check">{{ !empty($stats['last_check']) ? (is_numeric($stats['last_check']) ? \Carbon\Carbon::createFromTimestamp((int) $stats['last_check'])->diffForHumans() : \Carbon\Carbon::parse($stats['last_check'])->diffForHumans()) : 'Nunca' }}</span>
            </div>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Cron externo</span>
                <span class="dashboard-note__value text-success">
                    <i class="bi bi-shield-check me-1"></i> Token pronto
                </span>
            </div>
        </div>
    </section>

    <section class="premium-panel mb-4">
        <div class="premium-panel__header">
            <div>
                <span class="premium-panel__kicker">Operacao central</span>
                <h2 class="premium-panel__title">Monitoramento e execucao</h2>
                <p class="premium-panel__subtitle mb-0">Dispare o ciclo principal de cron jobs imediatamente ou force a rodada de jobs avulsos.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-outline-secondary" id="btn-refresh">
                    <i class="bi bi-arrow-clockwise me-1"></i> Atualizar
                </button>
                <button type="button" class="btn btn-success fw-bold text-white shadow-sm" id="btn-run-main">
                    <i class="bi bi-lightning-charge-fill me-1"></i> Executar Cron Principal
                </button>
                <button type="button" class="btn btn-outline-primary" id="btn-run-all">
                    <i class="bi bi-play-fill me-1"></i> Forcar todos
                </button>
            </div>
        </div>
    </section>

    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-3">
            <div class="card cron-kpi">
                <div class="card-body">
                    <div>
                        <span class="cron-kpi__label">Total de jobs</span>
                        <span class="cron-kpi__value" id="kpi-total">{{ $stats['total'] }}</span>
                    </div>
                    <div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-list-task"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card cron-kpi">
                <div class="card-body">
                    <div>
                        <span class="cron-kpi__label">Sucesso</span>
                        <span class="cron-kpi__value text-success" id="kpi-ok">{{ $stats['ok'] }}</span>
                    </div>
                    <div class="stat-icon bg-success-subtle text-success"><i class="bi bi-check-circle"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card cron-kpi">
                <div class="card-body">
                    <div>
                        <span class="cron-kpi__label">Com erro</span>
                        <span class="cron-kpi__value text-danger" id="kpi-errors">{{ $stats['errors'] }}</span>
                    </div>
                    <div class="stat-icon bg-danger-subtle text-danger"><i class="bi bi-exclamation-triangle"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card cron-kpi">
                <div class="card-body">
                    <div>
                        <span class="cron-kpi__label">Atrasados</span>
                        <span class="cron-kpi__value text-warning" id="kpi-overdue">{{ $stats['overdue'] }}</span>
                    </div>
                    <div class="stat-icon bg-warning-subtle text-warning"><i class="bi bi-hourglass-bottom"></i></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8 col-xl-9">
            <x-ui.premium-card title="Jobs Registrados" subtitle="Lista completa de tarefas, status mais recente e acionamento individual.">
                <x-slot:actions>
                    <div class="cron-filters">
                        <div class="btn-group btn-group-sm" role="group" id="filter-buttons">
                            <button type="button" class="btn btn-outline-secondary active" data-filter="all">Todos</button>
                            <button type="button" class="btn btn-outline-secondary" data-filter="ok">OK</button>
                            <button type="button" class="btn btn-outline-secondary" data-filter="error">Com erro</button>
                            <button type="button" class="btn btn-outline-secondary" data-filter="pending">Aguardando</button>
                        </div>
                    </div>
                </x-slot:actions>

                <div class="cron-table-wrapper p-0">
                    <table class="table table-bordered table-striped dt-responsive nowrap w-100 app-datatable" id="table-admin-system-cron" data-export-title="Jobs do Sistema Cron" style="width: 100% !important;">
                        <thead class="table-light">
                            <tr>
                                <th class="all" data-priority="1">Job</th>
                                <th class="min-tablet" data-priority="2">Intervalo</th>
                                <th class="desktop">Última Execução</th>
                                <th class="desktop">Duração</th>
                                <th class="min-tablet" data-priority="3">Status</th>
                                <th class="all text-end no-export col-actions" data-priority="1" style="width: 110px; min-width: 105px; max-width: 120px; white-space: nowrap;">Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($jobs as $name => $job)
                            @php
                                $s = $state['jobs'][$name] ?? [];
                                $statusKey = $s['last_status'] ?? null;
                                $lastRun = $s['last_run'] ?? null;
                                $duration = $s['duration_ms'] ?? null;
                                $error = $s['last_error'] ?? null;
                                $expectedNext = $lastRun ? ($lastRun + $job['interval'] * 60) : null;
                                $isOverdue = $statusKey === 'ok' && $expectedNext && ($expectedNext + 60 < time());
                                $filterCat = ! $statusKey ? 'pending' : $statusKey;
                            @endphp
                            <tr data-name="{{ $name }}" data-status="{{ $filterCat }}">
                                <td>
                                    <div><code class="fw-bold fs-6">{{ $name }}</code></div>
                                    @if(! empty($job['description']))
                                        <small class="text-muted d-block mt-1">{{ $job['description'] }}</small>
                                    @endif
                                    @if($error)
                                        <div class="mt-1 p-1 px-2 rounded bg-danger-subtle text-danger border border-danger-subtle small font-monospace" style="font-size: 0.72rem;">
                                            <i class="bi bi-bug me-1"></i>{{ $error }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-info-subtle text-info border border-info-subtle">
                                        <i class="bi bi-stopwatch me-1"></i> {{ $job['interval'] }} min
                                    </span>
                                </td>
                                <td>
                                    @if($lastRun)
                                        <div class="small fw-semibold">{{ is_numeric($lastRun) ? \Carbon\Carbon::createFromTimestamp((int) $lastRun)->format('d/m H:i:s') : \Carbon\Carbon::parse($lastRun)->format('d/m H:i:s') }}</div>
                                        <small class="text-muted">{{ is_numeric($lastRun) ? \Carbon\Carbon::createFromTimestamp((int) $lastRun)->diffForHumans() : \Carbon\Carbon::parse($lastRun)->diffForHumans() }}</small>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($duration !== null)
                                        <span class="badge bg-secondary-subtle text-secondary font-monospace">{{ $duration }}ms</span>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($statusKey === 'ok')
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                                            <i class="bi bi-check-circle me-1"></i> OK
                                        </span>
                                        @if($isOverdue)
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle mt-1 d-block">
                                                <i class="bi bi-hourglass-split me-1"></i> Atrasado
                                            </span>
                                        @endif
                                    @elseif($statusKey === 'error')
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle" data-bs-toggle="tooltip" title="{{ $error }}">
                                            <i class="bi bi-exclamation-triangle me-1"></i> Erro
                                        </span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                                            <i class="bi bi-hourglass me-1"></i> Aguardando
                                        </span>
                                    @endif
                                </td>
                                <td class="all text-end no-export col-actions" style="width: 110px; min-width: 105px; max-width: 120px; white-space: nowrap;">
                                    <div class="d-inline-flex align-items-center justify-content-end gap-1 flex-nowrap">
                                        <button class="btn btn-sm btn-outline-primary p-1 px-2" data-run-job="{{ $name }}" title="Executar job agora">
                                            <i class="bi bi-play-fill me-1"></i>Executar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    <i class="bi bi-list-task fs-1 d-block mb-2 opacity-50"></i>
                                    Nenhum job registrado.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ui.premium-card>
        </div>

        <div class="col-lg-4 col-xl-3">
            <div class="cron-side-list">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3 class="dashboard-panel__title mb-0">Heartbeat</h3>
                            <span class="dashboard-panel__subtitle">Ciclo automatico interno do sistema.</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-3">
                            <div class="display-6 fw-bold text-success">{{ $heartbeatSec }}s</div>
                            <small class="text-muted">Intervalo minimo entre verificacoes</small>
                        </div>
                        <div class="cron-side-item">
                            <div class="d-flex justify-content-between gap-2">
                                <span class="text-muted">Ultima verificacao</span>
                                <span class="fw-semibold">{{ !empty($stats['last_check']) ? (is_numeric($stats['last_check']) ? \Carbon\Carbon::createFromTimestamp((int) $stats['last_check'])->diffForHumans() : \Carbon\Carbon::parse($stats['last_check'])->diffForHumans()) : 'Nunca' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-header bg-light">
                        <div>
                            <h3 class="dashboard-panel__title mb-0">Agendador no Servidor (Cron externo / cPanel)</h3>
                            <span class="dashboard-panel__subtitle">Configure uma das opções abaixo no seu cPanel em "Cron Jobs / Tarefas Cron".</span>
                        </div>
                    </div>
                    <div class="card-body">
                        {{-- Opção 1: Schedule Laravel (Recomendado) --}}
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <strong class="small text-success"><i class="bi bi-star-fill me-1"></i> Opção 1: Laravel Schedule (Recomendado)</strong>
                                <span class="badge text-bg-success-subtle text-success">A cada 1 minuto</span>
                            </div>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control font-monospace small" id="cmd-schedule" value="{{ $cmdSchedule }}" readonly>
                                <button type="button" class="btn btn-outline-secondary" onclick="copyToClip('cmd-schedule', 'Comando Schedule copiado!')" title="Copiar comando">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                            <small class="text-muted d-block mt-1">Dispara o agendador padrão do Laravel que agora executa o cron principal continuamente.</small>
                        </div>

                        {{-- Opção 2: CLI Direto --}}
                        <div class="mb-3 pt-2 border-top">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <strong class="small text-primary"><i class="bi bi-terminal me-1"></i> Opção 2: CLI Direto (cron:run)</strong>
                                <span class="badge text-bg-primary-subtle text-primary">A cada 1 ou 5 min</span>
                            </div>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control font-monospace small" id="cmd-cronrun" value="{{ $cmdCronRun }}" readonly>
                                <button type="button" class="btn btn-outline-secondary" onclick="copyToClip('cmd-cronrun', 'Comando cron:run copiado!')" title="Copiar comando">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                        </div>

                        {{-- Opção 3: cURL Webhook --}}
                        <div class="pt-2 border-top">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <strong class="small text-secondary"><i class="bi bi-globe me-1"></i> Opção 3: Cron externo via URL / cURL (Webhook)</strong>
                                <span class="badge text-bg-secondary-subtle text-secondary">A cada 5 min</span>
                            </div>
                            <div class="input-group input-group-sm mb-1">
                                <input type="text" class="form-control font-monospace small" id="cron-url" value="{{ $cronUrl }}" readonly>
                                <button type="button" class="btn btn-outline-secondary" onclick="copyToClip('cron-url', 'URL do cron copiada!')" title="Copiar URL">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control font-monospace small" id="cmd-curl" value="{{ $cmdCurl }}" readonly>
                                <button type="button" class="btn btn-outline-secondary" onclick="copyToClip('cmd-curl', 'Comando cURL copiado!')" title="Copiar cURL">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-info-subtle">
                    <div class="card-body">
                        <h6 class="text-info"><i class="bi bi-info-circle me-1"></i> Como funciona</h6>
                        <ol class="small ps-3 mb-0">
                            <li>Cada modulo registra jobs com intervalo proprio.</li>
                            <li>O heartbeat verifica jobs vencidos apos cada requisicao.</li>
                            <li>O lock por arquivo evita execucao concorrente.</li>
                            <li>O estado fica salvo em <code>storage/app/cron-state.json</code>.</li>
                            <li>O historico persistido usa a tabela <code>cron_jobs</code>.</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const csrf = document.querySelector('meta[name=csrf-token]').content;

document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));

document.querySelectorAll('.badge.bg-danger').forEach(b => {
    b.style.cursor = 'pointer';
    b.addEventListener('click', () => {
        const tr = b.closest('tr');
        const next = tr?.nextElementSibling;
        if (next?.classList.contains('error-detail')) {
            next.style.display = next.style.display === 'none' ? '' : 'none';
        }
    });
});

async function runJob(btn, silent = false) {
    const name = btn.dataset.runJob;
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Executando...';
    try {
        const r = await fetch(`{{ url('/admin/system/cron') }}/${name}/run`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        });
        const data = await r.json().catch(() => ({}));
        if (r.ok && data.ok) {
            const result = data.result || {};
            const ok = result.last_status !== 'error';
            if (!silent) {
                if (ok) window.toast.success(`Job ${name} executado em ${result.duration_ms || 0}ms!`);
                else window.toast.error(`Job ${name} falhou: ${result.last_error || 'erro'}`);
            }
            return ok;
        }
        if (!silent) window.toast.error(`Falha ao executar ${name}.`);
        return false;
    } catch (err) {
        if (!silent) window.toast.error('Erro de rede.');
        return false;
    } finally {
        btn.disabled = false;
        btn.innerHTML = original;
    }
}

function copyToClip(elemId, msg) {
    const input = document.getElementById(elemId);
    if (!input) return;
    input.select();
    input.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(input.value).then(() => {
        if (window.toast) window.toast.success(msg || 'Copiado para a área de transferência!');
    }).catch(() => {
        document.execCommand('copy');
        if (window.toast) window.toast.success(msg || 'Copiado!');
    });
}

function copyCronUrl() {
    copyToClip('cron-url', 'URL do cron copiada!');
}

document.getElementById('btn-run-main')?.addEventListener('click', async () => {
    const btn = document.getElementById('btn-run-main');
    const original = btn.innerHTML;

    const confirm = await Swal.fire({
        icon: 'question',
        title: 'Executar Cron Principal?',
        text: 'O sistema executará todos os jobs que atingiram seu tempo de intervalo no ciclo oficial.',
        showCancelButton: true,
        confirmButtonText: 'Executar Agora',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#198754'
    });
    if (!confirm.isConfirmed) return;

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Executando ciclo...';
    if (window.toast) window.toast.info('Ciclo principal do cron iniciado...');

    try {
        const r = await fetch(`{{ route('admin.system.cron.run-main') }}`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ force: false })
        });
        const data = await r.json().catch(() => ({}));
        if (r.ok && data.ok) {
            const report = data.report || {};
            const executedCount = Object.keys(report.executed || {}).length;
            const ok = report.ok || 0;
            const errors = report.errors || 0;

            await Swal.fire({
                icon: errors === 0 ? 'success' : 'warning',
                title: 'Ciclo do Cron Concluído!',
                html: `<strong>${executedCount}</strong> jobs processados neste ciclo.<br>` +
                      (errors > 0 ? `<span class="text-danger">${errors} job(s) reportaram erro.</span>` : '<span class="text-success">Todos os jobs do ciclo executaram com sucesso.</span>'),
                confirmButtonText: 'OK'
            });
            location.reload();
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Falha na Execução',
                text: data.message || 'O agendador retornou uma resposta inesperada.'
            });
        }
    } catch (err) {
        Swal.fire({
            icon: 'error',
            title: 'Erro de Comunicação',
            text: 'Não foi possível contatar o agendador de tarefas.'
        });
    } finally {
        btn.disabled = false;
        btn.innerHTML = original;
    }
});

document.querySelectorAll('[data-run-job]').forEach(btn => {
    btn.addEventListener('click', async () => {
        await runJob(btn);
        setTimeout(() => location.reload(), 800);
    });
});

document.getElementById('btn-run-all').addEventListener('click', async () => {
    const ok = await Swal.fire({
        icon: 'question',
        title: 'Executar todos os jobs?',
        text: 'Isso forcara a execucao imediata de todos os jobs registrados, ignorando intervalos.',
        showCancelButton: true,
        confirmButtonText: 'Executar todos',
        cancelButtonText: 'Cancelar'
    });
    if (!ok.isConfirmed) return;
    const btns = document.querySelectorAll('[data-run-job]');
    if (!btns.length) return;
    window.toast.info(`Executando ${btns.length} jobs...`);
    let success = 0;
    let failed = 0;
    for (const btn of btns) {
        const r = await runJob(btn, true);
        r ? success++ : failed++;
    }
    if (failed === 0) window.toast.success(`Todos os ${success} jobs executados com sucesso!`);
    else window.toast.error(`${success} sucesso, ${failed} falha(s).`);
    setTimeout(() => location.reload(), 1200);
});

document.getElementById('btn-refresh').addEventListener('click', () => location.reload());

setInterval(() => {
    if (!document.hidden) location.reload();
}, 30000);

let currentFilter = 'all';
function getCronDT() {
    if (window.jQuery && $.fn.DataTable && $.fn.DataTable.isDataTable('#table-admin-system-cron')) {
        return $('#table-admin-system-cron').DataTable();
    }
    return null;
}

document.querySelectorAll('#filter-buttons [data-filter]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('#filter-buttons [data-filter]').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        currentFilter = btn.dataset.filter;
        const dt = getCronDT();
        if (dt) {
            if (currentFilter === 'all') {
                dt.column(4).search('').draw();
            } else if (currentFilter === 'ok') {
                dt.column(4).search('OK').draw();
            } else if (currentFilter === 'error') {
                dt.column(4).search('Erro').draw();
            } else if (currentFilter === 'pending') {
                dt.column(4).search('Aguardando').draw();
            }
        }
    });
});
</script>
@endpush
