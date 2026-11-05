@extends('admin.layouts.app')
@section('title', 'Papeis e Permissoes')
@section('content')
<div class="container-fluid">

    {{-- ========== HEADER ========== --}}
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h3 class="mb-1"><i class="bi bi-shield-lock me-2 text-primary"></i>Papeis e Permissoes</h3>
            <p class="text-muted mb-0 small">Crie roles, atribua permissoes por modulo e gerencie acessos dos usuarios admin. Tudo em tempo real, sem refresh.</p>
        </div>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNewRole">
            <i class="bi bi-plus-lg me-1"></i> Nova role
        </button>
    </div>

    {{-- ========== KPIs ========== --}}
    <div class="row g-3 mb-4">
        <div class="col-md-6 col-lg-3">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-person-badge"></i></div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="text-uppercase small text-muted fw-semibold">Papeis</div>
                        <div class="fs-4 fw-bold">{{ $roles->count() }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-success-subtle text-success"><i class="bi bi-key"></i></div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="text-uppercase small text-muted fw-semibold">Permissoes</div>
                        <div class="fs-4 fw-bold">{{ collect($permissionsGrouped)->flatten(1)->count() }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-info-subtle text-info"><i class="bi bi-grid-3x3-gap"></i></div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="text-uppercase small text-muted fw-semibold">Modulos</div>
                        <div class="fs-4 fw-bold">{{ count($permissionsGrouped) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-warning-subtle text-warning"><i class="bi bi-people"></i></div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="text-uppercase small text-muted fw-semibold">Admins</div>
                        <div class="fs-4 fw-bold">{{ $totalAdmins }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        {{-- ========== LISTA DE ROLES ========== --}}
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header">
                    <strong><i class="bi bi-person-badge me-1 text-primary"></i> Papeis (Roles)</strong>
                    <span class="badge bg-light text-dark border">{{ $roles->count() }}</span>
                </div>
                <div class="list-group list-group-flush" id="roles-list">
                    @forelse($roles as $role)
                    <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-start text-start gap-2"
                            data-role-id="{{ $role->id }}"
                            data-edit-role="{{ $role->id }}">
                        <div class="d-flex gap-2 align-items-start flex-grow-1 min-w-0">
                            <div class="role-level-badge bg-{{ $role->level >= 90 ? 'danger' : ($role->level >= 50 ? 'warning' : ($role->level >= 20 ? 'info' : 'secondary')) }}">
                                {{ $role->level }}
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="d-flex align-items-center gap-1 flex-wrap">
                                    <strong class="text-truncate">{{ $role->name }}</strong>
                                    @if($role->is_system)
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle small" title="Role do sistema">
                                            <i class="bi bi-lock-fill"></i> sistema
                                        </span>
                                    @endif
                                </div>
                                <small class="text-muted d-block">
                                    <code class="small">{{ $role->slug }}</code>
                                </small>
                                <small class="text-muted">
                                    <i class="bi bi-people me-1"></i>{{ $role->admin_users_count }} usuario(s)
                                </small>
                            </div>
                        </div>
                        <div class="d-flex flex-column gap-1">
                            <i class="bi bi-pencil text-muted small" title="Clique para editar"></i>
                            @if(!$role->is_system)
                                <button type="button" class="btn btn-sm btn-link text-danger p-0" data-delete-role="{{ $role->id }}" title="Excluir" onclick="event.stopPropagation();">
                                    <i class="bi bi-trash"></i>
                                </button>
                            @endif
                        </div>
                    </button>
                    @empty
                    <div class="list-group-item text-center text-muted py-4">
                        <i class="bi bi-person-badge fs-1 d-block mb-2"></i>
                        Nenhuma role criada.
                    </div>
                    @endforelse
                </div>
            </div>

            {{-- Usuarios admin --}}
            <div class="card shadow-sm mt-3">
                <div class="card-header">
                    <strong><i class="bi bi-people me-1 text-info"></i> Usuarios Admin</strong>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn-load-users">
                        <i class="bi bi-arrow-down-circle me-1"></i> Carregar
                    </button>
                </div>
                <div class="card-body p-0" id="users-section">
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-people fs-2 d-block mb-2"></i>
                        <small>Clique em "Carregar" para listar usuarios</small>
                    </div>
                </div>
            </div>
        </div>

        {{-- ========== PAINEL DE PERMISSOES ========== --}}
        <div class="col-lg-8">
            <div class="card shadow-sm" id="perm-panel">
                <div class="card-header">
                    <strong id="perm-panel-title">
                        <i class="bi bi-key me-1 text-warning"></i>
                        Selecione uma role para gerenciar permissoes
                    </strong>
                    <div class="d-flex gap-2 d-none" id="perm-actions">
                        <div class="position-relative" style="max-width: 220px;">
                            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-2 text-muted small"></i>
                            <input type="search" class="form-control form-control-sm ps-4" id="perm-search" placeholder="Buscar permissao...">
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-check-all">
                            <i class="bi bi-check2-all me-1"></i> Marcar tudo
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-uncheck-all">
                            <i class="bi bi-x me-1"></i> Limpar
                        </button>
                        <button type="button" class="btn btn-sm btn-success" id="btn-save-perms">
                            <i class="bi bi-check2-circle me-1"></i> Salvar
                        </button>
                    </div>
                </div>
                <div class="card-body" id="perm-panel-body">
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-key fs-1"></i>
                        <p class="mt-3 mb-1">Clique em qualquer role na lista ao lado.</p>
                        <small>As permissoes aparecerao aqui para edicao.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ========== MODAL: Nova Role ========== --}}
<div class="modal fade" id="modalNewRole" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="frm-new-role">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i> Nova role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label">Nome <span class="text-danger">*</span></label>
                            <input id="new-role-name" class="form-control" maxlength="80" placeholder="Ex.: Operador" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Slug (unico) <span class="text-danger">*</span></label>
                            <input id="new-role-slug" class="form-control" maxlength="60" placeholder="operator" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Descricao</label>
                            <input id="new-role-desc" class="form-control" maxlength="255" placeholder="Operadores do dia-a-dia">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label d-flex justify-content-between">
                                <span>Nivel <span class="text-danger">*</span></span>
                                <span class="text-muted small">0 = comum / 99 = total</span>
                            </label>
                            <input type="range" class="form-range" id="new-role-level" min="0" max="99" value="10" oninput="document.getElementById('level-display').textContent = this.value">
                            <div class="d-flex justify-content-between small">
                                <span class="text-muted">0</span>
                                <strong id="level-display" class="text-primary">10</strong>
                                <span class="text-muted">99</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2-circle me-1"></i> Criar role
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('head')
<style>
.role-level-badge {
    width: 36px; height: 36px;
    border-radius: 50%;
    color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-weight: 600;
    font-size: .85rem;
    flex-shrink: 0;
}
#roles-list .list-group-item-action.active-role {
    background: color-mix(in srgb, var(--bs-primary) 8%, transparent);
    border-left: 3px solid var(--bs-primary);
}

.perm-module-card {
    border: 1px solid var(--bs-border-color);
    border-radius: .65rem;
    overflow: hidden;
}
.perm-module-card .perm-module-header {
    padding: .65rem .85rem;
    background: var(--bs-tertiary-bg);
    border-bottom: 1px solid var(--bs-border-color);
    display: flex; align-items: center; justify-content: space-between;
    gap: .5rem;
}
.perm-module-card .perm-module-body {
    padding: .85rem;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: .65rem;
}
.perm-check-wrap {
    display: flex; align-items: center; gap: .5rem;
    padding: .35rem .55rem;
    border: 1px solid var(--bs-border-color);
    border-radius: .375rem;
    transition: all .12s ease;
    cursor: pointer;
}
.perm-check-wrap:hover { background: var(--bs-tertiary-bg); border-color: var(--bs-primary); }
.perm-check-wrap.is-checked { background: color-mix(in srgb, var(--bs-success) 8%, transparent); border-color: var(--bs-success); }
.perm-check-wrap.is-readonly {
    cursor: not-allowed;
    opacity: .82;
}
.perm-check-wrap.is-readonly:hover {
    background: inherit;
    border-color: var(--bs-border-color);
}
.perm-check-wrap input[type=checkbox] { margin: 0; flex-shrink: 0; }
.perm-action-name { font-size: .8rem; font-weight: 500; flex-grow: 1; }

.perm-module-counter {
    font-size: .75rem;
    background: var(--bs-body-bg);
    border: 1px solid var(--bs-border-color);
    padding: .15rem .5rem;
    border-radius: 1rem;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
}
[data-bs-theme="dark"] #roles-list .list-group-item-action.active-role { background: rgba(13,110,253,.15); }
</style>
@endpush

@push('scripts')
<script>
(function () {
    const csrf = document.querySelector('meta[name=csrf-token]').content;
    const headers = { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' };
    let currentRoleId = null;
    let permData = null;
    let currentRoleReadonly = false;

    /* ---------- Criar role ---------- */
    document.getElementById('frm-new-role').addEventListener('submit', async e => {
        e.preventDefault();
        const payload = {
            name: document.getElementById('new-role-name').value,
            slug: document.getElementById('new-role-slug').value,
            description: document.getElementById('new-role-desc').value,
            level: parseInt(document.getElementById('new-role-level').value) || 0,
        };
        try {
            const r = await fetch('{{ route("admin.permissions.roles.store") }}', {
                method: 'POST', credentials: 'same-origin', headers, body: JSON.stringify(payload),
            });
            const d = await r.json();
            if (r.ok) {
                window.toast.success('Role criada!');
                setTimeout(() => location.reload(), 600);
            } else {
                window.toast.error(d.message || 'Erro ao criar role.');
            }
        } catch (err) {
            window.toast.error('Erro de rede.');
        }
    });

    /* ---------- Auto-slug ---------- */
    const slugInput = document.getElementById('new-role-slug');
    document.getElementById('new-role-name').addEventListener('input', e => {
        if (slugInput.dataset.touched === '1') return;
        slugInput.value = e.target.value
            .toLowerCase()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    });
    slugInput.addEventListener('input', () => slugInput.dataset.touched = '1');

    /* ---------- Excluir role ---------- */
    document.querySelectorAll('[data-delete-role]').forEach(btn => {
        btn.onclick = async (e) => {
            e.stopPropagation();
            const ok = await Swal.fire({
                icon: 'warning', title: 'Excluir esta role?',
                text: 'Os usuarios atribuidos a essa role perderao todos os acessos vinculados.',
                showCancelButton: true, confirmButtonText: 'Excluir', cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545'
            });
            if (!ok.isConfirmed) return;
            try {
                const r = await fetch(`{{ url('/admin/permissions/roles') }}/${btn.dataset.deleteRole}`, {
                    method: 'DELETE', credentials: 'same-origin', headers,
                });
                if (r.ok) {
                    window.toast.success('Role excluida.');
                    btn.closest('[data-role-id]')?.remove();
                } else {
                    const d = await r.json();
                    window.toast.error(d.error || 'Nao foi possivel excluir.');
                }
            } catch (err) {
                window.toast.error('Erro de rede.');
            }
        };
    });

    /* ---------- Editar permissoes ---------- */
    function renderPermissions(data) {
        permData = data;
        currentRoleReadonly = !!data.readonly;
        let html = '';
        for (const [module, perms] of Object.entries(data.permissions)) {
            const checkedCount = perms.filter(p => data.role_permissions.includes(p.slug)).length;
            html += `
                <div class="perm-module-card mb-3" data-module="${module}">
                    <div class="perm-module-header">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-folder text-primary"></i>
                            <strong class="text-uppercase small">${module}</strong>
                            <span class="perm-module-counter">${checkedCount}/${perms.length}</span>
                        </div>
                        <label class="form-check form-check-inline m-0 small">
                            <input type="checkbox" class="form-check-input check-all-module" data-module="${module}" ${currentRoleReadonly ? 'disabled' : ''}>
                            <span class="form-check-label small">Selecionar todos</span>
                        </label>
                    </div>
                    <div class="perm-module-body">`;
            for (const p of perms) {
                const checked = data.role_permissions.includes(p.slug);
                html += `
                    <label class="perm-check-wrap ${checked ? 'is-checked' : ''} ${currentRoleReadonly ? 'is-readonly' : ''}">
                        <input class="form-check-input perm-check" type="checkbox" value="${p.slug}" ${checked ? 'checked' : ''} ${currentRoleReadonly ? 'disabled' : ''}>
                        <span class="perm-action-name">${p.action}</span>
                    </label>`;
            }
            html += `</div></div>`;
        }
        document.getElementById('perm-panel-body').innerHTML = html;
        syncReadonlyActions();

        // Atualiza estado do "selecionar todos"
        updateModuleCheckAlls();

        // Eventos
        document.querySelectorAll('.perm-check').forEach(cb => {
            cb.addEventListener('change', () => {
                cb.closest('.perm-check-wrap').classList.toggle('is-checked', cb.checked);
                updateModuleCounter(cb.closest('.perm-module-card'));
                updateModuleCheckAlls();
            });
        });

        document.querySelectorAll('.check-all-module').forEach(ca => {
            ca.addEventListener('change', () => {
                if (currentRoleReadonly) return;
                const card = ca.closest('.perm-module-card');
                card.querySelectorAll('.perm-check').forEach(cb => {
                    cb.checked = ca.checked;
                    cb.closest('.perm-check-wrap').classList.toggle('is-checked', ca.checked);
                });
                updateModuleCounter(card);
            });
        });
    }

    function syncReadonlyActions() {
        const saveBtn = document.getElementById('btn-save-perms');
        const checkAllBtn = document.getElementById('btn-check-all');
        const uncheckAllBtn = document.getElementById('btn-uncheck-all');
        const title = document.getElementById('perm-panel-title');

        [saveBtn, checkAllBtn, uncheckAllBtn].forEach(btn => {
            btn.disabled = currentRoleReadonly;
            btn.classList.toggle('opacity-75', currentRoleReadonly);
        });

        if (currentRoleReadonly && !title.querySelector('.superadmin-readonly-badge')) {
            title.insertAdjacentHTML('beforeend', `
                <span class="badge bg-success-subtle text-success border border-success-subtle ms-2 superadmin-readonly-badge">
                    acesso total automatico
                </span>
            `);
        } else if (!currentRoleReadonly) {
            title.querySelector('.superadmin-readonly-badge')?.remove();
        }
    }

    function updateModuleCounter(card) {
        const total = card.querySelectorAll('.perm-check').length;
        const checked = card.querySelectorAll('.perm-check:checked').length;
        card.querySelector('.perm-module-counter').textContent = `${checked}/${total}`;
    }

    function updateModuleCheckAlls() {
        document.querySelectorAll('.perm-module-card').forEach(card => {
            const all = card.querySelectorAll('.perm-check').length;
            const checked = card.querySelectorAll('.perm-check:checked').length;
            const ca = card.querySelector('.check-all-module');
            if (!ca) return;
            ca.checked = checked === all;
            ca.indeterminate = checked > 0 && checked < all;
        });
    }

    document.querySelectorAll('[data-edit-role]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const roleId = btn.dataset.editRole;
            currentRoleId = roleId;

            // Highlight role selecionada
            document.querySelectorAll('#roles-list .list-group-item-action').forEach(b => b.classList.remove('active-role'));
            btn.classList.add('active-role');

            try {
                const r = await fetch(`{{ url('/admin/permissions/roles') }}/${roleId}/edit`, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
                const data = await r.json();

                document.getElementById('perm-panel-title').innerHTML = `
                    <i class="bi bi-key me-1 text-warning"></i>
                    Permissoes de <span class="text-primary">${data.role.name}</span>
                    <small class="text-muted">(${data.role.slug})</small>
                `;
                document.getElementById('perm-actions').classList.remove('d-none');
                renderPermissions(data);
            } catch (err) {
                window.toast.error('Erro ao carregar permissoes.');
            }
        });
    });

    /* ---------- Salvar permissoes ---------- */
    document.getElementById('btn-save-perms').addEventListener('click', async () => {
        if (!currentRoleId) return;
        if (currentRoleReadonly) {
            window.toast.info('Superadmin ja possui acesso total automaticamente.');
            return;
        }
        const checked = Array.from(document.querySelectorAll('.perm-check:checked')).map(c => c.value);
        const btn = document.getElementById('btn-save-perms');
        const original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Salvando...';
        try {
            const r = await fetch(`{{ url('/admin/permissions/roles') }}/${currentRoleId}/sync`, {
                method: 'POST', credentials: 'same-origin', headers,
                body: JSON.stringify({ permissions: checked }),
            });
            const d = await r.json();
            if (r.ok) window.toast.success(d.message || `${d.count} permissoes salvas!`);
            else window.toast.error(d.error || 'Erro ao salvar.');
        } catch (err) {
            window.toast.error('Erro de rede.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = original;
        }
    });

    /* ---------- Marcar/desmarcar tudo ---------- */
    document.getElementById('btn-check-all').addEventListener('click', () => {
        if (currentRoleReadonly) return;
        document.querySelectorAll('.perm-check').forEach(cb => {
            cb.checked = true;
            cb.closest('.perm-check-wrap').classList.add('is-checked');
        });
        document.querySelectorAll('.perm-module-card').forEach(updateModuleCounter);
        updateModuleCheckAlls();
    });

    document.getElementById('btn-uncheck-all').addEventListener('click', () => {
        if (currentRoleReadonly) return;
        document.querySelectorAll('.perm-check').forEach(cb => {
            cb.checked = false;
            cb.closest('.perm-check-wrap').classList.remove('is-checked');
        });
        document.querySelectorAll('.perm-module-card').forEach(updateModuleCounter);
        updateModuleCheckAlls();
    });

    /* ---------- Busca em permissoes ---------- */
    document.getElementById('perm-search').addEventListener('input', e => {
        const q = e.target.value.toLowerCase().trim();
        document.querySelectorAll('.perm-module-card').forEach(card => {
            let anyVisible = false;
            card.querySelectorAll('.perm-check-wrap').forEach(wrap => {
                const txt = wrap.textContent.toLowerCase();
                const match = !q || txt.includes(q);
                wrap.style.display = match ? '' : 'none';
                if (match) anyVisible = true;
            });
            const moduleName = card.dataset.module.toLowerCase();
            const moduleMatch = moduleName.includes(q);
            card.style.display = (anyVisible || moduleMatch) ? '' : 'none';
        });
    });

    /* ---------- Carregar usuarios ---------- */
    document.getElementById('btn-load-users').addEventListener('click', async () => {
        const btn = document.getElementById('btn-load-users');
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Carregando...';
        try {
            const r = await fetch('{{ route("admin.permissions.users") }}', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
            const data = await r.json();

            let html = '<div class="table-responsive"><table class="table table-sm table-hover m-0 align-middle">';
            html += '<thead class="table-light"><tr><th>Usuario</th><th>Roles atuais</th><th>2FA</th><th class="text-end">Atribuir</th></tr></thead><tbody>';
            for (const u of data.users) {
                const isSuperadmin = !!u.is_superadmin;
                const initials = u.name.split(' ').slice(0, 2).map(p => p[0]).join('').toUpperCase();
                const roleBadges = (u.roles || []).map(r => `<span class="badge bg-primary-subtle text-primary border border-primary-subtle me-1 mb-1">${r.name}</span>`).join('') || '<small class="text-muted">—</small>';
                const twoFactorEnabled = !!(u.two_factor_enabled || u.two_factor_secret);
                const twoFactorHtml = twoFactorEnabled
                    ? `<div class="d-flex align-items-center gap-2 flex-wrap"><span class="badge bg-success-subtle text-success border border-success-subtle">Ativo</span><button type="button" class="btn btn-sm btn-outline-danger" data-disable-user-2fa="${u.id}" title="Desativar 2FA"><i class="bi bi-shield-x"></i></button></div>`
                    : '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Inativo</span>';
                const roleOptions = data.roles.map(r => {
                    const sel = (u.roles || []).some(ur => ur.id === r.id) ? 'selected' : '';
                    return `<option value="${r.id}" ${sel}>${r.name}</option>`;
                }).join('');
                html += `<tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-semibold" style="width:32px;height:32px;font-size:.75rem;">${initials}</div>
                            <div class="min-w-0">
                                <strong class="d-block text-truncate small">${u.name}</strong>
                                <small class="text-muted text-truncate d-block">${u.email}</small>
                                ${isSuperadmin ? '<span class="badge bg-success-subtle text-success border border-success-subtle mt-1">Superadmin fixo</span>' : ''}
                            </div>
                        </div>
                    </td>
                    <td>${roleBadges}</td>
                    <td>${twoFactorHtml}</td>
                    <td class="text-end" style="min-width:200px;">
                        <div class="d-flex gap-1 align-items-center justify-content-end">
                            <select class="form-select form-select-sm user-roles-select" data-user-id="${u.id}" multiple style="min-width:140px;max-width:180px;" ${isSuperadmin ? 'disabled' : ''}>
                                ${roleOptions}
                            </select>
                            <button class="btn btn-sm btn-outline-success" data-save-user-roles="${u.id}" title="Salvar" ${isSuperadmin ? 'disabled' : ''}><i class="bi bi-check-lg"></i></button>
                        </div>
                    </td>
                </tr>`;
            }
            html += '</tbody></table></div>';
            document.getElementById('users-section').innerHTML = html;

            document.querySelectorAll('[data-save-user-roles]').forEach(b => {
                b.addEventListener('click', async () => {
                    const uid = b.dataset.saveUserRoles;
                    const sel = b.closest('tr').querySelector('.user-roles-select');
                    const roleIds = Array.from(sel.selectedOptions).map(o => parseInt(o.value));
                    const r = await fetch(`{{ url('/admin/permissions/users') }}/${uid}/roles`, {
                        method: 'POST', credentials: 'same-origin', headers,
                        body: JSON.stringify({ roles: roleIds }),
                    });
                    const data = await r.json().catch(() => ({}));
                    if (r.ok) window.toast.success(data.message || 'Roles atualizadas!');
                    else window.toast.error(data.error || data.message || 'Erro ao atualizar.');
                });
            });

            document.querySelectorAll('[data-disable-user-2fa]').forEach(b => {
                b.addEventListener('click', async () => {
                    const uid = b.dataset.disableUser2fa;
                    const ok = await Swal.fire({
                        icon: 'warning',
                        title: 'Desativar 2FA deste usuario?',
                        text: 'Use esta acao somente quando o usuario solicitar recuperacao de acesso.',
                        showCancelButton: true,
                        confirmButtonText: 'Desativar 2FA',
                        cancelButtonText: 'Cancelar',
                        confirmButtonColor: '#dc3545',
                    });
                    if (!ok.isConfirmed) return;

                    const r = await fetch(`{{ url('/admin/permissions/users') }}/${uid}/2fa/disable`, {
                        method: 'POST', credentials: 'same-origin', headers,
                    });
                    const data = await r.json().catch(() => ({}));
                    if (r.ok && data.ok) {
                        window.toast.success(data.message || '2FA desativado.');
                        b.closest('td').innerHTML = '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Inativo</span>';
                    } else {
                        window.toast.error(data.message || data.error || 'Erro ao desativar 2FA.');
                    }
                });
            });
        } catch (err) {
            window.toast.error('Erro ao carregar usuarios.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-down-circle me-1"></i> Carregar';
        }
    });
})();
</script>
@endpush
