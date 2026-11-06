<?php

namespace App\Modules\Tenancy\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Modules\Holidays\Services\HolidayService;
use App\Modules\Tenancy\Models\CustomerPackageInstallment;
use App\Modules\Tenancy\Models\CustomerPackageSubscription;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantPackage;
use App\Support\Format;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TenantPackageService
{
    public function assignToCustomer(
        Tenant $tenant,
        Customer $customer,
        TenantPackage $package,
        string $paymentMode,
        int $installmentsCount,
        Carbon|string|null $firstDueDate = null
    ): CustomerPackageSubscription {
        if ((int) $customer->tenant_id !== $tenant->id || (int) $package->tenant_id !== $tenant->id) {
            throw new \RuntimeException('Cliente ou pacote indisponivel para este tenant.');
        }

        if (! $package->active) {
            throw new \RuntimeException('Este pacote esta inativo.');
        }

        $paymentMode = $paymentMode === 'installment' ? 'installment' : 'cash';
        if ($paymentMode === 'cash' && ! $package->allow_cash) {
            throw new \RuntimeException('Pagamento a vista nao esta habilitado neste pacote.');
        }

        if ($paymentMode === 'installment' && ! $package->allow_installments) {
            throw new \RuntimeException('Parcelamento nao esta habilitado neste pacote.');
        }

        $installmentsCount = $paymentMode === 'installment'
            ? max(2, min($installmentsCount, (int) $package->max_installments))
            : 1;

        $firstDueDate = $firstDueDate
            ? Carbon::parse($firstDueDate)->startOfDay()
            : now('America/Sao_Paulo')->addDays(3)->startOfDay();
        $firstDueDate = app(HolidayService::class)->nextBusinessDay($firstDueDate);

        $total = round((float) $package->total_amount, 2);
        $fee = $package->gateway_fee_mode === 'customer'
            ? round(($total * ((float) $package->gateway_fee_percent / 100)) + (float) $package->gateway_fixed_fee, 2)
            : 0.0;
        $interest = $paymentMode === 'installment'
            ? round($total * ((float) $package->installment_interest_percent / 100) * max(0, $installmentsCount - 1), 2)
            : 0.0;
        $finalAmount = round($total + $fee + $interest, 2);

        return DB::transaction(function () use (
            $tenant,
            $customer,
            $package,
            $paymentMode,
            $installmentsCount,
            $firstDueDate,
            $total,
            $fee,
            $interest,
            $finalAmount
        ) {
            $subscription = CustomerPackageSubscription::create([
                'tenant_id' => $tenant->id,
                'customer_id' => $customer->id,
                'tenant_package_id' => $package->id,
                'status' => 'active',
                'payment_mode' => $paymentMode,
                'installments_count' => $installmentsCount,
                'total_amount' => $total,
                'fee_amount' => $fee,
                'interest_amount' => $interest,
                'final_amount' => $finalAmount,
                'start_date' => now('America/Sao_Paulo')->toDateString(),
                'next_due_date' => $firstDueDate->toDateString(),
                'meta' => [
                    'package_name' => $package->name,
                    'gateway_fee_mode' => $package->gateway_fee_mode,
                    'billing_interval_days' => (int) ($package->settings['billing_interval_days'] ?? 30),
                ],
            ]);

            $billingIntervalDays = (int) ($subscription->meta['billing_interval_days'] ?? 30);
            $stepDays = $billingIntervalDays > 0 ? $billingIntervalDays : ($installmentsCount > 1 ? 30 : 0);
            $baseInstallment = round($finalAmount / $installmentsCount, 2);
            $createdTotal = 0.0;

            for ($i = 1; $i <= $installmentsCount; $i++) {
                $daysToAdd = ($i - 1) * $stepDays;
                $dueDate = app(HolidayService::class)->nextBusinessDay($firstDueDate->copy()->addDays($daysToAdd));
                $amount = $i === $installmentsCount
                    ? round($finalAmount - $createdTotal, 2)
                    : $baseInstallment;
                $createdTotal = round($createdTotal + $amount, 2);

                $invoice = Invoice::create([
                    'tenant_id' => $tenant->id,
                    'customer_id' => $customer->id,
                    'number' => $this->nextInvoiceNumber(),
                    'description' => $this->invoiceDescription($package, $i, $installmentsCount),
                    'amount' => $amount,
                    'due_date' => $dueDate->toDateString(),
                    'status' => 'pending',
                    'gateway' => $tenant->gateways()->where('active', true)->where('is_default', true)->value('gateway') ?: 'mercadopago',
                    'webhook_token' => Str::random(48),
                    'meta' => [
                        'origin' => 'tenant_package',
                        'subscription_id' => $subscription->id,
                        'tenant_package_id' => $package->id,
                        'installment_number' => $i,
                        'installments_count' => $installmentsCount,
                    ],
                ]);

                CustomerPackageInstallment::create([
                    'subscription_id' => $subscription->id,
                    'tenant_id' => $tenant->id,
                    'customer_id' => $customer->id,
                    'invoice_id' => $invoice->id,
                    'installment_number' => $i,
                    'amount' => $amount,
                    'due_date' => $dueDate->toDateString(),
                    'status' => 'pending',
                    'meta' => [
                        'invoice_number' => $invoice->number,
                    ],
                ]);
            }

            $subscription = $subscription->fresh(['package', 'installments.invoice', 'customer']);

            // Disparar notificação de nova assinatura
            try {
                app(\App\Modules\Notifications\Services\NotificationDispatcher::class)->send([
                    'channels'  => ['in_app', 'email'],
                    'group'     => 'subscription',
                    'type'      => 'success',
                    'title'     => 'Nova Assinatura Gerada',
                    'message'   => "O cliente {$customer->name} iniciou uma assinatura de {$package->name}.",
                    'icon'      => 'bi-stars',
                    'color'     => 'success',
                    'url'       => route('tenant.customers') . '?customer_id=' . $customer->id,
                    'recipients'=> ['tenant_id' => $tenant->id],
                ]);
            } catch (\Throwable) {}

            return $subscription;
        });

        // Agenda a régua de cobrança automática para as novas faturas do pacote
        try {
            app(\App\Modules\Billing\Services\BillingDispatcher::class)->scheduleNewInvoices();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("[TenantPackageService] Falha ao agendar régua automática: " . $e->getMessage());
        }

        return $subscription;
    }

    public function confirmInvoicePayment(int $invoiceId, string $gateway = 'manual', ?string $transactionId = null, bool $notifyCustomer = true): void
    {
        $installment = CustomerPackageInstallment::with(['subscription.package', 'customer', 'tenant'])
            ->where('invoice_id', $invoiceId)
            ->first();

        if (! $installment) {
            return;
        }

        // Se já foi completamente processado e notificado anteriormente (evita duplo envio de webhook/baixa)
        $meta = $installment->meta ?? [];
        if ($installment->status === 'paid' && ! empty($meta['notified_paid_at'])) {
            return;
        }

        DB::transaction(function () use ($installment, $gateway, $transactionId) {
            $installment->forceFill([
                'status' => 'paid',
                'paid_at' => $installment->paid_at ?: now(),
                'meta' => array_merge($installment->meta ?? [], [
                    'paid_gateway' => $gateway,
                    'transaction_id' => $transactionId,
                ]),
            ])->save();

            if ($installment->subscription) {
                $next = CustomerPackageInstallment::query()
                    ->where('subscription_id', $installment->subscription_id)
                    ->where('id', '!=', $installment->id)
                    ->whereIn('status', ['pending', 'overdue'])
                    ->orderBy('due_date')
                    ->first();

                $installment->subscription->forceFill([
                    'status' => $next ? 'active' : 'completed',
                    'next_due_date' => $next?->due_date,
                ])->save();
            }
        });

        $installment->refresh();

        if ($notifyCustomer) {
            $this->notifyPaymentConfirmation($installment);

            $installment->forceFill([
                'meta' => array_merge($installment->meta ?? [], [
                    'notified_paid_at' => now()->toIso8601String(),
                ]),
            ])->save();
        }
    }

    public function customerSummary(Customer $customer): array
    {
        $subscriptions = CustomerPackageSubscription::with(['package', 'installments'])
            ->where('customer_id', $customer->id)
            ->orderByDesc('id')
            ->get();

        $pending = CustomerPackageInstallment::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['pending', 'overdue'])
            ->sum('amount');

        $paid = CustomerPackageInstallment::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'paid')
            ->sum('amount');

        $overdueCount = CustomerPackageInstallment::query()
            ->where('customer_id', $customer->id)
            ->where(function ($q) {
                $q->where('status', 'overdue')
                    ->orWhere(function ($inner) {
                        $inner->where('status', 'pending')
                            ->whereDate('due_date', '<', now('America/Sao_Paulo')->toDateString());
                    });
            })
            ->count();

        return [
            'subscriptions' => $subscriptions,
            'pending_amount' => (float) $pending,
            'paid_amount' => (float) $paid,
            'pending_count' => CustomerPackageInstallment::query()
                ->where('customer_id', $customer->id)
                ->whereIn('status', ['pending', 'overdue'])
                ->count(),
            'overdue_count' => $overdueCount,
        ];
    }

    public function notifyCancellation(CustomerPackageSubscription $subscription): void
    {
        $tenant = $subscription->tenant;
        $customer = $subscription->customer;
        $package = $subscription->package;

        if (Schema::hasTable('admin_notifications')) {
            $notification = [
                'group' => 'billing',
                'type' => 'package_subscription_cancelled',
                'title' => 'Contratacao cancelada',
                'message' => $customer->name.' - '.$package->name,
                'url' => '/painel/pacotes',
                'icon' => 'bi-x-circle-fill',
                'color' => 'danger',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('admin_notifications', 'tenant_id')) {
                $notification['tenant_id'] = $tenant->id;
            }
            if (Schema::hasColumn('admin_notifications', 'user_id')) {
                $notification['user_id'] = $tenant->user_id;
            }

            DB::table('admin_notifications')->insert($notification);
        }

        $instance = $tenant->instances()->where('status', 'connected')->orderByDesc('is_default')->first()
            ?? $tenant->defaultInstance();

        if (! $instance || ! $customer->whatsapp) {
            return;
        }

        $resolver = app(TenantTemplateResolver::class);
        $template = $resolver->whatsapp($tenant->id, null, 'pacote_cancelamento', 'pacote-cancelamento');
        if (! $template || ! $template->active) {
            \Illuminate\Support\Facades\Log::warning("[TenantPackage] Notificação de cancelamento via WhatsApp cancelada: nenhum template ativo configurado para 'pacote_cancelamento'.");
            return;
        }

        $message = app(\App\Modules\Templates\Services\TemplateRenderer::class)->render($template, [
            'nome'              => $customer->name,
            'customer_name'     => $customer->name,
            'primeiro_nome'     => trim(explode(' ', $customer->name)[0] ?? ''),
            'nome_servico'      => $package->name,
            'servico_principal' => $package->name,
            'valor'             => $subscription->final_amount,
            'empresa'           => $tenant->company_name ?: config('app.name'),
            'whatsapp_suporte'  => $tenant->whatsapp_support ?? '',
        ]);

        $cleanTo = Format::whatsappNumber($customer->whatsapp);
        $context = [
            'origin' => 'tenant_package_cancellation',
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'skip_safety' => true,
        ];

        try {
            $msgId = app(InstanceManager::class)->sendText($instance, $cleanTo, $message, $context);

            if (Schema::hasTable('whatsapp_messages')) {
                \App\Models\WhatsAppMessage::create([
                    'to' => $cleanTo,
                    'message' => $message,
                    'status' => $msgId ? 'sent' : 'queued',
                    'tenant_instance_id' => $instance->id,
                    'meta' => array_merge($context, [
                        'meta_message_id' => $msgId,
                        'customer_name' => $customer->name,
                        'package_name' => $package->name,
                    ]),
                ]);
            }
        } catch (\Throwable) {
            // Notificacao nao deve quebrar o cancelamento.
        }
    }

    protected function notifyPaymentConfirmation(CustomerPackageInstallment $installment): void
    {
        $subscription = $installment->subscription;
        $tenant = $installment->tenant;
        $customer = $installment->customer;
        $nextDue = $subscription->next_due_date ? Format::date($subscription->next_due_date) : 'quitado';

        if (Schema::hasTable('admin_notifications')) {
            $notification = [
                'group' => 'billing',
                'type' => 'package_payment_paid',
                'title' => 'Pagamento de pacote confirmado',
                'message' => $customer->name.' - '.$subscription->package->name.' - parcela '.$installment->installment_number,
                'url' => '/painel/pacotes',
                'icon' => 'bi-check-circle-fill',
                'color' => 'success',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('admin_notifications', 'tenant_id')) {
                $notification['tenant_id'] = $tenant->id;
            }
            if (Schema::hasColumn('admin_notifications', 'user_id')) {
                $notification['user_id'] = $tenant->user_id;
            }

            DB::table('admin_notifications')->insert($notification);
        }

        $instance = $tenant->instances()->where('status', 'connected')->orderByDesc('is_default')->first()
            ?? $tenant->defaultInstance();

        $phone = $customer->whatsapp ?: $customer->phone;
        $email = $customer->email;
        $hasWhatsApp = ! empty($phone);
        $hasEmail = ! empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL) && ($tenant->emailConfig?->enabled ?? false);

        if (! $hasWhatsApp && ! $hasEmail) {
            \Illuminate\Support\Facades\Log::warning("[TenantPackage] Confirmação de parcela cancelada: cliente #{$customer->id} sem whatsapp e sem e-mail ativo.");
            return;
        }

        $resolver = app(TenantTemplateResolver::class);

        // 1. Disparo via WhatsApp (se cliente possuir telefone/whatsapp)
        if ($hasWhatsApp) {
            $template = $resolver->whatsapp($tenant->id, null, 'pacote_pagamento', 'pacote-pagamento-confirmado')
                ?? $resolver->whatsapp($tenant->id, null, 'agradecimento', 'pagamento-confirmado');

            if ($template && $template->active) {
                $message = app(\App\Modules\Templates\Services\TemplateRenderer::class)->render($template, [
                    'nome'               => $customer->name,
                    'customer_name'      => $customer->name,
                    'primeiro_nome'      => trim(explode(' ', $customer->name)[0] ?? ''),
                    'nome_servico'       => $subscription->package->name,
                    'servico_principal'  => $subscription->package->name,
                    'parcela_atual'      => $installment->installment_number,
                    'parcelas_total'     => $subscription->installments_count,
                    'resumo_parcelas'    => "{$installment->installment_number}/{$subscription->installments_count}",
                    'valor'              => $installment->amount,
                    'proximo_vencimento' => $nextDue,
                    'empresa'            => $tenant->company_name ?: config('app.name'),
                    'whatsapp_suporte'   => $tenant->whatsapp_support ?? '',
                ]);
            } else {
                $formattedAmount = number_format((float) $installment->amount, 2, ',', '.');
                $company = $tenant->company_name ?: config('app.name');
                $message = "*Pagamento Confirmado!* 🎉\n\n"
                    . "Olá, *{$customer->name}*!\n\n"
                    . "Identificamos o pagamento da sua parcela com sucesso:\n"
                    . "*Serviço:* {$subscription->package->name}\n"
                    . "*Parcela:* {$installment->installment_number}/{$subscription->installments_count}\n"
                    . "*Valor:* R$ {$formattedAmount}\n"
                    . "*Próximo vencimento:* {$nextDue}\n\n"
                    . "Agradecemos pela preferência!\n"
                    . "*{$company}*";
            }

            $cleanTo = Format::whatsappNumber($phone);
            $context = [
                'origin' => 'tenant_package_payment_confirmation',
                'tenant_id' => $tenant->id,
                'customer_id' => $customer->id,
                'skip_safety' => true,
            ];

            try {
                $msgId = null;
                if ($instance && class_exists(InstanceManager::class)) {
                    try {
                        $msgId = app(InstanceManager::class)->sendText($instance, $cleanTo, $message, $context);
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning("[TenantPackage] Falha ao enviar via instância do tenant #{$instance->id}: " . $e->getMessage());
                    }
                }

                if (! $msgId && class_exists(\App\Modules\WhatsApp\Services\WhatsAppMetaClient::class)) {
                    try {
                        $msgId = app(\App\Modules\WhatsApp\Services\WhatsAppMetaClient::class)->sendText($cleanTo, $message, $context);
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning("[TenantPackage] Falha ao enviar via WhatsAppMetaClient global: " . $e->getMessage());
                    }
                }

                if (Schema::hasTable('whatsapp_messages')) {
                    \App\Models\WhatsAppMessage::create([
                        'to' => $cleanTo,
                        'message' => $message,
                        'status' => $msgId ? 'sent' : 'queued',
                        'tenant_instance_id' => $instance?->id,
                        'meta' => array_merge($context, [
                            'meta_message_id' => $msgId,
                            'customer_name' => $customer->name,
                            'package_name' => $subscription->package->name,
                            'installment' => $installment->installment_number,
                        ]),
                    ]);
                }
            } catch (\Throwable) {
                // O pagamento não deve falhar por indisponibilidade do WhatsApp
            }
        }

        // 2. Disparo via E-mail (se cliente possuir e-mail e tenant tiver serviço de e-mail ativo)
        if ($hasEmail) {
            try {
                $emailTpl = $resolver->email($tenant->id, null, 'pacote_pagamento')
                    ?? $resolver->email($tenant->id, null, 'agradecimento');

                if (! $emailTpl) {
                    $emailTpl = \App\Modules\EmailBilling\Models\EmailTemplate::where('tenant_id', $tenant->id)
                        ->whereIn('slug', ['pagamento-confirmado', 'pacote-pagamento-confirmado'])
                        ->where('active', true)
                        ->first();
                }

                if (! $emailTpl && class_exists(\App\Modules\EmailBilling\Services\EmailTemplateDefaults::class)) {
                    $defaults = (new \App\Modules\EmailBilling\Services\EmailTemplateDefaults())->defaults();
                    if (isset($defaults['pagamento-confirmado'])) {
                        $emailTpl = new \App\Modules\EmailBilling\Models\EmailTemplate($defaults['pagamento-confirmado'] + [
                            'tenant_id' => $tenant->id,
                            'slug' => 'pagamento-confirmado',
                        ]);
                    }
                }

                $emailContext = [
                    'tenant'             => $tenant,
                    'tenant_id'          => $tenant->id,
                    'nome'               => $customer->name,
                    'customer_name'      => $customer->name,
                    'primeiro_nome'      => trim(explode(' ', $customer->name)[0] ?? ''),
                    'email'              => $email,
                    'nome_servico'       => $subscription->package->name,
                    'servico_principal'  => $subscription->package->name,
                    'parcela_atual'      => $installment->installment_number,
                    'parcelas_total'     => $subscription->installments_count,
                    'resumo_parcelas'    => "{$installment->installment_number}/{$subscription->installments_count}",
                    'valor'              => 'R$ ' . number_format((float) $installment->amount, 2, ',', '.'),
                    'amount'             => 'R$ ' . number_format((float) $installment->amount, 2, ',', '.'),
                    'proximo_vencimento' => $nextDue,
                    'data_pagamento'     => now('America/Sao_Paulo')->format('d/m/Y H:i'),
                    'metodo_pagamento'   => 'PIX/Cartão',
                    'empresa'            => $tenant->company_name ?: config('app.name'),
                    'email_suporte'      => $tenant->email_support ?? $tenant->emailConfig?->from_address ?? '',
                    'whatsapp_suporte'   => $tenant->whatsapp_support ?? '',
                    'link_reativacao'    => url('/'),
                    'link_pagamento'     => url('/'),
                ];

                if ($emailTpl && class_exists(\App\Modules\EmailBilling\Services\EmailTemplateRenderer::class)) {
                    $rendered = app(\App\Modules\EmailBilling\Services\EmailTemplateRenderer::class)->render($emailTpl, $emailContext);
                    $subject = $rendered['subject'];
                    $bodyHtml = $rendered['body_html'];
                    $bodyText = $rendered['body_text'];
                } else {
                    $company = $tenant->company_name ?: config('app.name');
                    $formattedAmount = number_format((float) $installment->amount, 2, ',', '.');
                    $subject = "Pagamento confirmado - {$subscription->package->name} (Parcela {$installment->installment_number}/{$subscription->installments_count})";
                    $bodyHtml = "<p>Olá <strong>{$customer->name}</strong>,</p><p>Confirmamos o recebimento do pagamento da parcela <strong>{$installment->installment_number}/{$subscription->installments_count}</strong> de <strong>{$subscription->package->name}</strong> no valor de <strong>R$ {$formattedAmount}</strong>.</p><p>Próximo vencimento: <strong>{$nextDue}</strong>.</p><p>Agradecemos pela preferência!</p><p><strong>{$company}</strong></p>";
                    $bodyText = "Olá {$customer->name},\n\nConfirmamos o pagamento da sua parcela {$installment->installment_number}/{$subscription->installments_count} de {$subscription->package->name} (R$ {$formattedAmount}).\nPróximo vencimento: {$nextDue}.\nAgradecemos pela preferência!\n{$company}";
                }

                if (class_exists(\App\Modules\EmailBilling\Services\SmtpMailer::class)) {
                    app(\App\Modules\EmailBilling\Services\SmtpMailer::class)->send(
                        $tenant,
                        $email,
                        $subject,
                        $bodyHtml,
                        $bodyText
                    );
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("[TenantPackage] Falha ao enviar e-mail de confirmação de parcela para #{$customer->id}: " . $e->getMessage());
            }
        }
    }

    protected function invoiceDescription(TenantPackage $package, int $installment, int $totalInstallments): string
    {
        $parts = [
            'Servico contratado: '.$package->name,
            trim((string) $package->description),
            "Parcela {$installment}/{$totalInstallments}",
        ];

        return implode("\n\n", array_filter($parts));
    }

    protected function nextInvoiceNumber(): string
    {
        return 'INV-'.str_pad((string) ((int) Invoice::max('id') + 1), 6, '0', STR_PAD_LEFT);
    }
}
