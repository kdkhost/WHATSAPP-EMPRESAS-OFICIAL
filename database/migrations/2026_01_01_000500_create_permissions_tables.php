<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sistema de permissoes modulares e granulares.
 *
 * Hierarquia:
 *   superadmin → acesso TOTAL (bypass de todas as verificacoes)
 *   admin      → acesso configuravel por modulo/acao
 *   user       → acesso publico (frontend)
 *
 * Cada modulo pode declarar suas permissions.
 * Permissions sao granulares: view, create, edit, delete, manage, export por modulo.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Roles (papeis) - superadmin, admin, operator, viewer, etc.
        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('name');                    // "Super Administrador"
            $t->string('slug', 60)->unique();      // "superadmin"
            $t->string('description')->nullable();
            $t->boolean('is_system')->default(false); // protegido contra exclusao
            $t->unsignedInteger('level')->default(0); // hierarquia (maior = mais poder)
            $t->timestamps();
        });

        // Permissions (acoes granulares)
        Schema::create('permissions', function (Blueprint $t) {
            $t->id();
            $t->string('module', 60)->index();     // "billing", "templates", "security", addon slug
            $t->string('action', 40);              // "view", "create", "edit", "delete", "manage", "export"
            $t->string('slug', 120)->unique();     // "billing.view", "billing.create"
            $t->string('label');                   // "Visualizar cobrancas"
            $t->string('group', 60)->nullable();   // agrupamento visual (ex.: "Cobrancas")
            $t->timestamps();

            $t->index(['module', 'action']);
        });

        // Pivot: role_id <-> permission_id
        Schema::create('role_permissions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $t->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['role_id', 'permission_id']);
        });

        // Pivot: admin_user <-> role (um usuario pode ter multiplas roles)
        Schema::create('admin_user_roles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('admin_user_id')->constrained('admin_users')->cascadeOnDelete();
            $t->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['admin_user_id', 'role_id']);
        });

        // Permissions diretas por usuario (override - pode ter permissao extra fora da role)
        Schema::create('admin_user_permissions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('admin_user_id')->constrained('admin_users')->cascadeOnDelete();
            $t->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $t->enum('type', ['grant', 'deny'])->default('grant'); // grant = permite, deny = nega (override)
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['admin_user_id', 'permission_id']);
        });

        // Modulos registrados (para gerenciamento no painel)
        Schema::create('registered_modules', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 60)->unique();      // "billing", "security", "meu-addon"
            $t->string('name');
            $t->enum('type', ['official', 'addon'])->default('official');
            $t->string('version', 20)->nullable();
            $t->string('author')->nullable();
            $t->text('description')->nullable();
            $t->boolean('enabled')->default(true);
            $t->boolean('installed')->default(true);
            $t->json('meta')->nullable();          // config extra, dependencias, etc.
            $t->timestamp('installed_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registered_modules');
        Schema::dropIfExists('admin_user_permissions');
        Schema::dropIfExists('admin_user_roles');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
