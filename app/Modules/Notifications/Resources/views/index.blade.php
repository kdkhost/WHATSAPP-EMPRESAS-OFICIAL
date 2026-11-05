@extends('admin.layouts.app')
@section('title', 'Central de Notificacoes')
@section('content')
<div class="container-fluid">
    <h2 class="mb-3"><i class="bi bi-megaphone"></i> Central de Notificacoes</h2>

    <div class="row g-3 mb-4">
        @php $cards = [
            ['Enviadas',  $stats['total_sent'],    'bg-success', 'bi-send-check'],
            ['Pendentes', $stats['pending'],       'bg-warning', 'bi-hourglass-split'],
            ['Falhas',    $stats['failed'],        'bg-danger',  'bi-x-circle'],
            ['Nao lidas', $stats['in_app_unread'], 'bg-info',    'bi-bell'],
        ]; @endphp
        @foreach($cards as [$label,$value,$color,$icon])
        <div class="col-md-3">
            <div class="card"><div class="card-body p-3 d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small text-uppercase">{{ $label }}</div>
                    <div class="fs-3 fw-bold">{{ number_format($value, 0, ',', '.') }}</div>
                </div>
                <div class="stat-icon text-white {{ $color }}"><i class="bi {{ $icon }}"></i></div>
            </div></div>
        </div>
        @endforeach
    </div>

    <div class="row g-3">
        {{-- Envio manual --}}
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><strong><i class="bi bi-send"></i> Enviar notificacao manual</strong></div>
                <div class="card-body">
                    <form id="frm-send-notif">
                        <div class="mb-3">
                            <label class="form-label">Canais</label>
                            <div class="d-flex gap-3">
                                @foreach (['in_app'=>'In-App','push'=>'Push','email'=>'E-mail','whatsapp'=>'WhatsApp'] as $k=>$v)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="channels[]" value="{{ $k }}" id="ch-{{ $k }}" @checked($k==='in_app')>
                                    <label class="form-check-label" for="ch-{{ $k }}">{{ $v }}</label>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Titulo</label>
                            <input type="text" name="title" class="form-control" required maxlength="255">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mensagem</label>
                            <textarea name="message" class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Destinatarios</label>
                            <select name="recipients" class="form-select">
                                <option value="all_admins">Todos os admins</option>
                                <option value="role:superadmin">Apenas superadmins</option>
                                <option value="role:admin">Apenas admins</option>
                            </select>
                        </div>
                        <button class="btn btn-primary"><i class="bi bi-send"></i> Enviar</button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Configuracao de canais --}}
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><strong><i class="bi bi-sliders"></i> Canais ativos</strong></div>
                <div class="card-body" id="channels-config">
                    <div class="text-center py-3"><div class="spinner-border text-primary"></div></div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header d-flex justify-content-between">
                    <strong>Historico recente</strong>
                    <a href="{{ route('admin.notifications-module.history') }}" class="btn btn-sm btn-outline-primary">Ver tudo</a>
                </div>
                <div class="card-body p-0">
                    <div class="text-center text-muted p-3 small">Acesse o historico completo para filtrar por canal e status.</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const csrf = document.querySelector('meta[name=csrf-token]').content;
    const headers = { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' };

    // Envio manual
    document.getElementById('frm-send-notif').addEventListener('submit', async e => {
        e.preventDefault();
        const fd = new FormData(e.target);
        const payload = {
            channels: fd.getAll('channels[]'),
            title: fd.get('title'),
            message: fd.get('message'),
            recipients: fd.get('recipients'),
        };
        const r = await fetch('{{ route("admin.notifications-module.send") }}', {
            method: 'POST', credentials: 'same-origin', headers, body: JSON.stringify(payload),
        });
        if (r.ok) { window.toast.success('Notificacao enviada!'); e.target.reset(); }
        else window.toast.error('Falha ao enviar.');
    });

    // Carregar canais
    (async () => {
        const r = await fetch('{{ route("admin.notifications-module.channels") }}', { credentials: 'same-origin' });
        const channels = await r.json();
        let html = '';
        for (const [key, ch] of Object.entries(channels)) {
            html += `<div class="d-flex justify-content-between align-items-center border-bottom py-2">
                <span>${ch.label}</span>
                <span class="badge text-bg-${ch.enabled ? 'success' : 'secondary'}">${ch.enabled ? 'Ativo' : 'Inativo'}</span>
            </div>`;
        }
        document.getElementById('channels-config').innerHTML = html;
    })();
})();
</script>
@endpush
