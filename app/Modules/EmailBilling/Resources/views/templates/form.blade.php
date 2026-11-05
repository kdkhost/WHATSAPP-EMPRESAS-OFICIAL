@extends(request()->is('painel*') ? 'tenancy::tenant.layout' : 'admin.layouts.app')
@section('title', $template->exists ? 'Editar template de email' : 'Novo template de email')

@push('head')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css">
<style>
.email-editor-page {
    --editor-border: rgba(15,23,42,.08);
    --editor-soft: color-mix(in srgb, var(--bs-primary) 12%, transparent);
    --preview-toolbar-bg: var(--bs-body-bg);
    --preview-toolbar-color: var(--bs-body-color);
    --preview-toolbar-border: var(--bs-border-color);
    --preview-envelope-bg: color-mix(in srgb, var(--bs-body-bg) 94%, var(--bs-primary) 6%);
    --preview-frame-bg: color-mix(in srgb, var(--bs-body-bg) 94%, var(--bs-primary) 6%);
    --preview-frame-border: var(--bs-border-color);
}
.editor-hero {
    border: 1px solid var(--editor-border);
    border-radius: 18px;
    background:
        linear-gradient(135deg, color-mix(in srgb, var(--bs-primary) 10%, transparent), transparent 40%),
        linear-gradient(180deg,var(--bs-body-bg),color-mix(in srgb,var(--bs-body-bg) 88%,var(--bs-primary) 12%));
    padding: 1.4rem;
}
.editor-hero-icon,
.editor-mini-icon {
    border-radius: var(--app-radius);
    display: grid;
    place-items: center;
    background: var(--editor-soft);
    color: var(--bs-primary);
}
.editor-hero-icon {
    width: 52px;
    height: 52px;
    font-size: 1.45rem;
}
.editor-mini-icon {
    width: 38px;
    height: 38px;
    font-size: 1rem;
}
.editor-section .card-header {
    min-height: 52px;
}
.editor-section,
.preview-shell,
.sample-card {
    border-color: var(--editor-border);
    border-radius: 18px;
    box-shadow: 0 14px 35px rgba(15,23,42,.05) !important;
}
.var-chip {
    cursor: pointer;
    transition: all .15s ease;
}
.var-chip:hover {
    background: var(--bs-primary) !important;
    border-color: var(--bs-primary) !important;
    color: #fff !important;
}
.preview-shell {
    position: sticky;
    top: 80px;
    overflow: hidden;
}
.preview-toolbar {
    background: var(--preview-toolbar-bg);
    color: var(--preview-toolbar-color);
    border-bottom: 1px solid var(--preview-toolbar-border);
}
.preview-envelope {
    background: var(--preview-envelope-bg);
}
.preview-subject-line {
    min-height: 24px;
}
.preview-frame {
    border: 0;
    width: 100%;
    min-height: 520px;
    background: var(--preview-frame-bg);
}
.email-editor-page .note-editor.note-frame {
    overflow: hidden;
    border-color: var(--editor-border);
    border-radius: 14px;
    background: var(--bs-body-bg);
    color: var(--bs-body-color);
}
.email-editor-page .note-toolbar {
    background: var(--bs-tertiary-bg);
    border-color: var(--editor-border);
}
.email-editor-page .note-editable {
    min-height: 330px;
    background: var(--bs-body-bg);
    color: var(--bs-body-color);
}
.email-editor-page .note-statusbar {
    background: var(--bs-tertiary-bg);
    border-color: var(--editor-border);
}
.email-editor-page .note-btn {
    border-color: var(--editor-border);
    background: var(--bs-body-bg);
    color: var(--bs-body-color);
}
.email-editor-page .note-dropdown-menu {
    background: var(--bs-body-bg);
    border-color: var(--bs-border-color);
    color: var(--bs-body-color);
}
.sample-grid .form-label {
    font-size: .72rem;
    text-transform: uppercase;
    letter-spacing: .02em;
    color: var(--bs-secondary-color);
}
[data-bs-theme="dark"] .email-editor-page {
    --editor-border: rgba(148,163,184,.18);
    --preview-toolbar-bg: color-mix(in srgb, var(--bs-body-bg) 88%, var(--bs-primary) 12%);
    --preview-toolbar-color: var(--bs-body-color);
    --preview-toolbar-border: rgba(148,163,184,.18);
    --preview-envelope-bg: color-mix(in srgb, var(--bs-body-bg) 88%, var(--bs-primary) 12%);
    --preview-frame-bg: color-mix(in srgb, var(--bs-body-bg) 92%, var(--bs-primary) 8%);
}
[data-bs-theme="dark"] .editor-hero,
[data-bs-theme="dark"] .preview-envelope {
    background-color: var(--preview-envelope-bg);
}
[data-bs-theme="dark"] .editor-section,
[data-bs-theme="dark"] .preview-shell,
[data-bs-theme="dark"] .sample-card {
    box-shadow: 0 14px 35px rgba(0,0,0,.22) !important;
}
[data-bs-theme="light"] .email-editor-page {
    --preview-toolbar-bg: var(--bs-body-bg);
    --preview-toolbar-color: var(--bs-body-color);
    --preview-toolbar-border: rgba(0,0,0,.08);
    --preview-envelope-bg: color-mix(in srgb, var(--bs-body-bg) 94%, var(--bs-primary) 6%);
    --preview-frame-bg: color-mix(in srgb, var(--bs-body-bg) 96%, var(--bs-primary) 4%);
}
</style>
@endpush

