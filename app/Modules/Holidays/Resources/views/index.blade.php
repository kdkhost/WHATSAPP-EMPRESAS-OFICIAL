@extends('admin.layouts.app')
@section('title', 'Feriados')
@push('head')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css">
<style>
/* FullCalendar prev/next buttons - usar setas de texto (chevron) consistentes com BS */
.fc .fc-button-primary {
    background-color: var(--bs-secondary-bg);
    border-color: var(--bs-border-color);
    color: var(--bs-body-color);
    font-weight: 500;
}
.fc .fc-button-primary:hover {
    background-color: var(--bs-tertiary-bg);
    border-color: var(--bs-border-color);
    color: var(--bs-body-color);
}
.fc .fc-button-primary:not(:disabled).fc-button-active,
.fc .fc-button-primary:not(:disabled):active {
    background-color: var(--bs-primary);
    border-color: var(--bs-primary);
    color: var(--bs-light);
}
.fc .fc-prev-button, .fc .fc-next-button {
    line-height: 1;
    font-size: 1.4rem;
    padding: 0.25rem 0.6rem;
}
.fc .fc-icon { font-family: inherit; }
</style>
@endpush
@section('content')
@php
    $totals = [
        'total'    => $holidays->total(),
        'national' => $holidays->where('type', 'national')->count(),
        'custom'   => $holidays->where('type', 'custom')->count(),
        'upcoming' => $holidays->filter(fn($h) => \Carbon\Carbon::parse($h->date)->isFuture())->count(),
    ];
@endphp
<div class="container-fluid">

    <x-ui.page-header
        title="Feriados"
        subtitle="Cobranças, envios e suspensões não acontecem em feriados marcados. Ações são adiadas para o próximo dia útil. Páscoa, Carnaval e Corpus Christi são calculados automaticamente."
        eyebrow="Calendário operacional"
        class="mb-4"
    >
        <x-slot:actions>
        <div class="d-flex gap-2 align-items-center justify-content-end">
            <button type="button" class="btn btn-outline-success btn-sm" id="btn-import">
                <i class="bi bi-flag me-1"></i> Importar nacionais
            </button>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalH">
                <i class="bi bi-plus-lg me-1"></i> Novo feriado
            </button>
        </div>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- ========== KPIs ========== --}}
    <div class="row g-3 mb-4">
        @php
            $cards = [
                ['Total cadastrados',   $totals['total'],    'primary', 'bi-calendar-check'],
                ['Nacionais',           $totals['national'], 'success', 'bi-flag'],
                ['Customizados',        $totals['custom'],   'info',    'bi-pencil-square'],
                ['Proximos',            $totals['upcoming'], 'warning', 'bi-calendar-event'],
            ];
        @endphp
        @foreach($cards as [$label, $value, $color, $icon])
        <div class="col-md-6 col-lg-3">
            <x-ui.stats-card :label="$label" :value="$value" :color="$color" :icon="$icon" class="h-100" />
        </div>
        @endforeach
    </div>

    <div class="alert alert-info border-0 shadow-sm mb-4">
        <div class="d-flex align-items-center gap-3">
            <i class="bi bi-info-circle-fill fs-3 text-info"></i>
            <div class="small mb-0">
                <strong>Regras automáticas:</strong> O sistema bloqueia cobranças, envios WhatsApp e suspensões de clientes em sábados, domingos e feriados marcados. As ações são reagendadas para o próximo dia útil automaticamente.
            </div>
        </div>
    </div>

    <div class="row g-3">
        {{-- ========== CALENDARIO ========== --}}
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-header">
                    <strong><i class="bi bi-calendar3 me-1 text-primary"></i> Calendario</strong>
                    <small class="text-muted">visão mensal</small>
                </div>
                <div class="card-body">
                    <div id="calendar"></div>
                </div>
            </div>
        </div>

        {{-- ========== LISTA ========== --}}
        <div class="col-lg-5">
            <div class="card shadow-sm">
                <div class="card-header">
                    <strong><i class="bi bi-list-ul me-1 text-primary"></i> Feriados cadastrados</strong>
                    <span class="badge bg-light text-dark border">{{ $totals['total'] }}</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                        <table class="table table-sm table-hover m-0 align-middle">
                            <thead class="sticky-top">
                                <tr>
                                    <th>Data</th>
                                    <th>Nome</th>
                                    <th>Tipo</th>
                                    <th class="text-center" title="Bloqueios">Bloqueia</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($holidays as $h)
                                @php
                                    $date = \Carbon\Carbon::parse($h->date);
                                    $isPast = $date->isPast() && ! $date->isToday();
                                    $typeMap = [
                                        'national'  => ['label' => 'Nacional',   'color' => 'success'],
                                        'state'     => ['label' => 'Estadual',   'color' => 'info'],
                                        'municipal' => ['label' => 'Municipal',  'color' => 'primary'],
                                        'custom'    => ['label' => 'Customizado','color' => 'secondary'],
                                    ];
                                    $type = $typeMap[$h->type] ?? ['label' => $h->type, 'color' => 'secondary'];
                                @endphp
                                <tr id="h-{{ $h->id }}" class="{{ $isPast ? 'opacity-50' : '' }}">
                                    <td class="small">
                                        <div class="fw-semibold">{{ $date->format('d/m/Y') }}</div>
                                        <small class="text-muted text-capitalize">{{ $date->locale('pt-BR')->isoFormat('ddd') }}</small>
                                    </td>
                                    <td class="small">
                                        <strong>{{ $h->name }}</strong>
                                        @if($h->uf)<small class="text-muted d-block">{{ strtoupper($h->uf) }}</small>@endif
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $type['color'] }}-subtle text-{{ $type['color'] }} border border-{{ $type['color'] }}-subtle small">
                                            {{ $type['label'] }}
                                        </span>
                                    </td>
                                    <td class="text-center small">
                                        @if($h->block_billing)<i class="bi bi-cash-coin text-danger" data-bs-toggle="tooltip" title="Bloqueia cobranças"></i> @endif
                                        @if($h->block_dispatch)<i class="bi bi-send text-warning" data-bs-toggle="tooltip" title="Bloqueia envios WhatsApp"></i> @endif
                                        @if($h->block_suspension)<i class="bi bi-pause-circle text-info" data-bs-toggle="tooltip" title="Bloqueia suspensões"></i>@endif
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-link text-danger p-0" data-del="{{ $h->id }}" title="Excluir">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-5">
                                        <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
                                        Nenhum feriado cadastrado.
                                        <div class="mt-2">
                                            <button class="btn btn-sm btn-outline-success" id="btn-import-empty">
                                                <i class="bi bi-flag me-1"></i> Importar nacionais
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($holidays->hasPages())
                    <div class="card-footer">{{ $holidays->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ========== MODAL: Novo feriado ========== --}}
