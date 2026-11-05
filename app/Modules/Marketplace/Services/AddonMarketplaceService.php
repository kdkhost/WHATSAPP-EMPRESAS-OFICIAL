<?php

namespace App\Modules\Marketplace\Services;

use App\Modules\Marketplace\Models\AddonSubmission;
use App\Modules\Marketplace\Models\MarketplaceAddon;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantAddon;
use App\Modules\Tenancy\Models\TenantInvoice;
use App\Modules\Tenancy\Services\TenantBillingService;
use App\Services\AddonManagerService;
use App\Services\AddonSecurityService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class AddonMarketplaceService
{
    public function __construct(
        protected AddonSecurityService $security,
        protected AddonManagerService $addons,
    ) {}

    public function catalog(): Collection
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('marketplace_addons')) {
            return collect();
        }

        $query = MarketplaceAddon::query();

        if (\Illuminate\Support\Facades\Schema::hasColumn('marketplace_addons', 'is_active')) {
            $query->where('is_active', true);
        } elseif (\Illuminate\Support\Facades\Schema::hasColumn('marketplace_addons', 'active')) {
            $query->where('active', 1);
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('marketplace_addons', 'source')) {
            $query->where(function ($q) {
                // Addons nativos do sistema OU addons de terceiros validados e aprovados pelo SuperAdmin
                $q->where('source', 'system');
                if (\Illuminate\Support\Facades\Schema::hasColumn('marketplace_addons', 'approved_by')) {
                    $q->orWhere(function ($sub) {
                        $sub->whereNotNull('approved_by');
                        if (\Illuminate\Support\Facades\Schema::hasColumn('marketplace_addons', 'is_installed')) {
                            $sub->where('is_installed', true);
                        }
                    });
                }
            });
        }

        return $query->orderBy('category')
            ->orderBy('name')
            ->get();
    }

    public function adminCatalog(): Collection
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('marketplace_addons')) {
            return collect();
        }

        return MarketplaceAddon::query()
            ->orderBy('category')
            ->orderBy('name')
            ->get();
    }

    public function tenantCatalog(Tenant $tenant): Collection
    {
        $active = $tenant->addons()->pluck('status', 'marketplace_addon_id');

        return $this->catalog()->map(function (MarketplaceAddon $addon) use ($active) {
            $addon->tenant_status = $active[$addon->id] ?? null;

            return $addon;
        });
    }

    public function recentSubmissions(): Collection
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('addon_submissions')) {
            return collect();
        }

        return AddonSubmission::query()->orderByDesc('id')->limit(50)->get();
    }

    public function submitZip(?Tenant $tenant, int $userId, UploadedFile $file): AddonSubmission
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        if ($extension !== 'zip') {
            throw new \RuntimeException('Envie um pacote ZIP valido do addon.');
        }

        $uuid = (string) Str::uuid();
        $baseDir = storage_path('app/addon-submissions/'.$uuid);
        File::ensureDirectoryExists($baseDir);

        $zipPath = $baseDir.'/package.zip';
        $file->move($baseDir, 'package.zip');

        [$manifest, $extractRoot, $securityReport] = $this->inspectZip($zipPath, $baseDir);

        return AddonSubmission::query()->create([
            'tenant_id' => $tenant?->id,
            'user_id' => $userId,
            'name' => (string) ($manifest['name'] ?? ''),
            'slug' => (string) ($manifest['slug'] ?? ''),
            'version' => (string) ($manifest['version'] ?? ''),
            'zip_path' => $zipPath,
            'extract_path' => $extractRoot,
            'status' => 'pending',
            'manifest' => $manifest,
            'security_report' => $securityReport,
        ]);
    }

    public function approve(AddonSubmission $submission, int $adminId): MarketplaceAddon
    {
        if ($submission->status === 'approved') {
            return MarketplaceAddon::query()->where('submission_id', $submission->id)->firstOrFail();
        }

        $extractRoot = (string) $submission->extract_path;
        if ($extractRoot === '' || ! is_dir($extractRoot)) {
            throw new \RuntimeException('Pacote extraido nao encontrado para aprovacao.');
        }

        $validation = $this->security->validateInstall($extractRoot, true);
        if (! ($validation['ok'] ?? false)) {
            $submission->update([
                'status' => 'rejected',
                'reviewed_by' => $adminId,
                'reviewed_at' => now(),
                'review_notes' => $validation['error'] ?? 'Falha na validacao de seguranca.',
                'security_report' => $validation,
            ]);

            throw new \RuntimeException($validation['error'] ?? 'Falha na validacao do addon.');
        }

        $manifest = $submission->manifest ?? [];
        $slug = Str::slug((string) ($manifest['slug'] ?? $submission->slug));
        if ($slug === '') {
            throw new \RuntimeException('Manifesto sem slug valido.');
        }

        $targetDir = app_path('Addons/'.Str::snake(str_replace('-', '_', $slug)));
        if (is_dir($targetDir)) {
            throw new \RuntimeException('Ja existe um addon instalado com este slug.');
        }

        File::ensureDirectoryExists(dirname($targetDir));
        if (! File::moveDirectory($extractRoot, $targetDir, true)) {
            throw new \RuntimeException('Nao foi possivel mover o addon aprovado para o diretorio do sistema.');
        }

        $installResult = $this->addons->install($slug);
        if (! ($installResult['ok'] ?? false)) {
            throw new \RuntimeException($installResult['message'] ?? 'Falha ao instalar addon aprovado.');
        }

        $addon = MarketplaceAddon::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'name' => (string) ($manifest['name'] ?? Str::headline($slug)),
                'version' => (string) ($manifest['version'] ?? '1.0.0'),
                'author' => (string) ($manifest['author'] ?? 'Marketplace'),
                'category' => (string) data_get($manifest, 'marketplace.category', $manifest['category'] ?? 'Addons'),
                'icon' => (string) data_get($manifest, 'menu.icon', 'bi-puzzle'),
                'color' => (string) data_get($manifest, 'marketplace.color', 'primary'),
                'price' => (float) data_get($manifest, 'marketplace.price', 0),
                'billing_cycle' => (string) data_get($manifest, 'marketplace.billing_cycle', 'monthly'),
                'commercial' => (bool) data_get($manifest, 'marketplace.commercial', true),
                'is_active' => true,
                'is_installed' => true,
                'source' => 'submission',
                'submission_id' => $submission->id,
                'approved_by' => $adminId,
                'approved_at' => now(),
                'package_path' => $submission->zip_path,
                'description' => (string) ($manifest['description'] ?? ''),
                'manifest' => $manifest,
            ]
        );

        $submission->update([
            'marketplace_addon_id' => $addon->id,
            'installed_path' => $targetDir,
            'status' => 'approved',
            'reviewed_by' => $adminId,
            'reviewed_at' => now(),
            'review_notes' => 'Addon aprovado e instalado no sistema.',
        ]);

        $this->addons->clearSystemCache();

        return $addon;
    }

    public function reject(AddonSubmission $submission, int $adminId, string $reason): void
    {
        $submission->update([
            'status' => 'rejected',
            'reviewed_by' => $adminId,
            'reviewed_at' => now(),
            'review_notes' => trim($reason) !== '' ? $reason : 'Submissao rejeitada pelo administrador.',
        ]);
    }

    public function subscribeTenant(Tenant $tenant, MarketplaceAddon $addon, TenantBillingService $billing, ?string $billingMode = null): TenantAddon
    {
        if (! $addon->is_active || ! $addon->is_installed) {
            throw new \RuntimeException('Este addon ainda não está pronto para uso comercial.');
        }

        // Validação estrita: Addons de terceiros PRECISAM de validação e aprovação prévia do SuperAdmin
        if ($addon->source !== 'system' && (empty($addon->approved_by) || empty($addon->approved_at))) {
            throw new \RuntimeException('Este addon enviado por terceiros aguarda validação e aprovação do SuperAdmin antes de ser liberado.');
        }

        return DB::transaction(function () use ($tenant, $addon, $billing, $billingMode) {
            $tenantAddon = TenantAddon::query()->firstOrNew([
                'tenant_id' => $tenant->id,
                'marketplace_addon_id' => $addon->id,
            ]);

            if (in_array($tenantAddon->status, ['active', 'pending'], true)) {
                return $tenantAddon;
            }

            $tenantAddon->fill([
                'billing_cycle' => $addon->billing_cycle,
                'recurring_amount' => $addon->price,
            ]);

            if ((float) $addon->price <= 0) {
                $tenantAddon->status = 'active';
                $tenantAddon->activated_at = now();
                $tenantAddon->save();

                return $tenantAddon;
            }

            $invoice = $this->attachAddonCharge($tenant, $addon, $billing, $billingMode);

            $tenantAddon->status = 'pending';
            $tenantAddon->tenant_invoice_id = $invoice?->id;
            $tenantAddon->save();

            try {
                app(\App\Modules\Notifications\Services\NotificationDispatcher::class)->send([
                    'channels'  => ['in_app', 'email'],
                    'group'     => 'subscription',
                    'type'      => 'info',
                    'title'     => 'Nova Assinatura de Addon',
                    'message'   => "O tenant {$tenant->name} assinou o addon {$addon->name}.",
                    'icon'      => 'bi-stars',
                    'color'     => 'primary',
                    'url'       => route('admin.addons.show', $addon->slug),
                    'recipients'=> ['all_admins' => true],
                ]);
            } catch (\Throwable) {}

            return $tenantAddon;
        });
    }

    public function syncTenantAddonPayment(TenantInvoice $invoice): void
    {
        $lineItems = collect(data_get($invoice->meta ?? [], 'line_items', []));

        $lineItems
            ->where('type', 'addon_subscription')
            ->each(function (array $line) use ($invoice) {
                $addonId = (int) ($line['addon_id'] ?? 0);
                if ($addonId < 1) {
                    return;
                }

                $tenantAddon = TenantAddon::query()->firstOrNew([
                    'tenant_id' => $invoice->tenant_id,
                    'marketplace_addon_id' => $addonId,
                ]);

                $tenantAddon->fill([
                    'tenant_invoice_id' => $invoice->id,
                    'billing_cycle' => (string) ($line['billing_cycle'] ?? 'monthly'),
                    'recurring_amount' => (float) ($line['amount'] ?? 0),
                    'status' => 'active',
                    'activated_at' => now(),
                    'cancelled_at' => null,
                    'suspended_at' => null,
                ]);
                $tenantAddon->save();
            });
    }

    public function tenantHasAddon(Tenant $tenant, string $slug): bool
    {
        return TenantAddon::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->whereHas('addon', fn ($query) => $query->where('slug', $slug)->where('is_active', true))
            ->exists();
    }

    public function tenantMenus(Tenant $tenant): array
    {
        $addons = TenantAddon::query()
            ->with('addon')
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->get();

        return $addons
            ->map(function (TenantAddon $tenantAddon) {
                $manifest = $tenantAddon->addon?->manifest ?? [];
                $slug = $tenantAddon->addon?->slug ?? '';
                $name = $tenantAddon->addon?->name ?? 'Addon';

                // Sempre tenta ler os menus do addon.json do sistema para garantir atualizacoes em tempo real (mesmo se o banco tiver cache)
                if ($slug !== '') {
                    $systemAddon = $this->addons->findBySlug($slug);
                    if ($systemAddon && !empty($systemAddon['meta'])) {
                        $manifest['tenant_menu'] = data_get($systemAddon['meta'], 'tenant_menu', data_get($manifest, 'tenant_menu', []));
                        $manifest['menu'] = data_get($systemAddon['meta'], 'menu', data_get($manifest, 'menu', []));
                    }
                }

                // O menu do addon na sidebar do sistema deve ser apenas a entrada do próprio addon (1 único link por addon).
                // Os submenus/telas internas do addon pertencem exclusivamente ao próprio addon (ficam na tela interna do addon).
                $menuConfig = data_get($manifest, 'menu', []);
                $tenantMenu = data_get($manifest, 'tenant_menu', []);

                $title = data_get($tenantMenu, 'title') 
                    ?: data_get($menuConfig, 'title') 
                    ?: $name;

                $icon = data_get($tenantMenu, 'icon') 
                    ?: data_get($menuConfig, 'icon') 
                    ?: 'bi-puzzle';

                $order = (int) (data_get($tenantMenu, 'order') ?: data_get($menuConfig, 'order', 100));

                // Determinar rota principal de entrada do addon
                $route = '';
                $url = '';

                // Se tenant_menu for uma lista com itens, pega a rota do primeiro item
                if (is_array($tenantMenu) && array_is_list($tenantMenu) && !empty($tenantMenu)) {
                    $route = (string) data_get($tenantMenu[0], 'route', '');
                    if (empty($icon) || $icon === 'bi-puzzle') {
                        $icon = (string) data_get($tenantMenu[0], 'icon', 'bi-puzzle');
                    }
                } elseif (is_array($tenantMenu) && !empty($tenantMenu['route'])) {
                    $route = (string) $tenantMenu['route'];
                } elseif (is_array($tenantMenu) && !empty($tenantMenu['items'][0]['route'])) {
                    $route = (string) $tenantMenu['items'][0]['route'];
                } elseif (!empty($menuConfig['route'])) {
                    $route = (string) $menuConfig['route'];
                } elseif (!empty($menuConfig['items'][0]['route'])) {
                    $route = (string) $menuConfig['items'][0]['route'];
                }

                // Fallback para rotas nomeadas conhecidas do addon
                if (empty($route) || ! \Illuminate\Support\Facades\Route::has($route)) {
                    $tenantNamedRoute = 'tenant.' . str_replace('_', '-', $slug) . '.index';
                    if (\Illuminate\Support\Facades\Route::has($tenantNamedRoute)) {
                        $route = $tenantNamedRoute;
                    } elseif (\Illuminate\Support\Facades\Route::has('tenant.' . $slug . '.index')) {
                        $route = 'tenant.' . $slug . '.index';
                    } elseif ($route !== '' && str_starts_with($route, 'admin.addons.generated.')) {
                        $url = '/painel/addons/' . $slug;
                        $route = '';
                    } else {
                        $url = '/painel/addons/' . $slug;
                        $route = '';
                    }
                }

                return [
                    'title' => (string) $title,
                    'icon'  => (string) $icon,
                    'route' => (string) $route,
                    'url'   => (string) $url,
                    'order' => $order,
                    'slug'  => $slug,
                ];
            })
            ->filter(fn (array $menu) => ($menu['route'] !== '' || $menu['url'] !== '') && filled($menu['title']))
            ->sortBy(['order', 'title'])
            ->values()
            ->all();
    }

    protected function attachAddonCharge(Tenant $tenant, MarketplaceAddon $addon, TenantBillingService $billing, ?string $billingMode = null): ?TenantInvoice
    {
        return DB::transaction(function () use ($tenant, $addon, $billing, $billingMode) {
            $isPlanProrata = $billingMode === 'plan_prorata';
            $quote = $isPlanProrata ? $this->calculateAddonProRata($tenant, $addon) : null;

            $line = [
                'type' => 'addon_subscription',
                'addon_id' => $addon->id,
                'addon_slug' => $addon->slug,
                'billing_cycle' => $addon->billing_cycle,
                'description' => $isPlanProrata
                    ? 'Pro-rata do addon '.$addon->name.' (ate vencimento do plano)'
                    : 'Assinatura do addon '.$addon->name,
                'quantity' => 1,
                'unit_amount' => $isPlanProrata ? (float) ($quote['prorata_amount'] ?? $addon->price) : (float) $addon->price,
                'amount' => $isPlanProrata ? (float) ($quote['prorata_amount'] ?? $addon->price) : (float) $addon->price,
                'recurring_amount' => (float) $addon->price,
            ];

            if ($isPlanProrata && $quote) {
                $line['period_start'] = $quote['period_start'] ?? null;
                $line['period_end'] = $quote['period_end'] ?? null;
                $line['remaining_days'] = $quote['remaining_days'] ?? null;
                $line['days_total'] = $quote['days_total'] ?? null;
            }

            $dueDate = $isPlanProrata && ! empty($quote['due_date'])
                ? $quote['due_date']
                : $billing->adjustToBusinessDay(now()->addDays(3))->toDateString();

            $invoice = $tenant->tenantInvoices()
                ->where('status', 'pending')
                ->whereDate('due_date', '>=', now()->toDateString())
                ->orderBy('due_date')
                ->lockForUpdate()
                ->first();

            if ($invoice) {
                $meta = $invoice->meta ?? [];
                $items = $meta['line_items'] ?? [];
                $items[] = $line;
                $meta['line_items'] = $items;
                $meta['grouped_addons'] = true;
                if ($isPlanProrata) {
                    $meta['plan_prorata_addon'] = true;
                }

                $invoice->forceFill([
                    'amount' => round((float) $invoice->amount + (float) $line['amount'], 2),
                    'description' => $isPlanProrata
                        ? 'Mensalidade e addons (pro-rata)'
                        : 'Mensalidade e addons recorrentes',
                    'meta' => $meta,
                ])->save();

                return $invoice->refresh();
            }

            $nextInvoiceNumber = 'TI-'.str_pad((string) (((int) TenantInvoice::max('id')) + 1), 6, '0', STR_PAD_LEFT);

            return $tenant->tenantInvoices()->create([
                'number' => $nextInvoiceNumber,
                'amount' => (float) $line['amount'],
                'description' => $isPlanProrata
                    ? 'Pro-rata do addon '.$addon->name.' (vinculado ao plano)'
                    : 'Assinatura do addon '.$addon->name,
                'due_date' => $dueDate,
                'status' => 'pending',
                'meta' => [
                    'line_items' => [$line],
                    'grouped_addons' => true,
                    'plan_prorata_addon' => $isPlanProrata,
                ],
            ]);
        });
    }

    protected function calculateAddonProRata(Tenant $tenant, MarketplaceAddon $addon): array
    {
        $periodStart = $tenant->current_period_start ? \Carbon\Carbon::parse($tenant->current_period_start)->startOfDay() : now()->startOfDay();
        $periodEnd = $tenant->current_period_end ? \Carbon\Carbon::parse($tenant->current_period_end)->endOfDay() : $periodStart->copy()->addMonth()->endOfDay();

        if ($periodEnd->lessThanOrEqualTo(now())) {
            $periodStart = now()->startOfDay();
            $periodEnd = $periodStart->copy()->addMonth()->endOfDay();
        }

        $totalDays = max(1, (int) ceil($periodStart->diffInHours($periodEnd) / 24));
        $remainingDays = max(1, (int) ceil(now()->startOfDay()->diffInHours($periodEnd) / 24));
        $remainingDays = min($remainingDays, $totalDays);

        $monthlyPrice = (float) $addon->price;
        $dailyRate = $monthlyPrice / $totalDays;
        $prorata = round($dailyRate * $remainingDays, 2);

        return [
            'monthly_price' => $monthlyPrice,
            'prorata_amount' => $prorata,
            'daily_rate' => round($dailyRate, 4),
            'remaining_days' => $remainingDays,
            'days_total' => $totalDays,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'due_date' => $periodEnd->copy()->startOfDay()->toDateString(),
        ];
    }

    protected function inspectZip(string $zipPath, string $baseDir): array
    {
        $extractDir = $baseDir.'/extract';
        File::ensureDirectoryExists($extractDir);

        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('Nao foi possivel abrir o pacote ZIP enviado.');
        }

        for ($i = 0; $i < $zip->numEntries; $i++) {
            $entry = $zip->getNameIndex($i);
            if ($entry === false) {
                continue;
            }

            $normalized = str_replace('\\', '/', $entry);
            if (str_contains($normalized, '..') || str_starts_with($normalized, '/')) {
                $zip->close();
                throw new \RuntimeException('Pacote invalido: path traversal detectado.');
            }
        }

        $zip->extractTo($extractDir);
        $zip->close();

        $root = $this->resolveAddonRoot($extractDir);
        if (! $root) {
            throw new \RuntimeException('Pacote sem addon.json ou module.json valido.');
        }

        $manifestPath = File::exists($root.'/addon.json') ? $root.'/addon.json' : $root.'/module.json';
        $manifest = json_decode((string) File::get($manifestPath), true);

        if (! is_array($manifest)) {
            throw new \RuntimeException('Manifesto do addon esta invalido.');
        }

        $slug = Str::slug((string) ($manifest['slug'] ?? ''));
        if ($slug === '' || empty($manifest['provider'])) {
            throw new \RuntimeException('Manifesto do addon precisa informar slug e provider.');
        }

        $security = $this->security->validateInstall($root, true);

        return [$manifest, $root, $security];
    }

    protected function resolveAddonRoot(string $extractDir): ?string
    {
        if (File::exists($extractDir.'/addon.json') || File::exists($extractDir.'/module.json')) {
            return $extractDir;
        }

        foreach (File::directories($extractDir) as $dir) {
            if (File::exists($dir.'/addon.json') || File::exists($dir.'/module.json')) {
                return $dir;
            }
        }

        return null;
    }
}
