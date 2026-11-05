<?php

use App\Models\AdminUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

function marketplaceAdmin(): AdminUser
{
    return AdminUser::create([
        'name' => 'Superadmin Marketplace',
        'email' => 'marketplace-superadmin@example.com',
        'password' => bcrypt('password'),
        'role' => 'superadmin',
        'active' => true,
    ]);
}

it('exibe todos os addons internos do marketplace como instalaveis', function () {
    foreach (['analytics_pro', 'analytics-pro', 'crm_deals', 'sms_gateway', 'asaas_gateway', 'export_excel'] as $dir) {
        File::deleteDirectory(app_path('Addons/'.$dir));
    }

    $this->actingAs(marketplaceAdmin(), 'admin')
        ->get(route('admin.addons.index'))
        ->assertOk()
        ->assertSee('data-marketplace-install="analytics-pro"', false)
        ->assertSee('data-marketplace-install="crm-deals"', false)
        ->assertSee('data-marketplace-install="sms-gateway"', false)
        ->assertSee('data-marketplace-install="asaas-gateway"', false)
        ->assertSee('data-marketplace-install="export-excel"', false)
        ->assertDontSee('Em breve');
});

it('instala addon interno do marketplace com telas e menus comerciais', function () {
    File::deleteDirectory(app_path('Addons/analytics_pro'));
    File::deleteDirectory(app_path('Addons/analytics-pro'));

    try {
        $this->actingAs(marketplaceAdmin(), 'admin')
            ->postJson(route('admin.addons.marketplace.download', 'analytics-pro'))
            ->assertOk()
            ->assertJsonPath('ok', true);

        expect(File::exists(app_path('Addons/analytics_pro/addon.json')))->toBeTrue()
            ->and(File::exists(app_path('Addons/analytics_pro/AddonServiceProvider.php')))->toBeTrue()
            ->and(File::exists(app_path('Addons/analytics_pro/Resources/views/index.blade.php')))->toBeTrue();

        $manifest = json_decode(File::get(app_path('Addons/analytics_pro/addon.json')), true);

        expect(data_get($manifest, 'marketplace.commercial'))->toBeTrue()
            ->and(data_get($manifest, 'menu.items'))->toHaveCount(4)
            ->and(DB::table('registered_modules')->where('slug', 'analytics-pro')->where('installed', true)->exists())->toBeTrue()
            ->and(DB::table('addon_menus')->where('module_slug', 'analytics-pro')->where('active', true)->count())->toBe(4)
            ->and(File::exists(app_path('Addons/analytics_pro/Jobs/ProcessAddonJob.php')))->toBeTrue()
            ->and(File::files(app_path('Addons/analytics_pro/database/migrations')))->not->toBeEmpty()
            ->and(DB::getSchemaBuilder()->hasTable('addon_analytics_pro_records'))->toBeTrue();

        view()->addNamespace('addon-analytics-pro', app_path('Addons/analytics_pro/Resources/views'));
        Route::get('/_test/addons/analytics-pro')->name('admin.addons.generated.analytics_pro.index');
        Route::get('/_test/addons/analytics-pro/registros')->name('admin.addons.generated.analytics_pro.records');
        Route::post('/_test/addons/analytics-pro/registros')->name('admin.addons.generated.analytics_pro.records.store');
        Route::delete('/_test/addons/analytics-pro/registros/{id}')->name('admin.addons.generated.analytics_pro.records.destroy');
        Route::get('/_test/addons/analytics-pro/configuracoes')->name('admin.addons.generated.analytics_pro.settings');
        Route::post('/_test/addons/analytics-pro/configuracoes')->name('admin.addons.generated.analytics_pro.settings.save');
        Route::get('/_test/addons/analytics-pro/relatorios')->name('admin.addons.generated.analytics_pro.reports');
        Route::post('/_test/addons/analytics-pro/cron/run')->name('admin.addons.generated.analytics_pro.cron.run');
        app('router')->getRoutes()->refreshNameLookups();

        $html = view('addon-analytics-pro::index', [
            'screen' => 'dashboard',
            'module' => [
                'name' => 'Analytics Pro',
                'description' => 'Relatorios avancados',
                'icon' => 'bi-graph-up-arrow',
                'version' => '1.0.0',
                'author' => 'Cobranca WhatsApp',
                'audience' => 'Superadmin/Admin/Tenant',
                'category' => 'Analytics',
            ],
            'config' => [
                'enabled_for_sale' => true,
                'monthly_fee' => '0,00',
                'public_description' => 'Relatorios avancados',
                'api_provider' => 'interno',
                'api_key' => '',
                'webhook_url' => '',
                'cron_enabled' => true,
                'cron_interval' => 60,
                'last_cron_at' => '',
            ],
            'records' => collect(),
            'stats' => ['total' => 0, 'pending' => 0, 'processed' => 0, 'failed' => 0, 'amount' => 0],
            'cronState' => null,
        ])->render();

        expect($html)->toContain('Analytics Pro')
            ->and($html)->toContain('Comercializavel')
            ->and($html)->toContain('Registros')
            ->and($html)->toContain('Cron interno');
    } finally {
        File::deleteDirectory(app_path('Addons/analytics_pro'));
        File::deleteDirectory(app_path('Addons/analytics-pro'));
    }
});
