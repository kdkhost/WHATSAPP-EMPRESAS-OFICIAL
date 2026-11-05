@extends('admin.layouts.app')
@section('title', 'Editor de Arquivos')

@push('head')
<style>
.file-editor-page {
    --file-editor-surface-soft: color-mix(in srgb, var(--bs-body-bg) 92%, var(--bs-primary) 8%);
    --file-editor-border-soft: color-mix(in srgb, var(--bs-border-color) 75%, transparent);
}
.file-editor-pane {
    height: 100%;
}
.file-editor-pane .card-header,
.file-editor-pane .card-footer {
    padding: .95rem 1rem;
}
.file-editor-pane__label {
    display: block;
    font-size: .74rem;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--bs-secondary-color);
    margin-bottom: .35rem;
}
.file-editor-tree {
    max-height: 640px;
    overflow: auto;
}
.tree-folder {
    cursor: pointer;
    padding: .45rem .65rem;
    border-radius: 12px;
    transition: var(--app-transition);
}
.tree-folder:hover {
    background: var(--file-editor-surface-soft);
}
.tree-file {
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: .55rem;
    padding: .45rem .65rem .45rem 1rem;
    border-radius: 12px;
    transition: var(--app-transition);
    color: inherit;
    text-decoration: none !important;
}
.tree-file:hover {
    background: var(--file-editor-surface-soft);
    color: inherit;
}
.tree-file.active {
    background: color-mix(in srgb, var(--bs-primary) 12%, transparent) !important;
    border: 1px solid color-mix(in srgb, var(--bs-primary) 30%, var(--file-editor-border-soft));
    color: inherit !important;
    font-weight: 700;
}
.folder-children {
    margin-left: .9rem;
    padding-left: .85rem;
    border-left: 1px dashed var(--file-editor-border-soft);
}
.file-editor-stage {
    min-height: 620px;
}
.file-editor-canvas {
    min-height: 520px;
    position: relative;
}
.file-editor-placeholder {
    position: absolute;
    inset: 0;
    display: grid;
    place-items: center;
    text-align: center;
    color: var(--bs-secondary-color);
    padding: 2rem;
}
</style>
@endpush

