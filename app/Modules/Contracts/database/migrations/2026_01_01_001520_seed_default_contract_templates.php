<?php

use App\Modules\Contracts\Services\ContractTemplateCatalog;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contract_templates') || ! Schema::hasTable('tenants')) {
            return;
        }

        $catalog = app(ContractTemplateCatalog::class);

        Tenant::query()->select(['id', 'company_name'])->chunkById(100, function ($tenants) use ($catalog): void {
            foreach ($tenants as $tenant) {
                $catalog->ensureDefaultsForTenant($tenant);
            }
        });
    }

    public function down(): void
    {
    }
};
