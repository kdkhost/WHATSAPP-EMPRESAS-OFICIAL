@extends('tenancy::tenant.layout')

@section('title', 'Calendário Operacional & Cobranças')

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
    cursor: pointer;
    border-radius: .5rem;
    padding: 2px 6px;
    font-size: .78rem;
    font-weight: 600;
}
.billing-calendar-shell .fc .fc-daygrid-day-number,
.billing-calendar-shell .fc .fc-event.holiday-global,
.billing-calendar-shell .fc .holiday-global {
    background-color: #6f42c1 !important;
    border-color: #6f42c1 !important;
    color: #ffffff !important;
}
.billing-calendar-shell .fc .fc-event.holiday-local,
.billing-calendar-shell .fc .holiday-local {
    background-color: #0dcaf0 !important;
    border-color: #0dcaf0 !important;
    color: #000000 !important;
}
[data-bs-theme="dark"] .billing-calendar-shell .fc {
    --fc-border-color: rgba(148, 163, 184, .16);
}
[data-bs-theme="dark"] .billing-calendar-shell .fc .fc-daygrid-day-frame,
[data-bs-theme="dark"] .billing-calendar-shell .fc .fc-timegrid-slot {
    background: transparent;
}
</style>
@endpush

@section('content')
<div class="container-fluid billing-calendar-shell">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h3 class="mb-1"><i class="bi bi-calendar3 me-2 text-primary"></i>Calendário de Cobranças & Feriados</h3>
            <p class="text-muted mb-0 small">Visualize suas faturas por vencimento e os feriados que adiam cobranças automaticamente.</p>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#modal-list-holidays">
                <i class="bi bi-list-check me-1"></i> Feriados ({{ count($holidays ?? []) }})
            </button>
            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modal-add-holiday">
                <i class="bi bi-plus-lg me-1"></i> Novo Feriado Local
            </button>
            <a href="{{ route('tenant.invoices') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-receipt me-1"></i> Faturas
            </a>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body py-2">
            <div class="d-flex flex-wrap gap-3 align-items-center small">
                <span class="fw-semibold text-muted">Legenda:</span>
                <span><span class="badge" style="background:#198754;">&nbsp;</span> Paga</span>
                <span><span class="badge" style="background:#ffc107;">&nbsp;</span> Pendente</span>
                <span><span class="badge" style="background:#dc3545;">&nbsp;</span> Vencida</span>
                <span><span class="badge" style="background:#6c757d;">&nbsp;</span> Cancelada</span>
                <span class="border-start ps-3 ms-2"></span>
                <span><span class="badge" style="background:#6f42c1;">&nbsp;</span> Feriado Global (Nacional)</span>
                <span><span class="badge" style="background:#0dcaf0;">&nbsp;</span> Feriado Local (Municipal)</span>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header">
            <strong><i class="bi bi-calendar-event me-1 text-primary"></i> Agenda unificada</strong>
        </div>
        <div class="card-body">
            <div id="calendar"></div>
        </div>
    </div>
</div>

<!-- Modal Editar Fatura -->
<div class="modal fade" id="modal-edit-invoice" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-receipt me-2 text-primary"></i>Fatura <span id="modal-inv-id"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <form id="frm-edit-inv" class="row g-3">
                    <input type="hidden" name="_method" value="PUT">
                    <div class="col-12">
                        <label class="form-label text-muted small mb-1">Cliente</label>
                        <div class="fw-semibold" id="modal-inv-customer"></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label text-muted small mb-1">Descrição</label>
                        <div class="fw-semibold" id="modal-inv-desc"></div>
                    </div>
                    <div class="col-6">
                        <label class="form-label text-muted small mb-1">Valor</label>
                        <div class="fw-bold fs-5 text-primary" id="modal-inv-amount"></div>
                    </div>
                    <div class="col-6">
                        <label class="form-label text-muted small mb-1">Pago em</label>
                        <div class="fw-semibold" id="modal-inv-paid"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select" id="modal-inv-status">
                            <option value="pending">Pendente</option>
                            <option value="paid">Pago</option>
                            <option value="overdue">Vencida</option>
                            <option value="cancelled">Cancelada</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Vencimento</label>
                        <input name="due_date" type="date" class="form-control" id="modal-inv-due">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <a href="#" target="_blank" class="btn btn-outline-success d-none" id="btn-open-payment"><i class="bi bi-credit-card me-1"></i>Pagar fatura</a>
                <button type="button" class="btn btn-primary" id="btn-save-invoice"><i class="bi bi-save me-1"></i>Salvar alterações</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Novo Feriado Local -->
