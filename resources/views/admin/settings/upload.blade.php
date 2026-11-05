@extends('admin.layouts.app')
@section('title', 'Configurações de Upload')

@php
    // Server info
    $phpUploadMax = ini_get('upload_max_filesize');
    $phpPostMax = ini_get('post_max_size');
    $phpMemoryLimit = ini_get('memory_limit');

    // Parse allowed mimes into array
    $currentMimes = array_map('trim', explode(',', $upload['allowed_mimes']));

    // Available mime types grouped
    $mimeGroups = [
        'Imagens' => [
            'image/jpeg' => ['label' => 'JPEG', 'icon' => 'bi-file-image', 'ext' => '.jpg, .jpeg'],
            'image/png' => ['label' => 'PNG', 'icon' => 'bi-file-image', 'ext' => '.png'],
            'image/webp' => ['label' => 'WebP', 'icon' => 'bi-file-image', 'ext' => '.webp'],
            'image/gif' => ['label' => 'GIF', 'icon' => 'bi-file-image', 'ext' => '.gif'],
            'image/svg+xml' => ['label' => 'SVG', 'icon' => 'bi-file-image', 'ext' => '.svg'],
        ],
        'Documentos' => [
            'application/pdf' => ['label' => 'PDF', 'icon' => 'bi-file-pdf', 'ext' => '.pdf'],
            'application/msword' => ['label' => 'DOC', 'icon' => 'bi-file-word', 'ext' => '.doc'],
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['label' => 'DOCX', 'icon' => 'bi-file-word', 'ext' => '.docx'],
            'application/vnd.ms-excel' => ['label' => 'XLS', 'icon' => 'bi-file-excel', 'ext' => '.xls'],
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['label' => 'XLSX', 'icon' => 'bi-file-excel', 'ext' => '.xlsx'],
        ],
        'Mídia' => [
            'audio/mpeg' => ['label' => 'MP3', 'icon' => 'bi-file-music', 'ext' => '.mp3'],
            'video/mp4' => ['label' => 'MP4', 'icon' => 'bi-file-play', 'ext' => '.mp4'],
            'audio/wav' => ['label' => 'WAV', 'icon' => 'bi-file-music', 'ext' => '.wav'],
        ],
        'Compactados' => [
            'application/zip' => ['label' => 'ZIP', 'icon' => 'bi-file-zip', 'ext' => '.zip'],
            'application/x-rar-compressed' => ['label' => 'RAR', 'icon' => 'bi-file-zip', 'ext' => '.rar'],
        ],
    ];
@endphp

