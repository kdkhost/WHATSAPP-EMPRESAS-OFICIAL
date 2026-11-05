<?php

use App\Models\AdminUser;
use App\Models\User;
use App\Modules\EmailBilling\Models\TenantEmailConfig;
use App\Modules\Tenancy\Models\Tenant;

it('permite que o admin configure SMTP pela propria conta tenant', function () {
    $admin = AdminUser::create([
        'name' => 'Administrador',
        'email' => 'admin-smtp@example.com',
        'password' => 'secret',
        'role' => 'superadmin',
        'active' => true,
    ]);

    $this->actingAs($admin, 'admin');

    $this->get('/admin/email/config')
        ->assertStatus(200)
        ->assertSee('Configurar SMTP')
        ->assertSee('/admin/email/config', false)
        ->assertSee('data-upload', false)
        ->assertSee('/admin/uploads/branding', false);

    $user = User::where('email', $admin->email)->first();
    expect($user)->not->toBeNull();
    expect(Tenant::where('user_id', $user->id)->exists())->toBeTrue();
});

it('salva configuracao SMTP do admin no tenant operacional', function () {
    $admin = AdminUser::create([
        'name' => 'Administrador',
        'email' => 'admin-save-smtp@example.com',
        'password' => 'secret',
        'role' => 'superadmin',
        'active' => true,
    ]);

    $this->actingAs($admin, 'admin');

    $response = $this->postJson('/admin/email/config', [
        'enabled' => true,
        'driver' => 'smtp',
        'host' => 'smtp.example.com',
        'port' => 587,
        'encryption' => 'tls',
        'username' => 'cobranca@example.com',
        'password' => 'senha-segura',
        'from_email' => 'cobranca@example.com',
        'from_name' => 'Cobranças Admin',
        'reply_to' => 'suporte@example.com',
    ]);

    $response->assertOk()->assertJson(['ok' => true]);

    $tenant = Tenant::whereHas('user', fn ($q) => $q->where('email', $admin->email))->first();

    expect(TenantEmailConfig::where('tenant_id', $tenant->id)->where('host', 'smtp.example.com')->exists())->toBeTrue();
});

it('renderiza o preview com o branding real do tenant', function () {
    $admin = AdminUser::create([
        'name' => 'Administrador Preview',
        'email' => 'admin-preview@example.com',
        'password' => 'secret',
        'role' => 'superadmin',
        'active' => true,
    ]);

    $this->actingAs($admin, 'admin');
    $this->get('/admin/email/config')->assertOk();

    $tenant = Tenant::whereHas('user', fn ($q) => $q->where('email', $admin->email))->firstOrFail();

    TenantEmailConfig::updateOrCreate(
        ['tenant_id' => $tenant->id],
        [
            'enabled' => true,
            'driver' => 'smtp',
            'host' => 'smtp.example.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'cobranca@example.com',
            'password' => 'senha-segura',
            'from_email' => 'cobranca@example.com',
            'from_name' => 'Marca Tenant',
            'settings' => [
                'brand_name' => 'Marca Tenant',
                'logo_url' => 'https://cdn.exemplo.com/logo-tenant.png',
                'primary_color' => '#123456',
                'accent_color' => '#654321',
                'footer_signature' => 'Marca Tenant',
            ],
        ]
    );

    $response = $this->postJson('/admin/email/templates/preview', [
        'subject' => 'Olá, {nome}',
        'body_html' => '<h1>Bem-vindo</h1><p>Pagamento em {vencimento}.</p>',
    ]);

    $response->assertOk()
        ->assertJsonPath('subject', 'Olá, Joao da Silva');

    $html = $response->json('body_html');
    expect($html)->toContain('Marca Tenant');
    expect($html)->toContain('https://cdn.exemplo.com/logo-tenant.png');
    expect($html)->toContain('Bem-vindo');
    expect($html)->toContain('Pagamento em');
    expect($html)->toContain('<!doctype html>');
    expect($html)->toContain('<td style="vertical-align:middle;padding:0;"><div style="font-size:22px;font-weight:800;line-height:1.2;color:#ffffff;');
    expect($html)->toContain('>Marca Tenant</div><div style="font-size:12px;line-height:1.5;color:rgba(255,255,255,.78);margin-top:3px;">Mensagem oficial</div>');
    expect($html)->toContain('min-height:56px;');
    expect($html)->not->toContain('rgba(255,255,255,.92)');
});

