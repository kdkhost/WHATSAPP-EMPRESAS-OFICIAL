@php
    $routePrefix = $routePrefix ?? (request()->is('painel/*') || session('admin_assist_mode') ? 'tenant.gestao-financeira.' : 'admin.gestao-financeira.');
    $baseUrl = str_starts_with($routePrefix, 'admin') ? '/admin/gestao-financeira' : '/painel/gestao-financeira';
@endphp
@extends('gestao-financeira::layouts.master')

@section('title', 'Relatório de fluxo de caixa')
@section('page_title', 'Fluxo de caixa anual')

@php
    $totalReceitasPagas = 0;
    $totalReceitasPendentes = 0;
    $totalDespesasPagas = 0;
    $totalDespesasPendentes = 0;

    foreach ($monthsData as $monthData) {
        $totalReceitasPagas += $monthData['receitas_pagas'];
        $totalReceitasPendentes += $monthData['receitas_pendentes'];
        $totalDespesasPagas += $monthData['despesas_pagas'];
        $totalDespesasPendentes += $monthData['despesas_pendentes'];
    }

    $saldoRealAno = $totalReceitasPagas - $totalDespesasPagas;
    $saldoProjetadoAno = ($totalReceitasPagas + $totalReceitasPendentes) - ($totalDespesasPagas + $totalDespesasPendentes);

    $monthNames = [
        1 => 'Janeiro',
        2 => 'Fevereiro',
        3 => 'Março',
        4 => 'Abril',
        5 => 'Maio',
        6 => 'Junho',
        7 => 'Julho',
        8 => 'Agosto',
        9 => 'Setembro',
        10 => 'Outubro',
        11 => 'Novembro',
        12 => 'Dezembro',
    ];
@endphp

@section('page_actions')
    <form action="{{ $baseUrl }}/relatorios/fluxo-de-caixa" method="GET" class="d-flex align-items-center gap-2">
        <label for="year" class="small text-muted mb-0">Ano</label>
        <select name="year" id="year" class="form-select form-select-sm" onchange="this.form.submit()">
            @for($year = now()->year - 3; $year <= now()->year + 1; $year++)
                <option value="{{ $year }}" @selected($ano == $year)>{{ $year }}</option>
            @endfor
        </select>
    </form>
@endsection

