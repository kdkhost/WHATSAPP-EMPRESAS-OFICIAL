<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('whatsapp_templates')) {
            // 1. Template padrão de Cobrança Gerada (Envio Imediato / Manual)
            $cobrancaGeradaHtml = '<p>Olá <strong>{primeiro_nome}</strong>, tudo bem?</p><p>Uma nova cobrança foi gerada para você com vencimento em <strong>{vencimento}</strong>.</p><p><strong>Fatura:</strong> #{numero_fatura}<br><strong>Serviço:</strong> {servico_principal}<br><strong>Valor:</strong> {valor}<br><strong>Vencimento:</strong> {vencimento}<br><strong>Parcelas:</strong> {resumo_parcelas}</p><p>Acesse o link seguro para pagamento:<br>{link_pagamento}</p><p>{qrcode_instrucao}</p><p>Pix Copia e Cola:<br>```{pix_copia_cola}```</p><p>Atenciosamente,<br><strong>{empresa}</strong></p>';
            $cobrancaGeradaText = "Olá *{primeiro_nome}*, tudo bem?\n\nUma nova cobrança foi gerada para você com vencimento em *{vencimento}*.\n\n*Fatura:* #{numero_fatura}\n*Serviço:* {servico_principal}\n*Valor:* {valor}\n*Vencimento:* {vencimento}\n*Parcelas:* {resumo_parcelas}\n\n*Acesse o link seguro para pagamento:*\n{link_pagamento}\n\n{qrcode_instrucao}\n\n*Pix Copia e Cola:*\n```{pix_copia_cola}```\n\nAtenciosamente,\n*{empresa}*";

            $exists = DB::table('whatsapp_templates')->where('slug', 'cobranca-gerada')->first();
            if ($exists) {
                DB::table('whatsapp_templates')->where('id', $exists->id)->update([
                    'name'            => 'Nova Cobrança Gerada',
                    'category'        => 'cobranca',
                    'schedule_offset' => 'manual',
                    'content_html'    => $cobrancaGeradaHtml,
                    'content_text'    => $cobrancaGeradaText,
                    'scope'           => 'system',
                    'is_system'       => true,
                    'is_default'      => true,
                    'active'          => true,
                    'updated_at'      => now(),
                ]);
            } else {
                DB::table('whatsapp_templates')->insert([
                    'name'            => 'Nova Cobrança Gerada',
                    'slug'            => 'cobranca-gerada',
                    'category'        => 'cobranca',
                    'schedule_offset' => 'manual',
                    'content_html'    => $cobrancaGeradaHtml,
                    'content_text'    => $cobrancaGeradaText,
                    'scope'           => 'system',
                    'is_system'       => true,
                    'is_default'      => true,
                    'active'          => true,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }

            // 2. Atualiza template 0d (vencimento-hoje) para explicitar a data do vencimento {vencimento}
            $vencHojeHtml = '<p>Olá <strong>{primeiro_nome}</strong>.</p><p>Hoje (<strong>{vencimento}</strong>) é o vencimento da sua fatura <strong>{numero_fatura}</strong>.</p><p><strong>Serviço:</strong> {servico_principal}<br><strong>Valor:</strong> {valor}<br><strong>Vencimento:</strong> {vencimento}<br><strong>Parcelas:</strong> {resumo_parcelas}</p><p>Pagamento seguro:<br>{link_pagamento}</p><p>{qrcode_instrucao}</p><p>Pix Copia e Cola:<br>```{pix_copia_cola}```</p><p>Se já realizou o pagamento, desconsidere este aviso. Obrigado!<br><strong>{empresa}</strong></p>';
            $vencHojeText = "Olá *{primeiro_nome}*.\n\nHoje (*{vencimento}*) é o vencimento da sua fatura *{numero_fatura}*.\n\n*Serviço:* {servico_principal}\n*Valor:* {valor}\n*Vencimento:* {vencimento}\n*Parcelas:* {resumo_parcelas}\n\n*Pagamento seguro:*\n{link_pagamento}\n\n{qrcode_instrucao}\n\n*Pix Copia e Cola:*\n```{pix_copia_cola}```\n\nSe já realizou o pagamento, desconsidere este aviso. Obrigado!\n*{empresa}*";

            DB::table('whatsapp_templates')
                ->where('slug', 'vencimento-hoje')
                ->where('is_system', true)
                ->update([
                    'content_html' => $vencHojeHtml,
                    'content_text' => $vencHojeText,
                    'updated_at'   => now(),
                ]);
        }

        // 3. Sanitiza faturas existentes com links externos de ticket do Mercado Pago
        if (Schema::hasTable('invoices')) {
            $invoices = DB::table('invoices')
                ->where(function ($q) {
                    $q->where('payment_url', 'LIKE', '%mercadopago.com%')
                      ->orWhere('payment_url', 'LIKE', '%/ticket%');
                })
                ->get(['id', 'webhook_token', 'payment_url', 'meta']);

            foreach ($invoices as $inv) {
                $token = $inv->webhook_token ?: Str::random(48);
                $meta = is_string($inv->meta) ? (json_decode($inv->meta, true) ?: []) : ((array) ($inv->meta ?? []));
                if (!empty($inv->payment_url)) {
                    $meta['gateway_payment']['ticket_url'] = (string) $inv->payment_url;
                }

                DB::table('invoices')->where('id', $inv->id)->update([
                    'webhook_token' => $token,
                    'payment_url'   => url('/fatura/' . $token),
                    'meta'          => json_encode($meta),
                    'updated_at'    => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Migração idempotente e progressiva.
    }
};
