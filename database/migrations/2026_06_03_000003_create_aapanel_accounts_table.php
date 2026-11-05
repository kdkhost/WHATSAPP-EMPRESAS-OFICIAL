<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('aapanel_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('server_id');
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('domain')->unique();
            $table->string('username')->nullable();
            $table->text('password_encrypted')->nullable();
            $table->string('ftp_username')->nullable();
            $table->text('ftp_password_encrypted')->nullable();
            $table->string('database_name')->nullable();
            $table->string('database_username')->nullable();
            $table->text('database_password_encrypted')->nullable();
            $table->string('site_id')->nullable();
            $table->string('status')->default('pending');
            $table->boolean('ssl_enabled')->default(false);
            $table->timestamp('provisioned_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('terminated_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('server_id')->references('id')->on('aapanel_servers')->cascadeOnDelete();
            $table->foreign('plan_id')->references('id')->on('aapanel_plans')->nullOnDelete();

            $table->index('status');
            $table->index('customer_id');
            $table->index('server_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aapanel_accounts');
    }
};
