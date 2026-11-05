<?php

use App\Http\Middleware\Ensure2FA;
use App\Http\Middleware\NoStoreAdminCache;
use App\Models\AdminUser;
use App\Services\RouteAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    $this->withoutMiddleware(Ensure2FA::class);
});

it('aplica headers no-cache em rotas admin', function () {
    $admin = AdminUser::create([
        'name' => 'Cache Test',
        'email' => 'cache-test@example.com',
        'password' => 'secret',
        'role' => 'superadmin',
        'active' => true,
    ]);

    $response = $this->actingAs($admin, 'admin')
        ->get(route('admin.dashboard'))
        ->assertOk();

    $cacheControl = $response->headers->get('Cache-Control');
    expect($cacheControl)->toContain('no-store');
    expect($cacheControl)->toContain('no-cache');
    expect($cacheControl)->toContain('must-revalidate');
    expect($cacheControl)->toContain('max-age=0');
    expect($response->headers->get('Pragma'))->toBe('no-cache');
    expect($response->headers->get('Expires'))->toBe('Fri, 01 Jan 1990 00:00:00 GMT');
});

it('nao aplica headers no-cache em rotas publicas', function () {
    $this->get(route('home'))
        ->assertOk();

    $response = $this->get(route('home'));
    $cacheControl = $response->headers->get('Cache-Control');
    expect($cacheControl)->not->toContain('no-store');
});

it('middleware NoStoreAdminCache funciona isoladamente', function () {
    $middleware = new NoStoreAdminCache;

    $request = Request::create('/admin/dashboard', 'GET');

    $response = $middleware->handle($request, fn ($req) => new Response('ok'));

    $cacheControl = $response->headers->get('Cache-Control');
    expect($cacheControl)->toContain('no-store');
    expect($cacheControl)->toContain('no-cache');
    expect($cacheControl)->toContain('must-revalidate');
    expect($response->headers->get('Pragma'))->toBe('no-cache');
    expect($response->headers->get('Expires'))->toBe('Fri, 01 Jan 1990 00:00:00 GMT');
});

it('middleware nao interfere em requisicoes nao-admin', function () {
    $middleware = new NoStoreAdminCache;

    $request = Request::create('/', 'GET');

    $response = $middleware->handle($request, fn ($req) => new Response('ok'));

    $cacheControl = $response->headers->get('Cache-Control');
    expect($cacheControl)->not->toContain('no-store');
});

it('auditoria nao acusa export-report como JSON apos correcao para view', function () {
    $report = app(RouteAuditService::class)->run();

    $jsonLinked = collect($report['json_endpoints_linked'] ?? []);
    $matched = $jsonLinked->firstWhere('url', '/admin/whatsapp/export-report');

    expect($matched)->toBeNull();
});

it('renderiza export-report como pagina admin e deixa JSON em rota separada', function () {
    $admin = AdminUser::create([
        'name' => 'Export Report Admin',
        'email' => 'export-report-admin@example.com',
        'password' => 'secret',
        'role' => 'superadmin',
        'active' => true,
    ]);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.whatsapp.export-report'))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
        ->assertSee('admin-premium-hero', false)
        ->assertSee('Relatório anti-ban WhatsApp');

    $this->actingAs($admin, 'admin')
        ->get(route('admin.whatsapp.export-report', ['format' => 'json']))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
        ->assertSee('Relatório anti-ban WhatsApp')
        ->assertDontSee('"generated_at"', false);

    $this->actingAs($admin, 'admin')
        ->getJson(route('admin.whatsapp.export-report.download'))
        ->assertOk()
        ->assertJsonStructure(['generated_at', 'period', 'instances', 'summary']);
});

it('auditoria nao acusa falsos positivos para rotas que retornam view', function () {
    $sidebarDir = resource_path('views/admin/partials');
    $sidebarPath = $sidebarDir.'/sidebar_test_view.php';
    File::ensureDirectoryExists($sidebarDir);
    File::put($sidebarPath, '<a href="{{ url(\'/admin/dashboard\') }}">Dashboard</a>');

    $report = app(RouteAuditService::class)->run();

    $jsonLinked = collect($report['json_endpoints_linked'] ?? []);
    $matched = $jsonLinked->firstWhere('url', '/admin/dashboard');

    expect($matched)->toBeNull();

    File::delete($sidebarPath);
});

it('comando app:audit-routes executa sem erros', function () {
    $this->artisan('app:audit-routes')
        ->expectsOutput('Auditoria de rotas concluida.')
        ->assertExitCode(0);

    expect(File::glob(storage_path('app/audits/routes-audit-*.json')))->not->toBeEmpty();
});

it('comando app:audit-routes --fail-on-error retorna 0 sem criticos', function () {
    $this->artisan('app:audit-routes --fail-on-error')
        ->assertExitCode(0);
});
