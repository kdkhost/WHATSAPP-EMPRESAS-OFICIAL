@extends('admin.layouts.app')
@section('title', 'Meu perfil')

@push('head')
<style>
.profile-page .profile-avatar-preview {
    width: 96px;
    height: 96px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 3px solid color-mix(in srgb, var(--bs-primary) 22%, transparent);
    background: linear-gradient(135deg, var(--bs-primary), color-mix(in srgb, var(--bs-primary) 45%, #0f172a));
    color: #fff;
    font-size: 2rem;
    font-weight: 800;
    background-size: cover;
    background-position: center;
    box-shadow: 0 16px 35px rgba(15, 23, 42, .18);
}
.profile-page .profile-avatar-preview.has-image {
    color: transparent;
}
.profile-page .form-control {
    min-height: 46px;
}
</style>
@endpush

@section('content')
<div class="container-fluid profile-page">
    <x-ui.page-header
        title="Meu perfil"
        subtitle="Atualize seus dados pessoais, foto, senha e segurança de acesso ao painel."
        eyebrow="Conta administrativa">
        <x-slot:aside>
            <div class="dashboard-note">
                <span class="dashboard-note__label">2FA</span>
                <span class="dashboard-note__value {{ $admin->two_factor_secret ? 'text-success' : 'text-danger' }}">
                    {{ $admin->two_factor_secret ? 'Ativo' : 'Inativo' }}
                </span>
            </div>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Membro desde</span>
                <span class="dashboard-note__value">{{ $admin->created_at?->format('d/m/Y') ?? '-' }}</span>
            </div>
        </x-slot:aside>
    </x-ui.page-header>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6 col-xl-3">
            <x-ui.stats-card label="Último login" :value="$admin->last_login_at ? \Carbon\Carbon::parse($admin->last_login_at)->diffForHumans() : 'Sem registro'" icon="bi-clock-history" color="info">
                <x-slot:meta>Referência do acesso administrativo mais recente.</x-slot:meta>
            </x-ui.stats-card>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <x-ui.stats-card label="E-mail" :value="$admin->email" icon="bi-envelope" color="primary">
                <x-slot:meta>Canal principal de autenticação e avisos.</x-slot:meta>
            </x-ui.stats-card>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <x-ui.stats-card label="Telefone" :value="$admin->phone ? \App\Support\Format::phone($admin->phone) : 'Não informado'" icon="bi-telephone" color="warning">
                <x-slot:meta>Contato administrativo de recuperação e operação.</x-slot:meta>
            </x-ui.stats-card>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <x-ui.stats-card label="Perfis vinculados" :value="$admin->roles->count()" icon="bi-person-badge" color="success">
                <x-slot:meta>Quantidade de papéis associados ao seu acesso.</x-slot:meta>
            </x-ui.stats-card>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <x-ui.premium-card title="Identidade visual" subtitle="Foto, nome e papéis vinculados ao acesso." class="text-center">
                <div class="profile-avatar-preview mx-auto mb-3 {{ $admin->avatar_url ? 'has-image' : '' }}"
                     id="profile-avatar-preview"
                     @if($admin->avatar_url) style="background-image:url('{{ $admin->avatar_url }}')" @endif>
                    @unless($admin->avatar_url)
                        {{ strtoupper(substr($admin->name ?? 'A', 0, 1)) }}
                    @endunless
                </div>
                <h5 class="mb-1">{{ $admin->name }}</h5>
                <p class="text-muted small mb-3">{{ $admin->email }}</p>
                <input type="file" id="profile-avatar-file" class="d-none" accept="image/jpeg,image/png,image/gif,image/webp">
                <button type="button" class="btn btn-outline-primary btn-sm mb-3" id="btn-profile-avatar">
                    <i class="bi bi-camera me-1"></i> Alterar foto
                </button>

                @if($admin->roles->count())
                    <div class="d-flex justify-content-center gap-1 flex-wrap mb-3">
                        @foreach($admin->roles as $role)
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">{{ $role->name }}</span>
                        @endforeach
                    </div>
                @endif

                <div class="small text-muted d-grid gap-2 text-start">
                    <div class="d-flex justify-content-between">
                        <span>Membro desde</span>
                        <strong>{{ $admin->created_at?->format('d/m/Y') ?? '-' }}</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Último login</span>
                        <strong>{{ $admin->last_login_at ? \Carbon\Carbon::parse($admin->last_login_at)->diffForHumans() : '-' }}</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>2FA</span>
                        <strong class="{{ $admin->two_factor_secret ? 'text-success' : 'text-danger' }}">
                            {{ $admin->two_factor_secret ? 'Ativo' : 'Inativo' }}
                        </strong>
                    </div>
                </div>
            </x-ui.premium-card>
        </div>

        <div class="col-lg-8">
            <x-ui.premium-card title="Dados pessoais" subtitle="Informações básicas do usuário administrativo." class="mb-4">
                <form id="frm-profile">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nome completo <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="{{ $admin->name }}" required maxlength="120">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">E-mail <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" value="{{ $admin->email }}" required maxlength="255">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Telefone</label>
                            <input type="text" name="phone" class="form-control" value="{{ $admin->phone ?? '' }}" data-mask="phone" maxlength="20">
                        </div>
                        <input type="hidden" name="avatar_url" id="profile-avatar-url" value="{{ $admin->avatar_url ?? '' }}">
                    </div>
                </form>
                <x-slot:actions>
                    <div class="text-end">
                        <button type="button" class="btn btn-primary" id="btn-save-profile">
                            <i class="bi bi-check2-circle me-1"></i> Salvar dados
                        </button>
                    </div>
                </x-slot:actions>
            </x-ui.premium-card>

            <x-ui.premium-card title="Alterar senha" subtitle="Defina uma nova senha para o acesso administrativo." class="mb-4">
                <form id="frm-password">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Senha atual <span class="text-danger">*</span></label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Nova senha <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" required minlength="8">
                            <small class="text-muted">Mínimo de 8 caracteres.</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Confirmar nova senha <span class="text-danger">*</span></label>
                            <input type="password" name="password_confirmation" class="form-control" required minlength="8">
                        </div>
                    </div>
                </form>
                <x-slot:actions>
                    <div class="text-end">
                        <button type="button" class="btn btn-warning" id="btn-save-password">
                            <i class="bi bi-lock me-1"></i> Alterar senha
                        </button>
                    </div>
                </x-slot:actions>
            </x-ui.premium-card>

            <x-ui.premium-card title="Segurança" subtitle="Gestão da autenticação em dois fatores e proteção da conta.">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div>
                        <h6 class="mb-1">Autenticação em dois fatores (2FA)</h6>
                        <small class="text-muted">Proteja sua conta com um código TOTP gerado pelo Google Authenticator ou app compatível.</small>
                    </div>
                    @if($admin->two_factor_secret)
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-success fs-6 py-2 px-3"><i class="bi bi-check-circle me-1"></i> Ativo</span>
                            <button type="button" class="btn btn-danger btn-sm" id="btn-disable-2fa">
                                <i class="bi bi-shield-x me-1"></i> Desativar
                            </button>
                        </div>
                    @else
                        <a href="{{ url('/admin/2fa') }}" class="btn btn-success btn-sm">
                            <i class="bi bi-shield-plus me-1"></i> Ativar 2FA
                        </a>
                    @endif
                </div>
            </x-ui.premium-card>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const csrf = document.querySelector('meta[name=csrf-token]').content;
const avatarInitial = @json(strtoupper(substr($admin->name ?? 'A', 0, 1)));

function setProfileAvatar(url) {
    const preview = document.getElementById('profile-avatar-preview');
    const input = document.getElementById('profile-avatar-url');
    input.value = url || '';
    if (url) {
        preview.classList.add('has-image');
        preview.style.backgroundImage = `url('${url}')`;
        preview.textContent = '';
        return;
    }
    preview.classList.remove('has-image');
    preview.style.backgroundImage = '';
    preview.textContent = avatarInitial;
}

document.getElementById('btn-profile-avatar')?.addEventListener('click', () => {
    document.getElementById('profile-avatar-file').click();
});

document.getElementById('profile-avatar-file')?.addEventListener('change', async (event) => {
    const file = event.target.files?.[0];
    if (!file) return;

    const btn = document.getElementById('btn-profile-avatar');
    const original = btn.innerHTML;
    const currentUrl = document.getElementById('profile-avatar-url').value;
    const fd = new FormData();
    fd.append('file', file);
    fd.append('owner_type', 'admin_user');
    fd.append('owner_id', '{{ $admin->id }}');
    if (currentUrl) fd.append('replace_url', currentUrl);

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Enviando...';
    try {
        const response = await fetch('{{ route("admin.uploads.store", "avatars") }}', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: fd,
        });
        const data = await response.json();
        if (!response.ok || !data.ok) {
            window.toast.error(data.message || data.error || 'Não foi possível enviar a foto.');
            return;
        }
        setProfileAvatar(data.url);
        window.toast.success('Foto enviada. Salve os dados para aplicar no perfil.');
    } catch (error) {
        window.toast.error('Erro de rede ao enviar a foto.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = original;
        event.target.value = '';
    }
});

document.getElementById('btn-save-profile').addEventListener('click', async () => {
    const fd = new FormData(document.getElementById('frm-profile'));
    const btn = document.getElementById('btn-save-profile');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Salvando...';
    try {
        const r = await fetch('{{ route("admin.profile.update") }}', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: fd,
        });
        const d = await r.json();
        if (r.ok && d.ok) window.toast.success('Perfil atualizado.');
        else window.toast.error(d.message || 'Erro ao salvar.');
    } catch (err) { window.toast.error('Erro de rede.'); }
    finally { btn.disabled = false; btn.innerHTML = orig; }
});

document.getElementById('btn-save-password').addEventListener('click', async () => {
    const fd = new FormData(document.getElementById('frm-password'));
    const btn = document.getElementById('btn-save-password');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Alterando...';
    try {
        const r = await fetch('{{ route("admin.profile.password") }}', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: fd,
        });
        const d = await r.json();
        if (r.ok && d.ok) {
            window.toast.success('Senha alterada com sucesso.');
            document.getElementById('frm-password').reset();
        } else {
            window.toast.error(d.message || 'Erro ao alterar senha.');
        }
    } catch (err) { window.toast.error('Erro de rede.'); }
    finally { btn.disabled = false; btn.innerHTML = orig; }
});

document.getElementById('btn-disable-2fa')?.addEventListener('click', async () => {
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
        inputValidator: (value) => !value ? 'Informe sua senha atual.' : null,
    });

    if (!result.isConfirmed) return;

    try {
        const r = await fetch('{{ route("admin.2fa.disable") }}', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ password: result.value }),
        });
        const d = await r.json();
        if (r.ok && d.ok) {
            window.toast.success(d.message || '2FA desativado.');
            setTimeout(() => window.location.reload(), 600);
            return;
        }
        window.toast.error(d.error || d.message || 'Não foi possível desativar o 2FA.');
    } catch (err) {
        window.toast.error('Erro de rede.');
    }
});
</script>
@endpush
