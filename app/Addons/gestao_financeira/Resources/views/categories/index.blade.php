@php
    $routePrefix = $routePrefix ?? (request()->is('painel/*') || session('admin_assist_mode') ? 'tenant.gestao-financeira.' : 'admin.gestao-financeira.');
    $baseUrl = str_starts_with($routePrefix, 'admin') ? '/admin/gestao-financeira' : '/painel/gestao-financeira';
@endphp
@extends('gestao-financeira::layouts.master')

@section('title', 'Categorias')
@section('page_title', 'Categorias financeiras')

@section('page_actions')
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCategory">
        <i class="bi bi-plus-lg me-1"></i> Nova categoria
    </button>
@endsection

@section('addon_content')

@php
    $receitas = $categories->where('type','receita');
    $despesas = $categories->where('type','despesa');
@endphp

<div class="row g-4">
    {{-- Receitas --}}
    <div class="col-xl-6">
        <div class="card shadow-sm border-0" style="border-radius:16px;">
            <div class="card-header d-flex align-items-center justify-content-between py-2" style="border-radius:16px 16px 0 0;">
                <strong><i class="bi bi-arrow-down-circle me-2 text-success"></i>Receitas <span class="badge bg-success-subtle text-success border border-success-subtle ms-1">{{ $receitas->count() }}</span></strong>
                <button type="button" class="btn btn-sm btn-outline-success" onclick="openNewCategoryModal('receita')">
                    <i class="bi bi-plus-lg me-1"></i> Nova receita
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle m-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Categoria</th>
                                <th class="text-center">Cor</th>
                                <th class="text-center">Lançamentos</th>
                                <th class="text-end pe-3">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="cat-receita-tbody">
                            @forelse($receitas as $cat)
                            <tr id="cat-row-{{ $cat->id }}" style="transition:.3s;">
                                <td class="ps-3 fw-semibold">{{ $cat->name }}</td>
                                <td class="text-center">
                                    <span class="d-inline-flex align-items-center gap-1">
                                        <span style="width:16px;height:16px;border-radius:50%;background:{{ $cat->color }};display:inline-block;border:2px solid {{ $cat->color }}55;"></span>
                                        <code class="small">{{ $cat->color }}</code>
                                    </span>
                                </td>
                                <td class="text-center"><span class="badge bg-secondary-subtle text-secondary border">{{ $cat->transactions_count }}</span></td>
                                <td class="text-end pe-3">
                                    <div class="d-flex gap-1 justify-content-end">
                                        <button class="btn btn-sm btn-outline-primary px-2" title="Editar categoria" onclick="editCategory({{ $cat->id }},'{{ addslashes($cat->name) }}','{{ $cat->type }}','{{ $cat->color }}',{{ $cat->transactions_count }})">
                                            <i class="bi bi-pencil me-1"></i>Editar
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger px-2 btn-del-cat" title="Excluir categoria" data-id="{{ $cat->id }}" data-name="{{ addslashes($cat->name) }}" data-count="{{ $cat->transactions_count }}">
                                            <i class="bi bi-trash me-1"></i>Excluir
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted small">
                                    <div>Nenhuma categoria de receita cadastrada.</div>
                                    <button type="button" class="btn btn-sm btn-outline-success mt-2" onclick="openNewCategoryModal('receita')">
                                        <i class="bi bi-plus-lg me-1"></i> Criar primeira receita
                                    </button>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Despesas --}}
    <div class="col-xl-6">
        <div class="card shadow-sm border-0" style="border-radius:16px;">
            <div class="card-header d-flex align-items-center justify-content-between py-2" style="border-radius:16px 16px 0 0;">
                <strong><i class="bi bi-arrow-up-circle me-2 text-danger"></i>Despesas <span class="badge bg-danger-subtle text-danger border border-danger-subtle ms-1">{{ $despesas->count() }}</span></strong>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="openNewCategoryModal('despesa')">
                    <i class="bi bi-plus-lg me-1"></i> Nova despesa
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle m-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Categoria</th>
                                <th class="text-center">Cor</th>
                                <th class="text-center">Lançamentos</th>
                                <th class="text-end pe-3">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="cat-despesa-tbody">
                            @forelse($despesas as $cat)
                            <tr id="cat-row-{{ $cat->id }}" style="transition:.3s;">
                                <td class="ps-3 fw-semibold">{{ $cat->name }}</td>
                                <td class="text-center">
                                    <span class="d-inline-flex align-items-center gap-1">
                                        <span style="width:16px;height:16px;border-radius:50%;background:{{ $cat->color }};display:inline-block;border:2px solid {{ $cat->color }}55;"></span>
                                        <code class="small">{{ $cat->color }}</code>
                                    </span>
                                </td>
                                <td class="text-center"><span class="badge bg-secondary-subtle text-secondary border">{{ $cat->transactions_count }}</span></td>
                                <td class="text-end pe-3">
                                    <div class="d-flex gap-1 justify-content-end">
                                        <button class="btn btn-sm btn-outline-primary px-2" title="Editar categoria" onclick="editCategory({{ $cat->id }},'{{ addslashes($cat->name) }}','{{ $cat->type }}','{{ $cat->color }}',{{ $cat->transactions_count }})">
                                            <i class="bi bi-pencil me-1"></i>Editar
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger px-2 btn-del-cat" title="Excluir categoria" data-id="{{ $cat->id }}" data-name="{{ addslashes($cat->name) }}" data-count="{{ $cat->transactions_count }}">
                                            <i class="bi bi-trash me-1"></i>Excluir
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted small">
                                    <div>Nenhuma categoria de despesa cadastrada.</div>
                                    <button type="button" class="btn btn-sm btn-outline-danger mt-2" onclick="openNewCategoryModal('despesa')">
                                        <i class="bi bi-plus-lg me-1"></i> Criar primeira despesa
                                    </button>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal --}}
