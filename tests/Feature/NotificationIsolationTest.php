<?php

use App\Models\AdminUser;
use App\Models\User;
use App\Modules\Tenancy\Models\Plan;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;

function createTenantForNotifications(): array
{
    $plan = Plan::create([
        'name' => 'Plano Notificacoes',
        'slug' => 'plano-notificacoes-' . uniqid(),
        'price' => 0,
        'billing_cycle' => 'monthly',
        'trial_days' => 7,
        'max_instances' => 1,
        'max_customers' => 100,
        'max_invoices_month' => 100,
        'max_messages_month' => 100,
        'custom_gateway' => true,
        'custom_templates' => true,
        'installment_enabled' => true,
        'fee_passthrough' => false,
        'active' => true,
    ]);

    $user = User::create([
        'name' => 'Tenant Notificacoes',
        'email' => 'tenant-notificacoes-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $tenant = Tenant::create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'company_name' => 'Empresa Notificacoes',
        'slug' => 'empresa-notificacoes-' . uniqid(),
        'status' => 'active',
        'current_period_start' => now()->toDateString(),
        'current_period_end' => now()->addMonth()->toDateString(),
    ]);

    return [$user, $tenant];
}

it('isola notificacoes do superadmin e do tenant nas listagens', function () {
    [$tenantUser, $tenant] = createTenantForNotifications();

    $superadmin = AdminUser::create([
        'name' => 'Super Admin',
        'email' => 'superadmin-notificacoes@example.com',
        'password' => 'secret',
        'role' => 'superadmin',
        'active' => true,
        'two_factor_enabled' => false,
    ]);

    DB::table('admin_notifications')->insert([
        'group' => 'system',
        'type' => 'global',
        'title' => 'Aviso exclusivo do superadmin',
        'message' => 'Somente administracao',
        'icon' => 'bi-bell',
        'color' => 'primary',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('admin_notifications')->insert([
        'tenant_id' => $tenant->id,
        'user_id' => $tenantUser->id,
        'group' => 'billing',
        'type' => 'tenant',
        'title' => 'Aviso exclusivo do tenant',
        'message' => 'Somente cliente tenant',
        'url' => '/painel',
        'icon' => 'bi-receipt',
        'color' => 'warning',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($superadmin, 'admin')
        ->get(route('admin.notifications'))
        ->assertOk()
        ->assertSee('Aviso exclusivo do superadmin')
        ->assertDontSee('Aviso exclusivo do tenant');

    $this->actingAs($tenantUser, 'web')
        ->get(route('tenant.notifications'))
        ->assertOk()
        ->assertSee('Aviso exclusivo do tenant')
        ->assertDontSee('Aviso exclusivo do superadmin');
});

it('nao permite que admin comum veja notificacao global do superadmin', function () {
    $admin = AdminUser::create([
        'name' => 'Admin Comum',
        'email' => 'admin-comum-notificacoes@example.com',
        'password' => 'secret',
        'role' => 'admin',
        'active' => true,
        'two_factor_enabled' => false,
    ]);

    DB::table('admin_notifications')->insert([
        'group' => 'system',
        'type' => 'global',
        'title' => 'Aviso global superadmin',
        'message' => 'Restrito ao superadmin',
        'icon' => 'bi-bell',
        'color' => 'primary',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.notifications'))
        ->assertOk()
        ->assertDontSee('Aviso global superadmin');
});

it('marca como lidas apenas as notificacoes do contexto atual', function () {
    [$tenantUser, $tenant] = createTenantForNotifications();

    $superadmin = AdminUser::create([
        'name' => 'Super Admin Leitura',
        'email' => 'superadmin-leitura-notificacoes@example.com',
        'password' => 'secret',
        'role' => 'superadmin',
        'active' => true,
        'two_factor_enabled' => false,
    ]);

    $adminNotificationId = DB::table('admin_notifications')->insertGetId([
        'group' => 'system',
        'type' => 'global',
        'title' => 'Ler somente admin',
        'message' => 'Admin',
        'icon' => 'bi-bell',
        'color' => 'primary',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $tenantNotificationId = DB::table('admin_notifications')->insertGetId([
        'tenant_id' => $tenant->id,
        'user_id' => $tenantUser->id,
        'group' => 'billing',
        'type' => 'tenant',
        'title' => 'Ler somente tenant',
        'message' => 'Tenant',
        'icon' => 'bi-receipt',
        'color' => 'warning',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($superadmin, 'admin')
        ->post(route('admin.notifications.readAll'))
        ->assertOk();

    expect(DB::table('admin_notifications')->where('id', $adminNotificationId)->value('read_at'))->not->toBeNull()
        ->and(DB::table('admin_notifications')->where('id', $tenantNotificationId)->value('read_at'))->toBeNull();

    $this->actingAs($tenantUser, 'web')
        ->post(route('tenant.notifications.readAll'))
        ->assertOk();

    expect(DB::table('admin_notifications')->where('id', $tenantNotificationId)->value('read_at'))->not->toBeNull();
});

it('entrega feed em tempo real isolado por publico', function () {
    [$tenantUser, $tenant] = createTenantForNotifications();

    $superadmin = AdminUser::create([
        'name' => 'Super Admin Feed',
        'email' => 'superadmin-feed-notificacoes@example.com',
        'password' => 'secret',
        'role' => 'superadmin',
        'active' => true,
        'two_factor_enabled' => false,
    ]);

    DB::table('admin_notifications')->insert([
        'group' => 'system',
        'type' => 'admin_feed',
        'title' => 'Feed exclusivo admin',
        'message' => 'Admin',
        'icon' => 'bi-bell',
        'color' => 'primary',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('admin_notifications')->insert([
        'tenant_id' => $tenant->id,
        'user_id' => $tenantUser->id,
        'group' => 'billing',
        'type' => 'tenant_feed',
        'title' => 'Feed exclusivo tenant',
        'message' => 'Tenant',
        'url' => '/painel',
        'icon' => 'bi-receipt',
        'color' => 'warning',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($superadmin, 'admin')
        ->get(route('admin.notifications.feed'))
        ->assertOk()
        ->assertJsonPath('unread', 1)
        ->assertSee('Feed exclusivo admin')
        ->assertDontSee('Feed exclusivo tenant');

    $this->actingAs($tenantUser, 'web')
        ->get(route('tenant.notifications.feed'))
        ->assertOk()
        ->assertJsonPath('unread', 1)
        ->assertSee('Feed exclusivo tenant')
        ->assertDontSee('Feed exclusivo admin');
});

it('salva preferencia de som de notificacao por usuario', function () {
    [$tenantUser] = createTenantForNotifications();

    $admin = AdminUser::create([
        'name' => 'Admin Som',
        'email' => 'admin-som-notificacoes@example.com',
        'password' => 'secret',
        'role' => 'superadmin',
        'active' => true,
        'two_factor_enabled' => false,
    ]);

    $this->actingAs($admin, 'admin')
        ->postJson(route('admin.notifications.sound'), ['enabled' => false])
        ->assertOk()
        ->assertJsonPath('enabled', false);

    expect($admin->refresh()->notification_sound_enabled)->toBeFalse();

    $this->actingAs($tenantUser, 'web')
        ->postJson(route('tenant.notifications.sound'), ['enabled' => false])
        ->assertOk()
        ->assertJsonPath('enabled', false);

    expect($tenantUser->refresh()->notification_sound_enabled)->toBeFalse();
});

it('mantem o contador do sininho proximo ao icone de notificacoes', function () {
    [$tenantUser, $tenant] = createTenantForNotifications();

    DB::table('admin_notifications')->insert([
        'tenant_id' => $tenant->id,
        'user_id' => $tenantUser->id,
        'group' => 'billing',
        'type' => 'tenant_badge',
        'title' => 'Badge proximo ao sininho',
        'message' => 'Tenant',
        'icon' => 'bi-bell',
        'color' => 'primary',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($tenantUser, 'web')
        ->get(route('tenant.dashboard'))
        ->assertOk()
        ->assertSee('notification-bell__link', false)
        ->assertSee('notification-bell__badge', false)
        ->assertDontSee('start-100 translate-middle', false);
});

it('abre o detalhe e marca a notificacao individual do admin como lida', function () {
    $admin = AdminUser::create([
        'name' => 'Admin Detalhe',
        'email' => 'admin-detalhe-notificacoes@example.com',
        'password' => 'secret',
        'role' => 'superadmin',
        'active' => true,
        'two_factor_enabled' => false,
    ]);

    $notificationId = DB::table('admin_notifications')->insertGetId([
        'group' => 'system',
        'type' => 'detail_admin',
        'title' => 'Detalhe do admin',
        'message' => 'Abrir pagina de detalhe',
        'url' => '/admin/dashboard',
        'icon' => 'bi-bell',
        'color' => 'primary',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.notifications.show', $notificationId))
        ->assertOk()
        ->assertSee('Detalhe do admin')
        ->assertSee('Abrir pagina de detalhe')
        ->assertSee(route('admin.notifications.show', $notificationId));

    expect(DB::table('admin_notifications')->where('id', $notificationId)->value('read_at'))->not->toBeNull();

    $this->actingAs($admin, 'admin')
        ->post(route('admin.notifications.read', $notificationId))
        ->assertOk()
        ->assertJsonPath('notification.url', route('admin.notifications.show', $notificationId))
        ->assertJsonPath('notification.detail_url', route('admin.notifications.show', $notificationId))
        ->assertJsonPath('notification.related_url', '/admin/dashboard')
        ->assertJsonPath('unread', 0);
});

it('abre o detalhe do tenant sem expor rota admin na campainha', function () {
    [$tenantUser, $tenant] = createTenantForNotifications();

    $notificationId = DB::table('admin_notifications')->insertGetId([
        'tenant_id' => $tenant->id,
        'user_id' => $tenantUser->id,
        'group' => 'billing',
        'type' => 'detail_tenant',
        'title' => 'Detalhe do tenant',
        'message' => 'Abrir pagina segura do tenant',
        'url' => '/admin/financeiro/faturas/10',
        'icon' => 'bi-bell',
        'color' => 'primary',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($tenantUser, 'web')
        ->get(route('tenant.notifications.feed'))
        ->assertOk()
        ->assertJsonPath('unread', 1)
        ->assertJsonPath('latest_notification.url', route('tenant.notifications.show', $notificationId))
        ->assertJsonPath('latest_notification.related_url', null);

    $this->actingAs($tenantUser, 'web')
        ->post(route('tenant.notifications.read', $notificationId))
        ->assertOk()
        ->assertJsonPath('notification.url', route('tenant.notifications.show', $notificationId))
        ->assertJsonPath('notification.detail_url', route('tenant.notifications.show', $notificationId))
        ->assertJsonPath('notification.related_url', null)
        ->assertJsonPath('unread', 0);
});
