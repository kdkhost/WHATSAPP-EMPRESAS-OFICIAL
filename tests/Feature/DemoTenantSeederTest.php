<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Modules\Templates\Models\WhatsAppTemplate;
use App\Modules\Tenancy\Models\Tenant;
use Database\Seeders\DemoTenantSeeder;

it('popula cliente demo com tenant e dados operacionais', function () {
    $this->seed(DemoTenantSeeder::class);

    $user = User::where('email', DemoTenantSeeder::EMAIL)->first();
    expect($user)->not->toBeNull();

    $tenant = Tenant::where('user_id', $user->id)->first();
    expect($tenant)->not->toBeNull()
        ->and($tenant->status)->toBe('active')
        ->and($tenant->instances()->count())->toBeGreaterThan(0)
        ->and($tenant->gateways()->count())->toBeGreaterThan(0)
        ->and($tenant->billingConfig()->exists())->toBeTrue();

    expect(Customer::where('tenant_id', $tenant->id)->count())->toBeGreaterThanOrEqual(3);
    expect(Invoice::where('tenant_id', $tenant->id)->count())->toBeGreaterThanOrEqual(3);
    expect(WhatsAppTemplate::where('tenant_id', $tenant->id)->count())->toBeGreaterThanOrEqual(3);

    $this->actingAs($user, 'web')
        ->get(route('tenant.dashboard'))
        ->assertOk();
});
