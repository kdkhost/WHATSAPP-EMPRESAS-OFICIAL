<?php

namespace App\Services;

use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

class RouteAuditService
{
    public function run(): array
    {
        $report = [
            'generated_at' => now()->toIso8601String(),
            'summary' => [
                'routes' => 0,
                'critical' => 0,
                'warnings' => 0,
            ],
            'controllers_missing' => [],
            'methods_missing' => [],
            'middleware_missing' => [],
            'views_missing' => [],
            'named_routes_missing' => [],
            'addon_menus_invalid' => [],
            'sidebar_links_invalid' => [],
            'json_endpoints_linked' => [],
            'warnings' => [],
            'report_path' => null,
        ];

        foreach (Route::getRoutes() as $route) {
            $report['summary']['routes']++;
            $this->auditController($route, $report);
            $this->auditMiddleware($route, $report);
        }

        $this->auditAddonMenus($report);
        $this->auditSidebarRouteCalls($report);
        $this->auditSidebarUrlCalls($report);

        $report['summary']['critical'] =
            count($report['controllers_missing'])
            + count($report['methods_missing'])
            + count($report['middleware_missing'])
            + count($report['views_missing'])
            + count($report['named_routes_missing'])
            + count($report['addon_menus_invalid'])
            + count($report['sidebar_links_invalid'])
            + count($report['json_endpoints_linked']);

        $report['report_path'] = $this->persist($report);

        return $report;
    }

    protected function auditController(IlluminateRoute $route, array &$report): void
    {
        $action = $route->getActionName();

        if ($action === 'Closure') {
            return;
        }

        if ($action === 'Illuminate\Routing\ViewController') {
            $view = $route->getAction('view') ?: $route->defaults['view'] ?? null;

            if ($view && ! View::exists($view)) {
                $report['views_missing'][] = $this->routeIssue($route, [
                    'view' => $view,
                    'error' => 'view_missing',
                ]);
            }

            return;
        }

        if (str_contains($action, '@')) {
            [$controller, $method] = explode('@', $action, 2);

            if (! class_exists($controller)) {
                $report['controllers_missing'][] = $this->routeIssue($route, [
                    'controller' => $controller,
                ]);

                return;
            }

            if (! method_exists($controller, $method)) {
                $report['methods_missing'][] = $this->routeIssue($route, [
                    'controller' => $controller,
                    'method' => $method,
                ]);
            }

            return;
        }

        if (! class_exists($action)) {
            $report['controllers_missing'][] = $this->routeIssue($route, [
                'controller' => $action,
            ]);

            return;
        }

        if (! method_exists($action, '__invoke')) {
            $report['methods_missing'][] = $this->routeIssue($route, [
                'controller' => $action,
                'method' => '__invoke',
            ]);
        }
    }

    protected function auditMiddleware(IlluminateRoute $route, array &$report): void
    {
        $router = app('router');
        $aliases = array_keys($router->getMiddleware());
        $groups = array_keys($router->getMiddlewareGroups());

        foreach ($route->middleware() as $middleware) {
            $name = Str::before((string) $middleware, ':');

            if ($name === '' || in_array($name, $aliases, true) || in_array($name, $groups, true) || class_exists($name)) {
                continue;
            }

            $report['middleware_missing'][] = $this->routeIssue($route, [
                'middleware' => $middleware,
            ]);
        }
    }

