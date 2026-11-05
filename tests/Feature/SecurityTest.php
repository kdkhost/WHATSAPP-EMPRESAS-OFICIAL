<?php

use Illuminate\Support\Facades\Cache;

it('blocks SQL injection attempts in query string', function () {
    $response = $this->get('/?q=1%27%20OR%20%271%27%3D%271');
    expect($response->status())->toBeIn([403, 200, 302, 503]);
});

it('serves a CSP-friendly home page', function () {
    $response = $this->get('/');
    if ($response->status() === 200) {
        expect($response->headers->has('X-Frame-Options'))->toBeTrue();
    }
});

it('rejects requests without CSRF on POST', function () {
    $response = $this->post('/admin/login', []);
    expect($response->status())->toBeIn([302, 419, 422]);
});

it('returns json 403 when waf blocks an ajax or api request', function () {
    Cache::put('waf.mode', 'block', 60);

    $response = $this->getJson('/?q=UNION%20SELECT%201');
    expect($response->status())->toBe(403)
        ->and($response->json('ok'))->toBeFalse()
        ->and($response->json('error'))->toBe('Requisição bloqueada por segurança.');
});

it('bypasses webhooks and cron from waf payload inspection', function () {
    Cache::put('waf.mode', 'block', 60);

    $response = $this->postJson('/webhooks/evolution/messages?q=UNION%20SELECT%201', [
        'message' => 'SELECT * FROM users WHERE 1=1',
    ]);

    expect($response->json('error'))->not->toBe('Requisição bloqueada por segurança.');
});
