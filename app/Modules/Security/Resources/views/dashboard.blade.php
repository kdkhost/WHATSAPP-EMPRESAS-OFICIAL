@extends('admin.layouts.app')
@section('title', 'WAF - Painel de Seguranca')
@section('content')
<div class="container-fluid">

    <x-ui.page-header
        title="Segurança do sistema"
        subtitle="Painel de segurança com modo detectar/bloquear, regras configuráveis, rate limit, blocklist, allowlist e auditoria para superadmin."
        eyebrow="WAF Enterprise"
        class="mb-4"
    >
        <x-slot:actions>
        <div class="d-flex gap-2 align-items-center flex-wrap justify-content-end">
            <select id="period-days" class="form-select form-select-sm" style="width: 160px;">
                <option value="1">Últimas 24h</option>
                <option value="7" selected>Últimos 7 dias</option>
                <option value="14">Últimos 14 dias</option>
                <option value="30">Últimos 30 dias</option>
            </select>
            <a href="{{ route('admin.security.events') }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-list-ul me-1"></i> Eventos
            </a>
            <a href="{{ route('admin.security.blocklist') }}" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-slash-circle me-1"></i> Blocklist
                <span class="badge bg-danger ms-1">{{ number_format($stats['blocklist_size'], 0, ',', '.') }}</span>
            </a>
            <a href="{{ route('admin.security.allowlist') }}" class="btn btn-outline-success btn-sm">
                <i class="bi bi-check-circle me-1"></i> Allowlist
                <span class="badge bg-success ms-1">{{ number_format($stats['allowlist_size'], 0, ',', '.') }}</span>
            </a>
        </div>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- ========== KPIs ========== --}}
    <div class="row g-3 mb-4">
        @php
            $cards = [
                ['Total de eventos',  $stats['events_total'],    'primary',   'bi-activity'],
                ['Bloqueios',         $stats['events_blocked'],  'danger',    'bi-shield-fill-x'],
                ['Criticos',          $stats['events_critical'], 'warning',   'bi-exclamation-octagon'],
                ['IPs unicos',        $stats['unique_ips'],      'info',      'bi-geo-alt'],
            ];
        @endphp
        @foreach($cards as [$label, $value, $color, $icon])
        <div class="col-md-6 col-lg-3">
            <x-ui.stats-card :label="$label" :value="number_format($value, 0, ',', '.')" :color="$color" :icon="$icon" class="h-100" />
        </div>
        @endforeach
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="premium-panel h-100">
                <div class="premium-panel__header">
                    <div>
                        <h2 class="premium-panel__title">Controles globais do WAF</h2>
                        <p class="premium-panel__subtitle">Ative, desative e ajuste o comportamento sem editar arquivo no servidor.</p>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="btn-waf-save">
                        <i class="bi bi-check2-circle me-1"></i> Salvar controles
                    </button>
                </div>
                <div class="premium-panel__body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Modo operacional</label>
                            <select class="form-select" data-waf-setting="security.waf_mode">
                                <option value="off">Desligado</option>
                                <option value="detect">Detectar e registrar</option>
                                <option value="block">Bloquear ameacas</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Limite por IP</label>
                            <input type="number" min="10" max="2000" class="form-control" data-waf-setting="security.waf_rate_limit_max">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Janela do limite</label>
                            <div class="input-group">
                                <input type="number" min="10" max="3600" class="form-control" data-waf-setting="security.waf_rate_limit_window">
                                <span class="input-group-text">seg</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="switch-card">
                                <input type="checkbox" data-waf-setting="security.waf_enabled">
                                <span><strong>WAF ativo</strong><small>Controle geral de protecao</small></span>
                            </label>
                        </div>
                        <div class="col-md-4">
                            <label class="switch-card">
                                <input type="checkbox" data-waf-setting="security.waf_admin_enabled">
                                <span><strong>Proteger admin</strong><small>Inspeciona rotas /admin</small></span>
                            </label>
                        </div>
                        <div class="col-md-4">
                            <label class="switch-card">
                                <input type="checkbox" data-waf-setting="security.waf_rate_limit_enabled">
                                <span><strong>Rate limit</strong><small>Bloqueio por excesso de requisicoes</small></span>
                            </label>
                        </div>
                        <div class="col-md-4">
                            <label class="switch-card">
                                <input type="checkbox" data-waf-setting="security.waf_auto_block_enabled">
                                <span><strong>Auto block</strong><small>Adiciona IP suspeito na blocklist</small></span>
                            </label>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Tempo de auto block</label>
                            <div class="input-group">
                                <input type="number" min="1" max="10080" class="form-control" data-waf-setting="security.waf_auto_block_minutes">
                                <span class="input-group-text">min</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="switch-card">
                                <input type="checkbox" data-waf-setting="security.waf_block_empty_user_agent">
                                <span><strong>User-Agent vazio</strong><small>Bloqueia requests sem identificacao</small></span>
                            </label>
                        </div>
                    </div>
                    <div class="d-flex gap-2 flex-wrap mt-3">
                        <a href="{{ route('admin.security.rules') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-sliders me-1"></i> Regras</a>
                        <a href="{{ route('admin.security.audit') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-journal-text me-1"></i> Auditoria</a>
                        <a href="{{ route('admin.security.login-attempts') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-key me-1"></i> Tentativas de login</a>
                        <button type="button" class="btn btn-outline-warning btn-sm" id="btn-waf-cache"><i class="bi bi-arrow-clockwise me-1"></i> Limpar cache WAF</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="premium-panel h-100">
                <div class="premium-panel__header">
                    <div>
                        <h2 class="premium-panel__title">Política recomendada</h2>
                        <p class="premium-panel__subtitle">Use detecção antes de bloquear em produção.</p>
                    </div>
                </div>
                <div class="premium-panel__body">
                    <div class="policy-step"><span>1</span><strong>Detectar</strong><small>Registre eventos sem interromper clientes.</small></div>
                    <div class="policy-step"><span>2</span><strong>Ajustar regras</strong><small>Revise falsos positivos em eventos e auditoria.</small></div>
                    <div class="policy-step"><span>3</span><strong>Bloquear</strong><small>Ative block com rate limit e auto block calibrados.</small></div>
                </div>
            </div>
        </div>
    </div>

    {{-- ========== GRAFICOS PRINCIPAIS ========== --}}
    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="card shadow-sm h-100">
                <div class="card-header">
                    <strong><i class="bi bi-graph-up me-1 text-primary"></i> Eventos por dia</strong>
                    <small class="text-muted">Total vs Bloqueados</small>
                </div>
                <div class="card-body">
                    <div class="chart-wrap" style="height: 280px;">
                        <canvas id="chart-by-day"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-header">
                    <strong><i class="bi bi-pie-chart me-1 text-info"></i> Por regra</strong>
                </div>
                <div class="card-body">
                    <div class="chart-wrap" style="height: 280px;">
                        <canvas id="chart-by-rule"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="card shadow-sm h-100">
                <div class="card-header">
                    <strong><i class="bi bi-clock me-1 text-warning"></i> Por hora (ultimas 24h)</strong>
                </div>
                <div class="card-body">
                    <div class="chart-wrap" style="height: 240px;">
                        <canvas id="chart-by-hour"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-header">
                    <strong><i class="bi bi-bar-chart me-1 text-danger"></i> Por severidade</strong>
                </div>
                <div class="card-body">
                    <div class="chart-wrap" style="height: 240px;">
                        <canvas id="chart-by-severity"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ========== TOP 10 ========== --}}
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header">
                    <strong><i class="bi bi-trophy me-1 text-warning"></i> Top 10 regras acionadas</strong>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover m-0 align-middle">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">#</th>
                                    <th>Regra</th>
                                    <th class="text-end">Eventos</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($topRules as $idx => $r)
                                <tr>
                                    <td><span class="text-muted small">#{{ $idx + 1 }}</span></td>
                                    <td><code class="fw-semibold">{{ $r->rule_code }}</code></td>
                                    <td class="text-end">
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">{{ number_format($r->total, 0, ',', '.') }}</span>
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="3" class="text-muted text-center py-4">Sem eventos no período</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header">
                    <strong><i class="bi bi-geo-alt me-1 text-danger"></i> Top 10 IPs</strong>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover m-0 align-middle">
                            <thead>
                                <tr>
                                    <th>IP</th>
                                    <th class="text-end">Eventos</th>
                                    <th class="text-end" style="width: 130px;">Ação</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($topIps as $r)
                                <tr>
                                    <td><code class="fw-semibold">{{ $r->ip }}</code></td>
                                    <td class="text-end">
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle">{{ number_format($r->total, 0, ',', '.') }}</span>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-danger" data-block-ip="{{ $r->ip }}">
                                            <i class="bi bi-shield-fill-x me-1"></i> Bloquear
                                        </button>
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="3" class="text-muted text-center py-4">Sem eventos no período</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('head')
<style>
.switch-card { display: flex; gap: 12px; align-items: flex-start; padding: 14px; border: 1px solid rgba(148,163,184,.24); border-radius: 14px; background: rgba(148,163,184,.06); min-height: 82px; cursor: pointer; }
.switch-card input { width: 2.4rem; height: 1.25rem; margin-top: 2px; accent-color: var(--bs-primary); }
.switch-card span { display: grid; gap: 2px; }
.switch-card small, .policy-step small { color: var(--bs-secondary-color); }
.policy-step { display: grid; grid-template-columns: 34px 1fr; gap: 2px 12px; padding: 14px 0; border-bottom: 1px solid rgba(148,163,184,.18); }
.policy-step:last-child { border-bottom: 0; }
.policy-step span { grid-row: span 2; width: 34px; height: 34px; display: inline-grid; place-items: center; border-radius: 999px; background: color-mix(in srgb, var(--bs-danger) 14%, transparent); color: var(--bs-danger); font-weight: 800; }
.chart-wrap { position: relative; }
.chart-wrap > canvas { width: 100% !important; height: 100% !important; }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js"></script>
<script>
(function () {
    const charts = {};
    const isDark = document.documentElement.dataset.bsTheme === 'dark';
    const gridColor = isDark ? 'rgba(255,255,255,.1)' : 'rgba(0,0,0,.06)';
    const tickColor = isDark ? 'rgba(255,255,255,.7)' : 'rgba(0,0,0,.7)';

    Chart.defaults.color = tickColor;
    Chart.defaults.borderColor = gridColor;

    function colors(n) {
        const palette = ['#0d6efd','#dc3545','#fd7e14','#ffc107','#198754','#20c997','#6f42c1','#d63384','#0dcaf0','#6c757d'];
        return Array.from({length: n}, (_, i) => palette[i % palette.length]);
    }

    function isTruthy(value) {
        return value === true || value === 1 || value === '1' || value === 'true';
    }

    async function loadWafSettings() {
        try {
            const r = await fetch(`{{ route('admin.security.waf.settings') }}`, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
            const data = await r.json();
            const settings = data.settings || {};
            document.querySelectorAll('[data-waf-setting]').forEach((input) => {
                const value = settings[input.dataset.wafSetting];
                if (input.type === 'checkbox') input.checked = isTruthy(value);
                else input.value = value ?? '';
            });
        } catch (err) {
            window.toast.error('Erro ao carregar configuracoes do WAF.');
        }
    }

    async function saveWafSettings() {
        const payload = {};
        document.querySelectorAll('[data-waf-setting]').forEach((input) => {
            payload[input.dataset.wafSetting] = input.type === 'checkbox' ? input.checked : input.value;
        });
        // Remover objeto 'security' aninhado se existir (duplicacao errada)
        delete payload.security;

        const btn = document.getElementById('btn-waf-save');
        const original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Salvando...';
        try {
            const r = await fetch(`{{ route('admin.security.waf.settings.update') }}`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            });
            const data = await r.json();
            if (r.ok && data.ok) window.toast.success(data.message || 'WAF atualizado.');
            else window.toast.error(data.message || 'Erro ao salvar WAF.');
        } catch (err) {
            window.toast.error('Erro de rede ao salvar WAF.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = original;
        }
    }

    async function loadCharts(days) {
        try {
            const r = await fetch(`{{ route('admin.security.charts') }}?days=${days}`, { credentials: 'same-origin' });
            const data = await r.json();

            const labelsDay = data.by_day.map(x => x.d);
            renderLine('chart-by-day', labelsDay, [
                { label: 'Total',      data: data.by_day.map(x => x.total),   borderColor: '#0d6efd', backgroundColor: 'rgba(13,110,253,.15)', fill: true, tension: .35 },
                { label: 'Bloqueados', data: data.by_day.map(x => x.blocked), borderColor: '#dc3545', backgroundColor: 'rgba(220,53,69,.15)', fill: true, tension: .35 },
            ]);

            const hoursLabels = Array.from({length: 24}, (_, i) => i.toString().padStart(2, '0') + 'h');
            const hoursData = Array(24).fill(0);
            data.by_hour.forEach(x => { hoursData[x.h] = x.total; });
            renderBar('chart-by-hour', hoursLabels, [{ label: 'Eventos', data: hoursData, backgroundColor: '#0dcaf0' }]);

            renderDoughnut('chart-by-rule', data.by_rule.map(x => x.rule_code), data.by_rule.map(x => x.total));
            renderDoughnut('chart-by-severity', data.by_severity.map(x => x.severity), data.by_severity.map(x => x.total));
        } catch (err) { window.toast.error('Erro ao carregar graficos.'); }
    }

    function commonOpts(extra = {}) {
        return Object.assign({
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { color: tickColor } } },
            scales: { x: { grid: { color: gridColor }, ticks: { color: tickColor } }, y: { grid: { color: gridColor }, ticks: { color: tickColor } } },
        }, extra);
    }

    function renderLine(id, labels, datasets) {
        if (charts[id]) charts[id].destroy();
        charts[id] = new Chart(document.getElementById(id), { type: 'line', data: { labels, datasets }, options: commonOpts() });
    }
    function renderBar(id, labels, datasets) {
        if (charts[id]) charts[id].destroy();
        charts[id] = new Chart(document.getElementById(id), { type: 'bar', data: { labels, datasets }, options: commonOpts({ plugins: { legend: { display: false } } }) });
    }
    function renderDoughnut(id, labels, values) {
        if (charts[id]) charts[id].destroy();
        charts[id] = new Chart(document.getElementById(id), {
            type: 'doughnut',
            data: { labels, datasets: [{ data: values, backgroundColor: colors(labels.length), borderWidth: 2, borderColor: getComputedStyle(document.body).getPropertyValue('--bs-body-bg') }] },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: { legend: { position: 'bottom', labels: { color: tickColor, boxWidth: 12, padding: 10, font: { size: 11 } } } }
            }
        });
    }

    document.getElementById('period-days').addEventListener('change', e => loadCharts(e.target.value));
    document.getElementById('btn-waf-save')?.addEventListener('click', saveWafSettings);
    document.getElementById('btn-waf-cache')?.addEventListener('click', async () => {
        try {
            const r = await fetch(`{{ route('admin.security.waf.cache.clear') }}`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
            });
            const data = await r.json();
            if (r.ok && data.ok) window.toast.success(data.message);
            else window.toast.error(data.message || 'Erro ao limpar cache WAF.');
        } catch (err) {
            window.toast.error('Erro de rede.');
        }
    });

    document.querySelectorAll('[data-block-ip]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const ip = btn.dataset.blockIp;
            const ok = await Swal.fire({
                icon: 'warning', title: `Bloquear o IP ${ip}?`,
                text: 'Esse IP será adicionado à blocklist.',
                showCancelButton: true, confirmButtonText: 'Bloquear', cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545'
            });
            if (!ok.isConfirmed) return;
            const fd = new FormData();
            fd.append('type', 'ip');
            fd.append('value', ip);
            fd.append('reason', 'Bloqueio manual via dashboard');
            try {
                const r = await fetch(`{{ route('admin.security.blocklist.store') }}`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                    credentials: 'same-origin', body: fd,
                });
                if (r.ok) {
                    window.toast.success('IP bloqueado!');
                    btn.disabled = true;
                    btn.innerHTML = '<i class="bi bi-check2 me-1"></i> Bloqueado';
                    btn.classList.replace('btn-outline-danger', 'btn-success');
                } else window.toast.error('Erro ao bloquear.');
            } catch (err) { window.toast.error('Erro de rede.'); }
        });
    });

    loadWafSettings();
    loadCharts(7);
})();
</script>
@endpush
