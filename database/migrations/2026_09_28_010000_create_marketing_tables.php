<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('marketing_visits')) {
            Schema::create('marketing_visits', function (Blueprint $table) {
                $table->id();
                $table->string('session_id', 64)->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->string('device_type', 20)->default('desktop');
                $table->text('referer')->nullable();
                $table->string('referer_host', 191)->nullable()->index();
                $table->string('origin_platform', 50)->default('Direto')->index();
                $table->text('url')->nullable();
                $table->string('utm_source', 100)->nullable()->index();
                $table->string('utm_medium', 100)->nullable();
                $table->string('utm_campaign', 100)->nullable()->index();
                $table->string('utm_term', 100)->nullable();
                $table->string('utm_content', 100)->nullable();
                $table->boolean('is_unique')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('marketing_leads')) {
            Schema::create('marketing_leads', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->string('whatsapp', 30)->index();
                $table->string('email', 120)->nullable()->index();
                $table->string('interest', 120)->nullable();
                $table->text('notes')->nullable();
                $table->string('status', 20)->default('new')->index();
                $table->string('origin_platform', 50)->default('Direto');
                $table->text('referer')->nullable();
                $table->string('utm_source', 100)->nullable();
                $table->string('utm_medium', 100)->nullable();
                $table->string('utm_campaign', 100)->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('device_type', 20)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_leads');
        Schema::dropIfExists('marketing_visits');
    }
};
