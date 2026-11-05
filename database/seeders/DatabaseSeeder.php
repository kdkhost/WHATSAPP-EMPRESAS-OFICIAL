<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Roles padrao (hierarquia por level)
        $roles = [
            ['name' => 'Super Administrador', 'slug' => 'superadmin', 'description' => 'Acesso TOTAL ao sistema. Bypass de todas as permissoes.', 'is_system' => true, 'level' => 100],
            ['name' => 'Administrador',       'slug' => 'admin',      'description' => 'Acesso configuravel por modulo/acao.',                    'is_system' => true, 'level' => 50],
            ['name' => 'Operador',            'slug' => 'operator',   'description' => 'Acesso limitado a operacoes do dia-a-dia.',               'is_system' => true, 'level' => 20],
            ['name' => 'Visualizador',        'slug' => 'viewer',     'description' => 'Apenas visualiza dados. Sem edicao.',                     'is_system' => true, 'level' => 5],
        ];
        foreach ($roles as $r) {
            DB::table('roles')->updateOrInsert(['slug' => $r['slug']], $r + ['created_at' => now(), 'updated_at' => now()]);
        }

        // Admin padrao (apenas se nao existir nenhum - o instalador cria o real)
        if (DB::table('admin_users')->count() === 0) {
            $adminId = DB::table('admin_users')->insertGetId([
                'name'       => 'Administrador',
                'email'      => 'admin@exemplo.com',
                'password'   => Hash::make('password'),
                'role'       => 'superadmin',
                'active'     => true,
                'theme_dark' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Atribui role superadmin
            $superRoleId = DB::table('roles')->where('slug', 'superadmin')->value('id');
            if ($superRoleId) {
                DB::table('admin_user_roles')->updateOrInsert(
                    ['admin_user_id' => $adminId, 'role_id' => $superRoleId],
                    ['created_at' => now()]
                );
            }
        }

        // Atribui permissions padrao a role "admin" (tudo exceto system.install e roles.manage)
        $adminRole = DB::table('roles')->where('slug', 'admin')->first();
        if ($adminRole) {
            $allPerms = DB::table('permissions')
                ->whereNotIn('slug', ['system.install', 'roles.manage', 'roles.delete', 'addons.manage', 'addons.delete'])
                ->pluck('id')->all();
            foreach ($allPerms as $permId) {
                DB::table('role_permissions')->updateOrInsert(
                    ['role_id' => $adminRole->id, 'permission_id' => $permId],
                    ['created_at' => now(), 'updated_at' => now()]
                );
            }
        }

        // Atribui permissions de visualizacao a role "viewer"
        $viewerRole = DB::table('roles')->where('slug', 'viewer')->first();
        if ($viewerRole) {
            $viewPerms = DB::table('permissions')->where('action', 'view')->pluck('id')->all();
            foreach ($viewPerms as $permId) {
                DB::table('role_permissions')->updateOrInsert(
                    ['role_id' => $viewerRole->id, 'permission_id' => $permId],
                    ['created_at' => now(), 'updated_at' => now()]
                );
            }
        }

        // Atribui permissions de operacao a role "operator"
        $operatorRole = DB::table('roles')->where('slug', 'operator')->first();
        if ($operatorRole) {
            $opPerms = DB::table('permissions')
                ->whereIn('action', ['view', 'create', 'edit'])
                ->whereIn('module', ['billing', 'customers', 'templates', 'whatsapp', 'crm'])
                ->pluck('id')->all();
            foreach ($opPerms as $permId) {
                DB::table('role_permissions')->updateOrInsert(
                    ['role_id' => $operatorRole->id, 'permission_id' => $permId],
                    ['created_at' => now(), 'updated_at' => now()]
                );
            }
        }

        // Regras de seguranca (WAF)
        $rules = [
            ['code' => 'sqli',      'name' => 'SQL Injection',           'action' => 'block', 'enabled' => true],
            ['code' => 'xss',       'name' => 'Cross-Site Scripting',    'action' => 'block', 'enabled' => true],
            ['code' => 'lfi',       'name' => 'Local File Inclusion',    'action' => 'block', 'enabled' => true],
            ['code' => 'rfi',       'name' => 'Remote File Inclusion',   'action' => 'block', 'enabled' => true],
            ['code' => 'badbot',    'name' => 'Bad Bots / Scanners',     'action' => 'block', 'enabled' => true],
            ['code' => 'ratelimit', 'name' => 'Rate Limit por IP',       'action' => 'block', 'enabled' => true,
             'config' => json_encode(['max' => 120, 'window_seconds' => 60])],
        ];
        foreach ($rules as $r) {
            DB::table('security_rules')->updateOrInsert(
                ['code' => $r['code']],
                $r + ['created_at' => now(), 'updated_at' => now()]
            );
        }

        // Settings iniciais
        $defaults = [
            ['group' => 'app',         'key' => 'name',            'value' => 'Cobranca WhatsApp', 'type' => 'string'],
            ['group' => 'app',         'key' => 'logo',            'value' => '/assets/img/logo.png', 'type' => 'string'],
            ['group' => 'app',         'key' => 'favicon',         'value' => '/assets/img/favicon.ico', 'type' => 'string'],
            ['group' => 'maintenance', 'key' => 'enabled',         'value' => '0', 'type' => 'bool'],
            ['group' => 'maintenance', 'key' => 'title',           'value' => 'Em manutencao', 'type' => 'string'],
            ['group' => 'maintenance', 'key' => 'message',         'value' => 'Estamos realizando melhorias. Voltamos em breve!', 'type' => 'string'],
            ['group' => 'pwa',         'key' => 'name',            'value' => 'Cobranca WhatsApp', 'type' => 'string'],
            ['group' => 'pwa',         'key' => 'short_name',      'value' => 'Cobranca', 'type' => 'string'],
            ['group' => 'pwa',         'key' => 'theme_color',     'value' => '#0d6efd', 'type' => 'string'],
            ['group' => 'pwa',         'key' => 'background_color','value' => '#ffffff', 'type' => 'string'],
            ['group' => 'pwa',         'key' => 'start_url',       'value' => '/?source=pwa', 'type' => 'string'],
            ['group' => 'pwa',         'key' => 'install_prompt',  'value' => '1', 'type' => 'bool'],
            ['group' => 'pwa',         'key' => 'push_enabled',    'value' => '0', 'type' => 'bool'],
            ['group' => 'seo',         'key' => 'title',           'value' => 'Cobranca automatica via WhatsApp', 'type' => 'string'],
            ['group' => 'seo',         'key' => 'description',     'value' => 'Reduza inadimplencia com lembretes automaticos', 'type' => 'string'],
        ];
        foreach ($defaults as $s) {
            DB::table('settings')->updateOrInsert(
                ['group' => $s['group'], 'key' => $s['key']],
                $s + ['created_at' => now(), 'updated_at' => now()]
            );
        }

        // Kanban boards iniciais
        $boards = [
            ['slug' => 'menus',      'name' => 'Menus',      'context' => 'menus'],
            ['slug' => 'categorias', 'name' => 'Categorias', 'context' => 'categorias'],
            ['slug' => 'cards',      'name' => 'Cards',      'context' => 'cards'],
            ['slug' => 'modulos',    'name' => 'Modulos',    'context' => 'modulos'],
        ];
        foreach ($boards as $b) {
            DB::table('kanban_boards')->updateOrInsert(
                ['slug' => $b['slug']],
                $b + ['created_at' => now(), 'updated_at' => now()]
            );
        }

        // Seeding columns and cards for 'menus'
        $menuBoardId = DB::table('kanban_boards')->where('slug', 'menus')->value('id');
        if ($menuBoardId && DB::table('kanban_columns')->where('board_id', $menuBoardId)->count() === 0) {
            $colHeaderId = DB::table('kanban_columns')->insertGetId(['board_id' => $menuBoardId, 'name' => 'Cabeçalho', 'sort' => 0]);
            $colFooterId = DB::table('kanban_columns')->insertGetId(['board_id' => $menuBoardId, 'name' => 'Rodapé', 'sort' => 1]);
            $colAdminId = DB::table('kanban_columns')->insertGetId(['board_id' => $menuBoardId, 'name' => 'Painel Admin', 'sort' => 2]);

            DB::table('kanban_cards')->insert([
                ['column_id' => $colHeaderId, 'title' => 'Home', 'content' => 'Link principal da página inicial.', 'sort' => 0, 'created_at' => now(), 'updated_at' => now()],
                ['column_id' => $colHeaderId, 'title' => 'Funcionalidades', 'content' => 'Seção com recursos e benefícios.', 'sort' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['column_id' => $colHeaderId, 'title' => 'Preços/Planos', 'content' => 'Tabela comparativa dos 4 pacotes.', 'sort' => 2, 'created_at' => now(), 'updated_at' => now()],
                ['column_id' => $colFooterId, 'title' => 'Termos de Uso', 'content' => 'Contrato de termos e regras.', 'sort' => 0, 'created_at' => now(), 'updated_at' => now()],
                ['column_id' => $colFooterId, 'title' => 'Políticas LGPD', 'content' => 'Privacidade e tratamento de dados.', 'sort' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['column_id' => $colAdminId, 'title' => 'Dashboard principal', 'content' => 'Métricas, KPIs e gráficos.', 'sort' => 0, 'created_at' => now(), 'updated_at' => now()],
                ['column_id' => $colAdminId, 'title' => 'CRM Funil', 'content' => 'Controle de vendas integrado.', 'sort' => 1, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        // Seeding columns and cards for 'categorias'
        $catBoardId = DB::table('kanban_boards')->where('slug', 'categorias')->value('id');
        if ($catBoardId && DB::table('kanban_columns')->where('board_id', $catBoardId)->count() === 0) {
            $colServId = DB::table('kanban_columns')->insertGetId(['board_id' => $catBoardId, 'name' => 'Recursos Principais', 'sort' => 0]);
            $colAddonId = DB::table('kanban_columns')->insertGetId(['board_id' => $catBoardId, 'name' => 'Addons Modulares', 'sort' => 1]);

            DB::table('kanban_cards')->insert([
                ['column_id' => $colServId, 'title' => 'Cobrança PIX Automática', 'content' => 'Geração de PIX com QR code dinâmico e webhook instantâneo.', 'sort' => 0, 'created_at' => now(), 'updated_at' => now()],
                ['column_id' => $colServId, 'title' => 'Envio Inteligente de Lembretes', 'content' => 'Réguas automáticas ativas com antecedência parametrizável.', 'sort' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['column_id' => $colAddonId, 'title' => 'Integração Evolution API', 'content' => 'Conexão modular do WhatsApp sem lag ou bloqueios.', 'sort' => 0, 'created_at' => now(), 'updated_at' => now()],
                ['column_id' => $colAddonId, 'title' => 'Painel White Label', 'content' => 'Customização visual completa para revenda.', 'sort' => 1, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        // Seeding columns and cards for 'cards'
        $cardsBoardId = DB::table('kanban_boards')->where('slug', 'cards')->value('id');
        if ($cardsBoardId && DB::table('kanban_columns')->where('board_id', $cardsBoardId)->count() === 0) {
            $colNewsId = DB::table('kanban_columns')->insertGetId(['board_id' => $cardsBoardId, 'name' => 'Novidades Recentes', 'sort' => 0]);
            $colDestId = DB::table('kanban_columns')->insertGetId(['board_id' => $cardsBoardId, 'name' => 'Casos de Sucesso', 'sort' => 1]);

            DB::table('kanban_cards')->insert([
                ['column_id' => $colNewsId, 'title' => 'Evolution API v2.0', 'content' => 'Melhorias de velocidade e reconexão automática integradas.', 'sort' => 0, 'created_at' => now(), 'updated_at' => now()],
                ['column_id' => $colNewsId, 'title' => 'Checkout Transparente Efi', 'content' => 'Transações de Pix e Cartão homologadas.', 'sort' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['column_id' => $colDestId, 'title' => 'Redução de Inadimplência', 'content' => 'Nossos clientes registraram queda média de 78% na inadimplência.', 'sort' => 0, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        // Templates de WhatsApp padrão do sistema (Catálogo completo e consistente)
        $tpls = [
            // --- Nova Cobrança Gerada (Envio Imediato / Manual) ---
            [
                'name' => 'Nova Cobrança Gerada',
                'slug' => 'cobranca-gerada',
                'category' => 'cobranca',
                'schedule_offset' => 'manual',
                'content_html' => '<p>Olá <strong>{primeiro_nome}</strong>, tudo bem?</p><p>Uma nova cobrança foi gerada para você com vencimento em <strong>{vencimento}</strong>.</p><p><strong>Fatura:</strong> #{numero_fatura}<br><strong>Serviço:</strong> {servico_principal}<br><strong>Valor:</strong> {valor}<br><strong>Vencimento:</strong> {vencimento}<br><strong>Parcelas:</strong> {resumo_parcelas}</p><p>Acesse o link seguro para pagamento:<br>{link_pagamento}</p><p>{qrcode_instrucao}</p><p>Pix Copia e Cola:<br>```{pix_copia_cola}```</p><p>Atenciosamente,<br><strong>{empresa}</strong></p>',
                'content_text' => "Olá *{primeiro_nome}*, tudo bem?\n\nUma nova cobrança foi gerada para você com vencimento em *{vencimento}*.\n\n*Fatura:* #{numero_fatura}\n*Serviço:* {servico_principal}\n*Valor:* {valor}\n*Vencimento:* {vencimento}\n*Parcelas:* {resumo_parcelas}\n\n*Acesse o link seguro para pagamento:*\n{link_pagamento}\n\n{qrcode_instrucao}\n\n*Pix Copia e Cola:*\n```{pix_copia_cola}```\n\nAtenciosamente,\n*{empresa}*",
                'variables' => json_encode(['{nome}', '{primeiro_nome}', '{valor}', '{vencimento}', '{numero_fatura}', '{link_pagamento}', '{pix_copia_cola}', '{empresa}', '{servico_principal}', '{resumo_parcelas}']),
                'active' => true,
            ],
            // --- Régua de Cobrança ---
            [
                'name' => 'Lembrete 3 dias antes',
                'slug' => 'lembrete-3d-antes',
                'category' => 'lembrete',
                'schedule_offset' => '-3d',
                'content_html' => '<p>Olá *{primeiro_nome}*, tudo bem?</p><p>Passando para lembrar que sua fatura *{numero_fatura}* no valor de *{valor}* vence em *{vencimento}*.</p><p>Link para pagamento seguro: {link_pagamento}</p><p>{pix_copia_cola}</p><p>Atenciosamente,<br>*{empresa}*</p>',
                'content_text' => "Olá *{primeiro_nome}*, tudo bem?\n\nPassando para lembrar que sua fatura *{numero_fatura}* no valor de *{valor}* vence em *{vencimento}*.\n\nLink para pagamento seguro:\n{link_pagamento}\n\n{pix_copia_cola}\n\nAtenciosamente,\n*{empresa}*",
                'variables' => json_encode(['{nome}', '{primeiro_nome}', '{valor}', '{vencimento}', '{numero_fatura}', '{link_pagamento}', '{pix_copia_cola}', '{empresa}']),
                'active' => true,
            ],
            [
                'name' => 'Lembrete 1 dia antes',
                'slug' => 'lembrete-1d-antes',
                'category' => 'lembrete',
                'schedule_offset' => '-1d',
                'content_html' => '<p>Olá *{primeiro_nome}*!</p><p>Amanhã vence sua fatura *{numero_fatura}* no valor de *{valor}*.</p><p>Acesse o pagamento para manter seus serviços ativos: {link_pagamento}</p><p>{pix_copia_cola}</p><p>Dúvidas? Estamos à disposição.<br>*{empresa}*</p>',
                'content_text' => "Olá *{primeiro_nome}*!\n\nAmanhã vence sua fatura *{numero_fatura}* no valor de *{valor}*.\n\nAcesse o pagamento para manter seus serviços ativos:\n{link_pagamento}\n\n{pix_copia_cola}\n\nDúvidas? Estamos à disposição.\n*{empresa}*",
                'variables' => json_encode(['{nome}', '{primeiro_nome}', '{valor}', '{vencimento}', '{numero_fatura}', '{link_pagamento}', '{pix_copia_cola}', '{empresa}']),
                'active' => true,
            ],
            [
                'name' => 'Vencimento hoje',
                'slug' => 'vencimento-hoje',
                'category' => 'cobranca',
                'schedule_offset' => '0d',
                'content_html' => '<p>Olá *{primeiro_nome}*!</p><p>Sua fatura *{numero_fatura}* no valor de *{valor}* vence *hoje ({vencimento})*.</p><p>Para efetuar o pagamento rápido e sem juros: {link_pagamento}</p><p>{pix_copia_cola}</p><p>Se já realizou o pagamento, por favor desconsidere.<br>*{empresa}*</p>',
                'content_text' => "Olá *{primeiro_nome}*!\n\nSua fatura *{numero_fatura}* no valor de *{valor}* vence *hoje ({vencimento})*.\n\nPara efetuar o pagamento rápido e sem juros:\n{link_pagamento}\n\n{pix_copia_cola}\n\nSe já realizou o pagamento, por favor desconsidere.\n*{empresa}*",
                'variables' => json_encode(['{nome}', '{primeiro_nome}', '{valor}', '{vencimento}', '{numero_fatura}', '{link_pagamento}', '{pix_copia_cola}', '{empresa}']),
                'active' => true,
            ],
            [
                'name' => '1 dia em atraso',
                'slug' => 'atraso-1d',
                'category' => 'atraso',
                'schedule_offset' => '+1d',
                'content_html' => '<p>Olá *{primeiro_nome}*.</p><p>Constatamos que a sua fatura *{numero_fatura}* com vencimento em *{vencimento}* no valor de *{valor}* consta pendente.</p><p>Regularize facilmente pelo link: {link_pagamento}</p><p>{pix_copia_cola}</p><p>Caso precise de suporte, fale conosco: {whatsapp_suporte}</p>',
                'content_text' => "Olá *{primeiro_nome}*.\n\nConstatamos que a sua fatura *{numero_fatura}* com vencimento em *{vencimento}* no valor de *{valor}* consta pendente.\n\nRegularize facilmente pelo link:\n{link_pagamento}\n\n{pix_copia_cola}\n\nCaso precise de suporte, fale conosco: {whatsapp_suporte}",
                'variables' => json_encode(['{nome}', '{primeiro_nome}', '{valor}', '{vencimento}', '{numero_fatura}', '{link_pagamento}', '{pix_copia_cola}', '{whatsapp_suporte}', '{empresa}']),
                'active' => true,
            ],
            [
                'name' => '3 dias em atraso',
                'slug' => 'atraso-3d',
                'category' => 'atraso',
                'schedule_offset' => '+3d',
                'content_html' => '<p>Aviso de Pendência: Olá *{primeiro_nome}*.</p><p>Sua fatura *{numero_fatura}* está com *{dias_atraso} dias de atraso*.</p><p>Evite o bloqueio dos serviços regularizando sua situação: {link_pagamento}</p><p>{pix_copia_cola}</p><p>Atenciosamente,<br>*{empresa}*</p>',
                'content_text' => "Aviso de Pendência: Olá *{primeiro_nome}*.\n\nSua fatura *{numero_fatura}* está com *{dias_atraso} dias de atraso*.\n\nEvite o bloqueio dos serviços regularizando sua situação:\n{link_pagamento}\n\n{pix_copia_cola}\n\nAtenciosamente,\n*{empresa}*",
                'variables' => json_encode(['{nome}', '{primeiro_nome}', '{valor}', '{vencimento}', '{dias_atraso}', '{numero_fatura}', '{link_pagamento}', '{pix_copia_cola}', '{empresa}']),
                'active' => true,
            ],
            [
                'name' => '7 dias em atraso',
                'slug' => 'atraso-7d',
                'category' => 'atraso',
                'schedule_offset' => '+7d',
                'content_html' => '<p>Importante: Olá *{primeiro_nome}*.</p><p>Identificamos que sua fatura *{numero_fatura}* está em aberto há *{dias_atraso} dias*.</p><p>Por favor, regularize para manter a continuidade do atendimento: {link_pagamento}</p><p>{pix_copia_cola}</p><p>Equipe de Cobrança - *{empresa}*</p>',
                'content_text' => "Importante: Olá *{primeiro_nome}*.\n\nIdentificamos que sua fatura *{numero_fatura}* está em aberto há *{dias_atraso} dias*.\n\nPor favor, regularize para manter a continuidade do atendimento:\n{link_pagamento}\n\n{pix_copia_cola}\n\nEquipe de Cobrança - *{empresa}*",
                'variables' => json_encode(['{nome}', '{primeiro_nome}', '{valor}', '{vencimento}', '{dias_atraso}', '{numero_fatura}', '{link_pagamento}', '{pix_copia_cola}', '{empresa}']),
                'active' => true,
            ],
            [
                'name' => '15 dias em atraso (Notificação Final)',
                'slug' => 'atraso-15d',
                'category' => 'atraso',
                'schedule_offset' => '+15d',
                'content_html' => '<p>Notificação Final: Prezado(a) *{nome}*.</p><p>Sua fatura *{numero_fatura}* no valor de *{valor}* venceu em *{vencimento}* ({dias_atraso} dias de atraso).</p><p>Evite restrições e cancelamento definitivo quitando agora: {link_pagamento}</p><p>{pix_copia_cola}</p><p>Dúvidas ou negociações: {whatsapp_suporte}</p>',
                'content_text' => "Notificação Final: Prezado(a) *{nome}*.\n\nSua fatura *{numero_fatura}* no valor de *{valor}* venceu em *{vencimento}* ({dias_atraso} dias de atraso).\n\nEvite restrições e cancelamento definitivo quitando agora:\n{link_pagamento}\n\n{pix_copia_cola}\n\nDúvidas ou negociações: {whatsapp_suporte}",
                'variables' => json_encode(['{nome}', '{primeiro_nome}', '{valor}', '{vencimento}', '{dias_atraso}', '{numero_fatura}', '{link_pagamento}', '{pix_copia_cola}', '{whatsapp_suporte}', '{empresa}']),
                'active' => true,
            ],
            [
                'name' => 'Boleto Bancário da Fatura',
                'slug' => 'boleto-fatura',
                'category' => 'cobranca',
                'schedule_offset' => null,
                'content_html' => '<p>Olá *{primeiro_nome}*!</p><p>Aqui está o boleto da sua fatura *{numero_fatura}* no valor de *{valor}*, com vencimento em *{vencimento}*.</p><p>Link do boleto: {boleto_url}</p><p>Linha digitável:<br>```{linha_digitavel}```</p><p>Atenciosamente,<br>*{empresa}*</p>',
                'content_text' => "Olá *{primeiro_nome}*!\n\nAqui está o boleto da sua fatura *{numero_fatura}* no valor de *{valor}*, com vencimento em *{vencimento}*.\n\nLink do boleto:\n{boleto_url}\n\nLinha digitável:\n```{linha_digitavel}```\n\nAtenciosamente,\n*{empresa}*",
                'variables' => json_encode(['{nome}', '{primeiro_nome}', '{valor}', '{vencimento}', '{numero_fatura}', '{boleto_url}', '{linha_digitavel}', '{empresa}']),
                'active' => true,
            ],

            // --- Pagamento e Baixa ---
            [
                'name' => 'Pagamento Confirmado',
                'slug' => 'pagamento-confirmado',
                'category' => 'agradecimento',
                'schedule_offset' => null,
                'content_html' => '<p>✅ *Pagamento Confirmado!*</p><p>Olá *{primeiro_nome}*, identificamos com sucesso o pagamento da sua fatura *{numero_fatura}* no valor de *{valor}*.</p><p>Forma de pagamento: *{forma_pagamento}*<br>Data da baixa: *{data_pagamento}*</p><p>Agradecemos a sua parceria!<br>*{empresa}*</p>',
                'content_text' => "✅ *Pagamento Confirmado!*\n\nOlá *{primeiro_nome}*, identificamos com sucesso o pagamento da sua fatura *{numero_fatura}* no valor de *{valor}*.\n\nForma de pagamento: *{forma_pagamento}*\nData da baixa: *{data_pagamento}*\n\nAgradecemos a sua parceria!\n*{empresa}*",
                'variables' => json_encode(['{nome}', '{primeiro_nome}', '{valor}', '{numero_fatura}', '{forma_pagamento}', '{data_pagamento}', '{proximo_vencimento}', '{empresa}']),
                'active' => true,
            ],
            [
                'name' => 'Pix Copia e Cola',
                'slug' => 'pix-copia-cola',
                'category' => 'pix',
                'schedule_offset' => null,
                'content_html' => '<p>Segue a chave Pix Copia e Cola da sua fatura *{numero_fatura}* no valor de *{valor}*:</p><p>```{pix_copia_cola}```</p><p>Para pagar: abra o app do seu banco, escolha Pix > Pix Copia e Cola, cole o código acima e confirme o pagamento instantâneo.</p><p>Link direto: {link_pagamento}</p>',
                'content_text' => "Segue a chave Pix Copia e Cola da sua fatura *{numero_fatura}* no valor de *{valor}*:\n\n```{pix_copia_cola}```\n\nPara pagar: abra o app do seu banco, escolha Pix > Pix Copia e Cola, cole o código acima e confirme o pagamento instantâneo.\n\nLink direto:\n{link_pagamento}",
                'variables' => json_encode(['{nome}', '{primeiro_nome}', '{valor}', '{vencimento}', '{numero_fatura}', '{pix_copia_cola}', '{link_pagamento}', '{empresa}']),
                'active' => true,
            ],

            // --- Contratos ---
            [
                'name' => 'Contrato Disponível para Assinatura',
                'slug' => 'contrato-assinatura',
                'category' => 'contrato',
                'schedule_offset' => null,
                'content_html' => '<p>Olá *{primeiro_nome}*!</p><p>Seu contrato *{contrato_numero}* (*{contrato_titulo}*) foi emitido e está pronto para sua assinatura eletrônica.</p><p>Para assinar de forma rápida e segura, acesse o link:<br>{link_assinatura}</p><p>Caso tenha dúvidas, entre em contato conosco.<br>*{empresa}*</p>',
                'content_text' => "Olá *{primeiro_nome}*!\n\nSeu contrato *{contrato_numero}* (*{contrato_titulo}*) foi emitido e está pronto para sua assinatura eletrônica.\n\nPara assinar de forma rápida e segura, acesse o link:\n{link_assinatura}\n\nCaso tenha dúvidas, entre em contato conosco.\n*{empresa}*",
                'variables' => json_encode(['{nome}', '{primeiro_nome}', '{contrato_numero}', '{contrato_titulo}', '{link_assinatura}', '{link_contrato}', '{empresa}']),
                'active' => true,
            ],
            [
                'name' => 'Contrato Assinado com Sucesso',
                'slug' => 'contrato-assinado',
                'category' => 'contrato_assinado',
                'schedule_offset' => null,
                'content_html' => '<p>✅ *Contrato Assinado!*</p><p>Olá *{primeiro_nome}*, confirmamos a assinatura com validade jurídica do contrato *{contrato_numero}* em *{data_assinatura}*.</p><p>Você pode visualizar e baixar a via assinada a qualquer momento no link:<br>{link_contrato}</p><p>Seja bem-vindo(a)!<br>*{empresa}*</p>',
                'content_text' => "✅ *Contrato Assinado!*\n\nOlá *{primeiro_nome}*, confirmamos a assinatura com validade jurídica do contrato *{contrato_numero}* em *{data_assinatura}*.\n\nVocê pode visualizar e baixar a via assinada a qualquer momento no link:\n{link_contrato}\n\nSeja bem-vindo(a)!\n*{empresa}*",
                'variables' => json_encode(['{nome}', '{primeiro_nome}', '{contrato_numero}', '{contrato_titulo}', '{link_contrato}', '{data_assinatura}', '{empresa}']),
                'active' => true,
            ],

            // --- Pacotes de Mensalidade / Serviços ---
            [
                'name' => 'Pacote - Cancelamento de Assinatura',
                'slug' => 'pacote-cancelamento',
                'category' => 'pacote_cancelamento',
                'schedule_offset' => null,
                'content_html' => '<p>Olá *{primeiro_nome}*.</p><p>Informamos que a contratação do pacote *{pacote_nome}* foi cancelada em *{data_cancelamento}*.</p><p>Motivo: {motivo}</p><p>Ficamos à disposição para reativá-lo quando desejar: {whatsapp_suporte}</p><p>Atenciosamente,<br>*{empresa}*</p>',
                'content_text' => "Olá *{primeiro_nome}*.\n\nInformamos que a contratação do pacote *{pacote_nome}* foi cancelada em *{data_cancelamento}*.\n\nMotivo: {motivo}\n\nFicamos à disposição para reativá-lo quando desejar: {whatsapp_suporte}\n\nAtenciosamente,\n*{empresa}*",
                'variables' => json_encode(['{nome}', '{primeiro_nome}', '{pacote_nome}', '{motivo}', '{data_cancelamento}', '{whatsapp_suporte}', '{empresa}']),
                'active' => true,
            ],
            [
                'name' => 'Pacote - Parcela Confirmada',
                'slug' => 'pacote-pagamento-confirmado',
                'category' => 'pacote_pagamento',
                'schedule_offset' => null,
                'content_html' => '<p>✅ *Parcela Confirmada!*</p><p>Olá *{primeiro_nome}*, recebemos a parcela *{parcela_numero}/{total_parcelas}* do pacote *{pacote_nome}* no valor de *{valor}*.</p><p>Próximo vencimento: *{proximo_vencimento}*</p><p>Obrigado por manter sua assinatura em dia!<br>*{empresa}*</p>',
                'content_text' => "✅ *Parcela Confirmada!*\n\nOlá *{primeiro_nome}*, recebemos a parcela *{parcela_numero}/{total_parcelas}* do pacote *{pacote_nome}* no valor de *{valor}*.\n\nPróximo vencimento: *{proximo_vencimento}*\n\nObrigado por manter sua assinatura em dia!\n*{empresa}*",
                'variables' => json_encode(['{nome}', '{primeiro_nome}', '{pacote_nome}', '{parcela_numero}', '{total_parcelas}', '{valor}', '{proximo_vencimento}', '{empresa}']),
                'active' => true,
            ],

            // --- Comunicação, Suporte e Notificações ---
            [
                'name' => 'Notificação do Sistema',
                'slug' => 'notificacao-sistema',
                'category' => 'notificacao',
                'schedule_offset' => null,
                'content_html' => '<p>📢 *{titulo}*</p><p>Olá *{primeiro_nome}*,</p><p>{mensagem}</p><p>Equipe *{empresa}*</p>',
                'content_text' => "📢 *{titulo}*\n\nOlá *{primeiro_nome}*,\n\n{mensagem}\n\nEquipe *{empresa}*",
                'variables' => json_encode(['{nome}', '{primeiro_nome}', '{titulo}', '{mensagem}', '{empresa}']),
                'active' => true,
            ],
            [
                'name' => 'Envio Manual Padrão',
                'slug' => 'envio-manual-padrao',
                'category' => 'manual',
                'schedule_offset' => null,
                'content_html' => '<p>Olá *{primeiro_nome}*, tudo bem?</p><p>{mensagem}</p><p>Qualquer dúvida estamos por aqui!<br>*{empresa}*</p>',
                'content_text' => "Olá *{primeiro_nome}*, tudo bem?\n\n{mensagem}\n\nQualquer dúvida estamos por aqui!\n*{empresa}*",
                'variables' => json_encode(['{nome}', '{primeiro_nome}', '{mensagem}', '{telefone}', '{email}', '{documento}', '{empresa}']),
                'active' => true,
            ],
            [
                'name' => 'Boas-Vindas ao Cliente',
                'slug' => 'boas-vindas',
                'category' => 'boas_vindas',
                'schedule_offset' => null,
                'content_html' => '<p>🎉 *Seja muito bem-vindo(a), {primeiro_nome}!*</p><p>É uma satisfação ter você como cliente da *{empresa}*.</p><p>Estamos à disposição pelo canal oficial de suporte: {whatsapp_suporte}</p><p>Conte conosco!</p>',
                'content_text' => "🎉 *Seja muito bem-vindo(a), {primeiro_nome}!*\n\nÉ uma satisfação ter você como cliente da *{empresa}*.\n\nEstamos à disposição pelo canal oficial de suporte: {whatsapp_suporte}\n\nConte conosco!",
                'variables' => json_encode(['{nome}', '{primeiro_nome}', '{email}', '{telefone}', '{whatsapp_suporte}', '{empresa}']),
                'active' => true,
            ],
            [
                'name' => 'Atendimento e Suporte',
                'slug' => 'suporte-atendimento',
                'category' => 'suporte',
                'schedule_offset' => null,
                'content_html' => '<p>Olá *{primeiro_nome}*!</p><p>Seu atendimento junto ao suporte da *{empresa}* está em andamento.</p><p>{mensagem}</p><p>Canal direto de atendimento: {whatsapp_suporte}</p>',
                'content_text' => "Olá *{primeiro_nome}*!\n\nSeu atendimento junto ao suporte da *{empresa}* está em andamento.\n\n{mensagem}\n\nCanal direto de atendimento: {whatsapp_suporte}",
                'variables' => json_encode(['{nome}', '{primeiro_nome}', '{mensagem}', '{whatsapp_suporte}', '{empresa}']),
                'active' => true,
            ],
        ];

        foreach ($tpls as $t) {
            $t += [
                'scope' => 'system',
                'is_system' => true,
                'is_default' => true,
                'locked' => true,
            ];
            DB::table('whatsapp_templates')->updateOrInsert(['slug' => $t['slug']], $t + ['created_at' => now(), 'updated_at' => now()]);
        }

        // Planos do tenancy (4 pacotes para frontend publico)
        $this->call(PlansSeeder::class);

        // Popula 10 itens para Slides, Testimonials e Popups
        $this->call(FrontendDataSeeder::class);
    }
}
