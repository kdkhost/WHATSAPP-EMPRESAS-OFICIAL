<aside class="app-sidebar shadow">
    <div class="sidebar-brand p-3 border-bottom">
        <a href="{{ route('admin.dashboard') }}" class="brand-link d-flex align-items-center text-decoration-none">
            @php
                $appName = $settingsManager->get('app.name', config('app.name'));
                $appLogo = $settingsManager->get('app.logo');
            @endphp
            @if($appLogo)
                <img src="{{ $appLogo }}" alt="{{ $appName }}" class="me-2" style="max-height:36px;max-width:160px;object-fit:contain;">
            @else
                <i class="bi bi-whatsapp text-success fs-3 me-2"></i>
            @endif
            <span class="brand-text fw-bold">{{ $appName }}</span>
        </a>
    </div>

    <div class="sidebar-wrapper">
        <nav class="mt-2">
            <ul class="nav sidebar-menu flex-column" role="menu">

                <li class="nav-header text-uppercase text-muted fw-bold small px-3 mt-3 mb-1">Principal</li>
                
                <li class="nav-item">
                    <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-speedometer2"></i><p>Dashboard</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ url('/admin/analytics') }}" class="nav-link {{ request()->is('admin/analytics*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-graph-up-arrow"></i><p>Analytics</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ url('/admin/subscriptions') }}" class="nav-link {{ request()->is('admin/subscriptions*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-arrow-repeat"></i><p>Assinaturas</p>
                    </a>
                </li>


                <li class="nav-header text-uppercase text-muted fw-bold small px-3 mt-3 mb-1">SaaS & Assinantes</li>
                
                @role('superadmin')
                <li class="nav-item">
                    <a href="{{ route('admin.tenancy.index') }}" class="nav-link {{ request()->is('admin/tenancy') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-building"></i><p>Planos e Tenants</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('admin.tenancy.tenants') }}" class="nav-link {{ request()->is('admin/tenancy/tenants*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-person-badge text-info"></i><p>Login supervisionado</p>
                    </a>
                </li>
                @endrole
                <li class="nav-item">
                    <a href="{{ url('/admin/white-label') }}" class="nav-link {{ request()->is('admin/white-label*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-palette"></i><p>White Label</p>
                    </a>
                </li>


                <li class="nav-header text-uppercase text-muted fw-bold small px-3 mt-3 mb-1">Comunicacao</li>

                <li class="nav-item {{ request()->is('admin/whatsapp/*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->is('admin/whatsapp/*') ? 'active' : '' }}"><i class="nav-icon bi bi-whatsapp text-success"></i><p>WhatsApp <i class="nav-arrow bi bi-chevron-right"></i></p></a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item"><a href="{{ url('/admin/whatsapp/instance') }}" class="nav-link {{ request()->is('admin/whatsapp/instance*') ? 'active' : '' }}">Instancia (Evolution)</a></li>
                        <li class="nav-item"><a href="{{ url('/admin/whatsapp/messages') }}" class="nav-link {{ request()->is('admin/whatsapp/messages*') ? 'active' : '' }}">Mensagens</a></li>
                        <li class="nav-item"><a href="{{ route('admin.whatsapp.safety') }}" class="nav-link {{ request()->is('admin/whatsapp/safety*') ? 'active' : '' }}">Anti-Ban e Seguranca</a></li>
                        <li class="nav-item"><a href="{{ url('/admin/whatsapp/export-report') }}" class="nav-link {{ request()->is('admin/whatsapp/export-report*') ? 'active' : '' }}"><i class="bi bi-download"></i> Exportar relatorio</a></li>
                    </ul>
                </li>
                <li class="nav-item {{ request()->is('admin/email/*', 'admin/contracts*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->is('admin/email/*', 'admin/contracts*') ? 'active' : '' }}"><i class="nav-icon bi bi-envelope-paper"></i><p>Cobranca Email <i class="nav-arrow bi bi-chevron-right"></i></p></a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item"><a href="{{ route('admin.email.config') }}" class="nav-link {{ request()->is('admin/email/config*') ? 'active' : '' }}"><i class="nav-icon bi bi-gear"></i><p>Configurar SMTP</p></a></li>
                        <li class="nav-item"><a href="{{ route('admin.email.templates') }}" class="nav-link {{ request()->is('admin/email/templates*') ? 'active' : '' }}"><i class="nav-icon bi bi-file-earmark-text"></i><p>Templates de Email</p></a></li>
                        <li class="nav-item"><a href="{{ route('admin.contracts.index') }}" class="nav-link {{ request()->is('admin/contracts*') ? 'active' : '' }}"><i class="nav-icon bi bi-file-earmark-check"></i><p>Contratos</p></a></li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a href="{{ route('admin.templates.index') }}" class="nav-link {{ request()->is('admin/templates*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-chat-square-text"></i><p>Templates Globais</p>
                    </a>
                </li>


                <li class="nav-header text-uppercase text-muted fw-bold small px-3 mt-3 mb-1">Comercial & Financeiro</li>

                <li class="nav-item {{ request()->is('admin/billing/*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->is('admin/billing/*') ? 'active' : '' }}"><i class="nav-icon bi bi-receipt text-primary"></i><p>Cobrancas <i class="nav-arrow bi bi-chevron-right"></i></p></a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item"><a href="{{ url('/admin/billing/invoices') }}" class="nav-link {{ request()->is('admin/billing/invoices') ? 'active' : '' }}"><i class="nav-icon bi bi-file-earmark-text"></i><p>Faturas</p></a></li>
                        <li class="nav-item"><a href="{{ route('admin.billing.invoices.create') }}" class="nav-link {{ request()->is('admin/billing/invoices/criar') ? 'active' : '' }}"><i class="nav-icon bi bi-plus-circle-dotted"></i><p>Criar Fatura Avulsa</p></a></li>
                        <li class="nav-item"><a href="{{ url('/admin/billing/customers') }}" class="nav-link {{ request()->is('admin/billing/customers*') ? 'active' : '' }}"><i class="nav-icon bi bi-people"></i><p>Clientes</p></a></li>
                        <li class="nav-item"><a href="{{ url('/admin/billing/dispatches') }}" class="nav-link {{ request()->is('admin/billing/dispatches*') ? 'active' : '' }}"><i class="nav-icon bi bi-send"></i><p>Envios agendados</p></a></li>
                        <li class="nav-item"><a href="{{ route('admin.billing.message-history.index') }}" class="nav-link {{ request()->is('admin/billing/message-history*') ? 'active' : '' }}"><i class="nav-icon bi bi-clock-history"></i><p>Historico de mensagens</p></a></li>
                        <li class="nav-item"><a href="{{ url('/admin/billing/calendar') }}" class="nav-link {{ request()->is('admin/billing/calendar*') ? 'active' : '' }}"><i class="nav-icon bi bi-calendar3"></i><p>Calendario</p></a></li>
                    </ul>
                </li>
                <li class="nav-item {{ request()->is('admin/crm*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->is('admin/crm*') ? 'active' : '' }}"><i class="nav-icon bi bi-kanban"></i><p>CRM <i class="nav-arrow bi bi-chevron-right"></i></p></a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item"><a href="{{ url('/admin/crm') }}" class="nav-link {{ request()->is('admin/crm') ? 'active' : '' }}">Visao Geral</a></li>
                        <li class="nav-item"><a href="{{ url('/admin/crm-advanced') }}" class="nav-link {{ request()->is('admin/crm-advanced*') ? 'active' : '' }}">Funil de Vendas</a></li>
                        <li class="nav-item"><a href="{{ url('/admin/crm/contacts') }}" class="nav-link {{ request()->is('admin/crm/contacts*') ? 'active' : '' }}">Contatos</a></li>
                    </ul>
                </li>
                <li class="nav-item {{ request()->is('admin/gateways/*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->is('admin/gateways/*') ? 'active' : '' }}"><i class="nav-icon bi bi-credit-card"></i><p>Gateways <i class="nav-arrow bi bi-chevron-right"></i></p></a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item"><a href="{{ url('/admin/gateways/mercadopago') }}" class="nav-link {{ request()->is('admin/gateways/mercadopago*') ? 'active' : '' }}">Mercado Pago</a></li>
                        <li class="nav-item"><a href="{{ url('/admin/gateways/efipro') }}" class="nav-link {{ request()->is('admin/gateways/efipro*') ? 'active' : '' }}">Efi Pro</a></li>
                        <li class="nav-item"><a href="{{ url('/admin/gateways/sumup') }}" class="nav-link {{ request()->is('admin/gateways/sumup*') ? 'active' : '' }}">SumUp</a></li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a href="{{ url('/admin/import/customers') }}" class="nav-link {{ request()->is('admin/import*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-upload"></i><p>Importar CSV</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('admin.marketing.index') }}" class="nav-link {{ request()->is('admin/marketing*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-bullseye text-danger"></i>
                        <p>
                            Marketing & Leads
                            @php
                                $badgeNewLeads = 0;
                                try {
                                    $badgeNewLeads = \App\Modules\Marketing\Models\MarketingLead::where('status', 'new')->count();
                                } catch (\Throwable) {}
                            @endphp
                            @if($badgeNewLeads > 0)
                                <span class="badge bg-danger float-end">{{ $badgeNewLeads }}</span>
                            @else
                                <span class="badge bg-primary-subtle text-primary float-end">Promo</span>
                            @endif
                        </p>
                    </a>
                </li>


                <li class="nav-header text-uppercase text-muted fw-bold small px-3 mt-3 mb-1">CMS & Configuracoes</li>

                <li class="nav-item {{ request()->is('admin/menus*', 'admin/categories*', 'admin/cards*', 'admin/kanban*', 'admin/settings/slides*', 'admin/settings/popups*', 'admin/settings/testimonials*', 'admin/settings/pages*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->is('admin/menus*', 'admin/categories*', 'admin/cards*', 'admin/kanban*', 'admin/settings/slides*', 'admin/settings/popups*', 'admin/settings/testimonials*', 'admin/settings/pages*') ? 'active' : '' }}"><i class="nav-icon bi bi-layout-wtf"></i><p>Conteudo do Site <i class="nav-arrow bi bi-chevron-right"></i></p></a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item"><a href="{{ url('/admin/kanban/menus') }}" class="nav-link {{ request()->is('admin/menus*', 'admin/kanban/menus*') ? 'active' : '' }}">Menus</a></li>
                        <li class="nav-item"><a href="{{ url('/admin/kanban/categorias') }}" class="nav-link {{ request()->is('admin/categories*', 'admin/kanban/categorias*') ? 'active' : '' }}">Categorias</a></li>
                        <li class="nav-item"><a href="{{ url('/admin/kanban/cards') }}" class="nav-link {{ request()->is('admin/cards*', 'admin/kanban/cards*') ? 'active' : '' }}">Cards</a></li>
                        <li class="nav-item"><a href="{{ url('/admin/settings/slides') }}" class="nav-link {{ request()->is('admin/settings/slides*') ? 'active' : '' }}">Slides/Carrossel</a></li>
                        <li class="nav-item"><a href="{{ url('/admin/settings/popups') }}" class="nav-link {{ request()->is('admin/settings/popups*') ? 'active' : '' }}">Popups</a></li>
                        <li class="nav-item"><a href="{{ url('/admin/settings/testimonials') }}" class="nav-link {{ request()->is('admin/settings/testimonials*') ? 'active' : '' }}">Depoimentos</a></li>
                        <li class="nav-item"><a href="{{ route('admin.settings.pages') }}" class="nav-link {{ request()->is('admin/settings/pages*') ? 'active' : '' }}">Paginas do site</a></li>
                    </ul>
                </li>
                @php
                    $settingsRoutes = ['admin/settings/general*', 'admin/settings/seo*', 'admin/pwa*', 'admin/maintenance*', 'admin/settings/lgpd*', 'admin/settings/google*', 'admin/settings/upload*', 'admin/settings/theme*', 'admin/settings/preload*', 'admin/opengraph*'];
                @endphp
                <li class="nav-item {{ request()->is(...$settingsRoutes) ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->is(...$settingsRoutes) ? 'active' : '' }}"><i class="nav-icon bi bi-gear"></i><p>Configuracoes <i class="nav-arrow bi bi-chevron-right"></i></p></a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item"><a href="{{ url('/admin/settings/general') }}" class="nav-link {{ request()->is('admin/settings/general*') ? 'active' : '' }}">Geral (logo/favicon)</a></li>
                        <li class="nav-item"><a href="{{ url('/admin/settings/seo') }}" class="nav-link {{ request()->is('admin/settings/seo*') ? 'active' : '' }}">SEO</a></li>
                        <li class="nav-item {{ request()->is('admin/pwa*') ? 'menu-open' : '' }}">
                            <a href="#" class="nav-link {{ request()->is('admin/pwa*') ? 'active' : '' }}"><p>PWA <i class="nav-arrow bi bi-chevron-right"></i></p></a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item"><a href="{{ url('/admin/pwa') }}" class="nav-link {{ request()->is('admin/pwa') || request()->is('admin/pwa/icons') || request()->is('admin/pwa/cache*') ? 'active' : '' }}">Configuracao</a></li>
                                <li class="nav-item"><a href="{{ route('admin.pwa.menu.index') }}" class="nav-link {{ request()->is('admin/pwa/menu*') ? 'active' : '' }}">Menu Footer</a></li>
                            </ul>
                        </li>
                        <li class="nav-item"><a href="{{ url('/admin/maintenance') }}" class="nav-link {{ request()->is('admin/maintenance*') ? 'active' : '' }}">Manutencao</a></li>
                        <li class="nav-item"><a href="{{ url('/admin/settings/lgpd') }}" class="nav-link {{ request()->is('admin/settings/lgpd*') ? 'active' : '' }}">LGPD</a></li>
                        <li class="nav-item"><a href="{{ url('/admin/settings/google') }}" class="nav-link {{ request()->is('admin/settings/google*') ? 'active' : '' }}">Google Ads/Business</a></li>
                        <li class="nav-item"><a href="{{ url('/admin/settings/upload') }}" class="nav-link {{ request()->is('admin/settings/upload*') ? 'active' : '' }}">Upload (tamanho)</a></li>
                        <li class="nav-item"><a href="{{ url('/admin/settings/theme') }}" class="nav-link {{ request()->is('admin/settings/theme*') ? 'active' : '' }}"><i class="nav-icon bi bi-palette"></i> Tema / Cores</a></li>
                        <li class="nav-item"><a href="{{ route('admin.settings.preload.index') }}" class="nav-link {{ request()->is('admin/settings/preload*') ? 'active' : '' }}"><i class="nav-icon bi bi-hourglass-split text-primary"></i> Tela de Carregamento</a></li>
                        <li class="nav-item"><a href="{{ url('/admin/opengraph') }}" class="nav-link {{ request()->is('admin/opengraph*') ? 'active' : '' }}"><i class="nav-icon bi bi-share"></i> Open Graph</a></li>
                    </ul>
                </li>


                <li class="nav-header text-uppercase text-muted fw-bold small px-3 mt-3 mb-1">Módulos & Integrações</li>

                @php
                    try {
                        $dynamicAddonMenus = \Illuminate\Support\Facades\Schema::hasTable('addon_menus')
                            ? \Illuminate\Support\Facades\DB::table('addon_menus')
                                ->leftJoin('registered_modules', 'registered_modules.slug', '=', 'addon_menus.module_slug')
                                ->select(
                                    'addon_menus.*',
                                    'registered_modules.name as module_name',
                                    'registered_modules.menu_title as module_menu_title',
                                    'registered_modules.icon as module_icon',
                                    'registered_modules.menu_order as module_menu_order'
                                )
                                ->where('active', true)
                                ->where(function ($query) {
                                    $query->whereNull('registered_modules.enabled')
                                        ->orWhere('registered_modules.enabled', true);
                                })
                                ->whereNotIn('addon_menus.module_slug', ['whmcs', 'cpanel', 'aapanel', 'hospedagem-aapanel'])
                                ->orderByRaw('COALESCE(registered_modules.menu_order, addon_menus.menu_order)')
                                ->orderByRaw('COALESCE(registered_modules.menu_title, registered_modules.name, addon_menus.module_slug)')
                                ->get()
                            : collect();
                    } catch (\Throwable) {
                        $dynamicAddonMenus = collect();
                    }

                    $dynamicAddonGroups = $dynamicAddonMenus->groupBy('module_slug');
                @endphp

                @foreach($dynamicAddonGroups as $moduleSlug => $menus)
                    @php
                        $firstAddonMenu = $menus->first();
                        $moduleTitle = $firstAddonMenu->module_menu_title ?: ($firstAddonMenu->module_name ?: \Illuminate\Support\Str::headline((string) $moduleSlug));
                        $moduleIcon = (string) ($firstAddonMenu->module_icon ?: ($firstAddonMenu->icon ?: 'bi-puzzle'));
                        $moduleIconClass = str_starts_with($moduleIcon, 'bi ') ? $moduleIcon : (str_starts_with($moduleIcon, 'bi-') ? 'bi '.$moduleIcon : $moduleIcon);
                        
                        $routeValue = trim((string) $firstAddonMenu->route);
                        $slugSnake = \Illuminate\Support\Str::snake(str_replace('-', '_', (string)$moduleSlug));

                        // Mapeamentos específicos para módulos do sistema
                        if (in_array($moduleSlug, ['analytics', 'analytics-pro', 'analytics_pro'], true) || str_contains((string)$moduleSlug, 'analytics')) {
                            $routeValue = \Illuminate\Support\Facades\Route::has('admin.analytics.index') ? 'admin.analytics.index' : '/admin/analytics';
                        } elseif (str_contains($routeValue, 'admin.addons.') || str_contains($routeValue, '/admin/addons/') || empty($routeValue)) {
                            if (\Illuminate\Support\Facades\Route::has("admin.{$slugSnake}.index")) {
                                $routeValue = "admin.{$slugSnake}.index";
                            } elseif (\Illuminate\Support\Facades\Route::has("admin.{$moduleSlug}.index")) {
                                $routeValue = "admin.{$moduleSlug}.index";
                            } elseif (\Illuminate\Support\Facades\Route::has("admin.{$slugSnake}.dashboard")) {
                                $routeValue = "admin.{$slugSnake}.dashboard";
                            } else {
                                $routeValue = "/admin/{$slugSnake}";
                            }
                        }

                        if (str_starts_with($routeValue, 'http')) {
                            $url = $routeValue;
                            $moduleActive = false;
                        } elseif (\Illuminate\Support\Facades\Route::has($routeValue)) {
                            $url = route($routeValue);
                            $moduleActive = request()->routeIs($routeValue) || request()->routeIs(\Illuminate\Support\Str::beforeLast($routeValue, '.').'.*');
                        } else {
                            $url = url($routeValue);
                            $moduleActive = request()->is(trim($routeValue, '/').'*') || request()->is('admin/'.$moduleSlug.'*') || request()->is('admin/'.$slugSnake.'*');
                        }
                    @endphp
                    <li class="nav-item">
                        <a href="{{ $url }}" class="nav-link {{ $moduleActive ? 'active' : '' }}">
                            <i class="nav-icon {{ $moduleIconClass }}"></i><p>{{ $moduleTitle }}</p>
                        </a>
                    </li>
                @endforeach
                
                @role('superadmin')
                <li class="nav-item">
                    <a href="{{ route('admin.aapanel.dashboard') }}" class="nav-link {{ request()->is('admin/aapanel*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-hdd-rack text-info"></i><p>Hospedagem aaPanel</p>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="{{ route('admin.whmcs.index') }}" class="nav-link {{ request()->is('admin/whmcs*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-box-seam"></i><p>Integracao WHMCS</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('admin.cpanel.index') }}" class="nav-link {{ request()->is('admin/cpanel*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-server"></i><p>WHM / cPanel</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('admin.marketplace.index') }}" class="nav-link {{ request()->is('admin/marketplace*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-shop text-primary"></i><p>Marketplace de Addons</p>
                    </a>
                </li>
                @endrole


                <li class="nav-header text-uppercase text-muted fw-bold small px-3 mt-3 mb-1">Seguranca & Sistema</li>

                @permission('roles.manage')
                <li class="nav-item">
                    <a href="{{ route('admin.permissions.index') }}" class="nav-link {{ request()->is('admin/permissions*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-shield-lock"></i><p>Permissoes</p>
                    </a>
                </li>
                @endpermission

                <li class="nav-item">
                    <a href="{{ route('admin.webhook-monitor.index') }}" class="nav-link {{ request()->is('admin/webhook-monitor*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-broadcast"></i><p>Monitor Webhooks</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ url('/admin/system/cron') }}" class="nav-link {{ request()->is('admin/system/cron*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-clock-history"></i><p>Cron Jobs</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ url('/admin/system/audit') }}" class="nav-link {{ request()->is('admin/system/audit*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-shield-check"></i><p>Auditoria do Sistema</p>
                    </a>
                </li>

                @role('superadmin')
                <li class="nav-item">
                    <a href="{{ route('admin.security.dashboard') }}" class="nav-link {{ request()->is('admin/security*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-shield-fill-check text-danger"></i><p>WAF Enterprise</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('admin.file-editor.index') }}" class="nav-link {{ request()->is('admin/file-editor*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-code-square text-info"></i><p>Editor de Arquivos</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ url('/admin/backups') }}" class="nav-link {{ request()->is('admin/backups*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-cloud-arrow-down"></i><p>Backups</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('admin.logs.index') }}" class="nav-link {{ request()->is('admin/logs*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-file-text"></i><p>Logs do Sistema</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ url('/admin/cache') }}" class="nav-link {{ request()->is('admin/cache*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-lightning-charge text-warning"></i><p>Cache e Limpeza</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('admin.addons.index') }}" class="nav-link {{ request()->is('admin/addons*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-puzzle-fill"></i><p>Gerenciar Addons</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('admin.affiliates.index') }}" class="nav-link {{ request()->is('admin/affiliates*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-people-fill"></i><p>Afiliados</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('admin.holidays.index') }}" class="nav-link {{ request()->is('admin/holidays*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-calendar-event"></i><p>Feriados Nacionais</p>
                    </a>
                </li>
                @endrole

            </ul>
        </nav>
    </div>
</aside>
