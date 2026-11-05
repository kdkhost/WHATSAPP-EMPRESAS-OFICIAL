<?php

namespace App\Modules\EmailBilling\Models;

use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailTemplate extends Model
{
    protected $fillable = [
        'tenant_id', 'name', 'slug', 'category', 'schedule_offset',
        'subject', 'body_html', 'body_text', 'variables', 'active',
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

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }

    /** Mesmas variaveis dos templates de WhatsApp (consistencia). */
    public static function availableVariables(): array
    {
        return [
            '{nome}'             => 'Nome completo do cliente',
            '{primeiro_nome}'    => 'Primeiro nome do cliente',
            '{email}'            => 'E-mail do cliente',
            '{valor}'            => 'Valor formatado em R$ (ex.: R$ 1.234,56)',
            '{valor_original}'   => 'Valor original antes de multa e juros',
            '{valor_atualizado}' => 'Valor recalculado com atraso',
            '{vencimento}'       => 'Data de vencimento (dd/mm/aaaa)',
            '{dias_atraso}'      => 'Dias em atraso',
            '{link_pagamento}'   => 'URL do checkout transparente',
            '{boleto_url}'       => 'Link direto para boleto/segunda via',
            '{linha_digitavel}'  => 'Linha digitavel do boleto',
            '{pix_copia_cola}'   => 'Codigo Pix copia e cola',
            '{qrcode_url}'       => 'URL da imagem do QR Code de pagamento',
            '{qrcode_pix_html}'  => 'Bloco HTML do QR Code para email',
            '{qrcode_instrucao}' => 'Instrucao automatica quando houver QR Code',
            '{numero_fatura}'    => 'Numero da fatura',
            '{descricao}'        => 'Descricao da cobranca',
            '{empresa}'          => 'Nome da empresa',
            '{logo_empresa}'     => 'URL da logomarca da empresa',
            '{logo_html}'        => 'Bloco HTML da logomarca formatada',
            '{cor_primaria}'     => 'Cor primária do tema/marca',
            '{cor_destaque}'     => 'Cor de destaque/secundária do tema/marca',
            '{whatsapp_suporte}' => 'WhatsApp de suporte',
            '{email_suporte}'    => 'E-mail de suporte',
            '{telefone_suporte}' => 'Telefone de suporte',
            '{data_cadastro}'    => 'Data de cadastro do cliente',
            '{data_evento}'      => 'Data do evento ou aviso',
            '{data_contrato}'    => 'Data do contrato',
            '{numero_contrato}'  => 'Numero do contrato',
            '{titulo_contrato}'  => 'Titulo do contrato',
            '{link_contrato}'    => 'URL publica do contrato',
            '{motivo}'           => 'Motivo do aviso',
            '{status_servico}'   => 'Status atual do servico',
            '{nome_servico}'     => 'Nome do servico/plano',
            '{servico_principal}' => 'Servico principal vinculado a fatura',
            '{servicos}'         => 'Lista de servicos vinculados',
            '{servicos_ativos}'  => 'Lista de servicos ativos',
            '{parcela_atual}'    => 'Numero da parcela atual',
            '{parcelas_total}'   => 'Total de parcelas',
            '{parcelas_pagas}'   => 'Quantidade de parcelas pagas',
            '{parcelas_pendentes}' => 'Quantidade de parcelas pendentes',
            '{resumo_parcelas}'  => 'Resumo de parcelas pagas e pendentes',
            '{whmcs_cliente_id}' => 'ID do cliente no WHMCS',
            '{whmcs_fatura_id}'  => 'ID da fatura no WHMCS',
            '{data_suspensao}'   => 'Data da suspensao',
            '{data_cancelamento}'=> 'Data do cancelamento',
            '{link_reativacao}'  => 'URL para reativar a conta ou servico',
            '{cupom}'            => 'Cupom promocional',
            '{desconto}'         => 'Desconto promocional',
            '{data_limite}'      => 'Data limite da campanha ou acao',
            '{status_pagamento}' => 'Status do pagamento',
            '{data_pagamento}'   => 'Data do pagamento',
            '{metodo_pagamento}' => 'Metodo de pagamento',
            '{id_transacao}'     => 'Identificador da transacao',
            '{ano_atual}'        => 'Ano atual (ex.: 2026)',
        ];
    }
}
