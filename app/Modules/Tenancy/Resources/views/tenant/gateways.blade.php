@extends('tenancy::tenant.layout')
@section('title', 'Gateways')

@section('content')
<x-ui.page-header
    title="Gateways de Pagamento"
    subtitle="Controle metodos, taxas, parcelamento, webhooks e ambiente por gateway sem sair do painel do tenant."
    eyebrow="Financeiro">
    <x-slot:actions>
        <div class="d-flex flex-wrap gap-2 justify-content-end">
            <span class="badge text-bg-primary">Tenant</span>
            <span class="badge text-bg-secondary">Multi-gateway</span>
        </div>
    </x-slot:actions>
</x-ui.page-header>

@if(! $tenant->plan->custom_gateway)
    <x-ui.premium-card class="mt-4" title="Recurso indisponivel" subtitle="Seu plano atual usa o gateway padrao do sistema.">
        <div class="alert alert-warning mb-0">
            Ative um plano com gateway proprio para habilitar PIX, cartao, boleto, taxas e webhooks por tenant.
        </div>
    </x-ui.premium-card>
@else
    @php
        $availableGateways = [
            'mercadopago' => [
                'label' => 'Mercado Pago',
                'icon' => 'bi-wallet2',
                'color' => 'primary',
                'credentials' => [
                    ['name' => 'access_token', 'label' => 'Access Token', 'type' => 'password'],
                    ['name' => 'public_key', 'label' => 'Public Key', 'type' => 'text'],
                ],
            ],
            'efipro' => [
                'label' => 'Efi Pro',
                'icon' => 'bi-bank',
                'color' => 'success',
                'credentials' => [
                    ['name' => 'client_id', 'label' => 'Client ID', 'type' => 'text'],
                    ['name' => 'client_secret', 'label' => 'Client Secret', 'type' => 'password'],
                    ['name' => 'pix_key', 'label' => 'Chave PIX', 'type' => 'text'],
                    ['name' => 'certificate_path', 'label' => 'Certificado PEM', 'type' => 'text'],
                ],
            ],
            'sumup' => [
                'label' => 'SumUp',
                'icon' => 'bi-credit-card-2-front',
                'color' => 'info',
                'credentials' => [
                    ['name' => 'api_key', 'label' => 'API Key', 'type' => 'password'],
                    ['name' => 'merchant_code', 'label' => 'Merchant Code', 'type' => 'text'],
                ],
            ],
        ];
    @endphp

    <div class="row g-3 mt-1">
        @foreach($availableGateways as $gwKey => $gwInfo)
            @php
                $configured = $gateways->firstWhere('gateway', $gwKey);
                $methods = $configured?->payment_methods ?? [];
                $fees = $configured?->fees ?? [];
                $installments = $configured?->installment_rules ?? [];
                $settings = $configured?->settings ?? [];
                $supports = $gatewaySchema[$gwKey]['supports'] ?? [];
            @endphp
            <div class="col-12 col-xl-4">
                <x-ui.premium-card :title="$gwInfo['label']" :subtitle="'Configuracao premium por tenant'" class="h-100">
                    <x-slot:kicker>{{ strtoupper($gwKey) }}</x-slot:kicker>
                    <x-slot:header>
                        <x-ui.status-badge :status="$configured?->active ? 'active' : 'inactive'" :label="$configured ? ($configured->active ? 'Ativo' : 'Inativo') : 'Nao configurado'" />
                    </x-slot:header>

                    <div class="d-flex align-items-start gap-3 mb-3">
                        <span class="stat-icon bg-{{ $gwInfo['color'] }} text-white">
                            <i class="bi {{ $gwInfo['icon'] }}"></i>
                        </span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                @if($configured?->is_default)
                                    <x-ui.status-badge status="production" label="Padrao" />
                                @endif
                                @if($configured?->environment)
                                    <x-ui.status-badge :status="$configured->environment" :label="strtoupper($configured->environment)" />
                                @endif
                                <span class="badge text-bg-light border">Prioridade {{ $configured?->priority ?? 0 }}</span>
                            </div>
                            <div class="small text-muted">
                                {{ $configured ? 'Webhook tokenizado, taxas e metodos prontos para uso.' : 'Configure credenciais e regras financeiras deste gateway.' }}
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mb-3">
                        @foreach(($gatewaySchema[$gwKey]['methods'] ?? []) as $method)
                            <span class="badge {{ !empty($methods[$method]) ? 'text-bg-success' : 'text-bg-secondary' }}">
                                {{ strtoupper($method) }}
                            </span>
                        @endforeach
                    </div>

                    <div class="row g-2 small">
                        <div class="col-6">
                            <div class="border rounded-3 p-2 h-100">
                                <div class="text-muted text-uppercase small mb-1">Taxa fixa</div>
                                <strong>R$ {{ number_format((float) ($fees['fixed'] ?? 0), 2, ',', '.') }}</strong>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded-3 p-2 h-100">
                                <div class="text-muted text-uppercase small mb-1">Taxa %</div>
                                <strong>{{ number_format((float) ($fees['percent'] ?? 0), 2, ',', '.') }}%</strong>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded-3 p-2 h-100">
                                <div class="text-muted text-uppercase small mb-1">Parcelas</div>
                                <strong>{{ (int) ($installments['max_installments'] ?? 1) }}x</strong>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded-3 p-2 h-100">
                                <div class="text-muted text-uppercase small mb-1">Limite</div>
                                <strong>R$ {{ number_format((float) ($settings['limit_per_charge'] ?? 0), 2, ',', '.') }}</strong>
                            </div>
                        </div>
                    </div>

                    <x-slot:actions>
                        <div class="d-flex gap-2 w-100 justify-content-between">
                            @if($configured)
                                <button type="button" class="btn btn-outline-danger btn-delete-gw" data-gateway="{{ $gwKey }}">
                                    <i class="bi bi-trash me-1"></i>Remover
                                </button>
                            @else
                                <span></span>
                            @endif
                            <button type="button" class="btn btn-{{ $gwInfo['color'] }}" data-bs-toggle="modal" data-bs-target="#modal-{{ $gwKey }}">
                                <i class="bi bi-sliders me-1"></i>Configurar
                            </button>
                        </div>
                    </x-slot:actions>
                </x-ui.premium-card>
            </div>

            <x-ui.premium-modal :id="'modal-'.$gwKey" :title="'Configurar '.$gwInfo['label']" size="xl">
                <form id="frm-{{ $gwKey }}" class="row g-3">
                    <div class="col-12">
                        <x-ui.section-title title="Credenciais" subtitle="Dados obrigatorios para autenticar no provedor." />
                    </div>
                    @foreach($gwInfo['credentials'] as $field)
                        @if($field['name'] === 'certificate_path')
                            @php
                                $certPath = $configured ? ($configured->credentials['certificate_path'] ?? '') : '';
                                $certExists = $certPath && file_exists($certPath);
                                $origName = $configured?->settings['certificate_original_name'] ?? ($certPath ? basename($certPath) : '');
                            @endphp
                            <div class="col-12">
                                <label class="form-label fw-semibold"><i class="bi bi-shield-lock me-1"></i> Certificado PIX (.pem ou .p12)</label>
                                <div class="p-3 border rounded-3 bg-light-subtle">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                        <div>
                                            @if($certExists)
                                                <span class="badge bg-success-subtle text-success fs-6 py-2 px-3">
                                                    <i class="bi bi-patch-check-fill me-1"></i> Certificado Ativo: <strong>{{ $origName }}</strong>
                                                </span>
                                                <div class="small text-muted mt-1">Configurado para emissão e conciliação de PIX com segurança mTLS.</div>
                                            @else
                                                <span class="badge bg-warning-subtle text-warning-emphasis fs-6 py-2 px-3">
                                                    <i class="bi bi-exclamation-triangle-fill me-1"></i> Nenhum certificado anexado
                                                </span>
                                                <div class="small text-muted mt-1">Obrigatório para cobranças PIX na Efi. Faça o envio do seu arquivo .pem ou .p12.</div>
                                            @endif
                                        </div>
                                        <div class="d-flex gap-2">
                                            <input type="file" id="tenant-efi-cert-file" class="d-none" accept=".pem,.p12,.crt,.cer">
                                            <button type="button" class="btn btn-outline-primary btn-sm" id="btn-tenant-upload-cert">
                                                <i class="bi bi-cloud-arrow-up me-1"></i> {{ $certExists ? 'Substituir Certificado' : 'Subir Certificado (.pem / .p12)' }}
                                            </button>
                                            @if($certExists)
                                                <button type="button" class="btn btn-outline-danger btn-sm" id="btn-tenant-delete-cert">
                                                    <i class="bi bi-trash me-1"></i> Remover
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                    <input type="hidden" name="certificate_path" class="gateway-credential" id="tenant-input-cert-path" value="{{ $certPath }}">
                                </div>
                            </div>
                        @else
                            <div class="col-md-6">
                                <label class="form-label">{{ $field['label'] }}</label>
                                <input
                                    type="{{ $field['type'] }}"
                                    name="{{ $field['name'] }}"
                                    class="form-control gateway-credential"
                                    value="{{ $configured ? ($configured->credentials[$field['name']] ?? '') : '' }}">
                            </div>
                        @endif
                    @endforeach

                    <div class="col-12 pt-2">
                        <x-ui.section-title title="Metodos e ambiente" subtitle="Ative apenas o que este tenant realmente usa." />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Ambiente</label>
                        <select name="environment" class="form-select">
                            <option value="production" @selected(($configured?->environment ?? 'production') === 'production')>Producao</option>
                            <option value="sandbox" @selected(($configured?->environment ?? 'production') === 'sandbox')>Sandbox</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Prioridade</label>
                        <input type="number" min="0" max="999" name="priority" class="form-control" value="{{ $configured?->priority ?? 0 }}">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_default" value="1" id="default-{{ $gwKey }}" @checked($configured?->is_default)>
                            <label class="form-check-label" for="default-{{ $gwKey }}">Definir como padrao</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="d-flex flex-wrap gap-3">
                            @foreach(($gatewaySchema[$gwKey]['methods'] ?? []) as $method)
                                <label class="form-check form-switch">
                                    <input class="form-check-input gateway-method" type="checkbox" data-method="{{ $method }}" @checked((bool) ($methods[$method] ?? false))>
                                    <span class="form-check-label">{{ strtoupper($method) }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="col-12 pt-2">
                        <x-ui.section-title title="Taxas e repasse" subtitle="Controle taxa fixa, percentual e absorcao." />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Taxa fixa</label>
                        <input type="number" step="0.01" min="0" name="fees_fixed" class="form-control" value="{{ $fees['fixed'] ?? 0 }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Taxa percentual</label>
                        <input type="number" step="0.01" min="0" name="fees_percent" class="form-control" value="{{ $fees['percent'] ?? 0 }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Split percentual</label>
                        <input type="number" step="0.01" min="0" name="split_percent" class="form-control" value="{{ $configured?->split_rules['percent'] ?? 0 }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Comissao fixa</label>
                        <input type="number" step="0.01" min="0" name="split_fixed" class="form-control" value="{{ $configured?->split_rules['fixed'] ?? 0 }}">
                    </div>

                    <div class="col-12 pt-2">
                        <x-ui.section-title title="Parcelamento e seguranca" subtitle="Maximo de parcelas, juros, limite e allowlist." />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Maximo de parcelas</label>
                        <input type="number" min="1" max="24" name="max_installments" class="form-control" value="{{ $installments['max_installments'] ?? 1 }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Parcelas sem juros</label>
                        <input type="number" min="0" max="24" name="interest_free_installments" class="form-control" value="{{ $installments['interest_free_installments'] ?? 0 }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Juros por parcela (%)</label>
                        <input type="number" step="0.01" min="0" name="interest_percent" class="form-control" value="{{ $installments['interest_percent'] ?? 0 }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Limite por cobranca</label>
                        <input type="number" step="0.01" min="0" name="limit_per_charge" class="form-control" value="{{ $settings['limit_per_charge'] ?? 0 }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">IP allowlist</label>
                        <input type="text" name="ip_allowlist" class="form-control" value="{{ $settings['ip_allowlist'] ?? '' }}" placeholder="1.2.3.4,5.6.7.8">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Webhook secret</label>
                        <input type="text" name="webhook_secret" class="form-control" value="{{ $configured?->webhook_secret ?? '' }}">
                    </div>

                    <div class="col-12 pt-2">
                        <x-ui.section-title title="Recursos extras" subtitle="Flags operacionais do gateway." />
                    </div>
                    <div class="col-12">
                        <div class="d-flex flex-wrap gap-3">
                            @foreach(['fee_passthrough' => 'Repassar taxa ao cliente', 'absorb_fees' => 'Absorver taxas', 'antifraud_enabled' => 'Antifraude', 'debug_mode' => 'Debug mode'] as $flag => $label)
                                <label class="form-check form-switch">
                                    <input class="form-check-input gateway-setting-flag" type="checkbox" data-setting="{{ $flag }}" @checked((bool) ($settings[$flag] ?? false))>
                                    <span class="form-check-label">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </form>
                <x-slot:footer>
                    <div class="d-flex justify-content-between align-items-center w-100">
                        <button type="button" class="btn btn-outline-secondary btn-test-gw" data-gateway="{{ $gwKey }}">
                            <i class="bi bi-plug me-1"></i>Testar Conexao
                        </button>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-{{ $gwInfo['color'] }} btn-save-gw" data-gateway="{{ $gwKey }}">
                                <i class="bi bi-check2 me-1"></i>Salvar gateway
                            </button>
                        </div>
                    </div>
                </x-slot:footer>
            </x-ui.premium-modal>
        @endforeach
    </div>
@endif
@endsection

@push('scripts')
<script>
const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';

function gatewayPayload(form, gateway) {
    const credentials = {};
    form.querySelectorAll('.gateway-credential').forEach((input) => {
        if (input.value.trim()) {
            credentials[input.name] = input.value.trim();
        }
    });

    const paymentMethods = {};
    form.querySelectorAll('.gateway-method').forEach((input) => {
        paymentMethods[input.dataset.method] = input.checked;
    });

    const settings = {
        limit_per_charge: Number(form.querySelector('[name=limit_per_charge]')?.value || 0),
        ip_allowlist: form.querySelector('[name=ip_allowlist]')?.value.trim() || '',
        webhook_secret: form.querySelector('[name=webhook_secret]')?.value.trim() || '',
    };

    form.querySelectorAll('.gateway-setting-flag').forEach((input) => {
        settings[input.dataset.setting] = input.checked;
    });

    return {
        gateway,
        credentials,
        is_default: !!form.querySelector('[name=is_default]')?.checked,
        environment: form.querySelector('[name=environment]')?.value || 'production',
        priority: Number(form.querySelector('[name=priority]')?.value || 0),
        payment_methods: paymentMethods,
        fees: {
            fixed: Number(form.querySelector('[name=fees_fixed]')?.value || 0),
            percent: Number(form.querySelector('[name=fees_percent]')?.value || 0),
        },
        split_rules: {
            percent: Number(form.querySelector('[name=split_percent]')?.value || 0),
            fixed: Number(form.querySelector('[name=split_fixed]')?.value || 0),
        },
        installment_rules: {
            max_installments: Number(form.querySelector('[name=max_installments]')?.value || 1),
            interest_free_installments: Number(form.querySelector('[name=interest_free_installments]')?.value || 0),
            interest_percent: Number(form.querySelector('[name=interest_percent]')?.value || 0),
        },
        settings,
    };
}

document.querySelectorAll('.btn-test-gw').forEach((btn) => {
    btn.addEventListener('click', async () => {
        const gateway = btn.dataset.gateway;
        const form = document.getElementById(`frm-${gateway}`);
        const payload = gatewayPayload(form, gateway);
        btn.disabled = true;
        window.toast.info('Testando credenciais...');

        try {
            const response = await fetch('{{ route("tenant.gateways.test") }}', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    gateway,
                    credentials: payload.credentials,
                    environment: payload.environment,
                })
            });

            const data = await response.json().catch(() => ({}));
            if (response.ok && data.ok) {
                await Swal.fire({
                    icon: 'success',
                    title: 'Conexao Bem-Sucedida!',
                    text: data.message || 'Credenciais validadas com sucesso.',
                });
            } else {
                await Swal.fire({
                    icon: 'error',
                    title: 'Falha na Validacao',
                    text: data.error || data.message || 'Nao foi possivel validar as credenciais.',
                });
            }
        } catch (error) {
            window.toast.error('Erro de conexao ao testar gateway.');
        } finally {
            btn.disabled = false;
        }
    });
});

