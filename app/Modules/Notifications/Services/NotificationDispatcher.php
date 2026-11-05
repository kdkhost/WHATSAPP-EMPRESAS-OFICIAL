<?php

namespace App\Modules\Notifications\Services;

use App\Modules\EmailBilling\Services\EmailLayoutRenderer;
use App\Modules\Settings\Services\SettingsManager;
use App\Modules\Tenancy\Models\TenantInstance;
use App\Modules\Tenancy\Services\InstanceManager;
use App\Modules\WhatsApp\Services\EvolutionClient;
use App\Modules\WhatsApp\Services\WhatsAppSafetyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * Central de despacho de notificacoes multi-canal.
 *
 * Uso por qualquer modulo/addon:
 *   app(NotificationDispatcher::class)->send([
 *       'channels'  => ['in_app', 'push', 'email', 'whatsapp'],
 *       'group'     => 'billing',
 *       'type'      => 'invoice_paid',
 *       'title'     => 'Pagamento aprovado!',
 *       'message'   => 'Fatura INV-001 foi paga.',
 *       'url'       => '/admin/billing/invoices/1',
 *       'icon'      => 'bi-check-circle',
 *       'color'     => 'success',
 *       'recipients'=> ['admin_user_id' => 1], // ou 'tenant_id', 'user_id', 'all_admins', 'role:admin'
 *       'data'      => ['invoice_id' => 1],     // dados extras
 *   ]);
 */
class NotificationDispatcher
{
    public function __construct(
        protected EmailLayoutRenderer $emailLayoutRenderer,
        protected ?InstanceManager $instances = null,
        protected ?WhatsAppSafetyService $safety = null,
    ) {}

    /** Envia notificacao em todos os canais declarados. */
    public function send(array $notification): void
    {
        $channels = $notification['channels'] ?? ['in_app'];

        foreach ($channels as $channel) {
            try {
                match ($channel) {
                    'in_app'   => $this->sendInApp($notification),
                    'push'     => $this->queuePush($notification),
                    'email'    => $this->sendEmail($notification),
                    'whatsapp' => $this->queueWhatsApp($notification),
                    default    => Log::warning("[Notifications] Canal desconhecido: {$channel}"),
                };
            } catch (\Throwable $e) {
                Log::error("[Notifications] Falha no canal {$channel}: {$e->getMessage()}");
            }
        }
    }

    /** Notificacao in-app (sininho do navbar admin). */
    protected function sendInApp(array $n): void
    {
        $tenantPayload = $this->tenantRecipientPayload($n);
        if ($tenantPayload !== null) {
            if (
                ! Schema::hasColumn('admin_notifications', 'tenant_id')
                || ! Schema::hasColumn('admin_notifications', 'user_id')
            ) {
                Log::warning('[Notifications] Notificacao tenant ignorada: schema de audiencia ausente.');
                return;
            }

            DB::table('admin_notifications')->insert($this->payload($n, null, $tenantPayload));
            return;
        }

        $recipients = $this->resolveRecipients($n);

        foreach ($recipients as $adminId) {
            DB::table('admin_notifications')->insert($this->payload($n, $adminId));
        }
    }

    protected function payload(array $n, ?int $adminId = null, array $tenantPayload = []): array
    {
        $group = $n['group'] ?? 'system';
        [$defaultIcon, $defaultColor] = $this->defaultIconAndColor($group);

        $payload = [
            'admin_user_id' => $adminId,
            'group'         => $group,
            'type'          => $n['type'] ?? 'info',
            'title'         => $n['title'] ?? '',
            'message'       => $n['message'] ?? null,
            'url'           => $n['url'] ?? null,
            'icon'          => $n['icon'] ?? $defaultIcon,
            'color'         => $n['color'] ?? $defaultColor,
            'created_at'    => now(),
            'updated_at'    => now(),
        ];

        if (
            $tenantPayload
            && Schema::hasColumn('admin_notifications', 'tenant_id')
            && Schema::hasColumn('admin_notifications', 'user_id')
        ) {
            $payload['tenant_id'] = $tenantPayload['tenant_id'] ?? null;
            $payload['user_id'] = $tenantPayload['user_id'] ?? null;
        }

        return $payload;
    }

    protected function tenantRecipientPayload(array $n): ?array
    {
        $recipients = $n['recipients'] ?? [];
        $tenantId = $recipients['tenant_id'] ?? $n['tenant_id'] ?? null;
        $userId = $recipients['user_id'] ?? $n['user_id'] ?? null;

        if (! $tenantId && ! $userId) {
            return null;
        }

        if ($tenantId && ! $userId && Schema::hasTable('tenants')) {
            $userId = DB::table('tenants')->where('id', $tenantId)->value('user_id');
        }

        if ($userId && ! $tenantId && Schema::hasTable('tenants')) {
            $tenantId = DB::table('tenants')->where('user_id', $userId)->value('id');
        }

        return [
            'tenant_id' => $tenantId ? (int) $tenantId : null,
            'user_id' => $userId ? (int) $userId : null,
        ];
    }

