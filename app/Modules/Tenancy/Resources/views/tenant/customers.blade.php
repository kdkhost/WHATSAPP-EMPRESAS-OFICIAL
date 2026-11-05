@extends('tenancy::tenant.layout')
@section('title', 'Meus Clientes')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="m-0"><i class="bi bi-people me-2"></i>Meus Clientes</h4>
    <div>
        <button type="button" class="btn btn-outline-secondary me-2" onclick="syncWhmcs()">
            <i class="bi bi-arrow-repeat me-2"></i>Sincronizar WHMCS
        </button>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#customerModal">
            <i class="bi bi-plus-lg me-2"></i>Novo Cliente
        </button>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span>Lista de Clientes</span>
        <span class="badge bg-secondary">{{ $customers->count() }} total</span>
    </div>
    <div class="card-body p-3">
        @if($customers->count())
        <div>
            <table class="table table-bordered table-striped dt-responsive nowrap w-100 app-datatable" id="table-tenant-customers" style="width: 100% !important;" data-export-title="Meus Clientes">
                <thead class="table-light">
                    <tr>
                        <th class="all" data-priority="1">Nome</th>
                        <th class="desktop">Email</th>
                        <th class="all" data-priority="1">WhatsApp</th>
                        <th class="desktop">Documento</th>
                        <th class="min-tablet" data-priority="2">Status</th>
                        <th class="desktop">Parcelas restantes</th>
                        <th class="all text-end no-export" data-priority="1" style="width: 140px; min-width: 130px; max-width: 150px; white-space: nowrap;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($customers as $customer)
                    <tr>
                        <td>
                            <strong>{{ $customer->name }}</strong>
                            @if(!empty($customer->meta['whmcs_id']))
                                <span class="badge bg-secondary ms-1" style="font-size: 0.65em; vertical-align: middle;">WHMCS</span>
                            @endif
                        </td>
                        <td>{{ $customer->email }}</td>
                        <td>
                            @if(!empty($customer->whatsapp))
                                <div class="d-flex align-items-center gap-1 text-nowrap">
                                    <i class="bi bi-whatsapp text-success"></i>
                                    <span>{{ \App\Support\Format::phone($customer->whatsapp) }}</span>
                                    @if($customer->whatsapp_optin)
                                        <span class="badge bg-success-subtle text-success border border-success-subtle p-1 ms-1" title="Quero Receber Mensagens WhatsApp: Sim (Opt-in)">
                                            <i class="bi bi-check-lg"></i>
                                        </span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle p-1 ms-1" title="Quero Receber Mensagens WhatsApp: Não (Opt-out)">
                                            <i class="bi bi-slash-circle"></i>
                                        </span>
                                    @endif
                                </div>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                            @if(isset($customer->meta['whatsapp_name']))
                                <br><small class="text-success"><i class="bi bi-person-badge me-1"></i>{{ $customer->meta['whatsapp_name'] }}</small>
                            @endif
                        </td>
                        <td>
                            @php
                                $cleanDoc = preg_replace('/\D/', '', (string)($customer->document ?: ($customer->cnpj ?: $customer->cpf)));
                                $isPj = $customer->person_type === 'Juridica' || strlen($cleanDoc) === 14;
                                $docFormatted = $isPj 
                                    ? \App\Support\Format::cnpj($customer->cnpj ?: $customer->document) 
                                    : \App\Support\Format::cpf($customer->cpf ?: $customer->document);
                            @endphp
                            @if($docFormatted)
                                <div class="d-flex align-items-center gap-1 text-nowrap">
                                    <span class="badge {{ $isPj ? 'bg-info-subtle text-info border border-info-subtle' : 'bg-secondary-subtle text-secondary border' }} font-monospace" style="font-size:0.7em;">
                                        {{ $isPj ? 'CNPJ' : 'CPF' }}
                                    </span>
                                    <span class="font-monospace small">{{ $docFormatted }}</span>
                                </div>
                            @else
                                <span class="text-muted small">-</span>
                            @endif

                            @if($customer->formatted_birth_date)
                                <div class="small text-muted mt-1" title="Data de Nascimento">
                                    <i class="bi bi-cake2 text-warning me-1"></i>{{ $customer->formatted_birth_date }}
                                </div>
                            @endif
                        </td>
                        <td>
                            @if($customer->active)
                            <span class="badge bg-success fs-6">Ativo</span>
                            @else
                            <span class="badge bg-secondary fs-6">Inativo</span>
                            @endif
                        </td>
                        <td>
                            @if($customer->installments_count > 0)
                            <span class="badge bg-warning fs-6">{{ $customer->installments_count }} restantes</span>
                            @else
                            <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td class="all text-end no-export" style="width: 140px; min-width: 130px; max-width: 150px; white-space: nowrap;">
                            <div class="d-inline-flex align-items-center justify-content-end gap-1 flex-nowrap">
                                <a href="{{ route('tenant.invoices') }}?nova_avulsa=1&customer_id={{ $customer->id }}" class="btn btn-sm btn-outline-success p-1 px-2" title="Gerar Cobrança Avulsa">
                                    <i class="bi bi-file-earmark-plus"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-primary p-1 px-2" onclick="editCustomer({{ $customer->id }})" title="Editar Cliente">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger p-1 px-2" onclick="deleteCustomer({{ $customer->id }})" title="Excluir Cliente">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="text-center p-5">
            <i class="bi bi-people fs-1 text-muted"></i>
            <p class="text-muted mt-3">Nenhum cliente cadastrado.</p>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#customerModal">
                <i class="bi bi-plus-lg me-2"></i>Cadastrar Primeiro Cliente
            </button>
        </div>
        @endif
    </div>
    @if($customers instanceof \Illuminate\Pagination\LengthAwarePaginator && $customers->hasPages())
    <div class="card-footer">
        {{ $customers->links() }}
    </div>
    @endif