document.querySelectorAll('.btn-save-gw').forEach((btn) => {
    btn.addEventListener('click', async () => {
        const gateway = btn.dataset.gateway;
        const form = document.getElementById(`frm-${gateway}`);
        const payload = gatewayPayload(form, gateway);
        btn.disabled = true;

        try {
            const response = await fetch('{{ route("tenant.gateways.save") }}', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const data = await response.json().catch(() => ({}));
            if (response.ok && data.ok) {
                window.toast.success('Gateway salvo com sucesso.');
                setTimeout(() => location.reload(), 700);
            } else {
                window.toast.error(data.error || data.message || 'Falha ao salvar gateway.');
            }
        } catch (error) {
            window.toast.error('Erro de conexao ao salvar gateway.');
        }

        btn.disabled = false;
    });
});

document.querySelectorAll('.btn-delete-gw').forEach((btn) => {
    btn.addEventListener('click', async () => {
        const gateway = btn.dataset.gateway;
        const confirmed = await Swal.fire({
            title: 'Remover este gateway?',
            text: 'As cobrancas pendentes deixarao de receber atualizacoes automaticas via webhook deste gateway.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sim, remover',
            cancelButtonText: 'Cancelar',
        });
        if (!confirmed.isConfirmed) return;

        btn.disabled = true;

        try {
            const response = await fetch(`{{ url('/painel/gateways') }}/${gateway}`, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json'
                }
            });

            const data = await response.json().catch(() => ({}));
            if (response.ok && data.ok) {
                window.toast.success('Gateway removido com sucesso.');
                setTimeout(() => location.reload(), 500);
            } else {
                window.toast.error(data.error || 'Falha ao remover gateway.');
                btn.disabled = false;
            }
        } catch (error) {
            window.toast.error('Erro de conexao ao remover gateway.');
            btn.disabled = false;
        }
    });
});

