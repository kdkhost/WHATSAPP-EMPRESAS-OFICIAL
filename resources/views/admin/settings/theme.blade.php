@extends('admin.layouts.app')
@section('title', 'Tema e Cores')

@section('content')
<div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="mb-1"><i class="bi bi-palette me-2"></i>Tema e Cores</h3>
            <p class="text-muted mb-0 small">Personalize a paleta de cores do sistema. Alterações aplicam em tempo real.</p>
        </div>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-reset">
            <i class="bi bi-arrow-counterclockwise me-1"></i>Restaurar Padrão
        </button>
    </div>

    <form id="theme-form">
        @csrf
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header">
                        <span><i class="bi bi-brush me-1"></i> Cores Principais</span>
                        <button type="submit" class="btn btn-primary btn-sm" id="btn-save">
                            <i class="bi bi-check2-circle me-1"></i>Salvar
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            @php
                                $colors = [
                                    'primary' => ['label' => 'Primária', 'desc' => 'Botões, links e destaques'],
                                    'secondary' => ['label' => 'Secundária', 'desc' => 'Elementos auxiliares'],
                                    'success' => ['label' => 'Sucesso', 'desc' => 'Confirmações e status OK'],
                                    'danger' => ['label' => 'Perigo', 'desc' => 'Erros, exclusões e alertas'],
                                    'warning' => ['label' => 'Alerta', 'desc' => 'Avisos e pendências'],
                                    'info' => ['label' => 'Info', 'desc' => 'Informações e dicas'],
                                    'dark' => ['label' => 'Escuro', 'desc' => 'Base visual do modo escuro'],
                                    'light' => ['label' => 'Claro', 'desc' => 'Base visual do modo claro'],
                                ];
                            @endphp
                            @foreach($colors as $key => $meta)
                                <div class="col-md-3 col-6">
                                    <div class="text-center">
                                        <input type="color" class="form-control form-control-color mx-auto mb-1" name="{{ $key }}"
                                               value="{{ $theme[$key] }}" style="width:48px;height:48px;border-radius:50%;padding:2px;cursor:pointer;">
                                        <div class="fw-semibold small">{{ $meta['label'] }}</div>
                                        <div class="text-muted" style="font-size:0.65rem;">{{ $meta['desc'] }}</div>
                                        <input type="text" class="form-control form-control-sm text-center mt-1" value="{{ $theme[$key] }}"
                                               data-sync="{{ $key }}" maxlength="7" style="font-size:0.7rem;font-family:monospace;">
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header">
                        <span><i class="bi bi-layout-sidebar me-1"></i> Cores do Layout</span>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            @php
                                $layout = [
                                    'sidebar_bg' => ['label' => 'Fundo Sidebar', 'icon' => 'bi-layout-sidebar'],
                                    'sidebar_active' => ['label' => 'Item Ativo Sidebar', 'icon' => 'bi-cursor'],
                                    'navbar_bg' => ['label' => 'Fundo Navbar', 'icon' => 'bi-window'],
                                    'body_bg' => ['label' => 'Fundo da Página', 'icon' => 'bi-square'],
                                ];
                            @endphp
                            @foreach($layout as $key => $meta)
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center gap-3 p-2 border rounded bg-body-tertiary">
                                        <input type="color" class="form-control form-control-color flex-shrink-0" name="{{ $key }}"
                                               value="{{ $theme[$key] }}" style="width:40px;height:40px;border-radius:8px;padding:2px;">
                                        <div class="flex-grow-1">
                                            <div class="fw-semibold small"><i class="bi {{ $meta['icon'] }} me-1"></i>{{ $meta['label'] }}</div>
                                            <input type="text" class="form-control form-control-sm mt-1" value="{{ $theme[$key] }}"
                                                   data-sync="{{ $key }}" maxlength="7" style="font-size:0.7rem;font-family:monospace;">
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-reset-2">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Restaurar
                        </button>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-check2-circle me-1"></i>Salvar Cores
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header">
                        <span><i class="bi bi-eye me-1"></i> Preview ao Vivo</span>
                    </div>
                    <div class="card-body p-0">
                        <div id="theme-preview" class="p-3">
                            <div class="d-flex rounded overflow-hidden border" style="min-height:200px;">
                                <div id="prev-sidebar" class="p-2 d-flex flex-column gap-1" style="width:70px;background:{{ $theme['sidebar_bg'] }};">
                                    <div class="rounded-1 px-2 py-1" style="font-size:0.55rem;color:rgba(255,255,255,.6);">Menu</div>
                                    <div class="rounded-1 px-2 py-1" id="prev-sidebar-active" style="font-size:0.55rem;color:#fff;background:{{ $theme['sidebar_active'] }};">Ativo</div>
                                    <div class="rounded-1 px-2 py-1" style="font-size:0.55rem;color:rgba(255,255,255,.6);">Item</div>
                                    <div class="rounded-1 px-2 py-1" style="font-size:0.55rem;color:rgba(255,255,255,.6);">Item</div>
                                </div>
                                <div class="flex-grow-1 d-flex flex-column" id="prev-body" style="background:{{ $theme['body_bg'] }};">
                                    <div id="prev-navbar" class="p-2 border-bottom" style="background:{{ $theme['navbar_bg'] }};font-size:0.6rem;">
                                        <span class="text-muted">Navbar</span>
                                    </div>
                                    <div class="p-2 flex-grow-1">
                                        <div class="d-flex gap-1 flex-wrap mb-2">
                                            <span class="badge" id="pv-primary" style="background:{{ $theme['primary'] }};color:#fff;">Primary</span>
                                            <span class="badge" id="pv-success" style="background:{{ $theme['success'] }};color:#fff;">Success</span>
                                            <span class="badge" id="pv-danger" style="background:{{ $theme['danger'] }};color:#fff;">Danger</span>
                                            <span class="badge" id="pv-warning" style="background:{{ $theme['warning'] }};color:#000;">Warning</span>
                                            <span class="badge" id="pv-info" style="background:{{ $theme['info'] }};color:#000;">Info</span>
                                        </div>
                                        <div class="border rounded p-2 bg-white" style="font-size:0.6rem;">
                                            <div class="text-muted">Card de conteúdo</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header">
                        <span><i class="bi bi-stars me-1"></i> Presets Rápidos</span>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-2">Clique para aplicar um preset de cores:</p>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-sm btn-outline-primary preset-btn" data-preset="default">
                                <span class="d-inline-block rounded-circle me-1" style="width:12px;height:12px;background:#0d6efd;"></span>Padrão
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-success preset-btn" data-preset="green">
                                <span class="d-inline-block rounded-circle me-1" style="width:12px;height:12px;background:#198754;"></span>Verde
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger preset-btn" data-preset="red">
                                <span class="d-inline-block rounded-circle me-1" style="width:12px;height:12px;background:#dc3545;"></span>Vermelho
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary preset-btn" data-preset="purple">
                                <span class="d-inline-block rounded-circle me-1" style="width:12px;height:12px;background:#6f42c1;"></span>Roxo
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-warning preset-btn" data-preset="orange">
                                <span class="d-inline-block rounded-circle me-1" style="width:12px;height:12px;background:#fd7e14;"></span>Laranja
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-info preset-btn" data-preset="teal">
                                <span class="d-inline-block rounded-circle me-1" style="width:12px;height:12px;background:#20c997;"></span>Teal
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header">
                        <span><i class="bi bi-info-circle me-1"></i> Dicas</span>
                    </div>
                    <div class="card-body small text-muted">
                        <div class="d-flex gap-2 mb-2">
                            <i class="bi bi-check-circle-fill text-success flex-shrink-0"></i>
                            <span>As cores são aplicadas <strong>em tempo real</strong> enquanto você ajusta.</span>
                        </div>
                        <div class="d-flex gap-2 mb-2">
                            <i class="bi bi-check-circle-fill text-success flex-shrink-0"></i>
                            <span>Clique em <strong>Salvar</strong> para persistir as alterações.</span>
                        </div>
                        <div class="d-flex gap-2">
                            <i class="bi bi-check-circle-fill text-success flex-shrink-0"></i>
                            <span>Use <strong>Restaurar Padrão</strong> para voltar às cores originais.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var form = document.getElementById('theme-form');
    var defaults = {
        primary: '#5b4df6',
        secondary: '#7c3aed',
        success: '#198754',
        danger: '#dc3545',
        warning: '#ffc107',
        info: '#0dcaf0',
        dark: '#212529',
        light: '#f8f9fa',
        sidebar_bg: '#1a1d21',
        sidebar_active: '#5b4df6',
        navbar_bg: '#f8fafc',
        body_bg: '#eef2f7'
    };
    var presets = {
        default: defaults,
        green: { ...defaults, primary: '#198754', secondary: '#157347', sidebar_active: '#198754' },
        red: { ...defaults, primary: '#dc3545', secondary: '#be123c', sidebar_active: '#dc3545' },
        purple: { ...defaults, primary: '#5b4df6', secondary: '#7c3aed', sidebar_active: '#5b4df6' },
        orange: { ...defaults, primary: '#fd7e14', warning: '#fd7e14', sidebar_active: '#fd7e14' },
        teal: { ...defaults, primary: '#20c997', info: '#20c997', sidebar_active: '#20c997' }
    };

    function hexToRgb(hex) {
        var value = String(hex || '').replace('#', '');
        if (value.length === 3) {
            value = value.split('').map(function (part) { return part + part; }).join('');
        }
        if (value.length !== 6) {
            return null;
        }

        return [
            parseInt(value.slice(0, 2), 16),
            parseInt(value.slice(2, 4), 16),
            parseInt(value.slice(4, 6), 16)
        ];
    }

    function luminance(hex) {
        var rgb = hexToRgb(hex);
        if (!rgb) return 1;
        return [rgb[0], rgb[1], rgb[2]].map(function (channel) {
            var value = channel / 255;
            return value <= 0.03928 ? value / 12.92 : Math.pow((value + 0.055) / 1.055, 2.4);
        }).reduce(function (acc, value, index) {
            return acc + value * [0.2126, 0.7152, 0.0722][index];
        }, 0);
    }

    function isDark(hex) {
        return luminance(hex) < 0.42;
    }

    function mixColors(foreground, background, ratio) {
        var fg = hexToRgb(foreground);
        var bg = hexToRgb(background);
        if (!fg || !bg) return background;
        var mix = function (from, to) {
            return Math.round((from * ratio) + (to * (1 - ratio)));
        };
        return '#' + [mix(fg[0], bg[0]), mix(fg[1], bg[1]), mix(fg[2], bg[2])].map(function (value) {
            return value.toString(16).padStart(2, '0');
        }).join('');
    }

    function contrastText(hex, dark, light) {
        return isDark(hex) ? (light || '#f8fafc') : (dark || '#0f172a');
    }

    function setColorFamily(root, name, value, subtleAlpha, borderAlpha) {
        var rgb = hexToRgb(value);
        root.style.setProperty('--' + name, value);
        if (!rgb) {
            return;
        }

        root.style.setProperty('--' + name + '-rgb', rgb.join(', '));
        root.style.setProperty('--' + name + '-bg-subtle', 'rgba(' + rgb.join(', ') + ', ' + subtleAlpha + ')');
        root.style.setProperty('--' + name + '-border-subtle', 'rgba(' + rgb.join(', ') + ', ' + borderAlpha + ')');
    }

    form.querySelectorAll('input[type=color]').forEach(function (picker) {
        var text = form.querySelector('[data-sync="' + picker.name + '"]');
        if (!text) return;

        picker.addEventListener('input', function () {
            text.value = picker.value;
            updatePreview();
            applyLive();
        });

        text.addEventListener('input', function () {
            if (/^#[0-9a-f]{6}$/i.test(text.value)) {
                picker.value = text.value;
                updatePreview();
                applyLive();
            }
        });
    });

    function getValues() {
        var values = {};
        form.querySelectorAll('input[type=color]').forEach(function (el) {
            values[el.name] = el.value;
        });
        return values;
    }

    function setValues(values) {
        Object.keys(values).forEach(function (key) {
            var picker = form.querySelector('input[type=color][name="' + key + '"]');
            var text = form.querySelector('[data-sync="' + key + '"]');
            if (picker) picker.value = values[key];
            if (text) text.value = values[key];
        });
        updatePreview();
        applyLive();
    }

    function updatePreview() {
        var values = getValues();
        var sidebarText = contrastText(values.sidebar_bg);
        var sidebarMuted = isDark(values.sidebar_bg) ? mixColors('#f8fafc', values.sidebar_bg, 0.72) : mixColors('#0f172a', values.sidebar_bg, 0.58);
        var activeText = contrastText(values.sidebar_active);
        var navbarText = contrastText(values.navbar_bg);
        var bodyText = contrastText(values.body_bg, '#111827', '#f8fafc');
        var cardSurface = isDark(values.body_bg) ? mixColors('#ffffff', values.body_bg, 0.06) : '#ffffff';

        document.getElementById('prev-sidebar').style.background = values.sidebar_bg;
        document.getElementById('prev-sidebar').style.color = sidebarMuted;
        document.getElementById('prev-sidebar-active').style.background = values.sidebar_active;
        document.getElementById('prev-sidebar-active').style.color = activeText;
        document.getElementById('prev-navbar').style.background = values.navbar_bg;
        document.getElementById('prev-navbar').style.color = navbarText;
        document.getElementById('prev-body').style.background = values.body_bg;
        document.getElementById('prev-body').style.color = bodyText;
        document.getElementById('pv-primary').style.background = values.primary;
        document.getElementById('pv-success').style.background = values.success;
        document.getElementById('pv-danger').style.background = values.danger;
        document.getElementById('pv-warning').style.background = values.warning;
        document.getElementById('pv-info').style.background = values.info;
        var previewCard = document.querySelector('#theme-preview .border.rounded.p-2');
        if (previewCard) {
            previewCard.style.background = cardSurface;
            previewCard.style.color = bodyText;
        }
    }

    function applyLive() {
        var values = getValues();
        var root = document.documentElement;

        setColorFamily(root, 'bs-primary', values.primary, '.16', '.38');
        setColorFamily(root, 'bs-secondary', values.secondary, '.14', '.34');
        setColorFamily(root, 'bs-success', values.success, '.16', '.34');
        setColorFamily(root, 'bs-danger', values.danger, '.16', '.34');
        setColorFamily(root, 'bs-warning', values.warning, '.18', '.38');
        setColorFamily(root, 'bs-info', values.info, '.16', '.34');

        root.style.setProperty('--bs-dark', values.dark);
        root.style.setProperty('--bs-light', values.light);
        root.style.setProperty('--app-sidebar-bg', values.sidebar_bg);
        root.style.setProperty('--app-sidebar-color', contrastText(values.sidebar_bg));
        root.style.setProperty('--app-sidebar-muted', isDark(values.sidebar_bg) ? mixColors('#f8fafc', values.sidebar_bg, 0.72) : mixColors('#0f172a', values.sidebar_bg, 0.58));
        root.style.setProperty('--app-sidebar-active', values.sidebar_active);
        root.style.setProperty('--app-sidebar-active-color', contrastText(values.sidebar_active));
        root.style.setProperty('--app-sidebar-hover', isDark(values.sidebar_bg) ? 'rgba(255,255,255,.07)' : 'rgba(15,23,42,.06)');
        root.style.setProperty('--app-sidebar-tree-active', isDark(values.sidebar_bg) ? 'rgba(255,255,255,.10)' : 'rgba(15,23,42,.08)');
        root.style.setProperty('--app-sidebar-brand-bg', isDark(values.sidebar_bg) ? mixColors('#000000', values.sidebar_bg, 0.14) : mixColors('#ffffff', values.sidebar_bg, 0.18));
        root.style.setProperty('--app-sidebar-border', isDark(values.sidebar_bg) ? 'rgba(255,255,255,.06)' : 'rgba(15,23,42,.08)');
        root.style.setProperty('--app-sidebar-scrollbar', isDark(values.sidebar_bg) ? 'rgba(255,255,255,.16)' : 'rgba(15,23,42,.18)');
        root.style.setProperty('--app-navbar-bg', values.navbar_bg);
        root.style.setProperty('--app-navbar-color', contrastText(values.navbar_bg));
        root.style.setProperty('--app-navbar-muted', isDark(values.navbar_bg) ? mixColors('#f8fafc', values.navbar_bg, 0.72) : mixColors('#0f172a', values.navbar_bg, 0.58));
        root.style.setProperty('--app-navbar-border', isDark(values.navbar_bg) ? 'rgba(255,255,255,.08)' : 'rgba(15,23,42,.08)');
        root.style.setProperty('--app-body-bg', values.body_bg);
        root.style.setProperty('--app-body-color', contrastText(values.body_bg, '#111827', '#f8fafc'));
        root.style.setProperty('--app-body-muted', isDark(values.body_bg) ? mixColors('#f8fafc', values.body_bg, 0.74) : mixColors('#0f172a', values.body_bg, 0.58));
        root.style.setProperty('--app-surface-bg', isDark(values.body_bg) ? mixColors('#ffffff', values.body_bg, 0.06) : '#ffffff');
        root.style.setProperty('--app-surface-soft', isDark(values.body_bg) ? mixColors('#ffffff', values.body_bg, 0.10) : mixColors('#ffffff', values.body_bg, 0.78));
        root.style.setProperty('--app-surface-border', isDark(values.body_bg) ? 'rgba(255,255,255,.10)' : 'rgba(15,23,42,.08)');
    }

    document.querySelectorAll('.preset-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            setValues(presets[btn.dataset.preset] || defaults);
        });
    });

    document.getElementById('btn-reset').addEventListener('click', function () {
        setValues(defaults);
    });

    document.getElementById('btn-reset-2').addEventListener('click', function () {
        setValues(defaults);
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = document.getElementById('btn-save');
        var original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Salvando...';

        fetch('{{ route('admin.settings.theme.update') }}', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                'Accept': 'application/json'
            },
            body: JSON.stringify(getValues())
        })
        .then(function (response) { return response.json(); })
        .then(function (data) {
            btn.disabled = false;
            btn.innerHTML = original;

            if (data.ok) {
                window.toast.success('Cores salvas com sucesso.');
            } else {
                window.toast.error('Erro ao salvar as cores.');
            }
        })
        .catch(function () {
            btn.disabled = false;
            btn.innerHTML = original;
            window.toast.error('Erro de conexão.');
        });
    });

    updatePreview();
    applyLive();
})();
</script>
@endpush
