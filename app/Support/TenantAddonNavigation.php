<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

class TenantAddonNavigation
{
    public static function aapanel(): array
    {
        return [
            [
                'label' => 'Dashboard',
                'icon' => 'bi bi-speedometer2',
                'url' => self::route('painel.aapanel.dashboard', '/painel/aapanel'),
                'active' => request()->routeIs('painel.aapanel.dashboard'),
            ],
            [
                'label' => 'Servidores',
                'icon' => 'bi bi-hdd-network',
                'url' => self::route('painel.aapanel.servers.index', '/painel/aapanel/servers'),
                'active' => request()->routeIs('painel.aapanel.servers.*'),
            ],
            [
                'label' => 'Planos',
                'icon' => 'bi bi-layers',
                'url' => self::route('painel.aapanel.plans.index', '/painel/aapanel/plans'),
                'active' => request()->routeIs('painel.aapanel.plans.*'),
            ],
            [
                'label' => 'Contas',
                'icon' => 'bi bi-globe',
                'url' => self::route('painel.aapanel.accounts.index', '/painel/aapanel/accounts'),
                'active' => request()->routeIs('painel.aapanel.accounts.*'),
            ],
            [
                'label' => 'Logs',
                'icon' => 'bi bi-journal-text',
                'url' => self::route('tenant.addons.aapanel.logs', '/painel/addons/aapanel/logs'),
                'active' => request()->routeIs('tenant.addons.aapanel.logs'),
            ],
            [
                'label' => 'Configurações',
                'icon' => 'bi bi-gear',
                'url' => self::route('tenant.addons.aapanel.settings', '/painel/addons/aapanel/configuracoes'),
                'active' => request()->routeIs('tenant.addons.aapanel.settings'),
            ],
        ];
    }

    public static function whmcs(): array
    {
        return [
            [
                'label' => 'Dashboard',
                'icon' => 'bi bi-speedometer2',
                'url' => self::route('tenant.whmcs.index', '/painel/whmcs'),
                'active' => request()->routeIs('tenant.whmcs.index'),
            ],
            [
                'label' => 'Conexões',
                'icon' => 'bi bi-plug',
                'url' => self::route('tenant.whmcs.index', '/painel/whmcs'),
                'active' => request()->routeIs('tenant.whmcs.index'),
            ],
            [
                'label' => 'Sincronização',
                'icon' => 'bi bi-arrow-repeat',
                'url' => self::route('tenant.whmcs.index', '/painel/whmcs'),
                'active' => request()->routeIs('tenant.whmcs.index'),
            ],
            [
                'label' => 'Webhooks',
                'icon' => 'bi bi-broadcast',
                'url' => self::route('tenant.whmcs.index', '/painel/whmcs'),
                'active' => request()->routeIs('tenant.whmcs.index'),
            ],
            [
                'label' => 'Logs',
                'icon' => 'bi bi-journal-text',
                'url' => self::route('tenant.addons.whmcs.logs', '/painel/addons/whmcs/logs'),
                'active' => request()->routeIs('tenant.addons.whmcs.logs'),
            ],
            [
                'label' => 'Configurações',
                'icon' => 'bi bi-gear',
                'url' => self::route('tenant.addons.whmcs.settings', '/painel/addons/whmcs/configuracoes'),
                'active' => request()->routeIs('tenant.addons.whmcs.settings'),
            ],
        ];
    }

    public static function cpanel(): array
    {
        return [
            [
                'label' => 'Dashboard',
                'icon' => 'bi bi-speedometer2',
                'url' => self::route('tenant.cpanel.index', '/painel/cpanel'),
                'active' => request()->routeIs('tenant.cpanel.index'),
            ],
            [
                'label' => 'Servidores',
                'icon' => 'bi bi-server',
                'url' => self::route('tenant.cpanel.index', '/painel/cpanel'),
                'active' => request()->routeIs('tenant.cpanel.index'),
            ],
            [
                'label' => 'Contas',
                'icon' => 'bi bi-person-vcard',
                'url' => self::route('tenant.cpanel.index', '/painel/cpanel'),
                'active' => request()->routeIs('tenant.cpanel.index'),
            ],
            [
                'label' => 'Pacotes',
                'icon' => 'bi bi-box',
                'url' => self::route('tenant.cpanel.index', '/painel/cpanel'),
                'active' => request()->routeIs('tenant.cpanel.index'),
            ],
            [
                'label' => 'Logs',
                'icon' => 'bi bi-journal-text',
                'url' => self::route('tenant.addons.cpanel.logs', '/painel/addons/cpanel/logs'),
                'active' => request()->routeIs('tenant.addons.cpanel.logs'),
            ],
            [
                'label' => 'Configurações',
                'icon' => 'bi bi-gear',
                'url' => self::route('tenant.addons.cpanel.settings', '/painel/addons/cpanel/configuracoes'),
                'active' => request()->routeIs('tenant.addons.cpanel.settings'),
            ],
        ];
    }

    private static function route(string $name, string $fallback): string
    {
        return Route::has($name) ? route($name) : url($fallback);
    }
}
