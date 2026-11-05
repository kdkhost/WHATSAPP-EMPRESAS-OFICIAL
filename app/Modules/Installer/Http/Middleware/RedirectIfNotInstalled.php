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
            if (! $request->is('install*') && ! $request->is('installer.php*')) {
                return redirect('/installer.php');
            }
        }
        return $next($request);
    }
}
