<?php

namespace App\Modules\EmailBilling\Services;

use App\Modules\EmailBilling\Models\EmailMessage;
use App\Modules\EmailBilling\Models\TenantEmailConfig;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Transport;

/**
 * Mailer dinamico que envia usando configuracao SMTP do tenant.
 * Cada tenant tem seu proprio servidor de email.
 *
 * Suporta SMTP padrao + SendGrid/Mailgun/SES via API key.
 */
class SmtpMailer
{
    protected array $backoffMinutes = [1, 5, 15, 60, 240];

    public function __construct(protected EmailLayoutRenderer $layoutRenderer) {}

    /** Envia email com a configuracao do tenant. */
    public function send(
        Tenant $tenant,
        string $to,
        string $subject,
        string $bodyHtml,
        ?string $bodyText = null,
        ?array $attachments = null
    ): array
    {
        $config = $tenant->emailConfig;
        if (!$config || !$config->enabled) {
            return ['ok' => false, 'error' => 'Email nao configurado pelo tenant.'];
        }

        // UTF-8 garantido em assunto e corpo
        $subject = mb_convert_encoding($subject, 'UTF-8', 'auto');
        $bodyHtml = mb_convert_encoding($bodyHtml, 'UTF-8', 'auto');

        try {
            $messageId = $this->generateMessageId();
            $this->deliver($config, $tenant->id, $to, $subject, $bodyHtml, $bodyText, $messageId, $attachments);

            $log = EmailMessage::create([
                'tenant_id' => $tenant->id,
                'to'        => $to,
                'subject'   => $subject,
                'body_html' => mb_substr($bodyHtml, 0, 65000),
                'status'    => 'sent',
                'message_id'=> $messageId,
                'sent_at'   => now(),
            ]);

            return ['ok' => true, 'message_id' => $messageId, 'log_id' => $log->id];

        } catch (\Throwable $e) {
            Log::error("[Email tenant={$tenant->id}] {$e->getMessage()}");
            EmailMessage::create([
                'tenant_id' => $tenant->id,
                'to'        => $to,
                'subject'   => $subject,
                'body_html' => mb_substr($bodyHtml, 0, 65000),
                'status'    => 'failed',
                'error'     => mb_substr($e->getMessage(), 0, 1000),
            ]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** Enfileira email para processamento em lote (anti-bloqueio/spam). */
    public function enqueue(
        Tenant $tenant,
        string $to,
        string $subject,
        string $bodyHtml,
        ?string $bodyText = null,
        ?int $dispatchId = null,
        ?array $attachments = null
    ): array
    {
        $config = $tenant->emailConfig;
        if (! $config || ! $config->enabled) {
            return ['ok' => false, 'error' => 'Email nao configurado pelo tenant.'];
        }

        $headers = ['body_text' => $bodyText, 'attachments' => $attachments ?: []];
        $log = EmailMessage::create([
            'tenant_id'        => $tenant->id,
            'dispatch_id'      => $dispatchId,
            'to'               => $to,
            'subject'          => mb_convert_encoding($subject, 'UTF-8', 'auto'),
            'body_html'        => mb_substr(mb_convert_encoding($bodyHtml, 'UTF-8', 'auto'), 0, 65000),
            'status'           => 'queued',
            'attempts'         => 0,
            'max_attempts'     => (int) config('modules.email_queue.max_attempts', 5),
            'next_attempt_at'  => now(),
            'headers'          => $headers,
        ]);

        return ['ok' => true, 'queued' => true, 'log_id' => $log->id];
    }

    /** Processa fila de emails pendentes com limite por tenant. */
    public function processQueue(int $limit = 100): array
    {
        $report = ['processed' => 0, 'sent' => 0, 'failed' => 0, 'deferred' => 0];
        $workerId = gethostname() . ':' . getmypid();
        $lockedUntil = now()->subMinutes(10);
        $ratePerHour = (int) config('modules.email_queue.per_tenant_per_hour', 120);

        $rows = EmailMessage::query()
            ->whereIn('status', ['queued', 'failed'])
            ->where(function ($q) {
                $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now());
            })
            ->where(function ($q) use ($lockedUntil) {
                $q->whereNull('locked_at')->orWhere('locked_at', '<', $lockedUntil);
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($rows as $row) {
            $claimed = EmailMessage::where('id', $row->id)
                ->where(function ($q) use ($lockedUntil) {
                    $q->whereNull('locked_at')->orWhere('locked_at', '<', $lockedUntil);
                })
                ->update(['locked_at' => now(), 'locked_by' => $workerId]);

            if (! $claimed) {
                continue;
            }

            $report['processed']++;
            $fresh = EmailMessage::find($row->id);
            if (! $fresh) {
                continue;
            }

            $sentLastHour = EmailMessage::query()
                ->where('tenant_id', $fresh->tenant_id)
                ->where('status', 'sent')
                ->where('sent_at', '>=', now()->subHour())
                ->count();

            if ($sentLastHour >= $ratePerHour) {
                $fresh->update([
                    'status' => 'queued',
                    'next_attempt_at' => now()->addMinutes(15),
                    'locked_at' => null,
                    'locked_by' => null,
                ]);
                $report['deferred']++;
                continue;
            }

            $r = $this->sendQueuedMessage($fresh);
            if ($r['ok'] ?? false) {
                $report['sent']++;
            } else {
                $report['failed']++;
            }
        }

        return $report;
    }

    /** Testa configuracao enviando email de teste. */
    public function test(TenantEmailConfig $config, string $to): array
    {
        try {
            $bodyHtml = $this->buildTestBodyHtml($config);
            $this->deliver(
                $config,
                null,
                $to,
                '[Teste] Configuracao de email',
                $bodyHtml,
                "Este eh um email de teste do sistema de cobrancas.\n\nSe voce recebeu este email, sua configuracao SMTP esta funcionando corretamente.\n\nEnviado em: " . now()->format('d/m/Y H:i:s'),
                $this->generateMessageId()
            );

            $config->update([
                'last_test_at'     => now(),
                'last_test_status' => 'ok',
                'last_test_error'  => null,
            ]);

            return ['ok' => true];
        } catch (\Throwable $e) {
            $config->update([
                'last_test_at'     => now(),
                'last_test_status' => 'error',
                'last_test_error'  => $e->getMessage(),
            ]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    protected function sendQueuedMessage(EmailMessage $message): array
    {
        $tenant = Tenant::find($message->tenant_id);
        $config = $tenant?->emailConfig;

        if (! $tenant || ! $config || ! $config->enabled) {
            $message->update([
                'status' => 'failed',
                'error' => 'Configuracao de email do tenant indisponivel.',
                'attempts' => $message->attempts + 1,
                'next_attempt_at' => null,
                'locked_at' => null,
                'locked_by' => null,
            ]);
            return ['ok' => false, 'error' => 'tenant-sem-config'];
        }

        // Bloqueia envio de e-mail se a fatura já foi paga, cancelada ou estornada
        if ($message->dispatch_id) {
            $dispatch = \Illuminate\Support\Facades\DB::table('billing_dispatches')->where('id', $message->dispatch_id)->first();
            if ($dispatch) {
                $invoice = \Illuminate\Support\Facades\DB::table('invoices')->where('id', $dispatch->invoice_id)->first();
                $isInactive = false;
                if ($invoice) {
                    $localStatus = strtolower((string) $invoice->status);
                    $invMeta = is_string($invoice->meta) ? (json_decode($invoice->meta, true) ?: []) : ((array) ($invoice->meta ?? []));
                    $whmcsStatus = strtolower((string) ($invMeta['whmcs_status'] ?? ''));

                    $inactiveList = ['paid', 'paga', 'pago', 'canceled', 'cancelled', 'cancelada', 'cancelado', 'refunded', 'estornada', 'estornado'];
                    if (in_array($localStatus, $inactiveList, true) || in_array($whmcsStatus, $inactiveList, true)) {
                        $isInactive = true;
                    }
                }

                if ($dispatch->status === 'canceled' || $isInactive) {
                    $message->update([
                        'status' => 'failed',
                        'error' => 'Cancelado pelo sistema: fatura paga, cancelada ou estornada (envio de e-mail bloqueado).',
                        'next_attempt_at' => null,
                        'locked_at' => null,
                        'locked_by' => null,
                    ]);
                    return ['ok' => false, 'error' => 'fatura-cancelada-ou-estornada'];
                }
            }
        }

        try {
            $messageId = $message->message_id ?: $this->generateMessageId();
            $bodyText = $message->headers['body_text'] ?? null;
            $attachments = $message->headers['attachments'] ?? null;

            $this->deliver($config, $tenant->id, $message->to, $message->subject, $message->body_html, $bodyText, $messageId, $attachments);

            $message->update([
                'status' => 'sent',
                'message_id' => $messageId,
                'error' => null,
                'sent_at' => now(),
                'locked_at' => null,
                'locked_by' => null,
            ]);
            return ['ok' => true];
        } catch (\Throwable $e) {
            $attempts = $message->attempts + 1;
            $maxAttempts = max(1, (int) $message->max_attempts);
            $delay = $this->backoffMinutes[min($attempts - 1, count($this->backoffMinutes) - 1)] ?? 240;

            $message->update([
                'status' => $attempts >= $maxAttempts ? 'failed' : 'queued',
                'attempts' => $attempts,
                'error' => mb_substr($e->getMessage(), 0, 1000),
                'next_attempt_at' => $attempts >= $maxAttempts ? null : now()->addMinutes($delay),
                'locked_at' => null,
                'locked_by' => null,
            ]);
            Log::warning("[EmailQueue tenant={$message->tenant_id}] {$e->getMessage()}");
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    protected function buildTransport(TenantEmailConfig $config): \Symfony\Component\Mailer\Transport\TransportInterface
    {
        // SMTP padrao
        if ($config->driver === 'smtp') {
            $scheme = $config->encryption === 'ssl' ? 'smtps' : 'smtp';
            $username = urlencode((string) $config->username);
            $password = urlencode((string) $config->password);
            $dsn = "{$scheme}://{$username}:{$password}@{$config->host}:{$config->port}";
            return Transport::fromDsn($dsn);
        }

        // SendGrid/Mailgun/SES via API key
        $dsn = match ($config->driver) {
            'sendgrid' => "sendgrid+api://{$config->api_key}@default",
            'mailgun'  => "mailgun+api://{$config->api_key}:".($config->settings['domain'] ?? '')."@default",
            'ses'      => "ses+api://".($config->settings['region'] ?? 'us-east-1')."?access_key={$config->username}&secret_key={$config->api_key}",
            default    => throw new \RuntimeException('Driver nao suportado: ' . $config->driver),
        };
        return Transport::fromDsn($dsn);
    }

    protected function deliver(
        TenantEmailConfig $config,
        ?int $tenantId,
        string $to,
        string $subject,
        string $bodyHtml,
        ?string $bodyText,
        string $messageId,
        ?array $attachments = null
    ): void {
        $transport = $this->buildTransport($config);
        $mailer = new \Symfony\Component\Mailer\Mailer($transport);
        $email = (new \Symfony\Component\Mime\Email())
            ->from($config->from_email)
            ->to($to)
            ->subject($subject);

        if ($bodyHtml !== '') {
            $branding = $this->layoutRenderer->brandingFromTenantConfig($config, $config->tenant);
            $email->html($this->layoutRenderer->wrap($bodyHtml, $branding));
        }
        if ($bodyText) {
            $email->text($bodyText);
        }
        if ($config->reply_to) {
            $email->replyTo($config->reply_to);
        }

        foreach (($attachments ?: []) as $attachment) {
            $path = $attachment['path'] ?? null;
            if (! $path || ! is_file($path)) {
                continue;
            }

            $email->attachFromPath(
                $path,
                $attachment['name'] ?? basename($path),
                $attachment['mime'] ?? null
            );
        }

        $email->getHeaders()->addIdHeader('Message-ID', $this->normalizeMessageId($messageId));
        if ($tenantId) {
            $email->getHeaders()->addTextHeader('X-Tenant-Id', (string) $tenantId);
        }

        $mailer->send($email);
    }

    protected function generateMessageId(): string
    {
        $host = parse_url(config('app.url'), PHP_URL_HOST);
        $host = is_string($host) && $host !== '' ? $host : 'localhost.localdomain';
        $host = preg_replace('/[^a-z0-9.-]/i', '', $host) ?: 'localhost.localdomain';

        return bin2hex(random_bytes(16)).'@'.$host;
    }

    protected function normalizeMessageId(string $messageId): string
    {
        return trim($messageId, " <>\t\n\r\0\x0B");
    }

    protected function buildTestBodyHtml(TenantEmailConfig $config): string
    {
        $brandName = e(($config->settings['brand_name'] ?? null) ?: $config->from_name ?: 'Sua empresa');
        $timestamp = now('America/Sao_Paulo')->format('d/m/Y H:i:s');

        return '<p style="margin:0 0 16px;">Ola,</p>'
            . '<p style="margin:0 0 16px;">Este e um email de teste da configuracao transacional do sistema.</p>'
            . '<p style="margin:0 0 16px;">Se voce recebeu esta mensagem com o layout completo, a estrutura obrigatoria de envio da conta <strong>' . $brandName . '</strong> esta funcionando corretamente.</p>'
            . '<div style="margin:22px 0;padding:16px 18px;border-radius:14px;background:#f8fafc;border:1px solid #e5e7eb;">'
            . '<div style="font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#64748b;margin-bottom:6px;">Dados do teste</div>'
            . '<div style="font-size:14px;color:#0f172a;"><strong>Momento do envio:</strong> ' . e($timestamp) . '</div>'
            . '<div style="font-size:14px;color:#0f172a;"><strong>Remetente:</strong> ' . e($config->from_email) . '</div>'
            . '<div style="font-size:14px;color:#0f172a;"><strong>Driver:</strong> ' . e(strtoupper((string) $config->driver)) . '</div>'
            . '</div>'
            . '<p style="margin:0;color:#64748b;font-size:13px;">Se este email chegou sem o visual padrao, existe algum bypass no fluxo e ele deve ser corrigido.</p>';
    }
}
