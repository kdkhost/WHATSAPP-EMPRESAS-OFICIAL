<?php

use App\Models\AdminUser;

it('permite ao admin desativar o proprio 2fa pelo painel', function () {
    $admin = AdminUser::create([
        'name' => 'Admin 2FA',
        'email' => 'admin-2fa-disable@example.com',
        'password' => 'secret',
        'role' => 'admin',
        'active' => true,
        'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
        'two_factor_recovery_codes' => encrypt(json_encode(['abcde-12345'])),
        'two_factor_enabled' => true,
        'two_factor_confirmed_at' => now(),
    ]);

    $this->actingAs($admin, 'admin')
        ->withSession(['2fa_confirmed' => true])
        ->postJson(route('admin.2fa.disable'), ['password' => 'secret'])
        ->assertOk()
        ->assertJson(['ok' => true]);

    $admin->refresh();

    expect($admin->two_factor_secret)->toBeNull()
        ->and($admin->two_factor_enabled)->toBeFalse()
        ->and($admin->two_factor_confirmed_at)->toBeNull();
});

it('permite ao superadmin desativar 2fa de outro usuario admin', function () {
    $superadmin = AdminUser::create([
        'name' => 'Super 2FA',
        'email' => 'super-2fa-disable@example.com',
        'password' => 'secret',
        'role' => 'superadmin',
        'active' => true,
        'two_factor_enabled' => false,
    ]);

    $target = AdminUser::create([
        'name' => 'Usuario com 2FA',
        'email' => 'target-2fa-disable@example.com',
        'password' => 'secret',
        'role' => 'admin',
        'active' => true,
        'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
        'two_factor_recovery_codes' => encrypt(json_encode(['abcde-12345'])),
        'two_factor_enabled' => true,
        'two_factor_confirmed_at' => now(),
    ]);

    $this->actingAs($superadmin, 'admin')
        ->postJson(route('admin.permissions.users.2fa.disable', $target->id))
        ->assertOk()
        ->assertJson(['ok' => true]);

    $target->refresh();

    expect($target->two_factor_secret)->toBeNull()
        ->and($target->two_factor_enabled)->toBeFalse()
        ->and($target->two_factor_confirmed_at)->toBeNull();
});

it('alterna botoes de 2fa no perfil e salva avatar do admin', function () {
    $inactiveAdmin = AdminUser::create([
        'name' => 'Admin Sem 2FA',
        'email' => 'admin-profile-inactive@example.com',
        'password' => 'secret',
        'role' => 'admin',
        'active' => true,
        'two_factor_enabled' => false,
    ]);

    $this->actingAs($inactiveAdmin, 'admin')
        ->get(route('admin.profile'))
        ->assertOk()
        ->assertSee('btn btn-success btn-sm', false)
        ->assertSee('Ativar 2FA');

    $this->actingAs($inactiveAdmin, 'admin')
        ->postJson(route('admin.profile.update'), [
            'name' => 'Admin Sem 2FA',
            'email' => 'admin-profile-inactive@example.com',
            'phone' => '(21) 99999-1111',
            'avatar_url' => '/storage/uploads/avatars/admin.webp',
        ])
        ->assertOk()
        ->assertJson(['ok' => true]);

    expect($inactiveAdmin->refresh()->avatar_url)->toBe('/storage/uploads/avatars/admin.webp');

    $activeAdmin = AdminUser::create([
        'name' => 'Admin Com 2FA',
        'email' => 'admin-profile-active@example.com',
        'password' => 'secret',
        'role' => 'admin',
        'active' => true,
        'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
        'two_factor_recovery_codes' => encrypt(json_encode(['abcde-12345'])),
        'two_factor_enabled' => true,
        'two_factor_confirmed_at' => now(),
    ]);

    $this->actingAs($activeAdmin, 'admin')
        ->withSession(['2fa_confirmed' => true])
        ->get(route('admin.profile'))
        ->assertOk()
        ->assertSee('btn btn-danger btn-sm', false)
        ->assertSee('Desativar');
});
