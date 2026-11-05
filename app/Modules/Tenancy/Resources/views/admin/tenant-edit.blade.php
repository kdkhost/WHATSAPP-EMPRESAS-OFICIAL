@extends('admin.layouts.app')

@section('title', 'Editar Tenant - ' . $tenant->company_name)

@section('content')
<div class="container-fluid admin-module-shell">
    <form id="tenant-edit-form" action="{{ route('admin.tenancy.tenants.update', $tenant->id) }}" method="POST">
        @csrf
        @method('PUT')

        <x-ui.page-header
            title="Editar tenant"
            subtitle="Atualize dados cadastrais, plano, status da assinatura e acesso comercial da API REST."
            eyebrow="Superadmin"
            class="mb-4"
        >
            <x-slot:actions>
            <div class="d-flex gap-2 flex-wrap justify-content-end">
                <a href="{{ route('admin.tenancy.tenants.show', $tenant->id) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Voltar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i> Salvar
                </button>
            </div>
            </x-slot:actions>
        </x-ui.page-header>

        <div class="row g-4">
            <div class="col-lg-8">
                <x-ui.premium-card title="Dados do tenant" class="mb-4">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">Empresa</label>
                                <input type="text" name="company_name" class="form-control" value="{{ old('company_name', $tenant->company_name) }}" required maxlength="160">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Slug</label>
                                <input type="text" name="slug" class="form-control" value="{{ old('slug', $tenant->slug) }}" required maxlength="60">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nome do usuário</label>
                                <input type="text" name="user_name" class="form-control" value="{{ old('user_name', $tenant->user->name ?? '') }}" required maxlength="120">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">E-mail do usuário</label>
                                <input type="email" name="user_email" class="form-control" value="{{ old('user_email', $tenant->user->email ?? '') }}" required maxlength="160">
                            </div>
                        </div>
                </x-ui.premium-card>

                <x-ui.premium-card title="API REST comercial">
                        @php $apiSettings = $tenant->publicApiSettings(); @endphp
                        <div class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label d-block">Acesso REST</label>
                                <input type="hidden" name="api_enabled" value="0">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="api_enabled" name="api_enabled" value="1" {{ old('api_enabled', $tenant->publicApiEnabled()) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="api_enabled">Liberado para este tenant</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Taxa mensal da API</label>
                                <input type="text" name="api_monthly_fee" class="form-control money-mask" value="{{ old('api_monthly_fee', number_format((float) ($apiSettings['monthly_fee'] ?? 0), 2, ',', '.')) }}" placeholder="0,00">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Status atual</label>
                                <div>
                                    <span class="badge text-bg-{{ $tenant->publicApiEnabled() ? 'success' : 'secondary' }}">
                                        {{ $tenant->publicApiEnabled() ? 'API liberada' : 'API bloqueada' }}
                                    </span>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Observações comerciais</label>
                                <textarea name="api_notes" class="form-control" rows="3" maxlength="500">{{ old('api_notes', $apiSettings['notes'] ?? '') }}</textarea>
                            </div>
                        </div>
                        <div class="form-text mt-3">
                            A API também pode ser liberada automaticamente por plano quando a lista de recursos inclui API pública.
                        </div>
                </x-ui.premium-card>
            </div>

            <div class="col-lg-4">
                <x-ui.premium-card title="Assinatura" class="mb-4">
                        <div class="mb-3">
                            <label class="form-label">Plano</label>
                            <select name="plan_id" class="form-select" required>
                                @foreach($plans as $plan)
                                    <option value="{{ $plan->id }}" @selected((int) old('plan_id', $tenant->plan_id) === (int) $plan->id)>
                                        {{ $plan->name }} - {{ \App\Support\Format::money($plan->price) }}/{{ $plan->billing_cycle }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select" required>
                                @foreach(['trial' => 'Teste', 'active' => 'Ativo', 'suspended' => 'Suspenso', 'canceled' => 'Cancelado'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('status', $tenant->status) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Gateway da assinatura</label>
                            <input type="text" name="gateway" class="form-control" value="{{ old('gateway', $tenant->gateway) }}" maxlength="30" placeholder="mercadopago">
                        </div>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Fim do teste</label>
                                <input type="date" name="trial_ends_at" class="form-control" value="{{ old('trial_ends_at', $tenant->trial_ends_at?->format('Y-m-d')) }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Início do período</label>
                                <input type="date" name="current_period_start" class="form-control" value="{{ old('current_period_start', $tenant->current_period_start?->format('Y-m-d')) }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Fim do período</label>
                                <input type="date" name="current_period_end" class="form-control" value="{{ old('current_period_end', $tenant->current_period_end?->format('Y-m-d')) }}">
                            </div>
                        </div>
                </x-ui.premium-card>

                <div class="alert alert-info">
                    <strong>Cobrança da API:</strong> a taxa mensal fica registrada no tenant para controle comercial e pode ser incluída nas faturas recorrentes.
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.money-mask').forEach((input) => {
    input.addEventListener('input', () => {
        const digits = input.value.replace(/\D/g, '').padStart(3, '0');
        const value = Number(digits) / 100;
        input.value = value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    });
});

document.getElementById('tenant-edit-form')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const button = form.querySelector('button[type="submit"]');
    button.disabled = true;

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
            body: new FormData(form),
        });
        const data = await response.json();

        if (response.ok && data.ok) {
            window.toast?.success(data.message || 'Tenant atualizado.');
            window.location.href = data.redirect;
            return;
        }

        if (data.errors) {
            const first = Object.values(data.errors).flat()[0];
            window.toast?.error(first || 'Confira os campos do formulario.');
        } else {
            window.toast?.error(data.message || 'Não foi possível atualizar o tenant.');
        }
    } finally {
        button.disabled = false;
    }
});
</script>
@endpush
