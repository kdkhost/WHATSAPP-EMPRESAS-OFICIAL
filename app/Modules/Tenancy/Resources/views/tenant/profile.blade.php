@extends('tenancy::tenant.layout')
@section('title', 'Meu perfil')

@php
    $twoFactorActive = (bool) ($user->two_factor_enabled ?? false) && ! empty($user->two_factor_secret);
@endphp

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <span class="tenant-premium-kicker">Conta do usuario</span>
        <h3 class="mb-1"><i class="bi bi-person-circle me-2 text-primary"></i>Meu perfil</h3>
        <p class="text-muted mb-0 small">Atualize dados pessoais, endereco, senha, foto do painel e seguranca da conta.</p>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-4">
        <div class="tenant-premium-panel text-center">
            <div class="tenant-premium-panel__body">
                <div class="tenant-profile-avatar mx-auto mb-3 {{ $user->avatar_url ? 'has-image' : '' }}"
                     id="tenant-profile-avatar"
                     @if($user->avatar_url) style="background-image:url('{{ $user->avatar_url }}')" @endif>
                    @unless($user->avatar_url)
                        {{ strtoupper(substr($user->name ?? 'U', 0, 1)) }}
                    @endunless
                </div>
                <h5 class="mb-1">{{ $user->name }}</h5>
                <p class="text-muted small mb-3">{{ $user->email }}</p>
                <div class="upload-dropzone py-3" data-upload data-url="{{ route('tenant.uploads.store', 'avatars') }}" data-max-mb="5" id="upload-tenant-avatar" data-owner-type="tenant_user" data-owner-id="{{ $user->id }}">
                    <div class="upload-dropzone-inner">
                        <i class="bi bi-camera text-primary" style="font-size: 1.5rem;"></i>
                        <p class="mb-0 small text-muted">Arraste a foto aqui ou <span class="text-primary fw-semibold">clique</span></p>
                        <small class="text-muted">Max 5MB</small>
                    </div>
                </div>

                <hr class="my-3">
                <div class="profile-mini-list">
                    <div>
                        <span>Tenant</span>
                        <strong>{{ $tenant->company_name }}</strong>
                    </div>
                    <div>
                        <span>Plano</span>
                        <strong>{{ $tenant->plan?->name ?? 'Sem plano' }}</strong>
                    </div>
                    <div>
                        <span>2FA</span>
                        <strong class="{{ $twoFactorActive ? 'text-success' : 'text-danger' }}">
                            {{ $twoFactorActive ? 'Ativo' : 'Inativo' }}
                        </strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="alert alert-info border-0 mt-3 small mb-0">
            <i class="bi bi-info-circle me-1"></i>
            Esta foto e do seu usuario no painel. A foto da instancia WhatsApp continua sendo sincronizada somente pelo WhatsApp conectado.
        </div>
    </div>

    <div class="col-xl-8">
        <div class="tenant-premium-panel">
            <div class="tenant-premium-panel__header">
                <div>
                    <span class="tenant-premium-kicker">Dados pessoais</span>
                    <h5 class="tenant-premium-title">Informacoes do usuario</h5>
                </div>
            </div>
            <div class="tenant-premium-panel__body">
                <form id="tenant-profile-form">
                    @csrf
                    <input type="hidden" name="avatar_url" id="tenant-avatar-url" value="{{ $user->avatar_url ?? '' }}">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nome completo <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="{{ $user->name }}" maxlength="120" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">E-mail <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" value="{{ $user->email }}" maxlength="255" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Telefone</label>
                            <input type="text" name="phone" class="form-control" value="{{ $user->phone ?? '' }}" data-mask="phone" maxlength="20">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Documento</label>
                            <input type="text" name="document" class="form-control" value="{{ $user->document ?? '' }}" data-mask="document" maxlength="20">
                        </div>
                    </div>

                    <hr class="my-4">

                    <div data-address-block>
                        <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-3">
                            <div>
                                <span class="tenant-premium-kicker">Endereco completo</span>
                                <h6 class="mb-0">Dados de localizacao</h6>
                            </div>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                <i class="bi bi-pin-map me-1"></i> ViaCEP automatico
                            </span>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">CEP</label>
                                <input type="text" name="cep" class="form-control" value="{{ $user->cep ?? '' }}" data-mask="cep" data-cep maxlength="9">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Logradouro</label>
                                <input type="text" name="logradouro" class="form-control" value="{{ $user->logradouro ?? '' }}" maxlength="255">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Numero</label>
                                <input type="text" name="numero" class="form-control" value="{{ $user->numero ?? '' }}" maxlength="20">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Complemento</label>
                                <input type="text" name="complemento" class="form-control" value="{{ $user->complemento ?? '' }}" maxlength="255">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Bairro</label>
                                <input type="text" name="bairro" class="form-control" value="{{ $user->bairro ?? '' }}" maxlength="255">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Cidade</label>
                                <input type="text" name="cidade" class="form-control" value="{{ $user->cidade ?? '' }}" maxlength="255">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">UF</label>
                                <input type="text" name="uf" class="form-control text-uppercase" value="{{ $user->uf ?? '' }}" maxlength="2">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="tenant-premium-panel__footer">
                <button type="button" class="btn btn-primary btn-sm" id="btn-save-tenant-profile">
                    <i class="bi bi-check2-circle me-1"></i> Salvar perfil
                </button>
            </div>
        </div>

        <div class="tenant-premium-panel mt-3">
            <div class="tenant-premium-panel__header">
                <div>
                    <span class="tenant-premium-kicker">Seguranca</span>
                    <h5 class="tenant-premium-title">Senha e autenticacao em dois fatores</h5>
                </div>
            </div>
            <div class="tenant-premium-panel__body">
                <div class="row g-3">
                    <div class="col-lg-7">
                        <form id="tenant-password-form">
                            @csrf
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Senha atual</label>
                                    <input type="password" name="current_password" class="form-control" autocomplete="current-password" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Nova senha</label>
                                    <input type="password" name="password" class="form-control" autocomplete="new-password" minlength="8" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Confirmar nova senha</label>
                                    <input type="password" name="password_confirmation" class="form-control" autocomplete="new-password" minlength="8" required>
                                </div>
                            </div>
                            <button type="button" class="btn btn-warning btn-sm mt-3" id="btn-save-tenant-password">
                                <i class="bi bi-lock me-1"></i> Alterar senha
                            </button>
                        </form>
                    </div>
                    <div class="col-lg-5">
                        <div class="security-card h-100">
                            <div class="d-flex align-items-start justify-content-between gap-3">
                                <div>
                                    <h6 class="mb-1">Autenticacao em dois fatores</h6>
                                    <p class="text-muted small mb-0">Use Google Authenticator, Authy ou app compativel com TOTP.</p>
                                </div>
                                <span class="badge {{ $twoFactorActive ? 'bg-success' : 'bg-danger' }}">
                                    {{ $twoFactorActive ? 'Ativo' : 'Inativo' }}
                                </span>
                            </div>
                            <div class="mt-3">
                                @if($twoFactorActive)
                                    <button type="button" class="btn btn-danger btn-sm" id="btn-disable-tenant-2fa">
                                        <i class="bi bi-shield-x me-1"></i> Desativar 2FA
                                    </button>
                                @else
                                    <button type="button" class="btn btn-success btn-sm" id="btn-enable-tenant-2fa">
                                        <i class="bi bi-shield-plus me-1"></i> Ativar 2FA
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="tenant2faModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-shield-plus me-2 text-success"></i>Ativar 2FA</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="tenant-2fa-step-qr">
                    <p class="text-muted small">Escaneie o QR Code no app autenticador e depois confirme o codigo de 6 digitos.</p>
                    <div class="border rounded p-3 bg-white mx-auto mb-3 d-flex justify-content-center" style="width:236px;">
                        <div id="tenant-2fa-qr" style="width:200px;height:200px;"></div>
                    </div>
                    <div class="alert alert-info border-0 small">
                        Chave manual: <code id="tenant-2fa-secret" class="user-select-all"></code>
                    </div>
                    <form id="tenant-2fa-confirm-form">
                        @csrf
                        <label class="form-label">Codigo do app autenticador</label>
                        <input type="text" name="code" class="form-control form-control-lg text-center font-monospace"
                               maxlength="6" inputmode="numeric" autocomplete="one-time-code" required
                               style="letter-spacing:.45em;">
                    </form>
                </div>
                <div id="tenant-2fa-step-codes" class="d-none">
                    <div class="alert alert-success border-0">
                        <strong>2FA ativado.</strong> Guarde os codigos de recuperacao abaixo em local seguro.
                    </div>
                    <div class="border rounded p-3 bg-body-tertiary font-monospace small" id="tenant-2fa-recovery-codes"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-success btn-sm" id="btn-confirm-tenant-2fa">
                    <i class="bi bi-check2-circle me-1"></i> Confirmar e ativar
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.tenant-profile-avatar {
    width: 92px;
    height: 92px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 3px solid color-mix(in srgb, var(--bs-primary) 22%, transparent);
    background: linear-gradient(135deg, var(--bs-primary), color-mix(in srgb, var(--bs-primary) 45%, #0f172a));
    color: #fff;
    font-size: 2.15rem;
    font-weight: 800;
    background-size: cover;
    background-position: center;
    box-shadow: 0 16px 35px rgba(15, 23, 42, .18);
}
.tenant-profile-avatar.has-image { color: transparent; }
.profile-mini-list { display: grid; gap: .55rem; text-align: left; }
.profile-mini-list > div {
    display: flex;
    justify-content: space-between;
    gap: .75rem;
    padding: .65rem .75rem;
    border: 1px solid var(--app-border-soft);
    border-radius: 12px;
    background: color-mix(in srgb, var(--bs-body-bg) 92%, var(--bs-primary) 8%);
}
.profile-mini-list span { color: var(--bs-secondary-color); }
.security-card {
    border: 1px solid var(--app-border-soft);
    border-radius: 14px;
    padding: 1rem;
    background: color-mix(in srgb, var(--bs-body-bg) 92%, var(--bs-primary) 8%);
}
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
const tenantCsrf = document.querySelector('meta[name=csrf-token]').content;
const tenantAvatarInitial = @json(strtoupper(substr($user->name ?? 'U', 0, 1)));
let tenant2faQr = null;

function setTenantAvatar(url) {
    const preview = document.getElementById('tenant-profile-avatar');
    const input = document.getElementById('tenant-avatar-url');
    input.value = url || '';
    if (url) {
        preview.classList.add('has-image');
        preview.style.backgroundImage = `url('${url}')`;
        preview.textContent = '';
        return;
    }
    preview.classList.remove('has-image');
    preview.style.backgroundImage = '';
    preview.textContent = tenantAvatarInitial;
}

document.getElementById('btn-tenant-avatar')?.addEventListener('click', () => {
    document.getElementById('tenant-avatar-file').click();
});

document.getElementById('tenant-avatar-file')?.addEventListener('change', async (event) => {
    const file = event.target.files?.[0];
    if (!file) return;

    const btn = document.getElementById('btn-tenant-avatar');
    const original = btn.innerHTML;
    const currentUrl = document.getElementById('tenant-avatar-url').value;
    const fd = new FormData();
    fd.append('file', file);
    fd.append('owner_type', 'tenant_user');
    fd.append('owner_id', '{{ $user->id }}');
    if (currentUrl) fd.append('replace_url', currentUrl);

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Enviando...';
    try {
        const response = await fetch('{{ route("tenant.uploads.store", "avatars") }}', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': tenantCsrf, 'Accept': 'application/json' },
            body: fd,
        });
        const data = await response.json();
        if (!response.ok || !data.ok) {
            window.toast.error(data.message || data.error || 'Nao foi possivel enviar a foto.');
            return;
        }
        setTenantAvatar(data.url);
        window.toast.success('Foto enviada. Salve o perfil para aplicar.');
    } catch (error) {
        window.toast.error('Erro de rede ao enviar a foto.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = original;
        event.target.value = '';
    }
});

document.getElementById('btn-save-tenant-profile')?.addEventListener('click', async () => {
    const btn = document.getElementById('btn-save-tenant-profile');
    const original = btn.innerHTML;
    const fd = new FormData(document.getElementById('tenant-profile-form'));

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Salvando...';
    try {
        const response = await fetch('{{ route("tenant.profile.update") }}', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': tenantCsrf, 'Accept': 'application/json' },
            body: fd,
        });
        const data = await response.json();
        if (response.ok && data.ok) {
            window.toast.success('Perfil atualizado.');
            setTimeout(() => window.location.reload(), 600);
            return;
        }
        window.toast.error(data.message || 'Nao foi possivel salvar o perfil.');
    } catch (error) {
        window.toast.error('Erro de rede ao salvar o perfil.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = original;
    }
});

document.getElementById('btn-save-tenant-password')?.addEventListener('click', async () => {
    const btn = document.getElementById('btn-save-tenant-password');
    const original = btn.innerHTML;
    const form = document.getElementById('tenant-password-form');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Alterando...';
    try {
        const response = await fetch('{{ route("tenant.profile.password") }}', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': tenantCsrf, 'Accept': 'application/json' },
            body: new FormData(form),
        });
        const data = await response.json();
        if (response.ok && data.ok) {
            window.toast.success('Senha alterada com sucesso.');
            form.reset();
            return;
        }
        window.toast.error(data.message || 'Nao foi possivel alterar a senha.');
    } catch (error) {
        window.toast.error('Erro de rede ao alterar a senha.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = original;
    }
});

document.getElementById('btn-enable-tenant-2fa')?.addEventListener('click', async () => {
    const btn = document.getElementById('btn-enable-tenant-2fa');
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Gerando...';
    try {
        const response = await fetch('{{ route("tenant.profile.2fa.setup") }}', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': tenantCsrf, 'Accept': 'application/json' },
        });
        const data = await response.json();
        if (!response.ok || !data.ok) {
            window.toast.error(data.error || 'Nao foi possivel iniciar o 2FA.');
            return;
        }
        document.getElementById('tenant-2fa-secret').textContent = data.secret;
        document.getElementById('tenant-2fa-qr').innerHTML = '';
        tenant2faQr = new QRCode(document.getElementById('tenant-2fa-qr'), {
            text: data.qr_url,
            width: 200,
            height: 200,
            colorDark: '#000000',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.M,
        });
        document.getElementById('tenant-2fa-step-qr').classList.remove('d-none');
        document.getElementById('tenant-2fa-step-codes').classList.add('d-none');
        document.getElementById('btn-confirm-tenant-2fa').classList.remove('d-none');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('tenant2faModal')).show();
    } catch (error) {
        window.toast.error('Erro de rede ao iniciar o 2FA.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = original;
    }
});

document.getElementById('btn-confirm-tenant-2fa')?.addEventListener('click', async () => {
    const btn = document.getElementById('btn-confirm-tenant-2fa');
    const original = btn.innerHTML;
    const form = document.getElementById('tenant-2fa-confirm-form');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Validando...';
    try {
        const response = await fetch('{{ route("tenant.profile.2fa.confirm") }}', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': tenantCsrf, 'Accept': 'application/json' },
            body: new FormData(form),
        });
        const data = await response.json();
        if (response.ok && data.ok) {
            const codes = (data.recovery_codes || []).map(code => `<div class="py-1 border-bottom">${code}</div>`).join('');
            document.getElementById('tenant-2fa-recovery-codes').innerHTML = codes;
            document.getElementById('tenant-2fa-step-qr').classList.add('d-none');
            document.getElementById('tenant-2fa-step-codes').classList.remove('d-none');
            btn.classList.add('d-none');
            window.toast.success('2FA ativado com sucesso.');
            setTimeout(() => window.location.reload(), 1800);
            return;
        }
        window.toast.error(data.error || data.message || 'Codigo invalido.');
    } catch (error) {
        window.toast.error('Erro de rede ao confirmar o 2FA.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = original;
    }
});

document.getElementById('btn-disable-tenant-2fa')?.addEventListener('click', async () => {
    const result = await Swal.fire({
        icon: 'warning',
        title: 'Desativar 2FA?',
        text: 'Informe sua senha atual para confirmar.',
        input: 'password',
        inputPlaceholder: 'Senha atual',
        showCancelButton: true,
        confirmButtonText: 'Desativar 2FA',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545',
        inputValidator: value => !value ? 'Informe sua senha atual.' : null,
    });
    if (!result.isConfirmed) return;

    try {
        const response = await fetch('{{ route("tenant.profile.2fa.disable") }}', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': tenantCsrf,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ password: result.value }),
        });
        const data = await response.json();
        if (response.ok && data.ok) {
            window.toast.success(data.message || '2FA desativado.');
            setTimeout(() => window.location.reload(), 700);
            return;
        }
        window.toast.error(data.error || data.message || 'Nao foi possivel desativar o 2FA.');
    } catch (error) {
        window.toast.error('Erro de rede ao desativar o 2FA.');
    }
});
</script>
@endpush
