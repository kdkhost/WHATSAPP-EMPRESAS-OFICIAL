@extends('admin.layouts.app')

@section('title', $invoice->exists ? 'Editar fatura '.$invoice->number : 'Nova fatura')

@push('head')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css">
@endpush

@section('content')
<div class="container-fluid">
    <div class="invoice-editor-hero d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <span class="invoice-editor-kicker">Cobranca inteligente</span>
            <h2 class="m-0">
                <i class="bi bi-file-earmark-text me-2 text-primary"></i>
                {{ $invoice->exists ? 'Editar fatura '.$invoice->number : 'Nova fatura' }}
            </h2>
            <p class="text-muted mb-0 small">Monte a cobranca com descricao rica, gateway, envio por WhatsApp e preview operacional.</p>
        </div>
        @if($invoice->exists)
        <div class="d-flex gap-2">
            <button class="btn btn-outline-success" id="btn-send-whatsapp" title="Enviar via WhatsApp">
                <i class="bi bi-whatsapp"></i> Enviar
            </button>
            <button class="btn btn-outline-primary" id="btn-gen-link" title="Gerar link de pagamento">
                <i class="bi bi-link-45deg"></i> Link pagamento
            </button>
            <button class="btn btn-outline-secondary" id="btn-duplicate" title="Duplicar fatura">
                <i class="bi bi-copy"></i> Duplicar
            </button>
        </div>
        @endif
    </div>

    <form id="frm-invoice" class="row g-3" data-id="{{ $invoice->id }}">
        @csrf
        @if($invoice->exists) @method('PUT') @endif

        {{-- Coluna principal --}}
        <div class="col-lg-8">
            <div class="card invoice-editor-card">
                <div class="card-body">
                    {{-- Busca de cliente --}}
                    <div class="mb-3">
                        <label class="form-label">Cliente <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" id="customer-search" class="form-control"
                                   placeholder="Buscar por nome, email, CPF ou WhatsApp..."
                                   value="{{ $invoice->customer?->name }}" autocomplete="off">
                            <input type="hidden" name="customer_id" id="customer_id" value="{{ $invoice->customer_id }}">
                            <button type="button" class="btn btn-outline-secondary" id="btn-clear-customer">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                        <div id="customer-results" class="list-group position-absolute shadow invoice-customer-results"></div>
                        <div id="customer-info" class="small text-muted mt-1">
                            @if($invoice->customer)
                                WhatsApp: {{ \App\Support\Format::phone($invoice->customer->whatsapp) }} |
                                Doc: {{ \App\Support\Format::cpf($invoice->customer->document) ?: \App\Support\Format::cnpj($invoice->customer->document) }}
                            @endif
                        </div>
                    </div>

                    {{-- Descricao (Summernote) --}}
                    <div class="mb-3">
                        <label class="form-label">Descricao da cobranca <span class="text-danger">*</span></label>
                        <textarea name="description" id="editor-description">{{ old('description', $invoice->description) }}</textarea>
                    </div>

                    {{-- Valores --}}
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Valor <span class="text-danger">*</span></label>
                            <input type="text" name="amount" class="form-control" data-mask="money"
                                   value="{{ $invoice->amount ? \App\Support\Format::money($invoice->amount) : '' }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Vencimento <span class="text-danger">*</span></label>
                            <input type="date" name="due_date" class="form-control"
                                   value="{{ $invoice->due_date?->format('Y-m-d') ?? now()->addDays(7)->format('Y-m-d') }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Gateway</label>
                            <select name="gateway" class="form-select">
                                <option value="">Padrao ({{ config('gateways.default') }})</option>
                                @foreach($gateways as $k => $v)
                                <option value="{{ $k }}" @selected($invoice->gateway === $k)>{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between">
                    <a href="{{ route('admin.invoice-editor.index') }}" class="btn btn-link">Voltar</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2-circle"></i> {{ $invoice->exists ? 'Atualizar' : 'Criar fatura' }}
                    </button>
                </div>
            </div>
        </div>

        {{-- Coluna lateral --}}
        <div class="col-lg-4">
            {{-- Status --}}
            <div class="card invoice-editor-card mb-3">
                <div class="card-header"><strong>Status</strong></div>
                <div class="card-body">
                    <select name="status" class="form-select" id="status-select">
                        @foreach (['pending'=>'Pendente','sent'=>'Enviada','paid'=>'Paga','overdue'=>'Vencida','canceled'=>'Cancelada'] as $k=>$v)
                        <option value="{{ $k }}" @selected(($invoice->status ?? 'pending') === $k)>{{ $v }}</option>
                        @endforeach
                    </select>

                    @if(isset($invoice->meta['installment']) && ($invoice->meta['total_installments'] ?? 1) > 1)
                    <div class="mt-3" id="cascade-cancel-container" style="display: none;">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="cascade_cancel" id="cascade_cancel" value="1" checked>
                            <label class="form-check-label text-danger small fw-bold" for="cascade_cancel">
                                Cancelar também todas as outras parcelas associadas
                            </label>
                        </div>
                    </div>
                    @endif

                    @if($invoice->payment_url)
                    <div class="mt-3">
                        <label class="form-label small">Link de pagamento</label>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" value="{{ $invoice->payment_url }}" readonly id="payment-url-field">
                            <button type="button" class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('payment-url-field').value); window.toast.success('Copiado!')">
                                <i class="bi bi-clipboard"></i>
                            </button>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Opcoes de Parcelamento --}}
            @if(isset($invoice->meta['installment']) && ($invoice->meta['total_installments'] ?? 1) > 1)
            <div class="card invoice-editor-card mb-3">
                <div class="card-header"><strong>Parcelamento</strong></div>
                <div class="card-body">
                    <p class="small text-muted mb-3">Esta fatura e a parcela <strong>{{ $invoice->meta['installment'] }}</strong> de <strong>{{ $invoice->meta['total_installments'] }}</strong>.</p>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-sm btn-outline-success" onclick="anticipateLastParcel({{ $invoice->id }})">
                            <i class="bi bi-cash-stack"></i> Antecipar Última Parcela
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="anticipateThisParcel()">
                            <i class="bi bi-check2-circle"></i> Antecipar Esta Parcela
                        </button>
                    </div>
                </div>
            </div>
            @endif

            {{-- Envios agendados --}}
            @if($invoice->exists && $invoice->dispatches->count())
            <div class="card invoice-editor-card mb-3">
                <div class="card-header"><strong>Envios via WhatsApp</strong></div>
                <ul class="list-group list-group-flush">
                    @foreach($invoice->dispatches as $d)
                    <li class="list-group-item d-flex justify-content-between small">
                        <span>{{ $d->schedule_offset }} - {{ \App\Support\Format::datetime($d->scheduled_at) }}</span>
                        @php $sc = ['sent'=>'success','failed'=>'danger','scheduled'=>'secondary','sending'=>'info']; @endphp
                        <span class="badge text-bg-{{ $sc[$d->status] ?? 'secondary' }}">{{ $d->status }}</span>
                    </li>
                    @endforeach
                </ul>
            </div>
            @endif

            {{-- Preview WhatsApp --}}
            <div class="card invoice-editor-card invoice-preview-card">
                <div class="card-header"><strong><i class="bi bi-eye"></i> Preview</strong></div>
                <div class="card-body invoice-preview-body">
                    <div id="preview-bubble" class="invoice-preview-bubble">
                        <em class="text-muted">Salve a fatura para ver o preview da cobranca</em>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('styles')