<div class="modal fade" id="modal-add-holiday" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="frm-new-holiday">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-calendar-plus me-2 text-primary"></i>Novo Feriado Local</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label">Data <span class="text-danger">*</span></label>
                            <input type="date" name="date" class="form-control" required>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label">Nome do Feriado <span class="text-danger">*</span></label>
                            <input name="name" class="form-control" maxlength="120" required placeholder="Ex.: Aniversário da Cidade">
                        </div>
                        <div class="col-md-7">
                            <label class="form-label">Tipo</label>
                            <select name="type" class="form-select">
                                <option value="municipal" selected>Municipal</option>
                                <option value="state">Estadual</option>
                                <option value="custom">Customizado</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">UF (opcional)</label>
                            <input name="uf" class="form-control" maxlength="2" placeholder="SP" style="text-transform: uppercase;">
                        </div>
                        <div class="col-12">
                            <label class="form-label small text-uppercase fw-semibold text-muted">Ações bloqueadas no dia</label>
                            <div class="d-flex flex-column gap-2">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="block_billing" value="1" checked id="bh1">
                                    <label class="form-check-label" for="bh1"><i class="bi bi-cash-coin text-danger me-1"></i> Adiar cobranças</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="block_dispatch" value="1" checked id="bh2">
                                    <label class="form-check-label" for="bh2"><i class="bi bi-send text-warning me-1"></i> Pausar envios WhatsApp</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="block_suspension" value="1" checked id="bh3">
                                    <label class="form-check-label" for="bh3"><i class="bi bi-pause-circle text-info me-1"></i> Pausar suspensões</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i>Salvar Feriado</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Gerenciar Feriados -->
<div class="modal fade" id="modal-list-holidays" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-calendar-check me-2 text-primary"></i>Feriados Cadastrados</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body p-0">
                <div class="alert alert-light border-0 border-bottom rounded-0 mb-0 py-2 px-3 small text-muted">
                    <i class="bi bi-info-circle me-1 text-primary"></i> Feriados <strong>Globais</strong> são cadastrados pelo sistema e válidos para todos. Feriados <strong>Próprios</strong> foram criados por você para a sua cidade.
                </div>
                <div class="table-responsive" style="max-height: 450px;">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>Data</th>
                                <th>Nome</th>
                                <th>Tipo</th>
                                <th>Origem</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($holidays ?? [] as $h)
                            @php
                                $d = \Carbon\Carbon::parse($h->date);
                                $isGlobal = is_null($h->tenant_id);
                            @endphp
                            <tr id="holiday-row-{{ $h->id }}">
                                <td class="fw-semibold small">{{ $d->format('d/m/Y') }}</td>
                                <td class="small">
                                    {{ $h->name }}
                                    @if($h->uf)<span class="badge bg-secondary-subtle text-secondary small ms-1">{{ strtoupper($h->uf) }}</span>@endif
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border small text-capitalize">{{ $h->type }}</span>
                                </td>
                                <td>
                                    @if($isGlobal)
                                        <span class="badge" style="background:#6f42c1; color:#ffffff;"><i class="bi bi-globe me-1"></i>Global</span>
                                    @else
                                        <span class="badge" style="background:#0dcaf0; color:#000000;"><i class="bi bi-person me-1"></i>Local</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if(!$isGlobal)
                                        <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="deleteHoliday({{ $h->id }})" title="Excluir">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    @else
                                        <span class="text-muted small" title="Feriado global (somente leitura)"><i class="bi bi-lock-fill"></i></span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Nenhum feriado cadastrado.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#modal-add-holiday">
                    <i class="bi bi-plus-lg me-1"></i> Novo Feriado
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/locales/pt-br.global.min.js"></script>
<script>
const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
let calendar;

