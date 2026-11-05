@php
    $routePrefix = $routePrefix ?? (request()->is('painel/*') || session('admin_assist_mode') ? 'tenant.gestao-financeira.' : 'admin.gestao-financeira.');
    $baseUrl = str_starts_with($routePrefix, 'admin') ? '/admin/gestao-financeira' : '/painel/gestao-financeira';
@endphp
@extends('gestao-financeira::layouts.master')

@section('title', 'DRE')
@section('page_title', 'Demonstrativo do resultado do exercício')

@section('page_actions')
    <form action="{{ $baseUrl }}/relatorios/dre" method="GET" class="d-flex align-items-center gap-2">
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
                <span class="finance-addon-kpi__label">Faturamento bruto</span>
                <strong class="finance-addon-kpi__value text-success">R$ {{ number_format($totalReceitas, 2, ',', '.') }}</strong>
                <div class="finance-addon-kpi__meta">Receitas liquidadas no ano</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="finance-addon-kpi">
                <span class="finance-addon-kpi__label">Custos e despesas</span>
                <strong class="finance-addon-kpi__value text-danger">R$ {{ number_format($totalDespesas, 2, ',', '.') }}</strong>
                <div class="finance-addon-kpi__meta">Despesas liquidadas no ano</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="finance-addon-kpi">
                <span class="finance-addon-kpi__label">Resultado líquido</span>
                <strong class="finance-addon-kpi__value {{ $lucroLiquido >= 0 ? 'text-info' : 'text-danger' }}">R$ {{ number_format($lucroLiquido, 2, ',', '.') }}</strong>
                <div class="finance-addon-kpi__meta">{{ $lucroLiquido >= 0 ? 'Lucro' : 'Prejuízo' }} apurado no exercício</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="finance-addon-kpi">
                <span class="finance-addon-kpi__label">Margem líquida</span>
                <strong class="finance-addon-kpi__value {{ $margemLiquida >= 0 ? 'text-warning' : 'text-danger' }}">{{ number_format($margemLiquida, 1, ',', '.') }}%</strong>
                <div class="finance-addon-kpi__meta">Percentual sobre a receita</div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <x-ui.premium-card title="Receitas por categoria" subtitle="Composição das entradas liquidadas">
                @if($receitasPorCategoria->isEmpty())
                    <x-ui.empty-state icon="bi-pie-chart" title="Sem receitas no período" message="Não há receitas liquidadas no ano selecionado." />
                @else
                    <div class="finance-addon-chart">
                        <canvas id="receitasChart"></canvas>
                    </div>
                @endif
            </x-ui.premium-card>
        </div>
        <div class="col-lg-6">
            <x-ui.premium-card title="Despesas por categoria" subtitle="Composição das saídas liquidadas">
                @if($despesasPorCategoria->isEmpty())
                    <x-ui.empty-state icon="bi-pie-chart" title="Sem despesas no período" message="Não há despesas liquidadas no ano selecionado." />
                @else
                    <div class="finance-addon-chart">
                        <canvas id="despesasChart"></canvas>
                    </div>
                @endif
            </x-ui.premium-card>
        </div>
    </div>

    <x-ui.premium-card title="Estrutura contábil da DRE" subtitle="Resumo das receitas, despesas e resultado final">
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="border rounded-4 p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="h6 mb-0">Receitas</h3>
                        <span class="fw-bold text-success">R$ {{ number_format($totalReceitas, 2, ',', '.') }}</span>
                    </div>
                    <div class="d-grid gap-2">
                        @forelse($receitasPorCategoria as $category)
                            @php
                                $percent = $totalReceitas > 0 ? ($category->total / $totalReceitas) * 100 : 0;
                            @endphp
                            <div class="d-flex justify-content-between gap-3 small">
                                <span>{{ $category->name }}</span>
                                <span class="text-end">
                                    R$ {{ number_format($category->total, 2, ',', '.') }}
                                    <span class="finance-addon-muted">({{ number_format($percent, 1, ',', '.') }}%)</span>
                                </span>
                            </div>
                        @empty
                            <div class="finance-addon-muted small">Nenhuma receita líquida registrada no período.</div>
                        @endforelse
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="border rounded-4 p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="h6 mb-0">Despesas</h3>
                        <span class="fw-bold text-danger">R$ {{ number_format($totalDespesas, 2, ',', '.') }}</span>
                    </div>
                    <div class="d-grid gap-2">
                        @forelse($despesasPorCategoria as $category)
                            @php
                                $percent = $totalDespesas > 0 ? ($category->total / $totalDespesas) * 100 : 0;
                            @endphp
                            <div class="d-flex justify-content-between gap-3 small">
                                <span>{{ $category->name }}</span>
                                <span class="text-end">
                                    R$ {{ number_format($category->total, 2, ',', '.') }}
                                    <span class="finance-addon-muted">({{ number_format($percent, 1, ',', '.') }}%)</span>
                                </span>
                            </div>
                        @empty
                            <div class="finance-addon-muted small">Nenhuma despesa líquida registrada no período.</div>
                        @endforelse
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="border rounded-4 p-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <div class="h6 mb-1">Resultado líquido do exercício</div>
                        <div class="finance-addon-muted small">Margem líquida apurada: {{ number_format($margemLiquida, 1, ',', '.') }}%</div>
                    </div>
                    <div class="fs-4 fw-bold {{ $lucroLiquido >= 0 ? 'text-info' : 'text-danger' }}">
                        R$ {{ number_format($lucroLiquido, 2, ',', '.') }}
                    </div>
                </div>
            </div>
        </div>
    </x-ui.premium-card>