@section('addon_content')
    <div class="row g-4 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="finance-addon-kpi">
                <span class="finance-addon-kpi__label">Receitas realizadas</span>
                <strong class="finance-addon-kpi__value text-success">R$ {{ number_format($totalReceitasPagas, 2, ',', '.') }}</strong>
                <div class="finance-addon-kpi__meta">Pendente: R$ {{ number_format($totalReceitasPendentes, 2, ',', '.') }}</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="finance-addon-kpi">
                <span class="finance-addon-kpi__label">Despesas realizadas</span>
                <strong class="finance-addon-kpi__value text-danger">R$ {{ number_format($totalDespesasPagas, 2, ',', '.') }}</strong>
                <div class="finance-addon-kpi__meta">A pagar: R$ {{ number_format($totalDespesasPendentes, 2, ',', '.') }}</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="finance-addon-kpi">
                <span class="finance-addon-kpi__label">Saldo consolidado</span>
                <strong class="finance-addon-kpi__value {{ $saldoRealAno >= 0 ? 'text-info' : 'text-danger' }}">R$ {{ number_format($saldoRealAno, 2, ',', '.') }}</strong>
                <div class="finance-addon-kpi__meta">Somente lançamentos liquidados</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="finance-addon-kpi">
                <span class="finance-addon-kpi__label">Saldo projetado</span>
                <strong class="finance-addon-kpi__value {{ $saldoProjetadoAno >= 0 ? 'text-warning' : 'text-danger' }}">R$ {{ number_format($saldoProjetadoAno, 2, ',', '.') }}</strong>
                <div class="finance-addon-kpi__meta">Inclui pendências e projeções</div>
            </div>
        </div>
    </div>

    <x-ui.premium-card title="Evolução mensal" subtitle="Comparativo entre receitas e despesas do ano selecionado" class="mb-4">
        <div class="finance-addon-chart">
            <canvas id="monthlyFlowChart"></canvas>
        </div>
    </x-ui.premium-card>

    <x-ui.premium-card title="Detalhamento mensal" subtitle="Visão completa do realizado e do projetado">
        <div class="finance-addon-table">
            <x-ui.premium-table>
                <thead>
                    <tr>
                        <th>Mês</th>
                        <th class="text-end">Receitas pagas</th>
                        <th class="text-end">Receitas pendentes</th>
                        <th class="text-end">Despesas pagas</th>
                        <th class="text-end">Despesas pendentes</th>
                        <th class="text-end">Saldo real</th>
                        <th class="text-end">Saldo projetado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($monthsData as $monthIndex => $data)
                        @php
                            $saldoMesReal = $data['receitas_pagas'] - $data['despesas_pagas'];
                            $saldoMesProjetado = ($data['receitas_pagas'] + $data['receitas_pendentes']) - ($data['despesas_pagas'] + $data['despesas_pendentes']);
                        @endphp
                        <tr>
                            <td class="fw-semibold">{{ $monthNames[$monthIndex] }}</td>
                            <td class="text-end text-success">R$ {{ number_format($data['receitas_pagas'], 2, ',', '.') }}</td>
                            <td class="text-end">R$ {{ number_format($data['receitas_pendentes'], 2, ',', '.') }}</td>
                            <td class="text-end text-danger">R$ {{ number_format($data['despesas_pagas'], 2, ',', '.') }}</td>
                            <td class="text-end">R$ {{ number_format($data['despesas_pendentes'], 2, ',', '.') }}</td>
                            <td class="text-end {{ $saldoMesReal >= 0 ? 'text-success' : 'text-danger' }}">R$ {{ number_format($saldoMesReal, 2, ',', '.') }}</td>
                            <td class="text-end {{ $saldoMesProjetado >= 0 ? 'text-warning' : 'text-danger' }}">R$ {{ number_format($saldoMesProjetado, 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th>Totais</th>
                        <th class="text-end text-success">R$ {{ number_format($totalReceitasPagas, 2, ',', '.') }}</th>
                        <th class="text-end">R$ {{ number_format($totalReceitasPendentes, 2, ',', '.') }}</th>
                        <th class="text-end text-danger">R$ {{ number_format($totalDespesasPagas, 2, ',', '.') }}</th>
                        <th class="text-end">R$ {{ number_format($totalDespesasPendentes, 2, ',', '.') }}</th>
                        <th class="text-end {{ $saldoRealAno >= 0 ? 'text-success' : 'text-danger' }}">R$ {{ number_format($saldoRealAno, 2, ',', '.') }}</th>
                        <th class="text-end {{ $saldoProjetadoAno >= 0 ? 'text-warning' : 'text-danger' }}">R$ {{ number_format($saldoProjetadoAno, 2, ',', '.') }}</th>
                    </tr>
                </tfoot>
            </x-ui.premium-table>
        </div>
    </x-ui.premium-card>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        (() => {
            let flowChart;
            const chartSource = {!! json_encode($monthsData) !!};

            const themeValue = (name, fallback) => getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;

            const buildChart = () => {
                const canvas = document.getElementById('monthlyFlowChart');
                if (!canvas) {
                    return;
                }

                if (flowChart) {
                    flowChart.destroy();
                }

                const success = themeValue('--bs-success', '#198754');
                const danger = themeValue('--bs-danger', '#dc3545');
                const warning = themeValue('--bs-warning', '#ffc107');
                const muted = themeValue('--bs-secondary-color', '#6c757d');
                const border = themeValue('--bs-border-color', '#dee2e6');

                const receitasPagas = [];
                const receitasTotais = [];
                const despesasPagas = [];
                const despesasTotais = [];

                for (let month = 1; month <= 12; month += 1) {
                    const item = chartSource[month];
                    receitasPagas.push(item.receitas_pagas);
                    receitasTotais.push(item.receitas_pagas + item.receitas_pendentes);
                    despesasPagas.push(item.despesas_pagas);
                    despesasTotais.push(item.despesas_pagas + item.despesas_pendentes);
                }

                flowChart = new Chart(canvas.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'],
                        datasets: [
                            { label: 'Receitas pagas', data: receitasPagas, backgroundColor: success + 'cc', borderColor: success, borderWidth: 1, borderRadius: 6 },
                            { label: 'Receitas totais', data: receitasTotais, backgroundColor: success + '33', borderColor: success + '88', borderWidth: 1, borderRadius: 6 },
                            { label: 'Despesas pagas', data: despesasPagas, backgroundColor: danger + 'cc', borderColor: danger, borderWidth: 1, borderRadius: 6 },
                            { label: 'Despesas totais', data: despesasTotais, backgroundColor: warning + '40', borderColor: warning, borderWidth: 1, borderRadius: 6 },
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { labels: { color: muted } },
                            tooltip: {
                                callbacks: {
                                    label(context) {
                                        return context.dataset.label + ': ' + new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(context.parsed.y ?? 0);
                                    }
                                }
                            }
                        },
                        scales: {
                            x: { ticks: { color: muted }, grid: { color: border + '33' } },
                            y: {
                                ticks: {
                                    color: muted,
                                    callback(value) {
                                        return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL', maximumFractionDigits: 0 }).format(value);
                                    }
                                },
                                grid: { color: border + '33' }
                            }
                        }
                    }
                });
            };

            document.addEventListener('DOMContentLoaded', buildChart);
            window.addEventListener('admin:theme-change', buildChart);
        })();
    </script>
@endsection
