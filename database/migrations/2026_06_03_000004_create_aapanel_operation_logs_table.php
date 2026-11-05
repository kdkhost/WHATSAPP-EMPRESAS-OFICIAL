<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('aapanel_operation_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('server_id')->nullable();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->string('operation');
            $table->string('status')->default('pending');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('endpoint')->nullable();
            $table->text('message')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();

            $table->foreign('server_id')->references('id')->on('aapanel_servers')->nullOnDelete();
            $table->foreign('account_id')->references('id')->on('aapanel_accounts')->nullOnDelete();

            $table->index('operation');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aapanel_operation_logs');
    }
};
