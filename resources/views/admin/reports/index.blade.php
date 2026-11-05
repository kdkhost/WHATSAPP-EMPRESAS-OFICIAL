@extends('admin.layouts.app')
@section('title', 'Relatorios')

@push('head')
<style>
.reports-page {
    --reports-surface-soft: color-mix(in srgb, var(--bs-body-bg) 92%, var(--bs-primary) 8%);
    --reports-border-soft: color-mix(in srgb, var(--bs-border-color) 75%, transparent);
}
.reports-cards {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 1rem;
}
.reports-card {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 240px;
    padding: 1.2rem;
    border: 1px solid var(--reports-border-soft);
    border-radius: 18px;
    background: var(--bs-body-bg);
    box-shadow: var(--app-shadow-premium);
}
.reports-card__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
}
.reports-card__title {
    margin: 0 0 .35rem;
    font-size: 1.1rem;
    font-weight: 700;
}
.reports-card__text {
    margin: 0;
    color: var(--bs-secondary-color);
    font-size: .84rem;
    line-height: 1.5;
}
.reports-card__meta {
    display: grid;
    gap: .5rem;
    margin-top: 1rem;
}
.reports-chip {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    padding: .35rem .7rem;
    border-radius: 999px;
    background: var(--reports-surface-soft);
    color: var(--bs-secondary-color);
    font-size: .78rem;
    font-weight: 600;
}
@media (max-width: 991.98px) {
    .reports-cards {
        grid-template-columns: 1fr;
    }
}
</style>
@endpush

@section('content')
<div class="container-fluid reports-page">
    <section class="admin-premium-hero mb-4">
        <div>
            <span class="admin-premium-hero__eyebrow">Inteligencia operacional</span>
            <h1 class="admin-premium-hero__title">Relatorios em um hub mais claro para faturamento, clientes e mensagens.</h1>
            <p class="admin-premium-hero__text">
                Esta area agora apresenta os grupos de relatorio com mais contexto visual, para facilitar a expansao dos modulos sem deixar a tela parecer provisoria.
            </p>
        </div>
        <div class="dashboard-hero-notes">
            <div class="dashboard-note">
                <span class="dashboard-note__label">Financeiro</span>
                <span class="dashboard-note__value">Receita, status e gateways</span>
            </div>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Clientes</span>
                <span class="dashboard-note__value">Carteira, churn e crescimento</span>
            </div>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Mensagens</span>
                <span class="dashboard-note__value">Envios, entrega e volume</span>
            </div>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Estado atual</span>
                <span class="dashboard-note__value">Catalogo pronto para expansao</span>
            </div>
        </div>
    </section>

    <section class="premium-panel mb-4">
        <div class="premium-panel__header">
            <div>
                <span class="premium-panel__kicker">Catalogo de relatorios</span>
                <h2 class="premium-panel__title">Areas disponiveis</h2>
                <p class="premium-panel__subtitle mb-0">Cada bloco concentra uma frente de analise planejada para o administrativo.</p>
            </div>
        </div>
        <div class="premium-panel__body">
            <div class="reports-cards">
                <article class="reports-card">
                    <div>
                        <div class="reports-card__head mb-3">
                            <div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-receipt"></i></div>
                            <span class="badge bg-primary-subtle text-primary">Financeiro</span>
                        </div>
                        <h3 class="reports-card__title">Faturamento</h3>
                        <p class="reports-card__text">Concentrara receita por periodo, gateway, status de cobranca e comparativos de desempenho.</p>
                        <div class="reports-card__meta">
                            <span class="reports-chip"><i class="bi bi-graph-up-arrow"></i> Receita por janela</span>
                            <span class="reports-chip"><i class="bi bi-credit-card"></i> Corte por gateway</span>
                        </div>
                    </div>
                    <button class="btn btn-outline-primary disabled">Em breve</button>
                </article>

                <article class="reports-card">
                    <div>
                        <div class="reports-card__head mb-3">
                            <div class="stat-icon bg-success-subtle text-success"><i class="bi bi-people"></i></div>
                            <span class="badge bg-success-subtle text-success">Relacionamento</span>
                        </div>
                        <h3 class="reports-card__title">Clientes</h3>
                        <p class="reports-card__text">Cobertura para crescimento da base, churn, segmentacao comercial e comportamento da carteira.</p>
                        <div class="reports-card__meta">
                            <span class="reports-chip"><i class="bi bi-person-plus"></i> Novos e recorrentes</span>
                            <span class="reports-chip"><i class="bi bi-funnel"></i> Segmentos e funis</span>
                        </div>
                    </div>
                    <button class="btn btn-outline-success disabled">Em breve</button>
                </article>

                <article class="reports-card">
                    <div>
                        <div class="reports-card__head mb-3">
                            <div class="stat-icon bg-info-subtle text-info"><i class="bi bi-whatsapp"></i></div>
                            <span class="badge bg-info-subtle text-info">Comunicacao</span>
                        </div>
                        <h3 class="reports-card__title">Mensagens</h3>
                        <p class="reports-card__text">Leitura de volume, taxa de entrega, falhas de envio e comportamento do canal WhatsApp.</p>
                        <div class="reports-card__meta">
                            <span class="reports-chip"><i class="bi bi-send"></i> Fluxo de envios</span>
                            <span class="reports-chip"><i class="bi bi-check2-circle"></i> Entrega e abertura</span>
                        </div>
                    </div>
                    <button class="btn btn-outline-info disabled">Em breve</button>
                </article>
            </div>
        </div>
    </section>
</div>
@endsection
