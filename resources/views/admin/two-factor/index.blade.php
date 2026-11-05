@extends('admin.layouts.app')
@section('title', 'Autenticação em dois fatores')

@section('content')
<div class="container-fluid">
    <x-ui.page-header
        title="Autenticação em dois fatores (2FA)"
        subtitle="Proteja sua conta administrativa com TOTP usando Google Authenticator, Authy ou app compatível."
        eyebrow="Segurança de acesso">
        <x-slot:actions>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Voltar ao painel
            </a>
        </x-slot:actions>
        <x-slot:aside>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Status</span>
                <span class="dashboard-note__value {{ $enabled ? 'text-success' : 'text-warning' }}">
                    {{ $enabled ? 'Ativo' : 'Pendente' }}
                </span>
            </div>
        </x-slot:aside>
    </x-ui.page-header>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6 col-xl-4">
            <x-ui.stats-card label="Camada extra" :value="$enabled ? 'Habilitada' : 'Desabilitada'" icon="bi-shield-lock" :color="$enabled ? 'success' : 'warning'">
                <x-slot:meta>Confirmação adicional exigida no login administrativo.</x-slot:meta>
            </x-ui.stats-card>
        </div>
        <div class="col-12 col-md-6 col-xl-4">
            <x-ui.stats-card label="App compatível" value="TOTP padrão" icon="bi-phone" color="info">
                <x-slot:meta>Google Authenticator, Authy e similares.</x-slot:meta>
            </x-ui.stats-card>
        </div>
        <div class="col-12 col-md-6 col-xl-4">
            <x-ui.stats-card label="Recuperação" value="Códigos únicos" icon="bi-key" color="primary">
                <x-slot:meta>Gerados após ativação para uso emergencial.</x-slot:meta>
            </x-ui.stats-card>
        </div>
    </div>

    <div class="row g-4 justify-content-center">
        <div class="col-lg-8 col-xl-6">
            @if($enabled)
                <x-ui.premium-card title="2FA ativo" subtitle="Sua conta já está protegida com autenticação em dois fatores.">
                    <div class="text-center py-4">
                        <div class="rounded-circle bg-success-subtle text-success d-flex align-items-center justify-content-center mx-auto mb-3" style="width:80px;height:80px;font-size:2.5rem;">
                            <i class="bi bi-shield-fill-check"></i>
                        </div>
                        <h4 class="text-success mb-2">2FA está ativo</h4>
                        <p class="text-muted mb-4">Um código será solicitado a cada login administrativo.</p>

                        <div class="alert alert-warning border-0 text-start mx-auto" style="max-width: 400px;">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            <strong>Desativar 2FA</strong> remove a camada extra de segurança. Faça isso apenas se for necessário.
                        </div>

                        <form id="frm-disable" class="mx-auto" style="max-width: 350px;">
                            @csrf
                            <div class="mb-3 text-start">
                                <label class="form-label">Confirme sua senha para desativar</label>
                                <input type="password" name="password" class="form-control" required placeholder="Sua senha atual">
                            </div>
                            <button type="submit" class="btn btn-danger w-100">
                                <i class="bi bi-shield-x me-1"></i> Desativar 2FA
                            </button>
                        </form>
                    </div>
                </x-ui.premium-card>
            @else
                <x-ui.premium-card title="Ativar 2FA" subtitle="Siga os passos abaixo para concluir a configuração.">
                    <div id="step-1">
                        <div class="text-center py-4">
                            <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center mx-auto mb-3" style="width:64px;height:64px;font-size:1.8rem;">
                                <i class="bi bi-qr-code"></i>
                            </div>
                            <h5 class="mb-2">Passo 1: gerar QR Code</h5>
                            <p class="text-muted small mb-4">Clique no botão abaixo para gerar o QR Code e escanear com seu app autenticador.</p>
                            <button type="button" class="btn btn-success" id="btn-generate">
                                <i class="bi bi-shield-plus me-1"></i> Ativar 2FA
                            </button>
                        </div>
                    </div>

                    <div id="step-2" class="d-none">
                        <div class="text-center">
                            <h5 class="mb-3">Passo 2: escaneie o QR Code</h5>
                            <p class="text-muted small mb-3">Abra o Google Authenticator, Authy ou app equivalente e escaneie o código abaixo.</p>

                            <div class="border rounded p-3 d-inline-block bg-white mb-3">
                                <div id="qr-canvas" style="width:220px;height:220px;display:flex;align-items:center;justify-content:center;"></div>
                            </div>

                            <div class="mx-auto" style="max-width: 400px;">
                                <div class="alert alert-info border-0 text-start small">
                                    <i class="bi bi-info-circle me-1"></i>
                                    <strong>Não consegue escanear?</strong> Use a chave manual:
                                    <div class="mt-1">
                                        <code id="manual-secret" class="user-select-all fs-6"></code>
                                        <button type="button" class="btn btn-sm btn-link p-0 ms-2" onclick="navigator.clipboard.writeText(document.getElementById('manual-secret').textContent);window.toast.success('Chave copiada.');">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="text-center">
                            <h5 class="mb-3">Passo 3: confirme o código</h5>
                            <p class="text-muted small mb-3">Digite o código de 6 dígitos exibido no app autenticador.</p>

                            <form id="frm-confirm" class="mx-auto" style="max-width: 300px;">
                                @csrf
                                <div class="mb-3">
                                    <input type="text" name="code" class="form-control form-control-lg text-center font-monospace"
                                           maxlength="6" pattern="[0-9]{6}" required placeholder="000000"
                                           style="letter-spacing: .5em; font-size: 1.5rem;"
                                           autocomplete="one-time-code" inputmode="numeric">
                                </div>
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="bi bi-check2-circle me-1"></i> Confirmar e ativar
                                </button>
                            </form>
                        </div>
                    </div>

                    <div id="step-3" class="d-none">
                        <div class="text-center">
                            <div class="rounded-circle bg-success-subtle text-success d-flex align-items-center justify-content-center mx-auto mb-3" style="width:64px;height:64px;font-size:1.8rem;">
                                <i class="bi bi-check-lg"></i>
                            </div>
                            <h5 class="text-success mb-3">2FA ativado com sucesso</h5>
                            <p class="text-muted small mb-3">Guarde os códigos de recuperação abaixo em local seguro. Cada código pode ser usado uma única vez.</p>

                            <div class="border rounded p-3 bg-body-tertiary mx-auto mb-3" style="max-width: 350px;">
                                <div id="recovery-codes" class="font-monospace small text-start"></div>
                            </div>

                            <div class="d-flex gap-2 justify-content-center">
                                <button type="button" class="btn btn-outline-primary btn-sm" id="btn-copy-codes">
                                    <i class="bi bi-clipboard me-1"></i> Copiar códigos
                                </button>
                                <a href="{{ route('admin.dashboard') }}" class="btn btn-success btn-sm">
                                    <i class="bi bi-check2 me-1"></i> Concluir
                                </a>
                            </div>
                        </div>
                    </div>
                </x-ui.premium-card>

                <x-ui.premium-card title="Como funciona" subtitle="Resumo operacional do processo." class="mt-4">
                    <ol class="small ps-3 mb-0 text-muted">
                        <li class="mb-1">Instale o Google Authenticator, Authy ou app equivalente.</li>
                        <li class="mb-1">Gere o QR Code e escaneie com o aplicativo.</li>
                        <li class="mb-1">Digite o código de 6 dígitos para confirmar.</li>
                        <li class="mb-1">Guarde os códigos de recuperação em local seguro.</li>
                        <li>A cada login, será solicitado o código temporário do app.</li>
                    </ol>
                </x-ui.premium-card>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
