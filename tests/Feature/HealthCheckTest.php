<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('health check retorna 200', function () {
    Http::fake([
        'https://graph.facebook.com/*' => Http::response([
            'id' => '109876543210',
            'display_phone_number' => '+55 21 99999-8888',
            'verified_name' => 'Conta Teste',
            'quality_rating' => 'GREEN',
        ], 200),
    ]);

    // Cria installed.lock para permitir acesso
    file_put_contents(storage_path('installed.lock'), '{}');

    $response = $this->get('/health');
    $response->assertStatus(200);
    $response->assertJsonStructure(['status', 'timestamp', 'checks']);

    @unlink(storage_path('installed.lock'));
});

test('endpoint /up retorna 200', function () {
    file_put_contents(storage_path('installed.lock'), '{}');
    $this->get('/up')->assertStatus(200);
    @unlink(storage_path('installed.lock'));
});
