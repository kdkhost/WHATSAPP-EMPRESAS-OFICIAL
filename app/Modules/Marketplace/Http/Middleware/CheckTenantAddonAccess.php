<?php

namespace App\Modules\Marketplace\Http\Middleware;

use App\Modules\Marketplace\Services\AddonMarketplaceService;
use App\Modules\Tenancy\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckTenantAddonAccess
{
    public function __construct(protected AddonMarketplaceService $marketplace) {}

    public function handle(Request $request, Closure $next, ?string $slug = null)
    {
        $slug = $slug ?: $request->route('slug');

        if (! $slug) {
            abort(403, 'Addon nao especificado.');
        }

        // Modo assistido: superadmin supervisionando tenant — permite acesso
        if ($request->session()->get('admin_assist_mode') && Auth::guard('admin')->check()) {
            return $next($request);
        }

        $user = Auth::user();
        $tenant = Tenant::query()->where('user_id', $user?->id)->first();

        if (! $tenant || ! $this->marketplace->tenantHasAddon($tenant, $slug)) {
            abort(403, 'Este addon nao esta ativo para a sua conta.');
        }

        return $next($request);
    }
}