const csrf = document.querySelector('meta[name=csrf-token]').content;

@if(!$enabled)
let qrInstance = null;

document.getElementById('btn-generate').addEventListener('click', async () => {
    const btn = document.getElementById('btn-generate');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Gerando...';
    try {
        const r = await fetch('{{ route("admin.2fa.setup") }}', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        });
        const d = await r.json();
        if (d.ok) {
            const canvas = document.getElementById('qr-canvas');
            canvas.innerHTML = '';
            qrInstance = new QRCode(canvas, {
                text: d.qr_url,
                width: 200,
                height: 200,
                colorDark: '#000000',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.M,
            });
            document.getElementById('manual-secret').textContent = d.secret;
            document.getElementById('step-1').classList.add('d-none');
            document.getElementById('step-2').classList.remove('d-none');
        } else {
            window.toast.error(d.error || 'Erro ao gerar QR.');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-shield-plus me-1"></i> Ativar 2FA';
        }
    } catch (err) {
        window.toast.error('Erro de rede.');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-shield-plus me-1"></i> Ativar 2FA';
    }
});

document.getElementById('frm-confirm').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const btn = e.target.querySelector('button[type=submit]');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Verificando...';
    try {
        const r = await fetch('{{ route("admin.2fa.confirm") }}', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: fd,
        });
        const d = await r.json();
        if (d.ok) {
            const codes = (d.recovery_codes || []).map(c => `<div class="py-1 border-bottom">${c}</div>`).join('');
            document.getElementById('recovery-codes').innerHTML = codes;
            document.getElementById('step-2').classList.add('d-none');
            document.getElementById('step-3').classList.remove('d-none');
            window.toast.success('2FA ativado.');
        } else {
            window.toast.error(d.error || 'Código inválido.');
            btn.disabled = false;
            btn.innerHTML = orig;
        }
    } catch (err) {
        window.toast.error('Erro de rede.');
        btn.disabled = false;
        btn.innerHTML = orig;
    }
});

document.getElementById('btn-copy-codes')?.addEventListener('click', () => {
    const codes = document.getElementById('recovery-codes').textContent;
    navigator.clipboard.writeText(codes);
    window.toast.success('Códigos copiados.');
});
@endif

@if($enabled)
document.getElementById('frm-disable').addEventListener('submit', async e => {
    e.preventDefault();
    const ok = await Swal.fire({
        icon: 'warning', title: 'Desativar 2FA?',
        text: 'Sua conta ficará menos protegida. Tem certeza?',
        showCancelButton: true, confirmButtonText: 'Sim, desativar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545'
    });
    if (!ok.isConfirmed) return;
    const fd = new FormData(e.target);
    try {
        const r = await fetch('{{ route("admin.2fa.disable") }}', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: fd,
        });
        const d = await r.json();
        if (d.ok) { window.toast.success('2FA desativado.'); setTimeout(() => location.reload(), 800); }
        else window.toast.error(d.error || 'Erro ao desativar.');
    } catch (err) { window.toast.error('Erro de rede.'); }
});
@endif
</script>
@endpush
