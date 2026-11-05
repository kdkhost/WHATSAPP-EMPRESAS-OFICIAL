@extends('admin.layouts.app')
@section('title', 'Menu Footer PWA')

@push('head')
<style>
.pwa-menu-page {}
.pwa-menu-item { border: 1px solid var(--bs-border-color); border-radius: 12px; padding: 1rem; background: var(--bs-body-bg); }
.pwa-menu-item--disabled { opacity: .6; }
.pwa-menu-item__handle { cursor: grab; color: var(--bs-secondary-color); }
.pwa-menu-item__handle:active { cursor: grabbing; }
</style>
@endpush

@section('content')
<div class="container-fluid pwa-menu-page">
    <section class="admin-premium-hero mb-4">
        <div>
            <span class="admin-premium-hero__eyebrow">Navegação do app</span>
            <h1 class="admin-premium-hero__title">Menu inferior do PWA</h1>
            <p class="admin-premium-hero__text">
                Controle quais botoes aparecem no footer do PWA, em qual ordem, qual icone, pagina de destino e quem pode ver.
            </p>
        </div>
        <div class="dashboard-hero-notes">
            <div class="dashboard-note">
                <span class="dashboard-note__label">Arraste</span>
                <span class="dashboard-note__value">Para reordenar os itens</span>
            </div>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Visibilidade</span>
                <span class="dashboard-note__value">Tenant, admin ou ambos</span>
            </div>
        </div>
    </section>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="premium-panel">
                <div class="premium-panel__header">
                    <div>
                        <span class="premium-panel__kicker">Itens atuais</span>
                        <h2 class="premium-panel__title">Footer do PWA</h2>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" onclick="openModal()">
                        <i class="bi bi-plus-lg me-1"></i> Novo item
                    </button>
                </div>

                <div id="menu-list" class="d-flex flex-column gap-3">
                    @forelse($items as $item)
                    <div class="pwa-menu-item d-flex align-items-center gap-3" data-id="{{ $item->id }}">
                        <span class="pwa-menu-item__handle"><i class="bi bi-grip-vertical"></i></span>
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center gap-2">
                                <i class="{{ $item->icon }}"></i>
                                <strong>{{ $item->label }}</strong>
                                @if(! $item->is_active)
                                    <span class="badge bg-secondary">Inativo</span>
                                @endif
                            </div>
                            <div class="small text-muted mt-1">
                                URL: {{ $item->url ?: ($item->route_name ? 'route('.$item->route_name.')' : '-') }}
                                &middot; Papel: {{ $item->role }}
                                &middot; Posicao: {{ $item->position }}
                            </div>
                        </div>
                        <div class="d-flex gap-1">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="editItem({{ $item->id }}, {{ json_encode($item->toArray()) }})">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteItem({{ $item->id }})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                    @empty
                        <p class="text-muted">Nenhum item configurado.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="dashboard-panel__title mb-0">Dicas</h3>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2"><i class="bi bi-check-circle text-success me-1"></i> <strong>Route name</strong> tem prioridade sobre URL manual.</li>
                        <li class="mb-2"><i class="bi bi-check-circle text-success me-1"></i> <strong>Match patterns</strong> sao separados por virgula e definem quando o item fica ativo.</li>
                        <li class="mb-2"><i class="bi bi-check-circle text-success me-1"></i> <strong>Papel</strong> filtra quem ve o item (tenant, admin ou ambos).</li>
                        <li><i class="bi bi-check-circle text-success me-1"></i> <strong>Icone</strong> use classes Bootstrap Icons, ex: <code>bi bi-house-door</code>.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalItem" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="frm-item">
                @csrf
                <input type="hidden" name="id" id="item-id" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-title">Novo item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Label <span class="text-danger">*</span></label>
                            <input name="label" id="item-label" class="form-control" required maxlength="60">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Icone (classe BI) <span class="text-danger">*</span></label>
                            <input name="icon" id="item-icon" class="form-control" required maxlength="60" placeholder="bi bi-house-door">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">URL manual</label>
                            <input name="url" id="item-url" class="form-control" maxlength="255" placeholder="/painel">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Route name</label>
                            <input name="route_name" id="item-route" class="form-control" maxlength="120" placeholder="pwa.dashboard">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Posicao</label>
                            <input name="position" id="item-position" type="number" class="form-control" min="0" max="255" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Papel</label>
                            <select name="role" id="item-role" class="form-select">
                                <option value="both">Ambos</option>
                                <option value="tenant">Tenant</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Ativo?</label>
                            <select name="is_active" id="item-active" class="form-select">
                                <option value="1">Sim</option>
                                <option value="0">Nao</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Match patterns (virgula)</label>
                            <input name="match_patterns" id="item-patterns" class="form-control" maxlength="500" placeholder="pwa.dashboard, pwa/dashboard, painel">
                            <div class="form-text">Rotas e paths que deixam este item destacado como ativo.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
