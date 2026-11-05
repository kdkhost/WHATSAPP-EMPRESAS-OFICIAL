@extends('admin.layouts.app')
@section('title', 'Calendario de Cobrancas')

@push('head')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css">
<style>
.billing-calendar-shell .fc {
    --fc-border-color: var(--bs-border-color);
    --fc-page-bg-color: transparent;
    --fc-neutral-bg-color: var(--bs-tertiary-bg);
    --fc-today-bg-color: rgba(var(--bs-primary-rgb), .08);
}
.billing-calendar-shell .fc .fc-button-primary {
    background-color: var(--bs-secondary-bg);
    border-color: var(--bs-border-color);
    color: var(--bs-body-color);
    font-weight: 600;
}
.billing-calendar-shell .fc .fc-button-primary:hover {
    background-color: var(--bs-tertiary-bg);
    border-color: var(--bs-border-color);
    color: var(--bs-body-color);
}
.billing-calendar-shell .fc .fc-button-primary:not(:disabled).fc-button-active,
.billing-calendar-shell .fc .fc-button-primary:not(:disabled):active {
    background-color: var(--bs-primary);
    border-color: var(--bs-primary);
    color: #fff;
}
.billing-calendar-shell .fc .fc-toolbar-title { font-size: 1.2rem; font-weight: 700; }
.billing-calendar-shell .fc .fc-event {
    cursor: grab;
    border-radius: .5rem;
    padding: 2px 6px;
    font-size: .78rem;
    font-weight: 700;
}
.billing-calendar-shell .fc .fc-event:active { cursor: grabbing; }
.billing-calendar-shell .fc .fc-daygrid-day-number,
.billing-calendar-shell .fc .fc-col-header-cell-cushion { color: var(--bs-body-color); }
.billing-calendar-shell .fc .fc-daygrid-day.fc-day-disabled,
.billing-calendar-shell .fc .fc-daygrid-day.billing-blocked-day {
    background: repeating-linear-gradient(135deg, rgba(var(--bs-danger-rgb), .10), rgba(var(--bs-danger-rgb), .10) 8px, transparent 8px, transparent 16px);
}
.billing-calendar-shell .billing-holiday-chip {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    border: 1px solid rgba(var(--bs-danger-rgb), .35);
    color: var(--bs-danger);
    background: rgba(var(--bs-danger-rgb), .10);
    border-radius: 999px;
    padding: .25rem .6rem;
    font-size: .8rem;
    font-weight: 700;
}
.billing-calendar-shell .fc-bg-event { opacity: .18; }
[data-bs-theme="dark"] .billing-calendar-shell .fc {
    --fc-border-color: rgba(148, 163, 184, .16);
}
[data-bs-theme="dark"] .billing-calendar-shell .fc .fc-daygrid-day-frame,
[data-bs-theme="dark"] .billing-calendar-shell .fc .fc-timegrid-slot,
[data-bs-theme="dark"] .billing-calendar-shell .fc .fc-list-day-cushion {
    background: transparent;
}
[data-bs-theme="dark"] .billing-calendar-shell .fc .fc-list-event:hover td {
    background: rgba(255, 255, 255, .05);
}
</style>
@endpush

