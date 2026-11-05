<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Integracao com WHM/cPanel via API token.
 *
 * Funcionalidades:
 *  - Listar contas cPanel (whostmgr1/json-api/listaccts)
 *  - Suspender/ativar conta (suspendacct/unsuspendacct)
 *  - Criar conta nova (createacct)
 *  - Backup remoto
 *  - Sincronizar dados de cliente (whostmgr1/json-api/accountsummary)
 *
 * Requer: API Token criado em WHM > Development > Manage API Tokens
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cpanel_servers', function (Blueprint $t) {
            $t->id();
            $t->string('name', 80);
            $t->string('hostname');                     // server.exemplo.com
            $t->unsignedInteger('port')->default(2087); // 2087 (WHM) / 2083 (cPanel)
            $t->enum('type', ['whm', 'cpanel'])->default('whm');
            $t->string('username')->default('root');
            $t->text('api_token');                      // encriptado
            $t->boolean('verify_ssl')->default(true);
            $t->boolean('active')->default(true);
            $t->timestamp('last_check_at')->nullable();
            $t->string('last_check_status', 20)->nullable();
            $t->json('meta')->nullable();
            $t->timestamps();
        });

        Schema::create('cpanel_actions_log', function (Blueprint $t) {
            $t->id();
            $t->foreignId('server_id')->constrained('cpanel_servers')->cascadeOnDelete();
            $t->foreignId('admin_user_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $t->string('action', 60);
            $t->string('target', 120)->nullable();      // username/dominio
            $t->enum('status', ['success', 'error'])->default('success');
            $t->json('payload')->nullable();
            $t->text('response')->nullable();
            $t->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cpanel_actions_log');
        Schema::dropIfExists('cpanel_servers');
    }
};
