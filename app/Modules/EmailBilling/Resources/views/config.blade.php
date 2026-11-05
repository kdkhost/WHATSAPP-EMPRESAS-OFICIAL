@extends(request()->is('painel*') ? 'tenancy::tenant.layout' : 'admin.layouts.app')
@section('title', 'Configurar SMTP')

@php
    $brandSettings = is_array($config->settings) ? $config->settings : [];
    $emailBrandName = $brandSettings['brand_name'] ?? $config->from_name ?? ($tenant->company_name ?? config('app.name'));
    $emailLogoUrl = $brandSettings['logo_url'] ?? $settingsManager->get('app.logo');
    $emailPrimaryColor = $brandSettings['primary_color'] ?? $settingsManager->get('theme.primary', '#0d6efd');
    $emailAccentColor = $brandSettings['accent_color'] ?? $settingsManager->get('theme.secondary', '#6610f2');
    $emailFooterText = $brandSettings['footer_text'] ?? ($settingsManager->get('app.footer_text') ?: 'Esta mensagem faz parte da comunicacao oficial do sistema.');
    $emailFooterSignature = $brandSettings['footer_signature'] ?? $emailBrandName;
    $emailFooterSubtext = $brandSettings['footer_subtext'] ?? 'Mensagem transacional enviada automaticamente pelo sistema.';
    $statusTone = 'secondary';
    $statusIcon = 'pause-circle';
    $statusLabel = 'Desabilitado';
    $statusHint = 'Nenhum envio por email sera processado enquanto a conta estiver desligada.';

    if ($config->enabled && $config->last_test_status === 'ok') {
        $statusTone = 'success';
        $statusIcon = 'check-circle-fill';
        $statusLabel = 'Operacional';
        $statusHint = 'A configuracao foi validada e esta pronta para envio.';
    } elseif ($config->enabled) {
        $statusTone = 'warning';
        $statusIcon = 'exclamation-triangle-fill';
        $statusLabel = 'Aguardando teste';
        $statusHint = 'A conta esta ativa, mas ainda precisa de validacao real de envio.';
    }
@endphp

