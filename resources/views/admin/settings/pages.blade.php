@extends('admin.layouts.app')

@section('title', 'Paginas do Site')

@php
    $home = $content['home'] ?? [];
    $pacotes = $content['pacotes'] ?? [];
    $faq = $content['faq'] ?? [];
    $contato = $content['contato'] ?? [];
    $jsonPages = [
        'sobre' => 'Sobre',
        'recursos' => 'Recursos',
        'integracoes' => 'Integracoes',
        'marketplace' => 'Marketplace',
        'funil' => 'Funil',
        'blog' => 'Blog',
        'changelog' => 'Changelog',
        'privacidade' => 'Privacidade',
        'termos' => 'Termos',
        'cookies' => 'Cookies',
    ];
@endphp

@section('content')
<div class="container-fluid">
    <x-ui.page-header
        title="Paginas do Site"
        eyebrow="CMS do frontend"
        subtitle="Edite o texto e as listas principais do React publico sem mexer no codigo. Os blocos em JSON mantem o formato padrao do sistema."
    />

    {{-- Card de Ativação / Desativação Individual das Páginas --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-transparent py-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <h5 class="fw-bold mb-0">
                    <i class="bi bi-toggle2-on text-primary me-2"></i> Controle de Ativação das Páginas do Site
                </h5>
                <small class="text-muted">Ative ou desative páginas individuais em tempo real. Páginas desativadas são ocultadas dos menus públicos e bloqueadas para acesso direto.</small>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-sm btn-outline-success" onclick="toggleAllPages(true)">
                    <i class="bi bi-check2-all me-1"></i> Ativar Todas
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="toggleAllPages(false)">
                    <i class="bi bi-x-circle me-1"></i> Desativar Todas
                </button>
            </div>
        </div>
        <div class="card-body">
            {{-- Filtros de Categoria --}}
            <div class="d-flex gap-2 mb-4 flex-wrap" id="category-filters">
                <button type="button" class="btn btn-sm btn-primary active" data-filter="all">Todas as Páginas ({{ count($catalog ?? []) }})</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-filter="Principal">Principal</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-filter="Vendas">Vendas</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-filter="Produto">Produto</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-filter="Institucional">Institucional</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-filter="Suporte">Suporte & Ajuda</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-filter="Legal">Legal</button>
            </div>

            <div class="row g-3" id="pages-grid">
                @foreach(($catalog ?? []) as $slug => $pageInfo)
                @php
                    $isActive = $statuses[$slug] ?? true;
                @endphp
                <div class="col-md-6 col-lg-4 col-xxl-3 page-card-col" data-category="{{ $pageInfo['category'] }}">
                    <div class="card h-100 border transition-all" id="page-card-{{ $slug }}" style="background: {{ $isActive ? 'var(--bs-card-bg)' : 'rgba(0,0,0,0.02)' }};">
                        <div class="card-body p-3 d-flex flex-column justify-content-between">
                            <div>
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-3 p-2 bg-primary bg-opacity-10 text-primary">
                                            <i class="bi {{ $pageInfo['icon'] }} fs-5"></i>
                                        </div>
                                        <div>
                                            <h6 class="fw-bold mb-0 text-truncate" style="max-width: 150px;">{{ $pageInfo['name'] }}</h6>
                                            <span class="badge bg-light text-muted border font-monospace" style="font-size: 0.72rem;">{{ $pageInfo['route'] }}</span>
                                        </div>
                                    </div>
                                    <div class="form-check form-switch m-0">
                                        <input class="form-check-input page-toggle-switch" type="checkbox" role="switch"
                                            id="switch-{{ $slug }}"
                                            data-slug="{{ $slug }}"
                                            data-name="{{ $pageInfo['name'] }}"
                                            {{ $isActive ? 'checked' : '' }}
                                            style="cursor: pointer; width: 2.5em; height: 1.3em;">
                                    </div>
                                </div>
                                <p class="text-muted small mb-3" style="font-size: 0.8rem; line-height: 1.3;">
                                    {{ $pageInfo['description'] }}
                                </p>
                            </div>
                            <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                                <span class="badge {{ $isActive ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle' }}" id="badge-{{ $slug }}">
                                    <i class="bi {{ $isActive ? 'bi-check-circle-fill' : 'bi-eye-slash-fill' }} me-1"></i>
                                    <span>{{ $isActive ? 'Página Ativa' : 'Desativada' }}</span>
                                </span>
                                <a href="{{ $pageInfo['route'] }}" target="_blank" class="btn btn-sm btn-link text-decoration-none p-0 small">
                                    Ver no site <i class="bi bi-box-arrow-up-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-xl-8">
            <x-ui.premium-card title="Home" subtitle="Hero, blocos principais e CTA final.">
                <form class="page-content-form" data-page="home">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Kicker</label><input class="form-control" name="hero.kicker" value="{{ data_get($home, 'hero.kicker') }}"></div>
                        <div class="col-md-6"><label class="form-label">Titulo antes do destaque</label><input class="form-control" name="hero.title_before" value="{{ data_get($home, 'hero.title_before') }}"></div>
                        <div class="col-md-6"><label class="form-label">Titulo destacado</label><input class="form-control" name="hero.title_highlight" value="{{ data_get($home, 'hero.title_highlight') }}"></div>
                        <div class="col-md-6"><label class="form-label">Titulo final</label><input class="form-control" name="hero.title_after" value="{{ data_get($home, 'hero.title_after') }}"></div>
                        <div class="col-12"><label class="form-label">Descricao</label><textarea class="form-control" rows="3" name="hero.description">{{ data_get($home, 'hero.description') }}</textarea></div>
                        <div class="col-md-6"><label class="form-label">CTA primario</label><input class="form-control" name="hero.primary_cta_label" value="{{ data_get($home, 'hero.primary_cta_label') }}"></div>
                        <div class="col-md-6"><label class="form-label">URL CTA primario</label><input class="form-control" name="hero.primary_cta_url" value="{{ data_get($home, 'hero.primary_cta_url') }}"></div>
                        <div class="col-md-6"><label class="form-label">CTA secundario</label><input class="form-control" name="hero.secondary_cta_label" value="{{ data_get($home, 'hero.secondary_cta_label') }}"></div>
                        <div class="col-md-6"><label class="form-label">URL CTA secundario</label><input class="form-control" name="hero.secondary_cta_url" value="{{ data_get($home, 'hero.secondary_cta_url') }}"></div>
                        <div class="col-12"><label class="form-label">Features (JSON)</label><textarea class="form-control font-monospace" rows="8" name="features_json">@json(data_get($home, 'features'), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)</textarea></div>
                        <div class="col-12"><label class="form-label">Metricas (JSON)</label><textarea class="form-control font-monospace" rows="6" name="metrics_json">@json(data_get($home, 'metrics'), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)</textarea></div>
                        <div class="col-12"><label class="form-label">Titulo dos depoimentos</label><input class="form-control" name="testimonials_title" value="{{ data_get($home, 'testimonials_title') }}"></div>
                        <div class="col-12"><label class="form-label">Titulo CTA final</label><input class="form-control" name="final_cta.title" value="{{ data_get($home, 'final_cta.title') }}"></div>
                        <div class="col-12"><label class="form-label">Descricao CTA final</label><textarea class="form-control" rows="3" name="final_cta.description">{{ data_get($home, 'final_cta.description') }}</textarea></div>
                    </div>
                    <div class="mt-3 d-flex justify-content-end"><button class="btn btn-primary"><i class="bi bi-save me-1"></i>Salvar Home</button></div>
                </form>
            </x-ui.premium-card>

            <x-ui.premium-card title="Pacotes" subtitle="Hero e textos de comparativo.">
                <form class="page-content-form" data-page="pacotes">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Kicker</label><input class="form-control" name="hero.kicker" value="{{ data_get($pacotes, 'hero.kicker') }}"></div>
                        <div class="col-md-6"><label class="form-label">Titulo antes do destaque</label><input class="form-control" name="hero.title_before" value="{{ data_get($pacotes, 'hero.title_before') }}"></div>
                        <div class="col-md-6"><label class="form-label">Titulo destacado</label><input class="form-control" name="hero.title_highlight" value="{{ data_get($pacotes, 'hero.title_highlight') }}"></div>
                        <div class="col-12"><label class="form-label">Descricao</label><textarea class="form-control" rows="3" name="hero.description">{{ data_get($pacotes, 'hero.description') }}</textarea></div>
                        <div class="col-md-6"><label class="form-label">Titulo comparativo</label><input class="form-control" name="comparison.title" value="{{ data_get($pacotes, 'comparison.title') }}"></div>
                        <div class="col-md-6"><label class="form-label">Descricao comparativo</label><input class="form-control" name="comparison.description" value="{{ data_get($pacotes, 'comparison.description') }}"></div>
                        <div class="col-md-6"><label class="form-label">Kicker sandbox</label><input class="form-control" name="sandbox.kicker" value="{{ data_get($pacotes, 'sandbox.kicker') }}"></div>
                        <div class="col-md-6"><label class="form-label">Titulo sandbox</label><input class="form-control" name="sandbox.title" value="{{ data_get($pacotes, 'sandbox.title') }}"></div>
                        <div class="col-12"><label class="form-label">Descricao sandbox</label><textarea class="form-control" rows="2" name="sandbox.description">{{ data_get($pacotes, 'sandbox.description') }}</textarea></div>
                    </div>
                    <div class="mt-3 d-flex justify-content-end"><button class="btn btn-primary"><i class="bi bi-save me-1"></i>Salvar Pacotes</button></div>
                </form>
            </x-ui.premium-card>

            <x-ui.premium-card title="FAQ" subtitle="Titulo, placeholder e lista de perguntas.">
                <form class="page-content-form" data-page="faq">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Kicker</label><input class="form-control" name="hero.kicker" value="{{ data_get($faq, 'hero.kicker') }}"></div>
                        <div class="col-md-6"><label class="form-label">Titulo antes do destaque</label><input class="form-control" name="hero.title_before" value="{{ data_get($faq, 'hero.title_before') }}"></div>
                        <div class="col-md-6"><label class="form-label">Titulo destacado</label><input class="form-control" name="hero.title_highlight" value="{{ data_get($faq, 'hero.title_highlight') }}"></div>
                        <div class="col-md-6"><label class="form-label">Placeholder da busca</label><input class="form-control" name="hero.search_placeholder" value="{{ data_get($faq, 'hero.search_placeholder') }}"></div>
                        <div class="col-12"><label class="form-label">Mensagem vazia</label><input class="form-control" name="hero.empty_message" value="{{ data_get($faq, 'hero.empty_message') }}"></div>
                        <div class="col-12"><label class="form-label">Itens FAQ (JSON)</label><textarea class="form-control font-monospace" rows="8" name="items_json">@json(data_get($faq, 'items'), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)</textarea></div>
                    </div>
                    <div class="mt-3 d-flex justify-content-end"><button class="btn btn-primary"><i class="bi bi-save me-1"></i>Salvar FAQ</button></div>
                </form>
            </x-ui.premium-card>
        </div>

        <div class="col-12 col-xl-4">
            <x-ui.premium-card title="Contato" subtitle="Hero, canais e horarios exibidos no frontend.">
                <form class="page-content-form" data-page="contato">
                    <div class="row g-3">
                        <div class="col-12"><label class="form-label">Kicker</label><input class="form-control" name="hero.kicker" value="{{ data_get($contato, 'hero.kicker') }}"></div>
                        <div class="col-12"><label class="form-label">Titulo antes do destaque</label><input class="form-control" name="hero.title_before" value="{{ data_get($contato, 'hero.title_before') }}"></div>
                        <div class="col-12"><label class="form-label">Titulo destacado</label><input class="form-control" name="hero.title_highlight" value="{{ data_get($contato, 'hero.title_highlight') }}"></div>
                        <div class="col-12"><label class="form-label">Descricao</label><textarea class="form-control" rows="3" name="hero.description">{{ data_get($contato, 'hero.description') }}</textarea></div>
                        <div class="col-12"><label class="form-label">Canais (JSON)</label><textarea class="form-control font-monospace" rows="10" name="channels_json">@json(data_get($contato, 'channels'), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)</textarea></div>
                        <div class="col-12"><label class="form-label">Horarios (JSON)</label><textarea class="form-control font-monospace" rows="6" name="schedules_json">@json(data_get($contato, 'schedules'), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)</textarea></div>
                        <div class="col-12"><label class="form-label">Titulo do selo de resposta</label><input class="form-control" name="response.title" value="{{ data_get($contato, 'response.title') }}"></div>
                        <div class="col-12"><label class="form-label">Texto auxiliar</label><input class="form-control" name="response.hint" value="{{ data_get($contato, 'response.hint') }}"></div>
                    </div>
                    <div class="mt-3 d-flex justify-content-end"><button class="btn btn-primary"><i class="bi bi-save me-1"></i>Salvar Contato</button></div>
                </form>
            </x-ui.premium-card>

            <x-ui.premium-card title="Padrao" subtitle="Use JSON apenas para listas repetidas. O restante fica em campos diretos.">
                <ul class="small text-muted mb-0 ps-3">
                    <li>Cada item de FAQ usa <code>q</code> e <code>a</code>.</li>
                    <li>Canais de contato usam <code>icon</code>, <code>title</code>, <code>sub</code>, <code>info</code> e <code>href</code>.</li>
                    <li>Features da home usam <code>icon</code>, <code>title</code>, <code>desc</code> e <code>gradient</code>.</li>
                    <li>As alteracoes passam direto para o React publico via <code>/api/site</code>.</li>
                </ul>
            </x-ui.premium-card>
        </div>
    </div>

    <div class="row g-4 mt-1">
        <div class="col-12">
            <x-ui.premium-card title="Paginas adicionais do frontend" subtitle="Edicao completa em JSON para as demais rotas do React publico. O layout continua o mesmo; voce ajusta o conteudo, listas e blocos exibidos.">
                <div class="accordion" id="frontend-pages-json">
                    @foreach($jsonPages as $pageKey => $pageLabel)
                        <div class="accordion-item premium-accordion-item">
                            <h2 class="accordion-header" id="heading-{{ $pageKey }}">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-{{ $pageKey }}" aria-expanded="false" aria-controls="collapse-{{ $pageKey }}">
                                    {{ $pageLabel }}
                                </button>
                            </h2>
                            <div id="collapse-{{ $pageKey }}" class="accordion-collapse collapse" aria-labelledby="heading-{{ $pageKey }}" data-bs-parent="#frontend-pages-json">
                                <div class="accordion-body">
                                    <form class="page-json-form" data-page="{{ $pageKey }}">
                                        <label class="form-label">Estrutura JSON da pagina {{ $pageLabel }}</label>
                                        <textarea class="form-control font-monospace" rows="18" name="payload_json">@json($content[$pageKey] ?? new \stdClass(), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)</textarea>
                                        <div class="small text-muted mt-2">
                                            Salve somente um objeto JSON valido. O frontend faz merge com os defaults atuais do codigo.
                                        </div>
                                        <div class="mt-3 d-flex justify-content-end">
                                            <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Salvar {{ $pageLabel }}</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-ui.premium-card>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.premium-accordion-item {
    background: transparent;
    border: 1px solid var(--bs-border-color);
    border-radius: 1rem;
    overflow: hidden;
}

.premium-accordion-item + .premium-accordion-item {
    margin-top: .85rem;
}

.premium-accordion-item .accordion-button {
    background: var(--bs-body-bg);
    color: var(--bs-body-color);
    box-shadow: none;
    font-weight: 600;
}

.premium-accordion-item .accordion-button:not(.collapsed) {
    background: color-mix(in srgb, var(--bs-primary) 10%, var(--bs-body-bg));
    color: var(--bs-body-color);
}

.premium-accordion-item .accordion-body {
    background: var(--bs-body-bg);
}
</style>
@endpush

@push('scripts')
<script>
document.querySelectorAll('.page-content-form').forEach((form) => {
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const page = form.dataset.page;
        const payload = {};

        for (const [key, value] of new FormData(form).entries()) {
            if (key.endsWith('_json')) {
                const targetKey = key.replace('_json', '');
                try {
                    assignNested(payload, targetKey, JSON.parse(String(value || '[]')));
                } catch (error) {
                    window.toast?.error(`JSON invalido em ${targetKey}.`);
                    return;
                }
                continue;
            }

            assignNested(payload, key, value);
        }

        const response = await fetch(`{{ url('/admin/settings/pages') }}/${page}`, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ payload })
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.ok) {
            window.toast?.error(data.message || 'Nao foi possivel salvar a pagina.');
            return;
        }
        window.toast?.success(data.message || 'Pagina atualizada.');
    });
});

