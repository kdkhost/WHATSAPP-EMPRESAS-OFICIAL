/*
 * admin.js - Funcionalidades globais do painel administrativo.
 *
 * - SweetAlert2 para confirmacoes (substitui alert/confirm nativo)
 * - Toastify para notificacoes (substitui toasts bootstrap)
 * - Mascaras automaticas (moeda, CPF/CNPJ, telefone, data, CEP)
 * - ViaCEP com auto-preenchimento e foco no campo numero
 * - Upload arrastar-soltar global com barra animada + tempo restante
 * - Toggle dark/light com persistencia
 * - CRUD AJAX helper
 *
 * Sem dependencia de jQuery. UTF-8 sem BOM.
 */
(function () {
    'use strict';

    // ============================================================
    // 0) GLOBALS: SweetAlert2 + Toastify wrappers
    // ============================================================

    /**
     * window.toast - Notificacoes globais via Toastify.
     * Uso: window.toast.success('Salvo!'), window.toast.error('Falha.')
     */
    window.toast = {
        _show(message, type) {
            if (typeof Toastify === 'undefined') { console.warn('[toast]', message); return; }
            const colors = {
                success: 'linear-gradient(to right, #198754, #157347)',
                error:   'linear-gradient(to right, #dc3545, #b02a37)',
                warning: 'linear-gradient(to right, #ffc107, #e0a800)',
                info:    'linear-gradient(to right, #0dcaf0, #0aa2c0)',
            };
            Toastify({
                text: message,
                duration: 4000,
                gravity: 'top',
                position: 'right',
                stopOnFocus: true,
                style: { background: colors[type] || colors.info },
                close: true,
            }).showToast();
        },
        success: (m) => window.toast._show(m, 'success'),
        error:   (m) => window.toast._show(m, 'error'),
        warning: (m) => window.toast._show(m, 'warning'),
        info:    (m) => window.toast._show(m, 'info'),
    };

    /**
     * window.confirm2 - Confirmacoes globais via SweetAlert2.
     * Retorna Promise<boolean>.
     * Uso: const ok = await window.confirm2('Tem certeza?', 'Essa acao e irreversivel.');
     */
    window.confirm2 = function (title, text, opts = {}) {
        if (typeof Swal === 'undefined') { return Promise.resolve(confirm(title)); }
        return Swal.fire({
            title: title || 'Tem certeza?',
            text: text || '',
            icon: opts.icon || 'warning',
            showCancelButton: true,
            confirmButtonColor: opts.confirmColor || '#dc3545',
            cancelButtonColor: opts.cancelColor || '#6c757d',
            confirmButtonText: opts.confirmText || 'Sim, confirmar',
            cancelButtonText: opts.cancelText || 'Cancelar',
            reverseButtons: true,
            ...opts,
        }).then(r => r.isConfirmed);
    };

    /**
     * window.alert2 - Alerta global via SweetAlert2 (substitui alert()).
     * Uso: window.alert2('Sucesso!', 'Operacao concluida.', 'success');
     */
    // Interceptação global de alert nativo para usar SweetAlert2
    window.alert = function (message) {
        if (typeof Swal !== 'undefined') {
            return Swal.fire({
                title: 'Atenção',
                text: String(message || ''),
                icon: 'info',
                confirmButtonColor: '#3085d6',
                confirmButtonText: 'OK'
            });
        }
        console.warn('[Alerta Interceptado]', message);
    };

    window.alert2 = function (title, text, icon) {
        if (typeof Swal === 'undefined') {
            console.warn('[alert2 fallback]', title, text);
            return Promise.resolve();
        }
        return Swal.fire({ title, text: text || '', icon: icon || 'info', confirmButtonText: 'OK' });
    };

    // ============================================================
    // 1) MASCARAS AUTOMATICAS
    // ============================================================
    const masks = {
        money(v) {
            v = (v || '').replace(/\D/g, '');
            v = (parseInt(v || '0', 10) / 100).toFixed(2);
            return 'R$ ' + v.replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        },
        decimal(v) {
            v = String(v || '').replace(/[^\d,.-]/g, '').replace(/\./g, ',');
            const negative = v.startsWith('-') ? '-' : '';
            v = v.replace(/-/g, '');
            const hasComma = v.includes(',');
            const parts = v.split(',');
            const integer = (parts.shift() || '').replace(/\D/g, '').replace(/^0+(?=\d)/, '') || '0';
            const decimal = parts.join('').replace(/\D/g, '').slice(0, 2);
            return negative + integer + (hasComma ? ',' + decimal : '');
        },
        cpf(v) {
            v = (v || '').replace(/\D/g, '').slice(0, 11);
            return v.replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        },
        cnpj(v) {
            v = (v || '').replace(/\D/g, '').slice(0, 14);
            return v.replace(/(\d{2})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1/$2').replace(/(\d{4})(\d{1,2})$/, '$1-$2');
        },
        document(v) {
            const digits = (v || '').replace(/\D/g, '');
            return digits.length <= 11 ? masks.cpf(v) : masks.cnpj(v);
        },
        phone(v) {
            v = (v || '').replace(/\D/g, '').slice(0, 11);
            if (v.length <= 10) return v.replace(/(\d{2})(\d)/, '($1) $2').replace(/(\d{4})(\d)/, '$1-$2');
            return v.replace(/(\d{2})(\d)/, '($1) $2').replace(/(\d{5})(\d)/, '$1-$2');
        },
        cep(v) {
            v = (v || '').replace(/\D/g, '').slice(0, 8);
            return v.replace(/(\d{5})(\d)/, '$1-$2');
        },
        date(v) {
            v = (v || '').replace(/\D/g, '').slice(0, 8);
            return v.replace(/(\d{2})(\d)/, '$1/$2').replace(/(\d{2})(\d)/, '$1/$2');
        },
        time(v) {
            v = (v || '').replace(/\D/g, '').slice(0, 4);
            return v.replace(/(\d{2})(\d)/, '$1:$2');
        },
    };

    function parseMoney(value) {
        let v = String(value || '').trim();
        if (!v) return '0.00';

        v = v.replace(/[^\d,.-]/g, '');
        if (!v || v === '-' || v === ',' || v === '.') return '0.00';

        const negative = v.startsWith('-');
        v = v.replace(/-/g, '');

        const lastComma = v.lastIndexOf(',');
        const lastDot = v.lastIndexOf('.');

        if (lastComma !== -1 && lastDot !== -1) {
            const decimalSeparator = lastComma > lastDot ? ',' : '.';
            const thousandSeparator = decimalSeparator === ',' ? '.' : ',';
            v = v.split(thousandSeparator).join('');
            v = v.replace(decimalSeparator, '.');
        } else if (lastComma !== -1) {
            v = v.split('.').join('').replace(',', '.');
        } else if (lastDot !== -1) {
            const parts = v.split('.');
            const fraction = parts[parts.length - 1] || '';
            const hasMultipleDots = parts.length > 2;
            const looksLikeThousandOnly = !hasMultipleDots && fraction.length === 3 && parts[0].length <= 3;
            if (hasMultipleDots || looksLikeThousandOnly) {
                v = parts.join('');
            }
        }

        const parsed = Number.parseFloat(v);
        const normalized = Number.isFinite(parsed) ? parsed : 0;

        return (negative ? -normalized : normalized).toFixed(2);
    }

    function formatMoney(value) {
        const parsed = Number.parseFloat(parseMoney(value));
        const normalized = Number.isFinite(parsed) ? parsed : 0;

        return normalized.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }

    function applyMask(input) {
        const type = input.dataset.mask;
        if (!type || !masks[type]) return;
        const handler = () => { input.value = masks[type](input.value); };
        input.addEventListener('input', handler);
        if (input.value) handler();
    }

    function bindMasks(scope) { (scope || document).querySelectorAll('input[data-mask]').forEach(applyMask); }

    // ============================================================
    // 2) ViaCEP - busca automatica + foco no campo numero
    // ============================================================
    async function lookupCep(cep, container) {
        const digits = cep.replace(/\D/g, '');
        if (digits.length !== 8) return;
        try {
            const r = await fetch(`/api/v1/cep/${digits}`, { headers: { 'Accept': 'application/json' }});
            if (!r.ok) return;
            const d = await r.json();
            const map = { logradouro: d.logradouro, complemento: d.complemento, bairro: d.bairro, cidade: d.cidade, uf: d.uf };
            for (const [k, v] of Object.entries(map)) {
                const el = container.querySelector(`[data-cep-fill="${k}"], [name="${k}"]`);
                if (el && v) el.value = v;
            }
            const numero = container.querySelector('[data-cep-fill="numero"], [name="numero"]');
            if (numero) { numero.focus(); numero.select?.(); }
        } catch (e) { console.warn('ViaCEP error', e); }
    }

    function bindCep(scope) {
        (scope || document).querySelectorAll('input[data-cep], input[name="cep"]').forEach(input => {
            const container = input.closest('[data-address-block]') || input.closest('form') || document;
            input.addEventListener('blur', () => lookupCep(input.value, container));
        });
    }

    // ============================================================
    // 3) UPLOAD DRAG-DROP com progresso animado + tempo restante
    // ============================================================
    function fmtSize(b) {
        if (b < 1024) return b + ' B';
        if (b < 1048576) return (b/1024).toFixed(1) + ' KB';
        return (b/1048576).toFixed(1) + ' MB';
    }
    function fmtTime(s) {
        if (!isFinite(s) || s <= 0) return '--';
        if (s < 60) return Math.ceil(s) + 's';
        return Math.floor(s/60) + 'm ' + Math.ceil(s%60) + 's';
    }

    function escapeHtml(s) {
        return String(s || '').replace(/[&<>"']/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;' }[c]));
    }

    function uploadIconFor(file) {
        if (file.type?.startsWith('image/')) return 'bi-file-earmark-image text-primary';
        if (file.type === 'application/pdf') return 'bi-file-earmark-pdf text-danger';
        if (file.type?.includes('spreadsheet') || file.type === 'text/csv') return 'bi-file-earmark-spreadsheet text-success';
        if (file.type?.startsWith('video/')) return 'bi-file-earmark-play text-info';
        return 'bi-file-earmark text-secondary';
    }

    function ensureUploadScaffold(zone) {
        const host = zone.parentElement || zone;
        let progress = zone.querySelector('[data-upload-progress]') || host.querySelector('[data-upload-progress]');
        let preview = zone.querySelector('[data-upload-preview]') || host.querySelector('[data-upload-preview]');
        let done = zone.querySelector('[data-upload-done]') || host.querySelector('[data-upload-done]');

        if (!preview) {
            preview = document.createElement('div');
            preview.className = 'upload-preview-grid mt-3';
            preview.dataset.uploadPreview = '';
            preview.hidden = true;
            if (progress) progress.before(preview);
            else zone.appendChild(preview);
        }

        if (!progress) {
            progress = document.createElement('div');
            progress.className = 'upload-progress-list mt-3';
            progress.dataset.uploadProgress = '';
            preview.after(progress);
        }

        if (!done) {
            done = document.createElement('div');
            done.className = 'upload-done-list mt-3';
            done.dataset.uploadDone = '';
            done.hidden = true;
            progress.after(done);
        }

        return { preview, progress, done };
    }

    function renderSelectedPreview(zone, files) {
        const { preview } = ensureUploadScaffold(zone);
        const selected = Array.from(files || []);
        preview.innerHTML = '';
        preview.hidden = selected.length === 0;
        preview.style.display = selected.length === 0 ? 'none' : '';

        selected.forEach(file => {
            const item = document.createElement('div');
            item.className = 'upload-preview-card';

            if (file.type.startsWith('image/')) {
                const img = document.createElement('img');
                img.alt = file.name;
                const reader = new FileReader();
                reader.onload = (event) => { img.src = event.target.result; };
                reader.readAsDataURL(file);
                item.appendChild(img);
            } else {
                item.insertAdjacentHTML('beforeend', `<i class="bi ${uploadIconFor(file)}"></i>`);
            }

            item.insertAdjacentHTML('beforeend', `
                <div class="upload-preview-card__body">
                    <div class="upload-preview-card__name">${escapeHtml(file.name)}</div>
                    <small class="text-muted">${fmtSize(file.size)}</small>
                </div>
            `);
            preview.appendChild(item);
        });
    }

    function appendUploadDone(zone, response, file) {
        const { done } = ensureUploadScaffold(zone);
        const url = response?.url || response?.path || '';
        done.hidden = false;
        done.style.display = '';

        const item = document.createElement('div');
        item.className = 'upload-done-card';
        item.dataset.url = url;

        const isImage = file.type.startsWith('image/');
        item.innerHTML = `
            ${isImage && url
                ? `<img src="${escapeHtml(url)}" alt="${escapeHtml(file.name)}">`
                : `<i class="bi ${uploadIconFor(file)}"></i>`}
            <div class="upload-done-card__body">
                <div class="upload-done-card__name">${escapeHtml(file.name)}</div>
                ${url ? `<code>${escapeHtml(url)}</code>` : '<small class="text-muted">Upload concluido</small>'}
            </div>
            ${url ? `<button type="button" class="btn btn-sm btn-outline-secondary" data-copy-upload-url title="Copiar URL"><i class="bi bi-clipboard"></i></button>` : ''}
        `;

        item.querySelector('[data-copy-upload-url]')?.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            navigator.clipboard?.writeText(url).then(() => window.toast.success('URL copiada.'));
        });

        done.appendChild(item);
    }

    function bindUpload(scope) {
        (scope || document).querySelectorAll('[data-upload]').forEach(zone => {
            if (zone.__bound) return;
            zone.__bound = true;
            ensureUploadScaffold(zone);
            const input = zone.querySelector('input[type=file]') || (() => {
                const i = document.createElement('input');
                i.type = 'file'; i.hidden = true; i.multiple = !!zone.dataset.multiple;
                zone.appendChild(i); return i;
            })();

            zone.addEventListener('click', e => { if (e.target === zone || e.target.dataset.uploadTrigger !== undefined) input.click(); });
            zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
            zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
            zone.addEventListener('drop', e => { e.preventDefault(); zone.classList.remove('drag-over'); handleFiles(zone, e.dataTransfer.files); });
            input.addEventListener('change', () => handleFiles(zone, input.files));
        });
    }

    function handleFiles(zone, files) {
        const maxMb = parseInt(zone.dataset.maxMb || '50', 10);
        const url = zone.dataset.url || '/admin/uploads';
        const scaffold = ensureUploadScaffold(zone);
        renderSelectedPreview(zone, files);
        Array.from(files).forEach(file => {
            if (file.size > maxMb * 1048576) { window.toast.error(`Arquivo ${file.name} excede ${maxMb} MB.`); return; }
            uploadOne(file, url, scaffold.progress, zone);
        });
    }

    function uploadOne(file, url, container, zone) {
        const id = 'up' + Math.random().toString(36).slice(2);
        const isImage = file.type.startsWith('image/');
        let thumbHtml = '';
        if (isImage) {
            thumbHtml = `<img class="upload-item-thumb" src="" alt="" style="width:40px;height:40px;border-radius:.375rem;object-fit:cover;flex-shrink:0;">`;
        }
        container.insertAdjacentHTML('beforeend', `
            <div id="${id}" class="upload-item border rounded p-2 mb-2">
                <div class="d-flex align-items-center gap-2 mb-1">
                    ${thumbHtml}
                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex justify-content-between small">
                            <span class="text-truncate me-2" style="max-width:60%;">${file.name}</span>
                            <span class="text-muted">${fmtSize(file.size)}</span>
                        </div>
                    </div>
                </div>
                <div class="progress" style="height:6px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width:0%"></div>
                </div>
                <div class="d-flex justify-content-between small text-muted mt-1">
                    <span data-percent>0%</span><span data-eta>Calculando...</span><span data-speed>0 KB/s</span>
                </div>
            </div>`);
        const root = document.getElementById(id);
        const bar = root.querySelector('.progress-bar');
        const $pct = root.querySelector('[data-percent]'), $eta = root.querySelector('[data-eta]'), $spd = root.querySelector('[data-speed]');

        // Preview de imagem inline
        if (isImage) {
            const thumb = root.querySelector('.upload-item-thumb');
            const reader = new FileReader();
            reader.onload = (e) => { thumb.src = e.target.result; };
            reader.readAsDataURL(file);
        }

        const fd = new FormData();
        fd.append('file', file);
        fd.append('owner_type', zone.dataset.ownerType || '');
        fd.append('owner_id', zone.dataset.ownerId || '');

        const xhr = new XMLHttpRequest();
        xhr.open('POST', url);
        xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name=csrf-token]').content);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.withCredentials = true;

        const start = Date.now();
        xhr.upload.addEventListener('progress', e => {
            if (!e.lengthComputable) return;
            const pct = (e.loaded / e.total) * 100;
            const elapsed = (Date.now() - start) / 1000;
            const speed = e.loaded / elapsed;
            const remaining = (e.total - e.loaded) / Math.max(1, speed);
            bar.style.width = pct + '%';
            $pct.textContent = pct.toFixed(0) + '%';
            $eta.textContent = 'Restam: ' + fmtTime(remaining);
            $spd.textContent = fmtSize(speed) + '/s';
        });
        xhr.onload = () => {
            if (xhr.status >= 200 && xhr.status < 300) {
                let response = {};
                try { response = JSON.parse(xhr.responseText || '{}'); } catch {}
                bar.classList.remove('progress-bar-animated','progress-bar-striped');
                bar.style.width = '100%';
                $pct.textContent = '100%';
                $eta.textContent = 'Concluido';
                $spd.textContent = '';
                root.classList.add('done');
                appendUploadDone(zone, response, file);
                window.toast.success(`Upload concluido: ${file.name}`);
                zone.dispatchEvent(new CustomEvent('upload:done', { detail: { file, response, rawResponse: xhr.responseText }}));
            } else {
                bar.classList.replace('bg-success', 'bg-danger');
                bar.classList.remove('progress-bar-animated','progress-bar-striped');
                $eta.textContent = 'Falhou';
                window.toast.error(`Falha ao enviar ${file.name}`);
            }
        };
        xhr.onerror = () => { bar.classList.replace('bg-success', 'bg-danger'); $eta.textContent = 'Erro de rede'; };
        xhr.send(fd);
    }

    // ============================================================
    // 4) THEME TOGGLE (dark/light)
    // ============================================================
    function normalizeTheme(theme) {
        return theme === 'dark' ? 'dark' : 'light';
    }

    function readThemeCookie() {
        const match = document.cookie.match(/(?:^|;\s*)admin_theme=(dark|light)(?:;|$)/);

        return match ? normalizeTheme(match[1]) : null;
    }

    function themeVariableSet(theme) {
        const modes = window.__adminThemeVariables || {};

        return modes[normalizeTheme(theme)] || null;
    }

    function applyThemeVariables(theme) {
        const root = document.documentElement;
        const variables = themeVariableSet(theme);
        if (!root || !variables) return;

        Object.entries(variables).forEach(([name, value]) => {
            root.style.setProperty(`--${name}`, value);
        });

        const themeColorMeta = document.querySelector('meta[name="theme-color"]');
        if (themeColorMeta) {
            themeColorMeta.setAttribute('content', theme === 'dark'
                ? (variables['bs-body-bg'] || '#020617')
                : (variables['app-navbar-bg'] || variables['bs-body-bg'] || '#f8fafc'));
        }
    }

    function applyTheme(theme, btn = null) {
        const next = normalizeTheme(theme);
        applyThemeVariables(next);
        document.documentElement.dataset.bsTheme = next;

        const toggle = btn || document.querySelector('[data-theme-toggle], #theme-toggle');
        const icon = toggle?.querySelector('i');
        if (icon) {
            icon.className = `bi ${next === 'dark' ? 'bi-sun-fill' : 'bi-moon-stars-fill'}`;
        }

        document.dispatchEvent(new CustomEvent('admin:theme-change', { detail: { theme: next } }));
    }

    function persistThemeLocally(theme) {
        const next = normalizeTheme(theme);
        document.cookie = `admin_theme=${next}; path=/; max-age=31536000; SameSite=Lax`;

        try {
            window.localStorage.setItem('admin_theme', next);
        } catch {}
    }

    function restoreThemeHint() {
        try {
            const stored = window.localStorage.getItem('admin_theme');
            if (stored === 'dark' || stored === 'light') {
                applyTheme(stored);
                return;
            }
        } catch {}

        const fromCookie = readThemeCookie();
        if (fromCookie) {
            applyTheme(fromCookie);
        }
    }

    function syncThemeFromPersistence() {
        let target = null;

        try {
            const stored = window.localStorage.getItem('admin_theme');
            if (stored === 'dark' || stored === 'light') {
                target = stored;
            }
        } catch {}

        if (!target) {
            target = readThemeCookie();
        }

        if (!target) return;

        const current = normalizeTheme(document.documentElement.dataset.bsTheme);
        if (current !== target || themeVariableSet(target)) {
            applyTheme(target);
        }
    }

    function bindTheme() {
        const btn = document.querySelector('[data-theme-toggle], #theme-toggle');
        if (!btn || btn.__themeBound) return;

        btn.__themeBound = true;
        btn.addEventListener('click', async (event) => {
            event.preventDefault();

            const next = document.documentElement.dataset.bsTheme === 'dark' ? 'light' : 'dark';
            const endpoint = btn.dataset.themeEndpoint || '/admin/preferences/theme';
            const csrf = document.querySelector('meta[name=csrf-token]')?.content || window.csrf || '';

            applyTheme(next, btn);
            persistThemeLocally(next);

            try {
                await fetch(endpoint, {
                    method: 'POST',
                    cache: 'no-store',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ theme: next }),
                });
            } catch {}
        });
    }

    // ============================================================
    // 5) NOTIFICACOES EM TEMPO REAL (polling + som por usuario)
    // ============================================================
    function bindNotifications() {
        const csrf = document.querySelector('meta[name=csrf-token]')?.content || window.csrf || '';
        const originalPageTitle = document.title;
        let titleFlashInterval = null;

        function startTitleFlash(unread) {
            if (document.visibilityState === 'visible') return;
            if (titleFlashInterval) clearInterval(titleFlashInterval);
            let toggle = false;
            titleFlashInterval = setInterval(() => {
                document.title = toggle ? `🔔 (${unread}) Nova Notificação!` : originalPageTitle;
                toggle = !toggle;
            }, 1000);
        }

        function stopTitleFlash() {
            if (titleFlashInterval) {
                clearInterval(titleFlashInterval);
                titleFlashInterval = null;
                document.title = originalPageTitle;
            }
        }

        window.addEventListener('focus', stopTitleFlash);
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') stopTitleFlash();
        });

        // Desbloqueia contexto de áudio preventivamente na primeira interação
        function unlockAudio() {
            try {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                if (AudioContext) {
                    const ctx = window.__notificationAudioContext || new AudioContext();
                    window.__notificationAudioContext = ctx;
                    if (ctx.state === 'suspended') ctx.resume();
                }
            } catch {}
        }
        ['click', 'keydown', 'touchstart'].forEach(evt => {
            window.addEventListener(evt, unlockAudio, { once: true, passive: true });
        });

        function requestDesktopNotification() {
            if ('Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission();
            }
        }

        function showDesktopNotification(notification) {
            if (!('Notification' in window) || Notification.permission !== 'granted') return;
            try {
                const title = notification?.title || 'Nova Notificação';
                const body = notification?.message || 'Você recebeu uma nova notificação.';
                const targetUrl = notification?.detail_url || notification?.url || '#';
                const notif = new Notification(title, {
                    body: body,
                    icon: '/favicon.ico',
                    tag: 'notif-' + (notification?.id || Date.now()),
                });
                notif.onclick = function() {
                    window.focus();
                    if (targetUrl && targetUrl !== '#') window.location.href = targetUrl;
                    notif.close();
                };
            } catch (e) {
                console.warn('[notifications] notificação nativa indisponível', e);
            }
        }

        // Filtro rápido de grupos dentro do dropdown do sino
        window.filterNotificationGroup = function(button, group) {
            const menu = button.closest('.dropdown-menu') || document.querySelector('.notification-dropdown-menu');
            if (!menu) return;

            menu.querySelectorAll('[data-filter-group]').forEach(btn => {
                if (btn === button) {
                    btn.classList.add('active', 'btn-primary');
                    btn.classList.remove('btn-outline-secondary');
                } else {
                    btn.classList.remove('active', 'btn-primary');
                    btn.classList.add('btn-outline-secondary');
                }
            });

            const sections = menu.querySelectorAll('.notification-group-section');
            sections.forEach(sec => {
                if (group === 'all' || sec.dataset.groupSection === group) {
                    sec.style.display = 'block';
                } else {
                    sec.style.display = 'none';
                }
            });
        };

        function setCount(bell, unread) {
            const badge = bell.querySelector('[data-notification-count]');
            const markAll = bell.querySelector('[data-mark-all-read]');
            if (badge) {
                badge.textContent = unread > 99 ? '99+' : String(unread);
                badge.classList.toggle('d-none', unread <= 0);
            }
            if (markAll) markAll.classList.toggle('d-none', unread <= 0);
            bell.dataset.currentCount = String(unread);
        }

        function buildReadEndpoint(template, id) {
            if (!template || template === '#') return '';
            return template.replace('__ID__', String(id));
        }

        function setSoundState(bell, enabled) {
            bell.dataset.soundEnabled = enabled ? '1' : '0';
            const btn = bell.querySelector('[data-notification-sound-toggle]');
            const icon = btn?.querySelector('i');
            const label = btn?.querySelector('.visually-hidden');
            if (icon) icon.className = `bi ${enabled ? 'bi-volume-up-fill text-success' : 'bi-volume-mute-fill text-secondary'}`;
            if (label) label.textContent = enabled ? 'Som ativo' : 'Som inativo';
            if (btn) btn.title = enabled ? 'Desativar som' : 'Ativar som';
        }

        function showNotificationToast(notification) {
            const title = notification?.title || 'Nova Notificação';
            const message = notification?.message ? ` - ${notification.message}` : '';
            const targetUrl = notification?.detail_url || notification?.url || '#';

            if (typeof Toastify === 'undefined') {
                if (window.toast) window.toast.info(`${title}${message}`);
                return;
            }

            Toastify({
                text: `${title}${message}`,
                duration: 7000,
                gravity: 'top',
                position: 'right',
                stopOnFocus: true,
                close: true,
                style: {
                    background: 'linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%)',
                    borderRadius: '8px',
                    boxShadow: '0 4px 12px rgba(13, 110, 253, 0.35)',
                    fontSize: '0.875rem',
                    fontWeight: '500'
                },
                onClick: () => {
                    if (targetUrl && targetUrl !== '#') window.location.href = targetUrl;
                },
            }).showToast();
        }

        async function playNotificationSound() {
            try {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                if (AudioContext) {
                    const ctx = window.__notificationAudioContext || new AudioContext();
                    window.__notificationAudioContext = ctx;
                    if (ctx.state === 'suspended') await ctx.resume();

                    const now = ctx.currentTime;
                    const gain = ctx.createGain();
                    gain.gain.setValueAtTime(0.0001, now);
                    gain.gain.exponentialRampToValueAtTime(0.9, now + 0.04);
                    gain.gain.exponentialRampToValueAtTime(0.0001, now + 1.4);
                    gain.connect(ctx.destination);

                    // E6 (1318.5Hz) e B5 (987.8Hz) - Acorde suave tipo chime
                    [0, 0.22].forEach((offset, idx) => {
                        const osc = ctx.createOscillator();
                        osc.type = 'sine';
                        osc.frequency.setValueAtTime(idx === 0 ? 1046.5 : 1318.5, now + offset);
                        osc.connect(gain);
                        osc.start(now + offset);
                        osc.stop(now + offset + 0.45);
                    });
                    return;
                }

                const fallback = window.__notificationAudioFallback || new Audio('data:audio/wav;base64,UklGRlYAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YTIAAACAAGYAzgE0ApkC9gI7A2YDbgNRAw8DqQIxAqkBHAG2AEQAtf9s/yr/9v7V/tr+Cv9L/6X/GgCMABoBpwE1ArsCPQN7A7QDvAOAAzIDvQIkAn4B0wArAIf/8f5o/vr+Cv8x/4f/8wBbAN4AbwEFApQCHQOjA/ADHQQuBBkE2wN1A+YCUwK1ARwBfQDt/5f/Rf8U/wb/Gf9J/5v/AABkAO0AfQENApwCJwOsA/kDJgQ2BCIE4QNyA+QCVAK6ASIBgQDy/5z/Sv8Y/wv/Hf9M/53/AgBmAO8AgAEQAp4CKQOuA/sDKAQ4BCQE4wN0A+YCVgK8ASQBgwD0/57/Tf8b/w3/H/9O/57/BABoAPEAggESAqACKwOwA/0DKgQ6BCYE5Q');
                window.__notificationAudioFallback = fallback;
                fallback.currentTime = 0;
                await fallback.play();
            } catch (error) {
                console.warn('[notifications] som bloqueado pelo navegador', error);
            }
        }

        function visualAlert(bell, unread, latestNotification = null) {
            bell.classList.add('notification-bell-pulse');
            setTimeout(() => bell.classList.remove('notification-bell-pulse'), 5000);
            showNotificationToast(latestNotification || {
                title: unread > 1 ? `${unread} notificações pendentes` : 'Nova notificação recebida',
                message: latestNotification?.message || '',
                url: bell.dataset.indexUrl || '#',
            });
        }

        // Web Worker Anti-Throttling para garantir polling em aba minimizada
        function createPollingWorker(onTick) {
            try {
                const blob = new Blob([`
                    let timer = null;
                    self.onmessage = function(e) {
                        if (e.data === 'start') {
                            if (!timer) timer = setInterval(() => self.postMessage('tick'), 12000);
                        } else if (e.data === 'stop') {
                            if (timer) { clearInterval(timer); timer = null; }
                        }
                    };
                `], { type: 'application/javascript' });
                const worker = new Worker(URL.createObjectURL(blob));
                worker.onmessage = () => onTick();
                worker.postMessage('start');
                return worker;
            } catch {
                setInterval(onTick, 14000);
                return null;
            }
        }

        document.querySelectorAll('[data-notification-bell]').forEach(bell => {
            if (bell.__notificationBound) return;
            bell.__notificationBound = true;

            const feedEndpoint = bell.dataset.feedEndpoint;
            const readAllEndpoint = bell.dataset.readAllEndpoint;
            const readEndpointTemplate = bell.dataset.readEndpointTemplate;
            const soundEndpoint = bell.dataset.soundEndpoint;
            let latestId = parseInt(bell.dataset.latestId || '0', 10);

            async function refresh(silent = false) {
                if (!feedEndpoint || feedEndpoint === '#') return;
                try {
                    const r = await fetch(feedEndpoint, {
                        credentials: 'same-origin',
                        headers: { 'Accept': 'application/json' },
                    });
                    const data = await r.json();
                    if (!r.ok || !data.ok) return;

                    const unread = parseInt(data.unread || 0, 10);
                    const nextLatestId = parseInt(data.latest_id || 0, 10);
                    const list = bell.querySelector('[data-notification-list]');
                    const updated = bell.querySelector('[data-notification-updated]');
                    if (list && typeof data.html === 'string') list.innerHTML = data.html;
                    if (updated && data.updated_at) updated.textContent = `${data.updated_at}`;
                    setCount(bell, unread);

                    const hasNew = nextLatestId > latestId && unread > 0;
                    latestId = nextLatestId;
                    bell.dataset.latestId = String(nextLatestId);

                    if (!silent && hasNew) {
                        visualAlert(bell, unread, data.latest_notification || null);
                        if (bell.dataset.soundEnabled === '1') {
                            await playNotificationSound();
                        }
                        // Alerta visual de background se estiver minimizado
                        if (document.hidden || document.visibilityState !== 'visible') {
                            startTitleFlash(unread);
                            showDesktopNotification(data.latest_notification || null);
                        }
                    }
                } catch (error) {
                    console.warn('[notifications] feed falhou', error);
                }
            }

            bell.querySelector('[data-mark-all-read]')?.addEventListener('click', async (event) => {
                event.preventDefault();
                if (!readAllEndpoint || readAllEndpoint === '#') return;
                await fetch(readAllEndpoint, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                await refresh(true);
            });

            bell.querySelector('[data-notification-list]')?.addEventListener('click', async (event) => {
                const item = event.target.closest('[data-notification-item]');
                if (!item) return;

                event.preventDefault();
                event.stopPropagation();
                const id = item.dataset.id;
                let targetUrl = item.dataset.url || bell.dataset.indexUrl || '#';
                const readEndpoint = buildReadEndpoint(readEndpointTemplate, id);

                if (readEndpoint) {
                    try {
                        const response = await fetch(readEndpoint, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'X-CSRF-TOKEN': csrf,
                                'Accept': 'application/json',
                            },
                        });
                        if (response.ok) {
                            const data = await response.json();
                            if (typeof data.unread !== 'undefined') {
                                setCount(bell, parseInt(data.unread || 0, 10));
                            }
                            if (data.notification?.detail_url || data.notification?.url) {
                                targetUrl = data.notification.detail_url || data.notification.url;
                            }
                        }
                    } catch (error) {
                        console.warn('[notifications] leitura individual falhou', error);
                    }
                }

                window.location.href = targetUrl;
            });

            bell.querySelector('[data-notification-sound-toggle]')?.addEventListener('click', async (event) => {
                event.preventDefault();
                event.stopPropagation();
                const enabled = bell.dataset.soundEnabled !== '1';
                setSoundState(bell, enabled);
                if (enabled) {
                    unlockAudio();
                    requestDesktopNotification();
                    playNotificationSound();
                }
                if (soundEndpoint && soundEndpoint !== '#') {
                    try {
                        await fetch(soundEndpoint, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ enabled }),
                        });
                    } catch {}
                }
                if (window.toast) {
                    window.toast.info(enabled ? 'Som e alertas ativados.' : 'Som desativado.');
                }
            });

            bell.querySelector('[data-notification-toggle]')?.addEventListener('click', () => {
                unlockAudio();
                requestDesktopNotification();
                refresh(true);
            });

            // Dispara via Web Worker imune ao throttling de minimização
            createPollingWorker(() => refresh(false));
        });
    }

    // ============================================================
    // INIT
    // ============================================================
    restoreThemeHint();
    window.addEventListener('pageshow', syncThemeFromPersistence);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            syncThemeFromPersistence();
        }
    });
    window.addEventListener('storage', (event) => {
        if (event.key === 'admin_theme' && (event.newValue === 'dark' || event.newValue === 'light')) {
            applyTheme(event.newValue);
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        bindMasks();
        bindCep();
        bindUpload();
        bindTheme();
        bindNotifications();
    });

    // Expor para uso em conteudo dinamico (modais, ajax)
    window.adminUI = { bindMasks, bindCep, bindUpload, bindTheme, applyTheme, applyThemeVariables, masks, parseMoney, formatMoney, lookupCep };
})();


