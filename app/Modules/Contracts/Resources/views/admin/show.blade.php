@extends(request()->is('painel*') ? 'tenancy::tenant.layout' : 'admin.layouts.app')
@section('title', 'Contrato ' . $contract->number)

@section('content')
@php
    $contractRoutePrefix = $contractRoutePrefix ?? (request()->is('painel*') ? 'tenant.contracts.' : 'admin.contracts.');
    $contractBaseUrl = $contractBaseUrl ?? url(request()->is('painel*') ? '/painel/contratos' : '/admin/contracts');
    $statusConfig = [
        'draft' => ['secondary', 'Rascunho'],
        'sent' => ['primary', 'Enviado'],
        'viewed' => ['info', 'Visualizado'],
        'signed' => ['success', 'Assinado'],
        'rejected' => ['danger', 'Recusado'],
        'expired' => ['dark', 'Expirado'],
    ];
    [$statusColor, $statusLabel] = $statusConfig[$contract->status] ?? ['secondary', 'Indefinido'];
@endphp
<div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route($contractRoutePrefix . 'index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                    <h3 class="mb-0 fw-bold">{{ $contract->title ?: ($contract->template?->name ?: 'Contrato de Prestação de Serviços') }}</h3>
                    <span class="badge text-bg-{{ $statusColor }}">{{ $statusLabel }}</span>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap text-muted small">
                    <code>{{ $contract->number }}</code>
                    @if($contract->template?->name)
                        <span class="badge bg-primary-subtle text-primary"><i class="bi bi-tag me-1"></i>Tipo: {{ $contract->template->name }}</span>
                    @endif
                    @if($contract->template?->category)
                        <span class="badge bg-secondary-subtle text-secondary-emphasis"><i class="bi bi-folder2 me-1"></i>{{ $contract->template->category }}</span>
                    @endif
                    @if($contract->invoice)
                        <span class="badge bg-info-subtle text-info-emphasis"><i class="bi bi-receipt me-1"></i>Fatura #{{ $contract->invoice->number }}</span>
                    @endif
                </div>
                @if($contract->template?->description)
                    <div class="text-muted small mt-1"><i class="bi bi-info-circle me-1"></i><strong>Motivo / Objeto:</strong> {{ $contract->template->description }}</div>
                @endif
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @if(in_array($contract->status, ['draft', 'sent', 'viewed'], true))
                <a href="{{ route($contractRoutePrefix . 'edit', $contract->id) }}" class="btn btn-outline-warning btn-sm" title="Editar dados, cláusulas e layout do contrato antes da assinatura">
                    <i class="bi bi-pencil me-1"></i> Editar contrato
                </a>
                <button class="btn btn-success btn-sm" id="btn-send">
                    <i class="bi bi-send me-1"></i> Enviar para assinatura
                </button>
            @endif
            @if($contract->status === 'signed')
                <a href="{{ route('public.contracts.download', $contract->access_token) }}" target="_blank" class="btn btn-outline-success btn-sm">
                    <i class="bi bi-download me-1"></i> Baixar cópia
                </a>
            @endif
            <button class="btn btn-outline-secondary btn-sm" id="btn-copy-link">
                <i class="bi bi-link-45deg me-1"></i> Copiar link
            </button>
            <a href="{{ $contract->public_url }}" target="_blank" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-box-arrow-up-right me-1"></i> Página pública
            </a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <strong><i class="bi bi-file-earmark-richtext me-1"></i> Documento</strong>
                    <small class="text-muted">Timbrado: {{ $contract->background_path ? ($contract->background_type === 'pdf' ? 'PDF' : 'Imagem') : 'não configurado' }}</small>
                </div>
                <div class="card-body">
                    <div class="contract-admin-preview">
                        {!! app(\App\Modules\Contracts\Services\ContractService::class)->renderDocumentHtml($contract, $contract->status === 'signed') !!}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header"><strong><i class="bi bi-person-badge me-1"></i> Cliente</strong></div>
                <div class="card-body small">
                    <div class="fw-semibold text-body">{{ $contract->customer?->name }}</div>
                    <div>{{ $contract->customer?->email ?: 'Sem email' }}</div>
                    <div>{{ $contract->customer?->whatsapp ?: 'Sem WhatsApp' }}</div>
                    @if($contract->customer?->document)
                        <div>{{ $contract->customer->document }}</div>
                    @endif
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header"><strong><i class="bi bi-calendar3 me-1"></i> Datas e envio</strong></div>
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Criado</span><span>{{ $contract->created_at->format('d/m/Y H:i') }}</span></li>
                    <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Enviado</span><span>{{ $contract->sent_at?->format('d/m/Y H:i') ?: '—' }}</span></li>
                    <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Visualizado</span><span>{{ $contract->viewed_at?->format('d/m/Y H:i') ?: '—' }}</span></li>
                    <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Assinado</span><span>{{ $contract->signed_at?->format('d/m/Y H:i') ?: '—' }}</span></li>
                    <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Expira</span><span>{{ $contract->expires_at?->format('d/m/Y') ?: 'Sem limite' }}</span></li>
                    <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Canal de retorno</span><span>{{ $contract->return_channel ?: 'Ainda não definido' }}</span></li>
                </ul>
            </div>

            @if($contract->status === 'signed')
                <div class="card shadow-sm mb-4">
                    <div class="card-header"><strong><i class="bi bi-pen me-1"></i> Assinatura registrada</strong></div>
                    <div class="card-body small">
                        @if($contract->signature_image)
                            <div class="text-center mb-3">
                                <img src="{{ $contract->signature_image }}" alt="Assinatura" style="max-width:100%;max-height:120px;object-fit:contain;">
                            </div>
                        @endif
                        <div><strong>Nome:</strong> {{ $contract->signer_name }}</div>
                        <div><strong>Documento:</strong> {{ $contract->signer_document ?: 'Não informado' }}</div>
                        <div><strong>Email:</strong> {{ $contract->signer_email ?: 'Não informado' }}</div>
                        <div><strong>IP:</strong> {{ $contract->signer_ip ?: 'Não informado' }}</div>
                        <div><strong>Arquivo final:</strong> {{ $contract->signed_pdf_path ? 'PDF real gerado' : 'Fallback HTML assinado' }}</div>
                    </div>
                </div>
            @endif

            <div class="card shadow-sm">
                <div class="card-header"><strong><i class="bi bi-clock-history me-1"></i> Histórico</strong></div>
                <div class="card-body p-0">
                    @forelse($contract->events->sortByDesc(fn ($event) => $event->created_at?->timestamp ?? 0) as $event)
                        <div class="px-3 py-3 border-bottom small">
                            <div class="fw-semibold text-capitalize">{{ str_replace('_', ' ', $event->event) }}</div>
                            <div class="text-muted">{{ $event->created_at?->format('d/m/Y H:i:s') ?? 'Data nao registrada' }}</div>
                            @if($event->metadata)
                                <div class="text-muted mt-1">{{ json_encode($event->metadata, JSON_UNESCAPED_UNICODE) }}</div>
                            @endif
                        </div>
                    @empty
                        <div class="px-3 py-4 text-center text-muted small">Nenhum evento registrado até o momento.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.contract-admin-preview {
    overflow-x: auto;
    border-radius: 16px;
    background: var(--bs-tertiary-bg);
    padding: 20px;
}
.contract-admin-preview .cs-doc-wrap {
    /* garante que o wrapper não quebre a largura da card */
    max-width: 100%;
}
.contract-admin-preview .cs-page {
    /* escala proporcional quando a card é mais estreita que 794px */
    max-width: 100%;
}
@media (max-width: 900px) {
    .contract-admin-preview {
        padding: 10px;
        overflow-x: hidden;
    }
}
</style>
@endpush

@push('scripts')
<script>
(function () {
    const csrf = document.querySelector('meta[name=csrf-token]').content;
    const contractSendUrl = @json($contractBaseUrl . '/' . $contract->id . '/enviar');

    document.getElementById('btn-copy-link')?.addEventListener('click', () => {
        navigator.clipboard.writeText(@json($contract->public_url))
            .then(() => window.toast.success('Link copiado.'))
            .catch(() => window.toast.error('Não foi possível copiar o link.'));
    });

    document.getElementById('btn-send')?.addEventListener('click', async () => {
        const choice = await Swal.fire({
            title: 'Enviar para assinatura',
            input: 'select',
            inputOptions: {
                both: 'WhatsApp + Email',
                whatsapp: 'Apenas WhatsApp',
                email: 'Apenas Email'
            },
            inputValue: 'both',
            showCancelButton: true,
            confirmButtonText: 'Enviar',
            cancelButtonText: 'Cancelar',
        });

        if (!choice.isConfirmed) return;

        const formData = new FormData();
        formData.set('channel', choice.value);

        const response = await fetch(contractSendUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: formData,
        });
        const data = await response.json();

        if (response.ok && data.ok) {
            await Swal.fire({
                icon: 'success',
                title: 'Envio concluído',
                html: `Email: ${data.report?.email ? 'OK' : 'falhou'}<br>WhatsApp: ${data.report?.whatsapp ? 'OK' : 'falhou'}`,
            });
            location.reload();
            return;
        }

        window.toast.error(data.message || data.error || 'Falha ao enviar.');
    });
})();
</script>
@endpush