const invoiceModal = new bootstrap.Modal(document.getElementById('modal-edit-invoice'));
const addHolidayModal = new bootstrap.Modal(document.getElementById('modal-add-holiday'));

document.addEventListener('DOMContentLoaded', () => {
    const calendarEl = document.getElementById('calendar');

    calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'pt-br',
        timeZone: 'America/Sao_Paulo',
        height: 720,
        weekNumbers: true,
        navLinks: true,
        dayMaxEvents: true,
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,listWeek',
        },
        buttonText: {
            today: 'Hoje',
            month: 'Mês',
            week: 'Semana',
            list: 'Lista',
            prev: '\u2039',
            next: '\u203A',
        },
        events: async function(fetchInfo, success, failure) {
            try {
                const url = new URL('{{ route("tenant.calendar.events") }}', window.location.origin);
                url.searchParams.set('start', fetchInfo.startStr);
                url.searchParams.set('end', fetchInfo.endStr);
                const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const events = await response.json();
                success(events);
            } catch (error) {
                failure(error);
            }
        },
        editable: true,
        eventDrop: async function(info) {
            // Feriados não podem ser arrastados
            if (info.event.extendedProps && info.event.extendedProps.kind === 'holiday') {
                info.revert();
                return;
            }

            const rawId = info.event.id;
            const start = info.event.start.toISOString().slice(0, 10);
            try {
                const r = await fetch('{{ route("tenant.calendar.move") }}', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: rawId, start: start })
                });
                const d = await r.json();
                if (!r.ok || !d.ok) {
                    if (window.toast) window.toast.error(d.message || 'Não foi possível mover a fatura.');
                    info.revert();
                } else {
                    if (window.toast) window.toast.success('Vencimento atualizado com sucesso.');
                }
            } catch (e) {
                if (window.toast) window.toast.error('Erro ao mover fatura.');
                info.revert();
            }
        },
        eventClick: async function(info) {
            info.jsEvent.preventDefault();
            const props = info.event.extendedProps || {};

            // Clique em Feriado
            if (props.kind === 'holiday') {
                const isGlobal = props.is_global;
                const holidayId = props.holiday_id;
                
                let html = `<p class="mb-2 text-start"><strong>Data:</strong> ${info.event.startStr}</p>`;
                html += `<p class="mb-2 text-start"><strong>Tipo:</strong> ${props.type || 'Nacional'}</p>`;
                html += `<p class="mb-0 text-start"><strong>Origem:</strong> ${isGlobal ? 'Global (compartilhado do sistema)' : 'Próprio (criado por você)'}</p>`;

                if (!isGlobal && holidayId) {
                    const result = await Swal.fire({
                        icon: 'info',
                        title: info.event.title,
                        html: html,
                        showCancelButton: true,
                        confirmButtonText: 'Fechar',
                        cancelButtonText: '<i class="bi bi-trash me-1"></i> Excluir Feriado',
                        cancelButtonColor: '#dc3545',
                        confirmButtonColor: '#6c757d',
                    });

                    if (result.dismiss === Swal.DismissReason.cancel) {
                        deleteHoliday(holidayId);
                    }
                } else {
                    Swal.fire({
                        icon: 'info',
                        title: info.event.title,
                        html: html,
                        confirmButtonText: 'Entendido',
                        confirmButtonColor: '#6f42c1',
                    });
                }
                return;
            }

            // Clique em Fatura
            const rawId = info.event.id;
            const invoiceId = rawId.startsWith('inv-') ? rawId.substring(4) : rawId;
            try {
                const r = await fetch('{{ url("/painel/faturas") }}/' + invoiceId, {
                    headers: { 'Accept': 'application/json' }
                });
                const d = await r.json();
                if (!r.ok) throw new Error(d.message || 'Erro');

                document.getElementById('modal-inv-id').textContent = d.number || '#' + d.id;
                document.getElementById('modal-inv-customer').textContent = d.customer_name;
                document.getElementById('modal-inv-desc').textContent = d.description || 'Fatura #' + d.id;
                document.getElementById('modal-inv-amount').textContent = 'R$ ' + d.amount.toLocaleString('pt-BR', {minimumFractionDigits: 2});
                document.getElementById('modal-inv-status').value = d.status;
                document.getElementById('modal-inv-due').value = d.due_date;
                document.getElementById('modal-inv-paid').textContent = d.paid_at || '-';

                const payBtn = document.getElementById('btn-open-payment');
                if (d.payment_url && d.status !== 'paid' && d.status !== 'cancelled') {
                    payBtn.href = d.payment_url;
                    payBtn.classList.remove('d-none');
                } else {
                    payBtn.classList.add('d-none');
                }

                document.getElementById('frm-edit-inv').dataset.id = d.id;
                invoiceModal.show();
            } catch (e) {
                if (window.toast) window.toast.error('Erro ao carregar fatura.');
            }
        },
        eventDidMount: function(info) {
            info.el.title = info.event.title;
            const props = info.event.extendedProps || {};
            if (props.kind === 'holiday') {
                const isGlobal = props.is_global;
                const bg = isGlobal ? '#6f42c1' : '#0dcaf0';
                const text = isGlobal ? '#ffffff' : '#000000';
                info.el.style.setProperty('background-color', bg, 'important');
                info.el.style.setProperty('border-color', bg, 'important');
                info.el.style.setProperty('color', text, 'important');
            }
        },
    });

    calendar.render();
});

