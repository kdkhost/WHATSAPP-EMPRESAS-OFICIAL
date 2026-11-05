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

        $templates = DB::table('email_templates')->get();

        foreach ($templates as $tpl) {
            $html = (string) $tpl->body_html;
            $changed = false;

            // 1. Substitui background do cabeçalho estático
            if (str_contains($html, 'background:#0f172a')) {
                $html = str_replace('background:#0f172a', 'background:{cor_primaria}', $html);
                $changed = true;
            }

            // 2. Substitui cor do eyebrow estático
            if (str_contains($html, 'color:#93c5fd')) {
                $html = str_replace('color:#93c5fd', 'color:{cor_destaque}', $html);
                $changed = true;
            }

            // 3. Substitui cor do botão CTA estático
            if (str_contains($html, 'background:#0d6efd')) {
                $html = str_replace('background:#0d6efd', 'background:{cor_primaria}', $html);
                $changed = true;
            }

            // 4. Injeta {logo_html} no cabeçalho caso ainda não esteja presente
            if (! str_contains($html, '{logo_html}')) {
                if (str_contains($html, 'background:{cor_primaria};color:#ffffff;">')) {
                    $html = str_replace(
                        'background:{cor_primaria};color:#ffffff;">',
                        "background:{cor_primaria};color:#ffffff;\">\n{logo_html}",
                        $html
                    );
                    $changed = true;
                } elseif (preg_match('/(<td[^>]*padding:\s*22px 26px[^>]*>)/i', $html, $m)) {
                    $html = str_replace($m[1], $m[1] . "\n{logo_html}", $html);
                    $changed = true;
                }
            }

            if ($changed) {
                DB::table('email_templates')->where('id', $tpl->id)->update([
                    'body_html'  => $html,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // No-op
    }
};
