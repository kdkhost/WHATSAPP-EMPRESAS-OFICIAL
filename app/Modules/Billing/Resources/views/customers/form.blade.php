@extends('admin.layouts.app')

@section('title', $customer->exists ? 'Editar cliente' : 'Novo cliente')

@push('head')
<style>
.billing-customer-form .form-control,
.billing-customer-form .form-select {
    min-height: 46px;
}
.upload-zone {
    border: 2px dashed var(--bs-border-color);
    border-radius: var(--app-radius, 0.5rem);
    padding: 1.25rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
    background: var(--bs-body-bg);
}
.upload-zone:hover, .upload-zone.dragover {
    border-color: var(--bs-primary);
    background: color-mix(in srgb, var(--bs-primary) 5%, transparent);
}
.upload-zone-input {
    position: absolute;
    inset: 0;
    opacity: 0;
    cursor: pointer;
    z-index: 2;
}
</style>
@endpush

@section('content')
<div class="container-fluid billing-customer-form">
    <x-ui.page-header
        :title="$customer->exists ? 'Editar cliente' : 'Novo cliente'"
        :subtitle="$customer->exists ? 'Atualize os dados cadastrais, documentos e contatos usados nas cobranças.' : 'Cadastre um novo cliente com contatos, documento e endereço completos.'"
        eyebrow="Cadastro financeiro">
        <x-slot:actions>
            <a href="{{ route('admin.billing.customers.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Voltar
            </a>
        </x-slot:actions>
        <x-slot:aside>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Modo</span>
                <span class="dashboard-note__value">{{ $customer->exists ? 'Edição' : 'Criação' }}</span>
            </div>
        </x-slot:aside>
    </x-ui.page-header>

    <form id="frm-customer" data-id="{{ $customer->id }}">
        @csrf
        <div class="row g-4">
            <div class="col-lg-8">
                <x-ui.premium-card title="Dados principais" subtitle="Informações básicas do cliente para cadastro e relacionamento.">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Foto de Perfil (Opcional)</label>
                            
                            <div class="upload-zone mb-2" id="avatar-drop-zone">
                                <div class="upload-zone-content">
                                    <i class="bi bi-cloud-arrow-up fs-2 text-primary"></i>
                                    <p class="mb-0 small text-muted">Arraste a foto aqui ou <span class="text-primary fw-semibold">clique para selecionar</span></p>
                                    <small class="text-muted">PNG, JPG, WebP — Máx 2MB</small>
                                </div>
                                <input type="file" name="avatar" class="upload-zone-input" accept="image/*">
                            </div>

                            <div id="avatar-preview-wrap" class="d-flex align-items-center gap-3 bg-body-tertiary p-2 rounded border" style="{{ empty($customer->meta['avatar']) ? 'display: none !important;' : '' }}">
                                <img id="avatar-preview-img" src="{{ !empty($customer->meta['avatar']) ? Storage::url($customer->meta['avatar']) : '' }}" alt="Avatar" class="rounded-circle border" style="width:40px; height:40px; object-fit:cover;">
                                <div class="form-check m-0">
                                    <input class="form-check-input" type="checkbox" id="remove_avatar" name="remove_avatar" value="1">
                                    <label class="form-check-label text-danger small" for="remove_avatar">Remover foto</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nome <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="{{ $customer->name }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">E-mail</label>
                            <input type="email" name="email" class="form-control" value="{{ $customer->email }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tipo de Pessoa</label>
                            <select name="person_type" id="person_type" class="form-select">
                                <option value="PF" {{ ($customer->person_type ?? 'PF') === 'PF' ? 'selected' : '' }}>Pessoa Física (PF)</option>
                                <option value="PJ" {{ ($customer->person_type ?? '') === 'PJ' ? 'selected' : '' }}>Pessoa Jurídica (PJ)</option>
                            </select>
                        </div>
                        <div class="col-md-3" id="wrap-cpf">
                            <label class="form-label">CPF</label>
                            <input type="text" name="cpf" id="customer_cpf" class="form-control" value="{{ $customer->cpf }}" placeholder="000.000.000-00">
                        </div>
                        <div class="col-md-3" id="wrap-cnpj">
                            <label class="form-label">CNPJ</label>
                            <input type="text" name="cnpj" id="customer_cnpj" class="form-control" value="{{ $customer->cnpj }}" placeholder="00.000.000/0000-00">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Data de Nascimento</label>
                            <input type="date" name="birth_date" class="form-control" value="{{ $customer->birth_date ? ($customer->birth_date instanceof \DateTimeInterface ? $customer->birth_date->format('Y-m-d') : substr((string) $customer->birth_date, 0, 10)) : '' }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Telefone</label>
                            <input type="text" name="phone" class="form-control" value="{{ $customer->phone }}" data-mask="phone">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">WhatsApp</label>
                            <input type="text" name="whatsapp" class="form-control" value="{{ $customer->whatsapp }}" data-mask="phone">
                        </div>
                        <div class="col-md-4 d-flex align-items-center pt-md-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="whatsapp_optin" name="whatsapp_optin" value="1" {{ ($customer->whatsapp_optin ?? true) ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold small" for="whatsapp_optin">
                                    <i class="bi bi-whatsapp text-success me-1"></i> Quero receber mensagens WhatsApp
                                </label>
                            </div>
                        </div>
                    </div>
                </x-ui.premium-card>
            </div>

            <div class="col-lg-4">
                <x-ui.premium-card title="Endereço" subtitle="CEP com preenchimento automático via ViaCEP.">
                    <div class="row g-3">
                        <div class="col-5">
                            <label class="form-label">CEP</label>
                            <input type="text" name="cep" id="cep" class="form-control" value="{{ $customer->cep }}" data-mask="cep">
                        </div>
                        <div class="col-7">
                            <label class="form-label">Cidade</label>
                            <input type="text" name="cidade" class="form-control" value="{{ $customer->cidade }}">
                        </div>
                        <div class="col-9">
                            <label class="form-label">Logradouro</label>
                            <input type="text" name="logradouro" class="form-control" value="{{ $customer->logradouro }}">
                        </div>
                        <div class="col-3">
                            <label class="form-label">Número</label>
                            <input type="text" id="numero" name="numero" class="form-control" value="{{ $customer->numero }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Complemento</label>
                            <input type="text" name="complemento" class="form-control" value="{{ $customer->complemento }}">
                        </div>
                        <div class="col-4">
                            <label class="form-label">Bairro</label>
                            <input type="text" name="bairro" class="form-control" value="{{ $customer->bairro }}">
                        </div>
                        <div class="col-2">
                            <label class="form-label">UF</label>
                            <input type="text" name="uf" class="form-control" maxlength="2" value="{{ $customer->uf }}">
                        </div>
                    </div>
                </x-ui.premium-card>

                <x-ui.premium-card title="Ações" subtitle="Salve ou remova o cadastro conforme o contexto." class="mt-4">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> Salvar cliente
                        </button>

                        @if($customer->exists)
                            <button type="button" id="btn-delete" class="btn btn-outline-danger">
                                <i class="bi bi-trash me-1"></i> Excluir cliente
                            </button>
                        @endif
                    </div>
                </x-ui.premium-card>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const csrf = document.querySelector('meta[name=csrf-token]').content;
    const cepInput = document.getElementById('cep');
    const documentInput = document.querySelector('[name="document"]');
    const phoneInput = document.querySelector('[name="phone"]');
    const whatsappInput = document.querySelector('[name="whatsapp"]');
    const numeroInput = document.getElementById('numero');

    const onlyDigits = (value) => (value || '').replace(/\D/g, '');

    const formatCep = (value) => {
        const digits = onlyDigits(value).slice(0, 8);
        if (digits.length <= 5) return digits;
        return `${digits.slice(0, 5)}-${digits.slice(5)}`;
    };

    const formatDocument = (value) => {
        const digits = onlyDigits(value);
        if (digits.length <= 11) {
            return digits
                .replace(/^(\d{3})(\d)/, '$1.$2')
                .replace(/^(\d{3}\.\d{3})(\d)/, '$1.$2')
                .replace(/^(\d{3}\.\d{3}\.\d{3})(\d)/, '$1-$2');
        }

        return digits
            .replace(/^(\d{2})(\d)/, '$1.$2')
            .replace(/^(\d{2}\.\d{3})(\d)/, '$1.$2')
            .replace(/^(\d{2}\.\d{3}\.\d{3})(\d)/, '$1/$2')
            .replace(/^(\d{2}\.\d{3}\.\d{3}\/\d{4})(\d)/, '$1-$2');
    };

    const formatPhone = (value) => {
        const digits = onlyDigits(value);
        if (digits.length <= 10) {
            return digits
                .replace(/^(\d{2})(\d)/, '($1) $2')
                .replace(/^(\(\d{2}\) \d{4})(\d)/, '$1-$2');
        }

        return digits
            .replace(/^(\d{2})(\d)/, '($1) $2')
            .replace(/^(\(\d{2}\) \d{5})(\d)/, '$1-$2')
            .slice(0, 15);
    };

    const lookupCep = async (cep) => {
        try {
            const response = await fetch(`/api/v1/cep/${cep}`, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
            });

            if (!response.ok) {
                return null;
            }

            const data = await response.json();
            if (data.erro) {
                return null;
            }

            return data;
        } catch (error) {
            return null;
        }
    };

    const fillAddress = (data) => {
        if (!data) return;
        document.querySelector('[name="logradouro"]').value = data.logradouro || '';
        document.querySelector('[name="bairro"]').value = data.bairro || '';
        document.querySelector('[name="cidade"]').value = data.localidade || '';
        document.querySelector('[name="uf"]').value = data.uf || '';
        if (numeroInput && document.activeElement !== numeroInput) {
            numeroInput.focus();
        }
    };

    const debounce = (fn, delay = 300) => {
        let timer = null;
        return (...args) => {
            clearTimeout(timer);
            timer = setTimeout(() => fn(...args), delay);
        };
    };

    const handleCepChange = async () => {
        const cep = onlyDigits(cepInput.value);
        cepInput.value = formatCep(cep);
        if (cep.length !== 8) return;

        const data = await lookupCep(cep);
        if (data) {
            fillAddress(data);
        }
    };

    const debouncedCep = debounce(handleCepChange, 300);

    cepInput?.addEventListener('input', () => {
        cepInput.value = formatCep(cepInput.value);
        debouncedCep();
    });

    cepInput?.addEventListener('blur', handleCepChange);
    phoneInput?.addEventListener('input', () => phoneInput.value = formatPhone(phoneInput.value));
    whatsappInput?.addEventListener('input', () => whatsappInput.value = formatPhone(whatsappInput.value));

    const personTypeSelect = document.getElementById('person_type');
    const wrapCpf = document.getElementById('wrap-cpf');
    const wrapCnpj = document.getElementById('wrap-cnpj');
    const cpfInput = document.getElementById('customer_cpf');
    const cnpjInput = document.getElementById('customer_cnpj');

    const updatePersonTypeUI = () => {
        const val = personTypeSelect ? personTypeSelect.value : 'PF';
        if (val === 'PJ') {
            if (wrapCpf) wrapCpf.style.display = 'none';
            if (wrapCnpj) wrapCnpj.style.display = 'block';
        } else {
            if (wrapCpf) wrapCpf.style.display = 'block';
            if (wrapCnpj) wrapCnpj.style.display = 'none';
        }
    };

    personTypeSelect?.addEventListener('change', updatePersonTypeUI);
    updatePersonTypeUI();

    cpfInput?.addEventListener('input', () => {
        const d = onlyDigits(cpfInput.value).slice(0, 11);
        cpfInput.value = d
            .replace(/^(\d{3})(\d)/, '$1.$2')
            .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
            .replace(/\.(\d{3})(\d)/, '.$1-$2');
    });

    cnpjInput?.addEventListener('input', () => {
        const d = onlyDigits(cnpjInput.value).slice(0, 14);
        cnpjInput.value = d
            .replace(/^(\d{2})(\d)/, '$1.$2')
            .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
            .replace(/\.(\d{3})(\d)/, '.$1/$2')
            .replace(/(\d{4})(\d)/, '$1-$2');
    });

    const avatarZone = document.getElementById('avatar-drop-zone');
    if (avatarZone) {
        const avatarInput = avatarZone.querySelector('.upload-zone-input');
        const avatarContent = avatarZone.querySelector('.upload-zone-content');
        const defaultHtml = avatarContent.innerHTML;
        const previewWrap = document.getElementById('avatar-preview-wrap');
        const previewImg = document.getElementById('avatar-preview-img');
        const removeAvatarCheck = document.getElementById('remove_avatar');

        avatarZone.addEventListener('dragover', e => { e.preventDefault(); avatarZone.classList.add('dragover'); });
        avatarZone.addEventListener('dragleave', () => avatarZone.classList.remove('dragover'));
        avatarZone.addEventListener('drop', () => avatarZone.classList.remove('dragover'));

        avatarInput.addEventListener('change', () => {
            if (avatarInput.files && avatarInput.files.length > 0) {
                const file = avatarInput.files[0];
                const objectUrl = URL.createObjectURL(file);
                previewImg.src = objectUrl;
                previewWrap.style.setProperty('display', 'flex', 'important');
                if (removeAvatarCheck) removeAvatarCheck.checked = false;
                
                avatarContent.innerHTML = `<i class="bi bi-image fs-2 text-success"></i><p class="mb-0 small fw-semibold text-success">Pronto para salvar: ${file.name}</p>`;
            } else {
                avatarContent.innerHTML = defaultHtml;
            }
        });
    }

    document.getElementById('frm-customer').addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        const id = form.dataset.id;
        const isEdit = id && id !== '';
        const url = isEdit
            ? `{{ url('/admin/billing/customers') }}/${id}`
            : `{{ route('admin.billing.customers.store') }}`;

        const fd = new FormData(form);
        if (isEdit) {
            fd.set('_method', 'PUT');
        }

        const r = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: fd,
        });
        const data = await r.json().catch(() => ({}));

        if (r.ok && data.ok) {
            window.toast.success(isEdit ? 'Cliente atualizado.' : 'Cliente criado.');
            if (data.redirect) {
                setTimeout(() => window.location = data.redirect, 500);
            }
        } else {
            const errors = data.errors ? Object.values(data.errors).flat().join(', ') : (data.error || 'Erro ao salvar.');
            window.toast.error(errors);
        }
    });

    document.getElementById('btn-delete')?.addEventListener('click', async () => {
        const ok = await Swal.fire({
            icon: 'warning',
            title: 'Excluir cliente?',
            text: 'Clientes com faturas vinculadas não podem ser removidos.',
            showCancelButton: true,
            confirmButtonText: 'Excluir',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545'
        });

        if (!ok.isConfirmed) {
            return;
        }

        const id = document.getElementById('frm-customer').dataset.id;
        const r = await fetch(`{{ url('/admin/billing/customers') }}/${id}`, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        });
        const d = await r.json().catch(() => ({}));

        if (d.ok) {
            window.toast.success('Cliente excluído.');
            setTimeout(() => window.location = '{{ route("admin.billing.customers.index") }}', 500);
        } else {
            window.toast.error(d.error || 'Erro ao excluir.');
        }
    });
})();
</script>
@endpush
