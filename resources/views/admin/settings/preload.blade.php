@extends('admin.layouts.app')

@section('title', 'Tela de Pré-carregamento')

@section('content')
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-1">
                        <i class="bi bi-hourglass-split text-primary"></i>
                        Tela de Pré-carregamento Premium
                    </h1>
                    <p class="text-muted mb-0">Personalize completamente a tela de loading do seu sistema</p>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-primary" id="btn-preview">
                        <i class="bi bi-eye"></i> Visualizar
                    </button>
                    <button type="button" class="btn btn-primary" id="btn-save">
                        <i class="bi bi-check-lg"></i> Salvar Alterações
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Painel de Configuração -->
        <div class="col-lg-8">
            <form id="form-preload">
                @csrf

                <!-- Estilo de Animação -->
                <div class="card mb-4 shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-stars text-warning"></i>
                            Estilo de Animação
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold">Escolha o estilo</label>
                                <div class="row g-3">
                                    @foreach([
                                        'spinner' => ['icon' => 'arrow-repeat', 'name' => 'Spinner Clássico'],
                                        'pulse' => ['icon' => 'circle', 'name' => 'Pulso'],
                                        'dots' => ['icon' => 'three-dots', 'name' => 'Três Pontos'],
                                        'wave' => ['icon' => 'water', 'name' => 'Onda'],
                                        'gradient' => ['icon' => 'palette', 'name' => 'Gradiente Animado'],
                                        'particles' => ['icon' => 'stars', 'name' => 'Partículas'],
                                        'logo' => ['icon' => 'image', 'name' => 'Logo Animado'],
                                        'custom' => ['icon' => 'code-square', 'name' => 'Personalizado']
                                    ] as $key => $style)
                                    <div class="col-md-3 col-6">
                                        <input type="radio" class="btn-check" name="style" id="style-{{ $key }}" 
                                               value="{{ $key }}" {{ $settings->style === $key ? 'checked' : '' }}>
                                        <label class="btn btn-outline-primary w-100 d-flex flex-column align-items-center py-3" 
                                               for="style-{{ $key }}">
                                            <i class="bi bi-{{ $style['icon'] }} fs-3 mb-2"></i>
                                            <span class="small">{{ $style['name'] }}</span>
                                        </label>
                                    </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label for="animation_speed" class="form-label">Velocidade da Animação (ms)</label>
                                <input type="range" class="form-range" id="animation_speed" name="animation_speed" 
                                       min="100" max="5000" step="100" value="{{ $settings->animation_speed ?? 1400 }}">
                                <div class="d-flex justify-content-between small text-muted">
                                    <span>Rápido (100ms)</span>
                                    <span id="animation_speed_value">{{ $settings->animation_speed ?? 1400 }}ms</span>
                                    <span>Lento (5000ms)</span>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label for="spinner_color" class="form-label">Cor do Spinner</label>
                                <div class="input-group">
                                    <input type="color" class="form-control form-control-color" id="spinner_color" 
                                           name="spinner_color" value="{{ $settings->spinner_color ?? '#ffffff' }}">
                                    <input type="text" class="form-control" value="{{ $settings->spinner_color ?? '#ffffff' }}" 
                                           id="spinner_color_text">
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label for="accent_color" class="form-label">Cor de Destaque (Accent)</label>
                                <div class="input-group">
                                    <input type="color" class="form-control form-control-color" id="accent_color" 
                                           name="accent_color" value="{{ $settings->accent_color ?? '#6610f2' }}">
                                    <input type="text" class="form-control" value="{{ $settings->accent_color ?? '#6610f2' }}" 
                                           id="accent_color_text">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Background -->
                <div class="card mb-4 shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-image text-info"></i>
                            Fundo (Background)
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold">Tipo de fundo</label>
                                <div class="btn-group w-100" role="group">
                                    @foreach(['solid' => 'Cor Sólida', 'gradient' => 'Gradiente', 'image' => 'Imagem', 'video' => 'Vídeo'] as $key => $label)
                                    <input type="radio" class="btn-check" name="background_type" id="bg-{{ $key }}" 
                                           value="{{ $key }}" {{ $settings->background_type === $key ? 'checked' : '' }}>
                                    <label class="btn btn-outline-secondary" for="bg-{{ $key }}">{{ $label }}</label>
                                    @endforeach
                                </div>
                            </div>

                            <!-- Cor Sólida -->
                            <div class="col-md-6 bg-option bg-solid">
                                <label for="background_color" class="form-label">Cor de Fundo</label>
                                <div class="input-group">
                                    <input type="color" class="form-control form-control-color" id="background_color" 
                                           name="background_color" value="{{ $settings->background_color ?? '#000000' }}">
                                    <input type="text" class="form-control" value="{{ $settings->background_color ?? '#000000' }}" 
                                           id="background_color_text">
                                </div>
                            </div>

                            <!-- Gradiente -->
                            <div class="col-md-4 bg-option bg-gradient">
                                <label for="background_gradient_start" class="form-label">Cor Inicial</label>
                                <div class="input-group">
                                    <input type="color" class="form-control form-control-color" id="background_gradient_start" 
                                           name="background_gradient_start" value="{{ $settings->background_gradient_start ?? '#0d6efd' }}">
                                    <input type="text" class="form-control" value="{{ $settings->background_gradient_start ?? '#0d6efd' }}" 
                                           id="background_gradient_start_text">
                                </div>
                            </div>

                            <div class="col-md-4 bg-option bg-gradient">
                                <label for="background_gradient_end" class="form-label">Cor Final</label>
                                <div class="input-group">
                                    <input type="color" class="form-control form-control-color" id="background_gradient_end" 
                                           name="background_gradient_end" value="{{ $settings->background_gradient_end ?? '#6610f2' }}">
                                    <input type="text" class="form-control" value="{{ $settings->background_gradient_end ?? '#6610f2' }}" 
                                           id="background_gradient_end_text">
                                </div>
                            </div>

                            <div class="col-md-4 bg-option bg-gradient">
                                <label for="background_gradient_angle" class="form-label">Ângulo</label>
                                <input type="number" class="form-control" id="background_gradient_angle" 
                                       name="background_gradient_angle" min="0" max="360" 
                                       value="{{ $settings->background_gradient_angle ?? 135 }}">
                            </div>

                            <!-- Imagem -->
                            <div class="col-12 bg-option bg-image">
                                <label for="background_image" class="form-label">Imagem de Fundo</label>
                                <input type="text" class="form-control mb-2" id="background_image" name="background_image" 
                                       value="{{ $settings->background_image }}" placeholder="https://exemplo.com/imagem.jpg">
                                
                                <div class="upload-dropzone py-3" data-upload data-url="{{ url('/admin/uploads/backgrounds') }}" data-max-mb="5" id="upload-bg-image">
                                    <div class="upload-dropzone-inner">
                                        <i class="bi bi-cloud-arrow-up text-primary" style="font-size: 2rem;"></i>
                                        <p class="mb-0 small text-muted">Arraste a imagem aqui ou <span class="text-primary fw-semibold">clique</span></p>
                                        <small class="text-muted">PNG, JPG, SVG, WebP, HEIC — Máx 5MB</small>
                                    </div>
                                    <input type="file" accept="image/*,.heic,.heif" hidden>
                                </div>
                                <div data-upload-progress class="mt-2" id="progress-bg-image"></div>
                            </div>

                            <div class="col-md-6 bg-option bg-image">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="blur_background" name="blur_background" 
                                           {{ $settings->blur_background ? 'checked' : '' }}>
                                    <label class="form-check-label" for="blur_background">Aplicar Blur</label>
                                </div>
                            </div>

                            <div class="col-md-6 bg-option bg-image">
                                <label for="blur_amount" class="form-label">Intensidade do Blur</label>
                                <input type="range" class="form-range" id="blur_amount" name="blur_amount" 
                                       min="0" max="50" value="{{ $settings->blur_amount ?? 5 }}">
                                <small class="text-muted" id="blur_amount_value">{{ $settings->blur_amount ?? 5 }}px</small>
                            </div>

                            <!-- Vídeo -->
                            <div class="col-12 bg-option bg-video">
                                <label for="background_video" class="form-label">Vídeo de Fundo</label>
                                <input type="text" class="form-control mb-2" id="background_video" name="background_video" 
                                       value="{{ $settings->background_video }}" placeholder="https://exemplo.com/video.mp4">
                                
                                <div class="upload-dropzone py-3" data-upload data-url="{{ url('/admin/uploads/videos') }}" data-max-mb="20" id="upload-bg-video">
                                    <div class="upload-dropzone-inner">
                                        <i class="bi bi-cloud-arrow-up text-primary" style="font-size: 2rem;"></i>
                                        <p class="mb-0 small text-muted">Arraste o vídeo aqui ou <span class="text-primary fw-semibold">clique</span></p>
                                        <small class="text-muted">MP4, WebM — Máx 20MB</small>
                                    </div>
                                    <input type="file" accept="video/mp4,video/webm" hidden>
                                </div>
                                <div data-upload-progress class="mt-2" id="progress-bg-video"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Logo e Texto -->
                <div class="card mb-4 shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-type text-success"></i>
                            Logo e Texto
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="show_logo" name="show_logo" 
                                           {{ $settings->show_logo ? 'checked' : '' }}>
                                    <label class="form-check-label" for="show_logo">Exibir Logo</label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="show_text" name="show_text" 
                                           {{ $settings->show_text ? 'checked' : '' }}>
                                    <label class="form-check-label" for="show_text">Exibir Texto</label>
                                </div>
                            </div>

                            <div class="col-12" id="logo-section">
                                <label for="logo" class="form-label">Logo Animada</label>
                                <input type="text" class="form-control mb-2" id="logo" name="logo" 
                                       value="{{ $settings->logo }}" placeholder="https://exemplo.com/logo.png">
                                
                                <div class="upload-dropzone py-3" data-upload data-url="{{ url('/admin/uploads/branding') }}" data-max-mb="5" id="upload-logo">
                                    <div class="upload-dropzone-inner">
                                        <i class="bi bi-cloud-arrow-up text-primary" style="font-size: 2rem;"></i>
                                        <p class="mb-0 small text-muted">Arraste a logo aqui ou <span class="text-primary fw-semibold">clique</span></p>
                                        <small class="text-muted">PNG, JPG, SVG, WebP, HEIC — Máx 5MB</small>
                                    </div>
                                    <input type="file" accept="image/*,.heic,.heif" hidden>
                                </div>
                                <div data-upload-progress class="mt-2" id="progress-logo"></div>
                            </div>

                            <div class="col-12" id="text-section">
                                <label for="text" class="form-label">Texto de Carregamento</label>
                                <input type="text" class="form-control" id="text" name="text" 
                                       value="{{ $settings->text ?? 'Carregando...' }}" 
                                       placeholder="Carregando...">
                            </div>

                            <div class="col-md-4">
                                <label for="text_color" class="form-label">Cor do Texto</label>
                                <div class="input-group">
                                    <input type="color" class="form-control form-control-color" id="text_color" 
                                           name="text_color" value="{{ $settings->text_color ?? '#ffffff' }}">
                                    <input type="text" class="form-control" value="{{ $settings->text_color ?? '#ffffff' }}" 
                                           id="text_color_text">
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label for="font_family" class="form-label">Fonte</label>
                                <select class="form-select" id="font_family" name="font_family">
                                    <option value="system-ui, -apple-system, sans-serif" {{ $settings->font_family === 'system-ui, -apple-system, sans-serif' ? 'selected' : '' }}>System UI</option>
                                    <option value="'Segoe UI', Tahoma, Geneva, Verdana, sans-serif" {{ str_contains($settings->font_family, 'Segoe') ? 'selected' : '' }}>Segoe UI</option>
                                    <option value="Arial, Helvetica, sans-serif" {{ str_contains($settings->font_family, 'Arial') ? 'selected' : '' }}>Arial</option>
                                    <option value="'Times New Roman', Times, serif" {{ str_contains($settings->font_family, 'Times') ? 'selected' : '' }}>Times New Roman</option>
                                    <option value="'Courier New', Courier, monospace" {{ str_contains($settings->font_family, 'Courier') ? 'selected' : '' }}>Courier New</option>
                                    <option value="Georgia, 'Times New Roman', serif" {{ str_contains($settings->font_family, 'Georgia') ? 'selected' : '' }}>Georgia</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="font_size" class="form-label">Tamanho da Fonte</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="font_size" name="font_size" 
                                           min="10" max="48" value="{{ $settings->font_size ?? 16 }}">
                                    <span class="input-group-text">px</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Barra de Progresso -->
                <div class="card mb-4 shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-bar-chart text-danger"></i>
                            Barra de Progresso
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="show_progress_bar" name="show_progress_bar" 
                                           {{ $settings->show_progress_bar ? 'checked' : '' }}>
                                    <label class="form-check-label" for="show_progress_bar">Exibir Barra de Progresso</label>
                                </div>
                            </div>

                            <div class="col-md-6" id="progress-section">
                                <label for="progress_bar_color" class="form-label">Cor da Barra</label>
                                <div class="input-group">
                                    <input type="color" class="form-control form-control-color" id="progress_bar_color" 
                                           name="progress_bar_color" value="{{ $settings->progress_bar_color ?? '#0d6efd' }}">
                                    <input type="text" class="form-control" value="{{ $settings->progress_bar_color ?? '#0d6efd' }}" 
                                           id="progress_bar_color_text">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CSS e JS Personalizados -->
                <div class="card mb-4 shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-code-slash text-dark"></i>
                            Código Personalizado (Avançado)
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="custom_css" class="form-label">CSS Personalizado</label>
                                <textarea class="form-control font-monospace" id="custom_css" name="custom_css" 
                                          rows="6" placeholder="/* Seu CSS aqui */&#10;.preload-custom {&#10;  /* estilos */&#10;}">{{ $settings->custom_css }}</textarea>
                                <small class="text-muted">Adicione estilos CSS personalizados para o preload</small>
                            </div>

                            <div class="col-12">
                                <label for="custom_js" class="form-label">JavaScript Personalizado</label>
                                <textarea class="form-control font-monospace" id="custom_js" name="custom_js" 
                                          rows="6" placeholder="// Seu JavaScript aqui&#10;console.log('Preload iniciado');&#10;">{{ $settings->custom_js }}</textarea>
                                <small class="text-muted">Adicione scripts JavaScript personalizados</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Status -->
                <div class="card mb-4 shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-toggle-on text-primary"></i>
                            Status
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="enabled" name="enabled" 
                                   {{ $settings->enabled ? 'checked' : '' }}>
                            <label class="form-check-label" for="enabled">
                                <strong>Ativar Tela de Pré-carregamento</strong>
                                <br>
                                <small class="text-muted">Desative para usar o preload padrão do sistema</small>
                            </label>
                        </div>
                    </div>
                </div>

            </form>
        </div>

        <!-- Preview ao Vivo -->
        <div class="col-lg-4">
            <div class="position-sticky" style="top: 1rem;">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-eye"></i>
                            Preview ao Vivo
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div id="preview-container" style="height: 400px; position: relative; overflow: hidden; border-radius: 0 0 0.375rem 0.375rem;">
                            <!-- Preview será renderizado aqui -->
                        </div>
                    </div>
                    <div class="card-footer text-center">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btn-refresh-preview">
                            <i class="bi bi-arrow-clockwise"></i> Atualizar Preview
                        </button>
                    </div>
                </div>

                <div class="alert alert-info mt-3">
                    <i class="bi bi-info-circle"></i>
                    <strong>Dica:</strong> As alterações são aplicadas em tempo real no preview. 
                    Clique em "Salvar Alterações" para aplicar no site.
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
.bg-option {
    display: none;
}
.btn-check:checked + .btn-outline-primary {
    background-color: var(--bs-primary);
    color: white;
}
#preview-container {
    background: #f8f9fa;
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sincronizar color pickers com inputs de texto
    const colorInputs = ['spinner_color', 'accent_color', 'background_color', 'background_gradient_start', 
                         'background_gradient_end', 'text_color', 'progress_bar_color'];
    
    colorInputs.forEach(name => {
        const colorInput = document.getElementById(name);
        const textInput = document.getElementById(name + '_text');
        
        if (colorInput && textInput) {
            colorInput.addEventListener('input', () => {
                textInput.value = colorInput.value;
                updatePreview();
            });
            textInput.addEventListener('input', () => {
                if (/^#[0-9a-fA-F]{6}$/.test(textInput.value)) {
                    colorInput.value = textInput.value;
                    updatePreview();
                }
            });
        }
    });
    // Listener global de upload para autocompletar os campos
    ['upload-bg-image', 'upload-bg-video', 'upload-logo'].forEach(id => {
        const dropzone = document.getElementById(id);
        if (dropzone) {
            dropzone.addEventListener('upload:done', (e) => {
                const url = JSON.parse(e.detail.response).url;
                const targetId = id === 'upload-logo' ? 'logo' : (id === 'upload-bg-video' ? 'background_video' : 'background_image');
                const targetInput = document.getElementById(targetId);
                targetInput.value = url;
                targetInput.dispatchEvent(new Event('input', { bubbles: true }));
                targetInput.dispatchEvent(new Event('change', { bubbles: true }));
            });
        }
    });

    // Atualizar valores dos ranges
    const animationSpeed = document.getElementById('animation_speed');
    const animationSpeedValue = document.getElementById('animation_speed_value');
    animationSpeed?.addEventListener('input', () => {
        animationSpeedValue.textContent = animationSpeed.value + 'ms';
        updatePreview();
    });

    const blurAmount = document.getElementById('blur_amount');
    const blurAmountValue = document.getElementById('blur_amount_value');
    blurAmount?.addEventListener('input', () => {
        blurAmountValue.textContent = blurAmount.value + 'px';
        updatePreview();
    });

    // Controlar visibilidade das opções de background
    function updateBackgroundOptions() {
        const bgType = document.querySelector('input[name="background_type"]:checked')?.value;
        document.querySelectorAll('.bg-option').forEach(el => el.style.display = 'none');
        
        if (bgType === 'solid') {
            document.querySelectorAll('.bg-solid').forEach(el => el.style.display = 'block');
        } else if (bgType === 'gradient') {
            document.querySelectorAll('.bg-gradient').forEach(el => el.style.display = 'block');
        } else if (bgType === 'image') {
            document.querySelectorAll('.bg-image').forEach(el => el.style.display = 'block');
        } else if (bgType === 'video') {
            document.querySelectorAll('.bg-video').forEach(el => el.style.display = 'block');
        }
        updatePreview();
    }

    document.querySelectorAll('input[name="background_type"]').forEach(radio => {
        radio.addEventListener('change', updateBackgroundOptions);
    });
    updateBackgroundOptions();

    // Controlar visibilidade de logo e texto
    document.getElementById('show_logo')?.addEventListener('change', function() {
        document.getElementById('logo-section').style.display = this.checked ? 'block' : 'none';
        updatePreview();
    });

    document.getElementById('show_text')?.addEventListener('change', function() {
        document.getElementById('text-section').style.display = this.checked ? 'block' : 'none';
        updatePreview();
    });

    document.getElementById('show_progress_bar')?.addEventListener('change', function() {
        document.getElementById('progress-section').style.display = this.checked ? 'block' : 'none';
        updatePreview();
    });

    // Inicializar visibilidade
    document.getElementById('logo-section').style.display = 
        document.getElementById('show_logo').checked ? 'block' : 'none';
    document.getElementById('text-section').style.display = 
        document.getElementById('show_text').checked ? 'block' : 'none';
    document.getElementById('progress-section').style.display = 
        document.getElementById('show_progress_bar').checked ? 'block' : 'none';

    // Atualizar preview em tempo real
    document.querySelectorAll('#form-preload input, #form-preload select, #form-preload textarea').forEach(input => {
        input.addEventListener('input', updatePreview);
        input.addEventListener('change', updatePreview);
    });

    // Função para atualizar o preview
    function updatePreview() {
        const formData = new FormData(document.getElementById('form-preload'));
        const data = Object.fromEntries(formData.entries());
        
        // Converter checkboxes
        ['show_logo', 'show_text', 'show_progress_bar', 'blur_background', 'enabled'].forEach(key => {
            data[key] = formData.has(key);
        });

        renderPreview(data);
    }

    // Renderizar preview
    function renderPreview(data) {
        const container = document.getElementById('preview-container');
        
        // Background
        let bgStyle = '';
        if (data.background_type === 'solid') {
            bgStyle = `background: ${data.background_color};`;
        } else if (data.background_type === 'gradient') {
            bgStyle = `background: linear-gradient(${data.background_gradient_angle}deg, ${data.background_gradient_start}, ${data.background_gradient_end});`;
        } else if (data.background_type === 'image' && data.background_image) {
            bgStyle = `background: url('${data.background_image}') center/cover no-repeat;`;
            if (data.blur_background) {
                bgStyle += `backdrop-filter: blur(${data.blur_amount}px);`;
            }
        } else if (data.background_type === 'video' && data.background_video) {
            bgStyle = `background: #000;`;
        }

        // Gerar HTML do spinner baseado no estilo
        let spinnerHTML = '';
        const animDuration = (data.animation_speed / 1000) + 's';
        
        if (data.style === 'spinner') {
            spinnerHTML = `<div class="spinner-border" style="color: ${data.spinner_color}; width: 3rem; height: 3rem; animation-duration: ${animDuration};" role="status"></div>`;
        } else if (data.style === 'pulse') {
            spinnerHTML = `<div class="pulse-loader" style="width: 60px; height: 60px; background: ${data.spinner_color}; border-radius: 50%; animation: pulse ${animDuration} ease-in-out infinite;"></div>`;
        } else if (data.style === 'dots') {
            spinnerHTML = `<div class="dots-loader" style="display: flex; gap: 10px;">
                <div style="width: 15px; height: 15px; background: ${data.spinner_color}; border-radius: 50%; animation: bounce ${animDuration} ease-in-out infinite;"></div>
                <div style="width: 15px; height: 15px; background: ${data.spinner_color}; border-radius: 50%; animation: bounce ${animDuration} ease-in-out 0.2s infinite;"></div>
                <div style="width: 15px; height: 15px; background: ${data.spinner_color}; border-radius: 50%; animation: bounce ${animDuration} ease-in-out 0.4s infinite;"></div>
            </div>`;
        } else if (data.style === 'wave') {
            spinnerHTML = `<div class="wave-loader" style="display: flex; gap: 5px; align-items: flex-end; height: 40px;">
                ${[0, 0.1, 0.2, 0.3, 0.4].map(delay => 
                    `<div style="width: 8px; background: ${data.spinner_color}; animation: wave ${animDuration} ease-in-out ${delay}s infinite;"></div>`
                ).join('')}
            </div>`;
        } else if (data.style === 'gradient') {
            spinnerHTML = `<div style="width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, ${data.spinner_color}, ${data.accent_color}); animation: rotate ${animDuration} linear infinite;"></div>`;
        } else if (data.style === 'particles') {
            spinnerHTML = `<div class="particles-loader" style="position: relative; width: 100px; height: 100px;">
                ${[0, 45, 90, 135, 180, 225, 270, 315].map((angle, i) => 
                    `<div style="position: absolute; width: 10px; height: 10px; background: ${data.spinner_color}; border-radius: 50%; 
                    top: 50%; left: 50%; transform: rotate(${angle}deg) translateX(30px); 
                    animation: particle ${animDuration} ease-in-out ${i * 0.1}s infinite;"></div>`
                ).join('')}
            </div>`;
        } else if (data.style === 'logo' && data.logo) {
            spinnerHTML = `<img src="${data.logo}" alt="Logo" style="max-width: 120px; max-height: 120px; animation: pulse ${animDuration} ease-in-out infinite;">`;
        }

        // Montar HTML completo
        let html = `
            <div style="${bgStyle} width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; font-family: ${data.font_family};">
                ${data.background_type === 'video' && data.background_video ? 
                    `<video autoplay muted loop style="position: absolute; width: 100%; height: 100%; object-fit: cover; opacity: 0.5;">
                        <source src="${data.background_video}" type="video/mp4">
                    </video>` : ''}
                
                <div style="position: relative; z-index: 1; text-align: center;">
                    ${data.show_logo && data.logo && data.style !== 'logo' ? 
                        `<img src="${data.logo}" alt="Logo" style="max-width: 100px; max-height: 100px; margin-bottom: 20px;">` : ''}
                    
                    ${spinnerHTML}
                    
                    ${data.show_text && data.text ? 
                        `<p style="color: ${data.text_color}; font-size: ${data.font_size}px; margin-top: 20px; margin-bottom: 0;">${data.text}</p>` : ''}
                </div>
                
                ${data.show_progress_bar ? 
                    `<div style="position: absolute; bottom: 0; left: 0; right: 0; height: 4px; background: rgba(255,255,255,0.2);">
                        <div style="height: 100%; background: ${data.progress_bar_color}; width: 60%; animation: progress 2s ease-in-out infinite;"></div>
                    </div>` : ''}
            </div>
        `;

        container.innerHTML = html;
    }

    // Botão de salvar
    document.getElementById('btn-save')?.addEventListener('click', async function() {
        const btn = this;
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Salvando...';

        try {
            const formData = new FormData(document.getElementById('form-preload'));
            
            // Converter checkboxes para valores booleanos
            ['show_logo', 'show_text', 'show_progress_bar', 'blur_background', 'enabled'].forEach(key => {
                formData.set(key, formData.has(key) ? '1' : '0');
            });

            const response = await fetch('{{ route("admin.settings.preload.update") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                window.toast?.success(data.message || 'Configurações salvas com sucesso!');
            } else {
                if (data.errors) {
                    let errMsgs = [];
                    Object.keys(data.errors).forEach(key => {
                        errMsgs.push(`<strong>${key}</strong>: ${data.errors[key].join(', ')}`);
                    });
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro de Validação',
                        html: errMsgs.join('<br>'),
                        confirmButtonColor: 'var(--bs-primary)',
                        confirmButtonText: 'Entendido'
                    });
                } else {
                    window.toast?.error(data.message || 'Erro ao salvar configurações');
                }
            }
        } catch (error) {
            console.error('Erro:', error);
            window.toast?.error('Erro ao salvar configurações');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    });

    // Botão de preview em nova janela
    document.getElementById('btn-preview')?.addEventListener('click', function() {
        const formData = new FormData(document.getElementById('form-preload'));
        const params = new URLSearchParams(formData);
        window.open('{{ route("admin.settings.preload.preview") }}?' + params.toString(), 
                    'PreloadPreview', 
                    'width=800,height=600');
    });

    // Botão de refresh preview
    document.getElementById('btn-refresh-preview')?.addEventListener('click', updatePreview);

    // Inicializar preview
    updatePreview();

    // Adicionar estilos de animação
    const style = document.createElement('style');
    style.textContent = `
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.7; }
        }
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
        @keyframes wave {
            0%, 100% { height: 10px; }
            50% { height: 40px; }
        }
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        @keyframes particle {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.3; transform: scale(0.5); }
        }
        @keyframes progress {
            0% { width: 0%; }
            100% { width: 100%; }
        }
    `;
    document.head.appendChild(style);
});
</script>
@endpush
