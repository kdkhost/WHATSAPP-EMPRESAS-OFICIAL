<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabela de Tenants
        if (!Schema::hasTable('tenants')) {
            Schema::create('tenants', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('phone')->nullable();
                $table->string('document')->nullable();
                $table->string('plan_name')->default('Pro');
                $table->string('status')->default('active'); // active, suspended, pending
                $table->string('subscription_status')->default('active');
                $table->timestamp('subscription_expires_at')->nullable();

                // Credenciais Meta Cloud API
                $table->string('meta_phone_number_id')->nullable();
                $table->string('meta_waba_id')->nullable();
                $table->text('meta_access_token')->nullable();
                $table->string('meta_app_secret')->nullable();
                $table->string('meta_verify_token')->nullable();

                // Credenciais Mercado Pago SDK v2
                $table->text('mp_access_token')->nullable();
                $table->string('mp_public_key')->nullable();
                $table->string('mp_webhook_secret')->nullable();

                $table->json('settings')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('tenants', function (Blueprint $table) {
                if (!Schema::hasColumn('tenants', 'meta_phone_number_id')) $table->string('meta_phone_number_id')->nullable();
                if (!Schema::hasColumn('tenants', 'meta_waba_id')) $table->string('meta_waba_id')->nullable();
                if (!Schema::hasColumn('tenants', 'meta_access_token')) $table->text('meta_access_token')->nullable();
                if (!Schema::hasColumn('tenants', 'meta_app_secret')) $table->string('meta_app_secret')->nullable();
                if (!Schema::hasColumn('tenants', 'meta_verify_token')) $table->string('meta_verify_token')->nullable();
                if (!Schema::hasColumn('tenants', 'mp_access_token')) $table->text('mp_access_token')->nullable();
                if (!Schema::hasColumn('tenants', 'mp_public_key')) $table->string('mp_public_key')->nullable();
                if (!Schema::hasColumn('tenants', 'mp_webhook_secret')) $table->string('mp_webhook_secret')->nullable();
                if (!Schema::hasColumn('tenants', 'subscription_status')) $table->string('subscription_status')->default('active');
                if (!Schema::hasColumn('tenants', 'subscription_expires_at')) $table->timestamp('subscription_expires_at')->nullable();
            });
        }

        // 2. Tabela de Invoices
        if (!Schema::hasTable('invoices')) {
            Schema::create('invoices', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('number')->nullable()->index();
                $table->string('client_name')->nullable();
                $table->string('client_phone')->nullable()->index();
                $table->string('client_email')->nullable();
                $table->string('client_document')->nullable();
                $table->decimal('amount', 12, 2)->default(0.00);
                $table->date('due_date')->nullable()->index();
                $table->string('status')->default('pending')->index(); // pending, paid, overdue, canceled, refunded, partially_refunded
                $table->string('token', 64)->nullable()->unique()->index();
                $table->string('webhook_token', 64)->nullable()->unique()->index();
                $table->text('pix_code')->nullable();
                $table->longText('qr_code_base64')->nullable();
                $table->decimal('fee_mercadopago', 10, 2)->default(0.00);
                $table->decimal('net_amount', 12, 2)->default(0.00);
                $table->string('gateway')->default('mercadopago');
                $table->string('gateway_charge_id')->nullable()->index();
                $table->timestamp('paid_at')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('invoices', function (Blueprint $table) {
                if (!Schema::hasColumn('invoices', 'client_name')) $table->string('client_name')->nullable();
                if (!Schema::hasColumn('invoices', 'client_phone')) $table->string('client_phone')->nullable()->index();
                if (!Schema::hasColumn('invoices', 'client_email')) $table->string('client_email')->nullable();
                if (!Schema::hasColumn('invoices', 'client_document')) $table->string('client_document')->nullable();
                if (!Schema::hasColumn('invoices', 'token')) $table->string('token', 64)->nullable()->index();
                if (!Schema::hasColumn('invoices', 'pix_code')) $table->text('pix_code')->nullable();
                if (!Schema::hasColumn('invoices', 'qr_code_base64')) $table->longText('qr_code_base64')->nullable();
                if (!Schema::hasColumn('invoices', 'fee_mercadopago')) $table->decimal('fee_mercadopago', 10, 2)->default(0.00);
                if (!Schema::hasColumn('invoices', 'net_amount')) $table->decimal('net_amount', 12, 2)->default(0.00);
            });
        }

        // 3. Tabela de BillingRules (Régua de Disparos: D-3, D0, D+1, D+3, D+5)
        if (!Schema::hasTable('billing_rules')) {
            Schema::create('billing_rules', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->unsignedBigInteger('invoice_id')->index();
                $table->integer('trigger_offset')->default(0); // -3, 0, 1, 3, 5
                $table->date('scheduled_date')->index();
                $table->string('status')->default('scheduled')->index(); // scheduled, sent, canceled, failed
                $table->timestamp('sent_at')->nullable();
                $table->string('meta_message_id')->nullable()->index();
                $table->text('error_message')->nullable();
                $table->string('canceled_reason')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Safe down
    }
};

