@extends('admin.layouts.app')
@section('title', 'Importar Clientes')

@push('head')
<style>
/* ============================================================
   STEPPER DE PROGRESSO DA IMPORTAÇÃO
   ============================================================ */
.import-stepper {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1.15rem 1.4rem;
    background: var(--app-surface-bg);
    border: 1px solid var(--bs-border-color);
    border-radius: var(--app-radius-premium);
    box-shadow: var(--app-shadow-premium);
    transition: var(--app-transition);
}
.import-step {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    flex: 1;
    opacity: 0.5;
    transition: all 0.3s ease;
}
.import-step.active,
.import-step.completed {
    opacity: 1;
}
.import-step__number {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: var(--bs-tertiary-bg);
    color: var(--bs-secondary-color);
    border: 2px solid var(--bs-border-color);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.95rem;
    flex-shrink: 0;
    transition: all 0.3s ease;
}
.import-step.active .import-step__number {
    background: var(--bs-primary);
    color: #ffffff;
    border-color: var(--bs-primary);
    box-shadow: 0 0 0 4px rgba(var(--bs-primary-rgb), 0.2);
}
.import-step.completed .import-step__number {
    background: var(--bs-success);
    color: #ffffff;
    border-color: var(--bs-success);
}
.import-step__content {
    display: flex;
    flex-direction: column;
    min-width: 0;
}
.import-step__title {
    font-weight: 600;
    font-size: 0.875rem;
    color: var(--bs-body-color);
    line-height: 1.25;
}
.import-step__subtitle {
    font-size: 0.75rem;
    color: var(--bs-secondary-color);
    margin-top: 2px;
}
.import-step__divider {
    height: 2px;
    background: var(--bs-border-color);
    flex: 0 0 32px;
}
@media (max-width: 991.98px) {
    .import-stepper {
        flex-direction: column;
        align-items: stretch;
        gap: 0.85rem;
    }
    .import-step__divider {
        display: none;
    }
}

/* ============================================================
   DROPZONE DE UPLOAD DRAG & DROP
   ============================================================ */
