<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('health check retorna 200', function () {
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
