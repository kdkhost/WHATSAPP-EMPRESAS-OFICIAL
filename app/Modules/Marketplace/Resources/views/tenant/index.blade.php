@extends('tenancy::tenant.layout')

@section('title', 'Addons')

@section('content')
<x-ui.page-header
    title="Marketplace de Addons"
    eyebrow="Expansao do tenant"
    subtitle="Assine addons homologados para a sua conta e envie seu proprio pacote ZIP para revisao do superadmin."
>
    <x-slot:aside>
        <div class="admin-hero-note">
            <span class="admin-hero-note__label">Ativos na conta</span>
            <span class="admin-hero-note__value">{{ number_format($myAddons->where('status', 'active')->count(), 0, ',', '.') }}</span>
        </div>
    </x-slot:aside>
</x-ui.page-header>

<div class="row g-4">
    <div class="col-12 col-xl-8">
        <x-ui.premium-card title="Catalogo homologado" subtitle="Somente addons aprovados e instalados aparecem para assinatura.">
            <div class="row g-3">
                @forelse($catalog as $addon)
                    <div class="col-12 col-lg-6">
                        <div class="border rounded-4 p-3 h-100">
                            <div class="d-flex justify-content-between gap-3 align-items-start">
                                <div>
                                    <div class="fw-semibold">{{ $addon->name }}</div>
                                    <div class="small text-muted">{{ $addon->category ?: 'Addons' }} • v{{ $addon->version }}</div>
                                </div>
                                <x-ui.status-badge :status="$addon->tenant_status === 'active' ? 'active' : ($addon->tenant_status === 'pending' ? 'pending' : 'inactive')" :label="$addon->tenant_status ? ucfirst($addon->tenant_status) : 'Disponivel'" />
                            </div>
                            <p class="small text-muted mt-3 mb-3">{{ $addon->description ?: 'Sem descricao detalhada.' }}</p>
                            <div class="d-flex justify-content-between align-items-center">
                                <strong>{{ (float) $addon->price > 0 ? 'R$ '.number_format((float) $addon->price, 2, ',', '.') : 'Gratuito' }}</strong>
                                @if(in_array($addon->tenant_status, ['active', 'pending'], true))
                                    <button class="btn btn-sm btn-outline-secondary" disabled>{{ ucfirst($addon->tenant_status) }}</button>
                                @else
                                    <button class="btn btn-sm btn-primary" data-subscribe="{{ $addon->id }}">Assinar</button>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <x-ui.empty-state icon="bi-puzzle" title="Sem addons homologados" message="Quando o superadmin aprovar novos pacotes, eles aparecerao aqui para assinatura." />
                    </div>
                @endforelse
            </div>
        </x-ui.premium-card>
    </div>

    <div class="col-12 col-xl-4">
        <x-ui.premium-card title="Enviar ZIP do addon" subtitle="Arraste o pacote ou clique na area. O upload e automatico.">
            <form id="addon-upload-form">
                <div id="drop-zone" class="border border-2 border-dashed rounded-4 p-4 text-center cursor-pointer"
                     style="border-color: var(--bs-border-color); transition: all .2s;">
                    <i class="bi bi-cloud-arrow-up fs-1 text-muted mb-2 d-block"></i>
                    <div class="text-muted small mb-2" id="drop-text">Arraste o ZIP aqui ou clique para selecionar</div>
                    <input type="file" name="package" accept=".zip" class="d-none" id="file-input">

                    <div id="file-preview" class="d-none text-start">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="bi bi-file-zip fs-4 text-warning"></i>
                            <div class="flex-grow-1">
                                <div class="fw-semibold small" id="preview-name"></div>
                                <div class="text-muted small" id="preview-size"></div>
                            </div>
                            <button type="button" class="btn btn-sm btn-link text-danger" id="btn-remove-file"><i class="bi bi-x-lg"></i></button>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div id="upload-progress" class="progress-bar progress-bar-striped progress-bar-animated bg-primary"
                                 role="progressbar" style="width: 0%"></div>
                        </div>
                        <div class="small text-muted mt-1" id="upload-status">Preparando...</div>
                    </div>
                </div>
            </form>
        </x-ui.premium-card>

        <x-ui.premium-card title="Meus addons" subtitle="Status comercial da sua conta tenant.">
            <div class="d-grid gap-3">
                @forelse($myAddons as $tenantAddon)
                    @php
                        $manifest = $tenantAddon->addon?->manifest ?? [];
                        $addonRoute = null;
                        if (! empty($manifest['tenant_menu'])) {
                            $menu = is_array($manifest['tenant_menu']) && array_is_list($manifest['tenant_menu']) ? $manifest['tenant_menu'][0] : $manifest['tenant_menu'];
                            $addonRoute = $menu['route'] ?? null;
                        } elseif (! empty($manifest['menu']['items'][0]['route'])) {
                            $addonRoute = $manifest['menu']['items'][0]['route'];
                        } elseif (! empty($manifest['menu']['route'])) {
                            $addonRoute = $manifest['menu']['route'];
                        }
                        $addonUrl = $addonRoute && Route::has($addonRoute) ? route($addonRoute) : null;
                    @endphp
                    <div class="border rounded-4 p-3" data-addon-id="{{ $tenantAddon->id }}">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="fw-semibold">{{ $tenantAddon->addon?->name ?: 'Addon removido' }}</div>
                                <div class="small text-muted">{{ $tenantAddon->addon?->slug ?: '-' }}</div>
                                <span class="badge bg-{{ $tenantAddon->status === 'active' ? 'success' : ($tenantAddon->status === 'pending' ? 'info' : 'secondary') }} mt-1">
                                    {{ ucfirst($tenantAddon->status) }}
                                </span>
                            </div>
                        </div>
                        @if($tenantAddon->status === 'active')
                            <div class="d-flex gap-2 mt-3">
                                @if($addonUrl)
                                    <a href="{{ $addonUrl }}" class="btn btn-sm btn-primary flex-fill"><i class="bi bi-box-arrow-in-right me-1"></i>Acessar</a>
                                @endif
                                <button class="btn btn-sm btn-outline-warning js-deactivate-addon" data-id="{{ $tenantAddon->id }}" data-name="{{ $tenantAddon->addon?->name }}">
                                    <i class="bi bi-pause-circle me-1"></i>Desativar
                                </button>
                                <button class="btn btn-sm btn-outline-danger js-uninstall-addon" data-id="{{ $tenantAddon->id }}" data-name="{{ $tenantAddon->addon?->name }}">
                                    <i class="bi bi-trash me-1"></i>Desinstalar
                                </button>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="small text-muted">Nenhum addon contratado pela sua conta ainda.</div>
                @endforelse
            </div>
        </x-ui.premium-card>
    </div>
