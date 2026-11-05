<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('subscription_plans')) {
            return;
        }

        // Só insere se a tabela estiver vazia
        if (DB::table('subscription_plans')->count() > 0) {
            return;
        }

        $now = now();

        // 4 Planos
        DB::table('subscription_plans')->insert([
            [
                'id' => 1,
                'name' => 'Plano Essencial',
                'description' => 'Ideal para quem esta comecando. Envios basicos com suporte por e-mail.',
                'price' => 49.90,
                'cycle' => 'monthly',
                'trial_days' => 7,
                'grace_days' => 3,
                'gateway' => 'mercadopago',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 2,
                'name' => 'Plano Profissional',
                'description' => 'Para negocios em crescimento. Envios ilimitados, chatbot e relatorios.',
                'price' => 97.90,
                'cycle' => 'monthly',
                'trial_days' => 7,
                'grace_days' => 5,
                'gateway' => 'mercadopago',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 3,
                'name' => 'Plano VIP Mentoria',
                'description' => 'Mentoria trimestral com suporte prioritario e configuracao assistida.',
                'price' => 497.00,
                'cycle' => 'quarterly',
                'trial_days' => 0,
                'grace_days' => 7,
                'gateway' => 'efipro',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 4,
                'name' => 'Plano Anual Corporate',
                'description' => 'Plano anual completo para empresas. API dedicada e SLA garantido.',
                'price' => 997.00,
                'cycle' => 'annual',
                'trial_days' => 0,
                'grace_days' => 15,
                'gateway' => 'mercadopago',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        // 5 Assinaturas de exemplo
        DB::table('subscriptions')->insert([
            [
                'subscription_plan_id' => 2,
                'customer_name' => 'Marcelo Silva',
                'customer_email' => 'marcelo@exemplo.com',
                'customer_phone' => '11999990001',
                'price' => 97.90,
                'status' => 'active',
                'started_at' => '2025-10-01',
                'next_billing' => '2026-07-01',
                'canceled_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'subscription_plan_id' => 4,
                'customer_name' => 'KDK Host',
                'customer_email' => 'contato@kdkhost.com',
                'customer_phone' => '11999990002',
                'price' => 997.00,
                'status' => 'active',
                'started_at' => '2026-01-15',
                'next_billing' => '2027-01-15',
                'canceled_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'subscription_plan_id' => 1,
                'customer_name' => 'Roberto Carlos',
                'customer_email' => 'roberto@exemplo.com',
                'customer_phone' => '21999990003',
                'price' => 49.90,
                'status' => 'pending',
                'started_at' => '2026-04-10',
                'next_billing' => '2026-07-10',
                'canceled_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'subscription_plan_id' => 3,
                'customer_name' => 'Ana Clara',
                'customer_email' => 'ana@exemplo.com',
                'customer_phone' => '31999990004',
                'price' => 497.00,
                'status' => 'active',
                'started_at' => '2025-12-01',
                'next_billing' => '2026-09-01',
                'canceled_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'subscription_plan_id' => 1,
                'customer_name' => 'Juliana Souza',
                'customer_email' => 'juliana@exemplo.com',
                'customer_phone' => '41999990005',
                'price' => 49.90,
                'status' => 'canceled',
                'started_at' => '2026-02-20',
                'next_billing' => null,
                'canceled_at' => '2026-05-20',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('subscriptions')->truncate();
        DB::table('subscription_plans')->truncate();
    }
};
