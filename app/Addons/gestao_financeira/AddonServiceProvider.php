<?php

namespace App\Addons\gestao_financeira;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AddonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Route::middleware(['web', 'admin', '2fa', 'module.permission:gestao-financeira.view'])
            ->prefix('admin/gestao-financeira')
            ->name('admin.gestao-financeira.')
            ->group(function () {
                require __DIR__.'/routes/web.php';
            });

        // Rotas de tenant (acesso via painel do cliente)
        Route::middleware(['web', 'auth', '2fa'])
            ->prefix('painel/gestao-financeira')
            ->name('tenant.gestao-financeira.')
            ->group(function () {
                require __DIR__.'/routes/web.php';
            });

        $this->loadViewsFrom(__DIR__.'/Resources/views', 'gestao-financeira');
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        // Rota de Webhook (Sem middleware web/csrf, aceita GET para healthcheck e POST para eventos)
        Route::middleware(['api'])
            ->match(['get', 'post', 'head'], 'api/gestao-financeira/open-finance-webhook', [\App\Addons\gestao_financeira\Http\Controllers\OpenFinanceWebhookController::class, 'handle'])
            ->name('gestao_financeira.open_finance_webhook');

        if (class_exists(\App\Services\CronManager::class)) {
            try {
                \App\Services\CronManager::register(
                    name: 'gestao-financeira.daily-report',
                    callback: fn () => $this->sendDailyFinancialReport(),
                    intervalMinutes: 1440,
                    description: 'Envia resumo financeiro diário por WhatsApp para o administrador'
                );
            } catch (\Throwable) {
                //
            }
        }
    }

    protected function sendDailyFinancialReport(): void
    {
        try {
            $settings = null;
            if (class_exists(\App\Models\Addon::class)) {
                $addon = \App\Models\Addon::where('slug', 'gestao-financeira')->first();
                $settings = $addon ? json_decode($addon->settings, true) : null;
            }

            if (! $settings || ! ($settings['alerts_enabled'] ?? false) || empty($settings['alerts_whatsapp_number'])) {
                return;
            }

            $today = now()->format('Y-m-d');

            $receitas = \DB::table('addon_gestao_financeira_transactions')
                ->where('type', 'receita')
                ->whereDate('payment_date', $today)
                ->sum('amount');

            $despesas = \DB::table('addon_gestao_financeira_transactions')
                ->where('type', 'despesa')
                ->whereDate('payment_date', $today)
                ->sum('amount');

            $saldo = $receitas - $despesas;

            $mensagem = "Resumo financeiro diário\n\n";
            $mensagem .= 'Data: '.now()->format('d/m/Y')."\n\n";
            $mensagem .= 'Receitas recebidas: R$ '.number_format($receitas, 2, ',', '.')."\n";
            $mensagem .= 'Despesas pagas: R$ '.number_format($despesas, 2, ',', '.')."\n";
            $mensagem .= 'Saldo do dia: R$ '.number_format($saldo, 2, ',', '.')."\n\n";
            $mensagem .= 'Bom trabalho.';

            if (class_exists(\App\Services\WhatsappService::class)) {
                \App\Services\WhatsappService::sendMessage($settings['alerts_whatsapp_number'], $mensagem);
            }
        } catch (\Throwable $e) {
            \Log::error('Erro no envio do relatório financeiro via cron: '.$e->getMessage());
        }
    }
}
