<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_visits')) {
            Schema::create('system_visits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->index()->constrained('users')->nullOnDelete();
                $table->foreignId('tenant_id')->nullable()->index();
                $table->string('session_id', 64)->nullable()->index();
                $table->string('ip_address', 45)->nullable()->index();
                $table->text('user_agent')->nullable();
                $table->string('device_type', 20)->default('desktop')->index();
                $table->string('browser', 50)->nullable()->index();
                $table->string('platform', 50)->nullable()->index();
                $table->string('path', 191)->default('/')->index();
                $table->text('url')->nullable();
                $table->string('method', 10)->default('GET');
                $table->text('referer')->nullable();
                $table->string('referer_host', 191)->nullable()->index();
                $table->boolean('is_authenticated')->default(false)->index();
                $table->boolean('is_unique_today')->default(true)->index();
                $table->timestamp('last_activity_at')->nullable()->index();
                $table->timestamps();

                $table->index(['created_at', 'is_authenticated']);
                $table->index(['created_at', 'device_type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('system_visits');
    }
};