</div>

@if(!empty($systemAddons))
<div class="row g-4 mt-2">
    <div class="col-12">
        <x-ui.premium-card title="Modulos do sistema" subtitle="Addons instalados no sistema disponiveis para ativacao.">
            <div class="row g-3">
                @foreach($systemAddons as $sysAddon)
                    @php
                        $tenantAddon = $myAddons->first(fn($ta) => $ta->addon?->slug === $sysAddon['slug']);
                        $tenantStatus = $tenantAddon?->status;
                        $sysPrice = (float) data_get($sysAddon['meta'], 'marketplace.price', 0);
                        $isFree = $sysPrice <= 0;
                    @endphp
                    <div class="col-12 col-lg-6 col-xl-4">
                        <div class="border rounded-4 p-3 h-100">
                            <div class="d-flex justify-content-between gap-3 align-items-start">
                                <div>
                                    <div class="fw-semibold"><i class="bi {{ $sysAddon['icon'] }} me-1 text-primary"></i>{{ $sysAddon['name'] }}</div>
                                    <div class="small text-muted">{{ $sysAddon['slug'] }} • v{{ $sysAddon['version'] }}</div>
                                </div>
                                @if($tenantStatus === 'active')
                                    <span class="badge bg-success">Ativo</span>
                                @elseif($tenantStatus === 'pending')
                                    <span class="badge bg-info">Pendente</span>
                                @else
                                    <span class="badge bg-secondary">Disponivel</span>
                                @endif
                            </div>
                            <p class="small text-muted mt-2 mb-2">{{ $sysAddon['description'] ?: 'Sem descricao.' }}</p>
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted d-block">{{ $sysAddon['author'] ?? 'Sistema' }}</small>
                                    <strong class="{{ $isFree ? 'text-success' : 'text-primary' }}">
                                        {{ $isFree ? 'Gratuito' : 'R$ '.number_format($sysPrice, 2, ',', '.') }}
                                    </strong>
                                </div>
                                @if(in_array($tenantStatus, ['active', 'pending'], true))
                                    <button class="btn btn-sm btn-outline-secondary" disabled>
                                        {{ $tenantStatus === 'active' ? 'Ativo' : 'Pendente' }}
                                    </button>
                                @else
                                    <button class="btn btn-sm btn-{{ $isFree ? 'success' : 'primary' }} js-rent-addon"
                                            data-slug="{{ $sysAddon['slug'] }}"
                                            data-price="{{ $sysPrice }}"
                                            data-name="{{ $sysAddon['name'] }}">
                                        <i class="bi {{ $isFree ? 'bi-download' : 'bi-cart' }} me-1"></i>{{ $isFree ? 'Instalar' : 'Alugar' }}
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-ui.premium-card>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
const dropZone = document.getElementById('drop-zone');
const fileInput = document.getElementById('file-input');
const filePreview = document.getElementById('file-preview');
const dropText = document.getElementById('drop-text');
const uploadIcon = dropZone.querySelector('i.bi-cloud-arrow-up');
const progressBar = document.getElementById('upload-progress');
const uploadStatus = document.getElementById('upload-status');
const btnRemove = document.getElementById('btn-remove-file');

