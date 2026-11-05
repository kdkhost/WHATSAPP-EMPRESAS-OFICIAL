@php
    $routePrefix = $routePrefix ?? (request()->is('painel/*') || session('admin_assist_mode') ? 'tenant.gestao-financeira.' : 'admin.gestao-financeira.');
    $baseUrl = str_starts_with($routePrefix, 'admin') ? '/admin/gestao-financeira' : '/painel/gestao-financeira';
@endphp
@extends('gestao-financeira::layouts.master')

@section('title', 'Novo lançamento')
@section('page_title', 'Cadastrar lançamento')

@section('page_actions')
    <a href="{{ $baseUrl }}/transacoes" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar para lançamentos
    </a>
@endsection

@section('addon_content')
    <div class="row justify-content-center">
        <div class="col-xl-9">
            <x-ui.premium-card title="Novo lançamento" subtitle="Cadastre receitas e despesas com conta, categoria e comprovante">
                <form action="{{ $baseUrl }}/transacoes/salvar" method="POST" enctype="multipart/form-data" class="row g-3 finance-addon-form" id="transaction-form">
                    @csrf
                    <div class="col-md-6">
                        <label class="form-label">Tipo de lançamento</label>
                        <div class="d-flex gap-2">
                            <div class="form-check flex-fill border rounded-4 p-3">
                                <input class="form-check-input" type="radio" name="type" id="type_receita" value="receita" checked>
                                <label class="form-check-label fw-semibold ms-1" for="type_receita">Receita</label>
                            </div>
                            <div class="form-check flex-fill border rounded-4 p-3">
                                <input class="form-check-input" type="radio" name="type" id="type_despesa" value="despesa">
                                <label class="form-check-label fw-semibold ms-1" for="type_despesa">Despesa</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="status" class="form-label">Situação</label>
                        <select name="status" id="status" class="form-select" required>
                            <option value="pendente">Pendente</option>
                            <option value="pago">Pago</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label for="description" class="form-label">Descrição / título</label>
                        <input type="text" name="description" id="description" class="form-control" required placeholder="Ex.: recebimento de projeto, aluguel, manutenção">
                    </div>
                    <div class="col-md-4">
                        <label for="amount" class="form-label">Valor</label>
                        <input type="number" name="amount" id="amount" step="0.01" class="form-control" required placeholder="0,00">
                    </div>
                    <div class="col-md-4">
                        <label for="due_date" class="form-label">Data de vencimento</label>
                        <input type="date" name="due_date" id="due_date" class="form-control" required value="{{ now()->toDateString() }}">
                    </div>
                    <div class="col-md-4" id="payment-date-wrapper">
                        <label for="payment_date" class="form-label">Data de pagamento</label>
                        <input type="date" name="payment_date" id="payment_date" class="form-control" value="{{ now()->toDateString() }}">
                    </div>
                    <div class="col-md-6">
                        <label for="account_id" class="form-label">Conta / carteira</label>
                        <select name="account_id" id="account_id" class="form-select" required>
                            <option value="">Selecione</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}">{{ $account->name }} (R$ {{ number_format($account->current_balance, 2, ',', '.') }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="category_id" class="form-label">Categoria</label>
                        <select name="category_id" id="category_id" class="form-select" required>
                            <option value="">Selecione</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" data-type="{{ $category->type }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label for="notes" class="form-label">Observações</label>
                        <textarea name="notes" id="notes" class="form-control" rows="4" placeholder="Detalhes adicionais do lançamento"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Anexo / comprovante</label>
                        <div class="finance-addon-dropzone">
                            <input type="file" name="attachment" id="attachment" accept=".pdf,.jpg,.jpeg,.png">
                            <i class="bi bi-cloud-arrow-up fs-2 d-block mb-2"></i>
                            <div class="fw-semibold">Clique ou arraste o arquivo para anexar</div>
                            <div class="finance-addon-section-note">PDF, JPG ou PNG com até 4 MB.</div>
                        </div>
                    </div>
                    <div class="col-12 d-flex justify-content-end gap-2 mt-2">
                        <a href="{{ $baseUrl }}/transacoes" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i> Salvar lançamento
                        </button>
                    </div>
                </form>
            </x-ui.premium-card>
        </div>
    </div>
@endsection

@section('scripts')
<script>
(() => {
    const status = document.getElementById('status');
    const paymentDate = document.getElementById('payment_date');
    const paymentWrapper = document.getElementById('payment-date-wrapper');
    const categorySelect = document.getElementById('category_id');
    const typeRadios = [...document.querySelectorAll('input[name="type"]')];

    const syncPaymentField = () => {
        const paid = status.value === 'pago';
        paymentWrapper.classList.toggle('d-none', !paid);
        paymentDate.required = paid;
        if (!paid) paymentDate.value = '';
    };

    const syncCategories = () => {
        const selectedType = typeRadios.find(r => r.checked)?.value ?? 'receita';
        [...categorySelect.options].forEach(option => {
            if (!option.value) { option.hidden = false; return; }
            option.hidden = option.dataset.type !== selectedType;
        });
        if (categorySelect.selectedOptions[0]?.hidden) categorySelect.value = '';
    };

    status.addEventListener('change', syncPaymentField);
    typeRadios.forEach(r => r.addEventListener('change', syncCategories));
    syncPaymentField();
    syncCategories();
})();
</script>
@endsection
