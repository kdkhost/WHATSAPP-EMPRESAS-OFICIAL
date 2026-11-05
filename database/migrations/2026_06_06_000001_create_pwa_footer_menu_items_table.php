<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pwa_footer_menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('label', 60);
            $table->string('icon', 60)->default('bi bi-circle');
            $table->string('url', 255)->nullable();
            $table->string('route_name', 120)->nullable();
            $table->unsignedTinyInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('match_patterns')->nullable();
            $table->string('role', 20)->default('both')->comment('tenant, admin, both');
            $table->timestamps();
        });

        // Seed com itens padrao (mesmo layout atual)
        $now = now();
        \Illuminate\Support\Facades\DB::table('pwa_footer_menu_items')->insert([
            [
                'label' => 'Inicio',
                'icon' => 'bi bi-house-door',
                'route_name' => 'pwa.dashboard',
                'position' => 1,
                'is_active' => true,
                'match_patterns' => json_encode(['pwa.dashboard', 'pwa/dashboard', 'painel', 'painel/dashboard', 'admin', 'admin/dashboard']),
                'role' => 'both',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'label' => 'Mensagens',
                'icon' => 'bi bi-chat-dots',
                'route_name' => 'pwa.messages',
                'position' => 2,
                'is_active' => true,
                'match_patterns' => json_encode(['pwa.messages', 'pwa/mensagens', 'painel/whatsapp/mensagens*']),
                'role' => 'both',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'label' => 'Cobrancas',
                'icon' => 'bi bi-credit-card',
                'route_name' => 'pwa.billing',
                'position' => 3,
                'is_active' => true,
                'match_patterns' => json_encode(['pwa.billing', 'pwa/cobrancas', 'painel/cobrancas*']),
                'role' => 'both',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'label' => 'QR/Instancia',
                'icon' => 'bi bi-qr-code',
                'route_name' => 'pwa.instance',
                'position' => 4,
                'is_active' => true,
                'match_patterns' => json_encode(['pwa.instance', 'pwa/instancia', 'painel/whatsapp/instancia*']),
                'role' => 'both',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'label' => 'Perfil',
                'icon' => 'bi bi-person',
                'route_name' => 'pwa.profile',
                'position' => 5,
                'is_active' => true,
                'match_patterns' => json_encode(['pwa.profile', 'pwa/perfil', 'painel/perfil*']),
                'role' => 'both',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('pwa_footer_menu_items');
    }
};
