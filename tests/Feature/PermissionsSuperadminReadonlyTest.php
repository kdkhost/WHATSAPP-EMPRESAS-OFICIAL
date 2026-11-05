<?php

use App\Http\Middleware\Ensure2FA;
use App\Models\AdminUser;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Permissions\Models\Role;

it('retorna superadmin como readonly com todas as permissoes marcadas', function () {
    $this->withoutMiddleware(Ensure2FA::class);

    $actor = AdminUser::create([
        'name' => 'Owner',
        'email' => 'owner-permissions@example.com',
        'password' => 'secret',
        'role' => 'superadmin',
        'active' => true,
    ]);

    $role = Role::firstOrCreate(
        ['slug' => 'superadmin'],
        ['name' => 'Super Administrador', 'description' => 'Sistema', 'is_system' => true, 'level' => 100]
    );

    Permission::firstOrCreate(['slug' => 'billing.view'], ['module' => 'billing', 'action' => 'view', 'label' => 'Billing view']);
    Permission::firstOrCreate(['slug' => 'billing.edit'], ['module' => 'billing', 'action' => 'edit', 'label' => 'Billing edit']);

    $response = $this->actingAs($actor, 'admin')
        ->getJson(route('admin.permissions.roles.edit', $role->id))
        ->assertOk()
        ->assertJsonPath('readonly', true);

    $permissions = $response->json('role_permissions');

    expect($permissions)->toContain('billing.view')
        ->and($permissions)->toContain('billing.edit');
});

it('nao falha ao sincronizar permissoes da role superadmin', function () {
    $this->withoutMiddleware(Ensure2FA::class);

    $actor = AdminUser::create([
        'name' => 'Owner Sync',
        'email' => 'owner-sync@example.com',
        'password' => 'secret',
        'role' => 'superadmin',
        'active' => true,
    ]);

    $role = Role::firstOrCreate(
        ['slug' => 'superadmin'],
        ['name' => 'Super Administrador', 'description' => 'Sistema', 'is_system' => true, 'level' => 100]
    );

    Permission::firstOrCreate(['slug' => 'crm.view'], ['module' => 'crm', 'action' => 'view', 'label' => 'Crm view']);

    $this->actingAs($actor, 'admin')
        ->postJson(route('admin.permissions.roles.sync', $role->id), ['permissions' => ['crm.view']])
        ->assertOk()
        ->assertJsonPath('readonly', true)
        ->assertJsonPath('message', 'Superadmin tem acesso total automaticamente.');
});

it('nao permite alterar roles de usuario superadmin e responde sem erro', function () {
    $this->withoutMiddleware(Ensure2FA::class);

    $actor = AdminUser::create([
        'name' => 'Owner Users',
        'email' => 'owner-users@example.com',
        'password' => 'secret',
        'role' => 'superadmin',
        'active' => true,
    ]);

    $target = AdminUser::create([
        'name' => 'Marcelo Brad',
        'email' => 'marcelo-readonly@example.com',
        'password' => 'secret',
        'role' => 'superadmin',
        'active' => true,
    ]);

    $adminRole = Role::firstOrCreate(
        ['slug' => 'admin'],
        ['name' => 'Administrador', 'description' => 'Sistema', 'is_system' => true, 'level' => 50]
    );

    $superRole = Role::firstOrCreate(
        ['slug' => 'superadmin'],
        ['name' => 'Super Administrador', 'description' => 'Sistema', 'is_system' => true, 'level' => 100]
    );

    $target->roles()->syncWithoutDetaching([$superRole->id]);

    $this->actingAs($actor, 'admin')
        ->postJson(route('admin.permissions.users.roles.sync', $target->id), ['roles' => [$adminRole->id]])
        ->assertOk()
        ->assertJsonPath('readonly', true)
        ->assertJsonPath('message', 'Superadmin mantem acesso total automaticamente.');

    expect($target->fresh()->isSuperAdmin())->toBeTrue();
});
