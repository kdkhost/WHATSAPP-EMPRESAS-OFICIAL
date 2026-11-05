@extends('admin.layouts.app')
@section('title', 'Editor de faturas')
@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="m-0"><i class="bi bi-file-earmark-text"></i> Editor de faturas</h2>
        <a href="{{ route('admin.invoice-editor.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Nova fatura
        </a>
    </div>

    <div class="card">
        <div class="card-header">
            <form class="d-flex gap-2">
                <input type="search" name="q" class="form-control form-control-sm" placeholder="Buscar..." value="{{ request('q') }}">
                <select name="status" class="form-select form-select-sm" style="max-width:150px;">
                    <option value="">Todos</option>
                    @foreach(['pending'=>'Pendente','sent'=>'Enviada','paid'=>'Paga','overdue'=>'Vencida','canceled'=>'Cancelada'] as $k=>$v)
                    <option value="{{ $k }}" @selected(request('status')===$k)>{{ $v }}</option>
                    @endforeach
                </select>
                <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
            </form>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover m-0">
                <thead class="table-light">
                    <tr><th>#</th><th>Cliente</th><th>Valor</th><th>Vencimento</th><th>Status</th><th>Gateway</th><th class="text-end">Acoes</th></tr>
                </thead>
                <tbody>
                    @forelse($invoices as $i)
                    <tr>
                        <td><a href="{{ route('admin.invoice-editor.edit', $i->id) }}"><strong>{{ $i->number }}</strong></a></td>
                        <td>{{ $i->customer?->name ?? '-' }}</td>
                        <td>{{ \App\Support\Format::money($i->amount) }}</td>
                        <td>{{ \App\Support\Format::date($i->due_date) }}</td>
                        <td>
                            @php $colors = ['pending'=>'warning','sent'=>'primary','paid'=>'success','overdue'=>'danger','canceled'=>'secondary']; @endphp
                            <span class="badge text-bg-{{ $colors[$i->status] ?? 'secondary' }}">{{ $i->status }}</span>
                        </td>
                        <td><small>{{ $i->gateway ?: 'padrao' }}</small></td>
                        <td class="text-end">
                            <a href="{{ route('admin.invoice-editor.edit', $i->id) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                            <button class="btn btn-sm btn-outline-success" data-send="{{ $i->id }}"><i class="bi bi-whatsapp"></i></button>
                            <button class="btn btn-sm btn-outline-danger" data-delete="{{ $i->id }}"><i class="bi bi-trash"></i></button>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center text-muted p-5">Nenhuma fatura. <a href="{{ route('admin.invoice-editor.create') }}">Criar a primeira</a></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $invoices->links() }}</div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const csrf = document.querySelector('meta[name=csrf-token]').content;
const headers = { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' };

document.querySelectorAll('[data-send]').forEach(btn => btn.onclick = async () => {
    const confirmed = await window.confirm2(
        'Enviar cobranca?',
        'A mensagem sera enviada agora pelo WhatsApp para este cliente.',
        {
            icon: 'question',
            confirmText: 'Enviar agora',
            cancelText: 'Cancelar',
            confirmColor: '#198754',
        }
    );
    if (!confirmed) return;
    const r = await fetch(`{{ url('/admin/invoice-editor') }}/${btn.dataset.send}/send`, { method:'POST', credentials:'same-origin', headers });
    const d = await r.json();
    d.ok ? window.toast.success('Enviado!') : window.toast.error(d.error || 'Falha.');
});

document.querySelectorAll('[data-delete]').forEach(btn => btn.onclick = async () => {
    const confirmed = await window.confirm2(
        'Excluir fatura?',
        'Esta acao remove a fatura e seus envios vinculados.',
        {
            icon: 'warning',
            confirmText: 'Sim, excluir',
            cancelText: 'Cancelar',
            confirmColor: '#dc3545',
        }
    );
    if (!confirmed) return;
    const r = await fetch(`{{ url('/admin/invoice-editor') }}/${btn.dataset.delete}`, { method:'DELETE', credentials:'same-origin', headers });
    if (r.ok) { btn.closest('tr').remove(); window.toast.success('Excluida.'); }
});
</script>
@endpush
