<?php

use App\Modules\Settings\Services\SettingsManager;

it('home page returns 200', function () {
    $response = $this->get('/');
    expect($response->status())->toBeIn([200, 302, 503]);
});

it('renderiza snippets do Google Tag Manager e Google tag no HTML publico', function () {
    $settings = app(SettingsManager::class);
    $settings->set('google.gtm_id', 'PP6JQNBF');
    $settings->set('google.ga_id', 'ABC1234567');
    $settings->set('google.gads_id', '987654321');

    $response = $this->get('/');

    $response->assertStatus(200);
    $response->assertSee("})(window,document,'script','dataLayer','GTM-PP6JQNBF');", false);
    $response->assertSee('https://www.googletagmanager.com/ns.html?id=GTM-PP6JQNBF', false);
    $response->assertSee("gtag('config', 'G-ABC1234567');", false);
    $response->assertSee("gtag('config', 'AW-987654321');", false);

    $csp = $response->headers->get('Content-Security-Policy');
    expect($csp)->toContain('https://www.googletagmanager.com');
    expect($csp)->toContain('https://www.google-analytics.com');
    expect($csp)->toContain('https://analytics.google.com');
});

it('robots.txt is served', function () {
    $response = $this->get('/robots.txt');
    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
});

it('sitemap.xml is served', function () {
    $response = $this->get('/sitemap.xml');
    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
});

it('manifest.json is served as JSON', function () {
    $response = $this->get('/manifest.json');
    $response->assertStatus(200);
    expect($response->headers->get('Content-Type'))->toContain('application/json');
});

it('service worker is served as JS', function () {
    $response = $this->get('/sw.js');
    $response->assertStatus(200);
    expect($response->headers->get('Content-Type'))->toContain('javascript');
    $response->assertSee("const CACHE = 'cwa-v3';", false);
    $response->assertSee("url.pathname.startsWith('/build/')", false);
});

it('openapi spec is valid JSON', function () {
    $settings = app(SettingsManager::class);
    $settings->set('app.name', 'Docs Premium');
    $settings->set('app.logo', '/uploads/brand/docs-premium.png');

    $response = $this->get('/api/openapi.json');
    $response->assertStatus(200);
    $data = $response->json();
    expect($data)->toHaveKeys(['openapi', 'info', 'paths', 'components']);
    expect($data['openapi'])->toBe('3.0.3');
    expect($data['info']['title'])->toBe('Docs Premium - API Publica');
    expect($data['info']['x-logo']['url'])->toContain('/uploads/brand/docs-premium.png');
});

it('swagger docs page renders', function () {
    $settings = app(SettingsManager::class);
    $settings->set('app.name', 'Docs Premium');
    $settings->set('app.logo', '/uploads/brand/docs-premium.png');
    $settings->set('theme.primary', '#123456');

    $response = $this->get('/api/docs');
    $response->assertStatus(200);
    $response->assertSee('swagger-ui');
    $response->assertSee('Documentacao da API - Docs Premium');
    $response->assertSee('/uploads/brand/docs-premium.png');
    $response->assertSee('--bs-primary: #123456;', false);
    $response->assertSee('--api-readable-accent:', false);
    $response->assertSee('--api-page-bg: #020617;', false);
    $response->assertSee('color-scheme: dark;', false);
    $response->assertSee('.swagger-ui .opblock .opblock-summary-path', false);
    $response->assertSee('.swagger-ui .info', false);
    $response->assertSee('body .dialog-ux .modal-ux', false);
    $response->assertSee('.swagger-ui .opblock-section-header', false);
});
