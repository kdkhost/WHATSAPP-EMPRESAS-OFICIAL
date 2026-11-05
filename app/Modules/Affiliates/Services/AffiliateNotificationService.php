<?php

namespace App\Modules\Affiliates\Services;

use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Notifications\Services\NotificationDispatcher;
use App\Modules\Settings\Services\SettingsManager;
use App\Modules\WhatsApp\Services\EvolutionClient;
use Illuminate\Support\Facades\Log;

class AffiliateNotificationService
{
    public function __construct(
        protected NotificationDispatcher $dispatcher,
        protected SettingsManager $settings,
    ) {}

    /**
     * Notifica quando o tenant submete a inscrição no programa de afiliados.
     */
    public function notifyApplicationSubmitted(Affiliate $affiliate): void
    {
        $user = $affiliate->user;
        $tenant = $affiliate->tenant;
        $appName = $this->settings->get('app.name', config('app.name', 'Sistema'));

        // 1. Notifica o próprio cliente (in-app, email, whatsapp)
        $msgCliente = "Olá, {$user->name}! 👋\n\n"
            . "Recebemos sua solicitação de cadastro no *Programa de Afiliados* da {$appName}.\n\n"
            . "📋 *Status:* Em Análise pela nossa equipe\n"
            . "📢 *Canal informado:* " . ($affiliate->promotion_channel ?: 'Divulgação Geral') . "\n\n"
            . "Assim que sua inscrição for analisada por nossos administradores, você receberá a confirmação por aqui e pelo e-mail com a liberação do seu link exclusivo e cupons de desconto!\n\n"
            . "Você pode acompanhar o status direto no seu painel: " . url('/painel/afiliado');

        $this->dispatchMultiChannel(
            affiliate: $affiliate,
            title: "Inscrição no Programa de Afiliados Recebida",
            message: $msgCliente,
            type: "affiliate_application_pending",
            icon: "bi-hourglass-split",
            color: "warning"
        );

        // 2. Notifica administradores in-app
        try {
            $this->dispatcher->send([
                'channels'   => ['in_app'],
                'group'      => 'registration',
                'type'       => 'affiliate_application',
                'title'      => 'Nova Inscrição de Afiliado',
                'message'    => "O cliente {$user->name} (" . ($tenant?->company_name ?? 'Tenant #' . $tenant?->id) . ") solicitou afiliação.",
                'url'        => route('admin.affiliates.index', ['status' => 'pending']),
                'icon'       => 'bi-person-plus-fill',
                'color'      => 'info',
                'recipients' => ['all_admins' => true],
            ]);
        } catch (\Throwable $e) {
            Log::warning("[AffiliateNotification] Erro ao notificar admin: " . $e->getMessage());
        }
    }

    /**
     * Notifica quando a afiliação é APROVADA.
     */
    public function notifyApproved(Affiliate $affiliate): void
    {
        $user = $affiliate->user;
        $appName = $this->settings->get('app.name', config('app.name', 'Sistema'));

        $commissionText = $affiliate->commission_mode === 'fixed'
            ? 'R$ ' . number_format((float) $affiliate->commission_fixed, 2, ',', '.')
            : number_format((float) $affiliate->commission_pct, 1, ',', '.') . '%';

        $discountText = $affiliate->discount_mode === 'fixed'
            ? 'R$ ' . number_format((float) $affiliate->discount_fixed, 2, ',', '.')
            : number_format((float) $affiliate->discount_pct, 1, ',', '.') . '%';

        $splitNotice = $affiliate->split_enabled
            ? "\n⚡ *Split Mercado Pago:* Ativado automaticamente direto na sua conta MP!"
            : "";

        $msg = "🎉 *Parabéns, {$user->name}! Seu cadastro de Afiliado foi APROVADO!*\n\n"
            . "Seja muito bem-vindo ao *Programa de Afiliados* da {$appName}.\n\n"
            . "🔗 *Seu Link Exclusivo de Divulgação:*\n{$affiliate->referral_url}\n\n"
            . "🎟 *Seu Cupom de Desconto para Clientes:*\n`{$affiliate->referral_code}` (Oferece {$discountText} de desconto na contratação)\n\n"
            . "💰 *Sua Comissão:* {$commissionText} ({$affiliate->commission_type_label}){$splitNotice}\n\n"
            . "Acesse o seu painel de afiliado agora mesmo para conferir cliques, conversões e saldo:\n"
            . url('/painel/afiliado');

        $this->dispatchMultiChannel(
            affiliate: $affiliate,
            title: "🎉 Parabéns! Sua Afiliação foi Aprovada!",
            message: $msg,
            type: "affiliate_approved",
            icon: "bi-check-circle-fill",
            color: "success"
        );
    }