/* ============================================================
 * DATATABLE - server-side processing helper
 * Uso:
 *   <table id="dt" data-table="invoices" data-columns='[...]'></table>
 *   admin.dt.init('#dt');
 * ============================================================ */
(function () {
    const adminUI = window.adminUI || {};

    function fmtMoney(v) { return 'R$ ' + parseFloat(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 }); }
    function fmtDate(v) { return v ? new Date(v).toLocaleDateString('pt-BR') : '—'; }
    function fmtDateTime(v) { return v ? new Date(v).toLocaleString('pt-BR') : '—'; }
    function fmtBadge(v, map = {}) {
        const m = map[v] || { color: 'secondary', label: v };
        return `<span class="badge bg-${m.color}">${m.label || v}</span>`;
    }
    function escapeHtml(s) {
        return (s || '').toString().replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;' }[c]));
    }

    window.adminDt = {
        formatters: { money: fmtMoney, date: fmtDate, datetime: fmtDateTime, badge: fmtBadge, escape: escapeHtml },

        init(selector, opts = {}) {
            const el = document.querySelector(selector);
            if (!el) return null;
            const table = el.dataset.table;
            const cols = JSON.parse(el.dataset.columns || '[]');
            const baseUrl = '/admin/datatable/' + table;

            let state = {
                draw: 0,
                start: 0,
                length: parseInt(el.dataset.length || 25, 10),
                search: '',
                orderCol: parseInt(el.dataset.orderCol || 0, 10),
                orderDir: el.dataset.orderDir || 'desc',
            };

            // Build wrapper UI: search input + length selector + table + pagination
            const wrap = document.createElement('div');
            wrap.className = 'dt-wrapper';
            wrap.innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <div class="d-flex gap-2 align-items-center">
                        <select class="form-select form-select-sm dt-length" style="width:auto;">
                            <option value="10">10</option>
                            <option value="25" selected>25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                        <small class="text-muted">por pagina</small>
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        <div class="position-relative">
                            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-2 text-muted small"></i>
                            <input type="search" class="form-control form-control-sm ps-4 dt-search" placeholder="Buscar..." style="width:240px;">
                        </div>
                        <a href="${baseUrl}/export" class="btn btn-sm btn-outline-success" target="_blank" title="Exportar CSV">
                            <i class="bi bi-download"></i>
                        </a>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover m-0 align-middle">
                        <thead class="table-light"><tr></tr></thead>
                        <tbody><tr><td colspan="${cols.length}" class="text-center text-muted py-5"><span class="spinner-border spinner-border-sm me-2"></span> Carregando...</td></tr></tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-2 flex-wrap gap-2">
                    <small class="text-muted dt-info">—</small>
                    <ul class="pagination pagination-sm m-0 dt-pagination"></ul>
                </div>
            `;
            el.replaceWith(wrap);

            const headRow = wrap.querySelector('thead tr');
            cols.forEach((c, i) => {
                const th = document.createElement('th');
                th.textContent = c.title || c.name;
                if (c.sortable !== false) {
                    th.style.cursor = 'pointer';
                    th.dataset.col = i;
                    th.addEventListener('click', () => {
                        if (state.orderCol === i) state.orderDir = state.orderDir === 'asc' ? 'desc' : 'asc';
                        else { state.orderCol = i; state.orderDir = 'asc'; }
                        load();
                    });
                }
                if (c.thClass) th.className = c.thClass;
                headRow.appendChild(th);
            });

            const searchInput = wrap.querySelector('.dt-search');
            const lengthSelect = wrap.querySelector('.dt-length');
            let searchTimer;

            searchInput.addEventListener('input', () => {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(() => { state.search = searchInput.value; state.start = 0; load(); }, 300);
            });
            lengthSelect.addEventListener('change', () => { state.length = parseInt(lengthSelect.value, 10); state.start = 0; load(); });

            async function load() {
                state.draw++;
                const params = new URLSearchParams({
                    draw: state.draw,
                    start: state.start,
                    length: state.length,
                    'search[value]': state.search,
                    'order[0][column]': state.orderCol,
                    'order[0][dir]': state.orderDir,
                });
                cols.forEach((c, i) => {
                    params.append(`columns[${i}][data]`, c.name);
                });

                try {
                    const r = await fetch(`${baseUrl}?${params}`, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
                    const d = await r.json();
                    renderRows(d.data || []);
                    renderPagination(d.recordsFiltered || 0);
                } catch (err) {
                    wrap.querySelector('tbody').innerHTML = `<tr><td colspan="${cols.length}" class="text-center text-danger py-3"><i class="bi bi-exclamation-triangle me-1"></i> Erro ao carregar.</td></tr>`;
                }
            }

            function renderRows(rows) {
                const tbody = wrap.querySelector('tbody');
                if (!rows.length) {
                    tbody.innerHTML = `<tr><td colspan="${cols.length}" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2"></i> Nenhum registro encontrado.</td></tr>`;
                    return;
                }
                tbody.innerHTML = rows.map(row => {
                    const cells = cols.map(c => {
                        let v = row[c.name];
                        if (c.render) v = c.render(v, row);
                        else v = escapeHtml(v);
                        const cls = c.tdClass || '';
                        return `<td class="${cls}">${v != null ? v : ''}</td>`;
                    }).join('');
                    return `<tr>${cells}</tr>`;
                }).join('');
            }

            function renderPagination(total) {
                const totalPages = Math.ceil(total / state.length);
                const currentPage = Math.floor(state.start / state.length) + 1;
                const ul = wrap.querySelector('.dt-pagination');
                const info = wrap.querySelector('.dt-info');
                info.textContent = `${state.start + 1} a ${Math.min(state.start + state.length, total)} de ${total}`;

                if (totalPages <= 1) { ul.innerHTML = ''; return; }

                let html = '';
                html += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${currentPage - 1}">‹</a></li>`;
                const max = 5;
                let from = Math.max(1, currentPage - 2);
                let to = Math.min(totalPages, from + max - 1);
                from = Math.max(1, to - max + 1);
                for (let p = from; p <= to; p++) {
                    html += `<li class="page-item ${p === currentPage ? 'active' : ''}"><a class="page-link" href="#" data-page="${p}">${p}</a></li>`;
                }
                html += `<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${currentPage + 1}">›</a></li>`;
                ul.innerHTML = html;

                ul.querySelectorAll('a[data-page]').forEach(a => {
                    a.addEventListener('click', e => {
                        e.preventDefault();
                        const p = parseInt(a.dataset.page, 10);
                        if (p >= 1 && p <= totalPages) {
                            state.start = (p - 1) * state.length;
                            load();
                        }
                    });
                });
            }

            load();
            return { reload: load };
        } };
    // ============================================================
    // 8) TOOLTIPS GLOBAIS (Bootstrap 5)
    // ============================================================
    function initGlobalTooltips() {
        if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) return;

        function applyToNode(node) {
            if (node.nodeType !== 1) return;
            if (node.hasAttribute('title') && node.getAttribute('title').trim() !== '' && !node.hasAttribute('data-bs-toggle') && !node.classList.contains('swal2-input') && !node.classList.contains('swal2-select') && !node.classList.contains('swal2-textarea')) {
                node.setAttribute('data-bs-toggle', 'tooltip');
                try { new bootstrap.Tooltip(node, { boundary: document.body }); } catch(e) {}
            }
            const children = node.querySelectorAll('[title]:not([data-bs-toggle="tooltip"]):not(.swal2-input):not(.swal2-select):not(.swal2-textarea)');
            children.forEach(el => {
                if (el.getAttribute('title').trim() !== '') {
                    el.setAttribute('data-bs-toggle', 'tooltip');
                    try { new bootstrap.Tooltip(el, { boundary: document.body }); } catch(e) {}
                }
            });
        }

        applyToNode(document.body);

        const observer = new MutationObserver(mutations => {
            mutations.forEach(mutation => {
                if (mutation.type === 'childList') {
                    mutation.addedNodes.forEach(applyToNode);
                } else if (mutation.type === 'attributes' && mutation.attributeName === 'title') {
                    const el = mutation.target;
                    if (el.hasAttribute('title') && el.getAttribute('title').trim() !== '' && !el.classList.contains('swal2-input') && !el.classList.contains('swal2-select') && !el.classList.contains('swal2-textarea')) {
                        if (!el.hasAttribute('data-bs-toggle')) {
                            el.setAttribute('data-bs-toggle', 'tooltip');
                            try { new bootstrap.Tooltip(el, { boundary: document.body }); } catch(e) {}
                        } else {
                            const tip = bootstrap.Tooltip.getInstance(el);
                            if (tip) {
                                el.setAttribute('data-bs-original-title', el.getAttribute('title'));
                                el.removeAttribute('title');
                            }
                        }
                    }
                }
            });
        });

        observer.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['title'] });
    }

    document.addEventListener('DOMContentLoaded', initGlobalTooltips);
})();
