<?php

namespace App\Notifications;

use App\Modules\EmailBilling\Services\EmailLayoutRenderer;
use App\Modules\Settings\Services\SettingsManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $settings = app(SettingsManager::class);
        $layout = app(EmailLayoutRenderer::class);
        $appName = $settings->get('app.name', config('app.name'));
        $resetUrl = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));
        $expireMinutes = (int) config('auth.passwords.users.expire', 60);

        $contentHtml = '<p style="margin:0 0 16px;">Ola <strong>' . e($notifiable->name ?? $notifiable->email) . '</strong>,</p>'
            . '<p style="margin:0 0 16px;">Recebemos uma solicitacao para redefinir a senha da sua conta.</p>'
            . '<p style="margin:28px 0;text-align:center;">'
            . '<a href="' . e($resetUrl) . '" style="display:inline-block;background:#0f172a;color:#ffffff;text-decoration:none;padding:14px 24px;border-radius:10px;font-weight:700;">Redefinir senha</a>'
            . '</p>'
            . '<div style="margin:22px 0;padding:16px 18px;border-radius:14px;background:#f8fafc;border:1px solid #e5e7eb;">'
            . '<div style="font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#64748b;margin-bottom:6px;">Informacoes importantes</div>'
            . '<div style="font-size:14px;color:#0f172a;line-height:1.7;">Este link expira em <strong>' . $expireMinutes . ' minuto(s)</strong>. Se voce nao solicitou a redefinicao, basta ignorar este email.</div>'
            . '</div>'
            . '<p style="margin:0;color:#64748b;font-size:13px;">Por seguranca, nao compartilhe este link com terceiros.</p>';

        $html = $layout->wrap($contentHtml, [
            'brand_name' => $appName,
            'logo_url' => $settings->get('app.logo', ''),
            'primary_color' => $settings->get('theme.primary', '#0d6efd'),
            'accent_color' => $settings->get('theme.secondary', '#6610f2'),
            'footer_signature' => $appName,
            'footer_text' => $settings->get('app.footer_text', 'Este email faz parte do fluxo seguro de acesso ao sistema.'),
            'footer_subtext' => 'Mensagem automatica de redefinicao de senha.',
        ]);

        return (new MailMessage)
            ->subject('Redefinicao de senha - ' . $appName)
            ->view('emails.raw-html', ['html' => $html]);
    }
}