    /**
     * Notifica quando a afiliação é REJEITADA.
     */
    public function notifyRejected(Affiliate $affiliate, string $reason): void
    {
        $user = $affiliate->user;
        $appName = $this->settings->get('app.name', config('app.name', 'Sistema'));

        $msg = "Olá, {$user->name}.\n\n"
            . "Gostaríamos de informar sobre o status da sua inscrição no *Programa de Afiliados* da {$appName}.\n\n"
            . "❌ *Status:* Inscrição Não Aprovada no Momento\n"
            . "📝 *Motivo / Justificativa:*\n{$reason}\n\n"
            . "Caso queira atualizar suas informações ou reenviar seus dados para nova análise, basta acessar o seu painel:\n"
            . url('/painel/afiliado');

        $this->dispatchMultiChannel(
            affiliate: $affiliate,
            title: "Atualização sobre sua inscrição de Afiliado",
            message: $msg,
            type: "affiliate_rejected",
            icon: "bi-x-circle-fill",
            color: "danger"
        );
    }

    /**
     * Notifica quando a afiliação é marcada como PENDENTE de informações.
     */
    public function notifyPending(Affiliate $affiliate, string $instructions): void
    {
        $user = $affiliate->user;
        $appName = $this->settings->get('app.name', config('app.name', 'Sistema'));

        $msg = "Olá, {$user->name}! ⚠️\n\n"
            . "Identificamos uma *pendência de informações* na sua inscrição no *Programa de Afiliados* da {$appName}.\n\n"
            . "📋 *Orientações da Administração:*\n{$instructions}\n\n"
            . "Por favor, acesse o painel para complementar seus dados e reenviar para aprovação:\n"
            . url('/painel/afiliado');

        $this->dispatchMultiChannel(
            affiliate: $affiliate,
            title: "Ação Necessária: Pendência em seu cadastro de Afiliado",
            message: $msg,
            type: "affiliate_pending_instructions",
            icon: "bi-exclamation-triangle-fill",
            color: "warning"
        );
    }

    /**
     * Envia nos canais e-mail, in-app e WhatsApp direto.
     */
    protected function dispatchMultiChannel(
        Affiliate $affiliate,
        string $title,
        string $message,
        string $type,
        string $icon,
        string $color
    ): void {
        $user = $affiliate->user;
        $tenant = $affiliate->tenant;

        if (! $user) {
            return;
        }

        // 1. Envio de Notificação in-app e E-mail
        try {
            $channels = ['in_app'];
            if (!empty($user->email)) {
                $channels[] = 'email';
            }

            $this->dispatcher->send([
                'channels'   => $channels,
                'group'      => 'registration',
                'type'       => $type,
                'title'      => $title,
                'message'    => $message,
                'url'        => '/painel/afiliado',
                'icon'       => $icon,
                'color'      => $color,
                'recipients' => [
                    'tenant_id' => $tenant?->id,
                    'user_id'   => $user->id,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning("[AffiliateNotification] Falha ao despachar in_app/email: " . $e->getMessage());
        }

        // 2. Envio via WhatsApp
        $this->sendDirectWhatsApp($affiliate, $message);
    }

    /**
     * Envia mensagem no WhatsApp do usuário/tenant via EvolutionClient.
     */
    protected function sendDirectWhatsApp(Affiliate $affiliate, string $message): void
    {
        $phone = $this->resolvePhoneNumber($affiliate);
        if (! $phone) {
            Log::info("[AffiliateNotification] WhatsApp não enviado: telefone ausente para user #{$affiliate->user_id}");
            return;
        }

        try {
            $evolution = app(EvolutionClient::class);
            if (! $evolution->isConfigured()) {
                Log::info("[AffiliateNotification] EvolutionClient não configurado para envio de WhatsApp.");
                return;
            }

            $evolution->sendText($phone, $message, [
                'tenant_id' => $affiliate->tenant_id,
                'user_id'   => $affiliate->user_id,
                'origin'    => 'affiliate_notification',
            ]);

            Log::info("[AffiliateNotification] Mensagem WhatsApp enviada com sucesso para {$phone}");
        } catch (\Throwable $e) {
            Log::warning("[AffiliateNotification] Falha ao enviar WhatsApp para {$phone}: " . $e->getMessage());
        }
    }

    /**
     * Resolve o número de telefone com DDI (55) formatado.
     */
    public function resolvePhoneNumber(Affiliate $affiliate): ?string
    {
        $user = $affiliate->user;
        $tenant = $affiliate->tenant;

        $phone = $user?->phone 
            ?: $user?->whatsapp 
            ?: $tenant?->whatsapp 
            ?: $tenant?->phone;

        // Se houver chave PIX de telefone salva em payment_info
        if (! $phone && !empty($affiliate->payment_info['pix_type']) && $affiliate->payment_info['pix_type'] === 'phone') {
            $phone = $affiliate->payment_info['pix_key'] ?? null;
        }

        if (! $phone) {
            return null;
        }

        $clean = preg_replace('/\D/', '', (string) $phone);
        if (strlen($clean) < 10) {
            return null;
        }

        if (! str_starts_with($clean, '55') && (strlen($clean) === 10 || strlen($clean) === 11)) {
            $clean = '55' . $clean;
        }

        return $clean;
    }
}
