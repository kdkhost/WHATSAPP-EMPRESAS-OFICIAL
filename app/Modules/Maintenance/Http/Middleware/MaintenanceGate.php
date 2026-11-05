<?php

namespace App\Modules\Maintenance\Http\Middleware;

use App\Modules\Settings\Services\SettingsManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de manutencao do modulo Maintenance.
 *
 * - Quando ativado, exibe pagina personalizada com contador regressivo.
 * - Liberacao por IP, MAC ou fingerprint de dispositivo (tabela trusted_devices).
 * - O painel /admin sempre passa.
 */
class MaintenanceGate
{
    public function handle(Request $request, Closure $next, ?SettingsManager $settings = null): Response
    {
        $settings = $settings ?: app(SettingsManager::class);
        $maintenanceEnabled = (bool) $settings->get('maintenance.enabled', false);
        
        if (! $maintenanceEnabled) {
            return $next($request);
        }

        // Sempre liberar o painel admin e rotas de auth publica
        if ($request->is('admin*', 'cron*', 'login', 'logout', 'cadastro', 'esqueci-senha', 'redefinir-senha*')) {
            return $next($request);
        }

        $ip = $request->ip();

        try {
            $allowed = DB::table('trusted_devices')
                ->where('active', true)
                ->where(function ($q) use ($ip) {
                    $q->where('ip', $ip);
                    // MAC e fingerprint sao opcionais (vem de cookie/header)
                })
                ->exists();
        } catch (\Throwable) {
            $allowed = false;
        }

        if ($allowed) {
            return $next($request);
        }

        return response()->view('frontend.maintenance', [
            'title'              => $settings->get('maintenance.title', 'Em manutencao'),
            'message'            => $settings->get('maintenance.message', 'Estamos realizando melhorias. Voltamos em breve!'),
            'until_at'           => $settings->get('maintenance.until_at'),
            'logo'               => $settings->get('app.logo'),
            'background_image'   => $settings->get('maintenance.background_image', ''),
            'background_opacity' => $settings->get('maintenance.background_opacity', 50),
            'overlay_color'      => $settings->get('maintenance.overlay_color', '#000000'),
            'text_color'         => $settings->get('maintenance.text_color', '#ffffff'),
        ], 503);
    }
}
