<?php

namespace App\Modules\Templates\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppTemplate extends Model
{
    protected $table = 'whatsapp_templates';

    protected $fillable = [
        'tenant_id', 'parent_id', 'name', 'slug', 'category', 'schedule_offset',
        'content_html', 'content_text', 'variables', 'active',
        'scope', 'is_system', 'is_default', 'locked', 'meta',
    ];

    protected $casts = [
        'variables' => 'array',
        'active'    => 'boolean',
        'is_system' => 'boolean',
        'is_default'=> 'boolean',
        'locked'    => 'boolean',
        'meta'      => 'array',
    ];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function customizations()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function isCustomizedFor(?int $tenantId): bool
    {
        if (! $tenantId) return false;
        if ($this->tenant_id === $tenantId) return true;

        return self::where('tenant_id', $tenantId)
            ->where('parent_id', $this->id)
            ->exists();
    }

    public function getCustomizationFor(?int $tenantId): ?self
    {
        if (! $tenantId) return null;
        if ($this->tenant_id === $tenantId) return $this;

        return self::where('tenant_id', $tenantId)
            ->where('parent_id', $this->id)
            ->first();
    }

    /** Todas as categorias suportadas no sistema. */
    public static function categories(): array
    {
        return [
            'cobranca'            => 'Cobrança',
            'lembrete'            => 'Lembrete',
            'atraso'              => 'Atraso',
            'agradecimento'       => 'Confirmação de Pagamento',
            'pix'                 => 'Pix Copia e Cola',
            'contrato'            => 'Contrato p/ Assinatura',
            'contrato_assinado'   => 'Contrato Assinado',
            'pacote_cancelamento' => 'Cancelamento de Pacote',
            'pacote_pagamento'    => 'Confirmação de Parcela',
            'notificacao'         => 'Notificação do Sistema',
            'manual'              => 'Envio Manual',
            'boas_vindas'         => 'Boas-vindas',
            'suporte'             => 'Suporte',
            'marketing'           => 'Marketing',
            'vendas'              => 'Vendas',
            'custom'              => 'Customizado',
        ];
    }

    /** Janelas e offsets de agendamento disponíveis. */
    public static function scheduleOffsets(): array
    {
        return [
            ''     => 'Manual / Disparo por Evento',
            '-3d'  => '3 dias antes do vencimento',
            '-1d'  => '1 dia antes do vencimento',
            '0d'   => 'No dia do vencimento',
            '+1d'  => '1 dia após vencimento',
            '+3d'  => '3 dias após vencimento',
            '+7d'  => '7 dias após vencimento',
            '+15d' => '15 dias após vencimento',
        ];
    }

    /** Classe de cor para badge de categoria. */
    public function categoryBadgeClass(): string
    {
        return match ($this->category) {
            'cobranca'            => 'primary',
            'lembrete'            => 'info',
            'atraso'              => 'danger',
            'agradecimento'       => 'success',
            'pix'                 => 'teal',
            'contrato'            => 'warning',
            'contrato_assinado'   => 'success',
            'pacote_cancelamento' => 'danger',
            'pacote_pagamento'    => 'success',
            'notificacao'         => 'dark',
            'manual'              => 'secondary',
            'boas_vindas'         => 'primary',
            'suporte'             => 'info',
            'marketing'           => 'warning',
            'vendas'              => 'primary',
            default               => 'secondary',
        };
    }

    /** Rótulo legível da categoria. */
    public function categoryLabel(): string
    {
        return self::categories()[$this->category] ?? ucfirst($this->category);
    }

