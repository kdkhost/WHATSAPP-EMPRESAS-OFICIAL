<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('whatsapp_instances')) {
            Schema::create('whatsapp_instances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
                $table->foreignId('tenant_instance_id')->nullable()->constrained('tenant_instances')->nullOnDelete();
                $table->string('instance_name', 120);
                $table->text('instance_token')->nullable();
                $table->string('global_api_key_ref', 120)->nullable();
                $table->string('phone', 32)->nullable();
                $table->string('jid', 120)->nullable();
                $table->string('profile_name', 160)->nullable();
                $table->text('profile_picture')->nullable();
                $table->string('client_name', 160)->nullable();
                $table->boolean('connected')->default(false);
                $table->boolean('logged_in')->default(false);
                $table->string('server_status', 80)->nullable();
                $table->timestamp('last_sync_at')->nullable();
                $table->timestamp('last_qr_at')->nullable();
                $table->timestamp('last_webhook_at')->nullable();
                $table->json('server_payload')->nullable();
                $table->string('local_hash', 80)->nullable();
                $table->string('remote_hash', 80)->nullable();
                $table->string('sync_status', 40)->default('local_only');
                $table->text('sync_error')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'instance_name']);
                $table->index('instance_name');
                $table->index('tenant_id');
                $table->index('phone');
                $table->index('sync_status');
                $table->index('created_at');
            });
        }

        if (! Schema::hasTable('whatsapp_customer_limits')) {
            Schema::create('whatsapp_customer_limits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
                $table->string('phone', 32)->nullable();
                $table->string('action_type', 60);
                $table->unsignedInteger('total_requests')->default(0);
                $table->date('limit_date');
                $table->timestamps();

                $table->unique(['tenant_id', 'customer_id', 'phone', 'action_type', 'limit_date'], 'whatsapp_limits_unique_day');
                $table->index('tenant_id');
                $table->index('customer_id');
                $table->index('phone');
                $table->index('limit_date');
            });
        }

        if (! Schema::hasTable('whatsapp_customer_service_logs')) {
            Schema::create('whatsapp_customer_service_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
                $table->foreignId('whatsapp_instance_id')->nullable()->constrained('whatsapp_instances')->nullOnDelete();
                $table->foreignId('tenant_instance_id')->nullable()->constrained('tenant_instances')->nullOnDelete();
                $table->string('phone', 32)->nullable();
                $table->string('action', 80)->nullable();
                $table->string('direction', 12)->default('in');
                $table->longText('message')->nullable();
                $table->longText('response')->nullable();
                $table->string('status', 40)->default('processed');
                $table->json('payload')->nullable();
                $table->string('ip', 64)->nullable();
                $table->text('user_agent')->nullable();
                $table->text('error')->nullable();
                $table->timestamps();

                $table->index('tenant_id');
                $table->index('customer_id');
                $table->index('phone');
                $table->index('action');
                $table->index('created_at');
            });
        }

        if (! Schema::hasTable('whatsapp_sync_logs')) {
            Schema::create('whatsapp_sync_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
                $table->foreignId('whatsapp_instance_id')->nullable()->constrained('whatsapp_instances')->nullOnDelete();
                $table->foreignId('tenant_instance_id')->nullable()->constrained('tenant_instances')->nullOnDelete();
                $table->string('instance_name', 120)->nullable()->index();
                $table->string('action', 80);
                $table->string('status', 40)->default('ok');
                $table->json('local_payload')->nullable();
                $table->json('remote_payload')->nullable();
                $table->text('error')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();

                $table->index('tenant_id');
                $table->index('status');
                $table->index('created_at');
            });
        }

        if (! Schema::hasTable('whatsapp_webhook_logs')) {
            Schema::create('whatsapp_webhook_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
                $table->foreignId('whatsapp_instance_id')->nullable()->constrained('whatsapp_instances')->nullOnDelete();
                $table->foreignId('tenant_instance_id')->nullable()->constrained('tenant_instances')->nullOnDelete();
                $table->string('instance_name', 120)->nullable();
                $table->string('event_name', 100)->nullable();
                $table->string('event_uid', 160)->nullable();
                $table->string('source_ip', 64)->nullable();
                $table->json('headers')->nullable();
                $table->json('raw_payload')->nullable();
                $table->json('normalized_payload')->nullable();
                $table->string('status', 40)->default('received');
                $table->text('error')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                $table->index('tenant_id');
                $table->index('tenant_instance_id');
                $table->index('instance_name');
                $table->index('event_name');
                $table->index('event_uid');
                $table->index('status');
                $table->index('created_at');
            });
        }

        if (! Schema::hasTable('security_audit_logs')) {
            Schema::create('security_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('admin_user_id')->nullable();
                $table->string('guard', 30)->nullable();
                $table->string('action', 100)->index();
                $table->string('subject_type', 120)->nullable();
                $table->string('subject_id', 120)->nullable();
                $table->string('ip', 64)->nullable();
                $table->text('user_agent')->nullable();
                $table->json('payload')->nullable();
                $table->string('status', 40)->default('logged')->index();
                $table->text('error')->nullable();
                $table->timestamps();

                $table->index('tenant_id');
                $table->index('user_id');
                $table->index('admin_user_id');
                $table->index('created_at');
            });
        }

        if (Schema::hasTable('registered_modules')) {
            Schema::table('registered_modules', function (Blueprint $table) {
                if (! Schema::hasColumn('registered_modules', 'route')) {
                    $table->string('route', 255)->nullable()->after('description');
                }
                if (! Schema::hasColumn('registered_modules', 'icon')) {
                    $table->string('icon', 80)->nullable()->after('route');
                }
                if (! Schema::hasColumn('registered_modules', 'menu_title')) {
                    $table->string('menu_title', 120)->nullable()->after('icon');
                }
                if (! Schema::hasColumn('registered_modules', 'menu_order')) {
                    $table->unsignedInteger('menu_order')->default(100)->after('menu_title');
                }
            });
        }

        if (! Schema::hasTable('addon_menus')) {
            Schema::create('addon_menus', function (Blueprint $table) {
                $table->id();
                $table->string('module_slug', 80)->index();
                $table->string('title', 120);
                $table->string('icon', 80)->nullable();
                $table->string('route', 255);
                $table->unsignedInteger('menu_order')->default(100);
                $table->boolean('active')->default(true)->index();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->unique(['module_slug', 'route'], 'addon_menus_module_route_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('addon_menus');
        Schema::dropIfExists('security_audit_logs');
        Schema::dropIfExists('whatsapp_webhook_logs');
        Schema::dropIfExists('whatsapp_sync_logs');
        Schema::dropIfExists('whatsapp_customer_service_logs');
        Schema::dropIfExists('whatsapp_customer_limits');
        Schema::dropIfExists('whatsapp_instances');
    }
};