    protected function auditAddonMenus(array &$report): void
    {
        try {
            if (! Schema::hasTable('addon_menus')) {
                return;
            }

            $columns = Schema::getColumnListing('addon_menus');
            $routeColumn = collect(['route', 'route_name'])->first(fn ($column) => in_array($column, $columns, true));
            $urlColumn = collect(['url', 'href', 'path'])->first(fn ($column) => in_array($column, $columns, true));

            if (! $routeColumn && ! $urlColumn) {
                return;
            }

            $query = DB::table('addon_menus')->select('addon_menus.*');
            if (Schema::hasTable('registered_modules')) {
                $query->leftJoin('registered_modules', 'registered_modules.slug', '=', 'addon_menus.module_slug')
                    ->where(function ($query) {
                        $query->whereNull('registered_modules.enabled')
                            ->orWhere('registered_modules.enabled', true);
                    })
                    ->where(function ($query) {
                        $query->whereNull('registered_modules.installed')
                            ->orWhere('registered_modules.installed', true);
                    });
            }

            $query->where('addon_menus.active', true)->orderBy('addon_menus.id')->chunk(200, function ($menus) use (&$report, $routeColumn, $urlColumn) {
                foreach ($menus as $menu) {
                    $routeName = $routeColumn ? (string) ($menu->{$routeColumn} ?? '') : '';
                    $url = $urlColumn ? (string) ($menu->{$urlColumn} ?? '') : '';

                    if ($routeName !== '' && ! Route::has($routeName)) {
                        $report['addon_menus_invalid'][] = [
                            'id' => $menu->id ?? null,
                            'label' => $menu->label ?? $menu->name ?? null,
                            'route' => $routeName,
                            'error' => 'named_route_missing',
                        ];
                    }

                    if ($url !== '' && str_starts_with($url, 'admin/') && ! $this->localUriExists($url)) {
                        $report['addon_menus_invalid'][] = [
                            'id' => $menu->id ?? null,
                            'label' => $menu->label ?? $menu->name ?? null,
                            'url' => $url,
                            'error' => 'local_uri_missing',
                        ];
                    }
                }
            });
        } catch (\Throwable $e) {
            $report['summary']['warnings']++;
            $report['warnings'][] = [
                'scope' => 'addon_menus',
                'error' => 'database_unavailable',
                'message' => $e->getMessage(),
            ];
        }
    }

    protected function auditSidebarRouteCalls(array &$report): void
    {
        $files = $this->sidebarFiles();

        foreach ($files as $file) {
            if (! File::exists($file)) {
                continue;
            }

            preg_match_all("/route\\(['\\\"]([^'\\\"]+)['\\\"]/", File::get($file), $matches);

            foreach (array_unique($matches[1] ?? []) as $routeName) {
                if (! Route::has($routeName)) {
                    $report['sidebar_links_invalid'][] = [
                        'file' => $this->shortPath($file),
                        'route' => $routeName,
                        'error' => 'named_route_missing',
                    ];
                }
            }
        }
    }

    protected function auditSidebarUrlCalls(array &$report): void
    {
        $files = $this->sidebarFiles();

        foreach ($files as $file) {
            if (! File::exists($file)) {
                continue;
            }

            preg_match_all("/url\(['\\\"]([^'\\\"]+)['\\\"]/", File::get($file), $matches);

            foreach (array_unique($matches[1] ?? []) as $url) {
                $route = $this->findRouteByUri($url);

                if ($route === null) {
                    continue;
                }

                if ($this->isJsonOnlyRoute($route)) {
                    $report['json_endpoints_linked'][] = [
                        'file' => $this->shortPath($file),
                        'url' => $url,
                        'route_name' => $route->getName(),
                        'uri' => $route->uri(),
                        'action' => $route->getActionName(),
                    ];
                }
            }
        }
    }

    protected function isJsonOnlyRoute(IlluminateRoute $route): bool
    {
        if (in_array('api', $route->gatherMiddleware(), true)) {
            return true;
        }

        $action = $route->getActionName();

        if ($action === 'Closure' || $action === 'Illuminate\Routing\ViewController') {
            return false;
        }

        if (! str_contains($action, '@')) {
            return false;
        }

        [$controller, $method] = explode('@', $action, 2);

        if (! class_exists($controller) || ! method_exists($controller, $method)) {
            return false;
        }

        try {
            $refMethod = new \ReflectionMethod($controller, $method);
            $returnType = $refMethod->getReturnType();

            if ($returnType) {
                if ($returnType instanceof \ReflectionNamedType) {
                    if ($returnType->getName() === 'Illuminate\Http\JsonResponse') {
                        return true;
                    }
                } elseif ($returnType instanceof \ReflectionUnionType) {
                    foreach ($returnType->getTypes() as $type) {
                        if ($type->getName() === 'Illuminate\Http\JsonResponse') {
                            return false;
                        }
                    }
                }
            }

            $phpDoc = $refMethod->getDocComment();
            if ($phpDoc !== false && preg_match('/@return\s+.*\\\\JsonResponse/', $phpDoc)) {
                return true;
            }
        } catch (\ReflectionException) {
            return false;
        }

        return false;
    }

