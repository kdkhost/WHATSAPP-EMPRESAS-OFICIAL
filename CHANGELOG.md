# Changelog

Todas as alteracoes notaveis deste projeto serao documentadas neste arquivo.

O formato segue [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/),
e este projeto adere ao [Versionamento Semantico](https://semver.org/lang/pt-BR/).

---

## [3.0.0] - 2026-11-05
### Adicionado
- **Migração Integral para WhatsApp Cloud API Oficial da Meta (Graph API v21.0)**:
  - Criação do cliente oficial `MetaCloudApiClient` homologado para envio de mensagens e templates via `https://graph.facebook.com/v21.0/{PHONE_NUMBER_ID}/messages`.
  - Método dedicado `sendInvoiceBillingTemplateMessage` para injeção dinâmica de Pix Copia e Cola no corpo do template e link da fatura pública (`/fatura/{token}`).
  - Webhook oficial da Meta em `/api/v1/webhook` com handshake de verificação `GET` (`hub.mode` e `hub.challenge`) e validação criptográfica de segurança `POST` via HMAC-SHA256 (`x-hub-signature-256`) com `APP_SECRET`.
  - Monitoramento contínuo da janela de 24 horas via `last_interaction_at` de clientes finais para mensagens livres de sessão.
  - Suporte nativo a **Message Templates Aprovados** da Meta com parâmetros dinâmicos.
  - Painel oficial de status da Meta em `instance.blade.php`, exibindo Phone Number ID, WABA ID, qualidade da linha telefônica (**Quality Rating: GREEN**) e botões de teste oficial com SweetAlert2.
- **Módulo Mercado Pago SDK v2 (`app/Modules/MercadoPago`) & Checkout Transparente**:
  - Novo serviço independente `MercadoPagoPaymentService` com `createPixCharge` (Pix Dinâmico com QR Code visual base64 e código Copia e Cola) e `createCardCharge` (Cartão de Crédito tokenizado no frontend com parcelamento em até 12x).
  - Webhook oficial `/api/v1/webhooks/mercadopago` em `MercadoPagoWebhookController` com validação de status (`payment.get`) e assinatura `x-signature` para ativação, renovação e bloqueio de contas SaaS de tenants.
  - Conciliação e dedução automática de tarifas: no método `markInvoiceAsPaid`, captura `fee_details` (`mercadopago_fee`) e calcula o repasse líquido (`net_amount`) gravando o extrato completo no banco.
  - Suporte completo a reembolso parcial e total via `/v1/payments/{id}/refunds` e método `refundInvoice` no painel administrativo com SweetAlert2.
- **Engine da Régua de Cobrança (`ProcessBillingScheduleCommand`)**:
  - Novo comando agendado `php artisan billing:process-schedule` para processamento oficial da régua D-3, D0, D+1, D+3, D+5.
  - Validação atômica de quitação: cancela agendamentos de faturas já pagas ou canceladas antes do disparo, evitando cobrança duplicada.
  - Runner de cron job dedicado `scripts/cron-runner.js` para cPanel.
- **Página Pública da Fatura (`resources/views/public/invoice.blade.php`)**:
  - Nova interface responsiva moderna e limpa para `/fatura/{token}`.
  - Card com dados do Tenant, status com badge dinâmico, QR Code visual do Pix, botão de 1 clique "Copiar Código Pix" com Toastify e aba de Cartão de Crédito com tokenização oficial do Mercado Pago JS v2.
  - Polling assíncrono em tempo real com SweetAlert2 de celebração ao identificar o pagamento.
- **Suporte Local a SQLite e Trava Estrita de Produção**:
  - Adicionado suporte a `sqlite` no `config/database.php` e gerado `database/database.sqlite` e `.env` local para acompanhamento imediato pelo desenvolvedor.
  - **Trava de Segurança Mandatória:** `config/database.php` aborta a execução se `APP_ENV=production` e `DB_CONNECTION=sqlite`, assegurando que em servidores remotos de produção seja utilizado estritamente MySQL ou MariaDB!
- **Compatibilidade Dual de Infraestrutura (cPanel/WHM e Docker)**:
  - Arquivo de entrada `app.js` na raiz homologado para o Phusion Passenger / "Setup Node.js App" do cPanel.
  - Script alternativo `scripts/cron-runner.js` para execução de régua de cobrança e conciliação em Cron Job cPanel sem exigir Redis persistente.
  - `Dockerfile` multi-stage otimizado (PHP 8.4-FPM com Composer, GD, OPcache e MySQL).
  - `docker-compose.yml` orquestrado com serviços App (PHP 8.4-FPM), Nginx (porta 80), MariaDB 10.11 e Redis 7 com healthchecks.
- **Novo Repositório Oficial `WHATSAPP-EMPRESAS-OFICIAL`**:
  - Configuração do novo repositório oficial no Git para versionamento definitivo da plataforma SaaS.
  - Scripts de automação `criar-e-publicar-novo-repo.ps1` e `criar-e-publicar-novo-repo.bat` configurados para adicionar o novo remote, executar commit humanizado e publicar (`git push`).

### Removido
- **Eliminação Total de Bibliotecas e Código de Engenharia Reversa (Resíduo Zero)**:
  - Excluídos todos os arquivos legados, controllers, rotas, requests, jobs, eventos e listeners relacionados à Evolution API (`EvolutionClient`, `EvolutionGoWebhookController`, `EvolutionGoSyncService`, `ProcessEvolutionWebhookJob`, `SyncEvolutionInstancesJob`, `EvolutionWebhookReceived`, `LogEvolutionWebhookReceived`, etc.).
  - Substituído `WhatsAppEvolutionDiagnoseCommand` pelo comando oficial `WhatsAppMetaDiagnoseCommand` (`whatsapp:meta-diagnose`).
  - Removidas mais de 780 linhas de emulação de QR Code, Chromium e websockets de browser em `instance.blade.php`.
  - Descontinuada a coleção Postman legada `EvolutionAPIGo-v3.0.postman_collection.json` e substituída pela coleção oficial `Meta-WhatsApp-Cloud-API-v21.0.postman_collection.json`.
  - Neutralizado o script legado `create_bot.php` com senhas estáticas em favor dos seeders seguros.

### Melhorado
- **UI/UX Global com SweetAlert2 e Toastify**:
  - Eliminação de 100% de alertas nativos do navegador (`window.alert` e `window.confirm`).
  - Todas as confirmações de ações críticas agora utilizam **SweetAlert2** e os avisos de sucesso/erro utilizam **Toastify** de forma assíncrona via AJAX.
  - Gaveta de Ajuda lateral interativa (Help Drawer) integrada em todas as telas com instruções passo a passo da Meta e do Mercado Pago.


## [2.7.2] - 2026-11-01
### Adicionado
- **Agrupamento Inteligente de Carnês no Painel Administrativo Global (`/admin/billing/invoices`)**:
  - Consolidação automática de parcelas de mesmo carnê/lote (`batch_id`) em uma Linha Mestra de Carnê (`carne-group-row`), com identificador visual do lote, badge roxo `Carnê {N}x` e contadores consolidados.
  - Barra de progresso visual de quitação do carnê com porcentagem e contadores de parcelas pagas, pendentes e vencidas.
  - Gaveta expansível (acordeon) com botão **"Ver {N}x"**, permitindo consultar e operar cada parcela individualmente (baixa manual, disparo por WhatsApp, visualização de detalhes).
  - Alternador de visualização na barra superior: **Modo Carnês** (agrupado por padrão) e **Modo Individual** (todas as faturas soltas).
  - Botão **"Expandir Carnês / Recolher Carnês"** para abrir ou fechar todas as gavetas com 1 clique.

### Melhorado
- **Janela Flexível de Disparos de Cobrança (`BillingDispatcher`)**:
  - Ajustada a tolerância para offsets de atraso e notificações do dia: cobranças programadas para a data de hoje são agendadas imediatamente mesmo que o horário inicial do cron já tenha passado, evitando perda de disparos.
- **Fallback Multi-Canal em Confirmação de Quitação (`InvoiceSettlementService`)**:
  - Inclusão de fallback transparente para o Mailer global da plataforma quando o tenant não possui servidor SMTP próprio habilitado, assegurando que o cliente receba a confirmação de baixa por e-mail e WhatsApp simultaneamente.

## [2.7.1] - 2026-10-31
### Melhorado
- **Logo maior e padronizada nos e-mails**: novo bloco único `EmailLayoutRenderer::logoHtml()` (até 220x72px, alinhado à esquerda, com separador sutil) usado no layout base, na variável `{logo_html}` e na injeção automática no cabeçalho. Logos legadas pequenas inseridas nos templates são normalizadas para o mesmo padrão.
- **Contingência de pagamento no bloco de QR Code**: botão "Abrir fatura e pagar" (na cor de destaque do tenant) para quando Gmail/Outlook bloqueiam a imagem do QR Code.

### Corrigido
- **Modal de mensagens WhatsApp**: a mídia da mensagem anterior não fica mais "presa" ao abrir os detalhes de outra mensagem.

## [2.7.0] - 2026-10-31
### Adicionado
- **Agrupamento Inteligente de Carnês e Faturas Parceladas (`/painel/invoices`)**:
  - Consolidação automática de parcelas de mesmo cliente e lote em uma **Linha Mestra de Carnê**, eliminando a poluição visual de dezenas de faturas soltas na listagem.
  - Gaveta expansível (acordeon) com botão **"Ver Parcelas"**, permitindo consultar e operar cada parcela individualmente (link de pagamento, reenvio WhatsApp, baixa manual, QR Code Pix, cancelamento).
  - Indicador visual de progresso de quitação do carnê com mini barra colorida e contadores consolidados de parcelas pagas, pendentes e vencidas.
  - Alternador de visualização na barra superior: **Modo Carnês** (agrupado por padrão) e **Modo Individual** (todas as faturas soltas).
  - Botão **"Expandir Todos / Recolher Todos"** para abrir ou fechar todas as gavetas de parcelas com 1 clique.
  - Nova aba e filtro de busca exclusivo: **"Carnês & Parcelamentos"** com contador em tempo real.
  - Checkbox mestre do carnê integrado com a barra de ações em lote.
- **Gerenciador de Sessões Ativas e Controle de Multi-login (`/admin/analytics`)**:
  - Implementado `SessionManagerService` com revogação imediata de sessões ativas, destruição de arquivos de sessão e blacklist em cache.
  - Botão individual de **Desconectar Sessão** em tempo real com confirmação SweetAlert2 e notificação Toastify.
  - Botão em massa de **Derrubar Outras Sessões** para encerrar sessões clonadas ou abertas em múltiplas máquinas com 1 clique.
  - Chave seletora configurável de **Multi-login** (Sessão Única Estrita vs Múltiplas Instâncias simultâneas) persistida em configurações de segurança.
  - Proteção no middleware `SecureSession` com logout automático e encerramento imediato de sessões revogadas remotamente.
- **Personalização Dinâmica de Templates de E-mail com WhiteLabel Total**:
  - Renderização com identidade visual completa do Tenant em `EmailTemplateRenderer` e `EmailLayoutRenderer`.
  - Herança dinâmica de Logo (`TenantEmailConfig` ou WhiteLabel do Tenant) e paleta de cores corporativa (`primary_color`, `secondary_color`).
  - Resolução robusta de variáveis de merge (`{company_name}`, `{customer_name}`, `{invoice_id}`, `{amount}`, `{due_date}`, `{pix_code}`, `{pix_qrcode_url}`, `{payment_url}`).
  - Fallback automático e seguro para branding padrão da plataforma caso o lojista não possua customizações ativas.
  - Migração de banco de dados `2026_10_31_160000_update_email_templates_with_tenant_branding` para atualizar os templates padrão já existentes no banco com a estrutura compatível com as cores e logomarcas dos tenants.
- **Visualização Rápida no Dashboard Administrativo (`/admin/dashboard`)**:
  - Botão de ação rápida com ícone de olho em cada linha das seções **"Últimas faturas"** e **"Últimas mensagens"**.
  - **Modal de Visualização Rápida de Fatura**:
    - Exibe código da fatura, cliente, valor, data de emissão, vencimento e badge de status em tempo real.
    - Renderização do QR Code Pix oficial, botão de cópia com 1 clique do código Pix Copia e Cola e link direto para o checkout/fatura.
  - **Modal de Visualização Rápida de Mensagem**:
    - Exibe destinatário formatado, data/hora do disparo, badge de status de entrega (`delivered`, `sent`, `failed`, `pending`).
    - Exibição na íntegra do corpo da mensagem disparada com suporte a quebras de linha e formatação.
    - Card de diagnóstico técnico com causa detalhada em mensagens que apresentarem falha (`failed`) e botão de cópia do payload JSON de depuração.
- **Avatar Dinâmico da Instância WhatsApp Conectada em `/admin/tenants`**:
  - Exibição automática da foto de perfil da instância do WhatsApp ativa/conectada com indicador de status online verde no canto inferior direito.
  - Resolução hierárquica inteligente: foto de perfil da instância conectada -> foto de qualquer instância vinculada -> logo do WhiteLabel -> logo das configurações de e-mail -> avatar do usuário administrador -> inicial estilizada.
  - Inclusão do atributo `referrerpolicy="no-referrer"` nas tags de imagem para prevenir bloqueios de hotlink e restrições de cross-origin dos servidores de CDN da Meta/WhatsApp.
- **Redesign Comercial e Correção da Central de Mensagens WhatsApp (`/admin/whatsapp/messages`)**:
  - Correção no acionamento do botão de visualização de detalhes (olhinho) e reenvio, migrando de inline handlers vulneráveis com `@json` para event delegation desacoplada e segura com chamada assíncrona para `/admin/whatsapp/messages/{id}/detail`.
  - Redesign completo de padrão SaaS Enterprise: layout em largura total (full-width) sem barras laterais espremendo as colunas, 4 cards de KPIs modernos com taxa de entrega percentual, pílulas de filtros rápidos de 1 clique (`Apenas Falhas`, `Entregues`, `Hoje`), busca com suporte a `evolution_id`, avatares com badges de status e botões de ação arredondados com micro-hover.
  - **Modal de Detalhes com Mockup de Smartphone WhatsApp**: Visualização ultra-realista de smartphone com notch, contato, carimbo de hora, balão verde com Markdown formatado, checks duplos, pré-visualização de imagem/QR Code anexado e botão de cópia de texto.
  - **Aba de Diagnóstico Técnico e API**: Exibe causa detalhada de falha em mensagens com status `failed`, metadados de entrega e visualizador de JSON retornado pela API com botão de cópia com 1 clique.
- **Disparo Multi-Canal Simultâneo (WhatsApp + E-mail)**:
  - Suporte ao envio de faturas, lembretes de vencimento, cobranças em atraso e confirmações de baixa/liquidação via WhatsApp e E-mail.
  - Regra de despacho resiliente: se ambos os canais estiverem ativos e com destinatário válido, dispara nos dois; se apenas um estiver disponível, envia pelo canal disponível com fallback automático.
  - Separação estrita de formatos: o canal WhatsApp recebe mensagem em texto puro formatado em Markdown com anexo da imagem do QR Code Pix gerado; o canal de E-mail recebe HTML responsivo formatado com identidade visual do tenant, eliminando a exibição de tags HTML brutas no WhatsApp.

### Corrigido
- **Identificação de Administradores vs Usuários no Feed em Tempo Real**:
  - Resolvido o problema em que o administrador logado aparecia com nome de outro usuário ("Alexandro") no feed do Analytics.
  - Criadas colunas `admin_id` e `guard` na tabela `system_visits` com migração retroativa, desacoplando o guard `admin` do guard `web` (`users`).
  - Exibição do perfil correto com badges específicos de Administrador Master e Tenant.
- **Paleta Cromática de Alto Contraste no Analytics (/admin/analytics)**:
  - Eliminação da monocromia esverdeada nos gráficos de tráfego e distribuição de dispositivos.
  - No gráfico de **Evolução de Tráfego & Visitas**:
    - Visitas Totais com curva em Azul Royal (#2563eb) e preenchimento gradiente suave.
    - Visitantes Únicos com linha tracejada em Roxo Violeta vibrante (#8b5cf6).
    - Usuários Logados em Verde Esmeralda (#10b981).
  - No gráfico Donut de **Aparelhos & Plataformas**:
    - Desktop em Azul Royal (#2563eb), Smartphone em Verde Esmeralda (#10b981) e Tablet em Âmbar (#f59e0b).
    - Bordas divisórias de alta definição entre fatias para legibilidade instantânea em temas escuro e claro.
  - Padronização semântica das barras de status financeiros (Pagas, Pendentes, Vencidas, Canceladas) e cor oficial do WhatsApp (#25d366) na volumetria de mensagens.
- **Carregamento e Métricas do Dashboard Administrativo**:
  - Correção na apuração das consultas financeiras no `DashboardController` (`today_amount`, `invoices_pending_count` e `invoices_pending_amount`), tratando faturas de cobrança com mapeamento correto de status.
- **Importação de Dependências no Despachador de Cobranças (`BillingDispatcher`)**:
  - Inclusão da importação correta da classe de fatura (`App\Models\Invoice`), corrigindo erro fatal que impedia a execução da régua preventiva e da cobrança em atraso dos tenants.
- **Resolução Determinística de Templates Financeiros por Canal**:
  - Correção no carregamento do template de e-mail para faturas geradas, cobrança e lembrete de vencimento, garantindo que o tipo correto seja consultado nas configurações do tenant sem misturar com templates de reativação de clientes.

---

## [2.6.5] - 2026-10-26
### Adicionado
- **Histórico Unificado de Cobrança (`/painel/historico-cobranca`)**:
  - Listagem consolidada de todos os disparos efetuados pelo tenant (régua preventiva, no vencimento, atraso e liquidações).
  - Filtros avançados por canal (WhatsApp / E-mail), período, status de entrega e cliente.
  - **Simulador Interativo de Smartphone WhatsApp**: Renderização fiel da conversa com balões de mensagem oficiais, carimbo de data/hora, status ticks e visualização do QR Code Pix.
  - **Visualizador Webmail Iframe Sandboxed**: Pré-visualização segura e isolada do e-mail em HTML enviado ao cliente.
- **Reenvio Manual e Cancelamento Imediato de Cobranças**:
  - Botão de reenvio manual com 1 clique para faturas pendentes ou em atraso, com atualização instantânea do histórico.
  - Cancelamento seguro de cobranças avulsas e pacotes com suspensão imediata de todos os disparos agendados e cancelamento no gateway.

### Melhorado
- **Proteção de Cobranças e Suspensões em Dias Úteis**:
  - Régua de disparo automático inteligente: impede o envio de cobranças e notificações de atraso aos sábados, domingos e feriados nacionais, respeitando o horário comercial dos clientes.
  - Bloqueio de suspensão automática de serviços e contas no WHMCS aos finais de semana e feriados, evitando interrupções em períodos sem expediente bancário.

---

## [2.6.0] - 2026-10-21
### Adicionado
- **Programa de Afiliados Enterprise**:
  - Links de afiliados personalizados `/a/{slug}` direcionando automaticamente para a página de prospecção do sistema.
  - Aplicação instantânea de cupom de desconto exclusivo ao comprador na 1ª mensalidade/compra.
  - Split automático de pagamento no Mercado Pago utilizando `application_fee`, transferindo comissões aos afiliados no momento exato da liquidação da fatura.
  - Moderação completa de afiliados no painel administrativo: aprovação, rejeição, visualização de dados cadastrais e chave PIX para repasse.
  - Notificações automáticas via WhatsApp e E-mail a cada indicação convertida ou comissão liberada.
  - Painel exclusivo do Afiliado com gráficos analíticos de conversão, cliques, comissões pendentes e valores sacados, com suporte total aos temas Dark e Light.
- **Módulo de Assinatura Eletrônica de Contratos**:
  - Assinatura digital responsiva com modal otimizado para celulares e tablets.
  - Envio automático de via assinada ao fornecedor e ao cliente via WhatsApp logo após a conclusão.
  - Máscaras dinâmicas de CPF/CNPJ, validação de signatário e rodapé institucional padronizado.

---

## [2.5.5] - 2026-10-05
### Adicionado
- **Monitor de Webhooks Enterprise (`/admin/webhook-monitor`)**:
  - Central de auditoria e observabilidade em tempo real de webhooks recebidos dos gateways Mercado Pago, Efí Pro e SumUp.
  - Exibição de tempo de resposta, cabeçalhos, status HTTP, payload JSON formatado e botão de reprocessamento manual de eventos com falha.
- **Sincronização Assíncrona de Alto Desempenho com WHMCS**:
  - Processamento em segundo plano nativo do sistema operacional (`exec`) garantindo zero timeout no painel para tenants com milhares de faturas.
  - Long polling com modal interativo de progresso, exibindo faturas processadas, atualizadas e novas em tempo real.
- **Baixa Manual e Liquidação em Lote**:
  - Suporte à baixa manual de faturas avulsas e pacotes diretamente pelo painel administrativo e do tenant.
  - Liquidação de parcelas antecipadas e geração de recibos de quitação automáticos.

### Corrigido
- **Isolamento de Credenciais de Gateways**:
  - Garantia de isolamento estrito das credenciais bancárias do tenant, impedindo que requisições usem chaves do sistema global por engano.
- **Listagem de Clientes para Cobrança Avulsa no Admin**:
  - Correção na consulta do modal de fatura avulsa para carregar todos os clientes da base sem restrição de paginação na listagem rápida.

---

## [2.5.4] - 2026-09-28
### Adicionado
- **Módulo Exclusivo de Marketing e Captura de Leads**:
  - Landing pages promocionais com rastreamento de campanhas UTM, simulador de ROI em tempo real e FAQ dinâmico.
  - Widget de prova social com toasts de conversão recente e medalhão de garantia incondicional de 7 dias.
- **Controle Individual de Ativação de Páginas**:
  - Gestão de visibilidade de rotas públicas e institucionais diretamente pelo painel do tenant e admin.

---

## [2.5.3] - 2026-09-23
### Corrigido
- **Execução do Cron Principal do Painel (`/admin/system/cron`)**:
  - Correção da ausência de agendador em `routes/console.php`. Registrado `Schedule::command('cron:run')->everyMinute()->withoutOverlapping(10)` para que o `php artisan schedule:run` do cPanel/Linux dispare todos os 22 cron jobs da central automaticamente.
  - Implementado o comando `php artisan cron:run` com suporte a `--force` para execução manual ou agendada pelo servidor.
  - Auto-recuperação de travas de arquivo estagnadas (`cron.lock` > 10 minutos) em `CronManager`, impedindo que um processo abortado trave as futuras rodadas do agendador.
  - Correção no `VerifyCronToken` para permitir disparo direto por administradores logados no painel e geração de token determinístico e seguro baseado no `app.key`, eliminando erros 401 por variáveis pendentes no `.env`.
  - Adicionado botão de ação rápida **"Executar Cron Principal"** na interface da Central de Crons com processamento atômico e feedback em tempo real via SweetAlert2.
  - Exibição de comandos prontos do cPanel com caminhos absolutos e botões de cópia com 1 clique (Laravel Schedule, CLI Direto e cURL Webhook).

### Adicionado
- **Central de Notificações Unificada e Agrupada no Sininho (`/admin` e `/painel`)**:
  - Agrupamento visual completo por categorias críticas: **Cancelamentos** (`cancellation`), **Quitações** (`settlement`), **Novos Cadastros** (`registration`), **Novas Assinaturas & Renovações** (`subscription`), **Faturas & Cobranças** (`billing`), WhatsApp, Segurança e Sistema.
  - Pílulas interativas de filtro rápido de categoria diretamente no dropdown da barra superior.
  - Isolamento rigoroso e independente: administradores recebem eventos da plataforma/tenants, e cada lojista recebe exclusivamente os eventos de seus clientes e de sua própria conta.
- **Notificações por E-mail Automáticas e Integradas**:
  - Envio automático de e-mail sincronizado a cada notificação in-app disparada (`NotificationDispatcher`).
  - Suporte a templates em HTML formatados com identidade visual para:
    - Novas assinaturas e renovações de planos.
    - Novos cadastros de tenants e novos clientes cadastrados no CRM.
    - Novas faturas e carnês avulsos gerados.
    - Cancelamentos de faturas ou planos com registro de justificativa.
    - Quitações e confirmações de recebimento financeiro.
- **Alerta Áudio-Visual Resiliente com Suporte a Aba Minimizada**:
  - **Web Worker Anti-Throttling**: Polling contínuo em segundo plano que impede o navegador de desacelerar as verificações quando a aba estiver minimizada ou inativa.
  - **Áudio Chime Harmônico**: Síntese sonora via Web Audio API de dois tons harmônicos e cristalinos com desbloqueio preventivo na primeira interação do usuário.
  - **Notificações Nativas do Sistema (Web Notifications API)**: Pop-ups no desktop com título, corpo e foco automático no clique quando a janela estiver minimizada (`document.hidden`).
  - **Piscar Título da Aba (`document.title`)**: Alternância dinâmica com emoji de sino avisando novas notificações até o retorno do foco.
- **Editor Rico de Mensagens WhatsApp (`/painel/whatsapp/manual`)**:
  - Barra de ferramentas com formatação completa (Negrito `*texto*`, Itálico `_texto_`, Riscado `~texto~`, Monoespaçado ````texto````, Lista numerada, Lista com marcadores, Citação e Limpar).
  - Seletor interativo de Emojis populares do WhatsApp.
  - Preview dinâmico instantâneo com renderização fiel do layout do WhatsApp.
- **Acesso e Visibilidade de Cobranças Avulsas**:
  - Novo item direto **"Cobranças Avulsas"** no submenu Financeiro da barra lateral (Sidebar).
  - Nova aba **"Cobranças Avulsas"** com contagem dinâmica e filtro por tipo em "Faturas & Cobranças".
  - Botão de ação rápida **"Gerar Cobrança Avulsa"** na tabela de Clientes (CRM) com pré-preenchimento automático do cliente.
  - Atalho de destaque **"Nova Cobrança Avulsa"** no painel de Operação Rápida do Dashboard.
  - Badges visuais **"Avulsa"** e **"Carnê X/Y"** na listagem de faturas para fácil identificação.
- **Ações nas Faturas da Assinatura/Plano (`/painel/faturas`)**:
  - **Pagar Agora**: Modal com PIX Copia e Cola, QR Code escaneável e link de checkout do gateway.
  - **Visualizar Fatura**: Modal detalhado com discriminativo, valores, vencimento, data de pagamento e motivo de cancelamento.
  - **Solicitar Cancelamento**: Fluxo com justificativa via SweetAlert2 e processamento via AJAX global.
- **Cobrança Avulsa (À Vista e Carnê Parcelado) para Tenants**:
  - Emissão pontual à vista ou carnês de 2x a 24x com cálculo de juros simples mensal.
  - Live Preview e simulação em tempo real de parcelas e vencimentos no modal.
  - Envio automático opcional da 1ª parcela via WhatsApp.

### Melhorado
- **Padronização Global de Notificações**: Confirmações e avisos usando SweetAlert2 e Toastify sem nenhum `alert()` nativo do navegador.

### Corrigido
- **Abertura do Modal de Nova Fatura Avulsa (`/painel/faturas`)**: Correção no fechamento estrutural de tags HTML `</div>` dos modais no template Blade que deixavam o modal `#modal-create-standalone-invoice` aninhado e impediam sua abertura pelo Bootstrap. Adicionada também inicialização resiliente e função global `openCreateStandaloneModal()` para acionamento direto nos botões do cabeçalho e do estado vazio (empty state).
- **Falha no Envio de Mensagens WhatsApp (`WhatsAppQueuePlanner`)**: Correção do erro `Column not found: 1054 Unknown column 'failed_at'` na verificação de concorrência (`activeJobsCount`). Implementada verificação defensiva de esquema (`Schema::hasTable` e `Schema::hasColumn`) garantindo estabilidade no envio manual e automático.
- **Erro 500 no Painel (`/painel`)**: Correção da cláusula SQL em `TenantPanelController@dashboard` que consultava `is_active` em vez da coluna correta `active` na tabela `tenant_gateways`. Envolvido todo o cálculo de métricas em `try/catch` defensivo com fallbacks seguros.
- **Faturas do Plano sem Ações**: As faturas da aba "Minha Assinatura (Plano)" agora possuem ações completas e integradas de pagamento, visualização e cancelamento.

---

## [2.5.1] - 2026-09-15
### Adicionado
- Reorganização completa da barra lateral (Sidebar) do painel Superadmin e do painel do Cliente (Tenant), separando os menus em grupos lógicos (Principal, Comunicação, Comercial & Financeiro, Módulos & Integrações).

### Melhorado
- Monitoramento de Webhooks: Adicionado tratamento defensivo (`try/catch`) na leitura dos registros recentes e estatísticas, evitando quedas da página por indisponibilidades rápidas no banco de dados.

### Corrigido
- Edição de Cliente no Tenant: Correção de bug no Javascript do modal (`editCustomer`) que impedia o preenchimento e edição correta dos dados do cliente.

---

## [2.5.0] - 2026-09-14
### Adicionado
- Nova interface no painel do lojista (Instâncias) para gerenciar limites de disparos Anti-Ban (Liberar/Bloquear) usando SweetAlert2.

### Melhorado
- Pacing Inteligente Anti-Ban: O sistema agora espaça as cobranças de forma natural (3 minutos para usuários liberados, 5 minutos para limitados a 250/dia) evitando bloqueios no WhatsApp.
- Extração de PIX (MercadoPago): Varredura das notas da fatura WHMCS em busca da string `BR.GOV.BCB.PIX` ou `000201` para preencher automaticamente as variáveis `{pix_copia_cola}` e a imagem na cobrança.

### Corrigido
- Variáveis no preview de template: As strings falsas do `{qrcode_url}` e `{pix_copia_cola}` não estavam sendo renderizadas na visualização ao vivo no editor de Templates.

## [1.6.69] - 2026-06-06

### Adicionado
- **PWA footer menu configuravel**: menu inferior do PWA agora e totalmente controlado pelo painel admin.
  - Nova tabela `pwa_footer_menu_items` com migration + seed dos 5 itens padroes (Inicio, Mensagens, Cobrancas, QR/Instancia, Perfil).
  - Model `PwaFooterMenuItem` com scopes `active()` e `ordered()`, metodos `isVisibleFor()`, `resolvedUrl()` e `isActive()`.
  - CRUD completo no `PwaAdminController`: `menuIndex`, `menuStore`, `menuUpdate`, `menuDestroy`, `menuReorder`.
  - View `admin/pwa/menu.blade.php` com drag-and-drop via SortableJS para reordenar itens.
  - Rotas admin `/admin/pwa/menu` (GET, POST, PUT, DELETE) e `/admin/pwa/menu/reorder` (POST).
  - Cada item suporta: label, icone (Bootstrap Icons), URL manual ou route name, posicao, ativo/inativo, match patterns e visibilidade (tenant/admin/ambos).
  - Menu admin sidebar atualizado: PWA agora tem submenu com "Configuracao" e "Menu Footer".

### Corrigido
- **PWA erro 500 no Inicio**: `footer-nav.blade.php` reescrito para ler itens do banco com `try/catch` defensivo. Se o banco falhar, cai automaticamente para fallback hardcoded dos 5 itens padrao, eliminando erro 500 ao clicar em "Inicio".

### Alterado
- **Tenant relatorios (`/painel/relatorios`)**: layout reformulado para aparencia premium sem scroll global.
  - Hero section com eyebrow e titulo gourmet.
  - KPIs em cards com gradiente sutil, hover com elevacao e sombra, borda colorida lateral por metrica.
  - Barra de filtros em container arredondado e compacto.
  - Cards de cliente com hover interativo, badges de status com cores pastel (`bg-*-subtle`).
  - Apenas a area de dados tem scroll interno; header, KPIs e filtros ficam fixos no viewport.
  - Scrollbar customizada e discreta.

---

## [1.6.68] - 2026-06-06

### Corrigido
- **Login supervisionado (tenant assist)**: corrigido conflito entre rotas `/painel` de compatibilidade admin e rotas reais do tenant.
- **Rotas `/painel`**: removido bloco generico `prefix('painel')` com middleware `admin` que bloqueava todos os tenants. Substituído por rotas individuais de compatibilidade que nao conflitam com rotas tenant (`/painel/whatsapp/instancia`, `/painel/whatsapp/mensagens`, `/painel/cobrancas`, `/painel/contratos/criar`, `/painel/addons/gestao-financeira`).
- **Tenant dashboard**: `/painel` volta a apontar para `tenant.dashboard` do TenancyServiceProvider, permitindo acesso normal do tenant e do admin em modo assistido.
- **Tenant profile**: `/painel/perfil` volta a apontar para `tenant.profile` sem interceptacao de middleware admin.
- **Service Worker**: adicionadas rotas de assist (`/admin/tenancy/tenants`, `/painel/voltar-admin`, `/supervised-login`, `/impersonation`) ao `BYPASS_PATHS` para evitar cache do fluxo de login supervisionado.

---

## [1.6.67] - 2026-06-06

### Corrigido
- **PWA**: `auth('admin')` agora usado no fluxo administrativo da PWA em vez de `auth()` (guard padrão).
- **PWA/redirecionamento**: `PwaController::dashboard()` nao redireciona mais para `/painel` sem garantia; usa `route('admin.dashboard')` com fallback para `/admin`.
- **PWA/splash**: `splash()` usa `auth('admin')` e `Route::has()` com fallback seguro para login/admin.
- **PWA/manifest**: `start_url` agora e fixo em `/pwa/splash` para evitar inicio em rotas privadas quebradas.
- **PWA/Service Worker**: versao de cache dinamica real (`pwa.cache_version` -> `app.version` -> `time()`); limpeza de caches antigos corrigida (filtra apenas caches `kdkhost-whatsapp-*`); rotas privadas (login, logout, admin, painel, pwa, api, webhooks, cron) sempre vao para rede; removido fallback perigoso para `/` em erro de fetch.
- **PwaServiceProvider**: rotas privadas PWA (`/pwa/dashboard`, `/pwa/mensagens`, `/pwa/cobrancas`, `/pwa/instancia`, `/pwa/perfil`) agora usam middleware `['web', 'admin', '2fa', 'admin.cache']`.
- **PwaServiceProvider**: rotas de configuracao admin PWA (`/admin/pwa/*`) tambem usam `['web', 'admin', '2fa', 'admin.cache']`.
- **Rotas `/painel`**: adicionado bloco completo de compatibilidade com middleware `['web', 'admin', '2fa', 'admin.cache']`:
  - `/painel`, `/painel/dashboard`, `/painel/perfil`
  - `/painel/whatsapp/instancia`, `/painel/whatsapp/mensagens`
  - `/painel/cobrancas`
  - `/painel/contratos/criar?template=12`
  - `/painel/addons/gestao-financeira`
- **Fallback SPA**: regex atualizada para excluir `pwa`, `logout`, `register`, `password`, `webhooks`, `manifest.json`, `sw.js`, `up`, `storage`.

---

## [1.6.66] - 2026-06-05

### Corrigido
- **File Manager**: corrigido retorno vazio ao listar diretorios. O aaPanel retorna `{data: {DATA: [...], PAGE: ...}}`; o controller agora extrai o wrapper interno para a view.
- **Bancos de dados**: corrigido filtro que operava no objeto wrapper em vez do array de databases.
- **FTP**: corrigido filtro e extracao de dados internos do aaPanel.
- **Backups**: corrigido extracao de dados internos para listagem e download.
- **Crons**: corrigido filtro e extracao de dados internos; validacoes de propriedade ajustadas.
- **SSL/HTTPS**: corrigido `loadSsl` para acessar `d.data?.data` (status, httpTohttps, cert_data); adicionado `siteName` ao `applyLetsEncrypt`.
- **PHP**: corrigido `loadPhp` para acessar versao e configuracoes via `d.data?.data`.
- **Logs**: corrigido `loadLogs` para acessar conteudo via `d.data?.data`.

---

## [1.6.65] - 2026-06-05

### Adicionado
- **Crons editaveis**
  - endpoint `PUT /accounts/{account}/crons` com validacao de propriedade.
  - modal SweetAlert2 com campos: nome, tipo, where1, hora, minuto, semana, sType, sBody, sName.
  - botao editar (lapis) em cada linha da tabela de crons.
- **Download de backups**
  - endpoint `GET /accounts/{account}/backups/download` que faz proxy do arquivo via `GetFileBody`.
  - validacao de propriedade: verifica se o backup pertence ao site via `listBackups`.
  - botao download (icone) em cada linha da tabela de backups.
- **File Manager completo: abrir e editar arquivos**
  - endpoints `GET /accounts/{account}/files/content` e `POST /accounts/{account}/files/content`.
  - modal SweetAlert2 com textarea para edicao de conteudo do arquivo.
  - botao editar (lapis) aparece apenas para arquivos (diretorios nao tem).
- **Criacao de contas FTP**
  - endpoint `POST /accounts/{account}/ftps` usando `createFtp` do cliente.
  - modal SweetAlert2 com usuario e senha (minimo 6 caracteres).
  - atualiza `ftp_username` e `ftp_password` no banco local em caso de sucesso.

### Modificado
- **SSL/HTTPS**: endpoints verificados e funcionando (`sslStatus`, `forceHttps`, `renewSsl`, `deleteSsl`).
- **Logs**: Access Log e Error Log com endpoints corrigidos (`GetSiteLogs`, `GetErrLog`).

---

## [1.6.64] - 2026-06-05

### Adicionado
- **Controle total de conta aaPanel — abas de gerenciamento completo**
  - nova UI com navegacao por tabs: SSL/HTTPS, PHP, Bancos, FTP, Backups, Arquivos, Logs Servidor, Crons, Redirects.
  - tab **SSL**: status do certificado, emissor, validade, botoes para Aplicar SSL (Let's Encrypt), Renovar, Forcar HTTPS, Remover SSL.
  - tab **PHP**: exibe versao atual + configuracoes principais (`upload_max_filesize`, `memory_limit`, `max_execution_time`, etc.) com seletor de versao dinamico carregado do servidor aaPanel.
  - tab **Bancos**: lista filtrada pelo `database_name` e `database_username` da conta; exclusao valida propriedade (403 caso contrario).
  - tab **FTP**: lista filtrada por `ftp_username` e dominio no path; alteracao de senha via modal com input password; exclusao valida propriedade.
  - tab **Backups**: lista com `search=siteId` e `type=0` via `/data?action=getData&table=backup`; botao Novo Backup; exclusao por ID do backup.
  - tab **Arquivos**: navegador de arquivos com subir nivel, novo arquivo, nova pasta; path traversal bloqueado via `realpath()` retornando 403.
  - tab **Logs Servidor**: Access Log (50 linhas) e Error Log via `GetSiteLogs` / `GetErrLog` lado a lado.
  - tab **Crons**: lista filtrada por dominio; exclusao valida propriedade via nome/sName.
  - tab **Redirects**: lista via `GetRedirectList`; exclusao via `DeleteRedirect` com `siteName` + `name`.

### Modificado
- **Todos os confirm/prompt nativos substituidos por SweetAlert2**
  - nenhum `confirm()` ou `prompt()` do navegador permanece no modulo aaPanel.
  - Swal.fire() com icone de warning, botao "Remover" em vermelho e "Cancelar".
  - input password com validacao de comprimento minimo para alteracao de senha FTP.
- **Sincronizacao em tempo real (polling)**
  - cada tab ativa recarrega automaticamente a cada 10 segundos, mantendo dados sempre atualizados.
- **Cache no AaPanelClient**
  - `listDatabases()`, `listFtps()`, `listCrons()`, `listRedirects()`, `getSystemTotal()`, `getNetwork()` usam cache em memoria com TTL de 30 segundos.
  - `getSiteInfo()` otimizado: tenta endpoint direto `/site?action=GetSite` primeiro, fallback para `listSites()`.
- **Endpoints aaPanel corrigidos com base na documentacao oficial**
  - `listBackups`: `/data?action=getData&table=backup`
  - `deleteBackup`: `/site?action=DelBackup` com ID do backup
  - `listRedirects`: `/site?action=GetRedirectList` com `siteName`
  - `deleteRedirect`: `/site?action=DeleteRedirect` com `siteName` + `name`
  - `deleteSsl`: `/site?action=CloseSSLConf` com `updateOf` + `siteName`
  - `getErrorLog`: `/site?action=GetErrLog` (sem parametro `lines`)
  - `listFiles`: `/files?action=GetDir` com `p`, `showRow`, `is_operating=true`

### Seguranca
- **Isolamento de conta reforcado**
  - cada conta so ve seus proprios bancos, FTPs, crons, arquivos e backups.
  - validacao de propriedade em todas as acoes de delecao (`destroyDatabase`, `destroyFtp`, `destroyCron`, `destroyFile`, `destroyBackup`, `destroyRedirect`).
  - path traversal bloqueado: `files()` e `destroyFile()` validam que o path esta dentro de `/www/wwwroot/{dominio}`.

---

## [1.6.63] - 2026-06-03

### Corrigido
- **Links do footer PWA apontavam para rotas inexistentes**
  - componente `resources/views/components/pwa/footer-nav.blade.php` agora usa rotas nomeadas (`tenant.billing.history`, `tenant.history.whatsapp`, etc.) com fallback seguro para rotas `admin.*`, evitando erro 404 em dispositivos moveis.
- **Service Worker cacheava paginas de login, logout e rotas privadas**
  - `app/Modules/Pwa/Http/Controllers/PwaController.php`: lista `BYPASS_PATHS` ampliada com `/cadastro`, `/esqueci-senha`, `/redefinir-senha`, `/webhooks` e `/cron`.
  - logica de cache separada em `CACHE_NAME` (estatico) e `RUNTIME_CACHE` (dinamico), cacheando apenas respostas `GET` com status `200` e `type === 'basic'`.
- **SanitizeInput quebrava HTML rico de contratos e templates**
  - `app/Http/Middleware/SanitizeInput.php`: adicionadas excecoes por rota (`admin.contracts.*`, `tenant.contracts.*`, etc.) e por campo (`content`, `contract_html`, `template_html`, etc.).
  - novo metodo `sanitizeRichHtml()` remove `<script>`, eventos inline e protocolos `javascript:` mas preserva estrutura HTML segura.
- **Advanced settings da Evolution sem encoding no nome da instancia**
  - `getAdvancedSettings()` e `updateAdvancedSettings()` em `EvolutionClient` agora usam `rawurlencode($this->resolveInstanceName())` no endpoint, evitando quebras com espacos ou caracteres especiais.
- **`checkNumber()` usava requisicao fora do padrao da classe**
  - refatorado para `sendInstanceRequest('POST', '/user/check', ...)` com fallback em multiplos formatos de resposta da Evolution.
- **Upload de midia nao fechava o resource do arquivo**
  - `sendMediaUpload()` agora abre o handle apos `isConfigured()`, envolve a requisicao em `try` e garante `fclose()` no bloco `finally`, evitando vazamento de recursos em workers de fila.
- **Endpoints suspeitos da Evolution sem protecao por feature flag**
  - adicionadas flags `send_pix_native`, `send_link_native` e `send_sticker_native` em `config/whatsapp.php`.
  - fallbacks automaticos: PIX envia como texto, link envia como texto com preview, sticker envia como imagem.
- **Tela de criacao de contratos com problemas no Summernote e preview**
  - `resources/views/contracts/admin/create.blade.php`: DOMPurify carregado antes do script inline, botao "Limpar rascunho" adicionado, debounce do preview reduzido para 300ms e sanitizacao protegida contra DOMPurify nao carregado.

### Documentacao
- Criado `docs/AUDITORIA_GERAL_SISTEMA.txt` com resumo das correcoes, arquivos alterados e pendencias.

---

## [1.6.62] - 2026-06-01

### Corrigido
- **Recuperacao automatica de QR para instancia presa na Evolution**
  - `ensureMainRemoteInstanceReady()` agora detecta a situacao real encontrada em producao: instancia `connected=false`, sem QR, mas com `jid` antigo.
  - nesses casos, o sistema reseta a instancia na Evolution usando o UUID remoto, nao o nome, aguarda a linha ser recriada/atualizada e sincroniza o token remoto antes de pedir QR.
  - se a linha sumir apos o reset, a instancia e recriada com o mesmo nome configurado, token vigente, `qrcode=true` e configuracoes avancadas do sistema.

### Validado
- `php -l app/Modules/WhatsApp/Http/Controllers/WhatsAppAdminController.php`
- Diagnostico remoto confirmou que a Evolution exige UUID para reset e que o QR passa a vir em `/instance/all` apos o reset.

---

## [1.6.61] - 2026-06-01

### Corrigido
- **QR ocultado por falso "conectado" no frontend**
  - na tela `/admin/whatsapp/instance`, a função JS `isConnectedStatus()` foi corrigida para respeitar primeiro `connected=false` e estados explícitos (`connecting`, `close`, `disconnected`, `error`).
  - com isso, `status: connected` legado não mascara mais a tela de QR quando a instância ainda não está conectada de fato.

---

## [1.6.60] - 2026-06-01

### Corrigido
- **Connect ainda podia retornar "já conectada" sem confirmação direta**
  - `admin/whatsapp/connect` agora só retorna "instância já conectada" quando `/instance/status` direto confirma estado `open`.
  - quando o status direto não confirma `open`, qualquer estado conectado vindo de cache/fallback é rebaixado para `connecting` e o fluxo de QR continua.

### Validado
- `php -l app/Modules/WhatsApp/Http/Controllers/WhatsAppAdminController.php`

---

## [1.6.59] - 2026-06-01

### Corrigido
- **Connect preso por cache de "conectado" sem QR**
  - `admin/whatsapp/connect` agora também prioriza o estado direto de `/instance/status` antes de preservar sessão em cache.
  - quando o estado direto não está `open`, o fluxo força `connecting` e impede retorno precoce como "instância já conectada".
  - isso evita abortar geração de QR por falso positivo de conexão antiga.

### Validado
- `php -l app/Modules/WhatsApp/Http/Controllers/WhatsAppAdminController.php`

---

## [1.6.58] - 2026-06-01

### Corrigido
- **QR não aparecia após reiniciar/conectar em alguns ciclos**
  - no frontend da página `/admin/whatsapp/instance`, o polling de status agora força nova tentativa de `connect` quando a instância está em tentativa de QR, não está conectada e ainda não existe QR retornado.
  - adicionado lock (`waConnectRetryRunning`) para evitar chamadas concorrentes durante a insistência automática.

### Objetivo
- evitar estado parado sem QR na tela quando a Evolution demora ou falha no primeiro retorno de QR.

---

## [1.6.57] - 2026-06-01

### Corrigido
- **Status com falso "conectado" bloqueando QR Code**
  - `admin/whatsapp/status` agora prioriza o estado direto de `/instance/status` antes de aplicar fallback/cache.
  - quando o status direto não está `open`, o backend força estado `connecting` e desativa `connected`.
  - a preservação de sessão conectada via cache foi bloqueada nesses casos, evitando ocultar QR por estado antigo.

### Validado
- `php -l app/Modules/WhatsApp/Http/Controllers/WhatsAppAdminController.php`

---

## [1.6.56] - 2026-06-01

### Corrigido
- **Reiniciar instância sem abrir QR no painel**
  - no backend (`WhatsAppAdminController::restart`), o estado pós-reinício agora prioriza a checagem direta de `/instance/status`; quando não estiver `open`, força `connecting` e mantém a rotina de obtenção de QR.
  - no frontend (`instance.blade.php`), o botão **Reiniciar** agora ativa imediatamente o modo de tentativa de QR (`waQrAttemptActive`), renderiza estado "Aguardando QR Code" e inicia auto-refresh quando a instância não volta conectada.

### Validado
- `php -l app/Modules/WhatsApp/Http/Controllers/WhatsAppAdminController.php`

---

## [1.6.55] - 2026-06-01

### Corrigido
- **Reiniciar instância sem retorno de QR no painel**
  - `admin/whatsapp/restart` agora prepara a instância remota antes do restart e reaproveita QR pré-existente quando disponível.
  - após reiniciar, o fluxo executa `connect` com payload completo da instância e fallback de `connect` simples quando necessário.
  - polling de QR foi ampliado (`10 tentativas x 1200ms` + fallback adicional), reduzindo casos em que o botão reiniciar ficava sem QR.
  - a resposta AJAX de `restart` agora devolve `qrcode` explicitamente quando a instância está em `connecting`, evitando tela sem QR após reinício.

### Validado
- `php -l app/Modules/WhatsApp/Http/Controllers/WhatsAppAdminController.php`

---

## [1.6.54] - 2026-06-01

### Corrigido
- **Conectar/Reiniciar - fluxo preso em "Solicitação enviada" sem QR**
  - `admin/whatsapp/connect` agora faz polling de QR com maior tolerância (`8 tentativas`, `1s`) e preferência pelo status direto da instância.
  - `admin/whatsapp/restart` agora trata retorno `client disconnected` como reinício em progresso e dispara `connect` explícito antes do polling de QR.
  - objetivo: evitar estado intermediário sem QR após restart e restabelecer abertura de QR quando a instância não estiver `open`.

### Validado
- `php -l app/Modules/WhatsApp/Http/Controllers/WhatsAppAdminController.php`

---

## [1.6.53] - 2026-06-01

### Corrigido
- **Reiniciar instância - QR não voltava a aparecer após restart**
  - no fluxo `admin/whatsapp/restart`, o estado pós-reinício agora considera primeiro o retorno direto de `/instance/status` para decidir se deve abrir novo QR.
  - quando o status direto não estiver `open`, o sistema força estado `connecting` e executa polling de QR com preferência por status direto da instância.
  - isso evita falso "conectado" causado por fallback global defasado e restaura a geração de QR após reinício quando necessário.

### Validado
- `php -l app/Modules/WhatsApp/Http/Controllers/WhatsAppAdminController.php`

---

## [1.6.52] - 2026-06-01

### Corrigido
- **Diagnostico Evolution - prioridade absoluta para `/instance/status` OPEN**
  - quando `instance_status` vier `open`, o diagnostico final passa a forcar:
    - `global_conflict = false`
    - `status_confidence = high`
  - em `getNormalizedInstanceStatus()`, quando a origem for `instance_status` com estado `open`, o estado final e fixado como conectado e recebe:
    - `priority_source = instance_status`
    - `diagnostic_note` explicando que conflitos de `/instance/all` foram ignorados.
  - log tecnico sanitizado adicionado no canal `daily` para registrar conflito global ignorado.

### Ajustado
- **Saida do comando `whatsapp:evolution-diagnose`**
  - quando `instance_all` vier `close`, mas `instance_status` estiver `open`, a mensagem do teste `instance_all` passa a exibir:
    - `Estado CLOSE ignorado porque /instance/status confirmou OPEN diretamente.`

### Validado
- `php -l app/Modules/WhatsApp/Services/EvolutionClient.php`
- `php -l app/Console/Commands/WhatsAppEvolutionDiagnoseCommand.php`
- `php artisan optimize:clear`
- `php artisan whatsapp:evolution-diagnose`

---

## [1.6.51] - 2026-06-01

### Corrigido
- **Diagnostico Evolution - normalizacao final de casos esperados**
  - `runDiagnosticRequest()` agora normaliza `instance_qr` com HTTP `400` para `OK/open` quando a instancia ja estiver conectada.
  - `runDiagnosticRequest()` agora normaliza `instance_info` com HTTP `500` para `OK/open` quando o fallback via `/instance/all` confirmar a instancia conectada.
  - adicionada escrita de warning sanitizado no canal `evolution` para o fallback de `instance_info`.

### Ajustado
- **CLI de diagnostico**
  - cabecalho da tabela atualizado para `Mensagem/Observacao`, deixando claro quando:
    - QR 400 conectado e comportamento esperado;
    - `/instance/info` falhou, mas fallback validou a instancia.

### Validado
- `php -l app/Modules/WhatsApp/Services/EvolutionClient.php`
- `php -l app/Console/Commands/WhatsAppEvolutionDiagnoseCommand.php`
- `php artisan whatsapp:evolution-diagnose`

---

## [1.6.50] - 2026-06-01

### Adicionado
- **Diagnostico Evolution com sinalizacao de conflito e confianca**
  - `whatsapp:evolution-diagnose` agora exibe:
    - `Conflito global de estado` (`sim/nao`)
    - `Confianca do status final` (`high|medium|low`)
  - `EvolutionClient::diagnoseConnection()` passa a retornar `global_conflict` e `status_confidence` para diferenciar conflito entre endpoints da Evolution sem rebaixar o status final operacional.

### Validado
- `php -l app/Modules/WhatsApp/Services/EvolutionClient.php`
- `php -l app/Console/Commands/WhatsAppEvolutionDiagnoseCommand.php`
- `php artisan whatsapp:evolution-diagnose`

---

## [1.6.49] - 2026-06-01

### Corrigido
- **Diagnostico Evolution - reconciliacao de estado em conflito**
  - quando `/instance/info` ou `/instance/all` indicarem `open`, o teste `instance_status` passa a ser normalizado para conectado mesmo se `/instance/status` vier `close`.
  - `instance_qr` com HTTP `400` passa a ser tratado como informativo (nao critico) quando a visao global confirmar que a instancia ja esta conectada.
  - objetivo: eliminar falso negativo operacional no painel e no health-check em cenarios de token/sessao divergente por endpoint.

### Validado
- `php -l app/Modules/WhatsApp/Services/EvolutionClient.php`
- `php artisan whatsapp:evolution-diagnose`
- `php artisan whatsapp:health-check`

---

## [1.6.48] - 2026-06-01

### Corrigido
- **Evolution status/QR - fallback robusto quando `/instance/info` falha**
  - `EvolutionClient::diagnoseConnection()` agora aplica fallback via `/instance/all` quando `/instance/info/{instance}` retornar erro do servidor Evolution.
  - o teste `instance_info` passa a refletir estado real da instância quando os dados existem em `/instance/all`.

- **Tenant QR - bloqueio adicional quando instância já está conectada**
  - `InstanceManager::getQrCode()` agora consulta estado remoto via `/instance/all` antes de gerar QR.
  - quando a instância já está `open/connected`, o sistema limpa QR pendente e não gera novo código.

### Validado
- `php -l app/Modules/WhatsApp/Services/EvolutionClient.php`
- `php -l app/Modules/Tenancy/Services/InstanceManager.php`
- `php artisan whatsapp:evolution-diagnose`
- `php artisan whatsapp:health-check`

---

## [1.6.47] - 2026-06-01

### Corrigido
- **EvolutionClient - fallback de configuracao e diagnostico mais fiel**
  - `diagnoseConnection()` agora permite lookup local de instancias para nao mascarar configuracoes reais por tenant.
  - `resolveBaseUrl()` ignora URL placeholder (`example.com`) e tenta fallback para `api_base_url` de instancias locais.
  - `resolveInstanceApiKey()` adiciona fallback para token de instancia local configurada.
  - `resolveInstanceName()` deixa de travar em `default` quando existe instancia real cadastrada localmente.

### Validado
- `php -l app/Modules/WhatsApp/Services/EvolutionClient.php`
- `php artisan whatsapp:evolution-diagnose`
- `php artisan whatsapp:health-check`

---

## [1.6.46] - 2026-06-01

### Adicionado
- **Comandos operacionais de diagnostico Evolution no console**
  - novo `php artisan whatsapp:health-check` para checagem rapida da conectividade Evolution.
  - novo `php artisan whatsapp:full-diagnose` para diagnostico detalhado com endpoints, estado e HTTP.
  - novo `php artisan system:safe-clear` para limpeza segura (`optimize:clear` + `queue:restart`).

### Corrigido
- **Compatibilidade do bootstrap de comandos no Laravel 13**
  - removido registro invalido via `Artisan::starting(...)` em `routes/console.php` que quebrava o console com erro `Call to undefined method Illuminate\\Foundation\\Console\\Kernel::starting()`.
  - mantido registro nativo do comando `whatsapp:evolution-diagnose` via bootstrap do app.

### Validado
- `php -l routes/console.php`
- `php artisan list --raw`
- `php artisan whatsapp:health-check`

---

## [1.6.45] - 2026-06-01

### Corrigido
- **PWA/Login - loop de autenticacao para contas sem tenant valido**
  - `LoginController@showPwa` agora valida tenant ativo antes de redirecionar para `tenant.dashboard`.
  - quando a sessao web existir sem tenant ativo, o sistema faz logout seguro, invalida sessao e retorna para `/pwa/login` com erro claro.
  - `LoginController@login` agora bloqueia acesso ao painel quando o usuario nao possui tenant ativo/trial valido, evitando redirecionamento em ciclo.

### Validado
- `php -l app/Http/Controllers/Auth/LoginController.php`
- `npm run build` (frontend)

---

## [1.6.44] - 2026-06-01

### Adicionado
- **Dashboard de auditoria no admin com execução em tempo real**
  - nova tela `/admin/system/audit` com score técnico, indicadores críticos e resumo da última auditoria.
  - botão `Executar auditoria agora` via AJAX sem refresh, acionando `system:audit-full --write-docs`.
  - atualização imediata dos indicadores no frontend após cada execução.
  - inclusão do item `Auditoria` no menu lateral administrativo.

### Validado
- `php -l app/Http/Controllers/Admin/SystemAuditController.php`
- `php artisan route:list --name=admin.system.audit`
- `php artisan view:cache`

### Corrigido
- **Admin Auditoria - erro JavaScript `csrf is not defined`**
  - tela `/admin/system/audit` agora usa token CSRF diretamente do `meta[name="csrf-token"]` em vez de variável global.

### Adicionado
- **Alerta automático de auditoria no sininho do admin**
  - `system:audit-full` agora dispara notificação in-app (`group=system`) para todos os admins quando qualquer métrica crítica da auditoria sai de zero.
  - adicionada deduplicação diária para evitar flood de alertas iguais.
  - quando as métricas críticas voltam para zero após alerta aberto, o sistema envia notificação de normalização (`system_audit_ok`).

---

## [1.6.43] - 2026-06-01

### Adicionado
- **Auditoria completa automatizada do sistema**
  - novo comando `php artisan system:audit-full` para auditar rotas, controllers, services, tenancy, PWA, Evolution e addons.
  - geracao automatica de relatórios em `storage/reports`:
    - `SYSTEM_AUDIT_REPORT.md`
    - `SECURITY_REPORT.md`
    - `PERFORMANCE_REPORT.md`
    - `DATABASE_REPORT.md`
    - `PWA_REPORT.md`
    - `EVOLUTION_REPORT.md`
    - `MULTITENANT_REPORT.md`
    - `ADDONS_REPORT.md`
  - log consolidado em `storage/logs/system_audit.log`.
  - snapshot tecnico em `docs/graphify/index.md` quando executado com `--write-docs`.

### Corrigido
- **Redução de falsos positivos na auditoria**
  - detector de metodos vazios passou a ignorar metodos magicos intencionais (`__construct`, `__destruct`, `__invoke`).
  - rotas tecnicas publicas sem middleware (health, SEO, PWA, OpenAPI e webhooks externos) passaram a ser tratadas como excecoes controladas no resumo.

### Validado
- `php -l app/Console/Commands/SystemAuditFullCommand.php`
- `php artisan system:audit-full --write-docs`

---

## [1.6.42] - 2026-05-31

### Adicionado
- **Tenant Instances - indicador visual de sincronizacao no modal**
  - modal `Configuracao Evolution Go` agora exibe selo visual `Sincronizado com Evolution em DD/MM/AAAA HH:mm:ss` por instancia.
  - o indicador e atualizado automaticamente apos cada `Salvar e aplicar`, sem refresh de pagina.
  - quando o backend nao retorna horario no payload, o sistema usa o timestamp local do momento da aplicacao.

### Validado
- `php -l app/Modules/Tenancy/Resources/views/tenant/instances.blade.php`.

---

## [1.6.41] - 2026-05-31

### Corrigido
- **Tenant Instances - pelicula de modal travada no admin**
  - adicionada limpeza defensiva de `modal-backdrop` orfa e `body.modal-open` quando nenhum modal estiver visivel.
  - troca para `bootstrap.Modal.getOrCreateInstance` no modal de configuracao para evitar duplicacao de instancia Bootstrap.
  - aplicado cleanup apos salvar configuracao e nos eventos globais `hidden.bs.modal`/`shown.bs.modal` para evitar bloqueio de cliques.

### Validado
- `php artisan test tests/Feature/TenantInstanceEvolutionTest.php` (32 testes, 235 assercoes).
- `php -l app/Modules/Tenancy/Resources/views/tenant/instances.blade.php`.

---

## [1.6.40] - 2026-05-31

### Corrigido
- **Evolution Go - sincronizacao real de eventos e configuracoes avancadas**
  - `Eventos para Webhook` continuam salvos por instancia e enviados em tempo real para a Evolution sem refresh.
  - o comportamento `Ignore Status` passou a ser tratado explicitamente no sistema (`ignore_status/ignoreStatus`) e convertido corretamente para `readStatus=false` no payload remoto.
  - compatibilidade mantida com payload legado (`read_status/readStatus`) para nao quebrar instancias antigas.
  - admin e tenant agora aplicam a mesma regra de `Ignore Status` ao salvar configuracao via AJAX.

### Validado
- `php artisan test tests/Feature/WhatsAppAdminInstancePageTest.php tests/Feature/TenantInstanceEvolutionTest.php` (59 testes, 411 assercoes).
- `php -l app/Modules/WhatsApp/Http/Controllers/WhatsAppAdminController.php`, `php -l app/Modules/Tenancy/Services/InstanceManager.php`, `php -l app/Modules/Tenancy/Resources/views/tenant/instances.blade.php`.

---

## [1.6.39] - 2026-05-31

### Adicionado
- **Evolution Go - gestao completa de produtores de eventos por instancia**
  - o sistema passou a salvar por instancia as opcoes RabbitMQ, WebSocket e NATS em `default/enabled/disabled`.
  - o payload enviado para a Evolution agora inclui `rabbitmqEnable`, `websocketEnable` e `natsEnable` junto do webhook em tempo real.
  - o admin principal e as instancias tenant mantem o registro local e reenviam essas configuracoes ao salvar, provisionar e conectar.

### Validado
- `php artisan test tests/Feature/WhatsAppAdminInstancePageTest.php tests/Feature/TenantInstanceEvolutionTest.php` (59 testes, 408 assercoes).
- `php -l app/Modules/Tenancy/Services/InstanceManager.php`, `php -l app/Modules/Tenancy/Http/Controllers/TenantPanelController.php` e `php -l app/Modules/WhatsApp/Http/Controllers/WhatsAppAdminController.php`.

---

## [1.6.38] - 2026-05-31

### Corrigido
- **Evolution Go - QR Code ilegivel no WhatsApp**
  - o sistema passou a priorizar o campo textual `Code/code/pairingCode` retornado pela Evolution antes da imagem `Qrcode/base64`.
  - o QR Code agora e gerado localmente no painel a partir do texto oficial de pareamento, evitando exibir a imagem densa/ilegivel retornada pela API.
  - o status da instancia tambem busca `/instance/qr` quando `/instance/all` retorna apenas imagem, para nao recolocar no painel o QR ilegivel da listagem.
  - a mesma prioridade foi aplicada no endpoint admin, no cliente Evolution e no JavaScript da tela `/admin/whatsapp/instance`.

### Validado
- `php artisan test tests/Feature/WhatsAppAdminInstancePageTest.php tests/Feature/TenantInstanceEvolutionTest.php` (59 testes, 404 assercoes).
- `php -l app/Modules/WhatsApp/Services/EvolutionClient.php` e `php -l app/Modules/WhatsApp/Http/Controllers/WhatsAppAdminController.php`.

---

## [1.6.37] - 2026-05-31

### Corrigido
- **Evolution Go - bloqueio de falso conectado sem leitura do QR**
  - o admin deixou de marcar a instancia como conectada quando a Evolution retorna `open/connected` sem numero ou JID real do WhatsApp.
  - quando existir QR Code e ainda nao houver identidade WhatsApp, o painel permanece em `Aguardando QR Code` e mantem o QR visivel para leitura.
  - o frontend tambem passou a exigir numero ou JID antes de ocultar o QR, parar o polling ou mostrar sucesso de conexao.
  - se a Evolution devolver uma imagem base64 invalida, o painel nao tenta transformar os bytes da imagem em outro QR Code, evitando QR visualmente valido mas ilegivel.

### Validado
- `php artisan test tests/Feature/WhatsAppAdminInstancePageTest.php tests/Feature/TenantInstanceEvolutionTest.php` (57 testes, 399 assercoes).
- `php -l app/Modules/WhatsApp/Http/Controllers/WhatsAppAdminController.php` e `php -l app/Modules/WhatsApp/Services/EvolutionClient.php`.

---

## [1.6.36] - 2026-05-31

### Corrigido
- **Evolution Go - recuperacao automatica da instancia principal**
  - o botao de conectar da instancia principal agora usa a Global API Key para verificar se a instancia configurada existe na Evolution antes de pedir QR Code.
  - quando a instancia existe mas o token local esta desatualizado, o sistema atualiza o token/API Key da instancia no painel automaticamente.
  - quando a instancia nao existe mais na Evolution, o sistema recria a instancia com o nome configurado, salva o novo token e usa o QR retornado pela Evolution.
  - isso corrige o caso real em que `testandoq` retornava `401 not authorized` em `/instance/status`, `/instance/connect` e `/instance/qr`, mesmo com a Global API Key funcionando em `/instance/all`.

### Validado
- `php -l app/Modules/WhatsApp/Http/Controllers/WhatsAppAdminController.php`: OK
- `php artisan test tests/Feature/WhatsAppAdminInstancePageTest.php tests/Feature/TenantInstanceEvolutionTest.php`: 56 passed
- `php artisan view:cache`: OK
- `git diff --check`: OK
- `graphify update .`: OK

---

## [1.6.35] - 2026-05-31

### Corrigido
- **Admin - formulario Evolution API em grade horizontal**
  - o bloco inicial de configuracao da Evolution API em `/admin/whatsapp/instance` passou a usar grid responsivo em duas colunas no desktop.
  - o card de configuracao ganhou mais largura em telas grandes, reduzindo o efeito de formulario comprido em coluna unica.
  - os botoes de salvar e testar tambem foram alinhados em grade horizontal no desktop, mantendo uma coluna no mobile.

### Validado
- `php artisan test tests/Feature/WhatsAppAdminInstancePageTest.php`: 23 passed
- `php artisan view:cache`: OK
- `git diff --check`: OK
- `graphify update .`: OK

---

## [1.6.34] - 2026-05-31

### Corrigido
- **Evolution Go - conexao e QR Code mais tolerantes a falhas de webhook**
  - o admin agora tenta um `instance/connect` minimo quando a Evolution rejeita o payload de webhook, evitando travar a geracao do QR Code por erro secundario de configuracao.
  - as instancias tenant passaram a usar o mesmo fallback minimo antes de buscar o QR, mantendo a conexao utilizavel mesmo se a configuracao remota do webhook falhar momentaneamente.
  - a criacao e recriacao de instancias tenant deixaram de enviar `webhookUrl` e `subscribe` diretamente no `/instance/create`; o webhook continua sendo aplicado pelo provisionamento gerenciado do sistema.
  - a pagina `/admin/whatsapp/instance` foi enxugada para remover blocos duplicados e deixar configuracao, acoes, QR Code e status em uma organizacao mais direta.

### Validado
- `php -l app/Modules/WhatsApp/Http/Controllers/WhatsAppAdminController.php`: OK
- `php -l app/Modules/Tenancy/Services/InstanceManager.php`: OK
- `php artisan test tests/Feature/WhatsAppAdminInstancePageTest.php tests/Feature/TenantInstanceEvolutionTest.php`: 55 passed
- `php artisan view:cache`: OK
- `php artisan route:list --path=admin/whatsapp`: OK
- `graphify update .`: OK
- `git diff --check`: OK

---

## [1.6.33] - 2026-05-31

### Corrigido
- **Admin - tela `/admin/whatsapp/instance` reorganizada e configuracao Evolution completada**
  - a tela principal da instancia ganhou organizacao visual mais clara, com resumo da instancia, bloco explicito de webhook, eventos, comportamento e status remoto.
  - a URL do webhook da instancia principal passou a aparecer na propria tela com copia direta, mantendo a integracao sempre vinculada ao sistema.
  - o salvamento da configuracao admin agora retorna `webhook_url`, reaplica a configuracao remota e mantem o resumo visual sincronizado sem refresh completo.
  - a assinatura do webhook passou a respeitar `webhook_by_events`; quando desligada, o sistema envia `ALL` no `instance/connect` da Evolution Go.
  - os eventos recomendados da instancia principal passaram a incluir `MESSAGES_DELETE`, alinhando melhor a governanca do historico com o fluxo tenant.

### Validado
- `php -l app/Modules/WhatsApp/Http/Controllers/WhatsAppAdminController.php`: OK
- `php -l app/Modules/WhatsApp/Services/EvolutionClient.php`: OK
- `php artisan test tests/Feature/WhatsAppAdminInstancePageTest.php`: OK
- `php artisan view:cache`: OK
- `php artisan route:list --path=admin/whatsapp`: OK
- `graphify update .`: OK

---

## [1.6.32] - 2026-05-31

### Corrigido
- **Notificacoes do painel - leitura individual com som, alerta visual e detalhe**
  - o sino do topo agora toca som ao receber novas notificacoes e exibe alerta visual clicavel com titulo e mensagem do item recebido.
  - o clique em cada item da campainha passou a marcar a notificacao individual como lida antes da navegacao, reduzindo a contagem de nao lidas no mesmo fluxo.
  - a abertura pela campainha agora vai sempre para a pagina de detalhe da notificacao, preservando o contexto do evento e exibindo a pagina relacionada apenas como acao secundaria dentro do detalhe.
  - o tenant deixou de receber links `/admin` no payload e na abertura da campainha, evitando fuga de contexto no modo assistido.

### Validado
- `php -l app/Http/Controllers/Admin/NotificationController.php`: OK
- `php artisan test tests/Feature/NotificationIsolationTest.php`: OK
- `graphify update .`: OK

---

## [1.6.31] - 2026-05-31

### Corrigido
- **Tenant - compatibilidade do webhook legado da Evolution**
  - a rota `/webhooks/evolution/messages` usada pelas instancias tenant passou a aceitar webhook legado com token valido mesmo quando a Evolution Go nao envia timestamp ou HMAC.
  - a verificacao forte de assinatura e replay continua preservada no endpoint dedicado `/api/webhooks/evolution-go`.
  - isso destrava a sincronizacao em tempo real dos eventos que estavam sendo rejeitados em producao por `webhook_timestamp_missing`.

### Validado
- `php -l app/Modules/Chatbot/Http/Controllers/ChatbotWebhookController.php`: OK
- `php artisan test tests/Feature/TenantInstanceEvolutionTest.php tests/Feature/WhatsAppWebhookSecurityTest.php`: 35 passed

---

## [1.6.30] - 2026-05-31

### Corrigido
- **Tenant - sincronizacao real da configuracao Evolution Go**
  - o salvamento da modal do tenant passou a aplicar primeiro o endpoint oficial `advanced-settings` da Evolution Go v3, em vez de priorizar o contrato legado.
  - o payload remoto foi corrigido para os nomes aceitos pela v3, incluindo `rejectCalls` e `rejectCallsMessage`.
  - ao desativar webhook na modal, o sistema agora envia `webhookUrl` vazio e `subscribe` vazio no `/instance/connect`, desativando o recebimento remoto em tempo real.
  - o provisionamento automatico e a recriacao de instancia passaram a reutilizar o mesmo payload oficial da v3 para evitar divergencia entre criacao e edicao.

### Validado
- `php -l app/Modules/Tenancy/Services/InstanceManager.php`: OK
- `php artisan test tests/Feature/TenantInstanceEvolutionTest.php`: 31 passed

---

## [1.6.29] - 2026-05-31

### Corrigido
- **Tenant - alinhamento dos switches na modal Evolution Go**
  - os toggles de comportamento dentro da modal `Configurar API` passaram a respeitar o grid do card sem deslocar o controle para fora da coluna.
  - o bloco agora mantém toggle, título e ícone de ajuda alinhados no dark mode e em telas menores.

### Validado
- `php -l app/Modules/Tenancy/Resources/views/tenant/instances.blade.php`: OK
- `php artisan test tests/Feature/TenantInstanceEvolutionTest.php`: 30 passed
- `graphify update .`: OK

---

## [1.6.28] - 2026-05-31

### Alterado
- **Tenant - ajuda contextual na modal Evolution Go**
  - a modal `Configurar API` do tenant passou a usar icones de ajuda com tooltip nos eventos, switches e controles de webhook, reduzindo poluicao visual sem perder explicacao funcional.
  - os cards e seletores da modal foram reorganizados para manter o nome tecnico da API visivel e mover a explicacao detalhada para o hover/focus do icone.
  - o clique no icone de ajuda nao marca nem desmarca checkboxes por acidente dentro dos labels do formulario.

### Validado
- `php -l app/Modules/Tenancy/Resources/views/tenant/instances.blade.php`: OK
- `php artisan test tests/Feature/TenantInstanceEvolutionTest.php`: 30 passed
- `graphify update .`: OK

---

## [1.6.27] - 2026-05-31

### Corrigido
- **Tenant - Sincronizacao somente com WhatsApp real conectado**
  - a sincronizacao deixou de marcar instancia como conectada apenas por `open`, `Connected` ou `LoggedIn` quando a Evolution nao retorna numero/JID valido do WhatsApp.
  - o endpoint de sincronizacao agora retorna erro controlado quando nao existe WhatsApp conectado, mantendo o card em estado desconectado e exibindo orientacao para gerar QR Code.
  - erros de sincronizacao sem identidade real nao sao apagados por provisionamento automatico de webhook/configuracoes.
  - configuracoes da API continuam sendo salvas localmente e agora tem cobertura para fallback v3 em `/instance/{instancia}/advanced-settings` quando `/settings/set` nao estiver disponivel.

### Validado
- `php -l app/Modules/Tenancy/Services/InstanceManager.php`: OK
- `php -l app/Modules/Tenancy/Http/Controllers/TenantPanelController.php`: OK
- `php -l app/Modules/Tenancy/Resources/views/tenant/instances.blade.php`: OK
- `php artisan test tests/Feature/TenantInstanceEvolutionTest.php`: 27 passed

---

## [1.6.26] - 2026-05-31

### Corrigido
- **Tenant - QR e nome de exibicao das instancias**
  - apos criar uma instancia, o painel agora abre o modal e busca o QR Code automaticamente mesmo quando a Evolution nao retorna o QR diretamente no `/instance/create`.
  - o identificador tecnico criado na Evolution passa a priorizar o nome do cliente da conta tenant e o slot sequencial a partir da instancia principal.
  - o nome informado pelo tenant ao criar/renomear a instancia fica como nome de exibicao no sistema, sem renomear o identificador tecnico da Evolution.
  - cards de instancia passam a priorizar o nome de exibicao solicitado pelo tenant no titulo.

### Validado
- `php -l app/Modules/Tenancy/Services/InstanceManager.php`: OK
- `php -l app/Modules/Tenancy/Models/TenantInstance.php`: OK
- `php -l app/Modules/Tenancy/Resources/views/tenant/instances.blade.php`: OK
- `php -l app/Modules/Tenancy/Resources/views/tenant/partials/instance-card.blade.php`: OK
- `php artisan test tests/Feature/TenantInstanceEvolutionTest.php`: 25 passed

---

## [1.6.25] - 2026-05-31

### Corrigido
- **Tenant - Sincronizacao fiel de instancias Evolution**
  - a exclusao agora usa `/instance/all` como fonte da verdade e remove localmente a instancia quando a Evolution confirma que ela nao existe mais, mesmo que o banco local ainda tenha perfil/status antigo.
  - novas instancias passam a registrar o nome tecnico na Evolution no padrao `username_do_cliente_id_slot`, preservando o nome escolhido pelo tenant apenas como nome de exibicao.
  - o webhook v3 agora e aplicado automaticamente via `/instance/connect` com `webhookUrl` e `subscribe`, mantendo fallback para `/webhook/set` quando necessario.
  - eventos locais como `MESSAGES_UPSERT` e `CONNECTION_UPDATE` sao traduzidos para os eventos Evolution Go v3 (`MESSAGE`, `CONNECTION`, `QRCODE`, etc.) ao configurar o servidor.

### Validado
- `php -l app/Modules/Tenancy/Services/InstanceManager.php`: OK
- `php -l app/Modules/Tenancy/Models/TenantInstance.php`: OK
- `php -l app/Modules/Chatbot/Http/Controllers/ChatbotWebhookController.php`: OK
- `php artisan test tests/Feature/TenantInstanceEvolutionTest.php`: 24 passed

---

## [1.6.24] - 2026-05-31

### Corrigido
- **Tenant - Recuperacao de instancias extras Evolution**
  - QR Code e sincronizacao de instancias extras agora verificam a existencia remota da instancia via `/instance/all` antes de operar com a chave da instancia.
  - quando a instancia extra existe apenas localmente ou retorna `not authorized`, o sistema recria a instancia remota com o mesmo nome, persiste o novo token e reaplica webhook/configuracoes antes de gerar QR ou sincronizar.
  - falhas recuperaveis como `not authorized`, `record not found` e `invalid UUID` passam por recuperacao automatica antes de exibir erro ao tenant.

### Validado
- `php -l app/Modules/Tenancy/Services/InstanceManager.php`: OK
- `php artisan test tests/Feature/TenantInstanceEvolutionTest.php`: 22 passed

---

## [1.6.23] - 2026-05-31

### Corrigido
- **Tenant - Exclusao de instancia com perfil obsoleto**
  - a remocao local de instancia tenant orfa deixou de depender dos campos de perfil estarem vazios quando a Evolution confirma `record not found` e `invalid UUID`.
  - perfis locais antigos com numero/JID salvo, mas sem UUID remoto valido e sem estado local conectado, agora podem ser removidos sem manter o erro `invalid UUID length: 11`.

### Validado
- `php -l app/Modules/Tenancy/Services/InstanceManager.php`: OK
- `php artisan test tests/Feature/TenantInstanceEvolutionTest.php`: 20 passed

---

## [1.6.22] - 2026-05-30

### Corrigido
- **Tenant - Exclusao de instancia Evolution orfa**
  - a exclusao de instancia do tenant agora remove o registro local quando a Evolution Go rejeita o nome da instancia como UUID (`invalid UUID length`) e nao existe UUID remoto valido.
  - instancias sem identidade sincronizada (`numero`, `jid` e telefone vazios ou `-`) em estados desconectados/erro passam a ser tratadas como orfas locais removiveis quando o servidor retorna `record not found`, `invalid UUID` ou `not authorized`.
  - ids remotos salvos em `meta` ou `profile_raw` agora so bloqueiam a remocao local quando forem UUIDs validos para delete na Evolution Go.

### Validado
- `php -l app/Modules/Tenancy/Services/InstanceManager.php`: OK
- `php artisan test tests/Feature/TenantInstanceEvolutionTest.php`: 19 passed

---

## [1.6.21] - 2026-05-30

### Corrigido
- **WhatsApp Evolution Go - Governanca e diagnostico**
  - `EvolutionClient` passou a separar explicitamente chave global (`global_api_key/global_apikey`) de chave da instancia (`instance_api_key/apikey`) para requests administrativos e de sessao.
  - requests internos da Evolution agora usam somente header `apikey`, sem `Authorization`, sem Bearer e sem chave na query string.
  - foi criado o comando `php artisan whatsapp:evolution-diagnose` para diagnosticar URL, instancia, chaves configuradas e endpoints principais sem expor segredos.
  - o painel admin ganhou endpoint protegido `admin.whatsapp.diagnose` para diagnostico AJAX seguro.
  - logs de transporte da Evolution registram endpoint, tipo da chave, status HTTP e tempo de resposta sem gravar valores sensiveis.

### Validado
- `php -l app/Modules/WhatsApp/Services/EvolutionClient.php`: OK
- `php -l app/Modules/WhatsApp/Http/Controllers/WhatsAppAdminController.php`: OK
- `php -l app/Modules/WhatsApp/Providers/WhatsAppServiceProvider.php`: OK
- `php -l app/Console/Commands/WhatsAppEvolutionDiagnoseCommand.php`: OK
- `php artisan list whatsapp`: OK
- `php artisan route:list --path=admin/whatsapp`: OK
- `php artisan test tests/Feature/WhatsAppEvolutionGovernanceTest.php`: 5 passed
- `php artisan test tests/Feature/WhatsAppAdminInstancePageTest.php`: 19 passed
- `php artisan optimize:clear`: OK
- `php artisan whatsapp:evolution-diagnose`: executado; retornou falha operacional esperada por chaves locais nao configuradas, sem expor segredos.

---

## [1.6.20] - 2026-05-30

### Corrigido
- **WhatsApp Admin - QR Code sem imagem quebrada**
  - o painel `/admin/whatsapp/instance` deixou de inserir a imagem do QR diretamente no HTML antes de validar o carregamento.
  - quando a Evolution retorna URL, base64 antigo ou payload invalido, a tela testa a imagem em memoria e cai automaticamente para QR Code gerado em canvas sem exibir icone quebrado.
  - a extracao AJAX do QR passou a aceitar campos adicionais (`code`, `url`, `image`) em `qrcode`, `data.qrcode` e `data.data.qrcode`.

### Validado
- `php -l app/Modules/WhatsApp/Resources/views/instance.blade.php`: OK
- `php artisan test tests/Feature/WhatsAppAdminInstancePageTest.php`: 19 passed
- `php artisan test tests/Feature/WhatsAppEvolutionGovernanceTest.php`: 3 passed
- `php artisan view:cache`: OK
- `php artisan route:list --path=admin/whatsapp`: OK

---

## [1.6.19] - 2026-05-30

### Corrigido
- **WhatsApp Admin - QR Code fixado**
  - o painel `/admin/whatsapp/instance` agora preserva o QR Code recente por janela de estabilidade quando a Evolution oscila para `close` sem devolver QR novo logo apos a geracao.
  - o frontend mantem o ultimo QR valido em memoria enquanto a instancia nao conecta, evitando o efeito de aparecer e sumir durante o polling AJAX.
  - QR salvo com prefixo antigo `data:image/*;base64,` mas sem imagem valida deixa de ser exibido como imagem quebrada e passa a ser tratado como texto para geracao local em canvas.

### Validado
- `php -l app/Modules/WhatsApp/Services/EvolutionClient.php`: OK
- `php -l app/Modules/WhatsApp/Http/Controllers/WhatsAppAdminController.php`: OK
- `php -l app/Modules/WhatsApp/Resources/views/instance.blade.php`: OK
- `php artisan test tests/Feature/WhatsAppAdminInstancePageTest.php`: 18 passed
- `php artisan test tests/Feature/WhatsAppEvolutionGovernanceTest.php`: 3 passed
- `php artisan view:cache`: OK

---

## [1.6.18] - 2026-05-30

### Corrigido
- **WhatsApp Admin - Estabilidade da instancia Evolution**
  - o botao `Atualizar status` da tela `/admin/whatsapp/instance` deixou de rebaixar uma sessao localmente conectada para `Aguardando QR Code` quando a Evolution retorna payload passivo inconsistente em `/instance/all`.
  - o fluxo `Conectar / Gerar QR Code` nao deleta nem recria mais a instancia remota automaticamente quando a Evolution retorna sucesso sem QR imediato, evitando derrubar sessoes ativas.
  - a renderizacao do QR Code agora diferencia imagem base64 real de codigo textual (`qrcode.Code`) e gera o QR localmente no navegador quando necessario.
  - `EvolutionClient` e `WhatsAppAdminController` passaram a validar assinatura de imagem antes de prefixar `data:image/png;base64`, evitando imagem quebrada no painel.
- **Documentacao operacional**
  - CHANGELOG voltou a registrar a entrega do ajuste de compatibilidade Evolution Go v3.0 da tela de instancia, incluindo o hotfix de QR Code e a protecao contra falso desconectado.
  - a colecao `EvolutionAPIGo-v3.0.postman_collection.json` foi incluida no versionamento com os endpoints/documentacao atualizados da Evolution API Go v3.0.

### Validado
- `node -e "JSON.parse(...EvolutionAPIGo-v3.0.postman_collection.json...)"`: OK
- `php -l app/Modules/WhatsApp/Services/EvolutionClient.php`: OK
- `php -l app/Modules/WhatsApp/Http/Controllers/WhatsAppAdminController.php`: OK
- `php -l app/Modules/WhatsApp/Resources/views/instance.blade.php`: OK
- `php artisan test tests/Feature/WhatsAppEvolutionGovernanceTest.php`: 3 passed
- `php artisan test tests/Feature/WhatsAppAdminInstancePageTest.php`: 17 passed
- `php artisan view:cache`: OK
- `php artisan route:list --path=admin/whatsapp`: OK

---

## [1.6.17] - 2026-05-29

### Corrigido
- **WhatsApp Admin - Fechamento do QR apos conexao**
  - o painel `/admin/whatsapp/instance` agora remove o QR Code imediatamente quando o status da Evolution confirma a instancia como conectada.
  - QR Code antigo tambem passa a ser removido quando a Evolution confirma estado desconectado sem devolver QR novo.
  - a view passou a fazer polling curto enquanto a instancia esta aguardando leitura do QR, fechando o box automaticamente apos a conexao.
  - os botoes da tela respeitam o ultimo status aplicado depois do AJAX, impedindo que "Conectar" seja reabilitado quando a instancia ja esta conectada.
- **WhatsApp Admin - Protecao contra numero duplicado**
  - `WhatsAppAdminController::connect()` agora bloqueia a conexao quando o numero persistido da instancia principal ja aparece conectado em outra instancia remota.
  - status normalizado, persistencia local e sync remoto passaram a limpar QR Code obsoleto quando o estado efetivo e `open/conectado`.

### Validado
- `php -l app/Modules/WhatsApp/Services/EvolutionClient.php`: OK
- `php -l app/Modules/WhatsApp/Http/Controllers/WhatsAppAdminController.php`: OK
- `php -l app/Services/EvolutionGoSyncService.php`: OK
- `php -l app/Modules/WhatsApp/Resources/views/instance.blade.php`: OK
- `php artisan test tests/Feature/WhatsAppAdminInstancePageTest.php`: 16 passed
- `php artisan test --filter=WhatsApp`: 60 passed
- `php artisan optimize:clear`: OK
- `php artisan route:list --path=admin/whatsapp`: OK

---

## [1.6.16] - 2026-05-29

### Corrigido
- **WhatsApp Admin - QR Code de conexão**
  - `WhatsAppAdminController::connect()` passou a enviar o número persistido da instância ao iniciar conexão com a Evolution.
  - quando a Evolution retorna `client disconnected` e não gera QR, o painel agora recupera a sessão quebrada recriando a instância remota com o mesmo nome/token e capturando o QR retornado.
  - o extrator de QR agora reconhece os formatos `Qrcode` e `Code` usados pela Evolution Go v3.0.
- **Evolution Client**
  - `EvolutionClient::getNormalizedInstanceStatus()` passou a desembrulhar corretamente respostas `/instance/all` no formato `data.data`, preservando UUID, status e QR reais da instância.

### Validado
- `php -l app/Modules/WhatsApp/Services/EvolutionClient.php`: OK
- `php -l app/Modules/WhatsApp/Http/Controllers/WhatsAppAdminController.php`: OK
- `php -l app/Services/EvolutionGoSyncService.php`: OK
- `php artisan test tests/Feature/WhatsAppAdminInstancePageTest.php`: 13 passed

---

## [1.6.15] - 2026-05-29

### Corrigido
- **WhatsApp Admin - Snapshot da instância principal**
  - `EvolutionGoSyncService::markRemoteOnly()` deixou de sobrescrever o snapshot persistido da instância principal com payload bruto de `/instance/all`.
  - o sync remoto agora preserva `display.qrcode`, `status_label`, `phone`, `profile_name`, `client_name` e demais campos usados pela tela `/admin/whatsapp/instance`.
  - instâncias remotas sem `state/status`, mas com `connected=true`, passam a ser normalizadas como `open/connected`, evitando falso desconectado no painel.
  - quando `/instance/all` retornar `qrcode`, o sync passa a promover esse QR para o `display` persistido e marca o estado como `connecting`, mantendo o box de conexão preenchido no painel.
- **WhatsApp Admin - Fluxo de conexão**
  - `WhatsAppAdminController::connect()` agora faz polling curto após `POST /instance/connect` antes de desistir do QR Code, cobrindo a latência real da Evolution na geração da sessão.
- **WhatsApp Admin - Textos PT-BR**
  - `WhatsAppAdminController` teve as mensagens públicas corrigidas para acentuação consistente em conexão, desconexão, reinício e salvamento de configuração.

### Validado
- `php -l app/Services/EvolutionGoSyncService.php`: OK
- `php -l app/Modules/WhatsApp/Http/Controllers/WhatsAppAdminController.php`: OK
- `php artisan test tests/Feature/WhatsAppAdminInstancePageTest.php`: 12 passed

---

## [1.6.14] - 2026-05-29

### Corrigido
- **WhatsApp Admin - Tela da instância**
  - a view `app/Modules/WhatsApp/Resources/views/instance.blade.php` voltou a usar SweetAlert2 nas confirmações de desconectar e reiniciar, eliminando o `confirm()` nativo do navegador.
  - as notificações da tela passaram a usar o wrapper global `window.toast` e `window.alert2`, alinhando o comportamento com o restante do painel AdminLTE.
  - os campos `Global API Key` e `Token/API Key da instância` voltaram a carregar os valores persistidos no formulário principal do admin.
- **WhatsApp Admin - Persistência de configuração**
  - `WhatsAppAdminController::saveConfig()` passou a preservar as chaves já salvas quando o formulário for reenviado sem alterar os campos sensíveis.
  - o salvamento principal agora sincroniza `whatsapp.global_api_key`, `whatsapp.global_apikey`, `whatsapp.instance_api_key`, `whatsapp.apikey` e `whatsapp.api_key`, reduzindo inconsistência entre aliases legados e a governança nova.
- **Evolution Client**
  - `EvolutionClient` voltou a priorizar a configuração injetada no construtor para `baseUrl` e `apiKey` antes do fallback por instância local, evitando que um token/URL de tenant sobreponha a configuração principal do painel `/admin/whatsapp/instance`.

### Validado
- `php -l app/Modules/WhatsApp/Services/EvolutionClient.php`: OK
- `php -l app/Modules/WhatsApp/Http/Controllers/WhatsAppAdminController.php`: OK
- `php -l app/Modules/WhatsApp/Resources/views/instance.blade.php`: OK
- `php artisan test tests/Feature/WhatsAppAdminInstancePageTest.php`: OK
- `php artisan test --filter=WhatsApp`: OK

---

## [1.6.13] - 2026-05-29

### Corrigido
- **WhatsApp Admin - Integracao da instancia principal**
  - `EvolutionClient::getNormalizedInstanceStatus()` passou a priorizar `/instance/all` como fonte principal de estado no ambiente Evolution v3, com fallback para `/instance/status` e enriquecimento por `info` e avatar quando disponiveis.
  - `WhatsAppAdminController` foi ajustado para responder de forma coerente em `status`, `connect`, `disconnect` e `restart`, preservando persistencia local e evitando falso erro quando a Evolution retorna HTTP inconsistente, mas o estado efetivo da instancia ja e conhecido.
  - A persistencia da instancia principal passou a refletir melhor origem, QR, estado remoto e dados de perfil, reduzindo lentidao de consulta e inconsistencias ao recarregar a tela.
- **WhatsApp Admin - View /admin/whatsapp/instance**
  - `instance.blade.php` foi substituida por uma view mais enxuta com card de configuracao, card de QR Code, bloco de status e acoes via AJAX.
  - Os IDs obrigatorios da tela foram preservados (`wa-status-*`, `wa-btn-*`, `wa-qr-box`, `wa-config-form`) e a view manteve compatibilidade com marcadores legados exigidos pela suite de testes.
  - O carregamento inicial passou a renderizar status persistido, numero formatado, QR inicial e origem persistida antes do refresh AJAX.
  - O CSS da tela foi alinhado com as variaveis globais do tema (`var(--app-surface-bg)`, `var(--bs-body-color)`, `var(--bs-secondary-color)` e derivados) para respeitar corretamente os modos dark/light do painel sem superfices claras fixas.
- **Favicon**
  - Confirmado o uso do favicon versionado em `resources/views/admin/layouts/app.blade.php` e a presenca de `public/favicon.ico`, mantendo cache-busting por `filemtime`.

### Validado
- `php -l app/Modules/WhatsApp/Services/EvolutionClient.php`: OK
- `php -l app/Modules/WhatsApp/Http/Controllers/WhatsAppAdminController.php`: OK
- `php -l app/Modules/WhatsApp/Resources/views/instance.blade.php`: OK
- `php artisan optimize:clear`: OK
- `php artisan route:list`: OK
- `php artisan test tests/Feature/WhatsAppAdminInstancePageTest.php`: 8 passed
- `php artisan test --filter=WhatsApp`: 50 passed
- `graphify update .`: OK
- `https://whatsapp.km.site.nom.br/favicon.ico`: HTTP 200

---

## [1.6.10] - 2026-05-24

### Adicionado
- **Fundacao premium global**
  - Criado design system reutilizavel em `resources/views/components/ui` com `page-header`, `premium-card`, `premium-form`, `premium-table`, `premium-modal`, `premium-toolbar`, `status-badge`, `empty-state`, `loading-state`, `stats-card`, `action-dropdown` e `section-title`.
  - Criada engine central de tema em `App\Support\Theme` para unificar resolucao dark/light, CSS variables e cor do navegador entre admin e tenant.
  - Criado SDK inicial de addons em `App\Support\Addons` com normalizacao de layout, tema, menu, assets e permissoes.
- **Gateways tenant avancados**
  - Adicionada migration incremental para evoluir `tenant_gateways` com `settings`, `payment_methods`, `fees`, `split_rules`, `installment_rules`, `webhook_token`, `webhook_secret`, `environment`, `priority`, `last_test_status`, `last_test_at` e `last_error`.
  - Adicionadas tabelas `gateway_webhooks`, `gateway_webhook_logs`, `gateway_webhook_events` e `gateway_webhook_failures` para profissionalizar rastreabilidade de webhooks sem quebrar a tabela antiga.
  - Criado core universal de gateways em `app/Modules/Gateways/Core` com schema, feature resolver, webhook manager, fee calculator, split manager e validation service.
  - Contrato `PaymentGateway` expandido com validacao de credenciais, calculo de taxas, metodos disponiveis, webhook dinamico e suporte por recurso.
- **Documentacao**
  - Adicionados documentos-base de arquitetura, tenancy, gateways, webhooks, addons, seguranca, tema, padrao de UI, deploy/cPanel e auditoria dos `untracked`.

### Alterado
- **Painel tenant de gateways**
  - Tela `painel/gateways` reorganizada em padrao premium com configuracao de credenciais, ambiente, prioridade, metodos, taxas, parcelamento, split, limite por cobranca, allowlist e flags operacionais.
  - Salvamento do gateway do tenant agora persiste o schema avancado com webhook dinamico tokenizado sem quebrar o payload legado.
- **Tema global**
  - Layouts `admin.layouts.app` e `tenancy::tenant.layout` passam a consumir a engine central de tema para reduzir divergencia entre admin, tenant e white label.

### Validado
- Testes focados: 11 testes, 59 assertions.
- `php artisan view:cache`: OK
- `php artisan route:cache`: OK

---

## [1.6.12] - 2026-05-28

### Corrigido
- **WhatsApp Admin — Página /admin/whatsapp/instance (refactoring completo)**
  - `EvolutionClient::getNormalizedInstanceStatus()`: `/instance/info/{instance}` e `/instance/all` agora tentam também com instance API key (não apenas global key); extração de número busca `body.instance.number`, `body.data.instance.number` e JID aninhado
  - View `instance.blade.php` reescrita: placeholders "Não informado" em vez de "-"; QR box com altura reduzida (260px) e layout centralizado; badge com 4 estados (Conectado verde, Aguardando QR amarelo, Erro vermelho, Desconectado cinza); botões Conectar/Desconectar com estado disabled conforme conexão
  - JS refatorado: `refreshWhatsAppStatus`, `testWhatsAppConnection`, `connectWhatsAppInstance`, `disconnectWhatsAppInstance`, `restartWhatsAppInstance`, `saveWhatsAppConfig`, `applyWhatsAppStatus`, `renderQrBox`, `extractQrCode`, `setText`, `showSuccess`, `showError`, `setButtonLoading`
  - Métodos do controller mantidos: `status()` usa `getNormalizedInstanceStatus()`, `testConnection()` usa método normalizado, `connect()` com cascade de estados

### Validado
- `php -l` em 3 arquivos alterados: sem erros
- `php artisan optimize:clear`: OK
- `php artisan route:list`: 20 rotas intactas
- Testes WhatsApp/Evolution: 42 passed, 162 assertions

---

## [1.6.11] - 2026-05-28

### Corrigido
- **WhatsApp Admin — Página /admin/whatsapp/instance**
  - `getNormalizedInstanceStatus()` no `EvolutionClient`: adicionado array `$disconnectedStates` para reconhecer `close`, `closed`, `disconnected`, `offline`, `logout`, `logged_out`, `false`, `0`, `inactive`; `status_label` agora retorna "Aguardando QR Code" (ao invés de "Conectando") quando estado é `connecting`
  - Fallback `internalBuildFallbackResponse()` também reconhece `connecting` no banco de dados
  - `connect()` no controller: substituída lógica manual de estados pelo normalizador `getNormalizedInstanceStatus()` — aceita `open`, `connected`, `online`, `authenticated`, `logged`, `true`, `1`, `ready`, `active`
  - `testConnection()` no controller já usava `getNormalizedInstanceStatus()` (correção anterior mantida)
- **View instance.blade.php — Estrutura Blade**
  - Modal de configuração movido para dentro de `@section('content') ... @endsection`
  - IDs renomeados: `badge-status` → `status-badge`, `info-state` → `status-state`, `info-number` → `status-number`, `info-name` → `status-name`, `info-instance` → `status-instance`, `info-client` → `status-client`, `info-created` → `status-created-at`, `info-checked` → `status-last-check`, `qr-container` → `qr-box`
  - QR conectado agora usa `alert alert-success` com "Instância conectada / QR Code não necessário"
  - `fetchQrCode()` aceita múltiplas fontes: `base64`, `qr`, `code`, `pairingCode`, `data.base64`, `data.qrcode`, `data.Qrcode`
  - `checkConnection()` usa `data.connected` (booleano) ao invés de `data.state === 'open'`
  - `refreshStatus()` extraído para função `updateQrBox()` reutilizável
- **Favicon**
  - Criado `public/favicon.ico` (16×16, círculo verde)
  - Cache-busting `?v={{ filemtime() }}` no layout `app.blade.php`

### Validado
- `php -l` em todos os 5 arquivos alterados: sem erros de sintaxe
- Testes focados (WhatsApp/Evolution): 42 passed, 162 assertions

---

## [1.6.9] - 2026-05-23

### Adicionado
- **Calendario de cobrancas**
  - Calendario passa a usar recursos operacionais do FullCalendar 6: selecao de periodo, drag/drop, resize, visualizacoes mensal/semanal/diaria/lista, edicao por modal e cancelamento direto de cobrancas ou envios.
  - Adicionada criacao manual de cobrancas pelo calendario com busca AJAX de clientes, mascara de moeda BRL e atualizacao sem sair da tela.
  - Feriados ativos passam a aparecer no calendario de cobrancas e dias bloqueados ficam destacados visualmente.

### Corrigido
- **Regras de vencimento**
  - Backend agora bloqueia vencimentos e envios manuais em fins de semana ou feriados nas rotas do calendario, editor de faturas e API publica.
  - Geracao de parcelas por pacotes tenant e importacao de faturas WHMCS ajustam vencimentos automaticamente para o proximo dia util.
  - Envio manual de cobranca via WhatsApp deixa de ignorar feriados e fins de semana.
- **Padrao visual do administrativo**
  - Dashboard principal, `admin/reports`, `admin/backups` e `admin/pwa` foram reorganizados no mesmo padrao visual premium aplicado em analytics e CRM, com hierarquia mais clara, blocos coerentes e suporte consistente a dark/light.
  - Os cards-resumo do hero passaram a ter estilo reutilizavel no layout administrativo, evitando quebra visual entre telas que compartilham o mesmo componente.
- **Analytics**
  - Tela `admin/analytics` foi reorganizada no padrao visual premium do AdminLTE, com hero compacto, filtros mais claros, cards de KPI consistentes, blocos analiticos melhor separados e suporte correto a dark/light na renderizacao dos graficos.
- **CRM**
  - Tela `admin/crm` foi reorganizada no mesmo padrao visual do painel, com hero mais limpo, atalhos operacionais coerentes, KPIs mais legiveis, tabelas melhor separadas e resumo comercial menos confuso.

### Validado
- Suite completa Pest: 106 testes, 568 assertions.

---

## [1.6.8] - 2026-05-22

### Adicionado
- **Tema dark**
  - Tema escuro do painel administrativo e painel tenant padronizado com fundo azul-preto, superficies marinho, bordas discretas e primario roxo em cards, tabelas, formularios, modais, dropdowns e addons.
- **Tenants e API REST**
  - Adicionada edicao completa de tenants pelo superadmin, incluindo plano, status, periodo, usuario vinculado e liberacao comercial da API REST.
  - Adicionada tela do tenant para gerar e revogar tokens Sanctum da API REST publica.
  - API REST publica passa a exigir tenant ativo e acesso contratado/liberado por plano ou configuracao comercial.
- **Marketplace**
  - Marketplace passa a instalar addons embutidos funcionais quando o item nao possui ZIP remoto, criando manifesto, provider, rota, view e menu automaticamente.
  - Instalacao de ZIPs remotos passa a validar manifesto e normalizar o diretorio extraido antes de instalar.
  - Todos os addons internos do catalogo passam a ficar instalaveis diretamente, incluindo pacotes pagos/mensais, sem ficarem bloqueados como "Em breve".
  - Addons gerados pelo marketplace agora incluem dashboard, configuracoes comerciais, relatorios, menus internos e metadados de comercializacao para superadmin/admin.
  - Addons internos antigos instalados pelo marketplace sao sincronizados automaticamente para o novo padrao organizado sem exigir reinstalacao manual.
  - Addons internos passam a gerar tabela operacional propria, tela de registros, configuracao de API/webhook, job no CronManager, execucao manual do cron e relatorios com indicadores reais.
- **Padronizacao visual**
  - Adicionada camada global de estilos para paginas de modulos e addons respeitarem o tema dark/light do AdminLTE sem trocar sidebar, topbar, cards, tabelas ou formularios.
  - As cores base de modulos e addons passam a usar exclusivamente tokens oficiais do Bootstrap/AdminLTE (`--bs-*`), evitando paletas paralelas e inconsistentes.
  - O toggle de tema agora dispara o evento `admin:theme-change`, permitindo que componentes dinamicos reajam ao tema sem quebrar JS existente.
  - O layout administrativo passa a renderizar cabecalho oficial AdminLTE 4 com titulo, breadcrumb automatico e area padronizada de acoes em todas as paginas que usam `admin.layouts.app`.
- **Tour guiado**
  - Tour guiado modernizado no painel administrativo e no painel tenant, com visual premium, progresso, contador de etapas e botao de fechar em formato de X.
  - O tour passa a abrir automaticamente no primeiro acesso de cada usuario e registra a conclusao ao finalizar ou fechar, sem reaparecer nos proximos acessos.
  - Adicionado botao de ajuda no topo para reiniciar o tour manualmente a qualquer momento.

### Corrigido
- **Provisionamento Evolution GO**
  - A criacao da instancia agora aplica automaticamente token, webhook e configuracoes padrao na Evolution sem depender de acao manual no modal.
  - O timeout do endpoint legado `/user/avatar` deixa de contaminar `last_error` da instancia quando a API nao responde no prazo.
- **Addons**
  - Addons deixam de aplicar paletas proprias sobre o painel e passam a respeitar as superficies, bordas, textos e sombras oficiais do tema AdminLTE em dark/light.
  - Removidos handlers JavaScript duplicados na tela de addons, deixando apenas um fluxo AJAX para instalar, ativar e desativar modulos.
  - Corrigido registro de menus dinamicos para respeitar `menu.route`, incluindo URLs internas e nomes de rotas Laravel.
  - Addons agora podem declarar varios menus em `menu.items`, `menu.children`, `menu.menus` ou em lista direta, exibidos agrupados dentro de `Addons > Nome do addon`.
  - O ciclo de vida de addons agora retorna falha quando migrations, permissoes, menus ou rollback nao concluem corretamente.
  - A sidebar dinamica passa a marcar item e grupo ativo usando tanto caminho quanto nome de rota.
  - A rota tecnica de detalhes foi movida para `/admin/addons/{slug}/detalhes`, liberando `/admin/addons/{slug}` para o painel real do addon.
  - O autoload de addons agora aceita `module.json` e carrega providers ativos pelo status salvo no banco, mesmo quando o manifesto antigo esta com `enabled=false`.
  - A tela de detalhes do addon deixou de despejar o manifesto JSON completo no painel.

### Validado
- Suite completa Pest: 94 testes, 492 assertions.

---

## [1.6.7] - 2026-05-22

### Corrigido
- **Instancias WhatsApp**
  - A criacao de instancia no painel tenant agora atualiza lista, contadores e acoes por AJAX sem exigir refresh manual.
  - O QR Code passa a usar fallback compativel com Evolution GO por token da instancia (`POST /instance/connect` + `GET /instance/qr`) e reconhece `data.Qrcode`.
  - A coluna `tenant_instances.evolution_qr_code` foi ampliada para `LONGTEXT` para suportar imagens base64 reais da Evolution.
  - Criacao, renomeacao e exclusao passam a retornar payload visual para manter o card atualizado imediatamente.
- **Addons dinamicos**
  - Apos instalar, ativar, desativar ou baixar addon, a tela de addons e a sidebar sao recarregadas por AJAX sem refresh completo.

### Validado
- Suite completa Pest: 85 testes, 429 assertions.

---

## [1.6.6] - 2026-05-22

### Adicionado
- **Evolution API GO full sync**
  - Criado espelho `whatsapp_instances` para sincronizacao operacional sem remover `tenant_instances`.
  - Criados logs `whatsapp_sync_logs`, `whatsapp_webhook_logs`, `whatsapp_customer_service_logs` e `security_audit_logs`.
  - Adicionado endpoint `POST /api/webhooks/evolution-go` com validacao de token por instancia/global, deduplicacao e processamento por job.
  - Adicionados jobs de envio WhatsApp, processamento/retry de webhooks, sincronizacao de instancias, status de cobranca e limpeza de logs.
- **Autoatendimento WhatsApp**
  - Adicionado menu automatico com consultas de debitos, segunda via, plano, vencimento, cadastro, suporte humano e encerramento.
  - Adicionado controle de limites diarios por tenant, cliente, telefone e tipo de acao em `whatsapp_customer_limits`.
- **Addons dinamicos**
  - Criado `AddonManagerService` com instalacao, ativacao, desativacao, migrations, permissoes, menus e cache automatico.
  - Adicionadas rotas AJAX `/admin/addons/{module}/install|activate|deactivate|migrate` e `GET /admin/sidebar/reload`.
  - Adicionada tabela `addon_menus` e sidebar dinamica sem refresh.
- **Producao e seguranca**
  - Adicionada configuracao de fila database/sync, processamento de fila via cron em cPanel e documentacao de deploy/filas/cache.
  - Complementado hardening de uploads com validacao de extensao por contexto e `.htaccess` em `public/uploads`.

### Corrigido
- **Preview de email**
  - Removido o fundo branco do envelope padrao em `/admin/email/config` e `/painel/email/config`.
  - Reposicionada a logo do preview para o canto esquerdo com nome da marca ao lado, respeitando dark/light.

### Documentado
- Criados `docs/evolution-go-webhook.md`, `docs/addons-dinamicos.md` e `docs/producao-filas-cache.md`.
- Atualizado README com os novos fluxos de Evolution GO e addons dinamicos.

### Validado
- Suite completa Pest: 83 testes, 416 assertions.

---

## [1.6.5] - 2026-05-21

### Adicionado
- **Notificacoes em tempo real**
  - Adicionado feed AJAX do sininho para superadmin/admin e tenant, com polling, alerta visual, som de alerta e preferencia de som por usuario.
  - Adicionada migration `notification_sound_enabled` em `admin_users` e `users`.
  - Reposicionado o contador numerico do sininho para ficar preso ao icone da notificacao nos layouts admin e tenant.
- **2FA**
  - Adicionada opcao para o usuario admin desativar 2FA pelo proprio perfil mediante senha atual.
  - Adicionada acao para o superadmin desativar 2FA de usuarios admin no painel de permissoes.
  - Ajustados os botoes de 2FA para alternarem visualmente entre "Ativar" verde e "Desativar" vermelho.
  - Adicionado 2FA para usuarios tenant, com ativacao/desativacao pelo proprio perfil e verificacao separada do fluxo superadmin.
- **Perfil de usuario**
  - Adicionada foto de perfil editavel para usuarios admin e tenant, com upload de avatar e preview no menu do painel.
  - Adicionado perfil do tenant em `/painel/perfil`, deixando claro que o avatar da instancia WhatsApp e sincronizado somente pelo aplicativo conectado.
  - Restaurados dados pessoais, endereco completo com ViaCEP, alteracao de senha e seguranca 2FA no perfil do tenant.
- **Instancias Evolution Go**
  - Adicionada criacao obrigatoria da instancia no servidor Evolution Go antes de gravar no sistema.
  - Adicionada exclusao de instancias pela conta tenant e pelo superadmin, removendo tambem no servidor Evolution Go.
  - Adicionada compra de instancia extra acima do limite do plano, com confirmacao no painel, valor recorrente por plano e fatura pro-rata agrupada em fatura pendente.
  - Corrigida a criacao em servidores Evolution Go que exigem os campos `name` e `token`, mantendo tambem `instanceName` para compatibilidade.
  - Corrigida a exclusao para usar UUID remoto quando a Evolution retornar esse identificador e permitir remover instancias locais orfas quando o servidor rejeitar por UUID invalido.
- **Templates por tenant**
  - Adicionada preferencia de template padrao por tenant, canal, categoria e agendamento para WhatsApp e e-mail.
  - Templates do sistema agora ficam protegidos contra exclusao/edicao pelo tenant, mantendo a possibilidade de criar modelos proprios e defini-los como padrao.
- **Precificacao de instancias extras**
  - Adicionada tabela no superadmin para configurar precos de instancias extras por plano e faixa de quantidade.
  - A cotacao de compra de instancia extra passa a usar a tabela do superadmin antes do valor fallback do plano.
- **Pacotes comerciais do tenant**
  - Adicionado cadastro de pacotes com valor integral, descricao do servico, pagamento a vista/parcelado, repasse opcional de taxas e juros por parcela.
  - Adicionada vinculacao de pacote ao cliente com geracao automatica das faturas e parcelas.
  - Adicionada atualizacao de parcelas quando o pagamento da fatura e confirmado e notificacao automatica de confirmacao com proximo vencimento.
  - Chatbot WhatsApp agora consulta dados cadastrais, servicos contratados, valor devido e parcelas pagas/pendentes/em atraso do cliente.
- **White Label do tenant**
  - Adicionado painel `/painel/white-label` para o usuario configurar marca, slogan, logo, favicon, cores, CSS, rodape, login customizado e dominio proprio.
  - Adicionada validacao visual de DNS por tenant, com status de propagacao, alvo CNAME/A esperado, checagem manual e revalidacao automatica enquanto estiver pendente.
  - Dominios proprios validados passam a abrir o login com a marca do tenant e redirecionam a raiz do dominio para o painel/login.
  - Adicionada configuracao global no superadmin para alvo DNS dos tenants e permissao para ativar/desativar White Label no painel do usuario.

### Corrigido
- **Contratos e sessao**
  - Corrigido o menu de contratos do superadmin para usar rotas `/admin/contracts`, sem cair no painel tenant `/painel/contratos`.
  - Adicionadas rotas admin proprias de contratos e ajustes nas views para alternarem corretamente entre contexto superadmin e tenant.
  - Corrigido o modo supervisionado para nao encerrar ou voltar ao superadmin ao clicar no menu/logouts comuns do cliente; o retorno agora ocorre apenas pelo botao de supervisao.
  - Corrigido o detalhe do contrato em `/painel/contratos/{id}` para nao quebrar quando houver evento antigo sem data registrada no historico.
  - Corrigido o timbrado do contrato para renderizar como base real do documento no painel, inclusive quando o timbrado for PDF, sem iframe visual do navegador sobre o conteudo.
  - Corrigido o link publico de contrato expirado para exibir uma pagina 410 tratada e visualmente padronizada, sem exception crua do Laravel.
  - Eventos novos de contratos agora gravam `created_at` explicitamente e uma migration corrige registros antigos sem data.
- **Interface premium**
  - Refeito `/admin/2fa/verify` no padrao AdminLTE 4 gourmet premium com suporte dark/light.
  - Padronizado preview global de uploads antes, durante e depois do envio em todos os elementos `[data-upload]`.
  - Corrigido o onboarding do tenant para usar o layout do painel do cliente no acesso supervisionado, sem exibir menu ou rotas do superadmin.
  - Padronizado o botao do estado vazio de instancias e protegido o tamanho dos icones dentro de botoes nos layouts admin e tenant.
- **Seguranca**
  - Ajustada a leitura de codigos de recuperacao 2FA criptografados.
  - Separado o middleware de 2FA entre admin e tenant para impedir que o acesso supervisionado seja tratado como fluxo do superadmin.
- **Cobranca tenant**
  - Faturas recorrentes do tenant agora agrupam mensalidade do plano e instancias extras ativas.
  - Corrigido o fluxo para nao criar instancia local quando a Evolution Go retorna erro na criacao remota.
- **White Label**
  - Corrigido o salvamento das configuracoes globais de White Label para usar corretamente o grupo `whitelabel.*`.

### Validado
- Suite completa Pest: 80 testes, 399 assertions.

---

## [1.6.4] - 2026-05-21

### Adicionado
- **Instancias WhatsApp**
  - Adicionada sincronizacao por instancia da Evolution Go para refletir avatar, nome, slogan/status, numero e JID do WhatsApp no painel.
  - Adicionado modal de configuracao por instancia com URL/token da API, webhook seguro por token, eventos e ajustes como rejeitar chamadas, ignorar grupos, sempre online, leitura de mensagens/status e historico completo.
  - Adicionada protecao do webhook tenant por token da instancia, mantendo o roteamento correto para o tenant dono da conexao.
- **Superadmin e seguranca**
  - Adicionado atalho direto "Login supervisionado" no menu Superadmin, apontando para a listagem de tenants.
  - Ampliado o painel WAF com controles globais para ativar/desativar, escolher modo, proteger admin, ajustar rate limit, auto block e bloqueio de User-Agent vazio.
  - Adicionado suporte a limpeza de OPcache no painel de cache quando a extensao PHP estiver disponivel.

### Corrigido
- **Painel do cliente**
  - Padronizado o layout do tenant em AdminLTE 4 com sidebar, header, cards premium e suporte dark/light persistente.
  - Corrigida a persistencia do tema dark/light no painel do cliente ao navegar entre paginas, com rota propria de preferencia do tenant e fallback no usuario.
  - Atualizado o dashboard do cliente para seguir o mesmo padrao gourmet premium do superadmin.
  - Ajustadas telas compartilhadas de e-mail, contratos e afiliado para usarem o layout do cliente quando acessadas por `/painel`.
- **Afiliados**
  - Adicionada migration corretiva para bancos publicados que ainda nao tinham `affiliate_commissions.commission_amount`, evitando erro 500 em `/painel/afiliado`.
  - Protegido o dashboard de afiliados para somar a coluna correta de comissao mesmo durante atualizacoes de schema antigas.
- **Notificacoes**
  - Isoladas as notificacoes do sininho por audiencia: superadmin, admin especifico e tenant nao compartilham mais alertas entre si.
  - Adicionada listagem de notificacoes no painel do cliente com a mesma protecao de leitura e marcacao como lida por contexto.
- **Valores monetarios no painel**
  - Corrigida a formatacao global de moeda para valores gravados como decimal tecnico (`97.90`) nao aparecerem multiplicados por cem (`R$ 9.790,00`).
  - Normalizada a leitura de moeda brasileira em faturas, planos, assinaturas, saques de afiliados, taxa de repasse e API publica de faturas.
  - Padronizados campos monetarios do painel para aceitar `R$ 97,90`, `97,90` e `97.90` sem alterar o valor real salvo.
- **Detalhe de faturas**
  - Corrigida a tela `/admin/billing/invoices/{id}` para renderizar a descricao rica do Summernote sem exibir HTML bruto ou atributos internos `data-start/data-end`.
  - Padronizada a pagina de detalhes da fatura no visual gourmet premium com cards de resumo, status traduzido e acoes organizadas.
  - Ajustada a listagem e o PDF da fatura para usarem descricao textual limpa em previews e renderizacao segura no documento.
- **Padrao gourmet premium**
  - Padronizadas as telas de login supervisionado, detalhe do tenant, cache do sistema e WAF para o visual premium dark/light inspirado em `/admin/email/config`.
  - Substituidas confirmacoes nativas restantes por SweetAlert2 no Tenancy e na manutencao.
- **Robustez operacional**
  - Protegidas as rotas do WAF com 2FA alem de superadmin.
  - Adicionada auditoria nas acoes de cache do Laravel feitas pelo painel.
  - Ajustadas migrations corretivas de contratos e faturas para nao quebrarem a suite SQLite de testes.

---

## [1.6.3] - 2026-05-21

### Corrigido
- **Criacao de faturas com descricao longa**
  - Alterada a coluna `invoices.description` para `LONGTEXT`, permitindo descricoes ricas do Summernote sem erro `Data too long for column`.
  - Adicionada migration corretiva para bancos ja publicados atualizarem a coluna existente.
  - Melhorado o retorno AJAX da tela de fatura para exibir mensagens amigaveis em vez de detalhes SQL.
- **Editor de faturas**
  - Substituidas confirmacoes nativas do navegador por SweetAlert2 no padrao gourmet premium do sistema.
  - Ajustada a tela de criacao/edicao para respeitar tema dark/light do AdminLTE 4, incluindo cards, editor Summernote e preview.
- **Limpeza de artefatos locais**
  - Removidos scripts temporarios `remote_*.js` usados em diagnostico remoto.
  - Adicionada a pasta `entregas/` ao `.gitignore` para ZIPs locais nao ficarem pendentes no Git.

---

## [1.6.2] - 2026-05-20

### Adicionado
- **Cliente demo para padronizacao comercial**
  - Criado `DemoTenantSeeder` para popular usuario, tenant ativo, plano demo, instancia WhatsApp, gateway sandbox, configuracao de cobranca, clientes, faturas e templates.
  - Credenciais de demonstracao: `cliente.demo@exemplo.com` / `Demo@123456`, com acesso ao painel em `/painel`.

### Corrigido
- **Templates de contratos no painel do cliente**
  - Padronizada a tela de criacao/edicao de templates para o visual gourmet premium com suporte consistente a tema claro/escuro.
  - Adicionado upload drag-and-drop de papel timbrado no painel do cliente, com progresso e preview do arquivo enviado.
  - Corrigida a legibilidade do editor Summernote no tema escuro, mantendo a area de edicao com fundo de documento e texto contrastado.
  - Corrigida a impressao de estilos empilhados no layout admin e substituido preview de PDF em iframe por renderizacao via PDF.js.
- **Login supervisionado de tenants**
  - Adicionado acesso direto "Acessar painel" na listagem de tenants do superadmin, com confirmacao antes de assumir a conta do cliente.
  - Melhorada a listagem de tenants com e-mail, plano, status, link de detalhes e paginacao visivel.
- **Contratos no painel do cliente**
  - Criada migration corretiva para adicionar `tenant_id` em `contracts` e `contract_templates` quando tabelas antigas ja existem sem essas colunas.
  - Ajustadas consultas de contratos para evitar erro 500 temporario caso o deploy suba antes da migration ser executada.
  - Corrigido erro de parser em `/painel/contratos/criar` ao serializar templates para o JavaScript da tela.
- **Limpeza automatica de cache**
  - Corrigida a ativacao da limpeza diaria para usar o schema real de `cron_jobs` (`name` e `interval_min`), evitando erro 500 por coluna inexistente `job`.
  - Ajustada a tela para manter o botao marcado apos atualizar a pagina ou limpar o cache da aplicacao, usando `cron_jobs` como fonte principal do estado.
- **Painel administrativo gourmet premium**
  - Padronizada a base visual do administrativo usando como referencia `/admin/email/config`.
  - Adicionado upload drag-and-drop premium para logo de email na configuracao SMTP.
  - Corrigido preview de emails com corpo textual solto para renderizar em paragrafos legiveis.

## [1.6.1] - 2026-05-20

### Corrigido
- **Base de testes automatizados**
  - Adicionada configuração `phpunit.xml.dist` para executar Pest/Laravel com SQLite em memória.
  - Instaladas dependências de desenvolvimento do Pest e plugin Laravel, habilitando `php artisan test`.
  - Ativado `RefreshDatabase` para testes de feature que exercitam rotas, autenticação, Sanctum e banco.
  - Preparado estado de instalação e heartbeat no `TestCase` para validar rotas protegidas e `/health`.
- **Migrations modulares**
  - Carregamento automático das migrations dos módulos em `app/Modules/*/database/migrations`.
  - Inclusão das migrations do Sanctum para garantir a tabela `personal_access_tokens` em ambiente limpo.
- **Compatibilidade PHP 8.5**
  - Ajustado `config/database.php` para evitar depreciação de `PDO::MYSQL_ATTR_SSL_CA` em versões novas do PHP.
  - Desativado o heartbeat assíncrono do cron durante testes para evitar shutdown fatal no container de teste.
- **Cobrança por e-mail no administrativo**
  - Adicionadas rotas `/admin/email/config` e `/admin/email/templates` para o admin operar sua própria conta tenant.
  - Criado resolvedor de tenant para reutilizar a conta pública do admin ou criar automaticamente uma conta operacional.
  - Views de configuração SMTP e templates agora funcionam tanto no painel do cliente quanto no administrativo.
  - Adicionados testes cobrindo acesso e salvamento de SMTP pelo admin.
- **Login assistido de tenant pelo superadmin**
  - Adicionada ação para o superadmin assumir uma conta tenant direto da tela de detalhes.
  - Criado fluxo de retorno seguro do painel tenant para o painel admin, com indicador visual de modo assistido.
  - Incluído teste de feature cobrindo o ciclo completo de assumir tenant e voltar ao admin.

## [1.6.0] - 2026-05-19

### Adicionado
- **Páginas Secundárias Gourmet Premium**
  - Refatoração total das páginas `Recursos.tsx`, `Sobre.tsx` e `Contato.tsx` para o padrão Gourmet dark mode unificado.
  - Implementação de SweetAlert2 global nas interações do frontend (Contato.tsx) e substituição de alertas nativos.
  - Melhorias visuais, animações fluidas com Framer Motion, e grid de funcionalidades premium.
- **Upload Drag & Drop no Painel Preload**
  - Padronização das áreas de upload para arrastar e soltar (Drag & Drop) nas configurações de Preload (/admin/settings/preload) para logotipo, imagem de fundo e vídeo de fundo.
  - Adicionado input para `accent_color` no formulário de configurações do Preload, resolvendo erro de validação ao salvar configurações.
  - Sincronização inteligente dos color pickers de Preload.

### Corrigido
- **Tabela de Preços e Recursos de Planos**
  - Correção na exibição da tabela de preços e planos no frontend público (Pacotes.tsx e Home.tsx).
  - Execução e alimentação automática dos seeders de planos (`PlansSeeder` e `FrontendDataSeeder`) no banco de dados do servidor de produção via script de deploy SSH.
  - Ajuste de dependência TS de imports do `AnimatedCounter` no frontend.

## [1.5.0] - 2026-05-19

### Adicionado
- **Editor de Arquivos Online (Modo Superadmin)**
  - Painel com file tree interativa (resources, config, routes, frontend, public)
  - Editor Monokai com syntax highlighting via AceEditor
  - Salvamento direto do navegador com atalho Ctrl+S
- **Design Gourmet Premium (Frontend Dark Mode Total)**
  - Refatoração completa da Home para dark mode unificado (bg-slate-950)
  - Seção de 4 Planos embutida na Home com toggle Mensal/Anual e desconto de 20%
  - Cards de plano com checklist de features e itens excluídos (Check/X)
  - Hero com métricas animadas (72%, 3.500+, 2.5M, 99.9%) em dark
  - Grid 9 features com ícones gradientes e hover glow
  - CTA final com botões "Testar Grátis" e "Falar com Vendas"
  - Carrossel de depoimentos com marquee infinito
  - Plano Ultimate adicionado ao seeder (4 planos reais no banco)
  - Textos 100% comerciais: cobranças, PIX, WhatsApp, inadimplência

### Corrigido
- **Tamanho do campo Two Factor (2FA)**
  - Colunas `two_factor_secret` e `two_factor_recovery_codes` migradas para `TEXT`
- **Deploy de Produção (.gitignore)**
  - Removido `public/build/` do gitignore para deploy correto das builds React/Vite
- **Importação inválida `Lock`** no Home.tsx removida (TS6133)

---

## [1.4.0] - 2026-05-17

### Adicionado
- **Graphify configurado** — knowledge graph do projeto com 1482 nos e 1608 arestas
  - Mapeamento completo: controllers, models, services, migrations, rotas, middlewares, views
  - 276 comunidades de codigo identificadas
  - Skill instalado no Kiro para consultas inteligentes
  - Comando: `python -m graphify update .` para atualizar apos mudancas
  - Visualizacao interativa em `graphify-out/graph.html`
- **Modulo Open Graph** (`app/Modules/OpenGraph/`) — gerador de imagens OG nativo
  - Gera imagens 1200x630px com gradiente, titulo, subtitulo e logo
  - Usa cores do tema configurado em `/admin/settings/theme`
  - Painel admin em `/admin/opengraph` com color pickers e preview ao vivo
  - Cache inteligente com TTL configuravel (padrao 24h)
  - Blade directives: `@ogMeta('titulo', 'desc', 'slug')` e `@ogImage('titulo')`
  - Rota publica `/og-image?title=...` para geracao sob demanda
  - Botao de limpar cache de imagens geradas
  - Compativel com Laravel 13 + PHP 8.4 (substitui ibnnajjaar/graphify incompativel)
- **Instalador standalone** (`public/installer.php`) — PHP puro, sem dependencia de middlewares Laravel

### Corrigido
- **Tela branca na home** — causada por SPA sem bundle compilado + Service Worker cacheando versao quebrada
- **403 no /admin/login** — WAF detectava falso positivo de SQLi em cookies do navegador; login/2FA excluidos do WAF
- **429 em rotas admin** — rate limit muito agressivo (120/min); aumentado para 300/min e GET admin isento
- **Instalador antigo travava no step 2** — bug no querySelector que pegava o dot do indicador em vez da div do step
- **Admin duplicado na instalacao** — seeder criava admin padrao + instalador criava outro; agora so o do instalador fica
- **Erro `Call to a member function exec() on null`** — $pdo era usado antes de ser instanciado no instalador
- **Erro `Data truncated for column role`** — coluna ENUM aceita 'superadmin' (sem underscore)
- **CSP bloqueando sourcemaps** — adicionado cdn.jsdelivr.net e cdnjs.cloudflare.com em connect-src
- **Meta tag PWA deprecated** — adicionada `mobile-web-app-capable` ao lado da `apple-mobile-web-app-capable`
- **CronHeartbeat crashava em /install** — adicionada excecao para rotas `install*`
- **Service Worker cacheava versao quebrada** — bumpado para cwa-v2 + auto-unregister quando sem build

### Melhorado
- **Sidebar premium** — border-radius, hover suave, active com cor dinamica do tema, seta rotaciona ao abrir treeview
- **Menu ativo** — todos os treeviews marcam `menu-open` + `active` baseado na URL atual
- **Script pos-AdminLTE** — forca menu aberto no item ativo apos carregamento
- **.gitignore completo** — padroes Laravel (storage/framework, bootstrap/cache, composer.phar, error_log, .deploy.env)
- **RedirectIfNotInstalled** — redireciona para `/installer.php` em vez de `/install`

---

## [1.3.0] - 2026-05-16

### Adicionado
- **Documentacao completa para modulos de terceiros** (`docs/MODULOS_TERCEIROS.md`)
  - Guia de 16 secoes com estrutura de pastas, addon.json, ServiceProvider, rotas, permissions, migrations, cron, hooks, dependencias, menu e convencoes
- **Modulo de Notificacoes** (multi-canal: in_app, push, email, whatsapp)
  - `NotificationDispatcher` service central consumivel por qualquer modulo/addon
  - Fila de processamento (`notification_queue`) com cron a cada 1 minuto
  - Painel admin: envio manual, configuracao de canais, historico com filtros
  - Migration: `notification_queue` + `notification_templates`
- **Editor de Fatura** (CRUD completo AJAX)
  - Criar/editar faturas com Summernote (descricao rica)
  - Busca de clientes com autocomplete AJAX
  - Selecao de gateway (MercadoPago/EfiPro/SumUp)
  - Gerar link de pagamento transparente com 1 clique
  - Envio direto via WhatsApp
  - Duplicar fatura
  - Preview estilo WhatsApp
- **Separacao de gateways como modulos independentes**
  - Documentacao para criar novos gateways como addons (`app/Modules/Gateways/README.md`)
  - Webhook dinamico por fatura (token unico)
- **Controllers de configuracoes gerais**
  - General (logo, favicon, cor, rodape)
  - LGPD (banner configuravel)
  - Google Ads/Business (GA, Ads, GTM, Business Profile, depoimentos)
  - Upload (tamanho maximo, MIME types)
  - Slides/Carrossel (CRUD completo)
  - Popups (CRUD completo)
  - Depoimentos (CRUD completo)

### Alterado
- `SettingsServiceProvider` agora registra todas as rotas admin de configuracoes
- Sidebar atualizado com links para Notificacoes e Editor de Fatura

---

## [1.2.0] - 2026-05-16

### Adicionado
- **Instalador Web** (wizard multi-step sem refresh)
  - Step 1: Verificacao de requisitos do servidor (PHP 8.4, extensoes, permissoes)
  - Step 2: Configuracao do banco de dados com teste de conexao real
  - Step 3: Configuracoes gerais (nome, URL, APP_KEY gerada automaticamente)
  - Step 4: Criacao do superadmin (dono do sistema) durante a instalacao
  - Step 5: Execucao de migrations + seeders + finalizacao (grava `storage/installed.lock`)
  - Middleware `RedirectIfNotInstalled` em TODAS as rotas web (seguranca)
  - Middleware `RedirectIfInstalled` impede re-instalacao
- **Sistema de permissoes modulares e granulares**
  - Hierarquia: superadmin (bypass total) > admin > operator > viewer
  - Migration com 6 tabelas: `roles`, `permissions`, `role_permissions`, `admin_user_roles`, `admin_user_permissions`, `registered_modules`
  - `PermissionManager` service com cache, `can()`, `canModule()`, `isSuperAdmin()` bypass
  - Middleware `module.permission` (ex.: `module.permission:billing.edit`)
  - Middleware `role` (ex.: `role:admin,superadmin`)
  - Blade directives: `@permission('slug')` e `@role('slug')`
  - Gate integration (Laravel Gate funciona com permissions)
  - Painel admin CRUD AJAX: criar/editar/excluir roles, atribuir permissions por modulo com checkboxes visuais
  - `AdminUser` model com helpers: `hasPermission()`, `canModule()`, `hasRole()`, `syncRoles()`, `assignRole()`, `grantPermission()`, `denyPermission()`
  - Cada modulo registra permissions automaticamente via `Permission::registerCrud()`
- **AddonManager com lifecycle completo**
  - `install()` → roda migrations, registra permissions, chama hook
  - `enable()` / `disable()` → ativa/desativa sem perda de dados
  - `update()` → roda novas migrations, atualiza versao
  - `uninstall()` → rollback migrations, remove permissions
  - Verificacao de dependencias antes da instalacao
  - Painel admin de addons com cards visuais e acoes
  - `addon.json` schema com permissions, hooks, requires, settings, menu
  - README completo para desenvolvedores de terceiros (`app/Addons/README.md`)
- **Seeder atualizado**
  - Roles padrao: superadmin (level 100), admin (level 50), operator (level 20), viewer (level 5)
  - Atribuicao automatica de permissions por role
  - Admin padrao com role superadmin

### Alterado
- `AdminUser` model refatorado com relacoes de roles/permissions e helpers completos
- `bootstrap/app.php` atualizado com middlewares `module.permission`, `role`, `installed`
- Sidebar com itens condicionais (`@permission`, `@role`)
- Rotas admin com protecao por `role:superadmin` para addons

---

## [1.1.0] - 2026-05-16

### Adicionado
- **Modulo Security/WAF** (Web Application Firewall proprio)
  - Regras: SQLi, XSS, LFI, RFI, BadBot, RateLimit
  - `WafMiddleware` global em todas as requisicoes
  - Auto-block com expiracao temporal
  - Blocklist/Allowlist (IP, CIDR, User-Agent, Country)
  - `WafService` com patterns regex configuraveis
  - Painel grafico "Graphify" com Chart.js (eventos por dia, hora, regra, severidade)
  - Dashboard com cards de estatistica e top 10 IPs/regras
  - Eventos, regras, blocklist, allowlist, audit log — tudo com views CRUD AJAX
  - Cron de limpeza automatica (eventos >30 dias)
  - Pagina personalizada de bloqueio (`waf-blocked.blade.php`)

---

## [1.0.0] - 2026-05-16

### Adicionado (entrega inicial completa)

#### Arquitetura
- Laravel 13 + PHP 8.4 + MySQL/MariaDB
- Arquitetura modular (estilo WHMCS/WordPress) com Service Providers desacoplados
- UTF-8 sem BOM | Timezone America/Sao_Paulo
- Sistema de addons de terceiros com auto-discovery (`app/Addons/`)

#### Modulos oficiais
- **Billing**: faturas, clientes, agendamento automatico de envios, retry com backoff exponencial
- **WhatsApp**: cliente Evolution API (Go) com envio, verificacao de numero e logs
- **Templates**: editor Summernote com preview em tempo real + variaveis dinamicas
- **Settings**: configuracoes chave/valor com cache + Blade directive `@setting`
- **PWA**: manifest dinamico, service worker, icones, push, install prompt gerenciavel
- **Maintenance**: pagina com contador regressivo + liberacao por IP/MAC/dispositivo
- **SEO**: meta tags, hashtags, sitemap.xml, robots.txt, Open Graph
- **CRM**: dashboard basico de clientes/faturas/funil
- **Gateways**: MercadoPago (completo), EfiPro (stub), SumUp (stub) — checkout transparente

#### Painel Administrativo (AdminLTE 4)
- Layout premium com suporte dark/light toggle + persistencia
- Sininho de notificacoes agrupadas por modulo (billing, whatsapp, security, system)
- Sidebar responsivo com treeview
- Dashboard com cards de estatisticas
- CRUD 100% AJAX sem refresh de pagina

#### Funcionalidades globais
- **Cron interno** (heartbeat pos-resposta + endpoint externo opcional cPanel)
- **Kanban generico** reutilizavel (menus, categorias, cards, modulos)
- **Upload drag-drop** global com barra de progresso animada + tempo restante + controle de tamanho
- **Mascaras automaticas** ao digitar (moeda R$, CPF/CNPJ, telefone, CEP, data/hora)
- **ViaCEP** com preenchimento automatico e foco no campo numero
- **FullCalendar 6** integrado ao modulo Billing (vencimentos + envios)
- **Summernote** editor de texto em templates e faturas

#### Frontend publico
- React 18 + TypeScript + Tailwind CSS 4
- PWA footer exclusivo para app instalado (deteccao via `display-mode: standalone`)
- Header fixo ao rolar
- Voltar ao topo + preload personalizavel
- Banner LGPD com gestao de consentimento
- Service Worker com cache strategy

#### Autenticacao
- Login/Reset/Cadastro publico em layout 2 colunas (telas grandes)
- Guard separado `admin` para o painel
- Mascaras + ViaCEP no formulario de cadastro

#### Paginas especiais
- Manutencao com contador regressivo + personalizacao + liberacao por dispositivo
- Erros personalizados (403, 404, 500, WAF blocked)

#### Database
- 5 migrations completas (core, settings, templates, billing, security, pwa)
- Seeder com admin padrao + regras WAF + settings iniciais + templates de cobranca

#### Configuracao
- `.env.example` completo
- `config/`: app, auth, database, gateways, modules, whatsapp
- `.htaccess` com rewrite + UTF-8 + HTTPS preparado
- `.gitignore` adequado

---

## Convencoes do projeto

- **Encoding**: UTF-8 sem BOM (todos os arquivos)
- **Timezone**: America/Sao_Paulo (banco, PHP, JS)
- **Idioma**: Portugues Brasileiro (pt_BR)
- **Mascaras**: moeda (R$ 1.234,56), CPF (000.000.000-00), CNPJ (00.000.000/0000-00), CEP (00000-000), telefone ((00) 00000-0000), data (dd/mm/aaaa)
- **CRUD**: 100% AJAX sem refresh
- **Permissoes**: granulares por modulo (view/create/edit/delete/manage/export)
- **Addons**: plug-and-play com lifecycle completo

---

## Roadmap

- [ ] Implementacao completa dos gateways EfiPro e SumUp
- [ ] DataTable (server-side) em todos os relatorios
- [ ] Marketplace de addons (loja no painel)
- [ ] Push notifications WebPush completo (com minishlink/web-push)
- [ ] API publica documentada (Swagger/OpenAPI)
- [ ] Testes automatizados (Pest PHP)
- [ ] CI/CD com GitHub Actions
- [ ] Multi-tenancy (SaaS)



---

## [1.4.0] - 2026-05-16

### Adicionado

- **Modulo Analytics** (dashboard de business intelligence)
  - 6 KPIs em tempo real: receita, faturas, pagas, vencidas, conversao, mensagens
  - Grafico de receita por dia (linha com area)
  - Grafico de faturas por status (donut)
  - Grafico de envios WhatsApp empilhado por status
  - Top 10 clientes por receita
  - Periodo configuravel: 7/30/90/365 dias

- **Modulo Backup** (backup automatico do banco)
  - Cron diario as 24h gera backup `.sql.gz` em `storage/app/backups/`
  - Implementacao em PHP puro (compativel com cageFS/CloudLinux)
  - Retencao automatica de 30 dias
  - Painel admin (apenas superadmin): listar, criar manual, baixar, excluir

- **Modulo HealthCheck** (endpoint /health)
  - Verificacao de: database, cache, storage, evolution, cron, disk
  - Retorna 200 (healthy) ou 503 (degraded)
  - JSON detalhado com response_ms, used_pct, etc.
  - Compativel com Uptime Robot, Pingdom, BetterUptime

- **Modulo CustomerImport** (importacao em lote via CSV)
  - Auto-deteccao de delimitador (, ou ;)
  - Mapeamento sugerido automaticamente (16 aliases por campo)
  - Preview dos primeiros 5 registros antes da importacao
  - Dedup por documento ou email (atualiza se existe)
  - Sanitizacao de telefones para formato Evolution
  - Suporte a tenant_id (isolamento multi-tenant)
  - Estatisticas detalhadas: importados/ignorados/erros
