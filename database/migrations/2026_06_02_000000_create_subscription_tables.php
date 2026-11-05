<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('subscription_plans')) {
            Schema::create('subscription_plans', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->decimal('price', 10, 2);
                $table->enum('cycle', ['monthly', 'quarterly', 'semiannual', 'annual'])->default('monthly');
                $table->unsignedInteger('trial_days')->default(0);
                $table->unsignedInteger('grace_days')->default(3);
                $table->string('gateway')->default('mercadopago');
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('subscriptions')) {
            Schema::create('subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('subscription_plan_id')->constrained('subscription_plans')->cascadeOnDelete();
                $table->string('customer_name');
                $table->string('customer_email')->nullable();
                $table->string('customer_phone')->nullable();
                $table->decimal('price', 10, 2);
                $table->enum('status', ['active', 'pending', 'canceled', 'expired'])->default('active');
                $table->date('started_at')->nullable();
                $table->date('next_billing')->nullable();
                $table->date('canceled_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('subscription_plans');
    }
};