const menuList = document.getElementById('menu-list');
if (menuList) {
    new Sortable(menuList, {
        handle: '.pwa-menu-item__handle',
        animation: 150,
        onEnd: async function () {
            const order = Array.from(menuList.children).map(el => el.dataset.id);
            try {
                const r = await fetch('{{ route("admin.pwa.menu.reorder") }}', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ order })
                });
                const d = await r.json();
                d.ok ? window.toast.success('Ordem salva.') : window.toast.error(d.error || 'Erro.');
            } catch {
                window.toast.error('Erro de rede ao reordenar.');
            }
        }
    });
}

function openModal() {
    document.getElementById('frm-item').reset();
    document.getElementById('item-id').value = '';
    document.getElementById('modal-title').textContent = 'Novo item';
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalItem'));
    modal.show();
}

function editItem(id, data) {
    document.getElementById('item-id').value = id;
    document.getElementById('item-label').value = data.label;
    document.getElementById('item-icon').value = data.icon;
    document.getElementById('item-url').value = data.url || '';
    document.getElementById('item-route').value = data.route_name || '';
    document.getElementById('item-position').value = data.position;
    document.getElementById('item-role').value = data.role || 'both';
    document.getElementById('item-active').value = data.is_active ? '1' : '0';
    document.getElementById('item-patterns').value = (data.match_patterns || []).join(', ');
    document.getElementById('modal-title').textContent = 'Editar item';
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalItem'));
    modal.show();
}

async function deleteItem(id) {
    const result = await Swal.fire({
        title: 'Remover este item?',
        text: 'Tem certeza que deseja remover este item?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sim, remover',
        cancelButtonText: 'Cancelar'
    });
    if (!result.isConfirmed) return;
    try {
        const r = await fetch(`{{ url('/admin/pwa/menu') }}/${id}`, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                'Accept': 'application/json'
            }
        });
        const d = await r.json();
        if (d.ok) {
            window.toast.success('Item removido.');
            document.querySelector(`[data-id="${id}"]`)?.remove();
        } else {
            window.toast.error(d.error || 'Erro.');
        }
    } catch {
        window.toast.error('Erro de rede.');
    }
}

document.getElementById('frm-item').addEventListener('submit', async function (e) {
    e.preventDefault();
    const id = document.getElementById('item-id').value;
    const fd = new FormData(this);
    const url = id ? `{{ url('/admin/pwa/menu') }}/${id}` : '{{ route("admin.pwa.menu.store") }}';
    const method = id ? 'PUT' : 'POST';

    try {
        const r = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                'Accept': 'application/json'
            },
            body: fd
        });
        const d = await r.json();
        if (d.ok) {
            window.toast.success('Item salvo.');
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalItem')).hide();
            setTimeout(() => location.reload(), 600);
        } else {
            window.toast.error(d.error || 'Erro ao salvar.');
        }
    } catch {
        window.toast.error('Erro de rede.');
    }
});
</script>
@endpush
