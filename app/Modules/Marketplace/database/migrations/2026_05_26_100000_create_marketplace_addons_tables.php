<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('marketplace_addons')) {
            Schema::create('marketplace_addons', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 100)->unique();
                $table->string('name', 160);
                $table->string('version', 40)->default('1.0.0');
                $table->string('author', 160)->nullable();
                $table->string('category', 100)->nullable();
                $table->string('icon', 80)->nullable();
                $table->string('color', 40)->nullable();
                $table->decimal('price', 10, 2)->default(0);
                $table->string('billing_cycle', 30)->default('monthly');
                $table->boolean('commercial')->default(true)->index();
                $table->boolean('is_active')->default(true)->index();
                $table->boolean('is_installed')->default(false)->index();
                $table->string('source', 40)->default('custom')->index();
                $table->unsignedBigInteger('submission_id')->nullable()->index();
                $table->unsignedBigInteger('approved_by')->nullable()->index();
                $table->timestamp('approved_at')->nullable()->index();
                $table->string('package_path', 255)->nullable();
                $table->text('description')->nullable();
                $table->json('manifest')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('addon_submissions')) {
            Schema::create('addon_submissions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('marketplace_addon_id')->nullable()->index();
                $table->string('name', 160)->nullable();
                $table->string('slug', 100)->nullable()->index();
                $table->string('version', 40)->nullable();
                $table->string('zip_path', 255);
                $table->string('extract_path', 255)->nullable();
                $table->string('installed_path', 255)->nullable();
                $table->string('status', 30)->default('pending')->index();
                $table->unsignedBigInteger('reviewed_by')->nullable()->index();
                $table->timestamp('reviewed_at')->nullable()->index();
                $table->text('review_notes')->nullable();
                $table->json('manifest')->nullable();
                $table->json('security_report')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tenant_addons')) {
            Schema::create('tenant_addons', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('marketplace_addon_id')->index();
                $table->unsignedBigInteger('tenant_invoice_id')->nullable()->index();
                $table->string('status', 30)->default('pending')->index();
                $table->string('billing_cycle', 30)->default('monthly');
                $table->decimal('recurring_amount', 10, 2)->default(0);
                $table->timestamp('activated_at')->nullable()->index();
                $table->timestamp('suspended_at')->nullable()->index();
                $table->timestamp('cancelled_at')->nullable()->index();
                $table->json('settings')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'marketplace_addon_id'], 'tenant_addons_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_addons');
        Schema::dropIfExists('addon_submissions');
        Schema::dropIfExists('marketplace_addons');
    }
};
