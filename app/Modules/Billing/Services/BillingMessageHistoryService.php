<?php

namespace App\Modules\Billing\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BillingMessageHistoryService
{
    public function retentionDays(): int
    {
        return max(1, (int) config('billing.message_history.retention_days', 14));
    }

    public function cleanup(?int $days = null): array
    {
        $cutoff = now('America/Sao_Paulo')->subDays($days ?: $this->retentionDays());

        $deleted = [
            'whatsapp' => 0,
            'email' => 0,
        ];

        if (Schema::hasTable('whatsapp_messages')) {
            $deleted['whatsapp'] = DB::table('whatsapp_messages')
                ->whereNotNull('dispatch_id')
                ->where('created_at', '<', $cutoff)
                ->delete();
        }

        if (Schema::hasTable('email_messages')) {
            $deleted['email'] = DB::table('email_messages')
                ->whereNotNull('dispatch_id')
                ->where('created_at', '<', $cutoff)
                ->delete();
        }

        return $deleted + [
            'total' => $deleted['whatsapp'] + $deleted['email'],
            'cutoff' => $cutoff->toDateTimeString(),
        ];
    }

    public function baseQuery(?string $channel = null)
    {
        $queries = [];
        $wantWhatsApp = $channel === null || $channel === '' || $channel === 'whatsapp';
        $wantEmail    = $channel === null || $channel === '' || $channel === 'email';

        $isSqlite = DB::connection()->getDriverName() === 'sqlite';
        $toCol = $isSqlite ? 'msg."to"' : 'msg.to';

        // 1. Disparos de cobrança WhatsApp da régua (billing_dispatches) - Enviados, Agendados, Falhas ou Cancelados
        if ($wantWhatsApp && Schema::hasTable('billing_dispatches') && Schema::hasTable('invoices') && Schema::hasTable('customers')) {
            $queries[] = DB::table('billing_dispatches as dispatch')
                ->join('invoices as invoice', 'invoice.id', '=', 'dispatch.invoice_id')
                ->join('customers as customer', 'customer.id', '=', 'invoice.customer_id')
                ->leftJoin('whatsapp_messages as msg', 'msg.dispatch_id', '=', 'dispatch.id')
                ->leftJoin('tenant_instances as instance', 'instance.id', '=', 'msg.tenant_instance_id')
                ->whereIn('dispatch.channel', ['whatsapp', 'both'])
                ->select([
                    DB::raw("'whatsapp' as channel"),
                    DB::raw('COALESCE(msg.id, dispatch.id) as source_id'),
                    'dispatch.id as dispatch_id',
                    'invoice.id as invoice_id',
                    'invoice.number as invoice_number',
                    'invoice.description as invoice_description',
                    'invoice.amount as invoice_amount',
                    'invoice.due_date as invoice_due_date',
                    'invoice.status as invoice_status',
                    'customer.id as customer_id',
                    'customer.name as customer_name',
                    'customer.whatsapp as customer_whatsapp',
                    'customer.email as customer_email',
                    DB::raw("COALESCE({$toCol}, customer.whatsapp) as recipient"),
                    DB::raw('NULL as subject'),
                    DB::raw("COALESCE(msg.message, dispatch.last_error, 'Cobrança de fatura') as body"),
                    DB::raw('COALESCE(msg.status, dispatch.status) as status'),
                    DB::raw("COALESCE(msg.meta_message_id, msg.external_id, '') as provider_id"),
                    DB::raw("COALESCE(msg.media_type, 'text') as media_type"),
                    'dispatch.schedule_offset',
                    'dispatch.channel as dispatch_channel',
                    'dispatch.last_error',
                    'dispatch.attempts',
                    'dispatch.scheduled_at',
                    DB::raw('COALESCE(msg.sent_at, dispatch.sent_at) as sent_at'),
                    'msg.delivered_at',
                    'msg.read_at',
                    DB::raw('COALESCE(msg.created_at, dispatch.created_at) as created_at'),
                    'msg.response',
                    'msg.meta',
                    'invoice.meta as invoice_meta',
                    'instance.display_name as instance_name',
                    DB::raw('COALESCE(invoice.tenant_id, customer.tenant_id, instance.tenant_id) as tenant_id'),
                ]);
        }

        // 2. Mensagens diretas de WhatsApp para clientes sem dispatch_id (recibos, confirmacoes de pagamento, manuais)
        if ($wantWhatsApp && Schema::hasTable('whatsapp_messages') && Schema::hasTable('customers')) {
            $queries[] = DB::table('whatsapp_messages as msg')
                ->leftJoin('customers as customer', function ($join) {
                    $join->on('customer.whatsapp', '=', 'msg.to');
                })
                ->leftJoin('tenant_instances as instance', 'instance.id', '=', 'msg.tenant_instance_id')
                ->whereNull('msg.dispatch_id')
                ->where('msg.direction', 'out')
                ->select([
                    DB::raw("'whatsapp' as channel"),
                    'msg.id as source_id',
                    DB::raw('NULL as dispatch_id'),
                    DB::raw('NULL as invoice_id'),
                    DB::raw('NULL as invoice_number'),
                    DB::raw('NULL as invoice_description'),
                    DB::raw('NULL as invoice_amount'),
                    DB::raw('NULL as invoice_due_date'),
                    DB::raw('NULL as invoice_status'),
                    'customer.id as customer_id',
                    'customer.name as customer_name',
                    'customer.whatsapp as customer_whatsapp',
                    'customer.email as customer_email',
                    'msg.to as recipient',
                    DB::raw('NULL as subject'),
                    'msg.message as body',
                    'msg.status',
                    DB::raw("COALESCE(msg.meta_message_id, msg.external_id, '') as provider_id"),
                    DB::raw("COALESCE(msg.media_type, 'text') as media_type"),
                    DB::raw("'manual' as schedule_offset"),
                    DB::raw("'whatsapp' as dispatch_channel"),
                    DB::raw('NULL as last_error'),
                    DB::raw('1 as attempts'),
                    'msg.created_at as scheduled_at',
                    'msg.sent_at',
                    'msg.delivered_at',
                    'msg.read_at',
                    'msg.created_at',
                    'msg.response',
                    'msg.meta',
                    DB::raw('NULL as invoice_meta'),
                    'instance.display_name as instance_name',
                    DB::raw('COALESCE(instance.tenant_id, customer.tenant_id) as tenant_id'),
                ]);
        }

        // 3. E-mails de cobrança (email_messages com ou sem dispatch)
        if ($wantEmail && Schema::hasTable('email_messages')) {
            $queries[] = DB::table('email_messages as msg')
                ->leftJoin('billing_dispatches as dispatch', 'dispatch.id', '=', 'msg.dispatch_id')
                ->leftJoin('invoices as invoice', 'invoice.id', '=', 'dispatch.invoice_id')
                ->leftJoin('customers as customer', function ($join) {
                    $join->on('customer.id', '=', 'invoice.customer_id')
                         ->orOn('customer.email', '=', 'msg.to');
                })
                ->select([
                    DB::raw("'email' as channel"),
                    'msg.id as source_id',
                    'msg.dispatch_id',
                    'invoice.id as invoice_id',
                    'invoice.number as invoice_number',
                    'invoice.description as invoice_description',
                    'invoice.amount as invoice_amount',
                    'invoice.due_date as invoice_due_date',
                    'invoice.status as invoice_status',
                    'customer.id as customer_id',
                    'customer.name as customer_name',
                    'customer.whatsapp as customer_whatsapp',
                    'customer.email as customer_email',
                    'msg.to as recipient',
                    'msg.subject',
                    'msg.body_html as body',
                    'msg.status',
                    'msg.message_id as provider_id',
                    DB::raw("'email' as media_type"),
                    DB::raw("COALESCE(dispatch.schedule_offset, 'manual') as schedule_offset"),
                    DB::raw("COALESCE(dispatch.channel, 'email') as dispatch_channel"),
                    'dispatch.last_error',
                    DB::raw('COALESCE(dispatch.attempts, 1) as attempts'),
                    DB::raw('COALESCE(dispatch.scheduled_at, msg.created_at) as scheduled_at'),
                    'msg.sent_at',
                    DB::raw('NULL as delivered_at'),
                    DB::raw('msg.opened_at as read_at'),
                    'msg.created_at',
                    DB::raw('msg.error as response'),
                    'msg.headers as meta',
                    'invoice.meta as invoice_meta',
                    DB::raw('NULL as instance_name'),
                    DB::raw('COALESCE(msg.tenant_id, invoice.tenant_id, customer.tenant_id) as tenant_id'),
                ]);
        }

        if ($queries === []) {
            return DB::query()->fromSub(
                DB::table(DB::raw('(select 1 as empty_source) as empty_source'))->whereRaw('1 = 0'),
                'history'
            );
        }

        $union = array_shift($queries);
        foreach ($queries as $query) {
            $union->unionAll($query);
        }

        return DB::query()->fromSub($union, 'history');
    }
}