// Salvar Fatura
document.getElementById('btn-save-invoice').addEventListener('click', async () => {
    const id = document.getElementById('frm-edit-inv').dataset.id;
    const fd = new FormData(document.getElementById('frm-edit-inv'));
    const r = await fetch('{{ url("/painel/faturas") }}/' + id, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        body: fd
    });
    const d = await r.json();
    if (r.ok && d.ok) {
        if (window.toast) window.toast.success('Fatura atualizada.');
        invoiceModal.hide();
        calendar.refetchEvents();
    } else {
        if (window.toast) window.toast.error(d.error || d.message || 'Erro ao atualizar fatura.');
    }
});

// Salvar Novo Feriado
document.getElementById('frm-new-holiday')?.addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData(e.target);
    ['block_billing','block_dispatch','block_suspension'].forEach(k => { if (!fd.has(k)) fd.set(k, '0'); });
    try {
        const r = await fetch('{{ route("tenant.holidays.store") }}', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: fd
        });
        const d = await r.json();
        if (r.ok && d.ok) {
            addHolidayModal.hide();
            e.target.reset();
            if (window.toast) window.toast.success('Feriado adicionado com sucesso!');
            calendar.refetchEvents();
            setTimeout(() => location.reload(), 1000);
        } else {
            Swal.fire('Aviso', d.error || 'Erro ao salvar feriado.', 'warning');
        }
    } catch (err) {
        Swal.fire('Erro', 'Erro ao processar solicitação.', 'error');
    }
});

// Excluir Feriado Próprio
async function deleteHoliday(id) {
    const ok = await Swal.fire({
        icon: 'warning',
        title: 'Excluir feriado local?',
        text: 'Tem certeza que deseja excluir este feriado municipal/local?',
        showCancelButton: true,
        confirmButtonText: 'Excluir',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545'
    });
    if (!ok.isConfirmed) return;

    try {
        const r = await fetch(`{{ url('/painel/feriados') }}/${id}`, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
        });
        const d = await r.json();
        if (r.ok && d.ok) {
            document.getElementById(`holiday-row-${id}`)?.remove();
            if (window.toast) window.toast.success('Feriado excluído com sucesso!');
            calendar.refetchEvents();
        } else {
            Swal.fire('Aviso', d.error || 'Erro ao excluir feriado.', 'warning');
        }
    } catch (err) {
        Swal.fire('Erro', 'Erro de conexão.', 'error');
    }
}
</script>
@endpush
