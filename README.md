# Plataforma SaaS de Cobrança e Régua Automatizada via WhatsApp Cloud API Oficial (Meta Graph API v21.0) & Mercado Pago

> **Software Proprietário Comercial Multi-Tenant**  
> Desenvolvido por **Marcelo Brad / KDKhost**  
> Contato: Tel: (21) 98132-5441 | Email: `marcelobradrj@gmail.com` / `contato@kdkhost.com.br`  
> Website: [https://kdkhost.com.br](https://kdkhost.com.br) | Central Financeira: [https://financeiro.kdkhost.com.br](https://financeiro.kdkhost.com.br)

---

## 🌟 Visão Geral da Plataforma

Esta aplicação foi refatorada e homologada como uma plataforma **SaaS Multi-Tenant de Cobrança e Régua Automatizada**, operando com **100% de conformidade com a WhatsApp Cloud API Oficial da Meta (Graph API v21.0)** e o **SDK v2 do Mercado Pago**.

### 🚫 Resíduo Zero de Engenharia Reversa
- **Eliminação Completa:** Removidas todas as dependências e emuladores de navegador (Baileys, Evolution API Go v3.0, whatsapp-web.js, WPPConnect, Venom, Puppeteer, sessões locais de Chromium e QR Codes de pareamento).
- **Sem Risco de Banimento:** A comunicação é feita exclusivamente através da infraestrutura oficial da Meta em servidores de nuvem, com verificação de qualidade da linha telefônica (Quality Rating: **GREEN**) e monitoramento contínuo.

---

## 🚀 Principais Recursos e Diferenciais

### 1. WhatsApp Cloud API Oficial (Meta Graph API v21.0)
- **Webhooks Oficiais com Handshake (`/api/v1/webhook`):**
  - **GET:** Handshake de verificação automática com validação de `hub.mode === 'subscribe'` e `hub.verify_token`, respondendo com `hub.challenge`.
  - **POST:** Verificação obrigatória do cabeçalho `x-hub-signature-256` utilizando **HMAC-SHA256** com o `APP_SECRET` da Meta sobre o payload bruto (`rawBody`).
- **Regra de 24 Horas & Templates Aprovados:**
  - Mensagens livres de sessão permitidas estritamente dentro da janela de 24h após a última interação do cliente.
  - Envios de cobrança ativa (fora da janela ou primeiro contato) utilizam **Message Templates Aprovados** na Meta com chave Pix Copia e Cola e link dinâmico da fatura pública.
  - Monitoramento contínuo do timestamp `last_interaction_at` de cada cliente final.

### 2. Monetização SaaS & Mercado Pago SDK v2
- **Checkout Transparente Duplo:**
  - **Pix Dinâmico:** Código Pix Copia e Cola instantâneo e renderização visual de QR Code em alta definição.
  - **Cartão de Crédito:** Tokenização direta via MercadoPago.js v2 / Bricks com parcelamento em até 12x, validação de CPF e identificação de bandeira/emissor.
- **Dedução e Repasse de Tarifas Automático:**
  - Captura das tarifas reais do gateway (`mercadopago_fee`, custos financeiros de parcelamento).
  - Cálculo instantâneo do repasse líquido para o Tenant (`net_tenant_amount`), armazenando o extrato detalhado para conciliação contábil.
- **Reembolso Parcial e Total:**
  - Endpoint de estorno direto com comunicação oficial com o Mercado Pago (`/v1/payments/{id}/refunds`), com atualização automática do status da fatura para `refunded` ou `partially_refunded`.
- **Webhooks Dinâmicos e Seguros:**
  - Cada fatura e assinatura SaaS gera uma `notification_url` dinâmica com validação de assinatura `x-signature` (`ts` + `v1`).

### 3. Página Pública da Fatura (`/fatura/{uuid}`)
- Layout responsivo, moderno e otimizado para celulares e desktops.
- Exibição de valor, vencimento, itens detalhados, status em tempo real (Pendente, Pago, Vencido).
- Botão "Copiar Código Pix" com 1 clique e feedback visual.
- Polling assíncrono via AJAX atualizando a tela imediatamente quando o pagamento for aprovado, com celebração via **SweetAlert2**.

### 4. Régua Automatizada de Cobrança (BullMQ + Redis / Cron Job cPanel)
- Agendamento em fila assíncrona:
  - **D-3:** Lembrete preventivo amigável com Pix e link da fatura.
  - **D0:** Alerta de vencimento no dia.
  - **D+1, D+3, D+5:** Cobrança de fatura em atraso.
- **Cancelamento Atômico:** Assim que a fatura é paga ou cancelada, todos os disparos subsequentes daquela fatura são purgados automaticamente da fila de envio.

### 5. UI/UX Global: SweetAlert2, Toastify e Ajuda Deslizante (Gaveta)
- **Zero Alertas Nativos:** `window.alert()` e `window.confirm()` foram 100% substituídos por **SweetAlert2** e **Toastify**.
- **Gaveta de Ajuda Lateral (Help Drawer):** Toda tela e campo de configuração conta com botão `?` que desliza um painel lateral da direita para a esquerda com instruções detalhadas da Meta e do Mercado Pago.

---

## 🛠️ Compatibilidade Dual de Implantação

O repositório foi construído para rodar perfeitamente em dois ambientes corporativos distintos:

### Ambiente A: cPanel / WHM (Setup Node.js App / Phusion Passenger)
- Ponto de entrada padrão: `app.js` na raiz do projeto.
- Conexão nativa com MySQL ou MariaDB do cPanel.
- Agendamento de cobrança via Cron Job nativo do cPanel:
  ```bash
  * * * * * cd /home/usuario/public_html && node scripts/cron-runner.js >> storage/logs/cron.log 2>&1
  ```
  *(Dispensa a obrigatoriedade de Redis local se o plano cPanel não permitir)*.

### Ambiente B: Docker & Docker Compose
- Container multi-stage em **Node.js 20 LTS Alpine** com baixo consumo de memória.
- `docker-compose.yml` pré-configurado contendo:
  - Serviço Web (`app`)
  - Banco de Dados PostgreSQL ou MySQL (`db`)
  - Cache e Filas Redis (`redis`)
- Inicialização rápida:
  ```bash
  docker compose up -d --build
  ```

---

## 📦 Estrutura de Arquivos Principais

```text
├── app.js                                 # Ponto de entrada cPanel Phusion Passenger
├── Dockerfile                             # Build multi-stage Node.js 20 LTS Alpine
├── docker-compose.yml                     # Orquestração App + Banco + Redis
├── package.json                           # Dependências e scripts de execução
├── prisma/
│   └── schema.prisma                      # Modelagem PostgreSQL / MySQL (Tenants, Faturas, Templates)
├── scripts/
│   ├── cron-runner.js                     # Fallback para Cron Job do cPanel
│   └── seed.ts                            # Carga de Planos SaaS, Tenants e Templates Meta
├── src/
│   ├── config/                            # Validações Zod de ambiente (.env), DB e Redis
│   ├── controllers/                       # Controladores dos Webhooks Meta, Mercado Pago e Faturas
│   ├── middlewares/                       # Validação HMAC-SHA256 (Meta) e x-signature (Mercado Pago)
│   ├── queues/                            # Filas BullMQ e Workers assíncronos
│   ├── routes/                            # Rotas de API, Webhooks e Faturas Públicas
│   ├── services/                          # Clientes oficiais Meta Graph API e Mercado Pago SDK
│   ├── views/                             # Templates SSR EJS da Fatura Pública e Dashboard
│   └── public/                            # JavaScripts globais (SweetAlert2, Toastify, Drawer)
├── app/Modules/                           # Módulos Laravel (WhatsApp, Gateways Mercado Pago, Tenancy)
└── docs/                                  # Guias passo a passo de deploy e auditoria
```

---

## 💻 Como Rodar o Projeto

### Modo 1: Desenvolvimento Local com SQLite (Acompanhamento Rápido)
Para rodar localmente sem necessidade de configurar servidor MySQL/MariaDB:
```bash
# 1. Configurar variáveis de ambiente para SQLite local (já pré-configurado no .env)
# DB_CONNECTION=sqlite
# DB_DATABASE=database/database.sqlite

# 2. Executar migrações locais no SQLite
php artisan migrate

# 3. Executar o servidor local de desenvolvimento
php artisan serve
```
> **⚠️ TRAVA DE SEGURANÇA EM PRODUÇÃO:** O arquivo `config/database.php` possui uma trava mandatória de segurança que aborta imediatamente a execução se `APP_ENV=production` e `DB_CONNECTION=sqlite`. Em servidores remotos de produção, utilize **estritamente MySQL ou MariaDB**!

### Modo 2: Deploy no cPanel / WHM (Hospedagem Compartilhada)
1. Suba os arquivos para a sua hospedagem cPanel.
2. No painel "Setup Node.js App", defina o arquivo de inicialização como `app.js`.
3. Configure o banco MariaDB/MySQL nativo do cPanel no `.env` (`DB_CONNECTION=mysql`).
4. Configure um Cron Job no cPanel a cada 1 minuto chamando:
   ```bash
   * * * * * cd /home/usuario/public_html && node scripts/cron-runner.js >> storage/logs/cron.log 2>&1
   ```
   *(Executa a régua de cobrança `php artisan billing:process-schedule` de forma autônoma sem exigir Redis persistente)*.

### Modo 3: Deploy via Docker Compose (Produção Completa)
Com apenas um comando você inicializa a aplicação completa (PHP 8.4-FPM + Nginx, MariaDB 10.11 e Redis 7):
```bash
docker compose up -d --build
```
Acesse `http://localhost` (Nginx + PHP) ou `http://localhost:3000` (Node Runner).

---

## ⚙️ Variáveis de Ambiente Essenciais (`.env`)

```ini
# AMBIENTE & PORTA
PORT=3000
NODE_ENV=production
APP_URL=https://cobranca.kdkhost.com.br

# WHATSAPP CLOUD API OFICIAL (META)
META_GRAPH_VERSION=v21.0
META_PHONE_NUMBER_ID=seu_phone_number_id_aqui
META_WABA_ID=seu_waba_id_aqui
META_ACCESS_TOKEN=seu_system_user_token_permanente_aqui
META_APP_SECRET=seu_app_secret_aqui
META_WEBHOOK_VERIFY_TOKEN=kdkhost_meta_webhook_secret_2026

# MERCADO PAGO SDK v2
MERCADOPAGO_ACCESS_TOKEN=APP_USR-seu-access-token-aqui
MERCADOPAGO_PUBLIC_KEY=APP_USR-sua-public-key-aqui
MERCADOPAGO_WEBHOOK_SECRET=seu-secret-webhook-mercadopago

# BANCO DE DADOS (MySQL / PostgreSQL)
DATABASE_URL="mysql://usuario:senha@localhost:3306/kdkhost_whatsapp"

# CACHE & FILAS (Redis / BullMQ)
REDIS_URL="redis://127.0.0.1:6379"
```

---

## 👨‍💻 Desenvolvedor & Suporte

- **Autor:** Marcelo Brad (Rio de Janeiro - RJ)
- **Email:** `marcelobradrj@gmail.com` / `contato@kdkhost.com.br`
- **WhatsApp Oficial:** [+55 (21) 98132-5441](https://wa.me/5521981325441)
- **Site:** [KDKhost Soluções Web](https://kdkhost.com.br)
- **Financeiro:** [Painel Financeiro KDKhost](https://financeiro.kdkhost.com.br)

*Todos os direitos reservados. Software de uso restrito e comercial da KDKhost.*

### Frontend público

```bash
cd frontend
npm install
npm run build
```

## Deploy

Fluxo padrão de publicação:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan view:cache
php artisan route:cache
php artisan storage:link --force
```

Documentação complementar:

- [docs/DEPLOY.md](docs/DEPLOY.md)
- [docs/CPANEL_DEPLOY.md](docs/CPANEL_DEPLOY.md)

---

## Módulos principais

- Cobranças e recorrência
- CRM administrativo
- WhatsApp Cloud API Oficial da Meta (Graph API v21.0)
- Integração e Hospedagem aaPanel (Websites, PHP-FPM, Bancos de Dados, Backups e Arquivos)
- API pública
- Tenancy e planos: gestão de planos SaaS, limites por plano, login supervisionado de tenants, página de detalhes do tenant (/admin/tenancy/tenants/{id}) reformulada em abas premium com KPIs em tempo real, instâncias WhatsApp com indicador luminoso, régua de cobrança, histórico de faturas e módulo integrado de afiliados.
- Programa de Afiliados e Divulgação: central administrativa completa (/admin/affiliates) com gráficos KPIs interativos em Chart.js (cliques x conversões, comissões diárias), rastreamento em tempo real de cliques e visitas com detecção de conversão (CR%), regras globais e individuais de ganhos/comissões (% ou valor fixo em R$, recorrência em todas as faturas, 1º pagamento ou vitalícia), regras de descontos de incentivo para novos compradores (% ou valor fixo, 1ª mensalidade ou contínuo), gerenciamento de saques PIX com nota/comprovante e aprovação de comissões com 1 clique via AJAX e SweetAlert2.
- Templates e notificações
- Gateways de pagamento
- Analytics
- Marketplace e addons
- PWA
- Segurança, WAF e trilhas de auditoria

## Gateways suportados

- Mercado Pago
- Efí Pro
- SumUp

Cada tenant pode operar com configuração própria, ambiente sandbox/produção, regras de taxas, métodos de pagamento, webhooks e políticas de segurança.

## Recursos de WhatsApp

- envio automatizado de cobrança com QR Code e PIX Copia e Cola
- fila inteligente com proteção anti-ban e diretrizes oficiais da Meta
- controle de cadência: Modo Ultra Seguro (Meta), Moderado e Acelerado (por conta e risco)
- gestão de tetos e permissões globais no painel administrativo
- painel do inquilino (tenant) com ajuste de cadência e termo de ciência de risco
- cooldown por destinatário, mensagem e campanha
- janela comercial (08:00 às 20:00) com reagendamento automático
- warmup progressivo de chips novos
- webhook seguro
- opt-out automático
- envio manual rápido no painel operacional com seleção de cliente, templates dinâmicos, interpolação de variáveis, edição direta de destinatário/mensagem e preview em tempo real com SweetAlert2 e Toast
- dashboard operacional anti-ban com métricas em tempo real

### Meta WhatsApp Cloud API Oficial (Graph API v21.0)

O sistema opera **exclusivamente com a API Oficial da Meta** (Cloud API Graph API v21.0) para toda a mensageria e automação do WhatsApp, garantindo conformidade total, entrega garantida e zero risco de bloqueio.

**Configuração no `.env`:**

```bash
# Meta WhatsApp Cloud API Oficial (Graph API v21.0)
META_GRAPH_VERSION=v21.0
META_PHONE_NUMBER_ID=seu_phone_number_id_aqui
META_WABA_ID=seu_waba_id_aqui
META_ACCESS_TOKEN=seu_system_user_token_permanente_aqui
META_APP_SECRET=seu_app_secret_aqui
META_WEBHOOK_VERIFY_TOKEN=seu_verify_token_aqui
```

**Principais Recursos da API Oficial da Meta:**
- **Webhooks Oficiais com Handshake:** Verificação de token no GET (`hub.challenge`) e assinatura criptográfica HMAC-SHA256 no POST (`x-hub-signature-256`).
- **Janela de 24 Horas:** Envio livre de sessão dentro de 24h e disparo automático via templates oficiais homologados fora da janela.
- **Templates de Cobrança com Pix e Link:** Envio aprovado com parâmetros dinâmicos de nome, fatura, vencimento, valor e código Pix Copia e Cola.
- **Disparo de Mídias e Documentos:** Envio oficial de imagens, áudios e PDFs de faturas/contratos.
- **Sem Risco de Banimento:** Comunicação autorizada pela Meta com monitoramento de qualidade da linha (Quality Rating GREEN).

---

## 🗄️ Estratégia de Banco de Dados: Local vs Remoto

- **Ambiente Local (Desenvolvimento):**
  - Utiliza **SQLite** (`DB_CONNECTION=sqlite`, `DB_DATABASE=database/database.sqlite`).
  - Permite rodar rapidamente em máquina local sem necessidade de servidor de banco de dados instalado.
- **Ambiente Remoto (Servidor de Produção):**
  - O uso de SQLite é **estritamente proibido** em produção (protegido por trava arquitetural no `config/database.php`).
  - Em servidores remotos (cPanel, VPS, Ubuntu, Docker, aaPanel), utilize **obrigatoriamente MySQL 8.0+ ou MariaDB 10.6+**:
    ```bash
    APP_ENV=production
    DB_CONNECTION=mysql
    DB_HOST=127.0.0.1
    DB_PORT=3306
    DB_DATABASE=nome_do_banco_producao
    DB_USERNAME=usuario_mysql
    DB_PASSWORD=senha_forte_mysql
    ```

## Tema e UI

O administrativo segue:

- AdminLTE 4
- Bootstrap 5
- dark/light centralizado
- design system reutilizável em `resources/views/components/ui`

Documentação:

- [docs/THEME_SYSTEM.md](docs/THEME_SYSTEM.md)
- [docs/UI_STANDARD.md](docs/UI_STANDARD.md)

## Addons e marketplace

O sistema possui suporte a addons com integração ao layout principal, permissões, menus, tema e assets.

Documentação:

- [docs/ADDONS.md](docs/ADDONS.md)
- [docs/ADDON_UI_STANDARD.md](docs/ADDON_UI_STANDARD.md)
- [docs/ADDON_SECURITY_STANDARD.md](docs/ADDON_SECURITY_STANDARD.md)
- [docs/ADDON_DEVELOPMENT_GUIDE.md](docs/ADDON_DEVELOPMENT_GUIDE.md)

## Multi-tenancy

O isolamento entre tenants usa `tenant_id`, escopos, middlewares e camadas específicas de tenancy.

Recursos recentes para membros (tenants):
- **Baixa Manual de Faturas Individuais, Parceladas e Antecipadas**: recurso completo de quitação manual integrado tanto no painel do lojista (`/painel/faturas`) quanto no painel administrativo (`/admin/billing/invoices`). Permite:
  - Dar baixa individual com escolha do meio de pagamento (Pix, Dinheiro, Cartão de Crédito/Débito, Transferência Bancária, Boleto ou Outro), data do pagamento retroativa/personalizada, observações internas e envio opcional de recibo/agradecimento via WhatsApp.
  - Baixa inteligente de parcelamento com detecção automática de parcelas antecipadas vinculadas ao mesmo carnê ou contrato, permitindo quitar com 1 clique a fatura atual juntamente com as parcelas já antecipadas (com totalizadores e valores calculados em tempo real), ou quitar todas as parcelas em aberto.
  - Baixa em lote (bulk action) para liquidar múltiplas faturas ou parcelas selecionadas de uma só vez, cancelando automaticamente quaisquer envios de cobrança agendados no WhatsApp para evitar cobranças indevidas.
  - Experiência 100% fluida em AJAX, com modais reativos, SweetAlert2 para confirmações e Toastify para alertas visuais, sem `alert()` nativo do navegador.
- **Cobrança Avulsa para Todas as Contas Tenants (À Vista e Parcelada / Carnê)**: funcionalidade integrada na tela de Faturas (`/painel/faturas`), permitindo que qualquer tenant gere faturas avulsas pontuais ou parceladas (carnês de até 24 parcelas mensais), com simulação dinâmica em tempo real (Live Preview), cálculo opcional de juros simples mensal, integração com gateways de pagamento (Mercado Pago, Efí Pro, SumUp), envio imediato opcional via WhatsApp para o cliente e agendamento automático na régua de cobrança. Interface moderna com SweetAlert2, Toastify e zero alerts nativos.
- **Checkout Transparente Seguro do Painel e Vencimento Preciso nos Disparos**: garantia de que toda cobrança gerada e enviada via WhatsApp/e-mail para os clientes utilize exclusivamente o link seguro de checkout transparente do próprio sistema (`/fatura/{webhook_token}`) em vez de redirecionar para links externos ou tickets brutos de gateways de pagamento (ex: Mercado Pago). O link do ticket original fica preservado internamente no histórico e nos metadados, enquanto a experiência do cliente final permanece 100% blindada e padronizada no painel. Além disso, o disparo imediato de cobranças foi ajustado para resolver inteligentemente a data exata de vencimento cadastrada: se a cobrança é criada com vencimento futuro (ex: para amanhã ou dias seguintes), a mensagem notifica a data real de vencimento e utiliza o template correto (`cobranca-gerada` / `-1d` / `-3d`), sem disparar indevidamente o template de vencimento de hoje.
- **Envio Manual de Mensagens WhatsApp no Sidebar**: tela dedicada (`/painel/whatsapp/manual`) e item de menu no sidebar sob **Comunicação > WhatsApp** logo acima de **Envios Agendados**, com autocomplete de clientes, autopreenchimento de templates, atalhos de tags, e **Preview Autêntico do WhatsApp** (wallpaper doodle, balão verde oficial com rabicho/tail, horário e duplo check azul `✓✓`).
- **Dashboard Analítica com KPIs e Gráficos Animados**: 4 KPIs consolidados em tempo real (Receita do Mês, Clientes Ativos, Disparos WhatsApp e Taxa de Adimplência), 2 gráficos dinâmicos animados com Chart.js (fluxo diário de mensagens e faturamento diário) e widget de **Saúde da Conta (Health Score 0-100)** com checklist interativo dos 4 pilares operacionais.
- **Notificações WhatsApp Transacionais Confiáveis**: envio imediato de aviso de cancelamento de contratação de pacote pelo gestor e envio automático de confirmação/agradecimento de pagamento assim que identificado pelo gateway de pagamento (Mercado Pago, Asaas, Efi, Pix), com cancelamento automático de cobranças pendentes já agendadas.
- **Correção e Fixação de Layout (Sidebar e Rodapé)**: barra lateral fixa no desktop que nunca rola junto com a página, rolagem independente interna no menu e rodapé com sticky footer flexbox que fica cravado na base em páginas curtas e sobe naturalmente no final do conteúdo em páginas longas sem descolar.
- **Novos Tipos de Vencimento de Pacotes**: suporte completo a planos e pacotes comerciais (`/painel/pacotes`) com ciclos Semanal (7d), Quinzenal (15d), Mensal (30d), Bimestral (60d), Trimestral (90d), Semestral (180d), Anual (365d), Bienal (730d), Trienal (1095d), **Vitalício** (0 - pagamento único) e **Permuta** (-1 - parceria/cortesia).
- **Conformidade Bancária Febraban: Cobrança Exclusiva em Dias Úteis e Bloqueio de Suspensão no WHMCS em Fins de Semana e Feriados**:
  - Quando a opção "Pular finais de semana e feriados bancários" (`skip_weekends_holidays`) está ativa:
    - Todas as faturas (avulsas, carnês, planos ou vindas do WHMCS) têm o vencimento (`due_date`) ajustado automaticamente para o primeiro dia útil subsequente caso caiam em sábado, domingo ou feriado bancário nacional.
    - Faturas pendentes existentes cadastradas para finais de semana ou feriados são automaticamente movidas para o próximo dia útil subsequente ao salvar as configurações de cobrança ou durante o ciclo da régua, sincronizando também os disparos agendados no WhatsApp e e-mail.
    - Disparos de cobrança nunca são enviados em fins de semana ou feriados; mensagens pendentes são reagendadas formalmente para a manhã do próximo dia útil.
    - Bloqueio de suspensão no WHMCS: a rotina de automação (`WhmcsServiceAutomation`) nunca suspende serviços em finais de semana ou feriados bancários nacionais.
    - Prorrogação no próprio WHMCS via API: ao sincronizar faturas do WHMCS que caem em dias não úteis, o sistema atualiza a `duedate` na fatura (`UpdateInvoice`) e a `nextduedate` no serviço (`UpdateClientProduct`) no próprio WHMCS, impedindo nativamente que o cron do WHMCS tente suspender a conta.
    - Reversão ativa no WHMCS: se o WHMCS suspender um serviço em dia não útil ou com fatura em aberto com vencimento em dia útil, o sistema executa `ModuleUnsuspend` automaticamente para restaurar o serviço.
    - Hook oficial para WHMCS: script pronto em `app/Modules/WhmcsIntegration/Resources/whmcs-hooks/protect_weekends_holidays.php` para instalação na pasta `/includes/hooks/` do WHMCS, interceptando o evento `PreModuleSuspend` e cancelando suspensões em fins de semana e feriados nacionais.
- **Integração WHMCS com Sincronização Automática via Cron (5 minutos) e Prevenção Total de Cobranças Indevidas**:
  - Sincronização automática em segundo plano a cada 5 minutos (`whmcs.auto_sync` via `CronManager`), atualizando clientes, serviços e faturas vindas do WHMCS (incluindo status `Unpaid`, `Paid`, `Cancelled` e `Refunded`).
  - Proteção total contra cobranças indevidas: o sistema valida o status tanto no banco de dados local quanto diretamente no WHMCS (inclusive em tempo real antes de qualquer envio de WhatsApp ou e-mail).
  - Se a fatura estiver **cancelada**, **paga** ou com status **estornado (refunded)** no WHMCS ou no sistema local: todos os disparos agendados no WhatsApp e na fila de e-mails são cancelados imediatamente, bloqueando qualquer mensagem ao cliente.
  - Bloqueio imediato em envios manuais e via API para faturas pagas, canceladas ou estornadas, com feedback reativo via SweetAlert2 e Toastify.
- **Padronização Visual Dark/Light**: temas unificados e compatíveis com Bootstrap 5 e AdminLTE 4 em todas as seções operacionais (incluindo aaPanel Servers `/painel/aapanel/servers`).
- **Padronização Global de Tabelas e DataTables Responsivos com Exportação**:
  - Unificação completa de todas as listagens, tabelas e DataTables do sistema (painel administrativo e painel do tenant) com a classe `app-datatable` e suporte nativo ao DataTables Responsive.
  - Barra de ferramentas de exportação padronizada estritamente com os 5 botões oficiais: `[CSV, Excel, PDF, Imprimir, Colunas]`.
  - Colunas de ações sempre diretas com ícones compactos (`p-1 px-2` em flex-nowrap) e visíveis em todos os dispositivos (`all text-end no-export`), sem menus dropdown de 3 pontinhos que quebram em telas móveis.
  - Breakpoints responsivos sem distorção ou quebra de layout: colunas críticas visíveis no celular (`all`), intermediárias no tablet (`min-tablet`) e secundárias no desktop (`desktop`).
  - Totalmente integrado ao SweetAlert2 para confirmações e exclusões, Toastify para notificações e AJAX para operações assíncronas sem reload de página.
- **Monitor de Webhooks Enterprise e Diagnóstico de Gateways**: painel dedicado (`/admin/webhook-monitor`) com observabilidade em tempo real dos webhooks recebidos de Mercado Pago, Efí Pro e SumUp. Inclui status de saúde com dot pulsante, cálculo de taxa de sucesso e erros 24h, cards com métricas individuais de cada provedor, tabela responsiva `app-datatable` com exportação (`[CSV, Excel, PDF, Imprimir, Colunas]`), filtros avançados por provedor/status e busca textual, além de modal para inspeção detalhada de payloads JSON e diagnósticos de erro com cópia rápida para o clipboard e zero alerts nativos.
- **Programa de Afiliados Enterprise com Moderação, Notificações WhatsApp/Email e Split Mercado Pago**:
  - **Inscrição do Cliente Tenant (`/painel/afiliado`)**: tenants participam do programa registrando-se pelo próprio painel do cliente, submetendo canais de divulgação, dados de PIX e Collector ID do Mercado Pago.
  - **Status e Estados de Inscrição**: suporte aos estados `pending` (em análise), `active` (aprovado/operante), `rejected` (recusado com motivo) e pendência com instruções de correção sem duplicar cadastros.
  - **Moderação Completa no Admin (`/admin/affiliates` e `/admin/tenancy/tenants/{id}#tab-affiliate`)**: o administrador aprova, rejeita ou define pendências com 1 clique utilizando modais com SweetAlert2, ajustando percentuais de comissão e parâmetros de Split Mercado Pago.
  - **Notificações Automáticas Multi-Canal (WhatsApp e E-mail)**: a cada alteração de status (aprovação, rejeição ou pendência), o sistema despacha simultaneamente e-mail transacional e mensagem formatada no WhatsApp do tenant com orientações, links e cupons.
  - **Split Automático Direto na Conta do Mercado Pago**: divisão automática de pagamentos no checkout (PIX e cartão) com aplicação direta de comissões na conta Mercado Pago do afiliado parceiro (`application_fee` / `marketplace_fee`), registrando a comissão imediatamente como liquidada (`status = 'paid'`).
  - **Links Inteligentes `/a/{slug}` e Desconto na 1ª Compra**: direciona leads para a página de prospecção já com cupom promocional pré-carregado no cadastro e desconto automático na primeira mensalidade.
  - **Painel com KPIs e Gráficos Interativos**: métricas de cliques, conversões, faturamento gerado e controle de saques PIX para comissões manuais, com suporte a Dark e Light Mode unificado.
- **Histórico Unificado de Cobrança e Disparos Automáticos Robustos Multi-Tenant**:
  - **Histórico Centralizado de Cobrança (`/painel/historico-cobranca`)**: visualização completa para o tenant contendo todos os disparos da régua (`billing_dispatches`) — agendados, enviados, falhas e cancelados —, além de mensagens avulsas diretas via WhatsApp (recibos, confirmações de pagamento, avisos) e e-mails de cobrança (`email_messages`), com resolução unificada de `tenant_id` por herança de cliente e instância.
  - **Garantia de Disparos Automáticos Contínuos**: correção da régua de cobrança em `BillingDispatcher` para que faturas com vencimento no próprio dia (offset `0d`) sejam disparadas mesmo se criadas após o horário padrão matinal, remoção de bloqueios indevidos em envios manuais aos fins de semana e agendamento automático instantâneo em criações via pacotes (`TenantPackageService`), calendário (`CalendarController`) e faturas avulsas (`InvoiceController`).
  - **Herança Automática de Tenant em Faturas**: preenchimento retroativo e em tempo de criação de `invoices.tenant_id = customers.tenant_id` para faturas que não tinham tenant explicitado, garantindo visibilidade total ao lojista.

- **Templates WhatsApp 100% Editáveis, Dinâmicos & White Label Completo**:
  - Eliminação total de mensagens com textos estáticos/hardcoded no código PHP.
  - Catálogo abrangente com 18 modelos padrão cobrindo todo o ciclo: Réguas de Cobrança (-3d, -1d, 0d, +1d, +3d, +7d, +15d), Boletos, Confirmações de Pagamento (Baixa), Pix Copia e Cola, Emissão/Assinatura de Contratos, Gestão de Pacotes/Assinaturas, Notificações Gerais e Suporte.
  - **White Label por Herança & Copy-On-Write**: os modelos padrão são compartilhados globalmente com todos os usuários como base homologada. Quando qualquer usuário ou tenant personaliza um template, o sistema cria automaticamente uma versão exclusiva para o perfil dele (armazenando vínculo `parent_id`), mantendo o modelo global e os outros usuários 100% intactos.
  - **Reversão com 1 Clique**: botão "Restaurar Padrão Global" permite que o tenant remova sua personalização a qualquer momento e volte a herdar o modelo compartilhado da plataforma.
  - **Mensagem Única com Imagem do QR Code**: cobranças via WhatsApp enviam a imagem do QR Code PIX com o texto completo do template formatado diretamente na legenda (`caption`) da imagem, garantindo que o cliente receba apenas 1 mensagem integrada com visualização escaneável e dados de pagamento.
  - **Resolução Inteligente de Templates**: tenant e admin seguem os templates oficiais de cobrança com base na data de vencimento (`vencimento-hoje`, `lembrete-1d-antes`, `lembrete-3d-antes`, `cobranca-gerada`, `atraso-1d`), isolando o template de boleto bancário exclusivamente para faturas com linha digitável.
  - Visualização ao vivo realista (Mockup de Smartphone WhatsApp) com renderização de negrito (`*`), itálico (`_`), tachado (`~`), blocos monoespaçados (` ``` `) e quebras de linha reais.
  - Interface no Admin e no Tenant com clonagem de templates, categorização com badges, filtros rápidos e inserção de variáveis por atalho em 1 clique.
  - Regra de Integridade: nenhuma mensagem é disparada pelo sistema sem um template válido e ativo selecionado.
- **Templates de E-mail 100% Personalizados com White Label e Identidade Visual do Tenant**:
  - **Identidade Visual Dinâmica**: renderização automática da logomarca (`logo_url`), cor primária (`primary_color`) e cor de destaque/secundária (`accent_color`) configuradas no painel do tenant (`tenant_email_configs` e configurações de WhiteLabel).
  - **Cabeçalho e Botões Customizados**: os e-mails transacionais e de régua de cobrança aplicam as cores da marca do tenant no cabeçalho, gradientes e botão CTA de pagamento, com injeção automática da logo sobre o título.
  - **Compatibilidade Retroativa e Variáveis Dinâmicas**: templates legados no banco têm suas cores estáticas e cabeçalhos adaptados dinamicamente em tempo de renderização, além do suporte nativo a variáveis `{cor_primaria}`, `{cor_destaque}`, `{logo_empresa}` e `{logo_html}`.
  - **Visualização Fiel no Histórico**: modais de histórico de cobrança e histórico de e-mails passam a renderizar os e-mails com a logo e paleta de cores reais do lojista.
- **Visualização Rápida no Dashboard Administrativo (`/admin/dashboard`)**: adição de botões de ação com modais de visualização rápida em "Últimas faturas" (detalhes do cliente, valor, status, vencimento, link de pagamento com cópia e atalho para a fatura completa) e "Últimas mensagens" (destinatário, texto do disparo, data/hora e diagnóstico de falhas sem reload de página).

Documentação:

- [docs/TENANCY.md](docs/TENANCY.md)

## Segurança

O projeto inclui:

- logs operacionais e auditoria
- WAF
- proteção de webhooks
- hardening de uploads
- políticas anti-flood
- trilha de alterações sensíveis

Documentação:

- [docs/SECURITY.md](docs/SECURITY.md)
- [SECURITY_HARDENING_REPORT.md](SECURITY_HARDENING_REPORT.md)

## Testes

```bash
php artisan test
```

Para smoke checks rápidos:

```bash
php artisan view:cache
php artisan route:cache
```

## Observação importante

Este repositório está padronizado para Laravel 13 com PHP 8.4. Sempre que documentação, mensagem de console ou material de apoio divergir disso, o padrão correto do projeto é:

- Laravel 13
- PHP 8.4
- MariaDB/MySQL
- AdminLTE 4 no admin
- React + TypeScript no frontend público

## Licença

Software proprietário. Todos os direitos reservados.