@section('content')
<div class="container-fluid">

    {{-- Page Header --}}
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="mb-1"><i class="bi bi-cloud-upload me-2"></i>Configurações de Upload</h3>
            <p class="text-muted mb-0 small">Defina limites de tamanho e tipos de arquivo permitidos no sistema.</p>
        </div>
    </div>

    <form id="frm-upload">
        @csrf
        <div class="row g-4">

            {{-- ============================================================
                 CARD 1: Limites de Upload
                 ============================================================ --}}
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title mb-0"><i class="bi bi-sliders me-2"></i>Limites de Upload</h3>
                        <button type="submit" class="btn btn-primary btn-sm" id="btn-save-upload">
                            <i class="bi bi-check2-circle me-1"></i>Salvar
                        </button>
                    </div>
                    <div class="card-body">

                        {{-- Tamanho Máximo - Slider + Number --}}
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Tamanho Máximo por Arquivo</label>
                            <div class="row align-items-center g-3">
                                <div class="col-md-8">
                                    <input type="range" class="form-range" id="size-slider"
                                           min="1" max="500" step="1" value="{{ $upload['max_size_mb'] }}">
                                    <div class="d-flex justify-content-between small text-muted mt-1">
                                        <span>1 MB</span>
                                        <span>100 MB</span>
                                        <span>250 MB</span>
                                        <span>500 MB</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="input-group">
                                        <input type="number" name="max_size_mb" id="size-number" class="form-control"
                                               min="1" max="500" value="{{ $upload['max_size_mb'] }}">
                                        <span class="input-group-text">MB</span>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-2">
                                <div class="progress" style="height:6px;">
                                    <div class="progress-bar" id="size-progress" role="progressbar"
                                         style="width:{{ ($upload['max_size_mb'] / 500) * 100 }}%"></div>
                                </div>
                            </div>
                            <small class="text-muted mt-1 d-block">
                                <i class="bi bi-info-circle me-1"></i>
                                O PHP também limita pelo php.ini. Valor efetivo: <strong>min({{ $upload['max_size_mb'] }}MB, {{ $phpUploadMax }})</strong>
                            </small>
                        </div>

                        <hr>

                        {{-- Tipos Permitidos - Checkboxes --}}
                        <div>
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <label class="form-label fw-semibold mb-0">Tipos de Arquivo Permitidos</label>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-primary" id="select-all-mimes">
                                        <i class="bi bi-check-all me-1"></i>Todos
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" id="deselect-all-mimes">
                                        <i class="bi bi-x-lg me-1"></i>Nenhum
                                    </button>
                                </div>
                            </div>

                            @foreach($mimeGroups as $groupName => $mimes)
                            <div class="mb-3">
                                <h6 class="small text-muted text-uppercase fw-semibold mb-2">
                                    <i class="bi bi-folder me-1"></i>{{ $groupName }}
                                </h6>
                                <div class="row g-2">
                                    @foreach($mimes as $mime => $info)
                                    <div class="col-md-4 col-6">
                                        <div class="form-check border rounded p-2 ps-4 {{ in_array($mime, $currentMimes) ? 'border-primary bg-primary bg-opacity-10' : '' }} mime-check-wrapper">
                                            <input class="form-check-input mime-checkbox" type="checkbox"
                                                   value="{{ $mime }}" id="mime-{{ Str::slug($mime) }}"
                                                   {{ in_array($mime, $currentMimes) ? 'checked' : '' }}>
                                            <label class="form-check-label d-flex align-items-center gap-2 w-100" for="mime-{{ Str::slug($mime) }}">
                                                <i class="bi {{ $info['icon'] }} text-primary"></i>
                                                <div>
                                                    <span class="fw-medium small">{{ $info['label'] }}</span>
                                                    <br><small class="text-muted">{{ $info['ext'] }}</small>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endforeach

                            {{-- Hidden input for allowed_mimes --}}
                            <input type="hidden" name="allowed_mimes" id="allowed-mimes-input" value="{{ $upload['allowed_mimes'] }}">

                            <div class="mt-2 p-2 bg-body-tertiary rounded">
                                <small class="text-muted">
                                    <i class="bi bi-check-circle text-success me-1"></i>
                                    <span id="mime-count">{{ count($currentMimes) }}</span> tipo(s) selecionado(s)
                                </small>
                            </div>
                        </div>

                    </div>
                    <div class="card-footer d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check2-circle me-1"></i>Salvar Configurações
                        </button>
                    </div>
                </div>
            </div>

            {{-- ============================================================
                 SIDEBAR: Server Info + Stats
                 ============================================================ --}}
            <div class="col-lg-4">

                {{-- Informações do Servidor --}}
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title mb-0"><i class="bi bi-server me-2"></i>Informações do Servidor</h3>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">Limites configurados no PHP (php.ini):</p>

                        <div class="list-group list-group-flush">
                            <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <div>
                                    <span class="small fw-medium">upload_max_filesize</span>
                                    <br><small class="text-muted">Tamanho máximo por arquivo</small>
                                </div>
                                <span class="badge bg-primary-subtle text-primary">{{ $phpUploadMax }}</span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <div>
                                    <span class="small fw-medium">post_max_size</span>
                                    <br><small class="text-muted">Tamanho máximo do POST</small>
                                </div>
                                <span class="badge bg-info-subtle text-info">{{ $phpPostMax }}</span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <div>
                                    <span class="small fw-medium">memory_limit</span>
                                    <br><small class="text-muted">Limite de memória PHP</small>
                                </div>
                                <span class="badge bg-warning-subtle text-warning">{{ $phpMemoryLimit }}</span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <div>
                                    <span class="small fw-medium">max_execution_time</span>
                                    <br><small class="text-muted">Tempo máximo de execução</small>
                                </div>
                                <span class="badge bg-secondary-subtle text-secondary">{{ ini_get('max_execution_time') }}s</span>
                            </div>
                        </div>

                        <div class="mt-3 p-2 bg-body-tertiary rounded">
                            <small class="text-muted">
                                <i class="bi bi-exclamation-triangle text-warning me-1"></i>
                                O limite efetivo é o <strong>menor valor</strong> entre a configuração do sistema e do PHP.
                            </small>
                        </div>
                    </div>
                </div>

                {{-- Estatísticas --}}
                <div class="card mt-3">
                    <div class="card-header">
                        <h3 class="card-title mb-0"><i class="bi bi-pie-chart me-2"></i>Estatísticas de Upload</h3>
                    </div>
                    <div class="card-body">
                        <div class="row g-3 text-center">
                            <div class="col-6">
                                <div class="border rounded p-3">
                                    <div class="fs-4 fw-bold text-primary" id="stat-total-files">—</div>
                                    <small class="text-muted">Total de Arquivos</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="border rounded p-3">
                                    <div class="fs-4 fw-bold text-info" id="stat-total-size">—</div>
                                    <small class="text-muted">Espaço Usado</small>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3">
                            <button type="button" class="btn btn-outline-primary btn-sm w-100" id="btn-refresh-stats">
                                <i class="bi bi-arrow-clockwise me-1"></i>Atualizar Estatísticas
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Dicas --}}
                <div class="card mt-3">
                    <div class="card-header">
                        <h3 class="card-title mb-0"><i class="bi bi-lightbulb me-2"></i>Recomendações</h3>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled small text-muted mb-0">
                            <li class="mb-2"><i class="bi bi-check-circle text-success me-1"></i> Limite uploads a 50MB para melhor performance</li>
                            <li class="mb-2"><i class="bi bi-check-circle text-success me-1"></i> Permita apenas tipos necessários (segurança)</li>
                            <li class="mb-2"><i class="bi bi-check-circle text-success me-1"></i> WebP é recomendado para imagens (menor tamanho)</li>
                            <li><i class="bi bi-shield-check text-primary me-1"></i> Arquivos executáveis (.exe, .bat) são sempre bloqueados</li>
                        </ul>
                    </div>
                </div>
            </div>

        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function() {
    const form = document.getElementById('frm-upload');
    const slider = document.getElementById('size-slider');
    const numberInput = document.getElementById('size-number');
    const progressBar = document.getElementById('size-progress');
    const mimesInput = document.getElementById('allowed-mimes-input');
    const mimeCount = document.getElementById('mime-count');

    // Sync slider <-> number input
    slider.addEventListener('input', function() {
        numberInput.value = this.value;
        updateProgress(this.value);
    });
    numberInput.addEventListener('input', function() {
        const val = Math.min(500, Math.max(1, parseInt(this.value) || 1));
        slider.value = val;
        updateProgress(val);
    });

    function updateProgress(val) {
        const pct = (val / 500) * 100;
        progressBar.style.width = pct + '%';
        if (val > 250) {
            progressBar.className = 'progress-bar bg-warning';
        } else if (val > 100) {
            progressBar.className = 'progress-bar bg-info';
        } else {
            progressBar.className = 'progress-bar bg-primary';
        }
    }

    // Mime checkboxes
    function updateMimesInput() {
        const checked = document.querySelectorAll('.mime-checkbox:checked');
        const mimes = Array.from(checked).map(cb => cb.value);
        mimesInput.value = mimes.join(',');
        mimeCount.textContent = mimes.length;

        // Update visual state
        document.querySelectorAll('.mime-check-wrapper').forEach(function(wrapper) {
            const cb = wrapper.querySelector('.mime-checkbox');
            if (cb.checked) {
                wrapper.classList.add('border-primary', 'bg-primary', 'bg-opacity-10');
            } else {
                wrapper.classList.remove('border-primary', 'bg-primary', 'bg-opacity-10');
            }
        });
    }

    document.querySelectorAll('.mime-checkbox').forEach(function(cb) {
        cb.addEventListener('change', updateMimesInput);
    });

    // Select all / Deselect all
    document.getElementById('select-all-mimes').addEventListener('click', function() {
        document.querySelectorAll('.mime-checkbox').forEach(cb => cb.checked = true);
        updateMimesInput();
    });
    document.getElementById('deselect-all-mimes').addEventListener('click', function() {
        document.querySelectorAll('.mime-checkbox').forEach(cb => cb.checked = false);
        updateMimesInput();
    });

    // Load stats
    function loadStats() {
        // Try to fetch stats from server
        fetch('{{ url("/admin/uploads/stats") }}', {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        })
        .then(r => r.json())
        .then(data => {
            document.getElementById('stat-total-files').textContent = data.total_files || '0';
            document.getElementById('stat-total-size').textContent = data.total_size || '0 MB';
        })
        .catch(function() {
            document.getElementById('stat-total-files').textContent = '—';
            document.getElementById('stat-total-size').textContent = '—';
        });
    }

    document.getElementById('btn-refresh-stats').addEventListener('click', loadStats);
    loadStats();

    // Form submit via AJAX
    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        // Validate at least one mime selected
        if (!mimesInput.value) {
            window.toast.error('Selecione pelo menos um tipo de arquivo permitido.');
            return;
        }

        const btn = document.getElementById('btn-save-upload');
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Salvando...';

        try {
            const fd = new FormData(form);
            const r = await fetch('{{ route("admin.settings.upload.update") }}', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'Accept': 'application/json'
                },
                body: fd
            });

            const data = await r.json();
            if (r.ok && data.ok) {
                window.toast.success('Configurações de upload salvas com sucesso!');
            } else {
                const errors = data.errors ? Object.values(data.errors).flat().join(', ') : 'Erro ao salvar';
                window.toast.error(errors);
            }
        } catch (err) {
            window.toast.error('Erro de conexão. Tente novamente.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    });
})();
</script>
@endpush