    protected function findRouteByUri(string $uri): ?IlluminateRoute
    {
        $uri = trim($uri, '/');

        foreach (Route::getRoutes() as $route) {
            if (trim($route->uri(), '/') === $uri) {
                return $route;
            }
        }

        return null;
    }

    protected function sidebarFiles(): array
    {
        return array_filter([
            resource_path('views/admin/partials/sidebar.blade.php'),
            resource_path('views/admin/layouts/sidebar.blade.php'),
            resource_path('views/tenant/partials/sidebar.blade.php'),
            resource_path('views/tenant/layouts/sidebar.blade.php'),
            resource_path('views/painel/partials/sidebar.blade.php'),
            resource_path('views/painel/layouts/sidebar.blade.php'),
        ], fn (string $path) => File::exists($path));
    }

    protected function shortPath(string $path): string
    {
        $base = resource_path('views');

        return str_starts_with($path, $base)
            ? 'resources/views/'.str_replace($base, '', $path)
            : $path;
    }

    protected function routeIssue(IlluminateRoute $route, array $extra): array
    {
        return array_merge([
            'name' => $route->getName(),
            'uri' => $route->uri(),
            'methods' => $route->methods(),
            'action' => $route->getActionName(),
        ], $extra);
    }

    protected function localUriExists(string $uri): bool
    {
        $uri = trim($uri, '/');

        foreach (Route::getRoutes() as $route) {
            if (trim($route->uri(), '/') === $uri) {
                return true;
            }
        }

        return false;
    }

    protected function persist(array $report): string
    {
        $directory = storage_path('app/audits');
        File::ensureDirectoryExists($directory);

        $timestamp = now()->format('Ymd-His');
        $jsonPath = $directory.'/routes-audit-'.$timestamp.'.json';
        File::put($jsonPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $mdPath = $directory.'/routes-audit-'.$timestamp.'.md';
        $md = $this->toMarkdown($report);
        File::put($mdPath, $md);

        return $jsonPath;
    }

    protected function toMarkdown(array $report): string
    {
        $lines = [];
        $lines[] = '# Relatório de Auditoria de Rotas';
        $lines[] = '';
        $lines[] = '**Gerado em:** '.$report['generated_at'];
        $lines[] = '';
        $lines[] = '## Resumo';
        $lines[] = '';
        $lines[] = '| Métrica | Valor |';
        $lines[] = '|---------|-------|';
        $lines[] = '| Rotas verificadas | '.$report['summary']['routes'].' |';
        $lines[] = '| Achados críticos | '.$report['summary']['critical'].' |';
        $lines[] = '| Avisos | '.$report['summary']['warnings'].' |';
        $lines[] = '';

        $sections = [
            'controllers_missing' => 'Controllers Ausentes',
            'methods_missing' => 'Métodos Ausentes',
            'middleware_missing' => 'Middlewares Ausentes',
            'views_missing' => 'Views Ausentes',
            'named_routes_missing' => 'Rotas Nomeadas Ausentes',
            'addon_menus_invalid' => 'Menus de Addons Inválidos',
            'sidebar_links_invalid' => 'Links da Sidebar Inválidos',
            'json_endpoints_linked' => 'Endpoints JSON Vinculados na Sidebar',
        ];

        foreach ($sections as $key => $label) {
            $items = $report[$key] ?? [];
            if ($items === []) {
                continue;
            }
            $lines[] = '## '.$label;
            $lines[] = '';
            $lines[] = 'Total: '.count($items);
            $lines[] = '';
            $lines[] = '| Rota/Item | Erro |';
            $lines[] = '|-----------|------|';
            foreach ($items as $item) {
                $name = $item['route'] ?? $item['uri'] ?? $item['url'] ?? $item['controller'] ?? '-';
                $error = $item['error'] ?? $item['middleware'] ?? $item['method'] ?? 'verificar';
                $lines[] = '| '.$name.' | '.$error.' |';
            }
            $lines[] = '';
        }

        if ($report['warnings'] ?? []) {
            $lines[] = '## Avisos';
            $lines[] = '';
            foreach ($report['warnings'] as $warn) {
                $lines[] = '- **'.$warn['scope'].'**: '.($warn['message'] ?? $warn['error']);
            }
            $lines[] = '';
        }

        $lines[] = '---';
        $lines[] = 'Relatório gerado automaticamente por RouteAuditService.';

        return implode("\n", $lines);
    }
}