it('formata preview com corpo textual solto em paragrafos legiveis', function () {
    $admin = AdminUser::create([
        'name' => 'Administrador Texto',
        'email' => 'admin-preview-texto@example.com',
        'password' => 'secret',
        'role' => 'superadmin',
        'active' => true,
    ]);

    $this->actingAs($admin, 'admin');
    $this->get('/admin/email/config')->assertOk();

    $response = $this->postJson('/admin/email/templates/preview', [
        'subject' => 'Pagamento estornado: fatura {numero_fatura}',
        'body_html' => 'Estorno registradoSeu pagamento foi estornadoFatura{numero_fatura}Valor{valor}Transacao{id_transacao}Metodo{metodo_pagamento}Observacao: O prazo pode variar.',
    ]);

    $response->assertOk()
        ->assertJsonPath('subject', 'Pagamento estornado: fatura INV-001234');

    $html = $response->json('body_html');
    expect($html)->toContain('<p style="margin:0 0 16px;');
    expect($html)->toContain('Fatura');
    expect($html)->toContain('Valor');
    expect($html)->toContain('Observacao');
});

it('renderiza qr code pix e parcelas no preview de email', function () {
    $admin = AdminUser::create([
        'name' => 'Administrador QR',
        'email' => 'admin-preview-qr@example.com',
        'password' => 'secret',
        'role' => 'superadmin',
        'active' => true,
    ]);

    $this->actingAs($admin, 'admin');
    $this->get('/admin/email/config')->assertOk();

    $response = $this->postJson('/admin/email/templates/preview', [
        'subject' => 'Fatura {numero_fatura} - {servico_principal}',
        'body_html' => '<p>{resumo_parcelas}</p>{qrcode_pix_html}',
    ]);

    $response->assertOk()
        ->assertJsonPath('subject', 'Fatura INV-001234 - Plano Pro');

    $html = $response->json('body_html');
    expect($html)->toContain('Pagamento por QR Code');
    expect($html)->toContain('api.qrserver.com');
    expect($html)->toContain('Pix copia e cola');
    expect($html)->toContain('Parcela 2/6');
});

it('permite que o tenant salve configuracao de email mesmo sem enviar o campo driver explicitamente', function () {
    $plan = \App\Modules\Tenancy\Models\Plan::firstOrCreate(
        ['slug' => 'plano-teste'],
        [
            'name' => 'Plano Teste',
            'price' => 10,
            'billing_cycle' => 'monthly',
            'active' => true,
        ]
    );

    $user = User::create([
        'name' => 'Tenant Email Test',
        'email' => 'tenant-email@example.com',
        'password' => 'secret',
    ]);

    $tenant = Tenant::create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'company_name' => 'Empresa Tenant',
        'slug' => 'empresa-tenant-email',
        'status' => 'active',
    ]);

    $this->actingAs($user);

    // Envia POST sem 'driver' (simulando radio nao marcado no formulário)
    $response = $this->postJson('/painel/email/config', [
        'enabled' => true,
        'host' => 'smtp.tenant.com',
        'port' => 587,
        'encryption' => 'tls',
        'username' => 'contato@tenant.com',
        'password' => 'senha123',
        'from_email' => 'contato@tenant.com',
        'from_name' => 'Empresa Tenant',
    ]);

    $response->assertOk()->assertJson(['ok' => true]);

    $config = TenantEmailConfig::where('tenant_id', $tenant->id)->first();
    expect($config)->not->toBeNull();
    expect($config->driver)->toBe('smtp');
    expect($config->host)->toBe('smtp.tenant.com');
    expect($config->username)->toBe('contato@tenant.com');
});

it('renderiza a pagina de email do tenant com radio smtp marcado por padrao', function () {
    $plan = \App\Modules\Tenancy\Models\Plan::firstOrCreate(
        ['slug' => 'plano-teste-view'],
        [
            'name' => 'Plano Teste View',
            'price' => 10,
            'billing_cycle' => 'monthly',
            'active' => true,
        ]
    );

    $user = User::create([
        'name' => 'Tenant View Test',
        'email' => 'tenant-view@example.com',
        'password' => 'secret',
    ]);

    $tenant = Tenant::create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'company_name' => 'Empresa View',
        'slug' => 'empresa-view-email',
        'status' => 'active',
    ]);

    $this->actingAs($user);

    $response = $this->get('/painel/email/config');
    $response->assertOk();
    $response->assertSee('id="drv-smtp" value="smtp" checked', false);
});