@section('content')
<div class="container-fluid email-config-page">
    <div class="email-hero mb-4">
        <div class="email-hero__content">
            <div class="email-hero__eyebrow">
                <i class="bi bi-envelope-paper-heart me-2"></i>Canal de cobranca por email
            </div>
            <h1 class="email-hero__title">Entrega profissional, reputacao protegida e configuracao sob controle.</h1>
            <p class="email-hero__text">Cada tenant envia com a propria infraestrutura. Isso reduz bloqueios, evita abuso compartilhado e preserva a reputacao do dominio de envio.</p>
        </div>
        <div class="email-hero__stats">
            <div class="hero-stat hero-stat--{{ $statusTone }}">
                <span class="hero-stat__label">Status atual</span>
                <strong class="hero-stat__value"><i class="bi bi-{{ $statusIcon }} me-2"></i>{{ $statusLabel }}</strong>
                <span class="hero-stat__hint">{{ $statusHint }}</span>
            </div>
            <div class="hero-stat">
                <span class="hero-stat__label">Driver selecionado</span>
                <strong class="hero-stat__value">{{ strtoupper($config->driver ?? 'smtp') }}</strong>
                <span class="hero-stat__hint">Troque conforme o provedor real de envio desta conta.</span>
            </div>
            <div class="hero-stat">
                <span class="hero-stat__label">Ultimo teste</span>
                <strong class="hero-stat__value">{{ $config->last_test_at ? $config->last_test_at->format('d/m/Y H:i') : 'Nao realizado' }}</strong>
                <span class="hero-stat__hint">{{ $config->last_test_at ? $config->last_test_at->diffForHumans() : 'Execute um teste antes de liberar a operacao.' }}</span>
            </div>
        </div>
    </div>

    <form id="frm-cfg">
        @csrf
        <div class="row g-4">
            <div class="col-xl-8">
                <div class="premium-panel mb-4">
                    <div class="premium-panel__header">
                        <div>
                            <div class="premium-panel__kicker">Ativacao</div>
                            <h3 class="premium-panel__title">Governanca do canal</h3>
                            <p class="premium-panel__subtitle">Ative apenas quando host, autenticacao e remetente estiverem consistentes.</p>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input premium-switch" type="checkbox" name="enabled" id="enabled" value="1" @checked($config->enabled)>
                        </div>
                    </div>
                    <div class="premium-panel__body">
                        <div class="switch-card">
                            <div>
                                <div class="switch-card__title">Permitir envio de cobrancas, contratos e comunicacoes por email</div>
                                <div class="switch-card__text">Quando desligado, o tenant continua operando nos demais canais, mas nenhuma fila de email sera executada.</div>
                            </div>
                            <span class="switch-card__badge switch-card__badge--{{ $config->enabled ? 'success' : 'muted' }}">{{ $config->enabled ? 'Ativo' : 'Inativo' }}</span>
                        </div>
                    </div>
                </div>

                <div class="premium-panel mb-4">
                    <div class="premium-panel__header">
                        <div>
                            <div class="premium-panel__kicker">Transporte</div>
                            <h3 class="premium-panel__title">Provedor de envio</h3>
                            <p class="premium-panel__subtitle">Escolha o mesmo canal que o tenant usa hoje na operacao real.</p>
                        </div>
                    </div>
                    <div class="premium-panel__body">
                        <div class="driver-grid">
                            @foreach([
                                'smtp'      => ['SMTP Padrao', 'bi-hdd-network', 'Hospedagem, Gmail, Outlook e SMTP dedicado'],
                                'sendgrid'  => ['SendGrid', 'bi-send', 'Entrega via API com boa reputacao transacional'],
                                'mailgun'   => ['Mailgun', 'bi-mailbox2-flag', 'Operacao por dominio autenticado'],
                                'ses'       => ['Amazon SES', 'bi-cloud-check', 'Escala alta com reputacao controlada'],
                            ] as $k => [$label, $icon, $hint])
                                <div class="driver-card">
                                    <input type="radio" class="btn-check" name="driver" id="drv-{{ $k }}" value="{{ $k }}" @checked(($config->driver ?? 'smtp') === $k)>
                                    <label for="drv-{{ $k }}" class="driver-card__label">
                                        <span class="driver-card__icon"><i class="bi {{ $icon }}"></i></span>
                                        <span class="driver-card__title">{{ $label }}</span>
                                        <span class="driver-card__hint">{{ $hint }}</span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="premium-panel mb-4" id="smtp-fields">
                    <div class="premium-panel__header">
                        <div>
                            <div class="premium-panel__kicker">SMTP</div>
                            <h3 class="premium-panel__title">Host e autenticacao</h3>
                            <p class="premium-panel__subtitle">Use as credenciais do proprio tenant. Nada de envio compartilhado.</p>
                        </div>
                    </div>
                    <div class="premium-panel__body">
                        <div class="row g-3">
                            <div class="col-md-7">
                                <label class="form-label">Host SMTP</label>
                                <input name="host" class="form-control form-control-lg" value="{{ $config->host }}" placeholder="smtp.gmail.com">
                                <small class="text-muted">Ex.: smtp.gmail.com, smtp.hostinger.com, smtp-mail.outlook.com</small>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Porta</label>
                                <input type="number" name="port" class="form-control form-control-lg" value="{{ $config->port ?? 587 }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Criptografia</label>
                                <select name="encryption" class="form-select form-select-lg">
                                    <option value="tls" @selected(($config->encryption ?? 'tls') === 'tls')>TLS</option>
                                    <option value="ssl" @selected($config->encryption === 'ssl')>SSL</option>
                                    <option value="none" @selected($config->encryption === 'none')>Nenhuma</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Usuario</label>
                                <input name="username" class="form-control" value="{{ $config->username }}" placeholder="financeiro@suaempresa.com">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Senha</label>
                                <div class="input-group input-group-premium">
                                    <input type="password" name="password" id="smtp-password" class="form-control"
                                           placeholder="{{ $config->password ? '••••••••' : 'Senha ou app password' }}">
                                    <button type="button" class="btn btn-outline-secondary" id="toggle-password" tabindex="-1">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <small class="text-muted">Se ficar em branco, a senha atual e mantida.</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="premium-panel mb-4" id="api-fields" style="display:none;">
                    <div class="premium-panel__header">
                        <div>
                            <div class="premium-panel__kicker">API</div>
                            <h3 class="premium-panel__title">Chave do provedor</h3>
                            <p class="premium-panel__subtitle">Use a chave exclusiva da conta do tenant.</p>
                        </div>
                    </div>
                    <div class="premium-panel__body">
                        <label class="form-label">API Key</label>
                        <div class="input-group input-group-premium">
                            <input type="password" name="api_key" id="api-key" class="form-control"
                                   placeholder="{{ $config->api_key ? '••••••••' : 'Sua API key' }}">
                            <button type="button" class="btn btn-outline-secondary" id="toggle-apikey" tabindex="-1">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <small class="text-muted">Se ficar em branco, a chave atual e mantida.</small>
                    </div>
                </div>

                <div class="premium-panel mb-4">
                    <div class="premium-panel__header">
                        <div>
                            <div class="premium-panel__kicker">Identidade</div>
                            <h3 class="premium-panel__title">Remetente e retorno</h3>
                            <p class="premium-panel__subtitle">Esses dados sao os que o cliente final vai ver na caixa de entrada.</p>
                        </div>
                    </div>
                    <div class="premium-panel__body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Email remetente <span class="text-danger">*</span></label>
                                <input type="email" name="from_email" class="form-control form-control-lg" required value="{{ $config->from_email }}" placeholder="noreply@suaempresa.com">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nome remetente <span class="text-danger">*</span></label>
                                <input name="from_name" class="form-control form-control-lg" required maxlength="120" value="{{ $config->from_name }}" placeholder="Financeiro Minha Empresa">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Reply-To</label>
                                <input type="email" name="reply_to" class="form-control" value="{{ $config->reply_to }}" placeholder="suporte@suaempresa.com">
                                <small class="text-muted">Email que recebera respostas do destinatario.</small>
                            </div>
                        </div>
                    </div>
                    <div class="premium-panel__footer">
                        <button type="button" class="btn btn-outline-info" id="btn-test-2">
                            <i class="bi bi-send-check me-1"></i>Testar conexao
                        </button>
                        <button type="submit" class="btn btn-primary" id="btn-save">
                            <i class="bi bi-check2-circle me-1"></i>Salvar configuracao
                        </button>
                    </div>
                </div>

                <div class="premium-panel mb-4">
                    <div class="premium-panel__header">
                        <div>
                            <div class="premium-panel__kicker">Layout obrigatorio</div>
                            <h3 class="premium-panel__title">Padrao visual do email</h3>
                            <p class="premium-panel__subtitle">Cor, logo e rodape ficam separados do conteudo do template e sao aplicados em todos os emails disparados pelo sistema.</p>
                        </div>
                    </div>
                    <div class="premium-panel__body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nome da marca no email</label>
                                <input type="text" name="settings[brand_name]" id="email-brand-name" class="form-control" maxlength="120" value="{{ $emailBrandName }}" placeholder="Minha Empresa">
                                <small class="text-muted">Se ficar em branco, usa o nome do remetente ou da empresa.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Logo do email</label>
                                <input type="hidden" name="settings[logo_url]" id="email-logo-url" value="{{ $emailLogoUrl }}">
                                <div class="email-logo-uploader" data-upload data-url="{{ request()->is('painel*') ? route('tenant.uploads.store', 'branding') : route('admin.uploads.store', 'branding') }}" data-max-mb="5" data-owner-type="email_branding">
                                    <input type="file" accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml,image/x-icon" hidden>
                                    <div class="email-logo-uploader__inner" data-upload-trigger>
                                        <div class="email-logo-uploader__preview">
                                            <img src="{{ $emailLogoUrl }}" alt="Logo do email" id="email-logo-upload-preview" class="{{ $emailLogoUrl ? '' : 'd-none' }}">
                                            <i class="bi bi-cloud-arrow-up {{ $emailLogoUrl ? 'd-none' : '' }}" id="email-logo-upload-icon"></i>
                                        </div>
                                        <div class="email-logo-uploader__copy">
                                            <strong>Arraste a logo aqui ou clique para enviar</strong>
                                            <span>PNG, JPG, WebP, SVG ou ICO ate 5 MB.</span>
                                        </div>
                                    </div>
                                    <div data-upload-progress class="email-logo-uploader__progress"></div>
                                </div>
                                <div class="d-flex align-items-center justify-content-between gap-2 mt-2">
                                    <small class="text-muted text-truncate" id="email-logo-url-label">{{ $emailLogoUrl ?: 'Se nao enviar uma logo, sera usada a logo principal do sistema.' }}</small>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="email-logo-clear">
                                        <i class="bi bi-x-lg me-1"></i>Limpar
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Cor principal</label>
                                <div class="input-group">
                                    <input type="color" id="email-primary-picker" class="form-control form-control-color" value="{{ $emailPrimaryColor }}">
                                    <input type="text" name="settings[primary_color]" id="email-primary-color" class="form-control" maxlength="7" value="{{ $emailPrimaryColor }}">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Cor de apoio</label>
                                <div class="input-group">
                                    <input type="color" id="email-accent-picker" class="form-control form-control-color" value="{{ $emailAccentColor }}">
                                    <input type="text" name="settings[accent_color]" id="email-accent-color" class="form-control" maxlength="7" value="{{ $emailAccentColor }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Assinatura do rodape</label>
                                <input type="text" name="settings[footer_signature]" id="email-footer-signature" class="form-control" maxlength="120" value="{{ $emailFooterSignature }}" placeholder="Financeiro Minha Empresa">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Texto principal do rodape</label>
                                <textarea name="settings[footer_text]" id="email-footer-text" class="form-control" rows="3" maxlength="500" placeholder="Mensagem institucional do rodape.">{{ $emailFooterText }}</textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Texto secundario do rodape</label>
                                <input type="text" name="settings[footer_subtext]" id="email-footer-subtext" class="form-control" maxlength="255" value="{{ $emailFooterSubtext }}" placeholder="Mensagem transacional enviada automaticamente pelo sistema.">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="premium-sidecard mb-4">
                    <div class="premium-sidecard__head">
                        <span class="premium-sidecard__kicker">Envelope padrao</span>
                        <h4>Preview obrigatorio</h4>
                    </div>
                    <div class="premium-sidecard__body">
                        <div class="email-shell-preview">
                            <div class="email-shell-preview__header" id="email-shell-header" style="background:linear-gradient(135deg, {{ $emailPrimaryColor }}, {{ $emailAccentColor }});">
                                <div class="email-shell-preview__brand">
                                    <img src="{{ $emailLogoUrl }}" alt="Logo" id="email-shell-logo" class="{{ $emailLogoUrl ? '' : 'd-none' }}">
                                    <div class="email-shell-preview__fallback {{ $emailLogoUrl ? 'd-none' : '' }}" id="email-shell-fallback">{{ \Illuminate\Support\Str::substr($emailBrandName, 0, 1) }}</div>
                                    <div class="email-shell-preview__brand-copy">
                                        <strong id="email-shell-brand-name">{{ $emailBrandName }}</strong>
                                        <span>Comunicacao oficial</span>
                                    </div>
                                </div>
                            </div>
                            <div class="email-shell-preview__body">
                                <div class="email-shell-preview__eyebrow">Template editavel</div>
                                <h5>O conteudo do template entra aqui.</h5>
                                <p>O header, as cores e o rodape seguem o padrao global desta conta. O texto do template continua editavel separadamente.</p>
                            </div>
                            <div class="email-shell-preview__footer">
                                <strong id="email-shell-signature">{{ $emailFooterSignature }}</strong>
                                <p id="email-shell-footer-text">{{ $emailFooterText }}</p>
                                <span id="email-shell-footer-subtext">{{ $emailFooterSubtext }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="premium-sidecard mb-4">
                    <div class="premium-sidecard__head">
                        <span class="premium-sidecard__kicker">Saude da conta</span>
                        <h4>Status operacional</h4>
                    </div>
                    <div class="premium-sidecard__body">
                        <div class="status-tile status-tile--{{ $statusTone }}">
                            <div class="status-tile__icon"><i class="bi bi-{{ $statusIcon }}"></i></div>
                            <div>
                                <strong>{{ $statusLabel }}</strong>
                                <p>{{ $statusHint }}</p>
                            </div>
                        </div>

                        @if($config->last_test_at)
                            <div class="test-report test-report--{{ $config->last_test_status === 'ok' ? 'success' : 'danger' }}">
                                <div class="test-report__title">
                                    <i class="bi bi-{{ $config->last_test_status === 'ok' ? 'check-circle-fill' : 'x-circle-fill' }} me-2"></i>
                                    Ultimo teste {{ $config->last_test_status === 'ok' ? 'aprovado' : 'com falha' }}
                                </div>
                                <div class="test-report__meta">{{ $config->last_test_at->format('d/m/Y H:i') }} • {{ $config->last_test_at->diffForHumans() }}</div>
                                @if($config->last_test_error)
                                    <pre class="test-report__error">{{ \Illuminate\Support\Str::limit($config->last_test_error, 320) }}</pre>
                                @endif
                            </div>
                        @else
                            <div class="empty-state">
                                <i class="bi bi-clock-history"></i>
                                <p>Nenhum teste real executado ainda. Valide o envio antes de liberar a conta.</p>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="premium-sidecard mb-4">
                    <div class="premium-sidecard__head">
                        <span class="premium-sidecard__kicker">Boas praticas</span>
                        <h4>Configuracoes recomendadas</h4>
                    </div>
                    <div class="premium-sidecard__body">
                        <div class="tip-list">
                            <div class="tip-item"><i class="bi bi-google"></i><div><strong>Gmail:</strong> `smtp.gmail.com`, porta `587`, TLS e App Password.</div></div>
                            <div class="tip-item"><i class="bi bi-microsoft"></i><div><strong>Outlook:</strong> `smtp-mail.outlook.com`, porta `587`, TLS.</div></div>
                            <div class="tip-item"><i class="bi bi-server"></i><div><strong>Hostinger:</strong> `smtp.hostinger.com`, porta `587`, TLS.</div></div>
                            <div class="tip-item"><i class="bi bi-shield-lock"></i><div><strong>Seguranca:</strong> senha e API key ficam protegidas no banco; deixe vazio para manter o valor salvo.</div></div>
                        </div>
                    </div>
                </div>

                <div class="premium-sidecard">
                    <div class="premium-sidecard__head">
                        <span class="premium-sidecard__kicker">Operacao</span>
                        <h4>Politica do sistema</h4>
                    </div>
                    <div class="premium-sidecard__body">
                        <div class="policy-note">
                            <i class="bi bi-stars"></i>
                            <div>
                                <strong>Email e WhatsApp podem operar juntos.</strong>
                                <p>O canal de disparo continua definido por template e fila. A configuracao desta tela so habilita a infraestrutura do tenant.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('head')
<style>
.email-config-page{--email-border:rgba(15,23,42,.08);--email-soft:color-mix(in srgb,var(--bs-primary) 8%,transparent)}
.email-hero{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(320px,1fr);gap:1.25rem;padding:1.4rem;border:1px solid var(--email-border);border-radius:18px;background:
linear-gradient(135deg,color-mix(in srgb,var(--bs-primary) 10%,transparent),transparent 40%),
linear-gradient(180deg,var(--bs-body-bg),color-mix(in srgb,var(--bs-body-bg) 88%,var(--bs-primary) 12%))}
.email-hero__eyebrow{display:inline-flex;align-items:center;padding:.45rem .75rem;border-radius:999px;background:var(--email-soft);font-size:.78rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:var(--bs-primary);margin-bottom:.9rem}
.email-hero__title{font-size:1.9rem;line-height:1.1;margin:0 0 .75rem}
.email-hero__text{max-width:58rem;color:var(--bs-secondary-color);margin:0}
.email-hero__stats{display:grid;gap:.9rem}
.hero-stat{padding:1rem 1.05rem;border-radius:16px;border:1px solid var(--email-border);background:var(--bs-body-bg)}
.hero-stat--success{background:color-mix(in srgb,var(--bs-success) 9%,var(--bs-body-bg));border-color:color-mix(in srgb,var(--bs-success) 20%,transparent)}
.hero-stat--warning{background:color-mix(in srgb,var(--bs-warning) 12%,var(--bs-body-bg));border-color:color-mix(in srgb,var(--bs-warning) 26%,transparent)}
.hero-stat--secondary{background:color-mix(in srgb,var(--bs-secondary) 10%,var(--bs-body-bg))}
.hero-stat__label,.premium-panel__kicker,.premium-sidecard__kicker{display:block;font-size:.74rem;letter-spacing:.08em;text-transform:uppercase;color:var(--bs-secondary-color);margin-bottom:.3rem}
.hero-stat__value{display:block;font-size:1.02rem}
.hero-stat__hint{display:block;color:var(--bs-secondary-color);font-size:.82rem;margin-top:.3rem;line-height:1.5}
.premium-panel,.premium-sidecard{border:1px solid var(--email-border);border-radius:18px;background:var(--bs-body-bg);overflow:hidden;box-shadow:0 14px 35px rgba(15,23,42,.05)}
.premium-panel__header,.premium-sidecard__head{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;padding:1.15rem 1.2rem;border-bottom:1px solid var(--email-border)}
.premium-panel__title{margin:0;font-size:1.08rem}
.premium-panel__subtitle,.switch-card__text,.status-tile p,.policy-note p,.empty-state p{margin:0;color:var(--bs-secondary-color);line-height:1.55}
.premium-panel__body,.premium-sidecard__body{padding:1.2rem}
.premium-panel__footer{display:flex;justify-content:flex-end;gap:.75rem;padding:1rem 1.2rem;border-top:1px solid var(--email-border);background:color-mix(in srgb,var(--bs-body-bg) 85%,var(--bs-primary) 15%)}
.premium-switch{width:3.1rem;height:1.7rem}
.switch-card{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1rem 1.1rem;border-radius:16px;border:1px solid var(--email-border);background:color-mix(in srgb,var(--bs-body-bg) 88%,var(--bs-primary) 12%)}
.switch-card__title{font-weight:700;margin-bottom:.2rem}
.switch-card__badge{padding:.45rem .7rem;border-radius:999px;font-size:.75rem;font-weight:700}
.switch-card__badge--success{background:color-mix(in srgb,var(--bs-success) 15%,transparent);color:var(--bs-success)}
.switch-card__badge--muted{background:color-mix(in srgb,var(--bs-secondary) 15%,transparent);color:var(--bs-secondary)}
.driver-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}
.driver-card__label{display:flex;flex-direction:column;gap:.45rem;height:100%;padding:1rem;border:1px solid var(--email-border);border-radius:16px;background:var(--bs-body-bg);transition:.18s ease;text-align:left}
.btn-check:checked + .driver-card__label{border-color:color-mix(in srgb,var(--bs-primary) 45%,transparent);background:color-mix(in srgb,var(--bs-primary) 8%,transparent);box-shadow:0 0 0 2px color-mix(in srgb,var(--bs-primary) 12%,transparent)}
.driver-card__icon{width:42px;height:42px;display:inline-flex;align-items:center;justify-content:center;border-radius:12px;background:color-mix(in srgb,var(--bs-primary) 12%,transparent);color:var(--bs-primary);font-size:1.15rem}
.driver-card__title{font-weight:700}
.driver-card__hint{font-size:.82rem;color:var(--bs-secondary-color);line-height:1.45}
.input-group-premium .btn{min-width:48px}
.status-tile,.policy-note{display:flex;gap:.9rem;align-items:flex-start;padding:1rem;border-radius:16px;border:1px solid var(--email-border);background:color-mix(in srgb,var(--bs-body-bg) 86%,var(--bs-primary) 14%)}
.status-tile--success{background:color-mix(in srgb,var(--bs-success) 10%,var(--bs-body-bg));border-color:color-mix(in srgb,var(--bs-success) 20%,transparent)}
.status-tile--warning{background:color-mix(in srgb,var(--bs-warning) 10%,var(--bs-body-bg));border-color:color-mix(in srgb,var(--bs-warning) 20%,transparent)}
.status-tile--secondary{background:color-mix(in srgb,var(--bs-secondary) 10%,var(--bs-body-bg))}
.status-tile__icon,.policy-note i,.empty-state i{width:42px;height:42px;display:inline-flex;align-items:center;justify-content:center;border-radius:12px;background:color-mix(in srgb,var(--bs-primary) 15%,transparent);color:var(--bs-primary);font-size:1.15rem;flex-shrink:0}
.test-report{margin-top:1rem;padding:1rem;border-radius:16px;border:1px solid var(--email-border)}
.test-report--success{background:color-mix(in srgb,var(--bs-success) 8%,var(--bs-body-bg));border-color:color-mix(in srgb,var(--bs-success) 18%,transparent)}
.test-report--danger{background:color-mix(in srgb,var(--bs-danger) 8%,var(--bs-body-bg));border-color:color-mix(in srgb,var(--bs-danger) 18%,transparent)}
.test-report__title{font-weight:700;margin-bottom:.35rem}
.test-report__meta{font-size:.82rem;color:var(--bs-secondary-color)}
.test-report__error{margin: .85rem 0 0;white-space:pre-wrap;font-size:.77rem;color:var(--bs-danger);background:transparent}
.tip-list{display:flex;flex-direction:column;gap:.85rem}
.tip-item{display:flex;gap:.8rem;align-items:flex-start;font-size:.9rem;line-height:1.55}
.tip-item i{width:20px;margin-top:.2rem;color:var(--bs-primary)}
.empty-state{padding:1rem;border:1px dashed var(--email-border);border-radius:16px;text-align:left}
.email-shell-preview{border:1px solid var(--email-border);border-radius:18px;overflow:hidden;background:var(--bs-body-bg);box-shadow:0 12px 30px rgba(15,23,42,.06)}
.email-shell-preview__header{padding:18px}
.email-shell-preview__brand{padding:14px 16px;border-radius:14px;background:rgba(15,23,42,.16);border:1px solid rgba(255,255,255,.2);min-height:82px;display:flex;align-items:center;justify-content:flex-start;gap:.9rem;backdrop-filter:blur(8px)}
.email-shell-preview__brand img{width:54px;height:54px;max-width:54px;max-height:54px;object-fit:contain;border-radius:14px;flex-shrink:0}
.email-shell-preview__fallback{width:48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center;background:#0f172a;color:#fff;font-size:1.1rem;font-weight:800}
.email-shell-preview__brand-copy{min-width:0;color:#fff;text-shadow:0 1px 2px rgba(0,0,0,.22)}
.email-shell-preview__brand-copy strong{display:block;font-size:1rem;line-height:1.25;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.email-shell-preview__brand-copy span{display:block;font-size:.76rem;opacity:.78;line-height:1.35}
.email-shell-preview__body{padding:18px 18px 16px}
.email-shell-preview__eyebrow{display:inline-flex;padding:.35rem .6rem;border-radius:999px;background:color-mix(in srgb,var(--bs-primary) 12%,transparent);color:var(--bs-primary);font-size:.72rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;margin-bottom:.75rem}
.email-shell-preview__body h5{margin:0 0 .55rem}
.email-shell-preview__body p{margin:0;color:var(--bs-secondary-color);line-height:1.6;font-size:.9rem}
.email-shell-preview__footer{padding:16px 18px;border-top:1px solid var(--email-border);background:color-mix(in srgb,var(--bs-body-bg) 92%,var(--bs-primary) 8%)}
.email-shell-preview__footer strong{display:block;margin-bottom:.35rem}
.email-shell-preview__footer p{margin:0 0 .35rem;font-size:.82rem;line-height:1.55;color:var(--bs-secondary-color)}
.email-shell-preview__footer span{font-size:.76rem;color:var(--bs-secondary-color)}
.email-logo-uploader{padding:0;border:1px dashed color-mix(in srgb,var(--bs-primary) 35%,var(--email-border));border-radius:16px;background:color-mix(in srgb,var(--bs-body-bg) 88%,var(--bs-primary) 12%);transition:.18s ease;text-align:left}
.email-logo-uploader:hover,.email-logo-uploader.drag-over{border-color:var(--bs-primary);background:color-mix(in srgb,var(--bs-primary) 10%,var(--bs-body-bg))}
.email-logo-uploader__inner{display:flex;align-items:center;gap:.9rem;padding:1rem;cursor:pointer}
.email-logo-uploader__inner *{pointer-events:none}
.email-logo-uploader__preview{width:64px;height:64px;border-radius:14px;background:rgba(255,255,255,.9);border:1px solid var(--email-border);display:flex;align-items:center;justify-content:center;flex-shrink:0;color:var(--bs-primary);font-size:1.7rem}
.email-logo-uploader__preview img{max-width:52px;max-height:52px;object-fit:contain}
.email-logo-uploader__copy{display:flex;flex-direction:column;gap:.2rem;min-width:0}
.email-logo-uploader__copy strong{font-size:.92rem}
.email-logo-uploader__copy span{font-size:.8rem;color:var(--bs-secondary-color)}
.email-logo-uploader__progress{padding:0 1rem 1rem}
.email-logo-uploader__progress:empty{display:none}
.email-logo-uploader .upload-item{background:var(--bs-body-bg);border-color:var(--email-border)!important;border-radius:12px!important;margin-bottom:0!important}
@media (max-width: 1199.98px){.email-hero{grid-template-columns:1fr}.driver-grid{grid-template-columns:1fr 1fr}}
@media (max-width: 767.98px){.driver-grid{grid-template-columns:1fr}.premium-panel__footer{flex-direction:column}.email-hero__title{font-size:1.5rem}.switch-card{flex-direction:column;align-items:flex-start}}
[data-bs-theme="light"] .email-shell-preview__brand{background:rgba(255,255,255,.16);border-color:rgba(255,255,255,.36)}
[data-bs-theme="light"] .email-shell-preview__brand-copy{color:#fff}
</style>
@endpush

@push('scripts')
<script>
(function() {
    const csrf = document.querySelector('meta[name=csrf-token]').content;

    function toggleDriver() {
        let driver = document.querySelector('input[name="driver"]:checked')?.value;
        if (!driver) {
            const smtpRadio = document.getElementById('drv-smtp');
            if (smtpRadio) {
                smtpRadio.checked = true;
            }
            driver = 'smtp';
        }
        document.getElementById('smtp-fields').style.display = driver === 'smtp' ? '' : 'none';
        document.getElementById('api-fields').style.display = driver !== 'smtp' ? '' : 'none';
    }
    document.querySelectorAll('input[name="driver"]').forEach(r => r.addEventListener('change', toggleDriver));
    toggleDriver();

    ['toggle-password', 'toggle-apikey'].forEach(id => {
        document.getElementById(id)?.addEventListener('click', function() {
            const inp = document.getElementById(id === 'toggle-password' ? 'smtp-password' : 'api-key');
            const icon = this.querySelector('i');
            inp.type = inp.type === 'password' ? 'text' : 'password';
            icon.className = inp.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
        });
    });

    function syncColor(pairA, pairB, callback) {
        const picker = document.getElementById(pairA);
        const text = document.getElementById(pairB);
        if (!picker || !text) return;
        picker.addEventListener('input', () => { text.value = picker.value; callback(); });
        text.addEventListener('input', () => {
            if (/^#[0-9a-fA-F]{6}$/.test(text.value)) {
                picker.value = text.value;
                callback();
            }
        });
    }

    function updateBrandPreview() {
        const logo = document.getElementById('email-logo-url')?.value?.trim() || '';
        const brandName = document.getElementById('email-brand-name')?.value?.trim() || 'E';
        const primary = document.getElementById('email-primary-color')?.value || '#0d6efd';
        const accent = document.getElementById('email-accent-color')?.value || '#6610f2';
        const signature = document.getElementById('email-footer-signature')?.value?.trim() || brandName;
        const footerText = document.getElementById('email-footer-text')?.value?.trim() || '';
        const footerSubtext = document.getElementById('email-footer-subtext')?.value?.trim() || '';

        const header = document.getElementById('email-shell-header');
        const logoEl = document.getElementById('email-shell-logo');
        const fallbackEl = document.getElementById('email-shell-fallback');
        const brandNameEl = document.getElementById('email-shell-brand-name');
        const signatureEl = document.getElementById('email-shell-signature');
        const footerTextEl = document.getElementById('email-shell-footer-text');
        const footerSubtextEl = document.getElementById('email-shell-footer-subtext');
        const uploadPreview = document.getElementById('email-logo-upload-preview');
        const uploadIcon = document.getElementById('email-logo-upload-icon');
        const uploadLabel = document.getElementById('email-logo-url-label');

        if (header) header.style.background = `linear-gradient(135deg, ${primary}, ${accent})`;
        if (brandNameEl) brandNameEl.textContent = brandName;
        if (signatureEl) signatureEl.textContent = signature;
        if (footerTextEl) footerTextEl.textContent = footerText;
        if (footerSubtextEl) footerSubtextEl.textContent = footerSubtext;
        if (uploadLabel) uploadLabel.textContent = logo || 'Se nao enviar uma logo, sera usada a logo principal do sistema.';

        if (logo && logoEl && fallbackEl) {
            logoEl.src = logo;
            logoEl.classList.remove('d-none');
            fallbackEl.classList.add('d-none');
            if (uploadPreview && uploadIcon) {
                uploadPreview.src = logo;
                uploadPreview.classList.remove('d-none');
                uploadIcon.classList.add('d-none');
            }
        } else if (logoEl && fallbackEl) {
            logoEl.classList.add('d-none');
            fallbackEl.classList.remove('d-none');
            fallbackEl.textContent = (brandName.charAt(0) || 'E').toUpperCase();
            if (uploadPreview && uploadIcon) {
                uploadPreview.classList.add('d-none');
                uploadIcon.classList.remove('d-none');
            }
        }
    }

    syncColor('email-primary-picker', 'email-primary-color', updateBrandPreview);
    syncColor('email-accent-picker', 'email-accent-color', updateBrandPreview);
    ['email-logo-url', 'email-brand-name', 'email-footer-signature', 'email-footer-text', 'email-footer-subtext']
        .forEach(id => document.getElementById(id)?.addEventListener('input', updateBrandPreview));
    updateBrandPreview();

    document.querySelector('.email-logo-uploader')?.addEventListener('upload:done', event => {
        let payload = event.detail?.response || {};
        if (typeof payload === 'string') {
            try {
                payload = JSON.parse(payload);
            } catch {
                payload = {};
            }
        }

        if (!payload.url) {
            window.toast.error('Upload concluido, mas a URL da logo nao foi retornada.');
            return;
        }

        const input = document.getElementById('email-logo-url');
        input.value = payload.url;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        window.toast.success('Logo do email atualizada. Clique em salvar para gravar a configuracao.');
    });

    document.getElementById('email-logo-clear')?.addEventListener('click', () => {
        const input = document.getElementById('email-logo-url');
        input.value = '';
        input.dispatchEvent(new Event('input', { bubbles: true }));
    });

    document.getElementById('frm-cfg').addEventListener('submit', async e => {
        e.preventDefault();
        const btn = document.getElementById('btn-save');
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Salvando...';
        const fd = new FormData(e.target);
        if (!fd.has('enabled')) fd.set('enabled', '0');
        if (!fd.get('driver')) {
            const checkedDriver = document.querySelector('input[name="driver"]:checked')?.value || 'smtp';
            fd.set('driver', checkedDriver);
        }
        try {
            const r = await fetch('{{ route($routeNames["configSave"]) }}', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: fd,
            });
            const d = await r.json();
            if (r.ok && d.ok) {
                window.toast.success('Configuracao salva com sucesso!');
            } else {
                let msg = d.error || d.message;
                if (d.errors && typeof d.errors === 'object') {
                    const errorList = Object.values(d.errors).flat();
                    if (errorList.length > 0) {
                        msg = errorList.join('<br>');
                    }
                }
                window.toast.error(msg || 'Erro ao salvar.');
            }
        } catch {
            window.toast.error('Erro de conexao.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = orig;
        }
    });

    async function doTest() {
        const { value: email } = await Swal.fire({
            title: 'Testar conexao de email',
            input: 'email',
            inputLabel: 'Email de destino',
            inputPlaceholder: 'seu@email.com',
            showCancelButton: true,
            confirmButtonText: 'Enviar teste',
            cancelButtonText: 'Cancelar',
            showLoaderOnConfirm: true,
            preConfirm: async (to) => {
                const fd = new FormData();
                fd.append('to', to);
                const r = await fetch('{{ route($routeNames["configTest"]) }}', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: fd,
                });
                return r.json();
            },
            allowOutsideClick: () => !Swal.isLoading()
        });
        if (!email) return;
        if (email.ok) {
            Swal.fire({ icon: 'success', title: 'Email enviado', text: 'Verifique a caixa de entrada e o spam.' });
        } else {
            Swal.fire({ icon: 'error', title: 'Falha no envio', html: `<pre class="text-start small mb-0">${email.error || 'Erro desconhecido'}</pre>` });
        }
    }

    document.getElementById('btn-test')?.addEventListener('click', doTest);
    document.getElementById('btn-test-2')?.addEventListener('click', doTest);
})();
</script>
@endpush