<style>
.invoice-editor-hero {
    border: 1px solid var(--app-border-soft, var(--bs-border-color));
    border-radius: var(--app-radius-premium, 18px);
    padding: 1.15rem 1.2rem;
    background:
        linear-gradient(135deg, color-mix(in srgb, var(--bs-primary) 10%, transparent), transparent 42%),
        linear-gradient(180deg, var(--bs-body-bg), color-mix(in srgb, var(--bs-body-bg) 88%, var(--bs-primary) 12%));
    box-shadow: var(--app-shadow-premium, 0 14px 35px rgba(15,23,42,.05));
}
.invoice-editor-kicker {
    display: inline-flex;
    align-items: center;
    margin-bottom: .35rem;
    color: var(--bs-primary);
    font-size: .74rem;
    font-weight: 800;
    letter-spacing: .08em;
    text-transform: uppercase;
}
.invoice-editor-card {
    border-color: var(--app-border-soft, var(--bs-border-color));
    background: var(--bs-body-bg);
    box-shadow: var(--app-shadow-premium, 0 14px 35px rgba(15,23,42,.05));
}
.invoice-customer-results {
    z-index: 1050;
    display: none;
    max-height: 220px;
    overflow-y: auto;
    min-width: min(620px, calc(100vw - 2rem));
    background: var(--bs-body-bg);
    border-color: var(--bs-border-color);
}
.invoice-preview-body {
    min-height: 112px;
    background:
        linear-gradient(135deg, color-mix(in srgb, var(--bs-success) 8%, transparent), transparent 45%),
        color-mix(in srgb, var(--bs-body-bg) 88%, var(--bs-secondary) 12%);
}
.invoice-preview-bubble {
    border-radius: 14px;
    padding: 12px 14px;
    font-size: .84rem;
    line-height: 1.55;
    color: var(--bs-body-color);
    background: color-mix(in srgb, var(--bs-success) 16%, var(--bs-body-bg) 84%);
    border: 1px solid color-mix(in srgb, var(--bs-success) 24%, var(--bs-border-color) 76%);
}
.note-editor.note-frame {
    border-color: var(--bs-border-color) !important;
    border-radius: 14px !important;
    overflow: hidden;
    background: var(--bs-body-bg) !important;
}
.note-editor .note-toolbar,
.note-editor .note-statusbar {
    background: var(--bs-tertiary-bg) !important;
    border-color: var(--bs-border-color) !important;
}
.note-editor .note-btn {
    color: var(--bs-body-color) !important;
    background: var(--bs-body-bg) !important;
    border-color: var(--bs-border-color) !important;
}
.note-editor .note-editing-area .note-editable {
    min-height: 260px;
    color: #111827 !important;
    background: #ffffff !important;
}
[data-bs-theme="dark"] .invoice-preview-body {
    background:
        linear-gradient(135deg, rgba(25,135,84,.14), transparent 48%),
        #151a20;
}
[data-bs-theme="dark"] .invoice-preview-bubble {
    color: #e5e7eb;
    background: rgba(25,135,84,.18);
    border-color: rgba(25,135,84,.28);
}
[data-bs-theme="dark"] .note-editor .note-btn {
    background: #20262d !important;
    border-color: rgba(255,255,255,.14) !important;
    color: #e5e7eb !important;
}
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>
<script>
(function () {
    const csrf = document.querySelector('meta[name=csrf-token]').content;
    const headers = { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' };

    // Summernote para descricao
    $('#editor-description').summernote({
        height: 180,
        placeholder: 'Descreva a cobranca (servico prestado, produto, etc.)...',
        toolbar: [['style',['bold','italic','underline','clear']],['para',['ul','ol']],['view',['codeview']]],
    });

    // Busca de cliente (autocomplete)
    let searchTimer;
    const searchInput = document.getElementById('customer-search');
    const resultsDiv = document.getElementById('customer-results');

    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(async () => {
            const q = searchInput.value.trim();
            if (q.length < 2) { resultsDiv.style.display = 'none'; return; }
            const r = await fetch(`{{ route('admin.invoice-editor.customers.search') }}?q=${encodeURIComponent(q)}`, { credentials: 'same-origin' });
            const customers = await r.json();
            if (!customers.length) { resultsDiv.style.display = 'none'; return; }
            resultsDiv.innerHTML = customers.map(c => `
                <a href="#" class="list-group-item list-group-item-action small" data-id="${c.id}" data-name="${c.name}" data-whatsapp="${c.whatsapp || ''}" data-doc="${c.document || ''}">
                    <strong>${c.name}</strong> <span class="text-muted">- ${c.email || ''} ${c.whatsapp ? '| ' + c.whatsapp : ''}</span>
                </a>
            `).join('');
            resultsDiv.style.display = 'block';

            resultsDiv.querySelectorAll('a').forEach(a => a.onclick = (e) => {
                e.preventDefault();
                document.getElementById('customer_id').value = a.dataset.id;
                searchInput.value = a.dataset.name;
                document.getElementById('customer-info').textContent =
                    `WhatsApp: ${a.dataset.whatsapp || '-'} | Doc: ${a.dataset.doc || '-'}`;
                resultsDiv.style.display = 'none';
            });
        }, 300);
    });

    document.getElementById('btn-clear-customer').onclick = () => {
        document.getElementById('customer_id').value = '';
        searchInput.value = '';
        document.getElementById('customer-info').textContent = '';
    };

    document.addEventListener('click', (e) => {
        if (!resultsDiv.contains(e.target) && e.target !== searchInput) resultsDiv.style.display = 'none';
    });

    // Submit AJAX
    document.getElementById('frm-invoice').addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        const id = form.dataset.id;
        const isEdit = id && id !== '';
        const url = isEdit
            ? `{{ url('/admin/invoice-editor') }}/${id}`
            : `{{ route('admin.invoice-editor.store') }}`;

        // Setup cascade cancel visibility
    const statusSelect = document.getElementById('status-select');
    const cascadeContainer = document.getElementById('cascade-cancel-container');
    if (statusSelect && cascadeContainer) {
        const toggleCascade = () => {
            cascadeContainer.style.display = statusSelect.value === 'canceled' ? 'block' : 'none';
        };
        statusSelect.addEventListener('change', toggleCascade);
        toggleCascade();
    }

    // Parse amount (remove mascara BR sem multiplicar centavos)
        const amountInput = form.querySelector('[name="amount"]');
        amountInput.value = window.adminUI?.parseMoney
            ? window.adminUI.parseMoney(amountInput.value)
            : amountInput.value.replace(/[^\d,.-]/g, '').replace(/\./g, '').replace(',', '.');

        const fd = new FormData(form);
        // Summernote content
        fd.set('description', $('#editor-description').summernote('code'));
        if (isEdit) fd.set('_method', 'PUT');

        try {
            const r = await fetch(url, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: fd,
            });
            let data = {};
            try { data = await r.json(); } catch (error) { data = {}; }

            if (r.ok && data.ok) {
                window.toast.success(isEdit ? 'Fatura atualizada!' : 'Fatura criada!');
                if (data.redirect) window.location = data.redirect;
                return;
            }

            if (r.status === 422 && data.errors) {
                const firstField = Object.keys(data.errors)[0];
                const firstError = Array.isArray(data.errors[firstField]) ? data.errors[firstField][0] : data.errors[firstField];
                window.toast.error(firstError || 'Verifique os campos obrigatorios.');
                return;
            }

            window.toast.error(data.message || data.error || 'Nao foi possivel salvar a fatura.');
        } catch (error) {
            window.toast.error('Erro de conexao ao salvar a fatura.');
        }
    });

    // Enviar WhatsApp
    document.getElementById('btn-send-whatsapp')?.addEventListener('click', async () => {
        const confirmed = await window.confirm2(
            'Enviar cobranca?',
            'A mensagem sera enviada agora pelo WhatsApp para o cliente selecionado.',
            {
                icon: 'question',
                confirmText: 'Enviar agora',
                cancelText: 'Cancelar',
                confirmColor: '#198754',
            }
        );
        if (!confirmed) return;
        const id = document.getElementById('frm-invoice').dataset.id;
        const r = await fetch(`{{ url('/admin/invoice-editor') }}/${id}/send`, {
            method: 'POST', credentials: 'same-origin', headers,
        });
        const d = await r.json();
        d.ok ? window.toast.success('Enviado!') : window.toast.error(d.error || 'Falha no envio.');
    });

    // Gerar link pagamento
    document.getElementById('btn-gen-link')?.addEventListener('click', async () => {
        const id = document.getElementById('frm-invoice').dataset.id;
        const r = await fetch(`{{ url('/admin/invoice-editor') }}/${id}/payment-link`, {
            method: 'POST', credentials: 'same-origin', headers,
        });
        const d = await r.json();
        if (d.ok) {
            window.toast.success('Link gerado: ' + d.payment_url);
            setTimeout(() => location.reload(), 1500);
        } else {
            window.toast.error(d.error || 'Falha ao gerar link.');
        }
    });

    // Duplicar
    document.getElementById('btn-duplicate')?.addEventListener('click', async () => {
        const id = document.getElementById('frm-invoice').dataset.id;
        const r = await fetch(`{{ url('/admin/invoice-editor') }}/${id}/duplicate`, {
            method: 'POST', credentials: 'same-origin', headers,
        });
        const d = await r.json();
        if (d.ok) {
            window.toast.success(`Duplicada: ${d.number}`);
            window.location = `{{ url('/admin/invoice-editor') }}/${d.id}/editar`;
        }
    });
    window.anticipateThisParcel = function() {
        const select = document.getElementById('status-select');
        if (select) {
            select.value = 'paid';
            select.dispatchEvent(new Event('change'));
            window.toast.info('Status alterado para Paga. Clique em Atualizar para confirmar.');
            
            // Optionally blink the save button to guide the user
            const btn = document.querySelector('button[type="submit"]');
            if (btn) {
                btn.classList.add('btn-warning');
                btn.classList.remove('btn-primary');
                setTimeout(() => {
                    btn.classList.remove('btn-warning');
                    btn.classList.add('btn-primary');
                }, 1500);
            }
        }
    };

    window.anticipateLastParcel = async function(id) {
        const result = await Swal.fire({
            title: 'Antecipar última parcela?',
            text: 'Deseja antecipar (dar baixa) na última parcela pendente deste parcelamento?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sim, antecipar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#198754'
        });
        if (!result.isConfirmed) return;
        
        try {
            const r = await fetch(`{{ url('/admin/invoice-editor') }}/${id}/anticipate-last`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
            });
            const d = await r.json();
            if (r.ok && d.ok) {
                window.toast.success(d.message || 'Última parcela antecipada.');
                setTimeout(() => location.reload(), 600);
            } else {
                window.toast.error(d.error || 'Erro ao antecipar parcela.');
            }
        } catch (e) {
            window.toast.error('Erro de rede.');
        }
    };

})();
</script>
@endpush
