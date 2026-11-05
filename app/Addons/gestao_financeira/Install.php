<?php

namespace App\Addons\gestao_financeira;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class Install
{
    /**
     * Executado na primeira instalação do addon.
     */
    public static function install(array $addon): void
    {
        self::runMigrations();

        if (Schema::hasTable('addon_gestao_financeira_categories')) {
            $defaultCategories = [
                ['name' => 'Vendas', 'type' => 'receita', 'color' => '#10B981'],
                ['name' => 'Assinaturas', 'type' => 'receita', 'color' => '#3B82F6'],
                ['name' => 'Prestação de Serviços', 'type' => 'receita', 'color' => '#8B5CF6'],
                ['name' => 'Outras Receitas', 'type' => 'receita', 'color' => '#6B7280'],
                ['name' => 'Marketing e Anúncios', 'type' => 'despesa', 'color' => '#EF4444'],
                ['name' => 'Infraestrutura e Hospedagem', 'type' => 'despesa', 'color' => '#F59E0B'],
                ['name' => 'Aluguel e Contas', 'type' => 'despesa', 'color' => '#EC4899'],
                ['name' => 'Folha de Pagamento', 'type' => 'despesa', 'color' => '#06B6D4'],
                ['name' => 'Impostos', 'type' => 'despesa', 'color' => '#6366F1'],
                ['name' => 'Outros Custos', 'type' => 'despesa', 'color' => '#9CA3AF'],
            ];

            foreach ($defaultCategories as $category) {
                if (DB::table('addon_gestao_financeira_categories')->where('name', $category['name'])->count() === 0) {
                    DB::table('addon_gestao_financeira_categories')->insert(array_merge($category, [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]));
                }
            }
        }

        if (Schema::hasTable('addon_gestao_financeira_accounts')) {
            $defaultAccounts = [
                ['name' => 'Caixa Pix', 'type' => 'digital', 'initial_balance' => 0.00, 'current_balance' => 0.00],
                ['name' => 'Conta Corrente Bancária', 'type' => 'banco', 'initial_balance' => 0.00, 'current_balance' => 0.00],
                ['name' => 'Dinheiro Físico', 'type' => 'dinheiro', 'initial_balance' => 0.00, 'current_balance' => 0.00],
            ];

            foreach ($defaultAccounts as $account) {
                if (DB::table('addon_gestao_financeira_accounts')->where('name', $account['name'])->count() === 0) {
                    DB::table('addon_gestao_financeira_accounts')->insert(array_merge($account, [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]));
                }
            }
        }
    }

    protected static function runMigrations(): void
    {
        $migrationsPath = realpath(__DIR__.'/database/migrations');
        if (! $migrationsPath || ! is_dir($migrationsPath)) {
            return;
        }

        $basePath = str_replace('\\', '/', base_path());
        $absolutePath = str_replace('\\', '/', $migrationsPath);
        $relativePath = ltrim(str_replace($basePath, '', $absolutePath), '/');

        try {
            Artisan::call('migrate', ['--path' => $relativePath, '--force' => true]);
        } catch (\Throwable $e) {
            Log::warning("[GestaoFinanceira] Falha ao rodar migrations de instalação (relativo): {$e->getMessage()}");
        }

        if (! Schema::hasTable('addon_gestao_financeira_accounts') && ! Schema::hasTable('addon_gestao_financeira_categories') && ! Schema::hasTable('addon_gestao_financeira_transactions')) {
            try {
                Artisan::call('migrate', ['--path' => $absolutePath, '--force' => true]);
            } catch (\Throwable $e) {
                Log::warning("[GestaoFinanceira] Falha ao rodar migrations de instalação (absoluto): {$e->getMessage()}");
            }
        }
    }

    /**
     * Executado ao desinstalar o addon.
     */
    public static function uninstall(array $addon): void
    {
        Schema::dropIfExists('addon_gestao_financeira_transactions');
        Schema::dropIfExists('addon_gestao_financeira_categories');
        Schema::dropIfExists('addon_gestao_financeira_accounts');
    }

    /**
     * Executado ao ativar o addon no painel.
     */
    public static function enable(array $addon): void
    {
        self::runMigrations();
    }

    /**
     * Executado ao desativar o addon no painel.
     */
    public static function disable(array $addon): void
    {
        //
    }

    /**
     * Executado ao atualizar a versão do addon no painel.
     */
    public static function update(array $addon): void
    {
        self::runMigrations();
    }
}
