<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $setting = DB::table('settings')->where('key', 'site.pages')->first();
        if ($setting) {
            $val = json_decode($setting->value, true);
            if (!isset($val['changelog'])) {
                $val['changelog'] = [];
            }
            $val['changelog']['releases'] = [
                [
                    'version' => '2.5.0',
                    'date' => '14 Setembro, 2026',
                    'label' => 'latest',
                    'labelColor' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                    'entries' => [
                        [ 'type' => 'new', 'text' => 'Painel do lojista: Nova seção de Segurança Anti-Ban para gerenciar fluxo de mensagens.' ],
                        [ 'type' => 'improvement', 'text' => 'Pacing Inteligente: Motor de cobranças agora respeita intervalos de 3 a 5 minutos, protegendo contas contra banimento do WhatsApp.' ],
                        [ 'type' => 'improvement', 'text' => 'Extração Automática de PIX: Detecção avançada do código Copia e Cola em faturas do MercadoPago integradas ao WHMCS.' ],
                        [ 'type' => 'fix', 'text' => 'Preview de Templates: Correção da exibição do QR Code e PIX no simulador em tempo real.' ]
                    ],
                ],
                [
                    'version' => '2.4.0',
                    'date' => '19 Maio, 2026',
                    'entries' => [
                        [ 'type' => 'new', 'text' => 'Marketplace de Addons: instale extensoes em um clique diretamente do painel.' ],
                        [ 'type' => 'new', 'text' => 'Pagina de Integracoes com documentacao da API REST e exemplos de codigo.' ],
                        [ 'type' => 'fix', 'text' => 'Ajustes visuais e operacionais no frontend publico e no painel administrativo.' ]
                    ],
                ],
                [
                    'version' => '2.0.0',
                    'date' => '02 Maio, 2026',
                    'label' => 'major',
                    'labelColor' => 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20',
                    'entries' => [
                        [ 'type' => 'new', 'text' => 'Reescrita completa da plataforma com arquitetura modular Laravel 13.' ],
                        [ 'type' => 'new', 'text' => 'Multi-tenancy SaaS com planos, tenants e instancias Evolution por cliente.' ],
                        [ 'type' => 'new', 'text' => 'Envios automaticos de cobranca via WhatsApp com Evolution API.' ]
                    ],
                ]
            ];
            DB::table('settings')->where('key', 'site.pages')->update(['value' => json_encode($val)]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No down migration needed
    }
};
