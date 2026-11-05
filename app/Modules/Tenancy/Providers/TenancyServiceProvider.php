<?php

namespace App\Modules\Tenancy\Providers;

use App\Modules\Tenancy\Services\InstanceManager;
use App\Modules\Tenancy\Services\TenantBillingService;
use App\Modules\Tenancy\Services\TenantPackageService;
use App\Modules\Tenancy\Services\TenantTemplateResolver;
use App\Services\CronManager;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(InstanceManager::class);
        $this->app->singleton(TenantBillingService::class);
        $this->app->singleton(TenantTemplateResolver::class);
        $this->app->singleton(TenantPackageService::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(app_path('Modules/Tenancy/Resources/views'), 'tenancy');

        // Rotas superadmin (gerencia planos, tenants, instancias)
        Route::middleware(['web', 'admin', '2fa', 'role:superadmin'])->prefix('admin/tenancy')->name('admin.tenancy.')->group(function () {
            $ctrl = \App\Modules\Tenancy\Http\Controllers\TenancyAdminController::class;
            $assist = \App\Modules\Tenancy\Http\Controllers\TenantAssistController::class;
            Route::get('/',               [$ctrl, 'index'])->name('index');
            Route::get('/plans',          [$ctrl, 'plans'])->name('plans');
            Route::post('/plans',         [$ctrl, 'storePlan'])->name('plans.store');
            Route::put('/plans/{id}',     [$ctrl, 'updatePlan'])->name('plans.update');
            Route::delete('/plans/{id}',  [$ctrl, 'destroyPlan'])->name('plans.destroy');
            Route::get('/extra-instance-prices', [$ctrl, 'extraInstancePrices'])->name('extra-instance-prices');
            Route::post('/extra-instance-prices', [$ctrl, 'storeExtraInstancePrice'])->name('extra-instance-prices.store');
            Route::put('/extra-instance-prices/{id}', [$ctrl, 'updateExtraInstancePrice'])->name('extra-instance-prices.update');
            Route::delete('/extra-instance-prices/{id}', [$ctrl, 'destroyExtraInstancePrice'])->name('extra-instance-prices.destroy');
            Route::get('/tenants',        [$ctrl, 'tenants'])->name('tenants');
            Route::get('/tenants/{id}',   [$ctrl, 'showTenant'])->name('tenants.show');
            Route::get('/tenants/{id}/edit', [$ctrl, 'editTenant'])->name('tenants.edit');
            Route::put('/tenants/{id}',   [$ctrl, 'updateTenant'])->name('tenants.update');
            Route::delete('/tenants/{id}',[$ctrl, 'destroyTenant'])->name('tenants.destroy');
            Route::post('/tenants/{id}/toggle-status', [$ctrl, 'toggleStatus'])->name('tenants.toggle-status');
            Route::post('/tenants/purge-non-brazilians', [$ctrl, 'purgeNonBrazilians'])->name('tenants.purge-non-brazilians');
            Route::post('/tenants/{id}/suspend', [$ctrl, 'suspendTenant'])->name('tenants.suspend');
            Route::post('/tenants/{id}/activate',[$ctrl, 'activateTenant'])->name('tenants.activate');
            Route::delete('/tenants/{tenantId}/instances/{instanceId}', [$ctrl, 'destroyTenantInstance'])->name('tenants.instances.destroy');
            Route::post('/tenants/{tenantId}/instances/{instanceId}/sync', [$ctrl, 'syncTenantInstance'])->name('tenants.instances.sync');
            Route::post('/tenants/{id}/assumir', [$assist, 'start'])->name('tenants.assist.start');
            Route::post('/tenants/{id}/disable-2fa', [$ctrl, 'disableUserTwoFactor'])->name('tenants.disable-2fa');
            Route::post('/tenants/{id}/affiliate', [$ctrl, 'saveTenantAffiliate'])->name('tenants.affiliate.save');
        });

        // Rotas do tenant (area do cliente logado)
        $tenantCtrl = \App\Modules\Tenancy\Http\Controllers\TenantPanelController::class;
        $assistCtrl = \App\Modules\Tenancy\Http\Controllers\TenantAssistController::class;

        // Rotas de 2FA e preferencias NAO devem ter middleware 2fa (evita loop de redirect)
        Route::middleware(['web', 'auth'])->prefix('painel')->name('tenant.')->group(function () use ($tenantCtrl) {
            Route::post('/preferencias/tema', function (\Illuminate\Http\Request $request) {
                $theme = $request->input('theme') === 'dark' ? 'dark' : 'light';
                $user = \Illuminate\Support\Facades\Auth::user();

                if ($user && \Illuminate\Support\Facades\Schema::hasColumn('users', 'theme_dark')) {
                    $user->forceFill(['theme_dark' => $theme === 'dark'])->save();
                }

                $request->session()->put('theme_mode', $theme);

                return response()->json(['ok' => true, 'theme' => $theme])
                    ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
                    ->header('Pragma', 'no-cache')
                    ->header('Expires', '0')
                    ->header('Vary', 'Cookie')
                    ->cookie('admin_theme', $theme, 60 * 24 * 365, '/', null, false, false);
            })->name('preferences.theme');
            Route::get('/2fa/verify',  [$tenantCtrl, 'showTwoFactorVerify'])->name('2fa.verify');
            Route::post('/2fa/verify', [$tenantCtrl, 'verifyTwoFactor'])->name('2fa.verify.check');
        });

        Route::middleware(['web', 'auth', '2fa'])->prefix('painel')->name('tenant.')->group(function () use ($tenantCtrl, $assistCtrl) {
            $ctrl = $tenantCtrl;
            $assist = $assistCtrl;
            Route::get('/',                [$ctrl, 'dashboard'])->name('dashboard');
            Route::get('/perfil',          [$ctrl, 'profile'])->name('profile');
            Route::post('/perfil',         [$ctrl, 'updateProfile'])->name('profile.update');
            Route::get('/planos',          [$ctrl, 'plans'])->name('plans');
            Route::post('/planos/assinar', [$ctrl, 'subscribePlan'])->name('plans.subscribe');
            Route::get('/clientes',        [$ctrl, 'customers'])->name('customers');
            Route::post('/clientes/sync-whmcs', [$ctrl, 'syncWhmcs'])->name('customers.sync-whmcs');
            Route::post('/clientes',       [$ctrl, 'storeCustomer'])->name('customers.store');
            Route::get('/clientes/{id}',   [$ctrl, 'showCustomer'])->name('customers.show');
            Route::put('/clientes/{id}',   [$ctrl, 'updateCustomer'])->name('customers.update');
            Route::delete('/clientes/{id}', [$ctrl, 'destroyCustomer'])->name('customers.destroy');
            Route::post('/perfil/senha',   [$ctrl, 'updateProfilePassword'])->name('profile.password');
            Route::post('/perfil/2fa/setup', [$ctrl, 'setupTwoFactor'])->name('profile.2fa.setup');
            Route::post('/perfil/2fa/confirm', [$ctrl, 'confirmTwoFactor'])->name('profile.2fa.confirm');
            Route::post('/perfil/2fa/disable', [$ctrl, 'disableTwoFactor'])->name('profile.2fa.disable');
            Route::get('/notificacoes', [\App\Http\Controllers\Admin\NotificationController::class, 'tenantIndex'])->name('notifications');
            Route::get('/notificacoes/feed', [\App\Http\Controllers\Admin\NotificationController::class, 'tenantFeed'])->name('notifications.feed');
            Route::get('/notificacoes/{notification}', [\App\Http\Controllers\Admin\NotificationController::class, 'tenantShow'])->name('notifications.show');
            Route::post('/notificacoes/{notification}/ler', [\App\Http\Controllers\Admin\NotificationController::class, 'tenantRead'])->name('notifications.read');
            Route::post('/notificacoes/read-all', [\App\Http\Controllers\Admin\NotificationController::class, 'tenantReadAll'])->name('notifications.readAll');
            Route::post('/notificacoes/som', [\App\Http\Controllers\Admin\NotificationController::class, 'tenantSound'])->name('notifications.sound');
            Route::post('/tour/complete', function () {
                $user = \Illuminate\Support\Facades\Auth::user();
                $user?->update(['tour_completed_at' => now()]);

                return response()->json(['ok' => true]);
            })->name('tour.complete');
            Route::post('/tour/reset', function () {
                $user = \Illuminate\Support\Facades\Auth::user();
                $user?->update(['tour_completed_at' => null]);

                return response()->json(['ok' => true]);
            })->name('tour.reset');
            Route::get('/instancias',      [$ctrl, 'instances'])->name('instances');
            Route::post('/instancias',     [$ctrl, 'createInstance'])->name('instances.create');
            Route::put('/instancias/{id}', [$ctrl, 'renameInstance'])->name('instances.rename');
            Route::delete('/instancias/{id}', [$ctrl, 'deleteInstance'])->name('instances.destroy');
            Route::get('/instancias/{id}/qr', [$ctrl, 'qrCode'])->name('instances.qr');
            Route::post('/instancias/{id}/sincronizar', [$ctrl, 'syncInstance'])->name('instances.sync');
            Route::post('/instancias/{id}/desconectar', [$ctrl, 'disconnectInstance'])->name('instances.disconnect');
            Route::post('/instancias/{id}/config', [$ctrl, 'saveInstanceConfig'])->name('instances.config');
            Route::get('/seguranca/limites',  [$ctrl, 'getSafetyLimits'])->name('safety.limits.get');
            Route::post('/seguranca/limites', [$ctrl, 'updateSafetyLimits'])->name('safety.limits');
            Route::get('/templates',       [$ctrl, 'templates'])->name('templates');
            Route::post('/templates',      [$ctrl, 'storeTemplate'])->name('templates.store');
            Route::put('/templates/{id}',  [$ctrl, 'updateTemplate'])->name('templates.update');
            Route::post('/templates/{id}/clonar', [$ctrl, 'cloneTemplate'])->name('templates.clone');
            Route::post('/templates/{id}/preview', [$ctrl, 'previewTemplate'])->name('templates.preview');
            Route::post('/templates/{id}/restaurar', [$ctrl, 'resetTemplateToDefault'])->name('templates.reset');
            Route::post('/templates/{id}/padrao', [$ctrl, 'setDefaultTemplate'])->name('templates.default');
            Route::delete('/templates/{id}', [$ctrl, 'deleteTemplate'])->name('templates.destroy');
            Route::get('/pacotes',         [$ctrl, 'packages'])->name('packages');
            Route::post('/pacotes',        [$ctrl, 'storePackage'])->name('packages.store');
            Route::put('/pacotes/{id}',    [$ctrl, 'updatePackage'])->name('packages.update');
            Route::delete('/pacotes/{id}', [$ctrl, 'destroyPackage'])->name('packages.destroy');
            Route::post('/pacotes/vincular', [$ctrl, 'assignPackage'])->name('packages.assign');
            Route::put('/contratacoes/{id}', [$ctrl, 'updateSubscription'])->name('subscriptions.update');
            Route::delete('/contratacoes/{id}', [$ctrl, 'cancelSubscription'])->name('subscriptions.cancel');
            Route::get('/gateways',        [$ctrl, 'gateways'])->name('gateways');
            Route::post('/gateways',       [$ctrl, 'saveGateway'])->name('gateways.save');
            Route::post('/gateways/test',  [$ctrl, 'testGateway'])->name('gateways.test');
            Route::post('/gateways/upload-certificate', [$ctrl, 'uploadCertificate'])->name('gateways.upload_certificate');
            Route::delete('/gateways/certificate/{gateway}', [$ctrl, 'deleteCertificate'])->name('gateways.delete_certificate');
            Route::delete('/gateways/{gateway}', [$ctrl, 'deleteGateway'])->name('gateways.delete');
            Route::get('/cobranca',        [$ctrl, 'billingConfig'])->name('billing');
            Route::post('/cobranca',       [$ctrl, 'saveBillingConfig'])->name('billing.save');
            Route::get('/faturas',         [$ctrl, 'myInvoices'])->name('invoices');
            Route::post('/faturas/avulsa',  [$ctrl, 'storeStandaloneInvoice'])->name('invoices.standalone.store');
            Route::get('/relatorios',      [$ctrl, 'reports'])->name('reports');
            Route::get('/whatsapp/manual', [$ctrl, 'waManual'])->name('whatsapp.manual');
            Route::get('/historico/whatsapp', [$ctrl, 'waHistory'])->name('history.whatsapp');
            Route::get('/whatsapp/customers/search', [$ctrl, 'waCustomerSearch'])->name('whatsapp.customers.search');
            Route::post('/whatsapp/render-template',  [$ctrl, 'waRenderTemplate'])->name('whatsapp.render-template');
            Route::post('/whatsapp/send-manual',     [$ctrl, 'waSendManual'])->name('whatsapp.send-manual');
            Route::get('/historico/email',    [$ctrl, 'emailHistory'])->name('history.email');
            // Envios agendados
            Route::get('/envios-agendados', [$ctrl, 'dispatches'])->name('dispatches');
            // Calendário de faturas
            Route::get('/calendario', [$ctrl, 'calendar'])->name('calendar');
            Route::get('/calendario/eventos', [$ctrl, 'calendarEvents'])->name('calendar.events');
            Route::post('/calendario/mover', [$ctrl, 'moveCalendarEvent'])->name('calendar.move');
            Route::get('/faturas/{id}', [$ctrl, 'showInvoice'])->name('invoices.show');
            Route::put('/faturas/{id}', [$ctrl, 'updateInvoice'])->name('invoices.update');
            Route::get('/faturas/plano/{id}',           [$ctrl, 'showPlanInvoice'])->name('invoices.plan.show');
            Route::post('/faturas/plano/{id}/pagar',    [$ctrl, 'payPlanInvoice'])->name('invoices.plan.pay');
            Route::post('/faturas/plano/{id}/cancelar', [$ctrl, 'cancelPlanInvoice'])->name('invoices.plan.cancel');
            Route::post('/faturas/lote',           [$ctrl, 'bulkInvoices'])->name('invoices.bulk');
            Route::post('/faturas/mesclar',        [$ctrl, 'mergeInvoices'])->name('invoices.merge');
            Route::post('/faturas/{id}/antecipar', [$ctrl, 'anticipateInvoice'])->name('invoices.anticipate');
            Route::post('/faturas/{id}/reenviar',  [$ctrl, 'resendInvoice'])->name('invoices.resend');
            Route::post('/faturas/{id}/cancelar',  [$ctrl, 'cancelInvoice'])->name('invoices.cancel');
            Route::post('/faturas/{id}/dar-baixa', [$ctrl, 'settleInvoice'])->name('invoices.settle');
            Route::get('/faturas/{id}/contexto-parcelamento', [$ctrl, 'invoiceInstallmentContext'])->name('invoices.installment-context');
            Route::delete('/faturas/{id}',         [$ctrl, 'destroyInvoice'])->name('invoices.destroy');

            // Feriados do tenant (ações integradas ao Calendário único)
            $holidayCtrl = \App\Modules\Tenancy\Http\Controllers\TenantHolidayController::class;
            Route::get('/feriados', fn() => redirect()->route('tenant.calendar'))->name('holidays');
            Route::post('/feriados', [$holidayCtrl, 'store'])->name('holidays.store');
            Route::put('/feriados/{id}', [$holidayCtrl, 'update'])->name('holidays.update');
            Route::delete('/feriados/{id}', [$holidayCtrl, 'destroy'])->name('holidays.destroy');

            // Histórico de mensagens de cobrança
            Route::get('/historico-cobranca', [$ctrl, 'billingMessageHistory'])->name('billing.history');
            Route::post('/uploads/{context?}', [\App\Http\Controllers\Admin\UploadController::class, 'store'])->name('uploads.store');
            Route::post('/voltar-admin',   [$assist, 'stop'])->name('assist.stop');
        });

        // Crons de tenancy
        CronManager::register(
            name: 'tenancy.generate_invoices',
            callback: fn () => app(TenantBillingService::class)->generateDueInvoices(),
            intervalMinutes: 720, // 12 horas
            description: 'Gera faturas de mensalidade para tenants prestes a vencer.'
        );
        CronManager::register(
            name: 'tenancy.suspend_overdue',
            callback: fn () => app(TenantBillingService::class)->suspendOverdue(),
            intervalMinutes: 1440, // 24 horas
            description: 'Suspende tenants com faturas vencidas apos grace period.'
        );
        CronManager::register(
            name: 'tenancy.expire_trials',
            callback: fn () => app(TenantBillingService::class)->expireTrials(),
            intervalMinutes: 360, // 6 horas
            description: 'Expira e suspende trials vencidos.'
        );
    }
}