</div>

<!-- Modal de Cliente -->
<div class="modal fade" id="customerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="customerModalTitle">Novo Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="customerForm" method="POST" action="{{ route('tenant.customers.store') }}">
                @csrf
                <div class="modal-body">
                    <input type="hidden" id="customerId" name="id" value="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Nome *</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                    </div>
                    <div class="row">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="person_type" class="form-label">Tipo de Pessoa</label>
                            <select class="form-select" id="person_type" name="person_type">
                                <option value="Fisica">Pessoa Física</option>
                                <option value="Juridica">Pessoa Jurídica</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="cpf" class="form-label">CPF</label>
                            <input type="text" class="form-control font-monospace" id="cpf" name="cpf" placeholder="000.000.000-00">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="cnpj" class="form-label">CNPJ</label>
                            <input type="text" class="form-control font-monospace" id="cnpj" name="cnpj" placeholder="00.000.000/0000-00">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="birth_date" class="form-label">Nascimento</label>
                            <input type="text" class="form-control font-monospace" id="birth_date" name="birth_date" placeholder="DD/MM/AAAA">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="whatsapp" class="form-label">WhatsApp <small class="text-success ms-2" id="whatsapp_name_display"></small></label>
                            <input type="text" class="form-control font-monospace" id="whatsapp" name="whatsapp" placeholder="(11) 99999-9999" data-mask="phone">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="document" class="form-label">Documento Principal (CPF/CNPJ)</label>
                            <input type="text" class="form-control font-monospace" id="document" name="document" placeholder="000.000.000-00 ou 00.000.000/0000-00" data-mask="document">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="cep" class="form-label">CEP</label>
                            <input type="text" class="form-control" id="cep" name="cep" placeholder="00000-000" data-mask="cep">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="logradouro" class="form-label">Endereço</label>
                            <input type="text" class="form-control" id="logradouro" name="logradouro">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="numero" class="form-label">Número</label>
                            <input type="text" class="form-control" id="numero" name="numero">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="complemento" class="form-label">Complemento</label>
                            <input type="text" class="form-control" id="complemento" name="complemento">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="bairro" class="form-label">Bairro</label>
                            <input type="text" class="form-control" id="bairro" name="bairro">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-5 mb-3">
                            <label for="cidade" class="form-label">Cidade</label>
                            <input type="text" class="form-control" id="cidade" name="cidade">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label for="uf" class="form-label">UF</label>
                            <input type="text" class="form-control" id="uf" name="uf" maxlength="2">
                        </div>
                        <div class="col-md-5 mb-3">
                            <label for="phone" class="form-label">Telefone</label>
                            <input type="text" class="form-control" id="phone" name="phone" placeholder="(11) 99999-9999" data-mask="phone">
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-4 mb-3 p-2 bg-body-tertiary rounded border">
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" type="checkbox" id="active" name="active" checked>
                            <label class="form-check-label fw-semibold" for="active">Cliente Ativo</label>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" type="checkbox" id="whatsapp_optin" name="whatsapp_optin" value="1" checked>
                            <label class="form-check-label fw-semibold text-success" for="whatsapp_optin">
                                <i class="bi bi-whatsapp me-1"></i>Quero Receber Mensagem WhatsApp (Opt-in)
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Formatadores de Entrada de Dados (Máscaras BR)
function formatPhoneInput(value) {
    if (!value) return '';
    let digits = value.replace(/\D/g, '');
    if (digits.startsWith('55') && digits.length > 11) {
        digits = digits.slice(2);
    }
    if (digits.length <= 10) {
        return digits
            .replace(/^(\d{2})(\d)/, '($1) $2')
            .replace(/^(\(\d{2}\) \d{4})(\d)/, '$1-$2')
            .slice(0, 14);
    }
    return digits
        .replace(/^(\d{2})(\d)/, '($1) $2')
        .replace(/^(\(\d{2}\) \d{5})(\d)/, '$1-$2')
        .slice(0, 15);
}