document.querySelectorAll('.page-json-form').forEach((form) => {
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const page = form.dataset.page;
        let payload = {};

        try {
            payload = JSON.parse(form.querySelector('[name="payload_json"]').value || '{}');
        } catch (error) {
            window.toast?.error('JSON invalido nesta pagina.');
            return;
        }

        const response = await fetch(`{{ url('/admin/settings/pages') }}/${page}`, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ payload })
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.ok) {
            window.toast?.error(data.message || 'Nao foi possivel salvar a pagina.');
            return;
        }
        window.toast?.success(data.message || 'Pagina atualizada.');
    });
});

function assignNested(target, dotted, value) {
    const parts = dotted.split('.');
    let current = target;
    parts.forEach((part, index) => {
        if (index === parts.length - 1) {
            current[part] = value;
            return;
        }
        current[part] = current[part] || {};
        current = current[part];
    });
}

// Filtros de Categoria de Páginas
document.querySelectorAll('#category-filters button').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('#category-filters button').forEach(b => {
            b.classList.remove('btn-primary', 'active');
            b.classList.add('btn-outline-secondary');
        });
        btn.classList.remove('btn-outline-secondary');
        btn.classList.add('btn-primary', 'active');
        
        const filter = btn.dataset.filter;
        document.querySelectorAll('.page-card-col').forEach(col => {
            if (filter === 'all' || col.dataset.category === filter) {
                col.style.display = '';
            } else {
                col.style.display = 'none';
            }
        });
    });
});