function resetUpload() {
    fileInput.value = '';
    filePreview.classList.add('d-none');
    dropText.classList.remove('d-none');
    uploadIcon.classList.remove('d-none');
    progressBar.style.width = '0%';
    progressBar.classList.remove('bg-success', 'bg-danger');
    progressBar.classList.add('bg-primary');
    uploadStatus.textContent = 'Preparando...';
    dropZone.classList.remove('border-primary', 'border-success', 'border-danger');
}

function formatBytes(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

async function uploadFile(file) {
    if (!file || file.name.split('.').pop().toLowerCase() !== 'zip') {
        window.toast?.error('Envie apenas arquivos ZIP.');
        resetUpload();
        return;
    }

    document.getElementById('preview-name').textContent = file.name;
    document.getElementById('preview-size').textContent = formatBytes(file.size);
    filePreview.classList.remove('d-none');
    dropText.classList.add('d-none');
    uploadIcon.classList.add('d-none');
    uploadStatus.textContent = 'Enviando...';

    const xhr = new XMLHttpRequest();
    const fd = new FormData();
    fd.append('package', file);
    fd.append('_token', document.querySelector('meta[name="csrf-token"]').content);

    xhr.upload.addEventListener('progress', (e) => {
        if (e.lengthComputable) {
            const pct = Math.round((e.loaded / e.total) * 100);
            progressBar.style.width = pct + '%';
            uploadStatus.textContent = pct + '% enviado';
        }
    });

    xhr.addEventListener('load', () => {
        progressBar.style.width = '100%';
        if (xhr.status >= 200 && xhr.status < 300) {
            const data = JSON.parse(xhr.responseText);
            if (data.ok) {
                progressBar.classList.remove('bg-primary');
                progressBar.classList.add('bg-success');
                uploadStatus.textContent = 'Enviado com sucesso!';
                window.toast?.success(data.message || 'Pacote enviado para revisao.');
                setTimeout(() => window.location.reload(), 1200);
            } else {
                progressBar.classList.remove('bg-primary');
                progressBar.classList.add('bg-danger');
                uploadStatus.textContent = 'Erro no envio';
                window.toast?.error(data.message || data.error || 'Erro ao enviar.');
            }
        } else {
            progressBar.classList.remove('bg-primary');
            progressBar.classList.add('bg-danger');
            uploadStatus.textContent = 'Falha no envio';
            window.toast?.error('Erro ao enviar o pacote.');
        }
    });

    xhr.addEventListener('error', () => {
        progressBar.classList.remove('bg-primary');
        progressBar.classList.add('bg-danger');
        uploadStatus.textContent = 'Erro de rede';
        window.toast?.error('Erro de rede ao enviar.');
    });

    xhr.open('POST', `{{ route('tenant.addons.submit') }}`);
    xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]').content);
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.send(fd);
}

dropZone.addEventListener('click', () => fileInput.click());

dropZone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropZone.classList.add('border-primary');
    dropZone.style.background = 'rgba(var(--bs-primary-rgb), .05)';
});

dropZone.addEventListener('dragleave', () => {
    dropZone.classList.remove('border-primary');
    dropZone.style.background = '';
});

dropZone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropZone.classList.remove('border-primary');
    dropZone.style.background = '';
    const file = e.dataTransfer.files[0];
    if (file) uploadFile(file);
});

fileInput.addEventListener('change', () => {
    const file = fileInput.files[0];
    if (file) uploadFile(file);
});

btnRemove?.addEventListener('click', (e) => {
    e.stopPropagation();
    resetUpload();
});

document.querySelectorAll('[data-subscribe]').forEach((button) => {
    button.addEventListener('click', async () => {
        const response = await fetch(`{{ url('/painel/addons') }}/${button.dataset.subscribe}/subscribe`, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.ok) {
            window.toast?.error(data.message || data.error || 'Nao foi possivel contratar o addon.');
            return;
        }
        window.toast?.success(data.message || 'Addon contratado.');
        window.location.reload();
    });
});