function formatDateInput(value) {
    if (!value) return '';
    const digits = value.replace(/\D/g, '').slice(0, 8);
    if (digits.length <= 2) return digits;
    if (digits.length <= 4) return digits.replace(/^(\d{2})(\d)/, '$1/$2');
    return digits.replace(/^(\d{2})(\d{2})(\d)/, '$1/$2/$3');
}

function formatCpfInput(value) {
    if (!value) return '';
    const digits = value.replace(/\D/g, '').slice(0, 11);
    return digits
        .replace(/^(\d{3})(\d)/, '$1.$2')
        .replace(/^(\d{3}\.\d{3})(\d)/, '$1.$2')
        .replace(/^(\d{3}\.\d{3}\.\d{3})(\d)/, '$1-$2');
}

function formatCnpjInput(value) {
    if (!value) return '';
    const digits = value.replace(/\D/g, '').slice(0, 14);
    return digits
        .replace(/^(\d{2})(\d)/, '$1.$2')
        .replace(/^(\d{2}\.\d{3})(\d)/, '$1.$2')
        .replace(/^(\d{2}\.\d{3}\.\d{3})(\d)/, '$1/$2')
        .replace(/^(\d{2}\.\d{3}\.\d{3}\/\d{4})(\d)/, '$1-$2');
}

function formatDocumentInput(value) {
    if (!value) return '';
    const digits = value.replace(/\D/g, '');
    if (digits.length <= 11) {
        return formatCpfInput(digits);
    }
    return formatCnpjInput(digits);
}

function formatCepInput(value) {
    if (!value) return '';
    const digits = value.replace(/\D/g, '');
    return digits
        .replace(/^(\d{5})(\d)/, '$1-$2')
        .slice(0, 9);
}

document.addEventListener('DOMContentLoaded', () => {
    const whatsappEl = document.getElementById('whatsapp');
    const phoneEl = document.getElementById('phone');
    const docEl = document.getElementById('document');
    const cpfEl = document.getElementById('cpf');
    const cnpjEl = document.getElementById('cnpj');
    const birthEl = document.getElementById('birth_date');
    const cepEl = document.getElementById('cep');

    whatsappEl?.addEventListener('input', (e) => { e.target.value = formatPhoneInput(e.target.value); });
    phoneEl?.addEventListener('input', (e) => { e.target.value = formatPhoneInput(e.target.value); });
    docEl?.addEventListener('input', (e) => { e.target.value = formatDocumentInput(e.target.value); });
    cpfEl?.addEventListener('input', (e) => { 
        e.target.value = formatCpfInput(e.target.value);
        if (docEl && (!docEl.value || docEl.value.replace(/\D/g, '').length <= 11)) {
            docEl.value = e.target.value;
        }
    });
    cnpjEl?.addEventListener('input', (e) => { 
        e.target.value = formatCnpjInput(e.target.value);
        if (docEl) {
            docEl.value = e.target.value;
        }
    });
    birthEl?.addEventListener('input', (e) => { e.target.value = formatDateInput(e.target.value); });
    cepEl?.addEventListener('input', (e) => { e.target.value = formatCepInput(e.target.value); });

    // Busca automática de endereço pelo CEP
    cepEl?.addEventListener('blur', async (e) => {
        const cep = e.target.value.replace(/\D/g, '');
        if (cep.length === 8) {
            try {
                const res = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
                const data = await res.json();
                if (!data.erro) {
                    if (data.logradouro) document.getElementById('logradouro').value = data.logradouro;
                    if (data.bairro) document.getElementById('bairro').value = data.bairro;
                    if (data.localidade) document.getElementById('cidade').value = data.localidade;
                    if (data.uf) document.getElementById('uf').value = data.uf;
                    document.getElementById('numero')?.focus();
                }
            } catch (err) {}
        }
    });
});

