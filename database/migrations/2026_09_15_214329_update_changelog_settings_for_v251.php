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
            if (!isset($val['changelog']['releases'])) {
                $val['changelog']['releases'] = [];
            }
            
            // Remove the 'latest' label from the old latest version
            foreach ($val['changelog']['releases'] as &$release) {
                if (isset($release['label']) && $release['label'] === 'latest') {
                    unset($release['label']);
                    unset($release['labelColor']);
                }
            }
            
            // Unshift the new version to the beginning of the array
            $newRelease = [
                'version' => '2.5.1',
                'date' => '15 Setembro, 2026',
                'label' => 'latest',
                'labelColor' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                'entries' => [
                    [ 'type' => 'new', 'text' => 'Reorganização completa da barra lateral (Sidebar) do painel Superadmin e do painel do Cliente (Tenant) em grupos lógicos.' ],
                    [ 'type' => 'improvement', 'text' => 'Monitoramento de Webhooks: Adicionado tratamento defensivo na leitura dos registros recentes, evitando telas de erro por falhas rápidas no banco de dados.' ],
                    [ 'type' => 'fix', 'text' => 'Edição de Cliente no Tenant: Correção de bug no Javascript do modal que impedia a visualização correta dos dados.' ]
                ],
            ];
            
            array_unshift($val['changelog']['releases'], $newRelease);
            
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
