{{-- Modal global de upload arrastar-soltar com progresso, preview e tempo restante --}}
<div class="modal fade" id="uploadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-cloud-upload me-2"></i> Enviar arquivos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                {{-- Zona de drop --}}
                <div class="upload-dropzone" id="globalUploadZone"
                     data-upload
                     data-url="{{ url($uploadPrefix ?? '/admin/uploads') }}"
                     data-max-mb="{{ config('modules.upload.max_size_mb', 50) }}"
                     data-multiple>
                    <div class="upload-dropzone-inner">
                        <i class="bi bi-cloud-arrow-up"></i>
                        <p class="mb-1 fw-semibold">Arraste arquivos aqui</p>
                        <p class="mb-0 small text-muted">ou clique para selecionar</p>
                        <span class="badge bg-light text-dark border mt-2">
                            <i class="bi bi-info-circle me-1"></i>
                            Maximo {{ config('modules.upload.max_size_mb', 50) }} MB por arquivo
                        </span>
                    </div>
                </div>

                {{-- Preview de imagens selecionadas --}}
                <div id="uploadPreviewGrid" class="row g-2 mt-3" style="display: none;" data-upload-preview></div>

                {{-- Progresso dos uploads --}}
                <div id="uploadProgressList" class="mt-3" data-upload-progress></div>

                {{-- Arquivos concluidos --}}
                <div id="uploadDoneList" class="mt-3" style="display: none;" data-upload-done>
                    <label class="form-label small fw-semibold text-uppercase text-muted">
                        <i class="bi bi-check-circle text-success me-1"></i> Arquivos enviados
                    </label>
                    <div id="uploadDoneItems"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-primary btn-sm d-none" id="uploadModalConfirm">
                    <i class="bi bi-check2-circle me-1"></i> Usar selecionado
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.upload-dropzone {
    border: 2px dashed var(--bs-border-color);
    border-radius: .65rem;
    padding: 2.5rem 1rem;
    text-align: center;
    cursor: pointer;
    transition: all .2s ease;
    background: var(--bs-tertiary-bg);
}
.upload-dropzone:hover, .upload-dropzone.drag-over {
    border-color: var(--bs-primary);
    background: color-mix(in srgb, var(--bs-primary) 5%, transparent);
}
.upload-dropzone-inner i {
    font-size: 3rem;
    color: var(--bs-primary);
    opacity: .6;
    display: block;
    margin-bottom: .5rem;
}
.upload-dropzone.drag-over .upload-dropzone-inner i {
    opacity: 1;
    transform: scale(1.1);
}
.upload-preview-thumb {
    width: 100%;
    aspect-ratio: 1;
    border-radius: .5rem;
    object-fit: cover;
    border: 2px solid var(--bs-border-color);
    cursor: pointer;
    transition: all .15s;
}
.upload-preview-thumb:hover, .upload-preview-thumb.selected {
    border-color: var(--bs-primary);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--bs-primary) 25%, transparent);
}
.upload-preview-file {
    display: flex;
    align-items: center;
    gap: .5rem;
    padding: .5rem .75rem;
    border: 1px solid var(--bs-border-color);
    border-radius: .5rem;
    cursor: pointer;
    transition: all .15s;
}
.upload-preview-file:hover, .upload-preview-file.selected {
    border-color: var(--bs-primary);
    background: color-mix(in srgb, var(--bs-primary) 5%, transparent);
}
.upload-item { transition: opacity .3s; }
.upload-item.done { opacity: .6; }
</style>

