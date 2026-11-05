<?php

namespace App\Modules\Tenancy\Services;

use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TenantInvoicePaymentService
{
    /**
     * Marca a fatura do tenant como paga e ativa o plano.
     */
    public function markInvoicePaid(TenantInvoice $invoice, string $transactionId): void
    {
        DB::transaction(function () use ($invoice, $transactionId) {
            // Atualizar status da fatura
            $invoice->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            // Buscar o tenant
            $tenant = Tenant::with('plan')->find($invoice->tenant_id);
            if (! $tenant) {
                return;
            }

            // Verificar se é uma fatura de assinatura de plano
            $planId = $invoice->meta['plan_id'] ?? null;
            if ($planId) {
                // Buscar o plano correto da fatura
                $plan = \App\Modules\Tenancy\Models\Plan::find($planId);
                if (! $plan) {
                    return;
                }

                $billingCycle = $plan->billing_cycle ?? 'monthly';
                $cycleMonths = $this->billingCycleMonths($billingCycle);

                $tenant->update([
                    'plan_id' => $planId,
                    'status' => 'active',
                    'trial_ends_at' => null,
                    'current_period_start' => now(),
                    'current_period_end' => now()->addMonths($cycleMonths),
                ]);

                try {
                    $dispatcher = app(\App\Modules\Notifications\Services\NotificationDispatcher::class);
                    $formattedVal = number_format($invoice->amount, 2, ',', '.');

                    // Notifica Administradores
                    $dispatcher->send([
                        'channels'   => ['in_app', 'email'],
                        'group'      => 'subscription',
                        'type'       => 'new_subscription',
                        'title'      => 'Nova Assinatura / Renovação',
                        'message'    => "O tenant {$tenant->company_name} ativou o plano {$plan->name} (Fatura {$invoice->number} - R$ {$formattedVal}).",
                        'url'        => route('admin.tenants.detail', $tenant->id),
                        'icon'       => 'bi-stars',
                        'color'      => 'primary',
                        'recipients' => ['all_admins' => true],
                    ]);

                    // Notifica o Tenant
                    $dispatcher->send([
                        'channels'   => ['in_app', 'email'],
                        'group'      => 'subscription',
                        'type'       => 'new_subscription',
                        'title'      => 'Assinatura Ativada!',
                        'message'    => "Seu plano {$plan->name} foi ativado com sucesso até " . now()->addMonths($cycleMonths)->format('d/m/Y') . ".",
                        'url'        => route('tenant.invoices'),
                        'icon'       => 'bi-stars',
                        'color'      => 'success',
                        'recipients' => ['tenant_id' => $tenant->id, 'user_id' => $tenant->user_id],
                    ]);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('[Tenant Subscription] Falha ao notificar assinatura: ' . $e->getMessage());
                }
            }
        });

        // Gera comissão para o afiliado que indicou este tenant (se houver)
        try {
            app(\App\Modules\Affiliates\Services\AffiliateService::class)->generateCommission($invoice);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[Tenant Billing] Falha ao processar comissão de afiliado: ' . $e->getMessage());
        }

        // Cancela cobranças pendentes ou em análise no Mercado Pago para esta fatura
        try {
            app(\App\Modules\Gateways\MercadoPago\Services\MercadoPagoGateway::class)
                ->cancelPendingChargesForInvoice($invoice);
        } catch (\Throwable) {}
    }

    /**
     * Retorna o número de meses baseado no ciclo de faturamento.
     */
    protected function billingCycleMonths(string $cycle): int
    {
        return match ($cycle) {
            'monthly' => 1,
            'quarterly' => 3,
            'semiannual' => 6,
            'annual' => 12,
            default => 1,
        };
    }
}