    protected function defaultIconAndColor(string $group): array
    {
        return match ($group) {
            'cancellation' => ['bi-x-circle', 'danger'],
            'settlement'   => ['bi-check-circle-fill', 'success'],
            'registration' => ['bi-person-plus', 'info'],
            'subscription' => ['bi-stars', 'primary'],
            'billing'      => ['bi-receipt', 'primary'],
            'whatsapp'     => ['bi-whatsapp', 'success'],
            'security'     => ['bi-shield-check', 'warning'],
            default        => ['bi-bell', 'primary'],
        };
    }

    /** Envia email diretamente com fallback seguro para fila se houver oscilação. */
    protected function sendEmail(array $n): void
    {
        try {
            $this->processEmail($n);
        } catch (\Throwable $e) {
            Log::warning("[Notifications] Falha no envio direto de email, enfileirando: {$e->getMessage()}");
            $this->queueEmail($n);
        }
    }

    /** Enfileira push notification (WebPush). */
    protected function queuePush(array $n): void
    {
        DB::table('notification_queue')->insert([
            'channel'    => 'push',
            'payload'    => json_encode($n, JSON_UNESCAPED_UNICODE),
            'status'     => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Enfileira email. */
    protected function queueEmail(array $n): void
    {
        DB::table('notification_queue')->insert([
            'channel'    => 'email',
            'payload'    => json_encode($n, JSON_UNESCAPED_UNICODE),
            'status'     => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Enfileira WhatsApp. */
    protected function queueWhatsApp(array $n): void
    {
        DB::table('notification_queue')->insert([
            'channel'    => 'whatsapp',
            'payload'    => json_encode($n, JSON_UNESCAPED_UNICODE),
            'status'     => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Processa fila de notificacoes pendentes. */
    public function processQueue(): array
    {
        $report = ['processed' => 0, 'failed' => 0];
        $items = DB::table('notification_queue')
            ->where('status', 'pending')
            ->orderBy('id')
            ->limit(50)
            ->get();

        foreach ($items as $item) {
            $payload = json_decode($item->payload, true) ?: [];
            try {
                match ($item->channel) {
                    'push'     => $this->processPush($payload),
                    'email'    => $this->processEmail($payload),
                    'whatsapp' => $this->processWhatsApp($payload),
                    default    => null,
                };
                DB::table('notification_queue')->where('id', $item->id)
                    ->update(['status' => 'sent', 'sent_at' => now(), 'updated_at' => now()]);
                $report['processed']++;
            } catch (\Throwable $e) {
                DB::table('notification_queue')->where('id', $item->id)
                    ->update(['status' => 'failed', 'error' => $e->getMessage(), 'updated_at' => now()]);
                $report['failed']++;
            }
        }
        return $report;
    }

    protected function processPush(array $payload): void
    {
        // Envia WebPush para todos os endpoints registrados
        $subscriptions = DB::table('push_subscriptions')->get();
        foreach ($subscriptions as $sub) {
            // Implementacao real requer web-push lib (minishlink/web-push)
            // Por ora registra como enviado para a estrutura funcionar
        }
    }

    protected function processEmail(array $payload): void
    {
        $recipients = $this->resolveEmailRecipients($payload);
        $subject = (string) ($payload['title'] ?? 'Notificacao');
        $message = (string) ($payload['message'] ?? $subject);
        $html = $this->emailLayoutRenderer->wrap(
            '<p style="margin:0 0 16px;">' . nl2br(e($message)) . '</p>',
            [
                'brand_name' => app(SettingsManager::class)->get('app.name', config('app.name')),
                'logo_url' => app(SettingsManager::class)->get('app.logo', ''),
                'footer_signature' => app(SettingsManager::class)->get('app.name', config('app.name')),
                'footer_text' => app(SettingsManager::class)->get('app.footer_text', 'Comunicado interno do sistema.'),
                'footer_subtext' => 'Notificacao administrativa enviada automaticamente.',
            ]
        );

        foreach ($recipients as $email) {
            Mail::html($html, function ($m) use ($email, $subject) {
                $m->to($email)->subject($subject);
            });
        }
    }

    protected function processWhatsApp(array $payload): void
    {
        $phone = $payload['whatsapp_to'] ?? $payload['data']['whatsapp'] ?? null;
        $tenantId = $payload['tenant_id'] ?? data_get($payload, 'recipients.tenant_id') ?? null;
        $context = [
            'tenant_id' => $tenantId,
            'origin' => 'notification_dispatcher',
            'campaign' => $payload['type'] ?? 'notification',
        ];

        if (! $phone) {
            return;
        }

        $template = null;
        if (! empty($payload['template_id'])) {
            $template = \App\Modules\Templates\Models\WhatsAppTemplate::find($payload['template_id']);
        } elseif (! empty($payload['template_slug'])) {
            $template = app(\App\Modules\Tenancy\Services\TenantTemplateResolver::class)
                ->resolveWhatsAppBySlug($tenantId ? (int) $tenantId : null, (string) $payload['template_slug']);
        } else {
            $template = app(\App\Modules\Tenancy\Services\TenantTemplateResolver::class)
                ->whatsapp($tenantId ? (int) $tenantId : null, null, 'notificacao', 'notificacao-sistema');
        }

        if (! $template || ! $template->active) {
            Log::warning('[Notifications] Envio WhatsApp cancelado: nenhum template ativo configurado para notificações.');
            return;
        }

        $title = (string) ($payload['title'] ?? 'Aviso do Sistema');
        $body = (string) ($payload['message'] ?? $title);
        $message = app(\App\Modules\Templates\Services\TemplateRenderer::class)->render($template, array_merge($payload['data'] ?? [], [
            'titulo' => $title,
            'title' => $title,
            'mensagem' => $body,
            'message' => $body,
            'whatsapp_suporte' => $payload['whatsapp_suporte'] ?? '',
        ]));

        if (trim($message) === '') {
            return;
        }

        $instance = $tenantId ? $this->resolveTenantInstance((int) $tenantId) : null;
        if ($instance) {
            ($this->instances ??= app(InstanceManager::class))->sendText($instance, $phone, $message, $context);

            return;
        }

        $inspection = ($this->safety ??= app(WhatsAppSafetyService::class))->inspect(null, $phone, $message, $context);
        if (! $inspection['allowed']) {
            $this->safety->recordOutcome($inspection, [
                'tenant_id' => $tenantId,
                'to' => $phone,
                'meta' => ['origin' => 'notification_dispatcher_fallback'],
            ]);

            return;
        }

        $evolution = app(EvolutionClient::class);
        if ($evolution->isConfigured()) {
            $evolution->sendText($phone, $message, array_merge($context, $inspection));
        }
    }

    /** Limpeza de notificacoes antigas. */
    public function cleanup(): void
    {
        DB::table('admin_notifications')
            ->whereNotNull('read_at')
            ->where('created_at', '<', now()->subDays(90))
            ->delete();

        DB::table('notification_queue')
            ->whereIn('status', ['sent', 'failed'])
            ->where('created_at', '<', now()->subDays(30))
            ->delete();
    }

    /** Resolve quais admin_user_ids devem receber. */
    protected function resolveRecipients(array $n): array
    {
        $recipients = $n['recipients'] ?? [];

        if (isset($recipients['admin_user_id'])) {
            return [(int) $recipients['admin_user_id']];
        }
        if (($recipients[0] ?? null) === 'all_admins' || empty($recipients)) {
            return DB::table('admin_users')->where('active', true)->pluck('id')->all();
        }
        if (is_string($recipients[0] ?? null) && str_starts_with($recipients[0], 'role:')) {
            $role = str_replace('role:', '', $recipients[0]);
            return DB::table('admin_user_roles')
                ->join('roles', 'roles.id', '=', 'admin_user_roles.role_id')
                ->where('roles.slug', $role)
                ->pluck('admin_user_roles.admin_user_id')
                ->all();
        }
        return array_filter(array_map('intval', (array) $recipients));
    }

    protected function resolveEmailRecipients(array $n): array
    {
        $emails = [];
        if (!empty($n['email_to'])) {
            $emails[] = $n['email_to'];
        }
        if (!empty($n['recipients']['email'])) {
            $emails[] = $n['recipients']['email'];
        }

        // Se a notificação for destinada a Tenant/Usuário
        $tenantPayload = $this->tenantRecipientPayload($n);
        if ($tenantPayload !== null) {
            $userId = $tenantPayload['user_id'] ?? null;
            $tenantId = $tenantPayload['tenant_id'] ?? null;

            if ($userId && Schema::hasTable('users')) {
                $userEmail = DB::table('users')->where('id', $userId)->value('email');
                if ($userEmail) {
                    $emails[] = $userEmail;
                }
            } elseif ($tenantId && Schema::hasTable('tenants') && Schema::hasTable('users')) {
                $tenantUserId = DB::table('tenants')->where('id', $tenantId)->value('user_id');
                if ($tenantUserId) {
                    $userEmail = DB::table('users')->where('id', $tenantUserId)->value('email');
                    if ($userEmail) {
                        $emails[] = $userEmail;
                    }
                }
            }
            return array_values(array_unique(array_filter($emails)));
        }

        // Destinatários Administradores
        $ids = $this->resolveRecipients($n);
        $adminEmails = DB::table('admin_users')->whereIn('id', $ids)->pluck('email')->all();
        return array_values(array_unique(array_filter(array_merge($emails, $adminEmails))));
    }

    protected function resolveTenantInstance(int $tenantId): ?TenantInstance
    {
        return TenantInstance::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }
}
