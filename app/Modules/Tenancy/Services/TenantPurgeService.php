<?php

namespace App\Modules\Tenancy\Services;

use App\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class TenantPurgeService
{
    /**
     * Verifica se o tenant/usuário possui registro nacional/brasileiro compatível.
     * Retorna false caso seja identificado como usuário estrangeiro / não-brasileiro.
     */
    public function isBrazilian(Tenant $tenant): bool
    {
        $user = $tenant->user;
        if (! $user) {
            $doc = preg_replace('/\D/', '', (string) ($tenant->document ?? ''));
            if ($doc !== '' && (strlen($doc) === 11 || strlen($doc) === 14)) {
                return true;
            }
            return false;
        }

        $phone = preg_replace('/\D/', '', (string) ($user->phone ?? ''));
        $rawPhone = trim((string) ($user->phone ?? ''));
        $doc = preg_replace('/\D/', '', (string) ($user->document ?? ''));

        // Se o telefone foi informado com prefixo internacional explícito (ex: +1, +44, +351, etc.)
        if (str_starts_with($rawPhone, '+')) {
            $digits = preg_replace('/\D/', '', $rawPhone);
            // Se começa com +55 e tem tamanho de celular/fixo BR (12 ou 13 dígitos), é BR
            if (str_starts_with($digits, '55') && (strlen($digits) === 12 || strlen($digits) === 13)) {
                return true;
            }
            // Qualquer outro '+' (ex: +1..., +44..., +351...) é estrangeiro!
            return false;
        }

        // Se tem telefone sem sinal de +, mas dígitos foram preenchidos
        if ($phone !== '') {
            // Se tem 12 ou 13 dígitos começando com 55: é BR
            if (str_starts_with($phone, '55') && (strlen($phone) === 12 || strlen($phone) === 13)) {
                return true;
            }

            // Se tem 10 ou 11 dígitos, verifica se os 2 primeiros dígitos formam um DDD brasileiro válido (11 a 99)
            if (strlen($phone) === 10 || strlen($phone) === 11) {
                $ddd = (int) substr($phone, 0, 2);
                if ($ddd >= 11 && $ddd <= 99) {
                    return true;
                }
            }

            // Se tem formato incompatível com plano de numeração nacional:
            return false;
        }

        // Se não tem telefone, verifica o documento CPF (11) ou CNPJ (14)
        if ($doc !== '') {
            if (strlen($doc) === 11 || strlen($doc) === 14) {
                return true;
            }
            return false;
        }

        // Sem telefone nem documento brasileiro
        return false;
    }

    /**
     * Retorna a lista de todos os tenants não-brasileiros.
     */
    public function findNonBrazilians()
    {
        return Tenant::with(['user', 'plan', 'instances'])->get()->filter(fn (Tenant $t) => ! $this->isBrazilian($t));
    }

    /**
     * Remove registros de uma tabela apenas se a tabela e a coluna existirem.
     * Evita erros tipo "Column not found 1054" e "Base table or view not found".
     */
    protected function deleteBy(string $table, string $column, mixed $value): int
    {
        try {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                if (is_array($value)) {
                    return ! empty($value) ? DB::table($table)->whereIn($column, $value)->delete() : 0;
                }
                return DB::table($table)->where($column, $value)->delete();
            }
        } catch (\Throwable $e) {
            Log::warning("Falha ao deletar em {$table} por {$column}: " . $e->getMessage());
        }
        return 0;
    }

    /**
     * Expurga totalmente um tenant e TODOS os dados vinculados a ele no sistema.
     */
    public function purgeTenant(Tenant $tenant): array
    {
        $tenant->loadMissing('instances');

        Schema::disableForeignKeyConstraints();

        try {
            return DB::transaction(function () use ($tenant) {
                $tenantId = $tenant->id;
                $userId   = $tenant->user_id;
                $deletedCounts = [];

                // 1. Localizar IDs de instâncias e faturas para deleções em cascata seguras
                $instanceIds = [];
                if (Schema::hasTable('tenant_instances') && Schema::hasColumn('tenant_instances', 'tenant_id')) {
                    $instanceIds = DB::table('tenant_instances')->where('tenant_id', $tenantId)->pluck('id')->toArray();
                }

                $invoiceIds = [];
                if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'tenant_id')) {
                    $invoiceIds = DB::table('invoices')->where('tenant_id', $tenantId)->pluck('id')->toArray();
                }

                $dispatchIds = [];
                if (! empty($invoiceIds) && Schema::hasTable('billing_dispatches') && Schema::hasColumn('billing_dispatches', 'invoice_id')) {
                    $dispatchIds = DB::table('billing_dispatches')->whereIn('invoice_id', $invoiceIds)->pluck('id')->toArray();
                }

                // 2. WhatsApp Messages
                if (Schema::hasTable('whatsapp_messages')) {
                    $q = DB::table('whatsapp_messages');
                    $hasCond = false;
                    if (! empty($dispatchIds) && Schema::hasColumn('whatsapp_messages', 'dispatch_id')) {
                        $q->whereIn('dispatch_id', $dispatchIds);
                        $hasCond = true;
                    }
                    if (! empty($instanceIds) && Schema::hasColumn('whatsapp_messages', 'tenant_instance_id')) {
                        $hasCond ? $q->orWhereIn('tenant_instance_id', $instanceIds) : $q->whereIn('tenant_instance_id', $instanceIds);
                        $hasCond = true;
                    }
                    if (Schema::hasColumn('whatsapp_messages', 'tenant_id')) {
                        $hasCond ? $q->orWhere('tenant_id', $tenantId) : $q->where('tenant_id', $tenantId);
                        $hasCond = true;
                    }
                    $deletedCounts['whatsapp_messages'] = $hasCond ? $q->delete() : 0;
                }

                // 3. Email Messages
                if (Schema::hasTable('email_messages')) {
                    $q = DB::table('email_messages');
                    $hasCond = false;
                    if (Schema::hasColumn('email_messages', 'tenant_id')) {
                        $q->where('tenant_id', $tenantId);
                        $hasCond = true;
                    }
                    if (! empty($dispatchIds) && Schema::hasColumn('email_messages', 'dispatch_id')) {
                        $hasCond ? $q->orWhereIn('dispatch_id', $dispatchIds) : $q->whereIn('dispatch_id', $dispatchIds);
                        $hasCond = true;
                    }
                    $deletedCounts['email_messages'] = $hasCond ? $q->delete() : 0;
                }

                // 4. Webhooks e Logs de Mensageria / Instância
                $deletedCounts['payment_webhooks']               = $this->deleteBy('payment_webhooks', 'invoice_id', $invoiceIds) + $this->deleteBy('payment_webhooks', 'tenant_id', $tenantId);
                $deletedCounts['billing_dispatches']             = $this->deleteBy('billing_dispatches', 'invoice_id', $invoiceIds) + $this->deleteBy('billing_dispatches', 'tenant_id', $tenantId);
                $deletedCounts['whatsapp_customer_limits']       = $this->deleteBy('whatsapp_customer_limits', 'tenant_id', $tenantId);
                $deletedCounts['whatsapp_customer_service_logs'] = $this->deleteBy('whatsapp_customer_service_logs', 'tenant_id', $tenantId);
                $deletedCounts['whatsapp_sync_logs']             = $this->deleteBy('whatsapp_sync_logs', 'tenant_id', $tenantId);
                $deletedCounts['whatsapp_webhook_logs']          = $this->deleteBy('whatsapp_webhook_logs', 'tenant_id', $tenantId);
                $deletedCounts['whatsapp_instance_reputations']  = $this->deleteBy('whatsapp_instance_reputations', 'tenant_id', $tenantId);
                $deletedCounts['whatsapp_instance_quarantines']  = $this->deleteBy('whatsapp_instance_quarantines', 'tenant_id', $tenantId);
                $deletedCounts['whatsapp_delivery_metrics']      = $this->deleteBy('whatsapp_delivery_metrics', 'tenant_id', $tenantId);
                $deletedCounts['whatsapp_recipient_scores']      = $this->deleteBy('whatsapp_recipient_scores', 'tenant_id', $tenantId);
                $deletedCounts['whatsapp_recipient_preferences'] = $this->deleteBy('whatsapp_recipient_preferences', 'tenant_id', $tenantId);
                $deletedCounts['whatsapp_safety_reputation']     = $this->deleteBy('whatsapp_safety_reputation', 'tenant_id', $tenantId);
                $deletedCounts['whatsapp_cooldowns']             = $this->deleteBy('whatsapp_cooldowns', 'tenant_id', $tenantId);
                $deletedCounts['whatsapp_instances']             = $this->deleteBy('whatsapp_instances', 'tenant_id', $tenantId);

                // 5. Invoices & Customers
                $deletedCounts['invoices']  = $this->deleteBy('invoices', 'tenant_id', $tenantId);
                $deletedCounts['customers'] = $this->deleteBy('customers', 'tenant_id', $tenantId);

                // 6. Instâncias WhatsApp do Tenant (exclusão remota tolerante)
                foreach ($tenant->instances as $instance) {
                    try {
                        if (class_exists(InstanceManager::class)) {
                            app(InstanceManager::class)->deleteInstance($instance);
                        }
                    } catch (\Throwable $e) {
                        Log::warning("Tentativa de exclusão remota da instância {$instance->id} ignorada: " . $e->getMessage());
                    }
                }
                $deletedCounts['tenant_instances'] = $this->deleteBy('tenant_instances', 'tenant_id', $tenantId);

                // 7. Gateways e Faturamento SaaS do Tenant
                $deletedCounts['tenant_gateways']        = $this->deleteBy('tenant_gateways', 'tenant_id', $tenantId);
                $deletedCounts['tenant_billing_config']  = $this->deleteBy('tenant_billing_config', 'tenant_id', $tenantId);
                $deletedCounts['tenant_billing_configs'] = $this->deleteBy('tenant_billing_configs', 'tenant_id', $tenantId);
                $deletedCounts['tenant_invoices']        = $this->deleteBy('tenant_invoices', 'tenant_id', $tenantId);
                $deletedCounts['gateway_webhooks']       = $this->deleteBy('gateway_webhooks', 'tenant_id', $tenantId);

                // 8. Pacotes, Assinaturas e Addons
                $deletedCounts['customer_package_installments']  = $this->deleteBy('customer_package_installments', 'tenant_id', $tenantId);
                $deletedCounts['customer_package_subscriptions'] = $this->deleteBy('customer_package_subscriptions', 'tenant_id', $tenantId);
                $deletedCounts['tenant_packages']                = $this->deleteBy('tenant_packages', 'tenant_id', $tenantId);
                $deletedCounts['tenant_addons']                  = $this->deleteBy('tenant_addons', 'tenant_id', $tenantId);
                $deletedCounts['tenant_template_preferences']    = $this->deleteBy('tenant_template_preferences', 'tenant_id', $tenantId);

                // 9. Templates WhatsApp do tenant
                $deletedCounts['whatsapp_templates'] = $this->deleteBy('whatsapp_templates', 'tenant_id', $tenantId);

                // 10. WHMCS Integration
                $deletedCounts['whmcs_services']       = $this->deleteBy('whmcs_services', 'tenant_id', $tenantId);
                $deletedCounts['whmcs_connections']    = $this->deleteBy('whmcs_connections', 'tenant_id', $tenantId);
                $deletedCounts['whmcs_webhook_events'] = $this->deleteBy('whmcs_webhook_events', 'tenant_id', $tenantId);
                $deletedCounts['whmcs_sync_log']       = $this->deleteBy('whmcs_sync_log', 'tenant_id', $tenantId);

                // 11. Marketing (se tiver tenant_id)
                $deletedCounts['marketing_leads']     = $this->deleteBy('marketing_leads', 'tenant_id', $tenantId);
                $deletedCounts['marketing_campaigns'] = $this->deleteBy('marketing_campaigns', 'tenant_id', $tenantId);

                // 12. Subscriptions
                $deletedCounts['subscriptions']      = $this->deleteBy('subscriptions', 'tenant_id', $tenantId);
                $deletedCounts['subscription_plans'] = $this->deleteBy('subscription_plans', 'tenant_id', $tenantId);

                // 13. Contratos e Email Billing
                $deletedCounts['contracts']             = $this->deleteBy('contracts', 'tenant_id', $tenantId);
                $deletedCounts['contract_templates']    = $this->deleteBy('contract_templates', 'tenant_id', $tenantId);
                $deletedCounts['contract_events']       = $this->deleteBy('contract_events', 'tenant_id', $tenantId);
                $deletedCounts['tenant_email_configs']  = $this->deleteBy('tenant_email_configs', 'tenant_id', $tenantId);
                $deletedCounts['email_templates']       = $this->deleteBy('email_templates', 'tenant_id', $tenantId);
                $deletedCounts['chatbot_interactions']  = $this->deleteBy('chatbot_interactions', 'tenant_id', $tenantId);

                // 14. Servidores cPanel & aaPanel
                $deletedCounts['cpanel_actions_log']     = $this->deleteBy('cpanel_actions_log', 'tenant_id', $tenantId);
                $deletedCounts['cpanel_servers']         = $this->deleteBy('cpanel_servers', 'tenant_id', $tenantId);
                $deletedCounts['aapanel_operation_logs'] = $this->deleteBy('aapanel_operation_logs', 'tenant_id', $tenantId);
                $deletedCounts['aapanel_accounts']       = $this->deleteBy('aapanel_accounts', 'tenant_id', $tenantId);
                $deletedCounts['aapanel_plans']          = $this->deleteBy('aapanel_plans', 'tenant_id', $tenantId);
                $deletedCounts['aapanel_servers']        = $this->deleteBy('aapanel_servers', 'tenant_id', $tenantId);

                // 15. Feriados e Auditoria
                $deletedCounts['holidays']             = $this->deleteBy('holidays', 'tenant_id', $tenantId);
                $deletedCounts['security_audit_logs']  = $this->deleteBy('security_audit_logs', 'tenant_id', $tenantId);

                // 16. Excluir o registro do Tenant
                $tenantName = $tenant->company_name ?? $tenant->name ?? "Tenant #{$tenantId}";
                $tenant->delete();
                $deletedCounts['tenants'] = 1;

                // 17. Excluir dados vinculados ao User e o próprio usuário
                if ($userId) {
                    $this->deleteBy('security_audit_logs', 'user_id', $userId);
                    $this->deleteBy('subscriptions', 'user_id', $userId);
                    $this->deleteBy('affiliates', 'user_id', $userId);
                    $this->deleteBy('pwa_subscriptions', 'user_id', $userId);
                    $this->deleteBy('sessions', 'user_id', $userId);

                    if (Schema::hasTable('personal_access_tokens') && Schema::hasColumn('personal_access_tokens', 'tokenable_id')) {
                        DB::table('personal_access_tokens')
                            ->where('tokenable_type', 'App\\Models\\User')
                            ->where('tokenable_id', $userId)
                            ->delete();
                    }

                    $user = User::find($userId);
                    if ($user) {
                        if (method_exists($user, 'tokens')) {
                            $user->tokens()->delete();
                        }
                        $user->delete();
                        $deletedCounts['users'] = 1;
                    }
                }

                Log::info("Tenant {$tenantId} ({$tenantName}) expurgado com sucesso.", $deletedCounts);

                return [
                    'ok'            => true,
                    'tenant_id'     => $tenantId,
                    'tenant_name'   => $tenantName,
                    'deleted_counts'=> $deletedCounts,
                ];
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    /**
     * Remove todos os tenants identificados como não-brasileiros.
     */
    public function purgeNonBrazilians(): array
    {
        $nonBrazilians = $this->findNonBrazilians();
        $total = $nonBrazilians->count();
        $purged = [];

        foreach ($nonBrazilians as $tenant) {
            $purged[] = $this->purgeTenant($tenant);
        }

        return [
            'ok'      => true,
            'total'   => $total,
            'purged'  => $purged,
            'message' => $total > 0
                ? "Foram removidos e expurgados {$total} tenant(s)/usuário(s) não-brasileiros com sucesso."
                : 'Nenhum usuário ou tenant não-brasileiro encontrado para remoção.',
        ];
    }
}