@section('content')
@php
    $previewBrandName = $previewDefaults['brand_name'] ?? $settingsManager->get('app.name', config('app.name'));
    $previewLogoUrl = $previewDefaults['logo_url'] ?? '';
    $categories = [
        'cobranca' => ['Cobrança', 'receipt-cutoff', 'primary'],
        'lembrete' => ['Lembrete', 'bell', 'info'],
        'atraso' => ['Atraso', 'exclamation-triangle', 'danger'],
        'agradecimento' => ['Agradecimento', 'heart', 'success'],
        'custom' => ['Custom', 'code-square', 'secondary'],
    ];
    $currentCategory = old('category', $template->category ?: 'cobranca');
@endphp

<div class="container-fluid email-editor-page">
    <div class="editor-hero mb-4">
        <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
            <div class="d-flex align-items-start gap-3 min-w-0">
                <a href="{{ route($routeNames['templates']) }}" class="btn btn-outline-secondary btn-sm mt-1" title="Voltar">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <div class="editor-hero-icon flex-shrink-0">
                    <i class="bi bi-envelope-paper"></i>
                </div>
                <div class="min-w-0">
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                        <h3 class="mb-0 text-truncate">
                            {{ $template->exists ? 'Editar template' : 'Novo template de email' }}
                        </h3>
                        <span class="badge bg-{{ ($categories[$currentCategory][2] ?? 'secondary') }}-subtle text-{{ ($categories[$currentCategory][2] ?? 'secondary') }} border border-{{ ($categories[$currentCategory][2] ?? 'secondary') }}-subtle">
                            {{ $categories[$currentCategory][0] ?? 'Custom' }}
                        </span>
                    </div>
                    <p class="text-muted mb-0">
                        Configure assunto, HTML, variáveis e horário de disparo. O envio real passa pela fila do tenant.
                    </p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-{{ old('active', $template->active ?? true) ? 'success' : 'secondary' }}-subtle text-{{ old('active', $template->active ?? true) ? 'success' : 'secondary' }} border border-{{ old('active', $template->active ?? true) ? 'success' : 'secondary' }}-subtle">
                    {{ old('active', $template->active ?? true) ? 'Ativo' : 'Inativo' }}
                </span>
                <button type="submit" form="frm-tpl" class="btn btn-primary" id="btn-save">
                    <i class="bi bi-check2-circle me-1"></i><span class="js-save-label">Salvar template</span>
                </button>
            </div>
        </div>
    </div>

    <form id="frm-tpl" data-id="{{ $template->id ?? '' }}">
        @csrf
        <div class="row g-4">
            <div class="col-xl-7 col-lg-7">
                <div class="card shadow-sm mb-4 editor-section">
                    <div class="card-header">
                        <span><i class="bi bi-sliders me-1"></i> Estrutura do template</span>
                        <span class="badge bg-body-tertiary text-body border">Dados principais</span>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nome do template <span class="text-danger">*</span></label>
                                <input name="name" class="form-control" required maxlength="120"
                                       value="{{ old('name', $template->name) }}"
                                       placeholder="Ex.: Cobrança 3 dias antes">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Categoria</label>
                                <select name="category" id="category" class="form-select" required>
                                    @foreach($categories as $k => [$label])
                                        <option value="{{ $k }}" @selected($currentCategory === $k)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Disparo automático</label>
                                <select name="schedule_offset" class="form-select">
                                    <option value="">Manual</option>
                                    @foreach(['-3d'=>'3 dias antes','-1d'=>'1 dia antes','0d'=>'No vencimento','+1d'=>'1 dia após','+3d'=>'3 dias após','+7d'=>'7 dias após','+15d'=>'15 dias após'] as $k=>$v)
                                        <option value="{{ $k }}" @selected(old('schedule_offset',$template->schedule_offset)===$k)>{{ $v }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4 editor-section">
                    <div class="card-header">
                        <span><i class="bi bi-envelope me-1"></i> Assunto</span>
                        <small class="text-muted">Aceita variáveis</small>
                    </div>
                    <div class="card-body">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-type"></i></span>
                            <input name="subject" id="subject" class="form-control" required maxlength="255"
                                   value="{{ old('subject', $template->subject) }}"
                                   placeholder="Ex.: Fatura {numero_fatura} vence em {vencimento}">
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4 editor-section">
                    <div class="card-header">
                        <span><i class="bi bi-braces me-1"></i> Variáveis rápidas</span>
                        <small class="text-muted">Clique para inserir</small>
                    </div>
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($variables as $var => $desc)
                                <button type="button" class="btn btn-sm btn-outline-secondary var-chip" data-var="{{ $var }}" title="{{ $desc }}">
                                    <code class="text-reset">{{ $var }}</code>
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4 editor-section">
                    <div class="card-header">
                        <span><i class="bi bi-code-slash me-1"></i> Corpo do email</span>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">HTML</span>
                    </div>
                    <div class="card-body">
                        <textarea name="body_html" id="editor">{!! old('body_html', $template->body_html) !!}</textarea>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-body d-flex align-items-center justify-content-between gap-3 flex-wrap">
                        <div class="d-flex align-items-center gap-3">
                            <div class="editor-mini-icon">
                                <i class="bi bi-power"></i>
                            </div>
                            <div>
                                <div class="fw-semibold">Publicação</div>
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" type="checkbox" name="active" id="active" value="1"
                                           @checked(old('active', $template->active ?? true))>
                                    <label class="form-check-label small" for="active">Template ativo para agendamento</label>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="{{ route($routeNames['templates']) }}" class="btn btn-outline-secondary">
                                <i class="bi bi-x-lg me-1"></i> Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary" id="btn-save-2">
                                <i class="bi bi-check2-circle me-1"></i> Salvar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-5 col-lg-5">
                <div class="card shadow-sm preview-shell">
                    <div class="preview-toolbar px-3 py-3 d-flex align-items-center justify-content-between gap-2">
                        <span class="fw-semibold"><i class="bi bi-eye me-1"></i> Preview em tempo real</span>
                        <span class="badge text-bg-success">live</span>
                    </div>
                    <div class="preview-envelope border-bottom px-3 py-3">
                        <div class="text-muted small text-uppercase fw-semibold mb-1">Assunto</div>
                        <div id="preview-subject" class="fw-semibold preview-subject-line text-truncate">Sem assunto</div>
                    </div>
                    <div class="card-body p-0">
                        <iframe id="preview-frame" class="preview-frame"
                                srcdoc="<html><body style='margin:0;padding:40px;font-family:Arial,sans-serif;color:#64748b;text-align:center;'>Comece a editar para visualizar exatamente como o cliente recebera este email.</body></html>"></iframe>
                    </div>
                </div>

                <div class="card shadow-sm mt-3 sample-card">
                    <div class="card-header">
                        <span><i class="bi bi-person-badge me-1"></i> Dados de exemplo</span>
                        <small class="text-muted">Preview</small>
                    </div>
                    <div class="card-body sample-grid">
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label mb-1">Nome</label>
                                <input class="form-control form-control-sm" id="sample-nome" value="João da Silva">
                            </div>
                            <div class="col-6">
                                <label class="form-label mb-1">Email</label>
                                <input class="form-control form-control-sm" id="sample-email" value="cliente@exemplo.com">
                            </div>
                            <div class="col-6">
                                <label class="form-label mb-1">Valor</label>
                                <input class="form-control form-control-sm" id="sample-valor" value="1234.56">
                            </div>
                            <div class="col-6">
                                <label class="form-label mb-1">Vencimento</label>
                                <input type="date" class="form-control form-control-sm" id="sample-vencimento"
                                       value="{{ now('America/Sao_Paulo')->addDays(3)->format('Y-m-d') }}">
                            </div>
                            <div class="col-6">
                                <label class="form-label mb-1">Fatura</label>
                                <input class="form-control form-control-sm" id="sample-fatura" value="INV-001234">
                            </div>
                            <div class="col-6">
                                <label class="form-label mb-1">Empresa</label>
                                <input class="form-control form-control-sm" id="sample-empresa" value="{{ $previewBrandName }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label mb-1">Link de pagamento</label>
                                <input class="form-control form-control-sm" id="sample-link" value="https://pagar.exemplo.com/abc123">
                            </div>
                            <div class="col-12">
                                <div class="rounded border bg-body-tertiary px-3 py-2 d-flex align-items-center gap-3">
                                    <div class="flex-shrink-0">
                                        @if($previewLogoUrl)
                                            <img src="{{ $previewLogoUrl }}" alt="{{ $previewBrandName }}" id="tenant-preview-logo" style="max-height:34px;max-width:140px;object-fit:contain;">
                                        @else
                                            <div id="tenant-preview-logo-fallback" class="small fw-semibold text-muted">Sem logo configurada</div>
                                        @endif
                                    </div>
                                    <div class="small text-muted">
                                        O preview usa o branding real configurado para este tenant no envio de email.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/lang/summernote-pt-BR.min.js"></script>
<script>
(function() {
    const csrf = document.querySelector('meta[name=csrf-token]').content;
    const $editor = $('#editor');
    const subjectInput = document.getElementById('subject');
    const previewSubject = document.getElementById('preview-subject');
    const previewFrame = document.getElementById('preview-frame');
    const previewBranding = @json($previewDefaults ?? []);
    let timer = null;

    $editor.summernote({
        lang: 'pt-BR',
        height: 410,
        dialogsInBody: true,
        placeholder: 'Digite o corpo do email. Use variáveis como {nome}, {valor}, {vencimento} e {link_pagamento}.',
        toolbar: [
            ['style',  ['style','bold','italic','underline','strikethrough','clear']],
            ['color',  ['color','backColor']],
            ['para',   ['ul','ol','paragraph','height']],
            ['table',  ['table']],
            ['insert', ['link','picture','hr']],
            ['view',   ['fullscreen','codeview','help']],
        ],
        callbacks: {
            onChange: schedulePreview,
            onKeyup: schedulePreview,
            onPaste: () => setTimeout(schedulePreview, 50),
        }
    });

    subjectInput.addEventListener('input', schedulePreview);
    ['nome','email','valor','vencimento','fatura','empresa','link'].forEach(id => {
        document.getElementById(`sample-${id}`)?.addEventListener('input', schedulePreview);
    });

    function schedulePreview() {
        clearTimeout(timer);
        timer = setTimeout(updatePreview, 280);
    }

    function readCssVar(name, fallback) {
        const value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        return value || fallback;
    }

    function isDarkTheme() {
        return document.documentElement.getAttribute('data-bs-theme') === 'dark';
    }

    function previewThemeStyle() {
        const dark = isDarkTheme();
        const bodyBg = dark ? '#070b17' : readCssVar('--bs-tertiary-bg', '#f4f6f9');
        const surface = dark ? '#0f172a' : '#ffffff';
        const surfaceSoft = dark ? '#111827' : '#f8fafc';
        const border = dark ? 'rgba(148,163,184,.18)' : 'rgba(15,23,42,.08)';
        const text = dark ? '#e5e7eb' : '#0f172a';
        const muted = dark ? '#94a3b8' : '#64748b';

        return `<style id="admin-email-preview-theme">
            html{background:${bodyBg}!important;}
            body{background:${bodyBg}!important;color:${text}!important;}
            body>div{background:${surface}!important;border-color:${border}!important;box-shadow:none!important;}
            body>div>div:nth-child(2){background:${surface}!important;color:${text}!important;}
            body>div>div:nth-child(2) *{border-color:${border}!important;}
            body>div>div:nth-child(3){background:${surfaceSoft}!important;border-top-color:${border}!important;}
            body>div>div:nth-child(3) div{color:${muted}!important;}
            body>div>div:nth-child(3) div:first-child{color:${text}!important;}
            table,td,th{border-color:${border}!important;}
            a{color:${readCssVar('--bs-primary', '#6c5ce7')}!important;}
        </style>`;
    }

    function applyPreviewTheme(html) {
        const safeHtml = html && html.trim() !== ''
            ? html
            : "<!doctype html><html lang='pt-BR'><head><meta charset='UTF-8'></head><body style='margin:0;padding:40px;font-family:Arial,sans-serif;color:#64748b;text-align:center;'>Corpo vazio.</body></html>";
        const style = previewThemeStyle();

        if (safeHtml.includes('</head>')) {
            return safeHtml.replace('</head>', `${style}</head>`);
        }

        return `${style}${safeHtml}`;
    }

    function setPreviewHtml(html) {
        previewFrame.srcdoc = applyPreviewTheme(html);
    }

    function resizePreview() {
        try {
            const doc = previewFrame.contentDocument || previewFrame.contentWindow?.document;
            if (!doc) return;
            const bodyHeight = doc.body ? doc.body.scrollHeight : 0;
            const htmlHeight = doc.documentElement ? doc.documentElement.scrollHeight : 0;
            previewFrame.style.height = `${Math.max(bodyHeight, htmlHeight, 620)}px`;
        } catch (e) {
            console.error(e);
        }
    }

    async function updatePreview() {
        const body = $editor.summernote('code');
        const subject = subjectInput.value;
        const sample = {
            nome: document.getElementById('sample-nome')?.value || 'João da Silva',
            email: document.getElementById('sample-email')?.value || 'cliente@exemplo.com',
            valor: parseFloat(document.getElementById('sample-valor')?.value || '1234.56'),
            vencimento: document.getElementById('sample-vencimento')?.value || '',
            numero_fatura: document.getElementById('sample-fatura')?.value || 'INV-001234',
            empresa: document.getElementById('sample-empresa')?.value || @json($previewBrandName),
            link_pagamento: document.getElementById('sample-link')?.value || 'https://pagar.exemplo.com/abc123',
            logo_empresa: previewBranding.logo_url || '',
            email_branding: {
                ...previewBranding,
                brand_name: document.getElementById('sample-empresa')?.value || previewBranding.brand_name || @json($previewBrandName),
                footer_signature: document.getElementById('sample-empresa')?.value || previewBranding.footer_signature || @json($previewBrandName),
            },
        };
        try {
            const r = await fetch('{{ route($routeNames["templatesPreview"]) }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ subject, body_html: body, sample }),
            });
            const data = await r.json();
            previewSubject.textContent = data.subject || 'Sem assunto';
            setPreviewHtml(data.body_html || '');
        } catch(e) {
            console.error(e);
        }
    }

    document.querySelectorAll('.var-chip').forEach(btn => {
        btn.addEventListener('click', () => {
            if (document.activeElement === subjectInput) {
                const s = subjectInput.selectionStart;
                subjectInput.value = subjectInput.value.slice(0, s) + btn.dataset.var + subjectInput.value.slice(subjectInput.selectionEnd);
                subjectInput.focus();
            } else {
                $editor.summernote('insertText', btn.dataset.var);
            }
            schedulePreview();
        });
    });

    async function doSave(e) {
        e.preventDefault();
        const buttons = [document.getElementById('btn-save'), document.getElementById('btn-save-2')].filter(Boolean);
        buttons.forEach(btn => btn.disabled = true);
        const primary = document.getElementById('btn-save');
        const orig = primary.innerHTML;
        primary.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Salvando...';

        const form = document.getElementById('frm-tpl');
        const id = form.dataset.id;
        const isEdit = id && id !== '';
        const url = isEdit ? `{{ $templateBaseUrl }}/${id}` : '{{ route($routeNames["templatesStore"]) }}';
        const fd = new FormData(form);
        fd.set('body_html', $editor.summernote('code'));
        if (isEdit) fd.set('_method', 'PUT');
        if (!fd.has('active')) fd.set('active', '0');

        try {
            const r = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: fd,
            });
            const data = await r.json();
            if (r.ok && data.ok) {
                window.toast.success('Template salvo com sucesso.');
                if (data.redirect) setTimeout(() => window.location = data.redirect, 700);
            } else {
                window.toast.error(data.message || 'Erro ao salvar.');
            }
        } catch {
            window.toast.error('Erro de conexão.');
        } finally {
            buttons.forEach(btn => btn.disabled = false);
            primary.innerHTML = orig;
        }
    }

    document.getElementById('frm-tpl').addEventListener('submit', doSave);
    previewFrame.addEventListener('load', resizePreview);
    new MutationObserver(() => updatePreview()).observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-bs-theme']
    });
    setTimeout(updatePreview, 350);
})();
</script>
@endpush
