@extends('admin.layouts.app')

@section('title', 'Nova Fatura')

@push('head')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
<style>
    .installment-preview {
        background: color-mix(in srgb, var(--bs-primary) 5%, var(--bs-body-bg));
        border: 1px solid color-mix(in srgb, var(--bs-primary) 20%, transparent);
        border-radius: 1rem;
        padding: 1.5rem;
    }
    .installment-item {
        display: flex;
        justify-content: space-between;
        padding: 0.75rem 0;
        border-bottom: 1px solid var(--bs-border-color-translucent);
    }
    .installment-item:last-child {
        border-bottom: none;
    }
    .total-highlight {
        font-size: 2rem;
        font-weight: 800;
        color: var(--bs-primary);
        letter-spacing: -0.05em;
    }
    /* Customização Premium do Select2 */
    .select2-container--bootstrap-5 {
        display: block;
        width: 100% !important;
    }
    .select2-container--bootstrap-5 .select2-selection {
        min-height: 48px !important;
        padding: 0.5rem 0.85rem !important;
        font-size: 0.95rem !important;
        border-radius: 0.6rem !important;
        border: 1px solid var(--bs-border-color) !important;
        background-color: var(--bs-body-bg) !important;
        color: var(--bs-body-color) !important;
        display: flex !important;
        align-items: center !important;
        transition: all .2s ease-in-out !important;
    }
    .select2-container--bootstrap-5.select2-container--focus .select2-selection,
    .select2-container--bootstrap-5.select2-container--open .select2-selection {
        border-color: var(--bs-primary) !important;
        box-shadow: 0 0 0 0.25rem color-mix(in srgb, var(--bs-primary) 20%, transparent) !important;
    }
    .select2-container--bootstrap-5 .select2-selection__rendered {
        color: var(--bs-body-color) !important;
        padding: 0 !important;
        line-height: normal !important;
        font-weight: 500;
        width: 100%;
    }
    .select2-container--bootstrap-5 .select2-selection__placeholder {
        color: var(--bs-secondary-color) !important;
    }
    /* Dropdown sempre elegante para baixo */
    .select2-container--bootstrap-5 .select2-dropdown {
        border-radius: 0.75rem !important;
        border: 1px solid var(--bs-border-color) !important;
        background-color: var(--bs-body-bg) !important;
        color: var(--bs-body-color) !important;
        box-shadow: 0 16px 36px rgba(0, 0, 0, 0.18), 0 4px 12px rgba(0, 0, 0, 0.08) !important;
        overflow: hidden !important;
        z-index: 9999 !important;
        margin-top: 4px !important;
    }
    .select2-container--bootstrap-5 .select2-dropdown--above {
        margin-top: 4px !important;
        border-top: 1px solid var(--bs-border-color) !important;
        border-radius: 0.75rem !important;
    }
    .select2-container--bootstrap-5 .select2-search--dropdown {
        padding: 0.75rem 0.85rem !important;
        background-color: color-mix(in srgb, var(--bs-primary) 5%, var(--bs-body-bg)) !important;
        border-bottom: 1px solid var(--bs-border-color) !important;
    }
    .select2-container--bootstrap-5 .select2-search__field {
        border-radius: 0.5rem !important;
        padding: 0.6rem 0.85rem !important;
        font-size: 0.92rem !important;
        background-color: var(--bs-body-bg) !important;
        color: var(--bs-body-color) !important;
        border: 1px solid var(--bs-border-color) !important;
        outline: none !important;
        box-shadow: none !important;
    }
    .select2-container--bootstrap-5 .select2-search__field:focus {
        border-color: var(--bs-primary) !important;
        box-shadow: 0 0 0 0.2rem color-mix(in srgb, var(--bs-primary) 20%, transparent) !important;
    }
    .select2-container--bootstrap-5 .select2-results__options {
        max-height: 290px !important;
        padding: 0.4rem !important;
    }
    .select2-container--bootstrap-5 .select2-results__option {
        border-radius: 0.5rem !important;
        padding: 0.6rem 0.85rem !important;
        margin-bottom: 3px !important;
        color: var(--bs-body-color) !important;
        transition: background-color .15s ease;
    }
    .select2-container--bootstrap-5 .select2-results__option--highlighted[aria-selected] {
        background-color: color-mix(in srgb, var(--bs-primary) 12%, var(--bs-body-bg)) !important;
        color: var(--bs-body-color) !important;
    }
    .select2-container--bootstrap-5 .select2-results__option--selected {
        background-color: var(--bs-primary) !important;
        color: #fff !important;
    }
    .select2-container--bootstrap-5 .select2-results__option--selected .text-muted,
    .select2-container--bootstrap-5 .select2-results__option--selected .select2-customer-name,
    .select2-container--bootstrap-5 .select2-results__option--selected .badge {
        color: #fff !important;
        border-color: rgba(255,255,255,0.4) !important;
        background-color: rgba(255,255,255,0.2) !important;
    }
    [data-bs-theme="dark"] .select2-customer-name {
        color: #f8fafc !important;
    }
    [data-bs-theme="light"] .select2-customer-name {
        color: #0f172a !important;
    }