// Toggle Individual de Status da Página
document.querySelectorAll('.page-toggle-switch').forEach(sw => {
    sw.addEventListener('change', async function() {
        const slug = this.dataset.slug;
        const name = this.dataset.name;
        const active = this.checked;
        
        if (!active && slug === 'home') {
            const confirm = await Swal.fire({
                title: 'Atenção: Desativar a Página Inicial?',
                text: 'Desativar a Home (/) exibirá um aviso de página indisponível na página principal.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, desativar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545'
            });
            if (!confirm.isConfirmed) {
                this.checked = true;
                return;
            }
        }
        
        try {
            const response = await fetch('{{ route('admin.settings.pages.toggle') }}', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ slug, active })
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok || !data.ok) {
                this.checked = !active;
                Swal.fire({
                    title: 'Erro',
                    text: data.message || 'Erro ao alterar status da página.',
                    icon: 'error'
                });
                return;
            }
            
            // Atualizar UI do card
            const card = document.getElementById(`page-card-${slug}`);
            const badge = document.getElementById(`badge-${slug}`);
            if (card) {
                card.style.background = active ? 'var(--bs-card-bg)' : 'rgba(0,0,0,0.02)';
            }
            if (badge) {
                if (active) {
                    badge.className = 'badge bg-success-subtle text-success border border-success-subtle';
                    badge.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i><span>Página Ativa</span>';
                } else {
                    badge.className = 'badge bg-danger-subtle text-danger border border-danger-subtle';
                    badge.innerHTML = '<i class="bi bi-eye-slash-fill me-1"></i><span>Desativada</span>';
                }
            }
            
            window.toast?.success(data.message || `Status da página "${name}" atualizado.`);
        } catch (err) {
            this.checked = !active;
            window.toast?.error('Erro de conexão ao alterar status.');
        }
    });
});

