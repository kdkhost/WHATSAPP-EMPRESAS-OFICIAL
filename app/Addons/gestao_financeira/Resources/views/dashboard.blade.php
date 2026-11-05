@php
    $routePrefix = $routePrefix ?? (request()->is('painel/*') || session('admin_assist_mode') ? 'tenant.gestao-financeira.' : 'admin.gestao-financeira.');
    $baseUrl = str_starts_with($routePrefix, 'admin') ? '/admin/gestao-financeira' : '/painel/gestao-financeira';
@endphp
@extends('gestao-financeira::layouts.master')
@section('title', 'Dashboard')
@section('page_title', 'Visão geral financeira')

@section('page_actions')
    <button type="button" class="btn btn-primary" id="btn-quick-add">
        <i class="bi bi-plus-lg me-1"></i> Lançamento rápido
    </button>
@endsection

@section('addon_content')

{{-- KPIs animados --}}
<div class="row g-3 mb-4">
    @php
    $kpis = [
        ['Saldo consolidado',  $saldoContas,         'primary', 'bi-wallet2',           'Disponível em todas as contas'],
        ['Receitas do mês',    $receitasRecebidas,   'success', 'bi-arrow-down-circle',  'Pendente: R$ '.number_format($receitasPendentes,2,',','.')],
        ['Despesas do mês',    $despesasPagas,       'danger',  'bi-arrow-up-circle',    'Pendente: R$ '.number_format($despesasPendentes,2,',','.')],
        ['Resultado líquido',  $saldoMes,            $saldoMes >= 0 ? 'info' : 'danger', 'bi-activity', 'Diferença do mês'],
    ];
    @endphp
    @foreach($kpis as $i => [$lbl, $val, $cor, $ico, $sub])
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-sm border-0 h-100 kpi-card" style="border-radius:18px;transition:.3s;cursor:default;"
             data-value="{{ $val }}" data-index="{{ $i }}">
            <div class="card-body d-flex align-items-center gap-3 py-3 px-4">
                <div class="kpi-icon bg-{{ $cor }}-subtle text-{{ $cor }}"
                     style="width:52px;height:52px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:1.4rem;transition:.3s;">
                    <i class="bi {{ $ico }}"></i>
                </div>
                <div class="flex-grow-1 min-w-0">
                    <div class="text-muted fw-semibold text-uppercase" style="font-size:.68rem;letter-spacing:.07em;">{{ $lbl }}</div>
                    <div class="fw-bold text-{{ $cor }} kpi-value" style="font-size:1.5rem;line-height:1.2;"
                         data-raw="{{ $val }}">R$ {{ number_format($val,2,',','.') }}</div>
                    <div class="text-muted" style="font-size:.75rem;">{{ $sub }}</div>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="row g-4 mb-4">
    {{-- Gráfico fluxo --}}
    <div class="col-xl-8">
        <div class="card shadow-sm border-0 h-100" style="border-radius:18px;">
            <div class="card-header d-flex align-items-center justify-content-between py-2 px-4 bg-transparent border-0">
                <strong><i class="bi bi-graph-up me-2 text-primary"></i>Fluxo de caixa — últimos 6 meses</strong>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-secondary chart-type-btn active" data-type="line"><i class="bi bi-graph-up-arrow"></i></button>
                    <button class="btn btn-sm btn-outline-secondary chart-type-btn" data-type="bar"><i class="bi bi-bar-chart"></i></button>
                </div>
            </div>
            <div class="card-body pt-0">
                <div style="position:relative;height:280px;">
                    <canvas id="flowChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- Vencimentos próximos --}}
    <div class="col-xl-4">
        <div class="card shadow-sm border-0 h-100" style="border-radius:18px;">
            <div class="card-header py-2 px-4 bg-transparent border-0">
                <strong><i class="bi bi-calendar-event me-2 text-warning"></i>Vencimentos próximos</strong>
                <span class="badge bg-warning-subtle text-warning border border-warning-subtle ms-2">{{ $upcomingTransactions->count() }} pendentes</span>
            </div>
            <div class="card-body p-0" style="max-height:310px;overflow-y:auto;">
                @forelse($upcomingTransactions as $t)
                <div class="d-flex align-items-center gap-3 px-4 py-3 border-bottom upcoming-item" style="transition:.2s;">
                    <div class="flex-shrink-0">
                        <div class="text-center" style="line-height:1;">
                            <div class="fw-bold text-{{ $t->type === 'receita' ? 'success' : 'danger' }}" style="font-size:.95rem;">{{ $t->due_date->format('d') }}</div>
                            <div class="text-muted" style="font-size:.7rem;text-transform:uppercase;">{{ $t->due_date->translatedFormat('M') }}</div>
                        </div>
                    </div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold text-truncate small">{{ $t->description }}</div>
                        <div class="text-muted" style="font-size:.72rem;">{{ $t->account?->name ?? '—' }}</div>
                    </div>
                    <div class="text-end flex-shrink-0">
                        <div class="fw-bold {{ $t->type === 'receita' ? 'text-success' : 'text-danger' }}" style="font-size:.9rem;">
                            {{ $t->type === 'receita' ? '+' : '−' }} R$ {{ number_format($t->amount,2,',','.') }}
                        </div>
                        <button class="btn btn-xs btn-outline-success mt-1 btn-pay-quick" data-id="{{ $t->id }}" style="font-size:.7rem;padding:.15rem .5rem;">
                            <i class="bi bi-check-lg"></i> Baixar
                        </button>
                    </div>
                </div>
                @empty
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-shield-check fs-3 d-block mb-2 text-success opacity-75"></i>
                    <div class="small">Nenhum vencimento nos próximos 7 dias</div>
                </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    {{-- Contas --}}
    <div class="col-xl-4">
        <div class="card shadow-sm border-0 h-100" style="border-radius:18px;">
            <div class="card-header py-2 px-4 bg-transparent border-0 d-flex align-items-center justify-content-between">
                <strong><i class="bi bi-bank me-2 text-primary"></i>Contas</strong>
                <a href="{{ $baseUrl }}/contas" class="btn btn-sm btn-outline-primary">Ver todas</a>
            </div>
            <div class="card-body p-0">
                @forelse($contas as $c)
                @php
                    $typeIcon = ['banco'=>'bi-building','digital'=>'bi-phone','dinheiro'=>'bi-cash-coin','cartao'=>'bi-credit-card','outro'=>'bi-wallet2'][$c->type] ?? 'bi-wallet2';
                    $typeCor  = ['banco'=>'primary','digital'=>'info','dinheiro'=>'warning','cartao'=>'secondary','outro'=>'secondary'][$c->type] ?? 'secondary';
                @endphp
                <div class="d-flex align-items-center gap-3 px-4 py-3 border-bottom">
                    <div class="bg-{{ $typeCor }}-subtle text-{{ $typeCor }}" style="width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="bi {{ $typeIcon }}"></i>
                    </div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold small text-truncate">{{ $c->name }}</div>
                    </div>
                    <div class="fw-bold {{ $c->current_balance >= 0 ? 'text-success' : 'text-danger' }}" style="font-size:.95rem;">
                        R$ {{ number_format($c->current_balance,2,',','.') }}
                    </div>
                </div>
                @empty
                <div class="text-center py-4 text-muted small">
                    <i class="bi bi-bank fs-3 d-block mb-2 opacity-50"></i>
                    <a href="{{ $baseUrl }}/contas" class="btn btn-sm btn-primary mt-2">Criar conta</a>
                </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Últimas transações --}}
    <div class="col-xl-8">
        <div class="card shadow-sm border-0 h-100" style="border-radius:18px;">
            <div class="card-header py-2 px-4 bg-transparent border-0 d-flex align-items-center justify-content-between">
                <strong><i class="bi bi-clock-history me-2 text-primary"></i>Transações recentes</strong>
                <a href="{{ $baseUrl }}/transacoes" class="btn btn-sm btn-outline-primary">Ver todas</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle m-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Descrição</th>
                                <th>Conta</th>
                                <th class="text-end">Valor</th>
                                <th class="text-end pe-4">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentTransactions as $t)
                            <tr style="transition:.2s;" id="dash-row-{{ $t->id }}">
                                <td class="ps-4">
                                    <div class="fw-semibold small">{{ \Illuminate\Support\Str::limit($t->description,40) }}</div>
                                    <div class="text-muted" style="font-size:.72rem;">{{ $t->due_date->format('d/m/Y') }}</div>
                                </td>
                                <td class="small text-muted">{{ $t->account?->name ?? '—' }}</td>
                                <td class="text-end fw-bold {{ $t->type === 'receita' ? 'text-success' : 'text-danger' }}" style="font-size:.9rem;">
                                    {{ $t->type === 'receita' ? '+' : '−' }} R$ {{ number_format($t->amount,2,',','.') }}
                                </td>
                                <td class="text-end pe-4">
                                    @if($t->status === 'pago')
                                        <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check-circle me-1"></i>Pago</span>
                                    @else
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle"><i class="bi bi-clock me-1"></i>Pendente</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="text-center py-4 text-muted small">
                                <i class="bi bi-receipt fs-3 d-block mb-2 opacity-50"></i>
                                Nenhuma transação recente
                            </td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal Lançamento Rápido --}}
