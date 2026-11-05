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

        // 1. Desmarca reativação de cliente e outros de marketing como padrão
        DB::table('email_templates')
            ->whereIn('slug', ['email-marketing-reativacao-cliente', 'servico-cancelado', 'pagamento-estornado'])
            ->update(['is_default' => false]);

        // 2. Define templates financeiros oficiais como padrões em suas respectivas categorias
        DB::table('email_templates')
            ->where('slug', 'cobranca-no-vencimento')
            ->update(['is_default' => true]);

        DB::table('email_templates')
            ->where('slug', 'cobranca-1-dia-atraso')
            ->update(['is_default' => true]);

        DB::table('email_templates')
            ->where('slug', 'cobranca-1-dia-antes')
            ->update(['is_default' => true]);

        // 3. Limpa preferências de e-mail que possam estar apontando para reativação de cliente em categorias financeiras
        if (Schema::hasTable('tenant_template_preferences')) {
            $mktIds = DB::table('email_templates')
                ->where('slug', 'email-marketing-reativacao-cliente')
                ->pluck('id');

            if ($mktIds->isNotEmpty()) {
                DB::table('tenant_template_preferences')
                    ->where('channel', 'email')
                    ->whereIn('template_id', $mktIds)
                    ->where(function ($q) {
                        $q->where('preference_key', 'like', 'cobranca%')
                            ->orWhere('preference_key', 'like', 'atraso%')
                            ->orWhere('preference_key', 'like', 'lembrete%');
                    })
                    ->delete();
            }
        }
    }

    public function down(): void
    {
        // No-op
    }
};
