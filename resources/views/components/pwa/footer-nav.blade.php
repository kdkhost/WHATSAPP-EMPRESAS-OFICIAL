@php
    $showFooter = auth()->check() || auth('admin')->check();

    // Detecta guard ativo para filtrar itens por papel
    $activeGuard = auth()->check() ? 'web' : (auth('admin')->check() ? 'admin' : null);

    // Tenta carregar itens do banco; se falhar ou estiver vazio, usa fallback seguro
    try {
        $dbItems = \App\Modules\Pwa\Models\PwaFooterMenuItem::active()->ordered()->get();
        $pwaFooterItems = $dbItems->filter(fn ($item) => $item->isVisibleFor($activeGuard))
            ->map(function ($item) {
                $patterns = $item->match_patterns ?? [];
                $isActive = false;
                foreach ($patterns as $pattern) {
                    if (empty($pattern)) continue;
                    if (request()->routeIs($pattern) || request()->is($pattern)) {
                        $isActive = true;
                        break;
                    }
                }
                return [
                    'label' => $item->label,
                    'icon' => $item->icon,
                    'url' => $item->resolvedUrl(),
                    'active' => $isActive,
                ];
            })
            ->values()
            ->all();
    } catch (\Throwable $e) {
        // Fallback hardcoded nunca quebra em producao
        $pwaFooterItems = [];
    }

    // Se o banco retornou vazio (ex: antes da migration rodar), usa fallback padrao
    if (empty($pwaFooterItems)) {
        $pwaFooterItems = [
            [
                'label' => 'Inicio',
                'icon' => 'bi bi-house-door',
                'url' => Route::has('pwa.dashboard') ? route('pwa.dashboard') : url('/pwa/dashboard'),
                'active' => request()->routeIs('pwa.dashboard')
                    || request()->is('pwa/dashboard')
                    || request()->is('painel')
                    || request()->is('painel/dashboard')
                    || request()->is('admin')
                    || request()->is('admin/dashboard'),
            ],
            [
                'label' => 'Mensagens',
                'icon' => 'bi bi-chat-dots',
                'url' => Route::has('pwa.messages') ? route('pwa.messages') : url('/pwa/mensagens'),
                'active' => request()->routeIs('pwa.messages')
                    || request()->is('pwa/mensagens')
                    || request()->is('painel/whatsapp/mensagens*'),
            ],
            [
                'label' => 'Cobrancas',
                'icon' => 'bi bi-credit-card',
                'url' => Route::has('pwa.billing') ? route('pwa.billing') : url('/pwa/cobrancas'),
                'active' => request()->routeIs('pwa.billing')
                    || request()->is('pwa/cobrancas')
                    || request()->is('painel/cobrancas*'),
            ],
            [
                'label' => 'QR/Instancia',
                'icon' => 'bi bi-qr-code',
                'url' => Route::has('pwa.instance') ? route('pwa.instance') : url('/pwa/instancia'),
                'active' => request()->routeIs('pwa.instance')
                    || request()->is('pwa/instancia')
                    || request()->is('painel/whatsapp/instancia*'),
            ],
            [
                'label' => 'Perfil',
                'icon' => 'bi bi-person',
                'url' => Route::has('pwa.profile') ? route('pwa.profile') : url('/pwa/perfil'),
                'active' => request()->routeIs('pwa.profile')
                    || request()->is('pwa/perfil')
                    || request()->is('painel/perfil*'),
            ],
        ];
    }
@endphp

@if($showFooter && count($pwaFooterItems) > 0)
    <nav class="pwa-footer-nav d-lg-none" aria-label="Menu principal da PWA">
        @foreach($pwaFooterItems as $item)
            <a href="{{ $item['url'] }}"
               class="pwa-footer-item {{ !empty($item['active']) ? 'is-active' : '' }}"
               aria-label="{{ $item['label'] }}">
                <i class="{{ $item['icon'] }}" aria-hidden="true"></i>
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>
@endif
