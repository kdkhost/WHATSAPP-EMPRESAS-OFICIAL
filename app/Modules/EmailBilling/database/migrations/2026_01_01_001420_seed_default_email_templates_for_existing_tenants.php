<?php

use App\Modules\EmailBilling\Services\EmailTemplateDefaults;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (! app()->runningInConsole()) {
            return;
        }

        $defaults = app(EmailTemplateDefaults::class);
        Tenant::query()->orderBy('id')->chunkById(200, function ($tenants) use ($defaults) {
            foreach ($tenants as $tenant) {
                $defaults->ensureForTenant($tenant);
            }
        });
    }

    public function down(): void
    {
        // Mantemos templates criados, sem reversao destrutiva.
    }
};

