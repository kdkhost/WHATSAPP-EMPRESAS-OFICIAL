<?php

namespace App\Modules\Installer\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redireciona para /install se o sistema NAO esta instalado.
 * Aplicado em todas as rotas web/admin exceto /install.
 */
class RedirectIfNotInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! file_exists(storage_path('installed.lock'))) {
            // Auto-detecta se o banco já está instalado/migrado (ambiente local sqlite ou tabelas existentes)
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('admin_users') || \Illuminate\Support\Facades\Schema::hasTable('users')) {
                    @file_put_contents(storage_path('installed.lock'), json_encode([
                        'installed_at'  => now()->toIso8601String(),
                        'auto_detected' => true,
                    ], JSON_PRETTY_PRINT));

                    return $next($request);
                }
            } catch (\Throwable) {}

            if (! $request->is('install*') && ! $request->is('installer.php*')) {
                return redirect('/installer.php');
            }
        }
        return $next($request);
    }
}
