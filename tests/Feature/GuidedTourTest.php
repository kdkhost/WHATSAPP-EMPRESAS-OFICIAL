<?php

use App\Models\AdminUser;
use App\Models\User;

it('marca o tour administrativo como concluido e permite reiniciar', function () {
    $admin = AdminUser::create([
        'name' => 'Admin Tour',
        'email' => 'admin-tour@example.com',
        'password' => bcrypt('password'),
        'role' => 'admin',
        'active' => true,
    ]);

    $this->actingAs($admin, 'admin')
        ->postJson(route('admin.tour.complete'))
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect($admin->fresh()->tour_completed_at)->not->toBeNull();

    $this->actingAs($admin, 'admin')
        ->postJson(route('admin.tour.reset'))
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect($admin->fresh()->tour_completed_at)->toBeNull();
});

it('marca o tour do tenant como concluido e permite reiniciar', function () {
    $user = User::create([
        'name' => 'Tenant Tour',
        'email' => 'tenant-tour@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user)
        ->postJson(route('tenant.tour.complete'))
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect($user->fresh()->tour_completed_at)->not->toBeNull();

    $this->actingAs($user)
        ->postJson(route('tenant.tour.reset'))
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect($user->fresh()->tour_completed_at)->toBeNull();
});
