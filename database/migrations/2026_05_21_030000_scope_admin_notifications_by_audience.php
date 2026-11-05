<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admin_notifications')) {
            return;
        }

        Schema::table('admin_notifications', function (Blueprint $table) {
            if (! Schema::hasColumn('admin_notifications', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
            }

            if (! Schema::hasColumn('admin_notifications', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->index();
            }
        });

        $this->backfillBillingNotifications();
    }

    private function backfillBillingNotifications(): void
    {
        if (! Schema::hasTable('invoices') || ! Schema::hasTable('tenants')) {
            return;
        }

        DB::table('admin_notifications')
            ->whereNull('tenant_id')
            ->whereNull('user_id')
            ->where('group', 'billing')
            ->where('url', 'like', '%/billing/invoices/%')
            ->orderBy('id')
            ->chunkById(100, function ($notifications) {
                foreach ($notifications as $notification) {
                    if (! preg_match('#/billing/invoices/(\d+)#', (string) $notification->url, $matches)) {
                        continue;
                    }

                    $invoice = DB::table('invoices')
                        ->select('id', 'tenant_id')
                        ->where('id', (int) $matches[1])
                        ->first();

                    if (! $invoice?->tenant_id) {
                        continue;
                    }

                    $tenant = DB::table('tenants')
                        ->select('id', 'user_id')
                        ->where('id', $invoice->tenant_id)
                        ->first();

                    if (! $tenant) {
                        continue;
                    }

                    DB::table('admin_notifications')->where('id', $notification->id)->update([
                        'tenant_id' => $tenant->id,
                        'user_id' => $tenant->user_id,
                        'url' => '/painel',
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Migration de seguranca: nao removemos colunas para evitar reabrir vazamento de notificacoes.
    }
};