@section('content')
<div class="container-fluid file-editor-page">
    <section class="admin-premium-hero mb-4">
        <div>
            <span class="admin-premium-hero__eyebrow">Superadmin e customizacao</span>
            <h1 class="admin-premium-hero__title">Editor interno para ajustes controlados em arquivos do sistema.</h1>
            <p class="admin-premium-hero__text">
                O fluxo agora respeita o tema do painel, separa exploracao de arquivos da area de edicao e deixa mais claro quando voce esta operando em modo sensivel.
            </p>
        </div>
        <div class="dashboard-hero-notes">
            <div class="dashboard-note">
                <span class="dashboard-note__label">Escopo permitido</span>
                <span class="dashboard-note__value">Views, frontend, configs e modulos</span>
            </div>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Protecao</span>
                <span class="dashboard-note__value">Backups automaticos antes de salvar</span>
            </div>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Modo atual</span>
                <span class="dashboard-note__value text-warning">Superadmin ativo</span>
            </div>
            <div class="dashboard-note">
                <span class="dashboard-note__label">Formatos</span>
                <span class="dashboard-note__value">PHP, Blade, TS, CSS, JSON e HTML</span>
            </div>
        </div>
    </section>

    <section class="premium-panel mb-4">
        <div class="premium-panel__header">
            <div>
                <span class="premium-panel__kicker">Operacao sensivel</span>
                <h2 class="premium-panel__title">Edicao controlada em tempo real</h2>
                <p class="premium-panel__subtitle mb-0">Use apenas para ajustes tecnicos conscientes. O sistema registra auditoria e gera backup do arquivo salvo.</p>
            </div>
            <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-2">
                <i class="bi bi-shield-exclamation me-1"></i> Modo superadmin
            </span>
        </div>
    </section>

    <div class="row g-4">
        <div class="col-xl-3 col-lg-4">
            <div class="card file-editor-pane file-editor-stage">
                <div class="card-header">
                    <div>
                        <h3 class="dashboard-panel__title mb-0">Explorador de arquivos</h3>
                        <span class="dashboard-panel__subtitle">Diretorios liberados para leitura e escrita controlada.</span>
                    </div>
                    <button class="btn btn-sm btn-outline-secondary" type="button" onclick="location.reload()">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                </div>
                <div class="card-body file-editor-tree">
                    <div id="file-explorer-tree" class="treeview">
                        @foreach($files as $node)
                            @include('admin.system.file-tree-node', ['node' => $node])
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-9 col-lg-8">
            <div class="card file-editor-pane file-editor-stage d-flex flex-column">
                <div class="card-header">
                    <div class="min-w-0">
                        <span class="file-editor-pane__label">Arquivo ativo</span>
                        <div class="font-monospace text-truncate" id="active-file-label">
                            <i class="bi bi-file-earmark-code me-2 text-info"></i>Nenhum arquivo selecionado
                        </div>
                    </div>
                    <button class="btn btn-primary" id="btn-save-file" type="button" disabled>
                        <i class="bi bi-cloud-arrow-up me-1"></i> Salvar e compilar
                    </button>
                </div>
                <div class="card-body p-0 file-editor-canvas">
                    <div id="code-editor" style="position:absolute; inset:0; font-size:14px;"></div>
                    <div id="editor-placeholder" class="file-editor-placeholder">
                        <div>
                            <i class="bi bi-code-square fs-1 d-block mb-2"></i>
                            <div class="fw-semibold mb-1">Selecione um arquivo no explorador.</div>
                            <div>O conteudo sera carregado no editor e o salvamento continuara protegido por backup e auditoria.</div>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2 small text-muted">
                    <span><i class="bi bi-info-circle me-1 text-info"></i> Suporta PHP, Blade, TS, TSX, JSON, CSS, HTML, JS e MD.</span>
                    <span id="editor-status-text">Pronto</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.15.0/ace.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.15.0/theme-monokai.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.15.0/mode-php.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.15.0/mode-javascript.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.15.0/mode-css.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.15.0/mode-json.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var editor = null;
    var activeFilePath = null;

    function getCSRFToken() {
        var meta = document.querySelector('meta[name=csrf-token]');
        return meta ? meta.content : '';
    }

    function initAceEditor(content, ext) {
        document.getElementById('editor-placeholder').classList.add('d-none');

        if (!editor) {
            editor = ace.edit('code-editor');
            editor.setTheme('ace/theme/monokai');
            editor.setOptions({
                fontFamily: 'Fira Code, Consolas, Monaco, monospace',
                fontSize: '14px',
                enableBasicAutocompletion: true,
                enableLiveAutocompletion: true,
                showPrintMargin: false,
                tabSize: 4
            });
        }

        editor.setValue(content, -1);
        editor.session.setUndoManager(new ace.UndoManager());

        var mode = 'ace/mode/text';
        if (ext === 'php') mode = 'ace/mode/php';
        else if (ext === 'tsx' || ext === 'ts' || ext === 'js') mode = 'ace/mode/javascript';
        else if (ext === 'css') mode = 'ace/mode/css';
        else if (ext === 'json') mode = 'ace/mode/json';

        editor.session.setMode(mode);
        document.getElementById('btn-save-file').disabled = false;
    }

    document.querySelectorAll('.tree-folder').forEach(function(folder) {
        folder.addEventListener('click', function(e) {
            e.stopPropagation();
            var targetId = this.dataset.target;
            var icon = this.querySelector('.folder-icon');
            var target = document.getElementById(targetId);
            if (target) target.classList.toggle('d-none');

            if (icon) {
                if (icon.classList.contains('bi-folder-fill')) {
                    icon.classList.remove('bi-folder-fill', 'text-warning');
                    icon.classList.add('bi-folder2-open', 'text-info');
                } else {
                    icon.classList.remove('bi-folder2-open', 'text-info');
                    icon.classList.add('bi-folder-fill', 'text-warning');
                }
            }
        });
    });

    document.querySelectorAll('.tree-file').forEach(function(fileLink) {
        fileLink.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            var path = this.dataset.path;

            document.querySelectorAll('.tree-file').forEach(function(el) {
                el.classList.remove('active');
            });
            this.classList.add('active');

            activeFilePath = path;
            document.getElementById('active-file-label').innerHTML = '<i class="bi bi-file-earmark-code me-2 text-info"></i>' + path;
            document.getElementById('editor-status-text').textContent = 'Carregando arquivo...';

            fetch('{{ route("admin.file-editor.load") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCSRFToken()
                },
                body: new URLSearchParams({ path: path })
            })
            .then(function(r) { return r.json(); })
            .then(function(r) {
                if (r.ok) {
                    initAceEditor(r.content, r.extension);
                    document.getElementById('editor-status-text').textContent = 'Arquivo carregado com sucesso.';
                } else {
                    window.toast.error(r.message || 'Erro ao carregar arquivo.');
                    document.getElementById('editor-status-text').textContent = 'Falha ao carregar.';
                }
            })
            .catch(function() {
                window.toast.error('Erro na requisicao.');
                document.getElementById('editor-status-text').textContent = 'Erro.';
            });
        });
    });

    document.getElementById('btn-save-file').addEventListener('click', function() {
        if (!editor || !activeFilePath) return;

        var content = editor.getValue();
        var btn = this;
        var statusText = document.getElementById('editor-status-text');

        statusText.textContent = 'Salvando e compilando...';
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Salvando...';

        fetch('{{ route("admin.file-editor.save") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCSRFToken()
            },
            body: new URLSearchParams({ path: activeFilePath, content: content })
        })
        .then(function(r) { return r.json(); })
        .then(function(r) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-cloud-arrow-up me-1"></i> Salvar e compilar';
            if (r.ok) {
                window.Swal.fire({
                    title: 'Sucesso!',
                    text: r.message || 'O arquivo foi salvo com sucesso.',
                    icon: 'success',
                    confirmButtonText: 'Fechar',
                    customClass: { confirmButton: 'btn btn-primary px-4' },
                    buttonsStyling: false
                });
                statusText.textContent = 'Arquivo salvo em tempo real.';
            } else {
                window.toast.error(r.message || 'Erro ao salvar o arquivo.');
                statusText.textContent = 'Falha ao salvar.';
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-cloud-arrow-up me-1"></i> Salvar e compilar';
            window.toast.error('Erro de conexao ao salvar.');
            statusText.textContent = 'Erro ao salvar.';
        });
    });
});
</script>
@endpush