<script>
(function() {
    let uploadCallback = null;
    let uploadContext = null;
    let uploadedUrls = [];
    let selectedUrl = null;

    const modal = document.getElementById('uploadModal');
    let bsModalInstance = null;
    function getBsModal() { if (!bsModalInstance && modal && typeof bootstrap !== 'undefined' && bootstrap.Modal) { bsModalInstance = bootstrap.Modal.getInstance(modal) || new bootstrap.Modal(modal); } return bsModalInstance; }
    const zone = document.getElementById('globalUploadZone');
    const previewGrid = document.getElementById('uploadPreviewGrid');
    const progressList = document.getElementById('uploadProgressList');
    const doneList = document.getElementById('uploadDoneList');
    const doneItems = document.getElementById('uploadDoneItems');
    const confirmBtn = document.getElementById('uploadModalConfirm');

    /**
     * API global: window.openUploadModal(options)
     *
     * options:
     *   context: string (ex: 'slides', 'avatars', 'kanban') - appended to URL
     *   multiple: boolean (default false)
     *   accept: string (ex: 'image/*')
     *   onUpload: function(url) - chamada quando um arquivo e concluido
     *   onSelect: function(url) - chamada quando usuario clica "Usar selecionado"
     */
    window.openUploadModal = function(options = {}) {
        uploadCallback = options.onUpload || options.onSelect || null;
        uploadContext = options.context || '';
        uploadedUrls = [];
        selectedUrl = null;

        // Reset UI
        previewGrid.innerHTML = '';
        previewGrid.style.display = 'none';
        progressList.innerHTML = '';
        doneList.style.display = 'none';
        doneItems.innerHTML = '';
        confirmBtn.classList.add('d-none');

        // Configure zone
        if (uploadContext) {
            zone.dataset.url = '{{ url("/admin/uploads") }}/' + uploadContext;
        } else {
            zone.dataset.url = '{{ url("/admin/uploads") }}';
        }

        const input = zone.querySelector('input[type=file]');
        if (input) {
            input.multiple = options.multiple !== false;
            input.accept = options.accept || '';
        }

        const m = getBsModal(); if (m) m.show();
    };

    // Listen for upload:done events from the global upload handler
    zone.addEventListener('upload:done', (e) => {
        try {
            const resp = typeof e.detail.response === 'string'
                ? JSON.parse(e.detail.response)
                : (e.detail.response || {});
            const url = resp.url || resp.path || '';
            if (url) {
                uploadedUrls.push(url);
                addDoneItem(url, e.detail.file);

                // Auto-callback se definido
                if (uploadCallback && typeof uploadCallback === 'function') {
                    uploadCallback(url);
                }
            }
        } catch (err) {}
    });

    // Preview de imagens ao selecionar (antes do upload)
    const observer = new MutationObserver(() => {
        const input = zone.querySelector('input[type=file]');
        if (!input) return;
        input.addEventListener('change', showPreviews);
    });
    observer.observe(zone, { childList: true });

    // Bind existing input
    setTimeout(() => {
        const input = zone.querySelector('input[type=file]');
        if (input) input.addEventListener('change', showPreviews);
    }, 100);

    function showPreviews() {
        const input = zone.querySelector('input[type=file]');
        if (!input || !input.files.length) return;

        previewGrid.innerHTML = '';
        previewGrid.style.display = '';

        Array.from(input.files).forEach(file => {
            const col = document.createElement('div');
            col.className = 'col-4 col-md-3';

            if (file.type.startsWith('image/')) {
                const img = document.createElement('img');
                img.className = 'upload-preview-thumb';
                img.alt = file.name;
                const reader = new FileReader();
                reader.onload = (e) => { img.src = e.target.result; };
                reader.readAsDataURL(file);
                col.appendChild(img);
            } else {
                const div = document.createElement('div');
                div.className = 'upload-preview-file';
                const iconMap = {
                    'application/pdf': 'bi-file-earmark-pdf text-danger',
                    'text/csv': 'bi-file-earmark-spreadsheet text-success',
                };
                const icon = iconMap[file.type] || 'bi-file-earmark text-primary';
                div.innerHTML = `<i class="bi ${icon} fs-4"></i><div class="min-w-0"><div class="small fw-semibold text-truncate">${file.name}</div><small class="text-muted">${fmtSize(file.size)}</small></div>`;
                col.appendChild(div);
            }

            previewGrid.appendChild(col);
        });
    }

    function addDoneItem(url, file) {
        doneList.style.display = '';
        const isImage = file.type.startsWith('image/');
        const item = document.createElement('div');
        item.className = 'upload-preview-file mb-2';
        item.dataset.url = url;
        item.innerHTML = `
            ${isImage ? `<img src="${url}" style="width:40px;height:40px;border-radius:.375rem;object-fit:cover;">` : '<i class="bi bi-file-earmark-check text-success fs-4"></i>'}
            <div class="min-w-0 flex-grow-1">
                <div class="small fw-semibold text-truncate">${file.name}</div>
                <code class="small text-muted text-truncate d-block" style="max-width:300px;">${url}</code>
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary" title="Copiar URL" onclick="navigator.clipboard.writeText('${url}');window.toast.success('URL copiada!');">
                <i class="bi bi-clipboard"></i>
            </button>
        `;
        item.addEventListener('click', () => {
            document.querySelectorAll('#uploadDoneItems .upload-preview-file').forEach(i => i.classList.remove('selected'));
            item.classList.add('selected');
            selectedUrl = url;
            confirmBtn.classList.remove('d-none');
        });
        doneItems.appendChild(item);
    }

    // Botao "Usar selecionado"
    confirmBtn.addEventListener('click', () => {
        if (selectedUrl && uploadCallback) {
            uploadCallback(selectedUrl);
        }
        getBsModal()?.hide();
    });

    function fmtSize(b) {
        if (b < 1024) return b + ' B';
        if (b < 1048576) return (b/1024).toFixed(1) + ' KB';
        return (b/1048576).toFixed(1) + ' MB';
    }
})();
</script>