</style>
@endpush

@section('content')
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <x-ui.page-header
                title="Criar Fatura Avulsa"
                subtitle="Gere uma cobrança única ou parcelada (carnê) sem depender de sincronização externa."
                eyebrow="Cobranças">
                <x-slot:actions>
                    <a href="{{ route('admin.billing.invoices.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Voltar
                    </a>
                </x-slot:actions>
            </x-ui.page-header>

            <form action="{{ route('admin.billing.invoices.store') }}" method="POST" id="invoiceForm">
                @csrf
                
                <x-ui.premium-card title="Informações do Cliente" subtitle="Selecione o cliente que receberá esta cobrança." icon="bi-person-badge" class="mb-4">
                    <div class="mb-3 position-relative">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label text-muted small fw-semibold text-uppercase letter-spacing-1 mb-0">Buscar Cliente</label>
                            <div>
                                @php
                                    $showWhmcsSync = false;
                                    if (Auth::guard('admin')->check()) {
                                        $showWhmcsSync = true;
                                    } else {
                                        $tenantId = \App\Modules\Tenancy\Models\Tenant::where('user_id', Auth::guard('web')->id())->value('id');
                                        if ($tenantId) {
                                            $showWhmcsSync = \App\Modules\WhmcsIntegration\Models\WhmcsConnection::where('tenant_id', $tenantId)->where('active', true)->exists();
                                        }
                                    }
                                @endphp
                                @if($showWhmcsSync)
                                    <button type="button" class="btn btn-sm btn-outline-info me-2" id="btn-sync-whmcs">
                                        <i class="bi bi-arrow-repeat"></i> Sincronizar WHMCS
                                    </button>
                                @endif
                                <a href="{{ route('admin.billing.customers.create') }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-plus-lg"></i> Novo Cliente
                                </a>
                            </div>
                        </div>
                        <select name="customer_id" id="customer_id" class="form-select" required>
                            <option value=""></option>
                            @if(isset($customers) && $customers->count() > 0)
                                @foreach($customers as $c)
                                    @php
                                        $doc = $c->formatted_document ?? $c->document;
                                        $zap = $c->formatted_whatsapp ?? $c->whatsapp;
                                        $tenantName = $c->tenant && filled($c->tenant->company_name) ? $c->tenant->company_name : 'Master';
                                    @endphp
                                    <option value="{{ $c->id }}"
                                        data-name="{{ $c->name }}"
                                        data-document="{{ $doc }}"
                                        data-whatsapp="{{ $zap }}"
                                        data-email="{{ $c->email }}"
                                        data-tenant="{{ $tenantName }}"
                                        {{ (string) old('customer_id') === (string) $c->id ? 'selected' : '' }}>
                                        {{ $c->name }}
                                    </option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                    
                    <div class="form-floating mt-4">
                        <input type="text" name="description" id="description" class="form-control" placeholder=" " required>
                        <label for="description">Descrição / Referência do Serviço</label>
                    </div>
                </x-ui.premium-card>

                <x-ui.premium-card title="Valores e Parcelamento" subtitle="Defina o valor base e as condições de pagamento." icon="bi-calculator" class="mb-4">
                    <div class="row g-4">
                        <div class="col-md-4">
                            <div class="form-floating">
                                <input type="text" name="amount" id="amount" class="form-control fw-bold text-primary fs-5 money-mask" placeholder=" " required>
                                <label for="amount">Valor Base (R$)</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating">
                                <select name="installments" id="installments" class="form-select fw-semibold">
                                    @for($i = 1; $i <= 24; $i++)
                                        <option value="{{ $i }}">{{ $i }}x {{ $i === 1 ? '(À vista)' : 'parcelas' }}</option>
                                    @endfor
                                </select>
                                <label for="installments">Parcelamento</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating">
                                <input type="number" name="interest_rate" id="interest_rate" class="form-control fw-semibold" placeholder=" " value="0" min="0" step="0.1">
                                <label for="interest_rate">Juros ao Mês (%)</label>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4 mt-2">
                        <div class="col-md-4">
                            <div class="form-floating">
                                <input type="date" name="first_due_date" id="first_due_date" class="form-control" value="{{ date('Y-m-d', strtotime('+1 day')) }}" required>
                                <label for="first_due_date">1º Vencimento</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating">
                                <select name="gateway" id="gateway" class="form-select">
                                    <option value="">(Padrão do Sistema)</option>
                                    <option value="mercadopago">Mercado Pago</option>
                                    <option value="efipro">Efi Pro</option>
                                    <option value="sumup">SumUp</option>
                                </select>
                                <label for="gateway">Gateway Preferencial</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating">
                                <select name="send_behavior" id="send_behavior" class="form-select">
                                    <option value="none">Agendamento Automático (Padrão)</option>
                                    <option value="immediate">Disparar Imediatamente (Agora)</option>
                                    <option value="next_cron">Enviar no Próximo Cron (Fila)</option>
                                    <option value="custom_date">Agendar para Data Específica</option>
                                </select>
                                <label for="send_behavior">Envio da 1ª Parcela</label>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4 mt-2" id="custom_date_row" style="display: none;">
                        <div class="col-md-4">
                            <div class="form-floating">
                                <input type="datetime-local" name="custom_send_date" id="custom_send_date" class="form-control" value="{{ date('Y-m-d\TH:i') }}">
                                <label for="custom_send_date">Data e Hora do Disparo</label>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 installment-preview" id="preview-box" style="display: none;">
                        <h5 class="fw-bold mb-4 text-primary"><i class="bi bi-magic me-2"></i>Simulação do Carnê</h5>
                        <div class="row">
                            <div class="col-md-6 border-end">
                                <div class="text-muted mb-1 small text-uppercase fw-semibold letter-spacing-1">Valor Total a Pagar</div>
                                <div class="total-highlight" id="preview-total">R$ 0,00</div>
                                <div class="text-muted small mt-2" id="preview-interest-info">Com 0% de juros (R$ 0,00 acréscimo)</div>
                            </div>
                            <div class="col-md-6 ps-md-4">
                                <div id="preview-list"></div>
                            </div>
                        </div>
                    </div>
                    
                    <x-slot:actions>
                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('admin.billing.invoices.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary shadow-sm" id="btnSubmit">
                                <i class="bi bi-check2-circle me-1"></i> Gerar Fatura(s)
                            </button>
                        </div>
                    </x-slot:actions>
                </x-ui.premium-card>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var btnSync = document.getElementById('btn-sync-whmcs');
    if (btnSync) {
        btnSync.addEventListener('click', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Sincronizando...',
                text: 'Buscando e atualizando clientes do WHMCS...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch('{{ route("admin.billing.customers.sync-whmcs") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                }
            })
            .then(res => res.json())
            .then(res => {
                if(res.ok) {
                    Swal.fire('Sucesso!', res.message || 'Sincronização concluída com sucesso.', 'success');
                } else {
                    Swal.fire('Aviso', res.message || 'Aviso durante sincronização.', 'warning');
                }
            })
            .catch(err => {
                Swal.fire('Erro!', 'Falha de comunicação com a API.', 'error');
            });
        });
    }
});
</script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-mask-plugin@1.14.16/dist/jquery.mask.min.js"></script>
<script>
$(document).ready(function() {
    $('.money-mask').mask('#.##0,00', {reverse: true});

    function formatCustomerOption(item) {
        if (!item.id) return item.text;
        var el = item.element;
        var name = (el ? $(el).data('name') : '') || item.name || item.text || '';
        var doc = (el ? $(el).data('document') : '') || item.document || '';
        var whatsapp = (el ? $(el).data('whatsapp') : '') || item.whatsapp || '';
        var tenant = (el ? $(el).data('tenant') : '') || item.tenant_name || '';

        var badge = '';
        if (tenant && tenant !== 'Master') {
            badge = '<span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-1"><i class="bi bi-building me-1"></i>' + tenant + '</span>';
        } else {
            badge = '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2 py-1">Master</span>';
        }

        var details = [];
        if (doc) details.push('<span><i class="bi bi-person-vcard me-1 opacity-75"></i>' + doc + '</span>');
        if (whatsapp) details.push('<span><i class="bi bi-whatsapp me-1 text-success"></i>' + whatsapp + '</span>');
        var detailsHtml = details.length ? '<div class="small text-muted mt-1 d-flex gap-3 align-items-center flex-wrap">' + details.join('') + '</div>' : '';

        return $(
            '<div class="d-flex justify-content-between align-items-center py-1">' +
                '<div class="me-2">' +
                    '<div class="fw-bold select2-customer-name fs-6">' + name + '</div>' +
                    detailsHtml +
                '</div>' +
                '<div class="flex-shrink-0">' + badge + '</div>' +
            '</div>'
        );
    }

    function formatCustomerSelection(item) {
        if (!item.id) return item.text;
        var el = item.element;
        var name = (el ? $(el).data('name') : '') || item.name || item.text || '';
        var doc = (el ? $(el).data('document') : '') || item.document || '';
        var tenant = (el ? $(el).data('tenant') : '') || item.tenant_name || '';
        var extra = [];
        if (doc) extra.push(doc);
        if (tenant && tenant !== 'Master') extra.push(tenant);
        return name + (extra.length ? ' (' + extra.join(' - ') + ')' : '');
    }

    function customCustomerMatcher(params, data) {
        if ($.trim(params.term) === '') {
            return data;
        }
        if (typeof data.text === 'undefined') {
            return null;
        }

        var el = data.element;
        var term = params.term.toLowerCase().trim();
        var cleanTerm = term.replace(/[\.\-\/\(\)\s]/g, '');

        var name = ((el ? $(el).data('name') : '') || data.text || '').toLowerCase();
        var doc = ((el ? $(el).data('document') : '') || '').toLowerCase();
        var cleanDoc = doc.replace(/[\.\-\/\(\)\s]/g, '');
        var whatsapp = ((el ? $(el).data('whatsapp') : '') || '').toLowerCase();
        var cleanWhatsapp = whatsapp.replace(/[\.\-\/\(\)\s]/g, '');
        var email = ((el ? $(el).data('email') : '') || '').toLowerCase();
        var tenant = ((el ? $(el).data('tenant') : '') || '').toLowerCase();

        if (
            name.indexOf(term) > -1 ||
            doc.indexOf(term) > -1 ||
            (cleanTerm && cleanDoc.indexOf(cleanTerm) > -1) ||
            whatsapp.indexOf(term) > -1 ||
            (cleanTerm && cleanWhatsapp.indexOf(cleanTerm) > -1) ||
            email.indexOf(term) > -1 ||
            tenant.indexOf(term) > -1
        ) {
            return data;
        }

        return null;
    }

    var $customerSelect = $('#customer_id');

    $customerSelect.select2({
        theme: 'bootstrap-5',
        placeholder: 'Clique para escolher ou digite para pesquisar cliente...',
        allowClear: true,
        width: '100%',
        templateResult: formatCustomerOption,
        templateSelection: formatCustomerSelection,
        ajax: {
            url: "{{ route('admin.billing.calendar.customers.search') }}",
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    q: params.term // search term
                };
            },
            processResults: function (data) {
                var results = $.map(data, function (obj) {
                    obj.text = obj.name;
                    obj.tenant_name = obj.tenant && obj.tenant.company_name ? obj.tenant.company_name : 'Master';
                    return obj;
                });
                return {
                    results: results
                };
            },
            cache: true
        }
    });

    // Forçar abertura para baixo e focar automaticamente no campo de pesquisa
    $customerSelect.on('select2:open', function() {
        var s2 = $(this).data('select2');
        if (s2 && s2.$dropdown) {
            s2.$dropdown.removeClass('select2-dropdown--above').addClass('select2-dropdown--below');
        }
        setTimeout(function() {
            var searchInput = document.querySelector('.select2-container--open .select2-search__field');
            if (searchInput) {
                searchInput.setAttribute('placeholder', 'Digite nome, CPF/CNPJ, WhatsApp ou empresa...');
                searchInput.focus();
            }
        }, 30);
    });

    const updatePreview = () => {
        let valStr = $('#amount').val().replace(/\./g, '').replace(',', '.');
        let amount = parseFloat(valStr);
        let inst = parseInt($('#installments').val()) || 1;
        let rate = parseFloat($('#interest_rate').val()) || 0;

        if (isNaN(amount) || amount <= 0) {
            $('#preview-box').slideUp();
            return;
        }

        // Formula de juros simples mensal
        let interestAmount = amount * (rate / 100) * inst;
        let total = amount + interestAmount;
        let portion = total / inst;

        $('#preview-total').text(total.toLocaleString('pt-BR', {style: 'currency', currency: 'BRL'}));
        
        if (rate > 0) {
            $('#preview-interest-info').text(`Total de juros: ` + interestAmount.toLocaleString('pt-BR', {style: 'currency', currency: 'BRL'}) + ` (`+rate+`% a.m)`);
            $('#preview-interest-info').addClass('text-danger').removeClass('text-muted');
        } else {
            $('#preview-interest-info').text(`Sem acréscimo de juros.`);
            $('#preview-interest-info').removeClass('text-danger').addClass('text-muted');
        }

        let firstDate = new Date($('#first_due_date').val() + 'T12:00:00');
        let listHtml = '';

        for(let i = 1; i <= inst; i++) {
            let d = new Date(firstDate);
            d.setMonth(d.getMonth() + (i - 1));
            
            let dateStr = d.toLocaleDateString('pt-BR', {day: '2-digit', month: '2-digit', year: 'numeric'});
            let valFormatted = portion.toLocaleString('pt-BR', {style: 'currency', currency: 'BRL'});
            
            listHtml += `
                <div class="installment-item">
                    <span class="fw-semibold text-secondary">`+i+`ª Parcela</span>
                    <span class="text-muted small">`+dateStr+`</span>
                    <span class="fw-bold text-dark">`+valFormatted+`</span>
                </div>
            `;
        }

        $('#preview-list').html(listHtml);
        $('#preview-box').slideDown();
    };

    $('#amount, #installments, #interest_rate, #first_due_date').on('input change', updatePreview);

    $('#send_behavior').on('change', function() {
        if ($(this).val() === 'custom_date') {
            $('#custom_date_row').slideDown();
        } else {
            $('#custom_date_row').slideUp();
        }
    });

    var $form = $('#invoiceForm');
    var formEl = $form[0];

    $form.on('submit', function(e) {
        e.preventDefault();
        e.stopPropagation();

        if (formEl && !formEl.checkValidity()) {
            formEl.reportValidity();
            return false;
        }

        var $btn = $('#btnSubmit');
        var originalText = $btn.html();
        var actionUrl = $form.attr('action');
        var formData = $form.serialize();

        Swal.fire({
            title: 'Confirmar Geração?',
            text: "Esta ação criará as cobranças no sistema e os links de pagamento.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#0d6efd',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sim, Gerar',
            cancelButtonText: 'Cancelar'
        }).then(function(result) {
            if (result.isConfirmed) {
                $btn.html('<span class="spinner-border spinner-border-sm me-2"></span> Processando...').prop('disabled', true);

                $.ajax({
                    url: actionUrl,
                    method: 'POST',
                    data: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    success: function(res) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Sucesso!',
                            text: res.message || 'Faturas geradas com sucesso.',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(function() {
                            if (res.invoice_id) {
                                window.location.href = "{{ url('admin/billing/invoices') }}/" + res.invoice_id;
                            } else {
                                window.location.href = "{{ route('admin.billing.invoices.index') }}";
                            }
                        });
                    },
                    error: function(xhr) {
                        $btn.html(originalText).prop('disabled', false);
                        var msg = xhr.responseJSON?.message || xhr.responseJSON?.error || 'Erro interno ao processar a fatura.';
                        Swal.fire('Erro', msg, 'error');
                    }
                });
            }
        });

        return false;
    });
});
</script>
@endpush
