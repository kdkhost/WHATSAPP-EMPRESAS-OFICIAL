<?php

namespace App\Modules\Billing\Services;

use App\Models\Invoice;

use App\Modules\EmailBilling\Models\EmailTemplate;
use App\Modules\EmailBilling\Services\EmailTemplateRenderer;
use App\Modules\EmailBilling\Services\SmtpMailer;
use App\Modules\Holidays\Services\HolidayService;
use App\Modules\InvoicePdf\Services\InvoicePdfService;
use App\Modules\Templates\Models\WhatsAppTemplate;
use App\Modules\Templates\Services\TemplateRenderer;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInstance;
use App\Modules\Tenancy\Services\InstanceManager;
use App\Modules\Tenancy\Services\TenantTemplateResolver;
use App\Modules\WhatsApp\Services\EvolutionClient;
use App\Modules\WhatsApp\Services\WhatsAppSafetyService;
use App\Modules\WhmcsIntegration\Models\WhmcsConnection;
use App\Modules\WhmcsIntegration\Models\WhmcsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Orquestrador de envios automaticos de cobranca.
 *
 * Suporta multi-canal:
 *  - whatsapp: envia mensagem (texto/audio/imagem) via Evolution
 *  - email: envia email via SMTP do tenant
 *  - both: envia em ambos canais
 *
 * Nao envia em fim de semana ou feriado (HolidayService).
 */
class BillingDispatcher
{
    public function __construct(
        protected EvolutionClient $evolution,
        protected TemplateRenderer $renderer,
        protected ?SmtpMailer $mailer = null,
        protected ?EmailTemplateRenderer $emailRenderer = null,
        protected ?HolidayService $holidays = null,
        protected ?TenantTemplateResolver $templateResolver = null,
        protected ?InstanceManager $instances = null,
        protected ?WhatsAppSafetyService $safety = null,
        protected ?InvoicePdfService $invoicePdf = null,
    ) {
        $this->mailer = $mailer ?? app(SmtpMailer::class);
        $this->emailRenderer = $emailRenderer ?? app(EmailTemplateRenderer::class);
        $this->holidays = $holidays ?? app(HolidayService::class);
        $this->templateResolver = $templateResolver ?? app(TenantTemplateResolver::class);
        $this->instances = $instances ?? app(InstanceManager::class);
        $this->safety = $safety ?? app(WhatsAppSafetyService::class);
        $this->invoicePdf = $invoicePdf ?? app(InvoicePdfService::class);
    }