<div class="modal fade" id="modalCategory" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="frm-category">
                @csrf
                <input type="hidden" name="_id" id="cat-id">
                <div class="modal-header">
                    <h5 class="modal-title" id="cat-modal-title"><i class="bi bi-tags me-2 text-primary"></i>Nova categoria</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold">Nome <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="Ex.: Vendas, Aluguel, Marketing...">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Tipo</label>
                        <select name="type" class="form-select" required>
                            <option value="receita">Receita</option>
                            <option value="despesa">Despesa</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Cor identificadora</label>
                        <div class="d-flex gap-2 align-items-center">
                            <input type="color" name="color" id="cat-color-picker" class="form-control form-control-color" value="#3B82F6" style="min-width:50px;cursor:pointer;">
                            <input type="text" id="cat-color-code" class="form-control font-monospace" value="#3B82F6" readonly style="width:90px;">
                        </div>
                    </div>
                    {{-- Preview --}}
                    <div class="col-12">
                        <div class="p-2 rounded-3 d-inline-flex align-items-center gap-2" id="cat-preview" style="background:#3B82F622;border:1px solid #3B82F655;">
                            <span style="width:10px;height:10px;border-radius:50%;background:#3B82F6;display:inline-block;"></span>
                            <span class="small fw-semibold" id="cat-preview-name" style="color:#3B82F6;">Prévia</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between align-items-center">
                    <button type="button" class="btn btn-outline-danger d-none" id="cat-btn-delete">
                        <i class="bi bi-trash me-1"></i> Excluir categoria
                    </button>
                    <div class="d-flex gap-2 ms-auto">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="cat-submit">
                            <span class="spinner-border spinner-border-sm d-none me-1" id="cat-spinner"></span>
                            <i class="bi bi-check2-circle me-1" id="cat-icon"></i>
                            <span id="cat-btn-label">Criar categoria</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function(){
    const csrf  = document.querySelector('meta[name=csrf-token]')?.content ?? '';
    const base  = @json($baseUrl);
    const modalId = 'modalCategory';
    const showModal = () => window.$ && typeof $.fn.modal === 'function' ? $('#'+modalId).modal('show') : bootstrap.Modal.getOrCreateInstance(document.getElementById(modalId)).show();
    const hideModal = () => window.$ && typeof $.fn.modal === 'function' ? $('#'+modalId).modal('hide') : bootstrap.Modal.getInstance(document.getElementById(modalId))?.hide();
    const frm   = document.getElementById('frm-category');
    const picker= document.getElementById('cat-color-picker');
    const code  = document.getElementById('cat-color-code');
    const prevBox  = document.getElementById('cat-preview');
    const prevName = document.getElementById('cat-preview-name');
    const catBtnDelete = document.getElementById('cat-btn-delete');

    const setLoading = on => {
        document.getElementById('cat-spinner').classList.toggle('d-none',!on);
        document.getElementById('cat-icon').classList.toggle('d-none',on);
        document.getElementById('cat-submit').disabled = on;
    };
    const toast = (msg, type='success') => {
        if (window.toastr) { window.toastr[type](msg); return; }
        Swal.fire({ icon: type === 'error' ? 'error' : 'success', title: msg, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
    };

    // Sincroniza cor em tempo real
    const syncColor = (hex) => {
        code.value = hex;
        prevBox.style.background  = hex+'22';
        prevBox.style.borderColor = hex+'55';
        prevName.style.color      = hex;
        prevBox.querySelector('span').style.background = hex;
    };
    picker.addEventListener('input', () => syncColor(picker.value));

    // Sincroniza preview name
    frm.elements['name'].addEventListener('input', e => { prevName.textContent = e.target.value || 'Prévia'; });

    // Reset ao abrir para novo
    window.openNewCategoryModal = (type = 'receita') => {
        frm.reset();
        const defaultColor = type === 'despesa' ? '#EF4444' : '#10B981';
        picker.value = defaultColor;
        syncColor(defaultColor);
        document.getElementById('cat-id').value = '';
        document.getElementById('cat-modal-title').innerHTML = `<i class="bi bi-tags me-2 text-primary"></i>Nova categoria (${type === 'despesa' ? 'Despesa' : 'Receita'})`;
        document.getElementById('cat-btn-label').textContent = 'Criar categoria';
        frm.elements['type'].value = type;
        prevName.textContent = 'Prévia';
        if (catBtnDelete) catBtnDelete.classList.add('d-none');
        showModal();
    };

    document.getElementById('modalCategory').addEventListener('show.bs.modal', function(e){
        if(!e.relatedTarget) return;
        frm.reset(); picker.value='#10B981'; syncColor('#10B981');
        document.getElementById('cat-id').value='';
        document.getElementById('cat-modal-title').innerHTML='<i class="bi bi-tags me-2 text-primary"></i>Nova categoria';
        document.getElementById('cat-btn-label').textContent='Criar categoria';
        prevName.textContent='Prévia';
        if (catBtnDelete) catBtnDelete.classList.add('d-none');
    });

    // Função de Excluir Categoria
    window.deleteCategory = async (id, name = '', count = 0) => {
        let warningText = 'Esta ação não poderá ser desfeita.';
        if (count > 0) {
            warningText = `Esta categoria possui ${count} lançamento(s) vinculado(s). Ao excluir, os lançamentos continuarão existindo e serão desvinculados. Deseja continuar?`;
        }

        const confirmResult = await Swal.fire({
            icon: 'warning',
            title: name ? `Excluir "${name}"?` : 'Excluir categoria?',
            text: warningText,
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-trash me-1"></i> Sim, excluir',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545',
            focusCancel: true
        });

        if (!confirmResult.isConfirmed) return;

        try {
            const r = await fetch(`${base}/categorias/${id}/excluir`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'X-HTTP-METHOD-OVERRIDE': 'DELETE',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ _method: 'DELETE' })
            });
            const d = await r.json();
            if (d.ok || r.ok) {
                hideModal();
                const row = document.getElementById(`cat-row-${id}`);
                if (row) {
                    row.style.transition = 'all .3s ease';
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(20px)';
                    setTimeout(() => {
                        row.remove();
                        // Se tabela ficar vazia recarrega suavemente
                        const tbody = document.getElementById(`cat-${row.id.includes('receita') ? 'receita' : 'despesa'}-tbody`);
                        if (tbody && tbody.children.length === 0) location.reload();
                    }, 300);
                } else {
                    setTimeout(() => location.reload(), 400);
                }
                toast(d.message || 'Categoria excluída com sucesso!');
            } else {
                toast(d.message || 'Erro ao excluir categoria.', 'error');
            }
        } catch (err) {
            toast('Erro de rede ao excluir a categoria.', 'error');
        }
    };

    // Editar
    window.editCategory = (id, name, type, color, count = 0) => {
        frm.reset();
        document.getElementById('cat-id').value = id;
        document.getElementById('cat-modal-title').innerHTML = '<i class="bi bi-pencil me-2 text-warning"></i>Editar categoria';
        document.getElementById('cat-btn-label').textContent = 'Salvar alterações';
        frm.elements['name'].value = name;
        frm.elements['type'].value = type;
        frm.elements['color'].value = color;
        picker.value = color;
        syncColor(color);
        prevName.textContent = name;

        if (catBtnDelete) {
            catBtnDelete.classList.remove('d-none');
            catBtnDelete.onclick = () => deleteCategory(id, name, count);
        }

        showModal();
    };

    // Submit
    frm.addEventListener('submit', async e => {
        e.preventDefault();
        setLoading(true);
        const id  = document.getElementById('cat-id').value;
        const url = id ? `${base}/categorias/${id}/atualizar` : `${base}/categorias/salvar`;
        const fd  = new FormData(frm);
        try {
            const r = await fetch(url,{method:'POST',credentials:'same-origin',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'},body:fd});
            const d = await r.json();
            if(d.ok||r.ok){ hideModal(); toast(id?'Categoria atualizada!':'Categoria criada!'); setTimeout(()=>location.reload(),500); }
            else toast(d.message||'Erro ao salvar.','error');
        } catch(err){ toast('Erro de rede.','error'); }
        setLoading(false);
    });

    // Excluir via botão da tabela
    document.querySelectorAll('.btn-del-cat').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.id;
            const name = btn.dataset.name || '';
            const count = parseInt(btn.dataset.count || '0');
            deleteCategory(id, name, count);
        });
    });
})();
</script>
@endsection