function editCustomer(id) {
    fetch(`{{ route('tenant.customers.show', ':id') }}`.replace(':id', id))
        .then(response => response.json())
        .then(data => {
            const customer = data.customer || data;
            document.getElementById('customerId').value = customer.id;
            document.getElementById('name').value = customer.name || '';
            document.getElementById('email').value = customer.email || '';
            document.getElementById('whatsapp').value = formatPhoneInput(customer.whatsapp || '');
            
            const whatsappNameDisplay = document.getElementById('whatsapp_name_display');
            if (customer.meta && customer.meta.whatsapp_name) {
                whatsappNameDisplay.innerHTML = `<i class="bi bi-check-circle"></i> Perfil: ${customer.meta.whatsapp_name}`;
            } else {
                whatsappNameDisplay.innerHTML = '';
            }

            const cleanDoc = (customer.document || '').replace(/\D/g, '');
            const cleanCpf = (customer.cpf || (cleanDoc.length === 11 ? cleanDoc : '')).replace(/\D/g, '');
            const cleanCnpj = (customer.cnpj || (cleanDoc.length === 14 ? cleanDoc : '')).replace(/\D/g, '');

            document.getElementById('person_type').value = customer.person_type || (cleanCnpj.length === 14 ? 'Juridica' : 'Fisica');
            document.getElementById('cpf').value = formatCpfInput(cleanCpf);
            document.getElementById('cnpj').value = formatCnpjInput(cleanCnpj);

            const rawBirth = customer.birth_date || (customer.meta && customer.meta.birth_date ? customer.meta.birth_date : '');
            if (rawBirth && rawBirth.includes('-')) {
                const parts = rawBirth.split('T')[0].split('-');
                if (parts.length === 3) {
                    document.getElementById('birth_date').value = `${parts[2]}/${parts[1]}/${parts[0]}`;
                } else {
                    document.getElementById('birth_date').value = formatDateInput(rawBirth);
                }
            } else {
                document.getElementById('birth_date').value = formatDateInput(customer.formatted_birth_date || rawBirth);
            }

            document.getElementById('whatsapp_optin').checked = customer.whatsapp_optin !== false && (customer.meta?.whatsapp_optin !== false);

            document.getElementById('document').value = formatDocumentInput(customer.document || cleanCnpj || cleanCpf);
            document.getElementById('cep').value = formatCepInput(customer.cep || '');
            document.getElementById('logradouro').value = customer.logradouro || '';
            document.getElementById('numero').value = customer.numero || '';
            document.getElementById('complemento').value = customer.complemento || '';
            document.getElementById('bairro').value = customer.bairro || '';
            document.getElementById('cidade').value = customer.cidade || '';
            document.getElementById('uf').value = customer.uf || '';
            document.getElementById('phone').value = formatPhoneInput(customer.phone || '');
            document.getElementById('active').checked = !!customer.active;
            
            document.getElementById('customerModalTitle').textContent = 'Editar Cliente';
            document.getElementById('customerForm').action = `{{ route('tenant.customers.update', ':id') }}`.replace(':id', customer.id);
            new bootstrap.Modal(document.getElementById('customerModal')).show();
        });
}

function deleteCustomer(id) {
    Swal.fire({
        title: 'Tem certeza?',
        text: 'Deseja excluir este cliente?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sim, excluir',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`{{ route('tenant.customers.destroy', ':id') }}`.replace(':id', id), {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.ok) {
                    window.location.reload();
                } else {
                    window.toast.error('Erro ao excluir cliente: ' + (data.message || 'Erro desconhecido'));
                }
            });
        }
    });
}

document.getElementById('customerForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const url = this.action;
    const method = document.getElementById('customerId').value ? 'PUT' : 'POST';
    
    fetch(url, {
        method: method,
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.ok) {
            window.location.reload();
        } else {
            window.toast.error('Erro ao salvar cliente: ' + (data.message || 'Erro desconhecido'));
        }
    });
});

// Resetar modal ao fechar
document.getElementById('customerModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('customerForm').reset();
    document.getElementById('customerId').value = '';
    document.getElementById('customerModalTitle').textContent = 'Novo Cliente';
    document.getElementById('customerForm').action = '{{ route('tenant.customers.store') }}';
});

function syncWhmcs() {
    Swal.fire({
        title: 'Sincronizar com WHMCS?',
        text: 'Isso importará e atualizará seus clientes a partir do seu painel WHMCS.',
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: 'Sim, sincronizar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Sincronizando...',
                text: 'Aguarde enquanto os clientes são importados.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch('{{ route('tenant.customers.sync-whmcs') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.ok) {
                    Swal.fire('Sucesso!', data.message, 'success').then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire('Erro', data.error || 'Erro desconhecido', 'error');
                }
            })
            .catch(error => {
                Swal.fire('Erro', 'Ocorreu um erro na requisição.', 'error');
            });
        }
    });
}
</script>
@endsection