    public function scheduleNewInvoices(): int
    {
        $count = 0;
        $emailEnabledTenants = DB::table('tenant_email_configs')
            ->where('enabled', true)
            ->pluck('tenant_id')
            ->flip();

        // Garante que todas as faturas pendentes que caiam em fim de semana ou feriado sejam movidas para o próximo dia útil
        Invoice::adjustPendingInvoicesToBusinessDays();

        $invoices = DB::table('invoices')
            ->leftJoin('customers', 'invoices.customer_id', '=', 'customers.id')
            ->whereIn('invoices.status', ['pending', 'sent', 'overdue'])
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('billing_dispatches')
                    ->whereColumn('billing_dispatches.invoice_id', 'invoices.id')
                    ->where('schedule_offset', '!=', 'manual');
            })
            ->select(
                'invoices.*',
                'customers.whatsapp as customer_whatsapp',
                'customers.phone as customer_phone',
                'customers.email as customer_email'
            )->get();

        // Filtra faturas que já constam como pagas no WHMCS (meta->whmcs_status = 'Paid')
        // para evitar agendamento indevido antes da sincronização local ser concluída
        $invoices = $invoices->filter(function ($inv) {
            $meta = is_string($inv->meta) ? (json_decode($inv->meta, true) ?: []) : ((array) ($inv->meta ?? []));
            $whmcsStatus = strtolower((string) ($meta['whmcs_status'] ?? ''));
            return $whmcsStatus !== 'paid' && $whmcsStatus !== 'cancelled' && $whmcsStatus !== 'refunded';
        });

        $defaultOffsets = [
            '-3d' => -3, '-1d' => -1, '0d' => 0, '+1d' => 1, '+3d' => 3, '+7d' => 7, '+15d' => 15,
        ];

        foreach ($invoices as $inv) {
            $due = Carbon::parse($inv->due_date)->setTimezone('America/Sao_Paulo')->startOfDay();
            $tenantId = $inv->tenant_id;
            $tenantCronTime = '09:00';
            $skipHolidays = true;
            $offsets = $defaultOffsets;

            if ($tenantId) {
                $bConfig = DB::table('tenant_billing_config')
                    ->where('tenant_id', $tenantId)
                    ->first(['cron_time', 'skip_weekends_holidays', 'days_before_due', 'days_after_due']);
                if ($bConfig) {
                    $tenantCronTime = (string) ($bConfig->cron_time ?: '09:00');
                    $skipHolidays = (bool) ($bConfig->skip_weekends_holidays ?? true);

                    $rawBefore = is_string($bConfig->days_before_due) ? json_decode($bConfig->days_before_due, true) : (array) ($bConfig->days_before_due ?? []);
                    $rawAfter  = is_string($bConfig->days_after_due)  ? json_decode($bConfig->days_after_due, true)  : (array) ($bConfig->days_after_due ?? []);

                    if (! empty($rawBefore) || ! empty($rawAfter)) {
                        $customOffsets = [];
                        if (! empty($rawBefore)) {
                            $beforeDays = array_unique(array_filter(array_map('intval', (array) $rawBefore)));
                            rsort($beforeDays);
                            foreach ($beforeDays as $bd) {
                                if ($bd > 0) $customOffsets["-{$bd}d"] = -$bd;
                            }
                        }
                        $customOffsets['0d'] = 0; // Sempre inclui o dia do vencimento
                        if (! empty($rawAfter)) {
                            $afterDays = array_unique(array_filter(array_map('intval', (array) $rawAfter)));
                            sort($afterDays);
                            foreach ($afterDays as $ad) {
                                if ($ad > 0) $customOffsets["+{$ad}d"] = $ad;
                            }
                        }
                        if (! empty($customOffsets)) {
                            $offsets = $customOffsets;
                        }
                    }
                }
            }
            [$hour, $minute] = $this->parseCronTime($tenantCronTime);

            $isOverdue = $due->isPast() && ! $due->isToday();

            if (! $isOverdue) {
                // Fatura em dia ou vencendo hoje: agenda a régua normalmente
                foreach ($offsets as $key => $days) {
                    $category = TenantTemplateResolver::categoryForOffset($key);
                    $waTpl = $this->templateResolver->whatsapp($tenantId ? (int) $tenantId : null, $key, $category);
                    if (! $waTpl) {
                        $waTpl = $this->templateResolver->whatsapp($tenantId ? (int) $tenantId : null, null, $category);
                    }

                    $emailTpl = null;
                    if ($tenantId && isset($emailEnabledTenants[$tenantId])) {
                        $emailTpl = $this->templateResolver->email((int) $tenantId, $key, $category)
                            ?? $this->templateResolver->email((int) $tenantId, null, $category);
                        if (! $emailTpl && class_exists(\App\Modules\EmailBilling\Services\EmailTemplateDefaults::class)) {
                            try {
                                $tModel = Tenant::find($tenantId);
                                if ($tModel) {
                                    app(\App\Modules\EmailBilling\Services\EmailTemplateDefaults::class)->ensureForTenant($tModel);
                                    $emailTpl = $this->templateResolver->email((int) $tenantId, $key, $category)
                                        ?? $this->templateResolver->email((int) $tenantId, null, $category);
                                }
                            } catch (\Throwable) {}
                        }
                    }

                    if (! $waTpl && ! $emailTpl) {
                        $waTpl = $this->templateResolver->whatsapp($tenantId ? (int) $tenantId : null, '0d', 'cobranca');
                    }

                    if (! $waTpl && ! $emailTpl) continue;

                    $scheduled = $due->copy()->addDays($days)->setTime($hour, $minute);
                    if ($skipHolidays) {
                        $scheduled = $this->holidays->nextBusinessDay($scheduled);
                    }
                    if ($scheduled->isPast()) {
                        // Se o marco agendado cair no dia de HOJE e o horário do cron já tiver passado hoje:
                        // agenda imediatamente para agora a fim de não perder a notificação configurada para este dia!
                        if ($scheduled->isToday()) {
                            $scheduled = now('America/Sao_Paulo');
                        } elseif ($days === 0 && $due->isToday()) {
                            $scheduled = now('America/Sao_Paulo');
                        } else {
                            continue;
                        }
                    }

                    $hasCustomerWa = ! empty($inv->customer_whatsapp) || ! empty($inv->customer_phone);
                    $hasCustomerEmail = ! empty($inv->customer_email) && filter_var($inv->customer_email, FILTER_VALIDATE_EMAIL);

                    $channel = match (true) {
                        ($waTpl && $emailTpl && $hasCustomerWa && $hasCustomerEmail) => 'both',
                        ($emailTpl && $hasCustomerEmail && ! $hasCustomerWa) => 'email',
                        ($waTpl && $hasCustomerWa && ! $hasCustomerEmail) => 'whatsapp',
                        ($waTpl && $emailTpl) => 'both',
                        ($waTpl) => 'whatsapp',
                        default => 'email',
                    };

                    DB::table('billing_dispatches')->insert([
                        'invoice_id'        => $inv->id,
                        'template_id'       => $waTpl?->id,
                        'email_template_id' => $emailTpl?->id,
                        'channel'           => $channel,
                        'schedule_offset'   => $key,
                        'scheduled_at'      => $scheduled,
                        'status'            => 'scheduled',
                        'attempts'          => 0,
                        'created_at'        => now(),
                        'updated_at'        => now(),
                    ]);
                    $count++;
                }
            } else {
                // Fatura já está vencida!
                $daysOverdue = max(1, (int) $due->diffInDays(now('America/Sao_Paulo')->startOfDay()));

                // Identifica qual o melhor template de atraso para cobrança imediata
                $bestPastOffset = '+1d';
                if ($daysOverdue >= 15) {
                    $bestPastOffset = '+15d';
                } elseif ($daysOverdue >= 7) {
                    $bestPastOffset = '+7d';
                } elseif ($daysOverdue >= 3) {
                    $bestPastOffset = '+3d';
                }

                // Agenda os offsets de atraso que ainda estão no futuro (se houver)
                foreach ($offsets as $key => $days) {
                    if ($days <= 0) continue; // Ignora -3d, -1d, 0d para fatura vencida

                    $scheduled = $due->copy()->addDays($days)->setTime($hour, $minute);
                    if ($skipHolidays) {
                        $scheduled = $this->holidays->nextBusinessDay($scheduled);
                    }

                    if ($scheduled->isToday()) {
                        $scheduled = now('America/Sao_Paulo');
                    } elseif ($scheduled->isPast()) {
                        continue;
                    }
                        $category = TenantTemplateResolver::categoryForOffset($key);
                        $waTpl = $this->templateResolver->whatsapp($tenantId ? (int) $tenantId : null, $key, $category);
                        $emailTpl = null;
                        if ($tenantId && isset($emailEnabledTenants[$tenantId])) {
                            $emailTpl = $this->templateResolver->email((int) $tenantId, $key, $category)
                                ?? $this->templateResolver->email((int) $tenantId, null, $category);
                        }

                        if ($waTpl || $emailTpl) {
                            $hasCustomerWa = ! empty($inv->customer_whatsapp) || ! empty($inv->customer_phone);
                            $hasCustomerEmail = ! empty($inv->customer_email) && filter_var($inv->customer_email, FILTER_VALIDATE_EMAIL);

                            $channel = match (true) {
                                ($waTpl && $emailTpl && $hasCustomerWa && $hasCustomerEmail) => 'both',
                                ($emailTpl && $hasCustomerEmail && ! $hasCustomerWa) => 'email',
                                ($waTpl && $hasCustomerWa && ! $hasCustomerEmail) => 'whatsapp',
                                ($waTpl && $emailTpl) => 'both',
                                ($waTpl) => 'whatsapp',
                                default => 'email',
                            };
                            DB::table('billing_dispatches')->insert([
                                'invoice_id'        => $inv->id,
                                'template_id'       => $waTpl?->id,
                                'email_template_id' => $emailTpl?->id,
                                'channel'           => $channel,
                                'schedule_offset'   => $key,
                                'scheduled_at'      => $scheduled,
                                'status'            => 'scheduled',
                                'attempts'          => 0,
                                'created_at'        => now(),
                                'updated_at'        => now(),
                            ]);
                            $count++;
                        }
                    }
                }

                // Cria o disparo imediato de atraso com o template mais apropriado
                $category = TenantTemplateResolver::categoryForOffset($bestPastOffset);
                $waTpl = $this->templateResolver->whatsapp($tenantId ? (int) $tenantId : null, $bestPastOffset, $category);
                if (! $waTpl && $bestPastOffset !== '+7d') {
                    $waTpl = $this->templateResolver->whatsapp($tenantId ? (int) $tenantId : null, '+7d', 'atraso');
                }
                if (! $waTpl && $bestPastOffset !== '+1d') {
                    $waTpl = $this->templateResolver->whatsapp($tenantId ? (int) $tenantId : null, '+1d', 'atraso');
                }
                if (! $waTpl) {
                    $waTpl = $this->templateResolver->whatsapp($tenantId ? (int) $tenantId : null, null, 'atraso');
                }

                $emailTpl = null;
                if ($tenantId && isset($emailEnabledTenants[$tenantId])) {
                    $emailTpl = $this->templateResolver->email((int) $tenantId, $bestPastOffset, $category);
                    if (! $emailTpl && $bestPastOffset !== '+7d') {
                        $emailTpl = $this->templateResolver->email((int) $tenantId, '+7d', 'atraso');
                    }
                    if (! $emailTpl && $bestPastOffset !== '+1d') {
                        $emailTpl = $this->templateResolver->email((int) $tenantId, '+1d', 'atraso');
                    }
                    if (! $emailTpl) {
                        $emailTpl = $this->templateResolver->email((int) $tenantId, null, 'atraso');
                    }
                }

                if ($waTpl || $emailTpl) {
                    $hasCustomerWa = ! empty($inv->customer_whatsapp) || ! empty($inv->customer_phone);
                    $hasCustomerEmail = ! empty($inv->customer_email) && filter_var($inv->customer_email, FILTER_VALIDATE_EMAIL);

                    $channel = match (true) {
                        ($waTpl && $emailTpl && $hasCustomerWa && $hasCustomerEmail) => 'both',
                        ($emailTpl && $hasCustomerEmail && ! $hasCustomerWa) => 'email',
                        ($waTpl && $hasCustomerWa && ! $hasCustomerEmail) => 'whatsapp',
                        ($waTpl && $emailTpl) => 'both',
                        ($waTpl) => 'whatsapp',
                        default => 'email',
                    };
                    $immediateAt = now('America/Sao_Paulo');
                    if ($skipHolidays) {
                        $immediateAt = $this->holidays->nextBusinessDay($immediateAt);
                    }

                    DB::table('billing_dispatches')->insert([
                        'invoice_id'        => $inv->id,
                        'template_id'       => $waTpl?->id,
                        'email_template_id' => $emailTpl?->id,
                        'channel'           => $channel,
                        'schedule_offset'   => $bestPastOffset,
                        'scheduled_at'      => $immediateAt,
                        'status'            => 'scheduled',
                        'attempts'          => 0,
                        'created_at'        => now(),
                        'updated_at'        => now(),
                    ]);
                    $count++;
                }
            }
        }

        // Também agenda disparos para faturas em atraso existentes que ficaram sem nenhum agendamento pendente
        $count += $this->scheduleOverdueInvoicesWithoutDispatches($emailEnabledTenants);

        return $count;
    }

    /**
     * Agenda cobrança de atraso periódica para faturas vencidas que não possuem nenhum dispatch pendente.
     */
    public function scheduleOverdueInvoicesWithoutDispatches(mixed $emailEnabledTenants = null): int
    {
        $emailEnabledTenants = $emailEnabledTenants ?? DB::table('tenant_email_configs')
            ->where('enabled', true)
            ->pluck('tenant_id')
            ->flip();

        $count = 0;

        // Faturas pendentes/overdue vencidas há pelo menos 1 dia que NÃO POSSUEM nenhum dispatch com status 'scheduled'
        $invoices = DB::table('invoices')
            ->leftJoin('customers', 'invoices.customer_id', '=', 'customers.id')
            ->whereIn('invoices.status', ['pending', 'overdue'])
            ->whereDate('invoices.due_date', '<', now('America/Sao_Paulo')->toDateString())
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('billing_dispatches')
                    ->whereColumn('billing_dispatches.invoice_id', 'invoices.id')
                    ->where('billing_dispatches.status', 'scheduled');
            })
            ->select(
                'invoices.*',
                'customers.whatsapp as customer_whatsapp',
                'customers.phone as customer_phone',
                'customers.email as customer_email'
            )
            ->get();

        foreach ($invoices as $inv) {
            // Verifica o último envio com sucesso
            $lastSent = DB::table('billing_dispatches')
                ->where('invoice_id', $inv->id)
                ->where('status', 'sent')
                ->orderByDesc('sent_at')
                ->first();

            // Se nunca enviou ou o último envio foi anterior ao vencimento, agenda cobrança de atraso
            $canSchedule = false;
            if (! $lastSent) {
                $canSchedule = true;
            } else {
                $lastSentAt = Carbon::parse($lastSent->sent_at ?? $lastSent->updated_at)->setTimezone('America/Sao_Paulo');
                $dueCarbon = Carbon::parse($inv->due_date)->setTimezone('America/Sao_Paulo')->startOfDay();
                
                if ($lastSentAt->lt($dueCarbon)) {
                    // Envio prévio ao vencimento (ex: aviso de criação ou lembrete) não bloqueia cobrança de atraso
                    $canSchedule = true;
                } elseif (! $lastSentAt->isToday()) {
                    // Permite cobrança de atraso caso não tenha sido enviado hoje
                    $canSchedule = true;
                }
            }

            if (! $canSchedule) {
                continue;
            }

            $due = Carbon::parse($inv->due_date)->setTimezone('America/Sao_Paulo')->startOfDay();
            $daysOverdue = max(1, (int) $due->diffInDays(now('America/Sao_Paulo')->startOfDay()));
            $offset = $daysOverdue >= 7 ? '+7d' : '+1d';
            $category = TenantTemplateResolver::categoryForOffset($offset);

            $tenantId = $inv->tenant_id;
            $waTpl = $this->templateResolver->whatsapp($tenantId ? (int) $tenantId : null, $offset, $category);
            if (! $waTpl && $offset !== '+7d') {
                $waTpl = $this->templateResolver->whatsapp($tenantId ? (int) $tenantId : null, '+7d', 'atraso');
            }
            if (! $waTpl && $offset !== '+1d') {
                $waTpl = $this->templateResolver->whatsapp($tenantId ? (int) $tenantId : null, '+1d', 'atraso');
            }
            if (! $waTpl) {
                $waTpl = $this->templateResolver->whatsapp($tenantId ? (int) $tenantId : null, null, 'atraso');
            }

            $emailTpl = null;
            if ($tenantId && isset($emailEnabledTenants[$tenantId])) {
                $emailTpl = $this->templateResolver->email((int) $tenantId, $offset, $category);
                if (! $emailTpl && $offset !== '+7d') {
                    $emailTpl = $this->templateResolver->email((int) $tenantId, '+7d', 'atraso');
                }
                if (! $emailTpl && $offset !== '+1d') {
                    $emailTpl = $this->templateResolver->email((int) $tenantId, '+1d', 'atraso');
                }
                if (! $emailTpl) {
                    $emailTpl = $this->templateResolver->email((int) $tenantId, null, 'atraso');
                }
            }

            if (! $waTpl && ! $emailTpl) {
                continue;
            }

            $hasCustomerWa = ! empty($inv->customer_whatsapp) || ! empty($inv->customer_phone);
            $hasCustomerEmail = ! empty($inv->customer_email) && filter_var($inv->customer_email, FILTER_VALIDATE_EMAIL);

            $channel = match (true) {
                ($waTpl && $emailTpl && $hasCustomerWa && $hasCustomerEmail) => 'both',
                ($emailTpl && $hasCustomerEmail && ! $hasCustomerWa) => 'email',
                ($waTpl && $hasCustomerWa && ! $hasCustomerEmail) => 'whatsapp',
                ($waTpl && $emailTpl) => 'both',
                ($waTpl) => 'whatsapp',
                default => 'email',
            };
            $scheduledAt = now('America/Sao_Paulo');
            $skipHolidays = true;
            if ($tenantId) {
                $skipHolidays = (bool) (DB::table('tenant_billing_config')
                    ->where('tenant_id', $tenantId)
                    ->value('skip_weekends_holidays') ?? true);
            }
            if ($skipHolidays) {
                $scheduledAt = $this->holidays->nextBusinessDay($scheduledAt);
            }

            DB::table('billing_dispatches')->insert([
                'invoice_id'        => $inv->id,
                'template_id'       => $waTpl?->id,
                'email_template_id' => $emailTpl?->id,
                'channel'           => $channel,
                'schedule_offset'   => $offset,
                'scheduled_at'      => $scheduledAt,
                'status'            => 'scheduled',
                'attempts'          => 0,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * Cancela todos os dispatches pendentes de faturas que já foram marcadas como
     * pagas no WHMCS (via meta->whmcs_status = 'Paid'), mesmo que o status local
     * ainda não tenha sido atualizado. Garante que cobranças não sejam enviadas
     * para clientes que anteciparam o pagamento no WHMCS.
     */
    public function cancelPaidWhmcsInvoiceDispatches(): int
    {
        $canceled = 0;
        $inactiveStatuses = ['paid', 'paga', 'pago', 'canceled', 'cancelled', 'cancelada', 'cancelado', 'refunded', 'estornada', 'estornado'];

        // Busca dispatches pendentes de faturas que são pagas, canceladas ou estornadas (local ou no WHMCS)
        $dispatches = DB::table('billing_dispatches')
            ->join('invoices', 'billing_dispatches.invoice_id', '=', 'invoices.id')
            ->whereIn('billing_dispatches.status', ['scheduled', 'failed', 'sending'])
            ->where(function ($q) use ($inactiveStatuses) {
                // Fatura local já marcada como paga, cancelada ou estornada
                $q->whereIn('invoices.status', $inactiveStatuses)
                  // OU whmcs_status no meta indica pago/cancelado/estornado (antes da sync local)
                  ->orWhere(function ($q2) {
                      $q2->whereIn('invoices.status', ['pending', 'sent', 'overdue'])
                         ->where(function ($q3) {
                             $q3->where('invoices.meta', 'like', '%"whmcs_status":"Paid"%')
                                ->orWhere('invoices.meta', 'like', '%"whmcs_status":"Cancelled"%')
                                ->orWhere('invoices.meta', 'like', '%"whmcs_status":"Canceled"%')
                                ->orWhere('invoices.meta', 'like', '%"whmcs_status":"Refunded"%');
                         });
                  });
            })
            ->select('billing_dispatches.id', 'invoices.status as invoice_status', 'invoices.meta as invoice_meta')
            ->get();

        foreach ($dispatches as $d) {
            $meta = is_string($d->invoice_meta) ? (json_decode($d->invoice_meta, true) ?: []) : ((array) ($d->invoice_meta ?? []));
            $whmcsStatus = strtolower((string) ($meta['whmcs_status'] ?? ''));
            $localStatus = strtolower((string) ($d->invoice_status ?? ''));

            $isInactive = in_array($localStatus, $inactiveStatuses, true)
                || in_array($whmcsStatus, ['paid', 'pago', 'paga', 'cancelled', 'canceled', 'cancelada', 'cancelado', 'refunded', 'estornada', 'estornado'], true);

            if ($isInactive) {
                $statusRef = in_array($localStatus, $inactiveStatuses, true) ? $localStatus : $whmcsStatus;
                $label = match (true) {
                    in_array($statusRef, ['canceled', 'cancelled', 'cancelada', 'cancelado'], true) => 'CANCELADA',
                    in_array($statusRef, ['refunded', 'estornada', 'estornado'], true) => 'ESTORNADA',
                    default => 'PAGA',
                };
                $reason = "Cancelado pelo sistema: fatura {$label} no sistema/WHMCS (cobrança bloqueada por WhatsApp e e-mail).";

                DB::table('billing_dispatches')->where('id', $d->id)->update([
                    'status'     => 'canceled',
                    'last_error' => $reason,
                    'updated_at' => now(),
                ]);
                $canceled++;
            }
        }

        return $canceled;
    }

    public function dispatchDue(): array
    {
        $report = ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'deferred' => 0];

        // 1. Limpeza preventiva de dispatches de faturas que já foram pagas, canceladas ou estornadas (status local)
        DB::table('billing_dispatches')
            ->join('invoices', 'billing_dispatches.invoice_id', '=', 'invoices.id')
            ->whereIn('billing_dispatches.status', ['scheduled', 'failed'])
            ->whereIn('invoices.status', ['paid', 'paga', 'pago', 'canceled', 'cancelled', 'cancelada', 'cancelado', 'refunded', 'estornada', 'estornado'])
            ->update([
                'billing_dispatches.status'     => 'canceled',
                'billing_dispatches.last_error' => 'Cancelado pelo sistema: fatura consta como paga, cancelada ou estornada (cobrança bloqueada por WhatsApp e e-mail).',
                'billing_dispatches.updated_at' => now(),
            ]);

        // 1b. Limpeza de dispatches de faturas com whmcs_status no meta (pagas, canceladas ou estornadas no WHMCS)
        DB::table('billing_dispatches')
            ->join('invoices', 'billing_dispatches.invoice_id', '=', 'invoices.id')
            ->whereIn('billing_dispatches.status', ['scheduled', 'failed'])
            ->whereIn('invoices.status', ['pending', 'sent', 'overdue'])
            ->where(function ($q) {
                $q->where('invoices.meta', 'like', '%"whmcs_status":"Paid"%')
                  ->orWhere('invoices.meta', 'like', '%"whmcs_status":"Cancelled"%')
                  ->orWhere('invoices.meta', 'like', '%"whmcs_status":"Canceled"%')
                  ->orWhere('invoices.meta', 'like', '%"whmcs_status":"Refunded"%');
            })
            ->update([
                'billing_dispatches.status'     => 'canceled',
                'billing_dispatches.last_error' => 'Cancelado pelo sistema: fatura paga, cancelada ou estornada no WHMCS (cobrança bloqueada por WhatsApp e e-mail).',
                'billing_dispatches.updated_at' => now(),
            ]);

        // 2. Limpeza de lembretes prévios obsoletos (-3d, -1d, 0d) para faturas que já venceram
        DB::table('billing_dispatches')
            ->join('invoices', 'billing_dispatches.invoice_id', '=', 'invoices.id')
            ->where('billing_dispatches.status', 'scheduled')
            ->whereIn('billing_dispatches.schedule_offset', ['-3d', '-1d', '0d'])
            ->whereDate('invoices.due_date', '<', now('America/Sao_Paulo')->toDateString())
            ->where('billing_dispatches.scheduled_at', '<=', now())
            ->update([
                'billing_dispatches.status'     => 'canceled',
                'billing_dispatches.last_error' => 'Cancelado pelo sistema: lembrete prévio obsoleto (fatura já se encontra vencida).',
                'billing_dispatches.updated_at' => now(),
            ]);

        $isNonBusinessDay = $this->holidays->isNonBusinessDay(now('America/Sao_Paulo'));
        $maxAttempts = (int) config('whatsapp.retry.max_attempts', 5);

        // Busca dispatches pendentes junto com o tenant_id (ou herdado do customer)
        $rows = DB::table('billing_dispatches')
            ->join('invoices', 'billing_dispatches.invoice_id', '=', 'invoices.id')
            ->leftJoin('customers', 'invoices.customer_id', '=', 'customers.id')
            ->select('billing_dispatches.*', DB::raw('COALESCE(invoices.tenant_id, customers.tenant_id) as tenant_id'))
            ->whereIn('billing_dispatches.status', ['scheduled', 'failed'])
            ->where('billing_dispatches.scheduled_at', '<=', now())
            ->where('billing_dispatches.attempts', '<', $maxAttempts)
            ->orderBy('billing_dispatches.scheduled_at')
            ->get();

        if ($rows->isEmpty()) {
            return $report;
        }

        $tenantIds = $rows->pluck('tenant_id')->filter()->unique();
        $controls = DB::table('whatsapp_safety_controls')
            ->whereIn('tenant_id', $tenantIds)
            ->get()
            ->keyBy('tenant_id');

        $billingConfigs = DB::table('tenant_billing_config')
            ->whereIn('tenant_id', $tenantIds)
            ->get()
            ->keyBy('tenant_id');

        $dispatchesToProcess = [];

        foreach ($rows->groupBy('tenant_id') as $tenantId => $tenantDispatches) {
            $bConfig = $tenantId ? $billingConfigs->get($tenantId) : null;
            $skipWeekends = (bool) ($bConfig->skip_weekends_holidays ?? true);

            // Respeita a configuracao especifica de fim de semana/feriado do tenant
            if ($isNonBusinessDay && $skipWeekends) {
                $report['skipped'] += $tenantDispatches->count();
                [$hour, $minute] = $this->parseCronTime((string) ($bConfig?->cron_time ?: '09:00'));
                $nextBusinessDate = $this->holidays->nextBusinessDay(now('America/Sao_Paulo'))->setTime($hour, $minute);
                DB::table('billing_dispatches')
                    ->whereIn('id', $tenantDispatches->pluck('id'))
                    ->where('status', 'scheduled')
                    ->update([
                        'scheduled_at' => $nextBusinessDate,
                        'updated_at'   => now(),
                    ]);
                continue;
            }

            $control = $controls->get($tenantId);
            $meta = is_string($control?->meta ?? null) ? json_decode($control->meta, true) : (array) ($control?->meta ?? []);
            $isAccelerated = ($meta['risk_mode'] ?? null) === 'accelerated' || ($control && (int) $control->limit_per_day > 300);
            
            // Intervalo exigido entre faturas: 2 minutos (se modo acelerado por conta e risco) ou 5 minutos (padrão seguro)
            $intervalMinutes = $isAccelerated ? 2 : 5;

            // Busca o ultimo envio deste tenant
            $lastDispatchQuery = DB::table('billing_dispatches')
                ->join('invoices', 'billing_dispatches.invoice_id', '=', 'invoices.id')
                ->leftJoin('customers', 'invoices.customer_id', '=', 'customers.id')
                ->where('billing_dispatches.status', 'sent')
                ->whereNotNull('billing_dispatches.updated_at');

            if (! empty($tenantId)) {
                $lastDispatchQuery->where(function ($q) use ($tenantId) {
                    $q->where('invoices.tenant_id', $tenantId)
                      ->orWhere('customers.tenant_id', $tenantId);
                });
            } else {
                $lastDispatchQuery->whereNull('invoices.tenant_id')->whereNull('customers.tenant_id');
            }

            $lastDispatch = $lastDispatchQuery->orderByDesc('billing_dispatches.updated_at')
                ->first(['billing_dispatches.updated_at']);

            $canSend = true;
            if ($lastDispatch) {
                $lastAt = \Carbon\Carbon::parse($lastDispatch->updated_at);
                $diffMinutes = abs(now()->diffInMinutes($lastAt));
                if ($diffMinutes < $intervalMinutes) {
                    $canSend = false;
                }
            }

            if ($canSend) {
                $sorted = $tenantDispatches->sortBy('scheduled_at');

                // Se houver múltiplos dispatches pendentes para a MESMA fatura no passado, seleciona o mais recente e descarta os mais antigos
                $byInvoice = $sorted->groupBy('invoice_id');
                $selectedDispatch = null;

                foreach ($byInvoice as $invId => $invDispatches) {
                    if ($invDispatches->count() > 1) {
                        $newest = $invDispatches->last();
                        foreach ($invDispatches as $old) {
                            if ($old->id !== $newest->id) {
                                DB::table('billing_dispatches')->where('id', $old->id)->update([
                                    'status'     => 'canceled',
                                    'last_error' => 'Cancelado pelo sistema: superado por disparo mais recente da mesma fatura.',
                                    'updated_at' => now(),
                                ]);
                                $report['skipped']++;
                            }
                        }
                        $selectedDispatch = $newest;
                    } else {
                        $selectedDispatch = $invDispatches->first();
                    }
                    break; // Processa 1 fatura por tenant por rodada
                }

                if ($selectedDispatch) {
                    $dispatchesToProcess[] = $selectedDispatch;
                }
            }
        }

        foreach ($dispatchesToProcess as $d) {
            $report = $this->processOne($d, $report, $maxAttempts);
        }
        
        return $report;
    }


    public function dispatchSpecific(int $dispatchId): bool
    {
        $d = DB::table('billing_dispatches')->where('id', $dispatchId)->first();
        if (! $d) return false;
        $r = $this->processOne($d, ['sent'=>0,'failed'=>0,'skipped'=>0,'deferred'=>0], 99);
        return $r['sent'] > 0 || ($r['deferred'] ?? 0) > 0;
    }

    public function pollGateways(): void { /* hook */ }

    protected function parseCronTime(string $cronTime): array
    {
        if (! preg_match('/^\d{2}:\d{2}$/', $cronTime)) {
            return [9, 0];
        }

        [$hour, $minute] = array_map('intval', explode(':', $cronTime));
        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            return [9, 0];
        }

        return [$hour, $minute];
    }

    protected function processOne(object $d, array $report, int $maxAttempts): array
    {
        DB::table('billing_dispatches')->where('id', $d->id)->update([
            'status' => 'sending', 'updated_at' => now(),
        ]);

        try {
            $invoice  = DB::table('invoices')->where('id', $d->invoice_id)->first();
            if (! $invoice) throw new \RuntimeException('Fatura nao encontrada.');

            $localStatus = strtolower((string) $invoice->status);
            $inactiveStatuses = ['paid', 'paga', 'pago', 'canceled', 'cancelled', 'cancelada', 'cancelado', 'refunded', 'estornada', 'estornado'];

            if (in_array($localStatus, $inactiveStatuses, true)) {
                $tipo = match (true) {
                    in_array($localStatus, ['canceled', 'cancelled', 'cancelada', 'cancelado'], true) => 'CANCELADA',
                    in_array($localStatus, ['refunded', 'estornada', 'estornado'], true) => 'ESTORNADA',
                    default => 'PAGA',
                };
                DB::table('billing_dispatches')->where('id', $d->id)->update([
                    'status'     => 'canceled',
                    'updated_at' => now(),
                    'last_error' => "Cancelado pelo sistema: a fatura consta como {$tipo} no sistema (cobrança bloqueada por WhatsApp e e-mail).",
                ]);
                $report['skipped']++;
                return $report;
            }

            // Verifica whmcs_status no meta: se a fatura foi paga/cancelada/estornada no WHMCS
            $invoiceMeta = is_string($invoice->meta) ? (json_decode($invoice->meta, true) ?: []) : ((array) ($invoice->meta ?? []));
            $whmcsStatusMeta = strtolower((string) ($invoiceMeta['whmcs_status'] ?? ''));
            if (in_array($whmcsStatusMeta, ['paid', 'paga', 'pago', 'cancelled', 'canceled', 'cancelada', 'cancelado', 'refunded', 'estornada', 'estornado'], true)) {
                $tipo = match (true) {
                    in_array($whmcsStatusMeta, ['canceled', 'cancelled', 'cancelada', 'cancelado'], true) => 'CANCELADA',
                    in_array($whmcsStatusMeta, ['refunded', 'estornada', 'estornado'], true) => 'ESTORNADA',
                    default => 'PAGA',
                };
                DB::table('billing_dispatches')->where('id', $d->id)->update([
                    'status'     => 'canceled',
                    'updated_at' => now(),
                    'last_error' => "Cancelado pelo sistema: fatura consta como {$tipo} no WHMCS (cobrança bloqueada por WhatsApp e e-mail).",
                ]);
                $report['skipped']++;
                return $report;
            }

            // Consulta em tempo real no WHMCS antes de enviar cobrança via WhatsApp/e-mail:
            // Se a fatura é originária do WHMCS, sincroniza o status exato antes de enviar
            // para garantir que fatura paga, cancelada ou estornada no WHMCS NÃO receba mensagem
            $whmcsId = data_get($invoiceMeta, 'whmcs_invoice_id');
            $connId  = data_get($invoiceMeta, 'whmcs_connection_id');
            if ($whmcsId) {
                try {
                    $whmcsConn = $connId
                        ? WhmcsConnection::find($connId)
                        : WhmcsConnection::where('active', true)->first();

                    if ($whmcsConn && $whmcsConn->active) {
                        (new \App\Modules\WhmcsIntegration\Services\WhmcsClient($whmcsConn))->syncSingleInvoice($whmcsId);

                        $refreshed = DB::table('invoices')->where('id', $d->invoice_id)->first();
                        if ($refreshed) {
                            $invoice = $refreshed;
                            $invoiceMeta = is_string($invoice->meta) ? (json_decode($invoice->meta, true) ?: []) : ((array) ($invoice->meta ?? []));
                            $whmcsStatusMeta = strtolower((string) ($invoiceMeta['whmcs_status'] ?? ''));
                            $localStatus = strtolower((string) $invoice->status);
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning("[BillingDispatcher] Falha ao consultar fatura #{$whmcsId} no WHMCS antes do envio: " . $e->getMessage());
                }

                if (in_array($localStatus, $inactiveStatuses, true) ||
                    in_array($whmcsStatusMeta, ['paid', 'paga', 'pago', 'cancelled', 'canceled', 'cancelada', 'cancelado', 'refunded', 'estornada', 'estornado'], true)) {
                    $statusRef = in_array($whmcsStatusMeta, ['paid', 'paga', 'pago', 'cancelled', 'canceled', 'cancelada', 'cancelado', 'refunded', 'estornada', 'estornado'], true)
                        ? $whmcsStatusMeta
                        : $localStatus;
                    $tipo = match (true) {
                        in_array($statusRef, ['canceled', 'cancelled', 'cancelada', 'cancelado'], true) => 'CANCELADA',
                        in_array($statusRef, ['refunded', 'estornada', 'estornado'], true) => 'ESTORNADA',
                        default => 'PAGA antecipadamente',
                    };
                    DB::table('billing_dispatches')->where('id', $d->id)->update([
                        'status'     => 'canceled',
                        'updated_at' => now(),
                        'last_error' => "Cancelado pelo sistema: fatura confirmada como {$tipo} no WHMCS (cobrança bloqueada por WhatsApp e e-mail).",
                    ]);
                    $report['skipped']++;
                    return $report;
                }
            }

            $alreadySentToday = DB::table('billing_dispatches')
                ->where('invoice_id', $d->invoice_id)
                ->where('status', 'sent')
                ->where('id', '!=', $d->id)
                ->whereDate('sent_at', now('America/Sao_Paulo')->toDateString())
                ->exists();

            if ($alreadySentToday) {
                DB::table('billing_dispatches')->where('id', $d->id)->update([
                    'status' => 'canceled',
                    'updated_at' => now(),
                    'last_error' => 'Cancelado pelo sistema: a fatura já recebeu uma cobrança no dia de hoje.',
                ]);
                $report['skipped']++;
                return $report;
            }

            $customer = DB::table('customers')->where('id', $invoice->customer_id)->first();
            if (! $customer) throw new \RuntimeException('Cliente nao encontrado.');

            $effectiveTenantId = $invoice->tenant_id ?: ($customer->tenant_id ?? null);
            $tenantOwner = $effectiveTenantId ? Tenant::find($effectiveTenantId) : null;
            $context = $this->billingContext($invoice, $customer, $tenantOwner);

            $channel = $d->channel ?? 'both';
            $sentAny = false;
            $errors = [];

            $customerPhone = $customer->whatsapp ?: $customer->phone ?: null;
            $customerEmail = $customer->email ?: null;
            $hasValidEmail = ! empty($customerEmail) && filter_var($customerEmail, FILTER_VALIDATE_EMAIL);
            $tenantEmailConfig = $tenantOwner?->emailConfig;
            $tenantEmailEnabled = (bool) ($tenantEmailConfig && $tenantEmailConfig->enabled);

            // Regra multi-canal com fallback cruzado:
            // - se 'both': tenta WhatsApp e E-mail.
            // - se 'whatsapp': tenta WhatsApp; se cliente não tiver telefone ou envio falhar, tenta E-mail se disponível.
            // - se 'email': tenta E-mail; se cliente não tiver e-mail ou tenant sem SMTP ou falhar, tenta WhatsApp se disponível.
            // - se cliente possui ambos os canais configurados e viáveis, envia por ambos.
            $tryWhatsApp = in_array($channel, ['whatsapp', 'both'], true)
                || (empty($customerEmail) || ! $tenantEmailEnabled);
            $tryEmail = in_array($channel, ['email', 'both'], true)
                || empty($customerPhone);

            if ($channel === 'whatsapp' && empty($customerPhone) && $hasValidEmail && $tenantEmailEnabled) {
                $tryWhatsApp = false;
                $tryEmail = true;
            }
            if ($channel === 'email' && (! $hasValidEmail || ! $tenantEmailEnabled) && ! empty($customerPhone)) {
                $tryEmail = false;
                $tryWhatsApp = true;
            }

            // === WhatsApp ===
            if ($tryWhatsApp) {
                try {
                    $waTpl = $d->template_id ? WhatsAppTemplate::find($d->template_id) : null;
                    if (! $waTpl) {
                        $waTpl = $this->resolveFallbackTemplateForInvoice($invoice, $effectiveTenantId);
                    }

                    if (! $customerPhone) {
                        $errors[] = 'WhatsApp: cliente sem numero de telefone/WhatsApp cadastrado';
                    } elseif (! $waTpl) {
                        $errors[] = 'WhatsApp: nenhum template ativo vinculado ao envio';
                    } else {
                        $phoneToSend = $customerPhone;
                        $message = $this->renderer->render($waTpl, $context);

                        // Consolidação em mensagem única: insere Pix Copia e Cola na mesma mensagem caso não esteja presente
                        $pixCopy = trim((string) ($context['pix_copia_cola'] ?? ''));
                        if ($pixCopy !== '' && ! str_contains($message, $pixCopy)) {
                            $pixBlock = "\n\n*Pix Copia e Cola:*\n```{$pixCopy}```";
                            if (! empty($context['link_pagamento']) && str_contains($message, (string) $context['link_pagamento'])) {
                                $message = str_replace(
                                    (string) $context['link_pagamento'],
                                    (string) $context['link_pagamento'] . $pixBlock,
                                    $message
                                );
                            } else {
                                $inserted = false;
                                foreach (['Se ja pagou', 'Se já pagou', 'Atenciosamente', 'Estamos a disposicao', 'Estamos à disposição', 'Se precisar de ajuda'] as $closing) {
                                    $pos = mb_strpos($message, $closing);
                                    if ($pos !== false) {
                                        $message = mb_substr($message, 0, $pos) . "*Pix Copia e Cola:*\n```{$pixCopy}```\n\n" . mb_substr($message, $pos);
                                        $inserted = true;
                                        break;
                                    }
                                }
                                if (! $inserted) {
                                    $message .= $pixBlock;
                                }
                            }
                        }

                        // Se houver linha digitável e não estiver na mensagem, inclui na mesma mensagem
                        $linhaDigitavel = trim((string) ($context['linha_digitavel'] ?? ''));
                        if ($linhaDigitavel !== '' && ! str_contains($message, $linhaDigitavel)) {
                            $linhaBlock = "\n\n*Linha Digitável:*\n```{$linhaDigitavel}```";
                            if (! empty($context['link_pagamento']) && str_contains($message, (string) $context['link_pagamento'])) {
                                $message = str_replace(
                                    (string) $context['link_pagamento'],
                                    (string) $context['link_pagamento'] . $linhaBlock,
                                    $message
                                );
                            } else {
                                $message .= $linhaBlock;
                            }
                        }

                        // Sanitiza espaçamentos e quebras de linha
                        $message = preg_replace("/\n{3,}/", "\n\n", trim($message));

                        $tenantInstance = $effectiveTenantId ? $this->resolveTenantInstance((int) $effectiveTenantId) : null;
                        $isManualOffset = in_array((string) $d->schedule_offset, ['manual', 'send_now', 'custom'], true);
                        $sendContext = [
                            'dispatch_id' => $d->id,
                            'tenant_id' => $effectiveTenantId,
                            'customer_id' => $customer->id,
                            'campaign' => $isManualOffset ? null : 'billing_'.$d->schedule_offset,
                            'origin' => $isManualOffset ? 'billing_dispatcher_manual' : 'billing_dispatcher',
                            'is_manual' => $isManualOffset,
                            'scheduled_for' => $d->scheduled_at,
                        ];

                        // Resolve se envia imagem do QR Code na mesma mensagem com a legenda formatada
                        $sendQrCode = (bool) (config('whatsapp.send_pix_qrcode_image', true) || config('whatsapp.send_separate_media', false));
                        $metodoPag = strtolower((string) ($context['payment_method'] ?? $context['metodo_pagamento'] ?? ''));
                        $isBoleto = str_contains($metodoPag, 'boleto');
                        $qrImage = '';
                        if ($sendQrCode && ! $isBoleto) {
                            $qrImage = (string) ($context['qrcode_media_url'] ?? $context['qrcode_url'] ?? $context['qrcode_pix'] ?? '');
                            $pixCopy = trim((string) ($context['pix_copia_cola'] ?? ''));

                            if ($qrImage === '' && $pixCopy !== '') {
                                $qrImage = $this->qrCodeUrl($pixCopy);
                            }

                            if ($qrImage !== '') {
                                $qrImage = $this->normalizeQrImageUrl($qrImage);
                            }
                        }

                        $isSingleWithQr = ($qrImage !== '');
                        $mediaType = $isSingleWithQr ? 'image' : 'text';

                        if ($tenantInstance) {
                            $inspection = $this->safety->inspect($tenantInstance, $phoneToSend, $message, array_merge($sendContext, [
                                'tenant_id' => $tenantInstance->tenant_id,
                                'tenant_instance_id' => $tenantInstance->id,
                                'media_type' => $mediaType,
                            ]));

                            if (! $inspection['allowed']) {
                                $this->safety->recordOutcome($inspection, [
                                    'tenant_id' => $tenantInstance->tenant_id,
                                    'tenant_instance_id' => $tenantInstance->id,
                                    'to' => $phoneToSend,
                                    'meta' => ['origin' => $sendContext['origin'], 'dispatch_id' => $d->id],
                                ]);

                                if ($inspection['defer']) {
                                    return $this->deferDispatch($d, $inspection, $report, $channel, $context);
                                }

                                $errors[] = 'WhatsApp: envio bloqueado pela camada anti-ban ('.$inspection['reason'].')';
                                $id = null;
                            } else {
                                if ($isSingleWithQr) {
                                    // Envia em MENSAGEM ÚNICA: imagem do QR Code + legenda com template completo
                                    $id = $this->instances->sendImage($tenantInstance, $phoneToSend, $qrImage, $message, array_merge($sendContext, [
                                        'skip_safety' => true,
                                        'inspection' => $inspection,
                                        'is_manual' => $isManualOffset,
                                        'delay_seconds' => $isManualOffset ? 0 : null,
                                    ]));

                                    // Fallback para texto caso falhe o envio da imagem
                                    if (! $id) {
                                        $id = $this->instances->sendText($tenantInstance, $phoneToSend, $message, array_merge($sendContext, [
                                            'skip_safety' => true,
                                            'inspection' => $inspection,
                                            'is_manual' => $isManualOffset,
                                            'delay_seconds' => $isManualOffset ? 0 : null,
                                        ]));
                                    }
                                } else {
                                    $id = $this->instances->sendText($tenantInstance, $phoneToSend, $message, array_merge($sendContext, [
                                        'skip_safety' => true,
                                        'inspection' => $inspection,
                                        'is_manual' => $isManualOffset,
                                        'delay_seconds' => $isManualOffset ? 0 : null,
                                    ]));
                                }
                            }
                        } else {
                            $inspection = $this->safety->inspect(null, $phoneToSend, $message, array_merge($sendContext, [
                                'media_type' => $mediaType,
                            ]));

                            if (! $inspection['allowed']) {
                                $this->safety->recordOutcome($inspection, [
                                    'tenant_id' => $invoice->tenant_id,
                                    'to' => $phoneToSend,
                                    'meta' => ['origin' => 'billing_dispatcher_fallback', 'dispatch_id' => $d->id],
                                ]);

                                if ($inspection['defer']) {
                                    return $this->deferDispatch($d, $inspection, $report, $channel, $context);
                                }

                                $errors[] = 'WhatsApp: envio bloqueado pela camada anti-ban ('.$inspection['reason'].')';
                                $id = null;
                            } else {
                                if ($isSingleWithQr) {
                                    // Envia em MENSAGEM ÚNICA: imagem do QR Code + legenda com template completo
                                    $id = $this->evolution->sendImage($phoneToSend, $qrImage, $message, array_merge($sendContext, $inspection, [
                                        'skip_safety' => true,
                                    ]));

                                    // Fallback para texto caso falhe o envio da imagem
                                    if (! $id) {
                                        $id = $this->evolution->sendText($phoneToSend, $message, array_merge($sendContext, $inspection, [
                                            'skip_safety' => true,
                                        ]));
                                    }
                                } else {
                                    $id = $this->evolution->sendText($phoneToSend, $message, array_merge($sendContext, $inspection, [
                                        'skip_safety' => true,
                                    ]));
                                }
                            }
                        }

                        if ($id) {
                            $sentAny = true;

                            // Disparos adicionais somente quando send_separate_media estiver ativado E não tiver enviado junto
                            if (config('whatsapp.send_separate_media', false) && ! $isSingleWithQr) {
                                if ($sendQrCode && ! $isBoleto && $qrImage !== '') {
                                    try {
                                        usleep(300000);
                                        $this->sendWhatsAppQrCode($tenantInstance, $customer, $qrImage, $context, $sendContext);
                                    } catch (\Throwable $e) {
                                        Log::warning("[BillingDispatcher] Falha ao enviar QR Code da fatura {$invoice->number}: " . $e->getMessage());
                                        $errors[] = 'WhatsApp QR Code: '.$e->getMessage();
                                    }
                                }
                            }

                            // Disparo de documento PDF de boleto quando configurado
                            if (config('whatsapp.send_separate_media', false)) {
                                $document = $this->resolveBoletoDocument($invoice, $context);
                                if ($document) {
                                    try {
                                        $this->sendWhatsAppBoleto($tenantInstance, $customer, $document, $context, $sendContext);
                                    } catch (\Throwable $e) {
                                        $errors[] = 'WhatsApp boleto: '.$e->getMessage();
                                    }
                                }
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    $errors[] = 'WhatsApp: ' . $e->getMessage();
                }
            }

            // === Email ===
            $shouldRunEmail = $tryEmail || (! $sentAny && $hasValidEmail && $tenantEmailEnabled);
            if ($shouldRunEmail && $hasValidEmail && $tenantEmailEnabled) {
                try {
                    $emailTpl = $d->email_template_id ? EmailTemplate::find($d->email_template_id) : null;

                    // Proteção: rejeita template não-financeiro (ex: marketing/reativação) para cobrança
                    if ($emailTpl && in_array($emailTpl->category, ['custom', 'marketing', 'notificacao'], true)) {
                        $emailTpl = null;
                    }

                    if (! $emailTpl && $effectiveTenantId) {
                        $category = TenantTemplateResolver::categoryForInvoice($invoice, $d->schedule_offset);
                        $offset = (string) ($d->schedule_offset ?: '0d');
                        if (in_array($offset, ['manual', 'custom', 'send_now'], true)) {
                            $offset = match ($category) {
                                'atraso' => '+1d',
                                'lembrete' => '-1d',
                                default => '0d',
                            };
                        }

                        $emailTpl = $this->templateResolver->email((int) $effectiveTenantId, $offset, $category)
                            ?? $this->templateResolver->email((int) $effectiveTenantId, null, $category);

                        if (! $emailTpl) {
                            $emailTpl = EmailTemplate::query()
                                ->where('tenant_id', $effectiveTenantId)
                                ->where('active', true)
                                ->where('category', $category)
                                ->orderByDesc('is_default')
                                ->first()
                                ?? EmailTemplate::query()
                                    ->where('tenant_id', $effectiveTenantId)
                                    ->where('active', true)
                                    ->whereIn('category', ['cobranca', 'atraso', 'lembrete'])
                                    ->orderByDesc('is_default')
                                    ->first();
                        }
                    }

                    if (! $emailTpl && $tenantOwner && class_exists(\App\Modules\EmailBilling\Services\EmailTemplateDefaults::class)) {
                        try {
                            app(\App\Modules\EmailBilling\Services\EmailTemplateDefaults::class)->ensureForTenant($tenantOwner);
                            $category = TenantTemplateResolver::categoryForInvoice($invoice, $d->schedule_offset);
                            $emailTpl = EmailTemplate::query()
                                ->where('tenant_id', $effectiveTenantId)
                                ->where('active', true)
                                ->where('category', $category)
                                ->orderByDesc('is_default')
                                ->first();
                        } catch (\Throwable) {}
                    }

                    $tenant = $tenantOwner;
                    if ($emailTpl && $tenant) {
                        $context['empresa'] = $tenant->company_name ?: config('app.name');
                        $rendered = $this->emailRenderer->render($emailTpl, $context);
                        $attachments = $this->emailAttachments($invoice, $context);
                        $r = $this->mailer->enqueue(
                            $tenant,
                            $customer->email,
                            $rendered['subject'],
                            $rendered['body_html'],
                            $rendered['body_text'],
                            (int) $d->id,
                            $attachments
                        );
                        if ($r['ok'] ?? false) $sentAny = true;
                        else $errors[] = 'Email: ' . ($r['error'] ?? 'desconhecido');
                    } else {
                        $errors[] = 'Email: nenhum template de e-mail ativo vinculado ao envio';
                    }
                } catch (\Throwable $e) {
                    $errors[] = 'Email: ' . $e->getMessage();
                }
            }

            DB::table('billing_dispatches')->where('id', $d->id)->update([
                'status'     => $sentAny ? 'sent' : 'failed',
                'attempts'   => $d->attempts + 1,
                'sent_at'    => $sentAny ? now() : null,
                'last_error' => $errors ? implode(' | ', $errors) : null,
                'payload'    => json_encode(['channel' => $channel, 'context' => $context], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);

            $notification = [
                'group'   => 'billing',
                'type'    => $sentAny ? 'dispatch_sent' : 'dispatch_failed',
                'title'   => $sentAny ? 'Cobranca enviada' : 'Falha ao enviar cobranca',
                'message' => "Fatura {$invoice->number} - {$customer->name} ({$channel})",
                'url'     => $tenantOwner ? '/painel' : "/admin/billing/invoices/{$invoice->id}",
                'icon'    => $sentAny ? 'bi-check-circle' : 'bi-exclamation-triangle',
                'color'   => $sentAny ? 'success' : 'danger',
                'created_at' => now(), 'updated_at' => now(),
            ];

            if (
                $tenantOwner
                && Schema::hasColumn('admin_notifications', 'tenant_id')
                && Schema::hasColumn('admin_notifications', 'user_id')
            ) {
                $notification['tenant_id'] = $tenantOwner->id;
                $notification['user_id'] = $tenantOwner->user_id;
            }

            DB::table('admin_notifications')->insert($notification);

            $sentAny ? $report['sent']++ : $report['failed']++;
        } catch (\Throwable $e) {
            Log::error('[BillingDispatcher] '.$e->getMessage(), ['dispatch_id' => $d->id]);
            $newAttempts = $d->attempts + 1;
            $backoff = config('whatsapp.retry.backoff_minutes', [1, 5, 15, 60, 240]);
            $delay = $backoff[min($newAttempts - 1, count($backoff) - 1)] ?? 60;
            DB::table('billing_dispatches')->where('id', $d->id)->update([
                'status'       => $newAttempts >= $maxAttempts ? 'failed' : 'scheduled',
                'attempts'     => $newAttempts,
                'scheduled_at' => now()->addMinutes((int) $delay),
                'last_error'   => $e->getMessage(),
                'updated_at'   => now(),
            ]);
            $report['failed']++;
        }

        return $report;
    }

    protected function deferDispatch(object $d, array $inspection, array $report, string $channel, array $context): array
    {
        $seconds = max(60, (int) ($inspection['release_seconds'] ?? 300));
        $scheduledFor = $inspection['scheduled_for'] ?? now()->addSeconds($seconds);

        DB::table('billing_dispatches')->where('id', $d->id)->update([
            'status' => 'scheduled',
            'scheduled_at' => $scheduledFor,
            'last_error' => null,
            'payload' => json_encode([
                'channel' => $channel,
                'context' => $context,
                'deferred' => [
                    'reason' => $inspection['reason'] ?? 'anti_ban_deferred',
                    'risk_score' => $inspection['risk_score'] ?? null,
                    'release_seconds' => $seconds,
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'updated_at' => now(),
        ]);

        $report['skipped']++;
        $report['deferred'] = ($report['deferred'] ?? 0) + 1;

        return $report;
    }

    protected function normalizeQrImageUrl(string $qrImage): string
    {
        if (str_starts_with($qrImage, 'data:image/') || (! str_starts_with($qrImage, 'http://') && ! str_starts_with($qrImage, 'https://'))) {
            try {
                $rawBase64 = preg_replace('/^data:image\/[^;]+;base64,/', '', $qrImage);
                $binary = base64_decode($rawBase64);
                if ($binary) {
                    $fileName = 'tmp/qrcodes/qr-'.md5($rawBase64).'.png';
                    \Illuminate\Support\Facades\Storage::disk('public')->put($fileName, $binary);
                    return url(\Illuminate\Support\Facades\Storage::disk('public')->url($fileName));
                }
            } catch (\Throwable) {}
        }

        return $qrImage;
    }

    protected function resolveFallbackTemplateForInvoice(object $invoice, ?int $tenantId): ?WhatsAppTemplate
    {
        $resolver = $this->templateResolver;
        $dueDate = $invoice->due_date ? Carbon::parse($invoice->due_date, 'America/Sao_Paulo')->startOfDay() : null;
        $today = now('America/Sao_Paulo')->startOfDay();

        if ($dueDate) {
            if ($dueDate->equalTo($today)) {
                return $resolver->whatsapp($tenantId, '0d', 'cobranca', 'vencimento-hoje')
                    ?: $resolver->whatsapp($tenantId, '0d', 'cobranca');
            }
            $diffDays = (int) $today->diffInDays($dueDate, false);
            if ($diffDays === 1) {
                return $resolver->whatsapp($tenantId, '-1d', 'lembrete', 'lembrete-1d-antes')
                    ?: $resolver->whatsapp($tenantId, '-1d', 'lembrete');
            }
            if ($diffDays > 1) {
                return $resolver->whatsapp($tenantId, '-3d', 'lembrete', 'lembrete-3d-antes')
                    ?: $resolver->whatsapp($tenantId, null, null, 'cobranca-gerada');
            }
            if ($diffDays < 0) {
                $offset = abs($diffDays) >= 7 ? '+7d' : '+1d';
                $slug = abs($diffDays) >= 7 ? 'atraso-7d' : 'atraso-1d';
                return $resolver->whatsapp($tenantId, $offset, 'atraso', $slug)
                    ?: $resolver->whatsapp($tenantId, '+1d', 'atraso');
            }
        }

        return $resolver->whatsapp($tenantId, '0d', 'cobranca', 'vencimento-hoje')
            ?: $resolver->whatsapp($tenantId, null, null, 'cobranca-gerada')
            ?: WhatsAppTemplate::query()
                ->where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))
                ->where('active', true)
                ->where('slug', '!=', 'boleto-fatura')
                ->first();
    }

    protected function sendWhatsAppQrCode(?TenantInstance $tenantInstance, object $customer, string $qrImage, array $context, array $sendContext): ?string
    {
        $caption = (string) ($context['qrcode_caption'] ?? 'QR Code para pagamento da fatura '.$context['numero_fatura']);
        $qrContext = array_merge($sendContext, [
            'campaign' => 'billing_qrcode_'.$sendContext['campaign'],
            'origin' => 'billing_dispatcher_qrcode',
            'media_type' => 'image',
            'skip_safety' => true,
        ]);

        $qrImage = $this->normalizeQrImageUrl($qrImage);

        $id = $tenantInstance
            ? $this->instances->sendImage($tenantInstance, $customer->whatsapp, $qrImage, $caption, $qrContext)
            : $this->evolution->sendImage($customer->whatsapp, $qrImage, $caption, $qrContext);

        if (! $id) {
            throw new \RuntimeException('imagem do QR Code nao confirmada pela Evolution.');
        }

        return $id;
    }

    protected function sendWhatsAppBoleto(?TenantInstance $tenantInstance, object $customer, array $document, array $context, array $sendContext): ?string
    {
        $tenantId = $sendContext['tenant_id'] ?? null;
        $boletoTpl = $this->templateResolver->whatsapp($tenantId ? (int) $tenantId : null, null, 'cobranca', 'boleto-fatura');
        $caption = $boletoTpl && $boletoTpl->active
            ? $this->renderer->render($boletoTpl, $context)
            : (string) ($context['boleto_caption'] ?? 'Boleto da fatura '.$context['numero_fatura']);

        $docContext = array_merge($sendContext, [
            'campaign' => 'billing_boleto_'.$sendContext['campaign'],
            'origin' => 'billing_dispatcher_boleto',
            'media_type' => 'document',
            'skip_safety' => true,
        ]);

        $id = $tenantInstance
            ? $this->instances->sendDocument($tenantInstance, $customer->whatsapp, $document['source'], $document['name'], $caption, $docContext)
            : $this->evolution->sendDocument($customer->whatsapp, $document['source'], $document['name'], $caption, $docContext);

        if (! $id) {
            throw new \RuntimeException('documento do boleto nao confirmado pela Evolution.');
        }

        return $id;
    }

    protected function sendWhatsAppPixCopiaCola(?TenantInstance $tenantInstance, object $customer, string $pixCopy, array $context, array $sendContext): ?string
    {
        $pixContext = array_merge($sendContext, [
            'campaign' => 'billing_pix_'.$sendContext['campaign'],
            'origin'   => 'billing_dispatcher_pix_copy',
        ]);

        $tenantId = $sendContext['tenant_id'] ?? null;
        $template = $this->templateResolver->whatsapp($tenantId ? (int) $tenantId : null, null, 'pix', 'pix-copia-cola');

        if (! $template || ! $template->active) {
            throw new \RuntimeException('Envio de PIX Copia e Cola cancelado: nenhum template ativo encontrado para a categoria pix.');
        }

        $message = $this->renderer->render($template, array_merge($context, ['pix_copia_cola' => $pixCopy]));

        $id = $tenantInstance
            ? $this->instances->sendText($tenantInstance, $customer->whatsapp, $message, array_merge($pixContext, ['skip_safety' => true]))
            : $this->evolution->sendText($customer->whatsapp, $message, array_merge($pixContext, ['skip_safety' => true]));

        if (! $id) {
            throw new \RuntimeException('mensagem PIX Copia e Cola nao confirmada pela Evolution.');
        }

        return $id;
    }

    protected function billingContext(object $invoice, object $customer, ?Tenant $tenant = null): array
    {
        $branding = [];
        if ($tenant && class_exists(\App\Modules\EmailBilling\Services\EmailLayoutRenderer::class)) {
            try {
                $branding = app(\App\Modules\EmailBilling\Services\EmailLayoutRenderer::class)->brandingFromTenantConfig($tenant->emailConfig, $tenant);
            } catch (\Throwable) {}
        }

        $brandName = $branding['brand_name'] ?? ($tenant?->company_name ?: config('app.name'));
        $logoUrl = $branding['logo_url'] ?? '';
        $primaryColor = $branding['primary_color'] ?? '#0f172a';
        $accentColor = $branding['accent_color'] ?? '#0d6efd';

        $context = [
            'tenant' => $tenant,
            'tenant_id' => $tenant?->id,
            'nome' => $customer->name,
            'email' => $customer->email,
            'valor' => $invoice->amount,
            'vencimento' => $invoice->due_date,
            'numero_fatura' => $invoice->number,
            'descricao' => $invoice->description,
            'empresa' => $brandName,
            'brand_name' => $brandName,
            'logo_empresa' => $logoUrl,
            'logo_url' => $logoUrl,
            'primary_color' => $primaryColor,
            'cor_primaria' => $primaryColor,
            'accent_color' => $accentColor,
            'cor_destaque' => $accentColor,
            'email_branding' => $branding,
        ];

        $context = array_merge(
            $context,
            $this->tenantContactContext($tenant),
            $this->paymentContext($invoice),
            $this->installmentContext($invoice),
            $this->whmcsContext($invoice, $customer)
        );

        $serviceName = $this->firstFilled(
            $context['servico_principal'] ?? null,
            $context['nome_servico'] ?? null,
            $this->invoiceServiceName($invoice)
        );

        $pacoteName = $this->firstFilled(
            $context['nome_pacote'] ?? null,
            $context['nome_produto'] ?? null,
            $serviceName
        );

        $context['servico_principal'] = $serviceName;
        $context['nome_servico']      = $serviceName;
        $context['servicos']          = $context['servicos'] ?? $serviceName;
        $context['nome_pacote']       = $pacoteName;
        $context['nome_produto']      = $pacoteName;
        $context['resumo_parcelas']   = $context['resumo_parcelas'] ?? 'Fatura unica';

        // Se o método for PIX ou envio de mídia avulsa mas não temos qrcode_media_url, geramos via URL de pagamento ou Pix
        $metodoPag = strtolower((string) ($context['payment_method'] ?? $context['metodo_pagamento'] ?? ''));
        $isPix = str_contains($metodoPag, 'pix') || $context['pix_copia_cola'] !== '';
        $sendSeparateMedia = (bool) config('whatsapp.send_separate_media', false);
        $sendQrCode = (bool) (config('whatsapp.send_pix_qrcode_image', true) || $sendSeparateMedia);
        $isBoleto = str_contains($metodoPag, 'boleto');
        if (! $isBoleto && ($isPix || $sendSeparateMedia) && ($context['qrcode_media_url'] ?? '') === '') {
            $payloadForQr = $this->firstFilled(
                $context['pix_copia_cola'] ?? null,
                $context['link_pagamento'] ?? null
            );
            if ($payloadForQr !== '') {
                $context['qrcode_media_url'] = $this->qrCodeUrl($payloadForQr);
                $context['qrcode_url']       = $context['qrcode_media_url'];
                $context['qrcode_pix']       = $context['qrcode_media_url'];
            }
        }

        $serviceLabel = $serviceName !== '' ? " - {$serviceName}" : '';
        $sendQrCode = (bool) (config('whatsapp.send_pix_qrcode_image', true) || config('whatsapp.send_separate_media', false));
        $context['qrcode_instrucao']  = ($sendQrCode && ($context['qrcode_media_url'] ?? '') !== '')
            ? 'Também enviamos a imagem do QR Code para você escanear e pagar com facilidade.'
            : '';
        $context['qrcode_caption']    = "QR Code PIX - Fatura {$context['numero_fatura']}{$serviceLabel} - {$context['valor']} - {$context['empresa']}";
        $context['boleto_caption']    = "Boleto - Fatura {$context['numero_fatura']}{$serviceLabel} - {$context['empresa']}";
        $context['pix_caption']       = "PIX Copia e Cola - Fatura {$context['numero_fatura']}{$serviceLabel}";

        return $context;
    }

    protected function tenantContactContext(?Tenant $tenant): array
    {
        $settings = $tenant?->settings ?? [];
        $emailConfig = $tenant?->emailConfig;

        return [
            'whatsapp_suporte' => (string) data_get($settings, 'support_whatsapp', ''),
            'email_suporte' => (string) (data_get($settings, 'support_email') ?: ($emailConfig?->from_email ?: '')),
            'telefone_suporte' => (string) data_get($settings, 'support_phone', ''),
        ];
    }

    protected function paymentContext(object $invoice): array
    {
        $meta = $this->meta($invoice->meta ?? null);
        $paymentUrl = trim((string) ($invoice->payment_url ?? ''));
        $boletoUrl = $this->firstFilled(
            data_get($meta, 'boleto_url'),
            data_get($meta, 'gateway_payment.boleto_url'),
            data_get($meta, 'gateway_payment.invoice_url'),
            data_get($meta, 'gateway_raw.data.link'),
            data_get($meta, 'whmcs_invoice_url')
        );
        $boletoPdfUrl = $this->firstFilled(
            data_get($meta, 'boleto_pdf_url'),
            data_get($meta, 'gateway_payment.boleto_pdf_url')
        );
        $barcode = $this->firstFilled(
            data_get($meta, 'linha_digitavel'),
            data_get($meta, 'gateway_payment.linha_digitavel')
        );
        $paymentMethod = $this->firstFilled(
            data_get($meta, 'metodo_pagamento'),
            data_get($meta, 'payment_method'),
            data_get($meta, 'whmcs_payment_method_raw')
        );
        $pixCopy = $this->firstFilled(
            data_get($meta, 'pix_copia_cola'),
            data_get($meta, 'pix_copy_paste'),
            data_get($meta, 'pix_payload'),
            data_get($meta, 'copy_paste'),
            data_get($meta, 'copia_cola'),
            data_get($meta, 'br_code'),
            data_get($meta, 'emv'),
            data_get($meta, 'gateway_payment.pix_copia_cola'),
            data_get($meta, 'gateway_raw.point_of_interaction.transaction_data.qr_code'),
            data_get($meta, 'gateway_raw.qrcode.qrcode'),
            data_get($meta, 'gateway_raw.qrcode.qrcodeTexto'),
            data_get($meta, 'qrcode.qrcode'),
            data_get($meta, 'qrcode.qrcodeTexto')
        );
        $qrImage = $this->firstFilled(
            data_get($meta, 'pix_qrcode'),
            data_get($meta, 'pix_qr_code'),
            data_get($meta, 'qrcode_url'),
            data_get($meta, 'qr_code_url'),
            data_get($meta, 'gateway_payment.qrcode_url'),
            data_get($meta, 'gateway_raw.qrcode.imagemQrcode'),
            data_get($meta, 'qrcode.imagemQrcode'),
            data_get($meta, 'imagemQrcode')
        );

        if ($paymentUrl !== '' && $this->isImagePayload($paymentUrl)) {
            $qrImage = $qrImage ?: $paymentUrl;
            $paymentUrl = '';
        }

        if ($paymentUrl !== '' && $this->looksLikePixPayload($paymentUrl)) {
            $pixCopy = $pixCopy ?: $paymentUrl;
            $paymentUrl = '';
        }

        $token = $invoice->webhook_token ?? null;
        if (! $token && isset($invoice->id)) {
            $token = \Illuminate\Support\Str::random(48);
            try {
                \Illuminate\Support\Facades\DB::table('invoices')->where('id', $invoice->id)->update(['webhook_token' => $token]);
            } catch (\Throwable) {}
        }
        $platformUrl = $token ? url('/fatura/' . $token) : '';

        // Se payment_url for link externo de gateway (Mercado Pago, ticket, etc.), substitui pelo checkout transparente do painel
        if (str_contains($paymentUrl, 'mercadopago.com') || str_contains($paymentUrl, '/ticket')) {
            $paymentUrl = $platformUrl;
        }
        $finalPaymentUrl = $paymentUrl ?: ($platformUrl ?: $boletoUrl);

        $whmcsCredit   = (float) data_get($meta, 'whmcs_credit_applied', 0);
        $whmcsSubtotal = (float) data_get($meta, 'whmcs_subtotal', 0);
        $whmcsBalance  = (float) data_get($meta, 'whmcs_balance', 0);
        $multaAtraso   = (float) data_get($meta, 'multa_atraso', 0);
        $jurosAtraso   = (float) data_get($meta, 'juros_atraso', 0);

        // Calcula multa e juros dinamicamente para faturas vencidas quando ainda não calculadas
        $due = ! empty($invoice->due_date) ? Carbon::parse($invoice->due_date)->startOfDay() : null;
        $isOverdue = $due && $due->isPast() && ! $due->isToday() && ! in_array(strtolower((string)($invoice->status ?? '')), ['paid', 'paga', 'pago']);
        if ($isOverdue && $multaAtraso == 0 && $jurosAtraso == 0) {
            try {
                $invModel = $invoice instanceof \App\Models\Invoice ? $invoice : \App\Models\Invoice::find($invoice->id);
                if ($invModel) {
                    $gatewaySlug = (string) ($invModel->gateway ?: config('gateways.default', 'mercadopago'));
                    $calc = app(\App\Modules\Billing\Services\InvoicePaymentService::class)->calcTotalPix($invModel, $gatewaySlug);
                    $multaAtraso = (float) ($calc['multa'] ?? 0);
                    $jurosAtraso = (float) ($calc['juros'] ?? 0);
                }
            } catch (\Throwable) {}
        }

        $baseVal = $whmcsBalance > 0 ? $whmcsBalance : (float) $invoice->amount;
        $valorAtualizado = (float) data_get($meta, 'valor_atualizado', round($baseVal + $multaAtraso + $jurosAtraso, 2));

        $infoCredito = '';
        if ($whmcsCredit > 0) {
            $infoCredito = "💳 Crédito aplicado: R$ " . number_format($whmcsCredit, 2, ',', '.') . " | Saldo pendente: R$ " . number_format($whmcsBalance ?: $invoice->amount, 2, ',', '.');
        }

        return [
            'link_pagamento' => $finalPaymentUrl,
            'payment_url' => $finalPaymentUrl,
            'platform_invoice_url' => $platformUrl,
            'boleto_url' => $boletoUrl,
            'boleto_pdf_url' => $boletoPdfUrl,
            'linha_digitavel' => $barcode,
            'metodo_pagamento' => $paymentMethod,
            'payment_method' => $paymentMethod,
            'pix_copia_cola' => $pixCopy,
            'qrcode_url' => $qrImage,
            'qrcode_pix' => $qrImage,
            'qrcode_media_url' => $qrImage,
            'valor_original' => data_get($meta, 'valor_original', $invoice->amount),
            'valor_atualizado' => $valorAtualizado,
            'multa' => $multaAtraso,
            'juros' => $jurosAtraso,
            'multa_atraso' => $multaAtraso,
            'juros_atraso' => $jurosAtraso,
            'credito_aplicado' => $whmcsCredit > 0 ? number_format($whmcsCredit, 2, ',', '.') : '',
            'whmcs_credit_applied' => $whmcsCredit,
            'whmcs_subtotal' => $whmcsSubtotal,
            'whmcs_balance' => $whmcsBalance,
            'saldo_pendente' => $whmcsBalance > 0 ? number_format($whmcsBalance, 2, ',', '.') : '',
            'info_credito' => $infoCredito,
        ];
    }

    protected function installmentContext(object $invoice): array
    {
        $meta = $this->meta($invoice->meta ?? null);

        if (Schema::hasTable('customer_package_installments')) {
            $row = DB::table('customer_package_installments as installment')
                ->join('customer_package_subscriptions as subscription', 'subscription.id', '=', 'installment.subscription_id')
                ->leftJoin('tenant_packages as package', 'package.id', '=', 'subscription.tenant_package_id')
                ->where('installment.invoice_id', $invoice->id)
                ->select([
                    'installment.subscription_id',
                    'installment.installment_number',
                    'installment.status',
                    'subscription.installments_count',
                    'package.name as package_name',
                ])
                ->first();

            if ($row) {
                $paid = DB::table('customer_package_installments')
                    ->where('subscription_id', $row->subscription_id)
                    ->where('status', 'paid')
                    ->count();
                $pending = DB::table('customer_package_installments')
                    ->where('subscription_id', $row->subscription_id)
                    ->whereIn('status', ['pending', 'overdue'])
                    ->count();
                $total = max(1, (int) $row->installments_count);
                $current = max(1, (int) $row->installment_number);

                return [
                    'nome_servico' => (string) ($row->package_name ?: ''),
                    'parcela_atual' => (string) $current,
                    'parcelas_total' => (string) $total,
                    'parcelas_pagas' => (string) $paid,
                    'parcelas_pendentes' => (string) $pending,
                    'resumo_parcelas' => $this->installmentSummary($current, $total, $paid, $pending),
                ];
            }
        }

        $current = (int) $this->firstFilled(
            data_get($meta, 'installment_number'),
            data_get($meta, 'parcela_atual')
        );
        $total = (int) $this->firstFilled(
            data_get($meta, 'installments_count'),
            data_get($meta, 'total_installments'),
            data_get($meta, 'parcelas_total')
        );

        if ($total > 1) {
            $current = $current > 0 ? $current : 1;
            $paid = (int) $this->firstFilled(data_get($meta, 'paid_installments'), data_get($meta, 'parcelas_pagas'), 0);
            $pending = max(0, $total - $paid);

            return [
                'parcela_atual' => (string) $current,
                'parcelas_total' => (string) $total,
                'parcelas_pagas' => (string) $paid,
                'parcelas_pendentes' => (string) $pending,
                'resumo_parcelas' => $this->installmentSummary($current, $total, $paid, $pending),
            ];
        }

        return [
            'parcela_atual' => '1',
            'parcelas_total' => '1',
            'parcelas_pagas' => '0',
            'parcelas_pendentes' => '1',
            'resumo_parcelas' => 'Fatura unica',
        ];
    }

    protected function installmentSummary(int $current, int $total, int $paid, int $pending): string
    {
        if ($total <= 1) {
            return 'Fatura unica';
        }

        return "Parcela {$current}/{$total} - {$paid} paga(s), {$pending} pendente(s)";
    }

    protected function invoiceServiceName(object $invoice): string
    {
        $description = html_entity_decode(strip_tags((string) ($invoice->description ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $description = preg_replace('/\s+/u', ' ', trim($description)) ?: '';
        $description = preg_replace('/\s+-\s+Parcela\s+\d+\s*\/\s*\d+.*/iu', '', $description) ?: $description;

        return $description;
    }

    protected function firstFilled(mixed ...$values): string
    {
        foreach ($values as $value) {
            if (is_string($value) || is_numeric($value)) {
                $value = trim((string) $value);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }

    protected function isImagePayload(string $value): bool
    {
        return str_starts_with($value, 'data:image/')
            || preg_match('/^https?:\/\/.+\.(png|jpe?g|webp|gif)(\?.*)?$/i', $value);
    }

    protected function looksLikePixPayload(string $value): bool
    {
        $normalized = strtoupper(preg_replace('/\s+/', '', $value) ?: '');

        return str_starts_with($normalized, '000201')
            || str_contains($normalized, 'BR.GOV.BCB.PIX');
    }

    protected function qrCodeUrl(string $payload): string
    {
        return 'https://api.qrserver.com/v1/create-qr-code/?size=360x360&margin=12&data='.rawurlencode($payload);
    }

    protected function resolveTenantInstance(int $tenantId): ?TenantInstance
    {
        return TenantInstance::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    protected function whmcsContext(object $invoice, object $customer): array
    {
        if (! Schema::hasTable('whmcs_services')) {
            return [];
        }

        $meta = $this->meta($invoice->meta ?? null);
        $connectionId = (int) ($meta['whmcs_connection_id'] ?? 0);

        if ($connectionId <= 0) {
            return [];
        }

        $rawServiceIds = $meta['whmcs_service_ids'] ?? [];
        if (is_string($rawServiceIds)) {
            $rawServiceIds = explode(',', $rawServiceIds);
        }

        $serviceIds = collect($rawServiceIds)
            ->map(fn ($id) => trim((string) $id))
            ->filter()
            ->unique()
            ->values();

        $services = WhmcsService::query()
            ->where('connection_id', $connectionId)
            ->where('customer_id', $customer->id)
            ->when($serviceIds->isNotEmpty(), fn ($query) => $query->whereIn('whmcs_service_id', $serviceIds->all()))
            ->orderByRaw("CASE status WHEN 'Active' THEN 0 WHEN 'Suspended' THEN 1 ELSE 2 END")
            ->orderBy('product_name')
            ->limit(10)
            ->get();

        if ($services->isEmpty()) {
            return [
                'whmcs_cliente_id' => (string) ($meta['whmcs_user_id'] ?? ''),
                'whmcs_fatura_id' => (string) ($meta['whmcs_invoice_id'] ?? ''),
            ];
        }

        $labels = $services->map(fn (WhmcsService $service) => $this->serviceLabel($service))->filter()->values();
        $active = $services->where('status', 'Active')->map(fn (WhmcsService $service) => $this->serviceLabel($service))->filter()->values();
        $suspended = $services->where('status', 'Suspended')->map(fn (WhmcsService $service) => $this->serviceLabel($service))->filter()->values();

        return [
            'servicos'          => $labels->implode(', '),
            'servicos_ativos'   => $active->implode(', '),
            'servicos_suspensos'=> $suspended->implode(', '),
            'servico_principal' => $labels->first() ?: '',
            'nome_servico'      => $labels->first() ?: '',
            'nome_pacote'       => $services->first()?->product_name ?: '',
            'nome_produto'      => $services->first()?->product_name ?: '',
            'grupo_produto'     => $services->first()?->group_name ?: '',
            'dominio_servico'   => $services->first()?->domain ?: '',
            'status_servico'    => (string) ($services->first()->status ?? ''),
            'whmcs_cliente_id'  => (string) ($meta['whmcs_user_id'] ?? $services->first()->whmcs_client_id ?? ''),
            'whmcs_fatura_id'   => (string) ($meta['whmcs_invoice_id'] ?? ''),
        ];
    }

    protected function serviceLabel(WhmcsService $service): string
    {
        $parts = array_filter([
            $service->product_name,
            $service->domain ? '('.$service->domain.')' : null,
        ]);

        return trim(implode(' ', $parts));
    }

    protected function emailAttachments(object $invoice, array $context): array
    {
        $attachments = [];

        if ($document = $this->resolveBoletoDocument($invoice, $context)) {
            $attachments[] = [
                'path' => $document['path'],
                'name' => $document['name'],
                'mime' => 'application/pdf',
            ];
        }

        if ($pdfPath = $this->renderInvoiceAttachment($invoice)) {
            $attachments[] = [
                'path' => $pdfPath,
                'name' => 'fatura-'.$invoice->number.'.pdf',
                'mime' => 'application/pdf',
            ];
        }

        return $attachments;
    }

    protected function resolveBoletoDocument(object $invoice, array $context): ?array
    {
        $meta = $this->meta($invoice->meta ?? null);
        $method = strtolower((string) ($context['payment_method'] ?? $context['metodo_pagamento'] ?? ''));
        $whmcsConnectionId = (int) ($meta['whmcs_connection_id'] ?? 0);

        if ($whmcsConnectionId > 0) {
            $attachEnabled = (bool) data_get(
                WhmcsConnection::query()->find($whmcsConnectionId)?->settings ?? [],
                'attach_boleto_pdf',
                true
            );

            if (! $attachEnabled) {
                return null;
            }
        }

        if (! str_contains($method, 'boleto')) {
            return null;
        }

        $pdfUrl = $this->firstFilled(
            $context['boleto_pdf_url'] ?? null,
            data_get($meta, 'boleto_pdf_url'),
            data_get($meta, 'gateway_payment.boleto_pdf_url')
        );

        if ($pdfUrl !== '') {
            $path = $this->cacheRemotePdf($pdfUrl, 'boleto-'.$invoice->number.'.pdf');
            if ($path) {
                return [
                    'path' => $path,
                    'name' => 'boleto-'.$invoice->number.'.pdf',
                    'source' => $path,
                ];
            }
        }

        return null;
    }

    protected function renderInvoiceAttachment(object $invoice): ?string
    {
        try {
            $model = $invoice instanceof \App\Models\Invoice ? $invoice : \App\Models\Invoice::with('customer')->find($invoice->id);
            if (! $model) {
                return null;
            }

            $pdf = $this->invoicePdf->generatePdfBinary($model);
            if (! is_string($pdf) || $pdf === '') {
                return null;
            }

            $relative = 'tmp/billing-attachments/fatura-'.$model->id.'-'.md5((string) $model->updated_at).'.pdf';
            Storage::disk('local')->put($relative, $pdf);

            return Storage::disk('local')->path($relative);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function cacheRemotePdf(string $url, string $filename): ?string
    {
        try {
            $response = Http::timeout(20)->retry(1, 250, throw: false)->get($url);
            if (! $response->successful()) {
                return null;
            }

            $contentType = strtolower((string) $response->header('Content-Type'));
            if ($contentType !== '' && ! str_contains($contentType, 'pdf')) {
                return null;
            }

            $relative = 'tmp/billing-attachments/'.md5($url).'-'.$filename;
            Storage::disk('local')->put($relative, $response->body());

            return Storage::disk('local')->path($relative);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function meta(mixed $meta): array
    {
        if (is_array($meta)) {
            return $meta;
        }

        if (is_string($meta) && $meta !== '') {
            $decoded = json_decode($meta, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}
