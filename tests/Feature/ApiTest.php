<?php

use App\Models\Customer;
use App\Models\User;
use App\Modules\Tenancy\Models\Plan;
use App\Modules\Tenancy\Models\Tenant;

it('rejects API request without bearer token', function () {
    $response = $this->getJson('/api/v1/customers');
    $response->assertStatus(401);
});

it('rejects API request with invalid token', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer invalid-token-here'])
        ->getJson('/api/v1/customers');
    $response->assertStatus(401);
});

it('openapi spec lists all expected endpoints', function () {
    $response = $this->get('/api/openapi.json');
    $paths = $response->json('paths');

    expect($paths)->toHaveKeys([
        '/customers',
        '/invoices',
        '/invoices/{id}/send',
        '/dispatches',
        '/templates',
        '/stats',
    ]);
});

it('openapi spec defines bearer auth security', function () {
    $response = $this->get('/api/openapi.json');
    $schemes = $response->json('components.securitySchemes');
    expect($schemes)->toHaveKey('BearerAuth');
    expect($schemes['BearerAuth']['scheme'])->toBe('bearer');
});

it('permite gerar token e consumir API quando o tenant tem acesso contratado', function () {
    $plan = Plan::create([
        'name' => 'Plano API',
        'slug' => 'plano-api',
        'price' => 199.90,
        'billing_cycle' => 'monthly',
        'trial_days' => 0,
        'max_instances' => 1,
        'max_customers' => 100,
        'max_invoices_month' => 100,
        'max_messages_month' => 100,
        'custom_gateway' => true,
        'custom_templates' => true,
        'installment_enabled' => true,
        'fee_passthrough' => false,
        'features' => ['API publica completa'],
        'active' => true,
        'sort' => 1,
    ]);

    $user = User::create([
        'name' => 'Usuario API',
        'email' => 'usuario-api@example.com',
        'password' => bcrypt('password'),
    ]);
    $tenant = Tenant::create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'company_name' => 'Tenant API',
        'slug' => 'tenant-api',
        'status' => 'active',
        'settings' => ['public_api' => ['enabled' => true, 'monthly_fee' => 49.90]],
    ]);

    Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Cliente API',
        'email' => 'cliente-api@example.com',
        'active' => true,
    ]);

    $plainToken = $this->actingAs($user)
        ->postJson(route('tenant.api.tokens.store'), ['name' => 'N8N'])
        ->assertCreated()
        ->json('token');

    $this->withHeaders(['Authorization' => 'Bearer '.$plainToken])
        ->getJson('/api/v1/customers')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Cliente API');
});

it('bloqueia API para tenant sem acesso comercial habilitado', function () {
    $plan = Plan::create([
        'name' => 'Plano Sem API',
        'slug' => 'plano-sem-api',
        'price' => 49.90,
        'billing_cycle' => 'monthly',
        'trial_days' => 0,
        'max_instances' => 1,
        'max_customers' => 100,
        'max_invoices_month' => 100,
        'max_messages_month' => 100,
        'custom_gateway' => false,
        'custom_templates' => true,
        'installment_enabled' => false,
        'fee_passthrough' => false,
        'features' => ['Dashboard simples'],
        'active' => true,
        'sort' => 1,
    ]);

    $user = User::create([
        'name' => 'Usuario Sem API',
        'email' => 'usuario-sem-api@example.com',
        'password' => bcrypt('password'),
    ]);
    Tenant::create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'company_name' => 'Tenant Sem API',
        'slug' => 'tenant-sem-api',
        'status' => 'active',
        'settings' => ['public_api' => ['enabled' => false]],
    ]);

    $plainToken = $user->createToken('Teste', ['public-api'])->plainTextToken;

    $this->withHeaders(['Authorization' => 'Bearer '.$plainToken])
        ->getJson('/api/v1/customers')
        ->assertStatus(402)
        ->assertJsonPath('upgrade_required', true);
});
