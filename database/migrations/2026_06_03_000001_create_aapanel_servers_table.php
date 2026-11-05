<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('aapanel_servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('host');
            $table->unsignedInteger('port')->default(7800);
            $table->boolean('use_ssl')->default(true);
            $table->text('api_key_encrypted');
            $table->string('status')->default('unknown');
            $table->timestamp('last_check_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('last_check_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aapanel_servers');
    }
};