// Desativar addon
document.querySelectorAll('.js-deactivate-addon').forEach(btn => {
    btn.addEventListener('click', async () => {
        const result = await Swal.fire({
            title: 'Desativar ' + btn.dataset.name + '?',
            text: 'O modulo sera desativado mas pode ser reativado depois.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sim, desativar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#ffc107',
            cancelButtonColor: '#6c757d',
            reverseButtons: true
        });
        if (!result.isConfirmed) return;
        const r = await fetch(`{{ url('/painel/addons') }}/${btn.dataset.id}/deactivate`, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        const d = await r.json().catch(() => ({}));
        if (r.ok && d.ok) {
            window.toast?.success(d.message);
            setTimeout(() => window.location.reload(), 800);
        } else {
            window.toast?.error(d.error || d.message || 'Erro ao desativar.');
        }
    });
});

// Desinstalar addon
document.querySelectorAll('.js-uninstall-addon').forEach(btn => {
    btn.addEventListener('click', async () => {
        const result = await Swal.fire({
            title: 'Desinstalar ' + btn.dataset.name + '?',
            text: 'Esta acao cancela a assinatura do modulo.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sim, desinstalar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            reverseButtons: true
        });
        if (!result.isConfirmed) return;
        const r = await fetch(`{{ url('/painel/addons') }}/${btn.dataset.id}/uninstall`, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        const d = await r.json().catch(() => ({}));
        if (r.ok && d.ok) {
            window.toast?.success(d.message);
            setTimeout(() => window.location.reload(), 800);
        } else {
            window.toast?.error(d.error || d.message || 'Erro ao desinstalar.');
        }
    });
});

// Alugar/Instalar modulo do sistema
document.querySelectorAll('.js-rent-addon').forEach(btn => {
    btn.addEventListener('click', async () => {
        const price = parseFloat(btn.dataset.price) || 0;
        const isFree = price <= 0;
        const slug = btn.dataset.slug;

        if (isFree) {
            const result = await Swal.fire({
                title: 'Instalar ' + btn.dataset.name + '?',
                text: 'Este modulo sera instalado e ativado na sua conta imediatamente.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sim, instalar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#198754',
                reverseButtons: true
            });
            if (!result.isConfirmed) return;
            await doRent(slug, null);
            return;
        }

        // Addon pago: mostrar opcoes de cobranca
        const result = await Swal.fire({
            title: 'Alugar ' + btn.dataset.name + '?',
            html: `
                <div class="text-start">
                    <p class="mb-3">Valor mensal: <strong>R$ ${price.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</strong></p>
                    <p class="small text-muted mb-3">Escolha como deseja pagar:</p>
                    <div class="d-grid gap-2">
                        <label class="border rounded p-2 d-flex align-items-center gap-2 cursor-pointer" style="border-color: var(--bs-border-color);">
                            <input type="radio" name="billing_mode" value="separate" checked class="form-check-input m-0">
                            <div>
                                <div class="fw-semibold small">Fatura separada</div>
                                <div class="small text-muted">Vencimento em 3 dias uteis</div>
                            </div>
                        </label>
                        <label class="border rounded p-2 d-flex align-items-center gap-2 cursor-pointer" style="border-color: var(--bs-border-color);">
                            <input type="radio" name="billing_mode" value="plan_prorata" class="form-check-input m-0">
                            <div>
                                <div class="fw-semibold small">Vincular ao plano (pro-rata)</div>
                                <div class="small text-muted">Paga proporcional ate o vencimento do plano. Renova junto.</div>
                            </div>
                        </label>
                    </div>
                </div>
            `,
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: 'Continuar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#0d6efd',
            reverseButtons: true,
            preConfirm: () => {
                const selected = document.querySelector('input[name="billing_mode"]:checked');
                return selected ? selected.value : 'separate';
            }
        });
        if (!result.isConfirmed) return;
        const billingMode = result.value === 'plan_prorata' ? 'plan_prorata' : null;
        await doRent(slug, billingMode);
    });
});

async function doRent(slug, billingMode) {
    const r = await fetch(`{{ url('/painel/addons/rent') }}`, {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ slug, billing_mode: billingMode })
    });
    const d = await r.json().catch(() => ({}));
    if (r.ok && d.ok) {
        window.toast?.success(d.message || 'Operacao realizada com sucesso.');
        setTimeout(() => window.location.reload(), 800);
    } else {
        window.toast?.error(d.error || d.message || 'Erro ao processar.');
    }
}
</script>
@endpush