<div class="modal fade" id="modalQuickAdd" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="frm-quick">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-lightning-charge me-2 text-primary"></i>Lançamento rápido</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-12">
                        <div class="btn-group w-100">
                            <input type="radio" class="btn-check" name="type" id="q-receita" value="receita" checked>
                            <label class="btn btn-outline-success" for="q-receita"><i class="bi bi-arrow-down-circle me-1"></i>Receita</label>
                            <input type="radio" class="btn-check" name="type" id="q-despesa" value="despesa">
                            <label class="btn btn-outline-danger" for="q-despesa"><i class="bi bi-arrow-up-circle me-1"></i>Despesa</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Descrição <span class="text-danger">*</span></label>
                        <input type="text" name="description" class="form-control" required placeholder="Ex.: pagamento cliente, fornecedor...">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Valor <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">R$</span>
                            <input type="number" name="amount" step="0.01" min="0.01" class="form-control" required placeholder="0,00">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Situação</label>
                        <select name="status" class="form-select">
                            <option value="pago">Pago</option>
                            <option value="pendente">Pendente</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Conta <span class="text-danger">*</span></label>
                        <select name="account_id" class="form-select" required>
                            <option value="">Selecione</option>
                            @foreach($contas as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Data</label>
                        <input type="date" name="due_date" class="form-control" value="{{ now()->toDateString() }}">
                    </div>
                    <input type="hidden" name="category_id" id="q-cat-id" value="">
                    <div class="col-12">
                        <label class="form-label fw-semibold small">Categoria <small class="text-muted">(opcional)</small></label>
                        <div class="d-flex flex-wrap gap-2" id="q-cats">
                            {{-- preenchido via JS --}}
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <span class="spinner-border spinner-border-sm d-none me-1" id="q-spinner"></span>
                        <i class="bi bi-check2-circle me-1"></i> Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
(function(){
    const csrf  = document.querySelector('meta[name=csrf-token]')?.content ?? '';
    const base  = @json($baseUrl);
    const toast = (msg, type='success') => {
        if (window.toastr) { window.toastr[type](msg); return; }
        Swal.fire({ icon: type === 'error' ? 'error' : 'success', title: msg, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
    };
    const cats  = @json($categoriesForJs);

    /* ── KPI hover ────────────────────────────────── */
    document.querySelectorAll('.kpi-card').forEach(card => {
        card.addEventListener('mouseenter', () => { card.style.transform='translateY(-4px)'; card.style.boxShadow='0 16px 32px rgba(15,23,42,.14)'; });
        card.addEventListener('mouseleave', () => { card.style.transform=''; card.style.boxShadow=''; });
    });

    /* ── Chart ───────────────────────────────────── */
    const chartData = @json($chartData);
    let chartType   = 'line';
    let chart;

    const cssVar = (n, fb) => getComputedStyle(document.documentElement).getPropertyValue(n).trim() || fb;

    const buildChart = (type='line') => {
        const canvas = document.getElementById('flowChart');
        if(!canvas) return;
        if(chart) chart.destroy();

        const success = cssVar('--bs-success','#198754');
        const danger  = cssVar('--bs-danger','#dc3545');
        const muted   = cssVar('--bs-secondary-color','#6c757d');
        const bdr     = cssVar('--bs-border-color','#dee2e6');
        const bodyBg  = cssVar('--bs-body-bg','#fff');
        const bodyClr = cssVar('--bs-body-color','#212529');

        chart = new Chart(canvas, {
            type,
            data: {
                labels: chartData.labels,
                datasets: [
                    {
                        label: 'Receitas',
                        data: chartData.receitas,
                        borderColor: success,
                        backgroundColor: type==='line' ? success+'28' : success+'bb',
                        tension: .38,
                        fill: type==='line',
                        borderWidth: 2.5,
                        pointRadius: type==='line' ? 4 : 0,
                        pointHoverRadius: 6,
                        borderRadius: type==='bar' ? 8 : 0,
                    },
                    {
                        label: 'Despesas',
                        data: chartData.despesas,
                        borderColor: danger,
                        backgroundColor: type==='line' ? danger+'1a' : danger+'bb',
                        tension: .38,
                        fill: type==='line',
                        borderWidth: 2.5,
                        pointRadius: type==='line' ? 4 : 0,
                        pointHoverRadius: 6,
                        borderRadius: type==='bar' ? 8 : 0,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 600, easing: 'easeOutQuart' },
                interaction: { mode:'index', intersect:false },
                plugins: {
                    legend: { labels:{ color:muted, usePointStyle:true, pointStyle:'circle' } },
                    tooltip: {
                        backgroundColor: bodyBg,
                        titleColor: bodyClr, bodyColor: bodyClr,
                        borderColor: bdr, borderWidth:1,
                        callbacks: { label: ctx => ` ${ctx.dataset.label}: R$ ${(ctx.parsed.y??0).toLocaleString('pt-BR',{minimumFractionDigits:2})}` }
                    }
                },
                scales: {
                    x: { ticks:{color:muted}, grid:{color:bdr+'44'} },
                    y: { ticks:{color:muted, callback:v=>'R$ '+(v/1000).toFixed(0)+'k'}, grid:{color:bdr+'44'} }
                }
            }
        });
    };

    buildChart(chartType);
    window.addEventListener('admin:theme-change', () => buildChart(chartType));

    document.querySelectorAll('.chart-type-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.chart-type-btn').forEach(b=>b.classList.remove('active'));
            btn.classList.add('active');
            chartType = btn.dataset.type;
            buildChart(chartType);
        });
    });

    /* ── Baixar rápido nos vencimentos ───────────── */
    document.querySelectorAll('.btn-pay-quick').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id = btn.dataset.id;
            btn.innerHTML='<span class="spinner-border spinner-border-sm" style="width:.8rem;height:.8rem;"></span>';
            btn.disabled = true;
            const r = await fetch(`${base}/transacoes/${id}/pagar`,{method:'POST',credentials:'same-origin',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}});
            const d = await r.json();
            if(d.ok||r.ok){
                const item = btn.closest('.upcoming-item');
                if(item){item.style.opacity='0';setTimeout(()=>item.remove(),400);}
                toast('Lançamento baixado!');
            } else { toast(d.message||'Erro','error'); btn.innerHTML='<i class="bi bi-check-lg"></i> Baixar'; btn.disabled=false; }
        });
    });

    /* ── Modal lançamento rápido ─────────────────── */
    const qModal = new bootstrap.Modal(document.getElementById('modalQuickAdd'));
    const qFrm   = document.getElementById('frm-quick');
    const qCats  = document.getElementById('q-cats');
    const qCatId = document.getElementById('q-cat-id');
    const qTypes = document.querySelectorAll('#frm-quick input[name="type"]');

    const renderCats = () => {
        const type = [...qTypes].find(r=>r.checked)?.value ?? 'receita';
        qCats.innerHTML = '';
        qCatId.value = '';
        cats.filter(c=>c.type===type).forEach(c => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-sm cat-chip';
            btn.style = `background:${c.color}22;color:${c.color};border:1px solid ${c.color}55;border-radius:999px;font-size:.75rem;padding:.25rem .75rem;transition:.2s;`;
            btn.textContent = c.name;
            btn.dataset.id  = c.id;
            btn.addEventListener('click', () => {
                qCats.querySelectorAll('.cat-chip').forEach(b=>b.style.opacity='.6');
                btn.style.opacity='1';
                btn.style.boxShadow=`0 0 0 2px ${c.color}66`;
                qCatId.value = c.id;
            });
            qCats.appendChild(btn);
        });
    };

    qTypes.forEach(r => r.addEventListener('change', renderCats));
    document.getElementById('btn-quick-add').addEventListener('click', () => { qFrm.reset(); renderCats(); qModal.show(); });

    qFrm.addEventListener('submit', async e => {
        e.preventDefault();
        const sp = document.getElementById('q-spinner');
        sp.classList.remove('d-none');
        const fd = new FormData(qFrm);
        fd.set('due_date', fd.get('due_date')||new Date().toISOString().split('T')[0]);
        if(fd.get('status')==='pago') fd.set('payment_date', fd.get('due_date'));
        try {
            const r = await fetch(`${base}/transacoes/salvar`,{method:'POST',credentials:'same-origin',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'},body:fd});
            const d = await r.json();
            if(d.ok||r.ok){ qModal.hide(); toast('Lançamento criado!'); setTimeout(()=>location.reload(),600); }
            else toast(d.message||'Erro ao salvar.','error');
        } catch(err){ toast('Erro de rede.','error'); }
        sp.classList.add('d-none');
    });
})();
</script>
@endsection
