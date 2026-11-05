@php
    $routePrefix = $routePrefix ?? (request()->is('painel/*') || session('admin_assist_mode') ? 'tenant.gestao-financeira.' : 'admin.gestao-financeira.');
    $baseUrl = str_starts_with($routePrefix, 'admin') ? '/admin/gestao-financeira' : '/painel/gestao-financeira';
    $year = now()->year;
@endphp
@extends('gestao-financeira::layouts.master')
@section('title', 'Relatórios')
@section('page_title', 'Relatórios financeiros')

@section('addon_content')
<div class="row g-4">
    {{-- DRE --}}
    <div class="col-md-6 col-xl-4">
        <a href="{{ $baseUrl }}/relatorios/dre" class="text-decoration-none">
            <div class="card shadow-sm border-0 h-100 report-card" style="border-radius:18px;transition:.3s;cursor:pointer;">
                <div class="card-body d-flex flex-column align-items-center justify-content-center text-center py-5">
                    <div class="bg-primary-subtle text-primary" style="width:64px;height:64px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.8rem;margin-bottom:1rem;">
                        <i class="bi bi-file-earmark-bar-graph"></i>
                    </div>
                    <h5 class="fw-bold mb-1">DRE</h5>
                    <p class="text-muted small mb-0">Demonstrativo de Resultado do Exercício — receitas e despesas agrupadas por categoria.</p>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle mt-3">Ver relatório</span>
                </div>
            </div>
        </a>
    </div>

    {{-- Fluxo de Caixa --}}
    <div class="col-md-6 col-xl-4">
        <a href="{{ $baseUrl }}/relatorios/fluxo-de-caixa" class="text-decoration-none">
            <div class="card shadow-sm border-0 h-100 report-card" style="border-radius:18px;transition:.3s;cursor:pointer;">
                <div class="card-body d-flex flex-column align-items-center justify-content-center text-center py-5">
                    <div class="bg-success-subtle text-success" style="width:64px;height:64px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.8rem;margin-bottom:1rem;">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                    <h5 class="fw-bold mb-1">Fluxo de Caixa</h5>
                    <p class="text-muted small mb-0">Entradas e saídas mês a mês — visão do fluxo financeiro anual completo.</p>
                    <span class="badge bg-success-subtle text-success border border-success-subtle mt-3">Ver relatório</span>
                </div>
            </div>
        </a>
    </div>

    {{-- Resumo rápido --}}
    <div class="col-md-6 col-xl-4">
        <div class="card shadow-sm border-0 h-100" style="border-radius:18px;">
            <div class="card-body d-flex flex-column align-items-center justify-content-center text-center py-5">
                <div class="bg-info-subtle text-info" style="width:64px;height:64px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.8rem;margin-bottom:1rem;">
                    <i class="bi bi-download"></i>
                </div>
                <h5 class="fw-bold mb-1">Exportar Lançamentos</h5>
                <p class="text-muted small mb-0">Baixe os lançamentos do período em formato CSV para análise em planilhas.</p>
                <a href="{{ $baseUrl }}/transacoes?export=csv" class="badge bg-info-subtle text-info border border-info-subtle mt-3 text-decoration-none">Em breve</a>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.report-card').forEach(c => {
    c.addEventListener('mouseenter', () => { c.style.transform='translateY(-4px)'; c.style.boxShadow='0 16px 32px rgba(15,23,42,.12)'; });
    c.addEventListener('mouseleave', () => { c.style.transform=''; c.style.boxShadow=''; });
});
</script>
@endpush
