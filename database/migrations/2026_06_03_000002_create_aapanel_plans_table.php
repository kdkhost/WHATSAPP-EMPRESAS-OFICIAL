<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('aapanel_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('server_id')->nullable();
            $table->unsignedInteger('disk_mb')->default(1024);
            $table->unsignedInteger('bandwidth_mb')->default(10240);
            $table->unsignedInteger('database_limit')->default(1);
            $table->unsignedInteger('ftp_limit')->default(1);
            $table->unsignedInteger('domain_limit')->default(1);
            $table->string('php_version')->default('83');
            $table->boolean('ssl_enabled')->default(true);
            $table->boolean('active')->default(true);
            $table->json('features')->nullable();
            $table->timestamps();

            $table->foreign('server_id')->references('id')->on('aapanel_servers')->nullOnDelete();
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aapanel_plans');
    }
};
