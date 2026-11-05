@php
    $routePrefix = $routePrefix ?? (request()->is('painel/*') || session('admin_assist_mode') ? 'tenant.gestao-financeira.' : 'admin.gestao-financeira.');
    $baseUrl = str_starts_with($routePrefix, 'admin') ? '/admin/gestao-financeira' : '/painel/gestao-financeira';
@endphp
@extends('gestao-financeira::layouts.master')

@section('title', 'Editar lançamento')
@section('page_title', 'Editar lançamento financeiro')

@section('page_actions')
    <a href="{{ $baseUrl }}/transacoes" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar para lançamentos
    </a>
@endsection

@section('addon_content')
    <div class="row justify-content-center">
        <div class="col-xl-9">
            <x-ui.premium-card title="Editar lançamento" subtitle="Atualize os dados financeiros, conta, categoria e comprovante">
                <form action="{{ $baseUrl }}/transacoes/{{ $transaction->id }}/atualizar" method="POST"
                      enctype="multipart/form-data" class="row g-3 finance-addon-form" id="transaction-form-edit">
                    @csrf
                    <div class="col-md-6">
                        <label class="form-label">Tipo de lançamento</label>
                        <div class="d-flex gap-2">
                            <div class="form-check flex-fill border rounded-4 p-3">
                                <input class="form-check-input" type="radio" name="type" id="edit_type_receita" value="receita" @checked($transaction->type === 'receita')>
                                <label class="form-check-label fw-semibold ms-1" for="edit_type_receita">Receita</label>
                            </div>
                            <div class="form-check flex-fill border rounded-4 p-3">
                                <input class="form-check-input" type="radio" name="type" id="edit_type_despesa" value="despesa" @checked($transaction->type === 'despesa')>
                                <label class="form-check-label fw-semibold ms-1" for="edit_type_despesa">Despesa</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="status" class="form-label">Situação</label>
                        <select name="status" id="status" class="form-select" required>
                            <option value="pendente" @selected($transaction->status === 'pendente')>Pendente</option>
                            <option value="pago"     @selected($transaction->status === 'pago')>Pago</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label for="description" class="form-label">Descrição / título</label>
                        <input type="text" name="description" id="description" class="form-control" required value="{{ $transaction->description }}">
                    </div>
                    <div class="col-md-4">
                        <label for="amount" class="form-label">Valor</label>
                        <input type="number" name="amount" id="amount" step="0.01" class="form-control" required
                               value="{{ number_format((float) $transaction->amount, 2, '.', '') }}">
                    </div>
                    <div class="col-md-4">
                        <label for="due_date" class="form-label">Data de vencimento</label>
                        <input type="date" name="due_date" id="due_date" class="form-control" required
                               value="{{ $transaction->due_date->format('Y-m-d') }}">
                    </div>
                    <div class="col-md-4" id="payment-date-wrapper">
                        <label for="payment_date" class="form-label">Data de pagamento</label>
                        <input type="date" name="payment_date" id="payment_date" class="form-control"
                               value="{{ $transaction->payment_date?->format('Y-m-d') }}">
                    </div>
                    <div class="col-md-6">
                        <label for="account_id" class="form-label">Conta / carteira</label>
                        <select name="account_id" id="account_id" class="form-select" required>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}" @selected($transaction->account_id == $account->id)>
                                    {{ $account->name }} (R$ {{ number_format($account->current_balance, 2, ',', '.') }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="category_id" class="form-label">Categoria</label>
                        <select name="category_id" id="category_id" class="form-select" required>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" data-type="{{ $category->type }}" @selected($transaction->category_id == $category->id)>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label for="notes" class="form-label">Observações</label>
                        <textarea name="notes" id="notes" class="form-control" rows="4">{{ $transaction->notes }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Substituir anexo / comprovante</label>
                        <div class="finance-addon-dropzone">
                            <input type="file" name="attachment" id="attachment" accept=".pdf,.jpg,.jpeg,.png">
                            <i class="bi bi-cloud-arrow-up fs-2 d-block mb-2"></i>
                            <div class="fw-semibold">Envie um novo arquivo para substituir o anterior</div>
                            <div class="finance-addon-section-note">PDF, JPG ou PNG com até 4 MB.</div>
                        </div>
                        @if($transaction->attachment_path)
                            <div class="mt-2">
                                <a href="{{ asset('storage/'.$transaction->attachment_path) }}" target="_blank" class="btn btn-sm btn-outline-info">
                                    <i class="bi bi-paperclip me-1"></i> Ver anexo atual
                                </a>
                            </div>
                        @endif
                    </div>
                    <div class="col-12 d-flex justify-content-between align-items-center gap-2 mt-2">
                        <button type="button" class="btn btn-outline-danger" id="btn-delete-tx">
                            <i class="bi bi-trash me-1"></i> Excluir lançamento
                        </button>
                        <div class="d-flex gap-2 ms-auto">
                            <a href="{{ $baseUrl }}/transacoes" class="btn btn-outline-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-1"></i> Salvar alterações
                            </button>
                        </div>
                    </div>
                </form>
            </x-ui.premium-card>
        </div>
    </div>
@endsection

@section('scripts')
<script>
(() => {
    const csrf = document.querySelector('meta[name=csrf-token]')?.content ?? '';
    const base = @json($baseUrl);
    const txId = @json($transaction->id);
    const status = document.getElementById('status');
    const paymentDate = document.getElementById('payment_date');
    const paymentWrapper = document.getElementById('payment-date-wrapper');
    const categorySelect = document.getElementById('category_id');
    const typeRadios = [...document.querySelectorAll('input[name="type"]')];
    const btnDeleteTx = document.getElementById('btn-delete-tx');

    const syncPaymentField = () => {
        const paid = status.value === 'pago';
        paymentWrapper.classList.toggle('d-none', !paid);
        paymentDate.required = paid;
        if (!paid) paymentDate.value = '';
    };

    const syncCategories = () => {
        const selectedType = typeRadios.find(r => r.checked)?.value ?? 'receita';
        [...categorySelect.options].forEach(option => {
            option.hidden = option.dataset.type !== selectedType;
        });
        if (categorySelect.selectedOptions[0]?.hidden) {
            const first = [...categorySelect.options].find(o => !o.hidden);
            if (first) categorySelect.value = first.value;
        }
    };

    if (btnDeleteTx) {
        btnDeleteTx.addEventListener('click', async () => {
            const ok = await Swal.fire({
                icon: 'warning',
                title: 'Excluir lançamento?',
                text: 'Esta ação não poderá ser desfeita e afetará os relatórios e saldos.',
                showCancelButton: true,
                confirmButtonText: '<i class="bi bi-trash me-1"></i> Sim, excluir',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545',
                focusCancel: true
            });

            if (!ok.isConfirmed) return;

            btnDeleteTx.disabled = true;
            btnDeleteTx.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Excluindo...';

            try {
                const r = await fetch(`${base}/transacoes/${txId}/excluir`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'X-HTTP-METHOD-OVERRIDE': 'DELETE',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ _method: 'DELETE' })
                });
                const d = await r.json();
                if (d.ok || r.ok) {
                    await Swal.fire({
                        icon: 'success',
                        title: 'Excluído!',
                        text: d.message || 'Lançamento excluído com sucesso.',
                        timer: 1500,
                        showConfirmButton: false
                    });
                    window.location.href = `${base}/transacoes`;
                } else {
                    Swal.fire({ icon: 'error', title: 'Erro', text: d.message || 'Erro ao excluir.' });
                    btnDeleteTx.disabled = false;
                    btnDeleteTx.innerHTML = '<i class="bi bi-trash me-1"></i> Excluir lançamento';
                }
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Erro de rede', text: 'Não foi possível se comunicar com o servidor.' });
                btnDeleteTx.disabled = false;
                btnDeleteTx.innerHTML = '<i class="bi bi-trash me-1"></i> Excluir lançamento';
            }
        });
    }

    status.addEventListener('change', syncPaymentField);
    typeRadios.forEach(r => r.addEventListener('change', syncCategories));
    syncPaymentField();
    syncCategories();
})();
</script>
@endsection