    /** Variáveis disponíveis organizadas por grupo temático. */
    public static function availableVariables(): array
    {
        return [
            // Dados do Cliente
            '{nome}'               => 'Nome completo do cliente',
            '{primeiro_nome}'      => 'Primeiro nome do cliente',
            '{email}'              => 'E-mail cadastrado do cliente',
            '{telefone}'           => 'WhatsApp / Telefone do cliente',
            '{documento}'          => 'CPF ou CNPJ do cliente',
            '{cpf}'                => 'CPF do cliente (apenas números ou formatado)',
            '{cnpj}'               => 'CNPJ do cliente (apenas números ou formatado)',
            '{nascimento}'         => 'Data de nascimento formatada (dd/mm/aaaa)',
            '{data_nascimento}'    => 'Data de nascimento formatada (dd/mm/aaaa)',
            '{tipo_pessoa}'        => 'Tipo de pessoa (Pessoa Física ou Pessoa Jurídica)',

            // Fatura e Cobrança
            '{numero_fatura}'      => 'Número identificador da fatura',
            '{valor}'              => 'Valor total formatado em R$ (ex.: R$ 150,00)',
            '{valor_original}'     => 'Valor original antes de juros ou multas',
            '{valor_atualizado}'   => 'Valor recalculado com acréscimos por atraso',
            '{vencimento}'         => 'Data de vencimento formatada (dd/mm/aaaa)',
            '{dias_atraso}'        => 'Quantidade de dias em atraso',
            '{descricao}'          => 'Descrição ou itens da cobrança',
            '{link_pagamento}'     => 'Link direto para pagamento / checkout',
            '{boleto_url}'         => 'URL do boleto bancário em PDF ou visualização',
            '{linha_digitavel}'    => 'Código de barras / Linha digitável do boleto',
            '{metodo_pagamento}'   => 'Método de pagamento configurado',
            '{forma_pagamento}'    => 'Forma de pagamento utilizada (ex.: PIX, Boleto)',
            '{data_pagamento}'     => 'Data e hora em que o pagamento foi confirmado',

            // Pix e QR Code
            '{pix_copia_cola}'     => 'Código Pix no formato Copia e Cola',
            '{qrcode_img}'         => 'Tag HTML com imagem do QR Code (HTML / E-mail / Preview)',
            '{qrcode_url}'         => 'Link direto ou Data URI da imagem do QR Code',
            '{qrcode_base64}'      => 'Código Base64 da imagem do QR Code Pix',
            '{qrcode_pix}'         => 'Imagem ou link do QR Code Pix',
            '{qrcode_instrucao}'   => 'Instrução automática de pagamento via QR Code',

            // Serviços e Pacotes
            '{nome_servico}'       => 'Nome do plano ou serviço contratado',
            '{servico_principal}'  => 'Serviço principal vinculado à fatura',
            '{servicos}'           => 'Lista completa de serviços vinculados',
            '{servicos_ativos}'    => 'Lista de serviços atualmente ativos',
            '{servicos_suspensos}' => 'Lista de serviços suspensos por pendência',
            '{status_servico}'     => 'Status atual do serviço principal',
            '{parcela_atual}'      => 'Número da parcela vigente (ex.: 2)',
            '{parcelas_total}'     => 'Total de parcelas do pacote (ex.: 12)',
            '{parcelas_pagas}'     => 'Total de parcelas já quitadas',
            '{parcelas_pendentes}' => 'Total de parcelas pendentes de pagamento',
            '{resumo_parcelas}'    => 'Resumo das parcelas (ex.: 2/12)',
            '{proximo_vencimento}' => 'Data do próximo vencimento agendado',

            // Contratos
            '{contrato_numero}'    => 'Número do contrato',
            '{contrato_titulo}'    => 'Título do contrato',
            '{link_contrato}'      => 'URL pública para visualização/download do contrato',
            '{link_assinatura}'    => 'Link exclusivo para assinatura eletrônica',
            '{data_assinatura}'    => 'Data e hora da confirmação da assinatura',

            // Notificações e Avisos Gerais
            '{titulo}'             => 'Título do aviso ou comunicado',
            '{mensagem}'           => 'Conteúdo principal da mensagem',

            // Empresa e Suporte
            '{empresa}'            => 'Nome da empresa emissora',
            '{whatsapp_suporte}'   => 'Número de WhatsApp do suporte',
            '{email_suporte}'      => 'E-mail de suporte e atendimento',
            '{telefone_suporte}'   => 'Telefone geral de atendimento',

            // Integrações WHMCS
            '{whmcs_cliente_id}'   => 'ID do cliente no WHMCS',
            '{whmcs_fatura_id}'    => 'ID da fatura no WHMCS',
        ];
    }

    /** Converte HTML formatado para markdown nativo do WhatsApp. */
    public static function htmlToWhatsApp(string $html): string
    {
        $text = $html;

        // Trata imagens (converte QR Code para aviso amigável de anexo)
        $text = preg_replace_callback('/<img\s+[^>]*>/i', function ($matches) {
            $tag = $matches[0];
            if (preg_match('/(qrcode|qr-code|pix|\{qrcode)/i', $tag)) {
                return "\n\n📱 *[QR Code Pix enviado em anexo]*\n\n";
            }
            if (preg_match('/alt=["\']([^"\']+)["\']/i', $tag, $altMatches)) {
                $alt = trim($altMatches[1]);
                if ($alt !== '') {
                    return "\n\n[" . $alt . "]\n\n";
                }
            }
            return '';
        }, $text);

        // Converte quebras de bloco e parágrafos em quebras de linha
        $text = preg_replace('/<\s*br\s*\/?>/i', "\n", $text);
        $text = preg_replace('/<\s*\/\s*p\s*>/i', "\n\n", $text);
        $text = preg_replace('/<\s*p[^>]*>/i', '', $text);
        $text = preg_replace('/<\s*\/\s*div\s*>/i', "\n", $text);
        $text = preg_replace('/<\s*div[^>]*>/i', '', $text);
        $text = preg_replace('/<\s*\/\s*li\s*>/i', "\n", $text);
        $text = preg_replace('/<\s*li[^>]*>/i', "• ", $text);

        // Formatação WhatsApp: negrito, itálico, tachado e código
        $text = preg_replace('/<\s*(strong|b)[^>]*>(.*?)<\s*\/\s*(strong|b)\s*>/is', '*$2*', $text);
        $text = preg_replace('/<\s*(em|i)[^>]*>(.*?)<\s*\/\s*(em|i)\s*>/is', '_$2_', $text);
        $text = preg_replace('/<\s*(strike|s|del)[^>]*>(.*?)<\s*\/\s*(strike|s|del)\s*>/is', '~$2~', $text);
        $text = preg_replace('/<\s*(pre|code)[^>]*>(.*?)<\s*\/\s*(pre|code)\s*>/is', '```$2```', $text);

        // Links
        $text = preg_replace('/<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/i', '$2: $1', $text);

        // Remove tags restantes e decodifica entidades HTML
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Sanitiza quebras de linha múltiplas
        return preg_replace("/\n{3,}/", "\n\n", trim($text));
    }
}
