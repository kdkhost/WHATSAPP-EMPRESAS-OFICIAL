<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modulo de Afiliados - sistema de indicacoes com comissao recorrente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('affiliates')) {
            Schema::create('affiliates', function (Blueprint $t) {
                $t->id();
                $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $t->string('slug', 60)->unique();
                $t->string('referral_code', 20)->unique();
                $t->decimal('commission_pct', 5, 2)->default(20.00);
                $t->enum('commission_type', ['recurring', 'first_payment', 'lifetime'])->default('recurring');
                $t->decimal('balance', 12, 2)->default(0);
                $t->decimal('total_earned', 12, 2)->default(0);
                $t->decimal('total_paid', 12, 2)->default(0);
                $t->decimal('min_withdrawal', 10, 2)->default(50);
                $t->json('payment_info')->nullable();
                $t->enum('status', ['pending', 'active', 'suspended'])->default('pending');
                $t->unsignedInteger('clicks')->default(0);
                $t->unsignedInteger('conversions')->default(0);
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('affiliate_clicks')) {
            Schema::create('affiliate_clicks', function (Blueprint $t) {
                $t->id();
                $t->foreignId('affiliate_id')->constrained('affiliates')->cascadeOnDelete();
                $t->string('ip', 45)->nullable();
                $t->string('user_agent', 500)->nullable();
                $t->string('referer', 500)->nullable();
                $t->string('country', 2)->nullable();
                $t->boolean('converted')->default(false);
                $t->timestamp('created_at')->useCurrent()->index();
            });
        }

        if (! Schema::hasTable('affiliate_referrals')) {
            Schema::create('affiliate_referrals', function (Blueprint $t) {
                $t->id();
                $t->foreignId('affiliate_id')->constrained('affiliates')->cascadeOnDelete();
                $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $t->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
                $t->date('cookie_expires_at')->nullable();
                $t->boolean('first_payment_done')->default(false);
                $t->timestamps();
                $t->unique(['affiliate_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('affiliate_commissions')) {
            Schema::create('affiliate_commissions', function (Blueprint $t) {
                $t->id();
                $t->foreignId('affiliate_id')->constrained('affiliates')->cascadeOnDelete();
                $t->foreignId('referral_id')->constrained('affiliate_referrals')->cascadeOnDelete();
                $t->foreignId('tenant_invoice_id')->nullable()->constrained('tenant_invoices')->nullOnDelete();
                $t->decimal('base_amount', 12, 2);
                $t->decimal('commission_pct', 5, 2);
                $t->decimal('commission_amount', 12, 2);
                $t->enum('status', ['pending', 'approved', 'paid', 'canceled'])->default('pending');
                $t->timestamp('approved_at')->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('affiliate_withdrawals')) {
            Schema::create('affiliate_withdrawals', function (Blueprint $t) {
                $t->id();
                $t->foreignId('affiliate_id')->constrained('affiliates')->cascadeOnDelete();
                $t->decimal('amount', 12, 2);
                $t->enum('status', ['pending', 'approved', 'paid', 'rejected'])->default('pending');
                $t->json('payment_info');
                $t->text('admin_note')->nullable();
                $t->timestamp('paid_at')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_withdrawals');
        Schema::dropIfExists('affiliate_commissions');
        Schema::dropIfExists('affiliate_referrals');
        Schema::dropIfExists('affiliate_clicks');
        Schema::dropIfExists('affiliates');
    }
};
