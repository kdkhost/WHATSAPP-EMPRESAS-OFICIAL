<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        DB::table('email_templates')
            ->whereIn('category', ['cobranca', 'lembrete', 'atraso'])
            ->where('body_html', 'not like', '%{qrcode_pix_html}%')
            ->orderBy('id')
            ->chunkById(100, function ($templates) {
                foreach ($templates as $template) {
                    $body = (string) $template->body_html;

                    if (! str_contains($body, '{servico_principal}')) {
                        $rows = '<tr>'
                            . '<td style="padding:10px 0;color:#64748b;font-size:13px;border-bottom:1px solid #e5e7eb;">Servico</td>'
                            . '<td style="padding:10px 0;color:#0f172a;font-size:13px;font-weight:700;text-align:right;border-bottom:1px solid #e5e7eb;">{servico_principal}</td>'
                            . '</tr>'
                            . '<tr>'
                            . '<td style="padding:10px 0;color:#64748b;font-size:13px;border-bottom:1px solid #e5e7eb;">Parcelas</td>'
                            . '<td style="padding:10px 0;color:#0f172a;font-size:13px;font-weight:700;text-align:right;border-bottom:1px solid #e5e7eb;">{resumo_parcelas}</td>'
                            . '</tr>';

                        $body = preg_replace('/<\/table>/i', $rows.'</table>', $body, 1) ?: $body;
                    }

                    if (str_contains($body, '<p style="margin:24px 0 0;font-size:14px;line-height:1.7;color:#334155;">')) {
                        $body = str_replace(
                            '<p style="margin:24px 0 0;font-size:14px;line-height:1.7;color:#334155;">',
                            '{qrcode_pix_html}<p style="margin:24px 0 0;font-size:14px;line-height:1.7;color:#334155;">',
                            $body
                        );
                    } else {
                        $body .= '{qrcode_pix_html}';
                    }

                    DB::table('email_templates')->where('id', $template->id)->update([
                        'body_html' => $body,
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Forward-only content upgrade.
    }
};