// Upload e exclusao do Certificado Efi pelo Tenant
const btnTenantUploadCert = document.getElementById('btn-tenant-upload-cert');
const tenantCertInput = document.getElementById('tenant-efi-cert-file');
const btnTenantDeleteCert = document.getElementById('btn-tenant-delete-cert');

if (btnTenantUploadCert && tenantCertInput) {
    btnTenantUploadCert.addEventListener('click', () => tenantCertInput.click());

    tenantCertInput.addEventListener('change', async () => {
        if (!tenantCertInput.files.length) return;
        const file = tenantCertInput.files[0];
        const isP12 = file.name.toLowerCase().endsWith('.p12');
        let passphrase = '';

        if (isP12) {
            const { value: pass, isDismissed } = await Swal.fire({
                title: 'Senha do Certificado (.p12)',
                text: 'Se o certificado tiver senha de protecao, digite-a abaixo:',
                input: 'password',
                inputPlaceholder: 'Senha (deixe em branco se nao houver)',
                showCancelButton: true,
                confirmButtonText: 'Continuar Envio',
                cancelButtonText: 'Cancelar',
            });
            if (isDismissed) {
                tenantCertInput.value = '';
                return;
            }
            passphrase = pass || '';
        }

        window.toast.info('Enviando e validando certificado...');
        const fd = new FormData();
        fd.append('certificate', file);
        if (passphrase) fd.append('passphrase', passphrase);
        fd.append('gateway', 'efipro');

        try {
            const r = await fetch('{{ route("tenant.gateways.upload_certificate") }}', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: fd,
            });
            const data = await r.json().catch(() => ({}));
            if (r.ok && data.ok) {
                const hiddenInput = document.getElementById('tenant-input-cert-path');
                if (hiddenInput && data.path) {
                    hiddenInput.value = data.path;
                }
                await Swal.fire({
                    icon: 'success',
                    title: 'Certificado Anexado!',
                    text: data.message || 'Certificado salvo com sucesso.',
                });
                location.reload();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Falha no Upload',
                    text: data.error || data.message || 'Nao foi possivel processar o certificado.',
                });
            }
        } catch (err) {
            window.toast.error('Erro de conexao ao enviar o certificado.');
        } finally {
            tenantCertInput.value = '';
        }
    });
}

if (btnTenantDeleteCert) {
    btnTenantDeleteCert.addEventListener('click', async () => {
        const confirmed = await Swal.fire({
            title: 'Remover Certificado Efi?',
            text: 'As transacoes PIX nao funcionarao sem o certificado.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sim, remover',
            cancelButtonText: 'Cancelar',
        });
        if (!confirmed.isConfirmed) return;

        try {
            const r = await fetch('{{ route("tenant.gateways.delete_certificate", "efipro") }}', {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            });
            const data = await r.json().catch(() => ({}));
            if (r.ok && data.ok) {
                window.toast.success('Certificado removido.');
                setTimeout(() => location.reload(), 500);
            } else {
                window.toast.error(data.error || 'Erro ao remover certificado.');
            }
        } catch (err) {
            window.toast.error('Erro de conexao ao remover certificado.');
        }
    });
}
</script>
@endpush