@section('content')
<div class="container-fluid billing-calendar-shell">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h3 class="mb-1"><i class="bi bi-calendar3 me-2 text-primary"></i>Calendario de Cobrancas</h3>
            <p class="text-muted mb-0 small">Arraste vencimentos, clique para editar, selecione um dia util para criar cobranca e cancele eventos sem sair do calendario.</p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <button type="button" class="btn btn-primary btn-sm" id="btnNewInvoice">
                <i class="bi bi-plus-lg me-1"></i> Nova cobranca
            </button>
            <a href="{{ route('admin.billing.invoices.index') }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-receipt me-1"></i> Faturas
            </a>
            <a href="{{ route('admin.holidays.index') }}" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-calendar-x me-1"></i> Feriados
            </a>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body py-2">
            <div class="d-flex flex-wrap gap-3 align-items-center small">
                <span class="fw-semibold text-muted">Legenda:</span>
                <span><span class="badge" style="background:#198754;">&nbsp;</span> Paga</span>
                <span><span class="badge" style="background:#ffc107;">&nbsp;</span> Pendente</span>
                <span><span class="badge" style="background:#0d6efd;">&nbsp;</span> Enviada</span>
                <span><span class="badge" style="background:#dc3545;">&nbsp;</span> Vencida</span>
                <span><span class="badge" style="background:#6c757d;">&nbsp;</span> Cancelada</span>
                <span><span class="badge" style="background:#0dcaf0;">&nbsp;</span> Envio agendado</span>
                <span class="billing-holiday-chip"><i class="bi bi-calendar-x"></i> Feriado / fim de semana bloqueado</span>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header d-flex align-items-center justify-content-between gap-2 flex-wrap">
            <strong><i class="bi bi-calendar-event me-1 text-primary"></i> Agenda operacional</strong>
            <small class="text-muted">FullCalendar 6: mes, semana, dia, lista, selecao, drag/drop, resize e edicao por modal.</small>
        </div>
        <div class="card-body">
            <div id="calendar"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="invoiceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form class="modal-content" id="invoiceForm">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="invoiceModalTitle">Nova cobranca</h5>
                    <div class="small text-muted" id="invoiceModalSubtitle">Somente dias uteis podem receber vencimentos.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="invoiceId">
                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="form-label">Cliente</label>
                        <input type="search" class="form-control mb-2" id="customerSearch" placeholder="Buscar por nome, email, documento ou WhatsApp">
                        <select class="form-select" id="customerId" required></select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Vencimento</label>
                        <input type="date" class="form-control" id="dueDate" required>
                        <div class="form-text text-danger d-none" id="dueDateWarning">Data bloqueada para cobranca.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Valor</label>
                        <input type="text" class="form-control" id="amount" placeholder="R$ 0,00" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Gateway</label>
                        <select class="form-select" id="gateway">
                            <option value="mercadopago">Mercado Pago</option>
                            <option value="efipro">Efi Pro</option>
                            <option value="sumup">SumUp</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="status">
                            <option value="pending">Pendente</option>
                            <option value="sent">Enviada</option>
                            <option value="overdue">Vencida</option>
                            <option value="canceled">Cancelada</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Descricao</label>
                        <textarea class="form-control" id="description" rows="5" required></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-danger d-none" id="btnCancelInvoice">
                        <i class="bi bi-x-circle me-1"></i> Cancelar cobranca
                    </button>
                    <a href="#" class="btn btn-outline-secondary d-none" id="btnOpenInvoice">
                        <i class="bi bi-box-arrow-up-right me-1"></i> Abrir detalhe
                    </a>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2-circle me-1"></i> Salvar
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="dispatchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Envio agendado</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <dl class="row mb-0">
                    <dt class="col-4">Fatura</dt><dd class="col-8" id="dispatchInvoice">-</dd>
                    <dt class="col-4">Cliente</dt><dd class="col-8" id="dispatchCustomer">-</dd>
                    <dt class="col-4">Agenda</dt><dd class="col-8" id="dispatchDate">-</dd>
                    <dt class="col-4">Status</dt><dd class="col-8" id="dispatchStatus">-</dd>
                </dl>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-outline-danger" id="btnCancelDispatch">
                    <i class="bi bi-x-circle me-1"></i> Cancelar envio
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/locales/pt-br.global.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const csrf = document.querySelector('meta[name=csrf-token]').content;
    const calendarEl = document.getElementById('calendar');
    const blockedDates = new Set();
    let selectedEventId = null;
    let customerTimer = null;

    const invoiceModal = new bootstrap.Modal(document.getElementById('invoiceModal'));
    const dispatchModal = new bootstrap.Modal(document.getElementById('dispatchModal'));

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'pt-br',
        timeZone: 'America/Sao_Paulo',
        height: 720,
        selectable: true,
        nowIndicator: true,
        weekNumbers: true,
        businessHours: { daysOfWeek: [1, 2, 3, 4, 5], startTime: '08:00', endTime: '18:00' },
        navLinks: true,
        dayMaxEvents: true,
        editable: true,
        eventStartEditable: true,
        eventDurationEditable: true,
        eventResizableFromStart: true,
        eventDisplay: 'block',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
        },
        buttonText: {
            today: 'Hoje',
            month: 'Mes',
            week: 'Semana',
            day: 'Dia',
            list: 'Lista',
            prev: '\u2039',
            next: '\u203A',
        },
        events: async function(fetchInfo, success, failure) {
            try {
                const url = new URL('{{ route("admin.billing.calendar.events") }}', window.location.origin);
                url.searchParams.set('start', fetchInfo.startStr);
                url.searchParams.set('end', fetchInfo.endStr);
                const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const events = await response.json();
                blockedDates.clear();
                events.forEach(event => {
                    if (event.extendedProps?.kind === 'holiday' && event.extendedProps?.blocked && event.start) {
                        blockedDates.add(event.start.substring(0, 10));
                    }
                });
                success(events);
            } catch (error) {
                failure(error);
            }
        },
        datesSet: function() {
            markBlockedWeekendDays();
        },
        selectAllow: function(selectInfo) {
            return isAllowedDate(selectInfo.startStr);
        },
        eventAllow: function(dropInfo, draggedEvent) {
            if (draggedEvent.extendedProps.kind === 'holiday') return false;
            return isAllowedDate(dropInfo.startStr);
        },
        select: function(info) {
            if (!isAllowedDate(info.startStr)) {
                window.toast?.error('Nao e permitido gerar cobranca em feriado ou fim de semana.');
                calendar.unselect();
                return;
            }
            openCreateModal(info.startStr.substring(0, 10));
        },
        eventDrop: async function(info) {
            const ok = await moveEvent(info.event.id, info.event.startStr, info.event.endStr);
            if (!ok) info.revert();
        },
        eventResize: async function(info) {
            const ok = await moveEvent(info.event.id, info.event.startStr, info.event.endStr);
            if (!ok) info.revert();
        },
        eventClick: function(info) {
            info.jsEvent.preventDefault();
            const kind = info.event.extendedProps.kind;
            if (kind === 'invoice') openInvoiceModal(info.event);
            if (kind === 'dispatch') openDispatchModal(info.event);
            if (kind === 'holiday') window.toast?.warning('Feriado bloqueado para cobrancas.');
        },
        eventDidMount: function(info) {
            info.el.title = info.event.title;
        },
    });

    calendar.render();
    searchCustomers('');

    document.getElementById('btnNewInvoice').addEventListener('click', () => {
        openCreateModal(nextBusinessDate(new Date()).toISOString().substring(0, 10));
    });

    document.getElementById('customerSearch').addEventListener('input', event => {
        clearTimeout(customerTimer);
        customerTimer = setTimeout(() => searchCustomers(event.target.value), 250);
    });

    document.getElementById('dueDate').addEventListener('change', event => {
        const blocked = !isAllowedDate(event.target.value);
        document.getElementById('dueDateWarning').classList.toggle('d-none', !blocked);
    });

    document.getElementById('amount').addEventListener('input', event => {
        let value = event.target.value.replace(/\D/g, '');
        value = (Number(value || 0) / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
        event.target.value = value;
    });

    document.getElementById('invoiceForm').addEventListener('submit', async event => {
        event.preventDefault();
        const id = document.getElementById('invoiceId').value;
        const dueDate = document.getElementById('dueDate').value;
        if (!isAllowedDate(dueDate)) {
            window.toast?.error('Vencimento nao pode cair em feriado ou fim de semana.');
            return;
        }

        const payload = {
            customer_id: document.getElementById('customerId').value,
            description: document.getElementById('description').value,
            amount: document.getElementById('amount').value,
            due_date: dueDate,
            gateway: document.getElementById('gateway').value,
            status: document.getElementById('status').value,
        };

        const url = id
            ? `{{ url('/admin/billing/calendar/invoices') }}/${id}`
            : '{{ route("admin.billing.calendar.invoices.store") }}';
        const result = await jsonRequest(url, id ? 'PUT' : 'POST', payload);
        if (!result.ok) return;

        window.toast?.success(result.message || 'Cobranca salva.');
        invoiceModal.hide();
        calendar.refetchEvents();
    });

    document.getElementById('btnCancelInvoice').addEventListener('click', async () => {
        if (!selectedEventId) return;
        const [type, id] = selectedEventId.split('-');
        if (type !== 'inv') return;

        try {
            const btn = document.getElementById('btnCancelInvoice');
            const orig = btn.innerHTML;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            btn.disabled = true;

            const r = await fetch(`{{ route("admin.billing.calendar") }}/invoice-installments/${id}`, { 
                credentials: 'same-origin', 
                headers: { 'Accept': 'application/json' }
            });
            const data = await r.json();

            btn.innerHTML = orig;
            btn.disabled = false;
            
            let confirmed = false;
            let cancelInstallments = false;

            if (data.has_installments) {
                const res = await Swal.fire({
                    icon: 'warning',
                    title: 'Parcelas em aberto!',
                    text: `Encontramos ${data.count} outra(s) parcela(s) em aberto no valor de R$ ${data.amount.toFixed(2).replace('.',',')} para este cliente. O que deseja fazer?`,
                    showDenyButton: true,
                    showCancelButton: true,
                    confirmButtonText: 'Cancelar TODAS',
                    denyButtonText: 'Cancelar apenas ESTA',
                    cancelButtonText: 'Desistir',
                    confirmButtonColor: '#dc3545',
                    denyButtonColor: '#6c757d'
                });
                
                if (res.isConfirmed) {
                    confirmed = true;
                    cancelInstallments = true;
                } else if (res.isDenied) {
                    confirmed = true;
                }
            } else {
                confirmed = await confirmAction('Cancelar cobrança?', 'A cobrança e seus envios agendados serão cancelados.');
            }

            if (!confirmed) return;

            const result = await jsonRequest('{{ route("admin.billing.calendar.events.cancel") }}', 'POST', { 
                id: selectedEventId, 
                cancel_installments: cancelInstallments 
            });
            
            if (!result.ok) return;

            window.toast?.success(result.message || 'Evento cancelado.');
            invoiceModal.hide();
            dispatchModal.hide();
            calendar.refetchEvents();
        } catch (e) {
            window.toast?.error('Erro de rede.');
        }
    });

    document.getElementById('btnCancelDispatch').addEventListener('click', () => cancelSelectedEvent('Cancelar envio?', 'Somente este envio agendado será cancelado.'));

    async function moveEvent(id, start, end) {
        if (!isAllowedDate(start)) {
            window.toast?.error('Nao e permitido mover para feriado ou fim de semana.');
            return false;
        }

        const result = await jsonRequest('{{ route("admin.billing.calendar.events.move") }}', 'POST', { id, start, end });
        if (result.ok) {
            window.toast?.success('Data atualizada.');
            return true;
        }

        return false;
    }

    async function cancelSelectedEvent(title, text) {
        if (!selectedEventId) return;
        const confirmed = await confirmAction(title, text);
        if (!confirmed) return;

        const result = await jsonRequest('{{ route("admin.billing.calendar.events.cancel") }}', 'POST', { id: selectedEventId });
        if (!result.ok) return;

        window.toast?.success(result.message || 'Evento cancelado.');
        invoiceModal.hide();
        dispatchModal.hide();
        calendar.refetchEvents();
    }

    async function jsonRequest(url, method, payload) {
        try {
            const response = await fetch(url, {
                method,
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            });
            const data = await response.json().catch(() => ({}));
            if (response.ok && (data.ok ?? true)) return data;

            const message = data.message || Object.values(data.errors || {})?.[0]?.[0] || 'Nao foi possivel concluir a operacao.';
            window.toast?.error(message);
            return { ok: false };
        } catch (error) {
            window.toast?.error('Erro de rede.');
            return { ok: false };
        }
    }

    async function searchCustomers(query) {
        const url = new URL('{{ route("admin.billing.calendar.customers.search") }}', window.location.origin);
        url.searchParams.set('q', query || '');
        const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
        const customers = await response.json();
        const select = document.getElementById('customerId');
        const current = select.value;
        select.innerHTML = customers.map(customer => {
            const label = [customer.name, customer.whatsapp, customer.email].filter(Boolean).join(' - ');
            return `<option value="${customer.id}">${escapeHtml(label)}</option>`;
        }).join('');
        if (current) select.value = current;
    }

    function openCreateModal(date) {
        selectedEventId = null;
        document.getElementById('invoiceId').value = '';
        document.getElementById('invoiceModalTitle').textContent = 'Nova cobranca';
        document.getElementById('invoiceModalSubtitle').textContent = 'Criacao manual bloqueada em feriados e fins de semana.';
        document.getElementById('dueDate').value = date;
        document.getElementById('amount').value = '';
        document.getElementById('description').value = '';
        document.getElementById('gateway').value = 'mercadopago';
        document.getElementById('status').value = 'pending';
        document.getElementById('status').disabled = true;
        document.getElementById('customerId').disabled = false;
        document.getElementById('customerSearch').disabled = false;
        document.getElementById('btnCancelInvoice').classList.add('d-none');
        document.getElementById('btnOpenInvoice').classList.add('d-none');
        invoiceModal.show();
    }

    function openInvoiceModal(event) {
        const props = event.extendedProps;
        selectedEventId = event.id;
        document.getElementById('invoiceId').value = props.invoice_id;
        document.getElementById('invoiceModalTitle').textContent = `Editar ${props.number}`;
        document.getElementById('invoiceModalSubtitle').textContent = props.customer_name || 'Cliente nao informado';
        document.getElementById('dueDate').value = props.due_date;
        document.getElementById('amount').value = props.amount_formatted;
        document.getElementById('description').value = stripHtml(props.description || '');
        document.getElementById('gateway').value = props.gateway || 'mercadopago';
        document.getElementById('status').value = props.status || 'pending';
        document.getElementById('status').disabled = false;
        document.getElementById('customerId').innerHTML = `<option value="${props.customer_id || ''}">${escapeHtml(props.customer_name || 'Cliente')}</option>`;
        document.getElementById('customerId').value = props.customer_id || '';
        document.getElementById('customerId').disabled = true;
        document.getElementById('customerSearch').disabled = true;
        document.getElementById('btnCancelInvoice').classList.toggle('d-none', ['paid', 'refunded', 'canceled'].includes(props.status));
        const openButton = document.getElementById('btnOpenInvoice');
        openButton.href = props.show_url;
        openButton.classList.remove('d-none');
        invoiceModal.show();
    }

    function openDispatchModal(event) {
        const props = event.extendedProps;
        selectedEventId = event.id;
        document.getElementById('dispatchInvoice').textContent = props.invoice_number || '-';
        document.getElementById('dispatchCustomer').textContent = props.customer_name || '-';
        document.getElementById('dispatchDate').textContent = formatDateTime(event.start);
        document.getElementById('dispatchStatus').textContent = props.status || '-';
        document.getElementById('btnCancelDispatch').classList.toggle('d-none', props.status !== 'scheduled');
        dispatchModal.show();
    }

    function isAllowedDate(value) {
        if (!value) return false;
        const iso = value.substring(0, 10);
        const date = new Date(`${iso}T12:00:00`);
        const day = date.getDay();
        return day !== 0 && day !== 6 && !blockedDates.has(iso);
    }

    function nextBusinessDate(date) {
        const next = new Date(date);
        while (!isAllowedDate(next.toISOString().substring(0, 10))) {
            next.setDate(next.getDate() + 1);
        }
        return next;
    }

    function markBlockedWeekendDays() {
        calendarEl.querySelectorAll('.fc-daygrid-day').forEach(dayEl => {
            const date = dayEl.dataset.date;
            if (!date) return;
            const blocked = !isAllowedDate(date);
            dayEl.classList.toggle('billing-blocked-day', blocked);
        });
    }

    async function confirmAction(title, text) {
        if (window.Swal) {
            const result = await Swal.fire({
                icon: 'warning',
                title,
                text,
                showCancelButton: true,
                confirmButtonText: 'Confirmar',
                cancelButtonText: 'Voltar',
            });
            return result.isConfirmed;
        }
        return Promise.resolve(false);
    }

    function formatDateTime(date) {
        if (!date) return '-';
        return date.toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
    }

    function stripHtml(html) {
        const div = document.createElement('div');
        div.innerHTML = html;
        return div.textContent || div.innerText || '';
    }

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, char => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        }[char]));
    }
});
</script>
@endpush