<div class="modal fade" id="modalH" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="frm-h">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-calendar-plus me-2"></i> Novo feriado</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label">Data <span class="text-danger">*</span></label>
                            <input type="date" name="date" class="form-control" required>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label">Nome <span class="text-danger">*</span></label>
                            <input name="name" class="form-control" maxlength="120" required placeholder="Ex.: Dia da Padroeira">
                        </div>
                        <div class="col-md-7">
                            <label class="form-label">Tipo</label>
                            <select name="type" class="form-select">
                                <option value="national">Nacional</option>
                                <option value="state">Estadual</option>
                                <option value="municipal">Municipal</option>
                                <option value="custom" selected>Customizado</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">UF (opcional)</label>
                            <input name="uf" class="form-control" maxlength="2" placeholder="SP" style="text-transform: uppercase;">
                        </div>
                        <div class="col-12">
                            <label class="form-label small text-uppercase fw-semibold text-muted">Ações bloqueadas</label>
                            <div class="d-flex flex-column gap-2">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="block_billing" value="1" checked id="b1">
                                    <label class="form-check-label" for="b1"><i class="bi bi-cash-coin text-danger me-1"></i> Bloquear cobranças</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="block_dispatch" value="1" checked id="b2">
                                    <label class="form-check-label" for="b2"><i class="bi bi-send text-warning me-1"></i> Bloquear envios WhatsApp</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="block_suspension" value="1" checked id="b3">
                                    <label class="form-check-label" for="b3"><i class="bi bi-pause-circle text-info me-1"></i> Bloquear suspensões</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i> Salvar feriado</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/locales/pt-br.global.min.js"></script>
<script>
const csrf = document.querySelector('meta[name=csrf-token]').content;

// Tooltips
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));

document.addEventListener('DOMContentLoaded', () => {
    new FullCalendar.Calendar(document.getElementById('calendar'), {
        initialView: 'dayGridMonth',
        locale: 'pt-br',
        timeZone: 'America/Sao_Paulo',
        height: 600,
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,listMonth' },
        buttonText: { today: 'Hoje', month: 'Mes', list: 'Lista', prev: '\u2039', next: '\u203A' },
        events: '{{ route("admin.holidays.events") }}',
        eventColor: '#198754',
    }).render();
});

document.getElementById('frm-h').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData(e.target);
    ['block_billing','block_dispatch','block_suspension'].forEach(k => { if (!fd.has(k)) fd.set(k, '0'); });
    try {
        const r = await fetch('{{ route("admin.holidays.store") }}', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: fd
        });
        if (r.ok) { window.toast.success('Feriado adicionado!'); setTimeout(() => location.reload(), 800); }
        else window.toast.error('Erro ao salvar.');
    } catch (err) { window.toast.error('Erro de rede.'); }
});

async function importNacionais() {
    const ok = await Swal.fire({
        icon: 'question', title: 'Importar feriados nacionais?',
        text: 'Adiciona 9 feriados nacionais brasileiros para os próximos 3 anos.',
        showCancelButton: true, confirmButtonText: 'Importar', cancelButtonText: 'Cancelar'
    });
    if (!ok.isConfirmed) return;
    try {
        const r = await fetch('{{ route("admin.holidays.import.brazilian") }}', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
        });
        const d = await r.json();
        if (d.ok) { window.toast.success(`${d.imported} feriados importados!`); setTimeout(() => location.reload(), 1000); }
        else window.toast.error('Erro ao importar.');
    } catch (err) { window.toast.error('Erro de rede.'); }
}

document.getElementById('btn-import').addEventListener('click', importNacionais);
document.getElementById('btn-import-empty')?.addEventListener('click', importNacionais);

document.querySelectorAll('[data-del]').forEach(b => b.onclick = async () => {
    const ok = await Swal.fire({
        icon: 'warning', title: 'Excluir feriado?',
        showCancelButton: true, confirmButtonText: 'Excluir', cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545'
    });
    if (!ok.isConfirmed) return;
    try {
        const r = await fetch(`{{ url('/admin/holidays') }}/${b.dataset.del}`, {
            method: 'DELETE', credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf }
        });
        if (r.ok) { document.getElementById(`h-${b.dataset.del}`).remove(); window.toast.success('Excluído!'); }
        else window.toast.error('Erro.');
    } catch (err) { window.toast.error('Erro de rede.'); }
});
</script>
@endpush