@endsection

@section('scripts')
    @if($receitasPorCategoria->count() > 0 || $despesasPorCategoria->count() > 0)
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            (() => {
                let receitasChart;
                let despesasChart;

                const themeValue = (name, fallback) => getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;

                const palette = () => [
                    themeValue('--bs-primary', '#0d6efd'),
                    themeValue('--bs-success', '#198754'),
                    themeValue('--bs-warning', '#ffc107'),
                    themeValue('--bs-danger', '#dc3545'),
                    themeValue('--bs-info', '#0dcaf0'),
                    '#6f42c1',
                    '#fd7e14',
                    '#20c997',
                ];

                const baseOptions = () => ({
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: themeValue('--bs-secondary-color', '#6c757d')
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label(context) {
                                    return context.label + ': ' + new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(context.parsed ?? 0);
                                }
                            }
                        }
                    }
                });

                const buildCharts = () => {
                    const colors = palette();

                    if (receitasChart) {
                        receitasChart.destroy();
                    }
                    if (despesasChart) {
                        despesasChart.destroy();
                    }

                    const receitasCanvas = document.getElementById('receitasChart');
                    if (receitasCanvas) {
                        receitasChart = new Chart(receitasCanvas.getContext('2d'), {
                            type: 'doughnut',
                            data: {
                                labels: {!! json_encode($receitasPorCategoria->pluck('name')) !!},
                                datasets: [{
                                    data: {!! json_encode($receitasPorCategoria->pluck('total')) !!},
                                    backgroundColor: colors,
                                }]
                            },
                            options: baseOptions()
                        });
                    }

                    const despesasCanvas = document.getElementById('despesasChart');
                    if (despesasCanvas) {
                        despesasChart = new Chart(despesasCanvas.getContext('2d'), {
                            type: 'doughnut',
                            data: {
                                labels: {!! json_encode($despesasPorCategoria->pluck('name')) !!},
                                datasets: [{
                                    data: {!! json_encode($despesasPorCategoria->pluck('total')) !!},
                                    backgroundColor: colors,
                                }]
                            },
                            options: baseOptions()
                        });
                    }
                };

                document.addEventListener('DOMContentLoaded', buildCharts);
                window.addEventListener('admin:theme-change', buildCharts);
            })();
        </script>
    @endif
@endsection
