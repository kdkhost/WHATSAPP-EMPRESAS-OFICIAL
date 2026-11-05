<?php

use App\Modules\EmailBilling\Services\EmailTemplateDefaults;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = app(EmailTemplateDefaults::class);

        Tenant::query()->orderBy('id')->chunkById(200, function ($tenants) use ($defaults) {
            foreach ($tenants as $tenant) {
                $defaults->ensureForTenant($tenant);
            }
        });
    }

    public function down(): void
    {
        // Nao removemos templates para evitar perda de edicoes feitas pelo cliente.
    }
};