// Toggle em Massa de Todas as Páginas
window.toggleAllPages = async function(active) {
    const actionText = active ? 'ativar todas' : 'desativar todas';
    const confirm = await Swal.fire({
        title: `Deseja realmente ${actionText} as páginas?`,
        text: active 
            ? 'Todas as páginas do site público serão ativadas e exibidas normalmente nos menus.' 
            : 'Todas as páginas do site serão desativadas e bloqueadas temporariamente.',
        icon: active ? 'question' : 'warning',
        showCancelButton: true,
        confirmButtonText: active ? 'Sim, ativar todas' : 'Sim, desativar todas',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: active ? '#198754' : '#dc3545'
    });
    
    if (!confirm.isConfirmed) return;
    
    try {
        const response = await fetch('{{ route('admin.settings.pages.toggle-all') }}', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ active })
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.ok) {
            Swal.fire({
                title: 'Erro',
                text: data.message || 'Erro ao atualizar todas as páginas.',
                icon: 'error'
            });
            return;
        }
        
        // Atualiza todos os switches e badges visualmente
        document.querySelectorAll('.page-toggle-switch').forEach(sw => {
            sw.checked = active;
            const slug = sw.dataset.slug;
            const card = document.getElementById(`page-card-${slug}`);
            const badge = document.getElementById(`badge-${slug}`);
            if (card) {
                card.style.background = active ? 'var(--bs-card-bg)' : 'rgba(0,0,0,0.02)';
            }
            if (badge) {
                if (active) {
                    badge.className = 'badge bg-success-subtle text-success border border-success-subtle';
                    badge.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i><span>Página Ativa</span>';
                } else {
                    badge.className = 'badge bg-danger-subtle text-danger border border-danger-subtle';
                    badge.innerHTML = '<i class="bi bi-eye-slash-fill me-1"></i><span>Desativada</span>';
                }
            }
        });
        
        Swal.fire({
            title: 'Sucesso!',
            text: data.message || `Todas as páginas foram ${active ? 'ativadas' : 'desativadas'}.`,
            icon: 'success',
            timer: 2000,
            showConfirmButton: false
        });
    } catch (err) {
        Swal.fire({
            title: 'Erro',
            text: 'Falha de comunicação com o servidor.',
            icon: 'error'
        });
    }
};
</script>
@endpush