.csv-upload-dropzone {
    border: 2px dashed var(--bs-border-color);
    border-radius: 1rem;
    background: var(--bs-tertiary-bg);
    padding: 3.5rem 1.5rem;
    text-align: center;
    position: relative;
    cursor: pointer;
    transition: all 0.25s ease-in-out;
}
.csv-upload-dropzone:hover,
.csv-upload-dropzone.dragover {
    border-color: var(--bs-primary);
    background: color-mix(in srgb, var(--bs-primary) 6%, var(--bs-body-bg));
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(var(--bs-primary-rgb), 0.12);
}
.csv-upload-dropzone__input {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
    z-index: 5;
}
.csv-upload-icon {
    width: 64px;
    height: 64px;
    margin: 0 auto 1.25rem;
    border-radius: 16px;
    background: color-mix(in srgb, var(--bs-primary) 12%, transparent);
    color: var(--bs-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    transition: transform 0.25s ease;
}
.csv-upload-dropzone:hover .csv-upload-icon {
    transform: scale(1.08);
}

/* ============================================================
   CARD DO ARQUIVO SELECIONADO
   ============================================================ */
.csv-file-card {
    background: var(--bs-tertiary-bg);
    border: 1px solid var(--bs-border-color);
    border-radius: 12px;
}
.csv-file-badge {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    background: color-mix(in srgb, var(--bs-success) 12%, transparent);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

/* ============================================================
   CAIXA DE MAPEAMENTO DE CAMPOS
   ============================================================ */
.mapping-box {
    background: var(--bs-tertiary-bg);
    border: 1px solid var(--bs-border-color);
    border-radius: 10px;
    padding: 0.9rem;
    transition: all 0.2s ease;
}
.mapping-box:focus-within {
    border-color: var(--bs-primary);
    box-shadow: 0 0 0 3px rgba(var(--bs-primary-rgb), 0.15);
}

/* ============================================================
   ADAPTAÇÕES OFICIAIS PARA TEMA ESCURO (DARK MODE)
   ============================================================ */
[data-bs-theme="dark"] .import-stepper {
    background: var(--admin-dark-surface, #1e293b);
    border-color: var(--admin-dark-border, #334155);
}
[data-bs-theme="dark"] .import-step__number {
    background: var(--admin-dark-field, #0f172a);
    border-color: var(--admin-dark-border, #334155);
    color: #94a3b8;
}
[data-bs-theme="dark"] .import-step__divider {
    background: var(--admin-dark-border, #334155);
}
[data-bs-theme="dark"] .csv-upload-dropzone {
    background: var(--admin-dark-field, #0f172a);
    border-color: var(--admin-dark-border, #334155);
}
[data-bs-theme="dark"] .csv-upload-dropzone:hover,
[data-bs-theme="dark"] .csv-upload-dropzone.dragover {
    background: color-mix(in srgb, var(--bs-primary) 12%, var(--admin-dark-field, #0f172a));
    border-color: var(--bs-primary);
}
[data-bs-theme="dark"] .csv-file-card {
    background: var(--admin-dark-field, #0f172a);
    border-color: var(--admin-dark-border, #334155);
}
[data-bs-theme="dark"] .mapping-box {
    background: var(--admin-dark-field, #0f172a);
    border-color: var(--admin-dark-border, #334155);
}
[data-bs-theme="dark"] #result-errors-list {
    background: var(--admin-dark-field, #0f172a) !important;
    color: #f87171 !important;
}
</style>
@endpush

@section('content')
<div class="container-fluid px-0">

    {{-- ========== HEADER ========== --}}
    <x-ui.page-header
        title="Importação de Clientes em Lote"
        subtitle="Carregue sua base de contatos via CSV ou TXT com detecção automática de delimitadores, mapeamento flexível e deduplicação inteligente."
        eyebrow="CRM & Clientes"
        class="mb-4"
    >
        <x-slot:actions>
            <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
                <button type="button" class="btn btn-outline-secondary" id="btn-top-download-model" title="Baixar modelo em CSV">
                    <i class="bi bi-file-earmark-arrow-down me-1 text-primary"></i>
                    <span>Baixar Modelo CSV</span>
                </button>
                <a href="{{ route('admin.billing.customers.index') }}" class="btn btn-primary" title="Ver clientes já cadastrados">
                    <i class="bi bi-people me-1"></i>
                    <span>Ver Clientes</span>
                </a>
            </div>
        </x-slot:actions>

        <x-slot:aside>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Formato Suportado</span>
                <span class="dashboard-note__value"><i class="bi bi-filetype-csv me-1 text-success"></i>CSV / TXT</span>
            </div>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Deduplicação</span>
                <span class="dashboard-note__value text-primary"><i class="bi bi-shield-check me-1"></i>Automática</span>
            </div>
        </x-slot:aside>
    </x-ui.page-header>

    {{-- ========== STEPPER VISUAL DE ETAPAS ========== --}}
    <div class="import-stepper mb-4">
        <div class="import-step active" id="step-nav-1">
            <div class="import-step__number">1</div>
            <div class="import-step__content">
                <span class="import-step__title">Upload do Arquivo</span>
                <span class="import-step__subtitle">Selecione o arquivo CSV ou TXT</span>
            </div>
        </div>
        <div class="import-step__divider"></div>
        <div class="import-step" id="step-nav-2">
            <div class="import-step__number">2</div>
            <div class="import-step__content">
                <span class="import-step__title">Mapeamento & Preview</span>
                <span class="import-step__subtitle">Associe colunas e revise as linhas</span>
            </div>
        </div>
        <div class="import-step__divider"></div>
        <div class="import-step" id="step-nav-3">
            <div class="import-step__number">3</div>
            <div class="import-step__content">
                <span class="import-step__title">Importação & Resultado</span>
                <span class="import-step__subtitle">Processamento e estatísticas finais</span>
            </div>
        </div>
    </div>

    <div class="row g-4">

        {{-- ======================================================== --}}
        {{-- COLUNA PRINCIPAL ESQUERDA (WORKFLOW EM 3 PASSOS) --}}
        {{-- ======================================================== --}}
        <div class="col-12 col-lg-8">

            {{-- ETAPA 1: SELEÇÃO E UPLOAD DO ARQUIVO --}}
            <div id="step-panel-1">
                <x-ui.premium-card title="Selecione ou Arraste seu Arquivo CSV" subtitle="O sistema aceita arquivos .csv ou .txt separados por vírgula (,) ou ponto e vírgula (;).">
                    {{-- Área Drag & Drop --}}
                    <div id="csv-drop-zone" class="csv-upload-dropzone mb-3">
                        <div class="csv-upload-icon">
                            <i class="bi bi-cloud-arrow-up"></i>
                        </div>
                        <h5 class="fw-bold mb-1 text-body">Arraste seu arquivo CSV para cá</h5>
                        <p class="text-secondary small mb-3">ou clique para procurar no seu computador</p>
                        
                        <div class="d-flex align-items-center justify-content-center gap-2 flex-wrap">
                            <span class="badge bg-secondary-subtle text-secondary border"><i class="bi bi-filetype-csv me-1"></i>.csv</span>
                            <span class="badge bg-secondary-subtle text-secondary border"><i class="bi bi-filetype-txt me-1"></i>.txt</span>
                            <span class="badge bg-secondary-subtle text-secondary border"><i class="bi bi-arrows-expand me-1"></i>Separado por , ou ;</span>
                            <span class="badge bg-secondary-subtle text-secondary border"><i class="bi bi-hdd me-1"></i>Até 10 MB</span>
                        </div>
                        <input type="file" id="csv-file" class="csv-upload-dropzone__input" accept=".csv,.txt">
                    </div>

                    {{-- Card Informativo do Arquivo Selecionado --}}
                    <div id="file-info" class="d-none csv-file-card p-3 mb-3">
                        <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
                            <div class="d-flex align-items-center gap-3 min-w-0">
                                <div class="csv-file-badge">
                                    <i class="bi bi-file-earmark-spreadsheet-fill text-success fs-3"></i>
                                </div>
                                <div class="min-w-0">
                                    <div class="fw-bold text-truncate text-body fs-6" id="file-name">arquivo.csv</div>
                                    <div class="d-flex align-items-center gap-2 small text-secondary mt-1 flex-wrap">
                                        <span id="file-size"><i class="bi bi-hdd me-1"></i>0 KB</span>
                                        <span class="d-none d-sm-inline">•</span>
                                        <span id="file-delimiter-info" class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                            <i class="bi bi-funnel me-1"></i>Delimitador: Auto
                                        </span>
                                        <span class="d-none d-sm-inline">•</span>
                                        <span id="file-records-info" class="badge bg-success-subtle text-success border border-success-subtle">
                                            <i class="bi bi-check-circle me-1"></i>Pronto para análise
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <button type="button" class="btn btn-outline-danger btn-sm" id="btn-clear-file" title="Remover e escolher outro arquivo">
                                    <i class="bi bi-trash me-1"></i> Trocar Arquivo
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info border-info-subtle bg-info-subtle d-flex align-items-start gap-2 mb-0 small">
                        <i class="bi bi-info-circle-fill text-info mt-1 flex-shrink-0"></i>
                        <div>
                            <strong>Dica Importante:</strong> A primeira linha do arquivo deve conter os nomes das colunas (cabeçalho). O sistema tentará sugerir automaticamente o vínculo de campos com base nos nomes encontrados.
                        </div>
                    </div>
                </x-ui.premium-card>
            </div>

            {{-- ETAPA 2: MAPEAMENTO DE COLUNAS & PREVIEW --}}
            <div id="step-panel-2" class="d-none">
                <x-ui.premium-card title="Mapeamento de Colunas do CSV" subtitle="Associe as colunas encontradas no arquivo aos campos correspondentes do cadastro de clientes.">
                    <div class="alert alert-light border d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2 small">
                            <i class="bi bi-file-earmark-check text-primary fs-5"></i>
                            <div>
                                <span class="fw-bold text-body" id="mapping-summary-file">arquivo.csv</span>
                                <span class="text-secondary d-block" style="font-size: 0.75rem;" id="mapping-summary-details">Analisando colunas...</span>
                            </div>
                        </div>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-back-to-upload">
                            <i class="bi bi-arrow-left me-1"></i> Trocar Arquivo
                        </button>
                    </div>

                    {{-- Grid Dinâmico de Mapeamento --}}
                    <div class="mb-4">
                        <h6 class="fw-bold text-body small mb-3">
                            <i class="bi bi-diagram-3 me-1 text-primary"></i> Associação de Campos
                        </h6>
                        <div id="mapping-fields" class="row g-3">
                            {{-- Renderizado dinamicamente via JS --}}
                        </div>
                    </div>

                    <hr class="my-4">

                    {{-- Visualização Prévia (Preview das primeiras 5 linhas) --}}
                    <div class="mb-4">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 class="fw-bold text-body small m-0">
                                <i class="bi bi-eye me-1 text-primary"></i> Amostra dos Dados (Primeiras 5 Linhas)
                            </h6>
                            <span class="badge bg-secondary-subtle text-secondary border" style="font-size: 0.72rem;">Preview</span>
                        </div>
                        <div class="table-responsive border rounded overflow-hidden">
                            <table class="table table-bordered table-striped dt-responsive nowrap w-100 mb-0" id="preview-table">
                                {{-- Cabeçalho e linhas inseridos via JS --}}
                            </table>
                        </div>
                    </div>

                    {{-- Barra de Ação de Importação --}}
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 pt-3 border-top">
                        <button type="button" class="btn btn-outline-secondary" id="btn-cancel-mapping">
                            <i class="bi bi-x-circle me-1"></i> Cancelar
                        </button>
                        <button type="button" class="btn btn-success px-4" id="btn-import">
                            <i class="bi bi-check2-circle me-1"></i> Iniciar Importação
                        </button>
                    </div>
                </x-ui.premium-card>
            </div>

            {{-- ETAPA 3: RESULTADO DA IMPORTAÇÃO --}}
            <div id="step-panel-3" class="d-none">
                <x-ui.premium-card title="Resultado do Processamento" subtitle="Estatísticas detalhadas da importação em lote realizada.">
                    {{-- KPIs de Sucesso, Ignorados e Erros --}}
                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-4">
                            <div class="card h-100 border border-success-subtle shadow-sm">
                                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                                    <div>
                                        <span class="text-secondary small fw-bold text-uppercase d-block" style="font-size: 0.72rem; letter-spacing: 0.05em;">Importados / Atualizados</span>
                                        <strong class="fs-2 text-success d-block mt-1" id="stat-imported">0</strong>
                                        <small class="text-success"><i class="bi bi-check-circle me-1"></i>Processados com sucesso</small>
                                    </div>
                                    <div class="badge bg-success-subtle text-success p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                                        <i class="bi bi-check2-circle fs-3"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="card h-100 border border-warning-subtle shadow-sm">
                                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                                    <div>
                                        <span class="text-secondary small fw-bold text-uppercase d-block" style="font-size: 0.72rem; letter-spacing: 0.05em;">Ignorados / Sem Nome</span>
                                        <strong class="fs-2 text-warning d-block mt-1" id="stat-skipped">0</strong>
                                        <small class="text-warning"><i class="bi bi-skip-forward me-1"></i>Linhas ignoradas</small>
                                    </div>
                                    <div class="badge bg-warning-subtle text-warning p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                                        <i class="bi bi-skip-forward fs-3"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="card h-100 border border-danger-subtle shadow-sm">
                                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                                    <div>
                                        <span class="text-secondary small fw-bold text-uppercase d-block" style="font-size: 0.72rem; letter-spacing: 0.05em;">Erros / Inconsistências</span>
                                        <strong class="fs-2 text-danger d-block mt-1" id="stat-errors">0</strong>
                                        <small class="text-danger"><i class="bi bi-exclamation-octagon me-1"></i>Falhas de validação</small>
                                    </div>
                                    <div class="badge bg-danger-subtle text-danger p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                                        <i class="bi bi-exclamation-octagon fs-3"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Lista de Erros (se houver) --}}
                    <div id="result-errors-box" class="card border-danger-subtle mb-4 d-none shadow-sm">
                        <div class="card-header bg-danger-subtle text-danger d-flex align-items-center justify-content-between py-2">
                            <span class="fw-bold small"><i class="bi bi-exclamation-triangle-fill me-2"></i>Inconsistências Identificadas</span>
                            <span class="badge bg-danger" id="badge-total-errors">0 erro(s)</span>
                        </div>
                        <div class="card-body p-0">
                            <div id="result-errors-list" class="p-3 font-monospace small" style="max-height: 220px; overflow-y: auto;"></div>
                        </div>
                    </div>

                    {{-- Ações Finais --}}
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 pt-3 border-top">
                        <button type="button" class="btn btn-outline-secondary" id="btn-restart-import">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Importar Novo Arquivo
                        </button>
                        <a href="{{ route('admin.billing.customers.index') }}" class="btn btn-primary px-4">
                            <i class="bi bi-people me-1"></i> Ver Lista de Clientes
                        </a>
                    </div>
                </x-ui.premium-card>
            </div>

        </div>

        {{-- ======================================================== --}}
        {{-- COLUNA LATERAL DIREITA (AJUDA, REGRAS E MODELO) --}}
        {{-- ======================================================== --}}
        <div class="col-12 col-lg-4">

            {{-- Card: Campos Suportados --}}
            <x-ui.premium-card title="Campos Suportados" subtitle="Mapeie os dados do seu arquivo para os campos cadastrais disponíveis:">
                @php
                    $supportedCols = [
                        ['name', 'Nome', 'bi-person', 'Obrigatório', 'danger'],
                        ['whatsapp', 'WhatsApp', 'bi-whatsapp', 'Recomendado', 'warning'],
                        ['email', 'E-mail', 'bi-envelope', 'Opcional', 'secondary'],
                        ['document', 'CPF ou CNPJ', 'bi-card-text', 'Opcional', 'secondary'],
                        ['phone', 'Telefone Fixo', 'bi-telephone', 'Opcional', 'secondary'],
                        ['cep', 'CEP', 'bi-geo-alt', 'Opcional', 'secondary'],
                        ['logradouro', 'Logradouro / Rua', 'bi-signpost', 'Opcional', 'secondary'],
                        ['numero', 'Número', 'bi-hash', 'Opcional', 'secondary'],
                        ['complemento', 'Complemento', 'bi-building', 'Opcional', 'secondary'],
                        ['bairro', 'Bairro', 'bi-map', 'Opcional', 'secondary'],
                        ['cidade', 'Cidade', 'bi-pin-map', 'Opcional', 'secondary'],
                        ['uf', 'UF (Estado)', 'bi-flag', 'Opcional', 'secondary'],
                    ];
                @endphp
                <div class="d-flex flex-column gap-2">
                    @foreach($supportedCols as [$key, $label, $icon, $req, $color])
                        <div class="d-flex align-items-center justify-content-between p-2 rounded border" style="background: var(--bs-tertiary-bg); border-color: var(--bs-border-color) !important;">
                            <div class="d-flex align-items-center gap-2 min-w-0">
                                <i class="bi {{ $icon }} text-primary"></i>
                                <span class="small fw-semibold text-body text-truncate">{{ $label }}</span>
                            </div>
                            <span class="badge bg-{{ $color }}-subtle text-{{ $color }} border border-{{ $color }}-subtle flex-shrink-0" style="font-size: 0.68rem;">{{ $req }}</span>
                        </div>
                    @endforeach
                </div>
            </x-ui.premium-card>

            {{-- Card: Boas Práticas e Dicas --}}
            <x-ui.premium-card title="Boas Práticas" subtitle="Orientações para uma importação sem falhas." class="mt-4">
                <div class="d-flex flex-column gap-3 small text-secondary">
                    <div class="d-flex gap-2">
                        <i class="bi bi-check-circle-fill text-success flex-shrink-0 mt-1"></i>
                        <span><strong>Detecção Inteligente:</strong> O sistema reconhece delimitadores de vírgula (<code>,</code>) ou ponto e vírgula (<code>;</code>) automaticamente.</span>
                    </div>
                    <div class="d-flex gap-2">
                        <i class="bi bi-check-circle-fill text-success flex-shrink-0 mt-1"></i>
                        <span><strong>Deduplicação Automática:</strong> Se o cliente já existir (mesmo CPF/CNPJ ou e-mail), os dados cadastrais serão atualizados em vez de duplicados.</span>
                    </div>
                    <div class="d-flex gap-2">
                        <i class="bi bi-check-circle-fill text-success flex-shrink-0 mt-1"></i>
                        <span><strong>Higienização de Telefones:</strong> Números com parênteses, traços e espaços são limpos automaticamente para integração com WhatsApp.</span>
                    </div>
                    <div class="d-flex gap-2">
                        <i class="bi bi-check-circle-fill text-success flex-shrink-0 mt-1"></i>
                        <span><strong>Codificação:</strong> Compatível com arquivos em <code>UTF-8</code> e <code>ISO-8859-1 (Latin1)</code>.</span>
                    </div>
                </div>
            </x-ui.premium-card>

            {{-- Card: Download do Modelo --}}
            <x-ui.premium-card title="Modelo de Exemplo" subtitle="Baixe uma planilha modelo pré-configurada." class="mt-4">
                <div class="text-center py-2">
                    <p class="text-secondary small mb-3">Utilize nosso modelo de planilha CSV para organizar seus contatos antes da importação:</p>
                    <button type="button" class="btn btn-outline-primary w-100 py-2 d-flex align-items-center justify-content-center gap-2" id="btn-download-model">
                        <i class="bi bi-file-earmark-arrow-down fs-5"></i>
                        <span class="fw-semibold">Baixar modelo_clientes.csv</span>
                    </button>
                </div>
            </x-ui.premium-card>

        </div>

    </div>

</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrf = document.querySelector('meta[name=csrf-token]') ? document.querySelector('meta[name=csrf-token]').content : '';
    let csvFile = null;
    let csvMapping = {};

    const FIELDS = [
        ['name', 'Nome', 'Obrigatório', 'danger', 'bi-person'],
        ['whatsapp', 'WhatsApp', 'Recomendado', 'warning', 'bi-whatsapp'],
        ['email', 'E-mail', 'Opcional', 'secondary', 'bi-envelope'],
        ['document', 'CPF ou CNPJ', 'Opcional', 'secondary', 'bi-card-text'],
        ['phone', 'Telefone', 'Opcional', 'secondary', 'bi-telephone'],
        ['cep', 'CEP', 'Opcional', 'secondary', 'bi-geo-alt'],
        ['logradouro', 'Logradouro / Rua', 'Opcional', 'secondary', 'bi-signpost'],
        ['numero', 'Número', 'Opcional', 'secondary', 'bi-hash'],
        ['complemento', 'Complemento', 'Opcional', 'secondary', 'bi-building'],
        ['bairro', 'Bairro', 'Opcional', 'secondary', 'bi-map'],
        ['cidade', 'Cidade', 'Opcional', 'secondary', 'bi-pin-map'],
        ['uf', 'UF (Estado)', 'Opcional', 'secondary', 'bi-flag'],
    ];

    const dropZone = document.getElementById('csv-drop-zone');
    const fileInput = document.getElementById('csv-file');
    const fileInfo = document.getElementById('file-info');

    // Toast helper usando Toastify / window.toast global
    function notify(text, type = 'info') {
        if (typeof window.toast !== 'undefined' && typeof window.toast[type] === 'function') {
            window.toast[type](text);
        } else if (typeof Toastify === 'function') {
            const colors = {
                success: 'linear-gradient(to right, #198754, #157347)',
                danger: 'linear-gradient(to right, #dc3545, #b02a37)',
                warning: 'linear-gradient(to right, #ffc107, #e0a800)',
                info: 'linear-gradient(to right, #0dcaf0, #0aa2c0)'
            };
            Toastify({
                text: text,
                duration: 3500,
                gravity: 'top',
                position: 'right',
                style: { background: colors[type] || colors.info },
                stopOnFocus: true
            }).showToast();
        }
    }

    // Atualização dos indicadores do Stepper
    function setStep(stepNumber) {
        for (let i = 1; i <= 3; i++) {
            const nav = document.getElementById(`step-nav-${i}`);
            const panel = document.getElementById(`step-panel-${i}`);
            
            if (nav) {
                nav.classList.remove('active', 'completed');
                if (i < stepNumber) {
                    nav.classList.add('completed');
                    const numEl = nav.querySelector('.import-step__number');
                    if (numEl) numEl.innerHTML = '<i class="bi bi-check-lg"></i>';
                } else if (i === stepNumber) {
                    nav.classList.add('active');
                    const numEl = nav.querySelector('.import-step__number');
                    if (numEl) numEl.textContent = i;
                } else {
                    const numEl = nav.querySelector('.import-step__number');
                    if (numEl) numEl.textContent = i;
                }
            }

            if (panel) {
                if (i === stepNumber) {
                    panel.classList.remove('d-none');
                } else {
                    panel.classList.add('d-none');
                }
            }
        }
    }

    // Drag & Drop no dropzone
    if (dropZone) {
        ['dragenter', 'dragover'].forEach(evt => {
            dropZone.addEventListener(evt, e => {
                e.preventDefault();
                dropZone.classList.add('dragover');
            });
        });

        ['dragleave', 'drop'].forEach(evt => {
            dropZone.addEventListener(evt, e => {
                e.preventDefault();
                dropZone.classList.remove('dragover');
            });
        });

        dropZone.addEventListener('drop', e => {
            if (e.dataTransfer.files && e.dataTransfer.files.length) {
                handleFile(e.dataTransfer.files[0]);
            }
        });
    }

    if (fileInput) {
        fileInput.addEventListener('change', e => {
            if (e.target.files && e.target.files[0]) {
                handleFile(e.target.files[0]);
            }
        });
    }

    // Trocar / Limpar Arquivo
    function resetToUpload() {
        csvFile = null;
        csvMapping = {};
        if (fileInput) fileInput.value = '';
        if (fileInfo) fileInfo.classList.add('d-none');
        if (dropZone) dropZone.classList.remove('d-none');
        setStep(1);
    }

    document.getElementById('btn-clear-file')?.addEventListener('click', async () => {
        resetToUpload();
    });

    document.getElementById('btn-back-to-upload')?.addEventListener('click', async () => {
        if (typeof Swal !== 'undefined') {
            const confirmChange = await Swal.fire({
                title: 'Trocar Arquivo?',
                text: 'Deseja voltar e selecionar outro arquivo? O mapeamento atual será redefinido.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sim, Trocar',
                cancelButtonText: 'Manter Atual',
                confirmButtonColor: '#0d6efd',
                cancelButtonColor: '#6c757d',
                reverseButtons: true
            });
            if (!confirmChange.isConfirmed) return;
        }
        resetToUpload();
    });

    document.getElementById('btn-cancel-mapping')?.addEventListener('click', () => {
        resetToUpload();
    });

    document.getElementById('btn-restart-import')?.addEventListener('click', () => {
        resetToUpload();
    });

    // Processamento do Arquivo e Envio de Preview
    async function handleFile(file) {
        const ext = file.name.split('.').pop().toLowerCase();
        if (!['csv', 'txt'].includes(ext)) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Formato Não Suportado',
                    text: 'Por favor, selecione um arquivo no formato .csv ou .txt.',
                    confirmButtonText: 'Entendido',
                    confirmButtonColor: '#dc3545'
                });
            } else {
                notify('Formato inválido. Use .csv ou .txt.', 'danger');
            }
            return;
        }

        if (file.size > 10 * 1024 * 1024) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Arquivo Muito Grande',
                    text: 'O tamanho máximo permitido para o arquivo é de 10 MB.',
                    confirmButtonText: 'Entendido',
                    confirmButtonColor: '#dc3545'
                });
            } else {
                notify('O arquivo ultrapassa o limite de 10 MB.', 'danger');
            }
            return;
        }

        csvFile = file;

        // Atualiza UI de Arquivo Selecionado
        document.getElementById('file-name').textContent = file.name;
        document.getElementById('file-size').innerHTML = `<i class="bi bi-hdd me-1"></i>${(file.size / 1024).toFixed(1)} KB`;
        if (dropZone) dropZone.classList.add('d-none');
        if (fileInfo) fileInfo.classList.remove('d-none');

        // Loading visual com SweetAlert2
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Analisando Arquivo...',
                text: 'Lendo cabeçalhos e preparando visualização prévia...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
        }

        try {
            const fd = new FormData();
            fd.append('file', file);

            const response = await fetch('{{ route("admin.import.customers.preview") }}', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json'
                },
                body: fd
            });

            const data = await response.json();
            if (typeof Swal !== 'undefined') Swal.close();

            if (!data.ok) {
                notify(data.error || 'Falha ao processar o arquivo CSV.', 'danger');
                resetToUpload();
                return;
            }

            // Atualiza tags de delimitador e total de linhas
            const delimLabel = data.delimiter === ';' ? 'Ponto e vírgula (;)' : 'Vírgula (,)';
            const delimEl = document.getElementById('file-delimiter-info');
            if (delimEl) delimEl.innerHTML = `<i class="bi bi-funnel me-1"></i>Delimitador: ${delimLabel}`;

            const recsEl = document.getElementById('file-records-info');
            if (recsEl) recsEl.innerHTML = `<i class="bi bi-check-circle me-1"></i>${data.preview.length}+ linhas identificadas`;

            // Configura Step 2
            csvMapping = data.mapping || {};
            document.getElementById('mapping-summary-file').textContent = file.name;
            document.getElementById('mapping-summary-details').textContent = `${data.headers.length} colunas encontradas • Delimitador: ${delimLabel}`;

            renderMapping(data.headers, data.preview);
            renderPreview(data.headers, data.preview);

            setStep(2);
            notify(`${data.headers.length} colunas identificadas com sucesso!`, 'success');

        } catch (err) {
            if (typeof Swal !== 'undefined') Swal.close();
            console.error('Erro na requisição de preview:', err);
            notify('Erro ao comunicar com o servidor.', 'danger');
            resetToUpload();
        }
    }

    // Renderização dos Selects de Mapeamento
    function renderMapping(headers, previewRows) {
        const container = document.getElementById('mapping-fields');
        if (!container) return;

        // Amostra rápida da primeira linha para ajudar o usuário
        const firstRow = previewRows && previewRows.length ? previewRows[0] : {};

        container.innerHTML = FIELDS.map(([key, label, req, color, icon]) => {
            const mappedHeader = csvMapping[key] || '';
            const opts = headers.map(h => {
                const isSelected = (mappedHeader === h) ? 'selected' : '';
                return `<option value="${h}" ${isSelected}>${h}</option>`;
            }).join('');

            const sampleVal = mappedHeader && firstRow[mappedHeader] ? String(firstRow[mappedHeader]).trim() : '';

            return `
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="mapping-box h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="form-label mb-0 fw-semibold d-flex align-items-center gap-2 small text-body">
                                    <i class="bi ${icon} text-primary"></i> ${label}
                                </span>
                                <span class="badge bg-${color}-subtle text-${color} border border-${color}-subtle" style="font-size:0.68rem;">${req}</span>
                            </div>
                            <select class="form-select" data-field="${key}">
                                <option value="">-- Ignorar / Não Importar --</option>
                                ${opts}
                            </select>
                        </div>
                        <div class="small text-secondary mt-2 text-truncate" style="font-size: 0.72rem;" id="sample-text-${key}">
                            ${sampleVal ? `<i class="bi bi-arrow-right-short text-primary me-1"></i>Ex: <span class="text-body fw-semibold">${sampleVal}</span>` : '<span class="opacity-50">Nenhum dado selecionado</span>'}
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        // Eventos de alteração dos selects para atualizar o objeto de mapeamento
        container.querySelectorAll('[data-field]').forEach(sel => {
            sel.addEventListener('change', e => {
                const fieldKey = e.target.dataset.field;
                const selectedVal = e.target.value || null;
                csvMapping[fieldKey] = selectedVal;

                // Atualiza amostra
                const sampleEl = document.getElementById(`sample-text-${fieldKey}`);
                if (sampleEl) {
                    const sample = selectedVal && firstRow[selectedVal] ? String(firstRow[selectedVal]).trim() : '';
                    sampleEl.innerHTML = sample
                        ? `<i class="bi bi-arrow-right-short text-primary me-1"></i>Ex: <span class="text-body fw-semibold">${sample}</span>`
                        : '<span class="opacity-50">Nenhum dado selecionado</span>';
                }
            });
        });
    }

    // Renderização da Tabela de Preview
    function renderPreview(headers, rows) {
        const table = document.getElementById('preview-table');
        if (!table) return;

        const thead = '<thead class="table-light"><tr>' + headers.map(h => `<th class="small text-nowrap"><i class="bi bi-arrow-down-short text-primary me-1"></i>${h}</th>`).join('') + '</tr></thead>';
        const tbody = '<tbody>' + rows.slice(0, 5).map(row => {
            return '<tr>' + headers.map(h => {
                const cellVal = row[h] !== null && row[h] !== undefined ? String(row[h]).trim() : '';
                return `<td class="small text-nowrap">${cellVal ? cellVal : '<span class="text-secondary opacity-50">—</span>'}</td>`;
            }).join('') + '</tr>';
        }).join('') + '</tbody>';

        table.innerHTML = thead + tbody;
    }

    // Execução da Importação de Fato
    document.getElementById('btn-import')?.addEventListener('click', async () => {
        if (!csvFile) {
            notify('Nenhum arquivo carregado.', 'warning');
            return;
        }

        // Validação obrigatória: campo "name"
        if (!csvMapping['name']) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Coluna Nome Obrigatória',
                    text: 'O campo "Nome" é obrigatório para cadastrar clientes. Por favor, vincule uma coluna do seu arquivo para o campo Nome.',
                    confirmButtonText: 'Entendido',
                    confirmButtonColor: '#0d6efd'
                });
            } else {
                notify('O campo Nome é obrigatório!', 'warning');
            }
            return;
        }

        // Confirmação com SweetAlert2
        if (typeof Swal !== 'undefined') {
            const confirmImport = await Swal.fire({
                title: 'Iniciar Importação?',
                text: `Deseja importar os clientes do arquivo "${csvFile.name}" com o mapeamento configurado?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '<i class="bi bi-check2-circle me-1"></i> Sim, Importar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#198754',
                cancelButtonColor: '#6c757d',
                reverseButtons: true
            });
            if (!confirmImport.isConfirmed) return;
        }

        const btn = document.getElementById('btn-import');
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Processando Importação...';

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Importando Clientes...',
                text: 'Processando deduplicações e gravando registros no banco de dados. Aguarde um instante...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
        }

        try {
            const fd = new FormData();
            fd.append('file', csvFile);
            Object.entries(csvMapping).forEach(([k, v]) => {
                if (v) fd.append(`mapping[${k}]`, v);
            });

            const response = await fetch('{{ route("admin.import.customers.import") }}', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json'
                },
                body: fd
            });

            const res = await response.json();
            btn.disabled = false;
            btn.innerHTML = originalHtml;
            if (typeof Swal !== 'undefined') Swal.close();

            if (res.ok) {
                const s = res.stats;
                document.getElementById('stat-imported').textContent = s.imported || 0;
                document.getElementById('stat-skipped').textContent = s.skipped || 0;
                document.getElementById('stat-errors').textContent = (s.errors && s.errors.length) ? s.errors.length : 0;

                const errorsBox = document.getElementById('result-errors-box');
                const errorsList = document.getElementById('result-errors-list');
                const badgeErrors = document.getElementById('badge-total-errors');

                if (s.errors && s.errors.length > 0) {
                    if (errorsBox) errorsBox.classList.remove('d-none');
                    if (badgeErrors) badgeErrors.textContent = `${s.errors.length} erro(s)`;
                    if (errorsList) {
                        errorsList.innerHTML = s.errors.map(e => `<div><i class="bi bi-x-circle text-danger me-1"></i>${e}</div>`).join('');
                    }
                } else {
                    if (errorsBox) errorsBox.classList.add('d-none');
                }

                setStep(3);

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Importação Concluída!',
                        html: `<b>${s.imported}</b> cliente(s) importados ou atualizados com sucesso.<br><small class="text-secondary">${s.skipped} ignorados • ${s.errors ? s.errors.length : 0} inconsistências</small>`,
                        confirmButtonText: 'Ver Resultados',
                        confirmButtonColor: '#198754'
                    });
                }
                notify(`${s.imported} clientes importados com sucesso!`, 'success');

            } else {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro na Importação',
                        text: res.error || 'Ocorreu uma falha ao importar os dados.',
                        confirmButtonText: 'Entendido',
                        confirmButtonColor: '#dc3545'
                    });
                } else {
                    notify(res.error || 'Falha na importação.', 'danger');
                }
            }

        } catch (err) {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
            if (typeof Swal !== 'undefined') Swal.close();
            console.error('Erro na importação:', err);
            notify('Erro inesperado na comunicação com o servidor.', 'danger');
        }
    });

    // Download do Modelo CSV
    const downloadModelHandler = (e) => {
        e.preventDefault();
        const csvContent = 'nome;whatsapp;email;cpf_cnpj;telefone;cep;logradouro;numero;complemento;bairro;cidade;uf\n' +
                           'João da Silva;11999998888;joao.silva@exemplo.com.br;12345678909;1133334444;01001000;Praça da Sé;100;Sala 501;Sé;São Paulo;SP\n' +
                           'Maria Oliveira;21988887777;maria.oliveira@exemplo.com.br;98765432100;;20040002;Avenida Rio Branco;156;;Centro;Rio de Janeiro;RJ\n';
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'modelo_importacao_clientes.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        notify('Modelo de planilha CSV baixado com sucesso!', 'info');
    };

    document.getElementById('btn-download-model')?.addEventListener('click', downloadModelHandler);
    document.getElementById('btn-top-download-model')?.addEventListener('click', downloadModelHandler);
});
</script>
@endpush
