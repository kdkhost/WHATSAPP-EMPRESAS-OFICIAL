@extends('admin.layouts.app')
@section('title', 'Historico de notificacoes')
@section('content')
<div class="container-fluid">
    <h2 class="mb-3"><i class="bi bi-clock-history"></i> Historico de envios</h2>

    <form class="d-flex gap-2 mb-3">
        <select name="channel" class="form-select form-select-sm" style="max-width:150px;">
            <option value="">Todos canais</option>
            @foreach (['push','email','whatsapp','sms'] as $ch)
            <option value="{{ $ch }}" @selected(request('channel')===$ch)>{{ ucfirst($ch) }}</option>
            @endforeach
        </select>
        <select name="status" class="form-select form-select-sm" style="max-width:150px;">
            <option value="">Todos status</option>
            @foreach (['pending','sent','failed'] as $st)
            <option value="{{ $st }}" @selected(request('status')===$st)>{{ ucfirst($st) }}</option>
            @endforeach
        </select>
        <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
    </form>

    <div class="card"><div class="card-body p-0">
        <table class="table table-sm table-hover m-0">
            <thead class="table-light"><tr>
                <th>ID</th><th>Canal</th><th>Status</th><th>Criado em</th><th>Enviado em</th><th>Erro</th>
            </tr></thead>
            <tbody>
                @forelse($items as $item)
                <tr>
                    <td>{{ $item->id }}</td>
                    <td><span class="badge text-bg-info">{{ $item->channel }}</span></td>
                    <td>
                        @php $sc = ['pending'=>'warning','sent'=>'success','failed'=>'danger','sending'=>'info']; @endphp
                        <span class="badge text-bg-{{ $sc[$item->status] ?? 'secondary' }}">{{ $item->status }}</span>
                    </td>
                    <td class="small">{{ \Carbon\Carbon::parse($item->created_at)->format('d/m H:i:s') }}</td>
                    <td class="small">{{ $item->sent_at ? \Carbon\Carbon::parse($item->sent_at)->format('d/m H:i:s') : '-' }}</td>
                    <td class="small text-danger">{{ \Illuminate\Support\Str::limit($item->error, 80) }}</td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center text-muted p-5">Nenhum envio no historico.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div><div class="card-footer">{{ $items->links() }}</div></div>
</div>
@endsection
