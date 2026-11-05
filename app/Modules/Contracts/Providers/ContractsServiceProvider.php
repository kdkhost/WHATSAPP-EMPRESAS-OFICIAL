<?php

namespace App\Modules\Contracts\Providers;

use App\Modules\Contracts\Http\Middleware\TenantContractMiddleware;
use App\Modules\Contracts\Services\ContractAccessService;
use App\Modules\Contracts\Services\ContractLimitService;
use App\Modules\Contracts\Services\ContractService;
use App\Modules\Contracts\Services\ContractTemplateCatalog;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ContractsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ContractService::class);
        $this->app->singleton(ContractTemplateCatalog::class);
        $this->app->singleton(ContractAccessService::class);
        $this->app->singleton(ContractLimitService::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(app_path('Modules/Contracts/Resources/views'), 'contracts');

        // Registra o middleware nomeado
        $this->app['router']->aliasMiddleware('contracts.tenant', TenantContractMiddleware::class);

        // Blade directive para verificar se o tenant tem acesso ao módulo
        Blade::if('contractsEnabled', function () {
            /** @var ContractAccessService $service */
            $service = app(ContractAccessService::class);

            // Superadmin sempre vê
            if (\Illuminate\Support\Facades\Auth::guard('admin')->check()) {
                $admin = \Illuminate\Support\Facades\Auth::guard('admin')->user();
                if ($admin?->role === 'superadmin') {
                    return true;
                }
            }

            if (\Illuminate\Support\Facades\Auth::guard('web')->check()) {
                $user = \Illuminate\Support\Facades\Auth::guard('web')->user();
                if (! $user) return false;
                $tenant = \App\Modules\Tenancy\Models\Tenant::where('user_id', $user->id)->first();
                if (! $tenant && ! empty($user->tenant_id)) {
                    $tenant = \App\Modules\Tenancy\Models\Tenant::find($user->tenant_id);
                }
                return $service->tenantHasAccess($tenant);
            }

            return false;
        });

        // Página pública — sem auth (acesso por token)
        Route::middleware(['web'])->group(function () {
            $pub = \App\Modules\Contracts\Http\Controllers\PublicContractController::class;
            Route::get('/contrato/{token}',           [$pub, 'show'])->name('public.contracts.show');
            Route::post('/contrato/{token}/assinar',  [$pub, 'sign'])->name('public.contracts.sign');
            Route::get('/contrato/{token}/sucesso',   [$pub, 'signed'])->name('public.contracts.signed');
            Route::get('/contrato/{token}/baixar',    [$pub, 'download'])->name('public.contracts.download');
        });

        // Painel superadmin
        Route::middleware(['web', 'admin', '2fa'])->prefix('admin/contracts')->name('admin.contracts.')->group(function () {
            $this->contractAdminRoutes();
        });

        // Painel tenant — rota de upgrade (sem middleware de acesso ao módulo)
        // Nome único 'tenant.contracts.upgrade' registrado apenas aqui
        Route::middleware(['web', 'auth'])->prefix('painel/contratos')
            ->group(function () {
                Route::get('/upgrade', function () {
                    $accessService = app(ContractAccessService::class);
                    if (\Illuminate\Support\Facades\Auth::guard('web')->check()) {
                        $user = \Illuminate\Support\Facades\Auth::guard('web')->user();
                        $tenant = $user ? \App\Modules\Tenancy\Models\Tenant::where('user_id', $user->id)->first() : null;
                        if ($tenant && $accessService->tenantHasAccess($tenant)) {
                            return redirect()->route('tenant.contracts.index');
                        }
                    }
                    return view('contracts::tenant.upgrade');
                })->name('tenant.contracts.upgrade');
            });

        // Painel tenant — rotas protegidas pelo middleware de acesso
        Route::middleware(['web', 'auth', '2fa', 'contracts.tenant'])->prefix('painel/contratos')->name('tenant.contracts.')->group(function () {
            $this->contractTenantRoutes();
        });
    }

    protected function contractAdminRoutes(): void
    {
        $ctrl = \App\Modules\Contracts\Http\Controllers\ContractsAdminController::class;

        Route::get('/',                      [$ctrl, 'index'])->name('index');
        Route::get('/criar',                 [$ctrl, 'create'])->name('create');
        Route::post('/',                     [$ctrl, 'store'])->name('store');
        Route::get('/customers/search',      [$ctrl, 'searchCustomers'])->name('customers.search');
        Route::get('/customers/{customerId}/invoices', [$ctrl, 'customerInvoices'])->name('customers.invoices');
        Route::get('/{id}',                  [$ctrl, 'show'])->name('show');
        Route::get('/{id}/editar',           [$ctrl, 'edit'])->name('edit');
        Route::put('/{id}',                  [$ctrl, 'update'])->name('update');
        Route::post('/{id}/enviar',          [$ctrl, 'send'])->name('send');
        Route::delete('/{id}',               [$ctrl, 'destroy'])->name('destroy');

        Route::get('/templates/list',        [$ctrl, 'templates'])->name('templates');
        Route::get('/templates/criar',       [$ctrl, 'createTemplate'])->name('templates.create');
        Route::post('/templates',            [$ctrl, 'storeTemplate'])->name('templates.store');
        Route::get('/templates/{id}/editar', [$ctrl, 'editTemplate'])->name('templates.edit');
        Route::put('/templates/{id}',        [$ctrl, 'updateTemplate'])->name('templates.update');
    }

    protected function contractTenantRoutes(): void
    {
        $ctrl = \App\Modules\Contracts\Http\Controllers\ContractsAdminController::class;

        Route::get('/',                      [$ctrl, 'index'])->name('index');
        Route::get('/criar',                 [$ctrl, 'create'])->name('create');
        Route::post('/',                     [$ctrl, 'store'])->name('store');
        Route::get('/customers/search',      [$ctrl, 'searchCustomers'])->name('customers.search');
        Route::get('/customers/{customerId}/invoices', [$ctrl, 'customerInvoices'])->name('customers.invoices');
        Route::get('/{id}',                  [$ctrl, 'show'])->name('show');
        Route::get('/{id}/editar',           [$ctrl, 'edit'])->name('edit');
        Route::put('/{id}',                  [$ctrl, 'update'])->name('update');
        Route::post('/{id}/enviar',          [$ctrl, 'send'])->name('send');
        Route::delete('/{id}',               [$ctrl, 'destroy'])->name('destroy');

        Route::get('/templates/list',        [$ctrl, 'templates'])->name('templates');
        Route::get('/templates/criar',       [$ctrl, 'createTemplate'])->name('templates.create');
        Route::post('/templates',            [$ctrl, 'storeTemplate'])->name('templates.store');
        Route::get('/templates/{id}/editar', [$ctrl, 'editTemplate'])->name('templates.edit');
        Route::put('/templates/{id}',        [$ctrl, 'updateTemplate'])->name('templates.update');
    }
}
