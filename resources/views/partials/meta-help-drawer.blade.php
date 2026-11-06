{{-- Central de Ajuda & Passo a Passo: Personalização, Configuração, Utilização e Outros --}}
{{-- Menu Deslizante da Direita para a Esquerda (Offcanvas) --}}

<div class="offcanvas offcanvas-end shadow-lg" tabindex="-1" id="metaHelpDrawer" aria-labelledby="metaHelpDrawerLabel" style="width: 660px; max-width: 95vw; z-index: 1085;">
    <div class="offcanvas-header border-bottom py-3 px-4" style="background: linear-gradient(135deg, rgba(13, 110, 253, 0.08), rgba(25, 135, 84, 0.08));">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle d-flex align-items-center justify-content-center text-white" style="width: 44px; height: 44px; background: linear-gradient(135deg, #0d6efd, #198754); box-shadow: 0 4px 12px rgba(13, 110, 253, 0.25);">
                <i class="bi bi-whatsapp fs-4"></i>
            </div>
            <div>
                <div class="d-flex align-items-center gap-2">
                    <h5 class="offcanvas-title mb-0 fw-bold" id="metaHelpDrawerLabel">Guia do Sistema & Meta WhatsApp</h5>
                    <span class="badge bg-success-subtle text-success border border-success-subtle small">Graph API v21.0</span>
                </div>
                <small class="text-muted d-block mt-1">Instruções passo a passo de personalização, configuração, utilização e boas práticas</small>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
    </div>

    {{-- Barra de Busca e Navegação Rápida --}}
    <div class="px-4 pt-3 pb-2 border-bottom bg-body-tertiary">
        <div class="position-relative mb-2">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" id="metaHelpSearch" class="form-control form-control-sm ps-5 rounded-pill" placeholder="Buscar qualquer item (ex: perfil, token, delay, pix, white-label, envio manual)...">
        </div>
        
        <div class="d-flex gap-1 overflow-x-auto pb-1 meta-help-nav-pills" style="white-space: nowrap; scrollbar-width: thin;">
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill active py-0 px-2" data-nav-topic="meta-overview">Visão Geral</button>
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-0 px-2" data-nav-topic="meta-customization">Personalização</button>
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-0 px-2" data-nav-topic="meta-configuration">Configuração</button>
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-0 px-2" data-nav-topic="meta-usage">Utilização</button>
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-0 px-2" data-nav-topic="meta-anti-ban">Anti-Ban</button>
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-0 px-2" data-nav-topic="meta-others">Outros & Dúvidas</button>
        </div>
    </div>

    <div class="offcanvas-body p-4" id="metaHelpDrawerBody" style="scroll-behavior: smooth;">

        {{-- ========================================================================= --}}
        {{-- PILAR 1: VISÃO GERAL                                                      --}}
        {{-- ========================================================================= --}}
        <section class="meta-help-topic mb-4" id="topic-meta-overview" data-keywords="visao geral meta api oficial whatsapp graph v21 introducao beneficios sem qr code">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="badge bg-primary px-2 py-1"><i class="bi bi-stars me-1"></i>Visão Geral</span>
                <h5 class="mb-0 fw-bold">WhatsApp Cloud API Oficial da Meta</h5>
            </div>
            
            <p class="text-body-secondary small">
                Nossa plataforma opera <strong>100% integrada à infraestrutura oficial da Meta</strong> através da <strong>Graph API v21.0</strong>. Este é o canal corporativo homologado globalmente pelo WhatsApp, sem uso de navegadores emulados ou bibliotecas não oficiais.
            </p>

            <div class="row g-2 mb-3">
                <div class="col-sm-6">
                    <div class="border rounded p-2 h-100 bg-body-tertiary">
                        <strong class="text-success d-flex align-items-center gap-1 small mb-1">
                            <i class="bi bi-shield-check"></i> Sem Risco de Banimento
                        </strong>
                        <span class="text-muted small">Os envios utilizam a rota oficial autenticada por Bearer Token da Meta. Seu número não é derrubado por desconexões forçadas.</span>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="border rounded p-2 h-100 bg-body-tertiary">
                        <strong class="text-primary d-flex align-items-center gap-1 small mb-1">
                            <i class="bi bi-phone-fill"></i> Independente de Aparelho
                        </strong>
                        <span class="text-muted small">Não precisa de celular conectado à tomada ou com Wi-Fi ligado. O envio e o webhook operam na nuvem da Meta 24/7 com 99.9% de SLA.</span>
                    </div>
                </div>
            </div>

            <div class="alert alert-light border small mb-0">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <strong>Endpoint Base Oficial:</strong>
                    <button class="btn btn-xs btn-outline-secondary py-0 px-2 btn-copy-meta-text" data-copy="https://graph.facebook.com/v21.0">Copiar</button>
                </div>
                <code class="text-primary">https://graph.facebook.com/v21.0/{PHONE_NUMBER_ID}/messages</code>
            </div>
        </section>

        <hr class="my-4">

        {{-- ========================================================================= --}}
        {{-- PILAR 2: PERSONALIZAÇÃO (PERFIL, FOTO, WHITE-LABEL, TEMPLATES)             --}}
        {{-- ========================================================================= --}}
        <section class="meta-help-topic mb-4" id="topic-meta-customization" data-keywords="personalizacao perfil foto avatar logotipo slogan white-label cores tema marca">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="badge bg-purple text-white px-2 py-1" style="background:#6f42c1;"><i class="bi bi-palette-fill me-1"></i>Personalização</span>
                <h5 class="mb-0 fw-bold">Instruções de Personalização</h5>
            </div>

            <p class="text-body-secondary small">
                Aprenda a personalizar o perfil da sua linha de WhatsApp na Meta, a marca visual do sistema e os modelos de mensagens:
            </p>

            {{-- 1. Perfil Oficial da Linha (Avatar, Nome, Slogan) --}}
            <div class="meta-help-subtopic border rounded p-3 mb-3 bg-body-tertiary" id="topic-customization-profile">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <strong class="text-primary small"><i class="bi bi-person-badge me-1"></i>1. Perfil da Linha de WhatsApp (Foto, Nome e Bio)</strong>
                    <span class="badge bg-secondary-subtle text-body border small">Meta Manager</span>
                </div>
                <p class="small text-muted mb-2"><strong>Como personalizar a foto e descrição comercial que o cliente vê no WhatsApp:</strong></p>
                <ol class="small ps-3 mb-2">
                    <li>Acesse o <a href="https://business.facebook.com/wa/manage/" target="_blank" rel="noopener">WhatsApp Manager (Gerenciador do WhatsApp)</a>.</li>
                    <li>No menu lateral esquerdo, clique em <strong>Ferramentas da conta</strong> &gt; <strong>Números de telefone</strong>.</li>
                    <li>Clique no ícone de <strong>Configurações (engrenagem)</strong> ao lado do seu número e acesse a aba <strong>Perfil</strong>.</li>
                    <li><strong>Foto de Perfil:</strong> Faça upload do logotipo da sua empresa (quadrado, mínimo 640x640px, PNG ou JPG).</li>
                    <li><strong>Descrição/Slogan:</strong> Insira a biografia da sua empresa (ex: <em>"Canal oficial de atendimento e faturamento KDKHost"</em>).</li>
                    <li><strong>Categoria e Site:</strong> Selecione o ramo da empresa e insira a URL do seu site. Clique em <strong>Salvar</strong>.</li>
                </ol>
                <div class="alert alert-info py-1 px-2 small mb-0">
                    <i class="bi bi-info-circle me-1"></i> No nosso painel, a foto e o nome sincronizam automaticamente ao salvar e testar a conexão!
                </div>
            </div>

            {{-- 2. Identidade Visual White-Label --}}
            <div class="meta-help-subtopic border rounded p-3 mb-3 bg-body-tertiary" id="topic-customization-whitelabel">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <strong class="text-primary small"><i class="bi bi-brush me-1"></i>2. Identidade Visual & White-Label do Painel</strong>
                    <span class="badge bg-secondary-subtle text-body border small">Painel / Tenant</span>
                </div>
                <p class="small text-muted mb-2"><strong>Como personalizar as cores e marca do sistema:</strong></p>
                <ul class="small ps-3 mb-0">
                    <li><strong>Logotipo e Favicon:</strong> No painel administrativo em <em>Configurações &gt; White-Label</em>, você pode fazer upload do logo da sua marca que aparecerá no topo do menu e na tela de login.</li>
                    <li><strong>Tema Claro / Escuro:</strong> O sistema possui alternador automático de modo escuro (Dark Mode) respeitando a preferência do operador.</li>
                    <li><strong>Domínio Próprio:</strong> Inquilinos corporativos podem apontar um subdomínio próprio (ex: <code>faturas.suaempresa.com.br</code>) com certificado SSL automático.</li>
                </ul>
            </div>

            {{-- 3. Personalização de Message Templates --}}
            <div class="meta-help-subtopic border rounded p-3 mb-3 bg-body-tertiary" id="topic-customization-templates">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <strong class="text-primary small"><i class="bi bi-card-text me-1"></i>3. Personalização de Modelos de Mensagem (Templates)</strong>
                    <span class="badge bg-secondary-subtle text-body border small">Templates</span>
                </div>
                <p class="small text-muted mb-2"><strong>Como deixar as mensagens de cobrança com a cara da sua empresa:</strong></p>
                <ul class="small ps-3 mb-0">
                    <li><strong>Cabeçalho com Imagem:</strong> Ao criar templates de cobrança na Meta, você pode definir cabeçalho do tipo <code>MÍDIA (Imagem)</code> para exibir a logomarca da sua empresa no topo da mensagem.</li>
                    <li><strong>Rodapé Institucional:</strong> Adicione uma linha de rodapé elegante como: <em>"Atendimento automático KDKHost &bull; Responda PARAR para sair"</em>.</li>
                    <li><strong>Saudação e Tom de Voz:</strong> Configure o texto como formal ou descontraído utilizando tags <code>{nome}</code> e <code>{primeiro_nome}</code>.</li>
                </ul>
            </div>
        </section>

        <hr class="my-4">

        {{-- ========================================================================= --}}
        {{-- PILAR 3: CONFIGURAÇÃO (API META, WABA, TOKEN, WEBHOOK)                    --}}
        {{-- ========================================================================= --}}
        <section class="meta-help-topic mb-4" id="topic-meta-configuration" data-keywords="configuracao meta waba phone id token permanente secret webhook setup api">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="badge bg-primary px-2 py-1"><i class="bi bi-gear-fill me-1"></i>Configuração</span>
                <h5 class="mb-0 fw-bold">Instruções de Configuração da Meta API</h5>
            </div>

            <p class="text-body-secondary small">
                Passo a passo individual para preencher cada campo de credencial da Meta WhatsApp Cloud API:
            </p>

            {{-- 1. Criar App --}}
            <div class="meta-help-subtopic border rounded p-3 mb-3 bg-body-tertiary" id="topic-meta-app-setup">
                <strong class="text-primary small d-block mb-1"><i class="bi bi-1-circle me-1"></i>1. Criar Aplicativo no Meta for Developers</strong>
                <p class="small text-muted mb-2">Acesse <a href="https://developers.facebook.com" target="_blank" rel="noopener">developers.facebook.com</a> &gt; <em>Meus Aplicativos &gt; Criar Aplicativo</em> &gt; Tipo <strong>Empresa / Outro</strong> &gt; Adicione o produto <strong>WhatsApp</strong>.</p>
            </div>

            {{-- 2. Token Permanente --}}
            <div class="meta-help-subtopic border rounded p-3 mb-3 bg-body-tertiary" id="topic-meta-system-user-token">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <strong class="text-warning-emphasis small"><i class="bi bi-key-fill me-1"></i>2. Token Permanente (System User Token)</strong>
                    <span class="badge bg-warning text-dark border small">Vital</span>
                </div>
                <div class="alert alert-danger py-1 px-2 small mb-2">
                    <strong>Importante:</strong> Nunca use o token temporário da tela inicial (ele expira em 24h).
                </div>
                <p class="small text-muted mb-2"><strong>Como gerar token que nunca expira:</strong></p>
                <ol class="small ps-3 mb-0">
                    <li>No <a href="https://business.facebook.com/settings/system-users" target="_blank" rel="noopener">Meta Business Suite &gt; Usuários do Sistema</a>, clique em <strong>Adicionar</strong> com perfil <strong>Administrador</strong>.</li>
                    <li>Clique em <strong>Atribuir Ativos</strong> e marque seu <em>Aplicativo</em> e sua <em>Conta do WhatsApp (WABA)</em> com <strong>Controle Total</strong>.</li>
                    <li>Clique em <strong>Gerar Novo Token</strong>, marque <em>Expiração: Nunca</em> e selecione as permissões <code>whatsapp_business_management</code> e <code>whatsapp_business_messaging</code>.</li>
                    <li>Copie o token longo (começa com <code>EAAG...</code>) e cole no campo <strong>Token Permanente</strong>.</li>
                </ol>
            </div>

            {{-- 3. Phone ID --}}
            <div class="meta-help-subtopic border rounded p-3 mb-3 bg-body-tertiary" id="topic-meta-phone-id">
                <strong class="text-primary small d-block mb-1"><i class="bi bi-telephone me-1"></i>3. Phone Number ID da Meta</strong>
                <p class="small text-muted mb-2">Código numérico de 15 a 17 dígitos fornecido pela Meta para a linha. No portal do desenvolvedor, vá em <em>WhatsApp &gt; Configuração da API &gt; Identificador do número de telefone (Phone number ID)</em> e copie o valor.</p>
            </div>

            {{-- 4. WABA ID --}}
            <div class="meta-help-subtopic border rounded p-3 mb-3 bg-body-tertiary" id="topic-meta-waba-id">
                <strong class="text-primary small d-block mb-1"><i class="bi bi-briefcase me-1"></i>4. WhatsApp Business Account ID (WABA ID)</strong>
                <p class="small text-muted mb-2">Identificador da sua conta comercial na Meta. Fica localizado diretamente abaixo do Phone Number ID na mesma tela de configuração da API, ou no topo do WhatsApp Manager.</p>
            </div>

            {{-- 5. App Secret --}}
            <div class="meta-help-subtopic border rounded p-3 mb-3 bg-body-tertiary" id="topic-meta-app-secret">
                <strong class="text-primary small d-block mb-1"><i class="bi bi-lock me-1"></i>5. Meta App Secret (HMAC-SHA256)</strong>
                <p class="small text-muted mb-2">Chave secreta para validação do cabeçalho de segurança <code>X-Hub-Signature-256</code> do webhook. Obtenha no Meta Developer em <em>Configurações do aplicativo &gt; Básico &gt; Chave Secreta do Aplicativo &gt; Mostrar</em>.</p>
            </div>

            {{-- 6. Webhook --}}
            <div class="meta-help-subtopic border rounded p-3 mb-3 bg-body-tertiary" id="topic-meta-webhook">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <strong class="text-success small"><i class="bi bi-arrow-repeat me-1"></i>6. Webhook Oficial & Verify Token</strong>
                    <span class="badge bg-success-subtle text-success border small">Tempo Real</span>
                </div>
                <p class="small text-muted mb-2">No menu do aplicativo da Meta em <em>WhatsApp &gt; Configuração &gt; Webhook</em>:</p>
                <ul class="small ps-3 mb-2">
                    <li><strong>URL de Retorno de Chamada:</strong> Cole <code>{{ url('/api/v1/webhook') }}</code></li>
                    <li><strong>Verificar Token:</strong> Cole <code>{{ config('whatsapp.meta_webhook_verify_token', env('META_WEBHOOK_VERIFY_TOKEN', 'kdkhost_meta_webhook_secret_2026')) }}</code></li>
                    <li>Clique em <strong>Verificar e Salvar</strong>.</li>
                    <li>Clique em <strong>Gerenciar campos</strong> e assine obrigatoriamente <code>messages</code> e <code>message_template_status_update</code>.</li>
                </ul>
            </div>
        </section>

        <hr class="my-4">

        {{-- ========================================================================= --}}
        {{-- PILAR 4: UTILIZAÇÃO (COBRANÇAS, ENVIO MANUAL, PIX, STATUS)                --}}
        {{-- ========================================================================= --}}
        <section class="meta-help-topic mb-4" id="topic-meta-usage" data-keywords="utilizacao como usar cobranca manual disparo fatura pix copia e cola wamid historico">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="badge bg-success px-2 py-1"><i class="bi bi-play-circle-fill me-1"></i>Utilização</span>
                <h5 class="mb-0 fw-bold">Instruções de Utilização no Dia a Dia</h5>
            </div>

            <p class="text-body-secondary small">
                Como utilizar as funcionalidades de cobrança e atendimento da plataforma na prática:
            </p>

            {{-- 1. Cobrança Automática com Pix --}}
            <div class="meta-help-subtopic border rounded p-3 mb-3 bg-body-tertiary" id="topic-usage-automatic-billing">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <strong class="text-primary small"><i class="bi bi-cash-coin me-1"></i>1. Disparo Automático de Cobrança com Pix</strong>
                    <span class="badge bg-secondary-subtle text-body border small">Automação</span>
                </div>
                <p class="small text-muted mb-2"><strong>Como o sistema cobra os clientes:</strong></p>
                <div class="small text-body-secondary mb-2">
                    Quando uma fatura é gerada pelo sistema (avulsa, recorrente ou via integração WHMCS):
                    <ol class="ps-3 mt-1 mb-0">
                        <li>O disparador seleciona o <strong>Message Template de Utilidade</strong> homologado.</li>
                        <li>Substitui automaticamente o nome do cliente, o valor e o vencimento da fatura.</li>
                        <li>Anexa a chave <strong>Pix Copia e Cola</strong> e o botão de acesso rápido à fatura.</li>
                        <li>Dispara respeitando a fila de cadência anti-ban e a janela comercial (08:00 às 20:00).</li>
                    </ol>
                </div>
            </div>

            {{-- 2. Envio Manual de Mensagem --}}
            <div class="meta-help-subtopic border rounded p-3 mb-3 bg-body-tertiary" id="topic-usage-manual-send">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <strong class="text-primary small"><i class="bi bi-send-plus me-1"></i>2. Envio Manual de Mensagens (Solução Rápida)</strong>
                    <span class="badge bg-secondary-subtle text-body border small">Operador</span>
                </div>
                <p class="small text-muted mb-2"><strong>Como disparar mensagem avulsa pelo painel:</strong></p>
                <ol class="small ps-3 mb-0">
                    <li>Acesse a tela <em>Envio Manual</em> ou clique no botão verde <em>"Nova mensagem manual"</em>.</li>
                    <li>Busque o cliente pelo nome, CPF ou WhatsApp para preencher os dados automaticamente.</li>
                    <li>Selecione o <strong>Modelo de Mensagem (Template)</strong> obrigatório.</li>
                    <li>Confira a pré-visualização no <strong>Simulador Realista de Smartphone</strong> na coluna direita.</li>
                    <li>Clique em <strong>Enviar Mensagem Oficial</strong>. A mensagem é enviada via Graph API com retorno do ID WAMID em tempo real.</li>
                </ol>
            </div>

            {{-- 3. Botão Copiar Chave Pix --}}
            <div class="meta-help-subtopic border rounded p-3 mb-3 bg-body-tertiary" id="topic-usage-pix-copy-paste">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <strong class="text-primary small"><i class="bi bi-qr-code me-1"></i>3. Botão "Copiar Chave Pix" no WhatsApp do Cliente</strong>
                    <span class="badge bg-secondary-subtle text-body border small">Experiência Pix</span>
                </div>
                <p class="small text-muted mb-2"><strong>Como o cliente realiza o pagamento:</strong></p>
                <div class="small text-body-secondary mb-0">
                    Ao receber a notificação no WhatsApp, o cliente vê um botão destacado <strong>"Copiar código"</strong>. Ao tocar nele, o código Pix Copia e Cola é copiado para a memória do celular. O cliente só precisa abrir o aplicativo do banco dele, clicar em <em>"Pix Copia e Cola"</em> e confirmar o pagamento. O webhook do gateway confirma e baixa a fatura automaticamente!
                </div>
            </div>

            {{-- 4. Status de Entrega WAMID --}}
            <div class="meta-help-subtopic border rounded p-3 mb-3 bg-body-tertiary" id="topic-usage-delivery-tracking">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <strong class="text-primary small"><i class="bi bi-check2-all me-1"></i>4. Status de Entrega & Auditoria (sent, delivered, read, failed)</strong>
                    <span class="badge bg-secondary-subtle text-body border small">Rastreamento</span>
                </div>
                <p class="small text-muted mb-2">Na tabela de histórico, acompanhe a evolução de cada mensagem pelo código <strong>WAMID</strong>:</p>
                <div class="d-flex flex-column gap-1 small">
                    <div><span class="badge bg-secondary me-1">sent</span> Mensagem recebida e processada pela infraestrutura da Meta.</div>
                    <div><span class="badge bg-info text-dark me-1">delivered</span> Entregue no celular do cliente (dois tracinhos cinzas).</div>
                    <div><span class="badge bg-success me-1">read</span> Mensagem lida e visualizada (dois tracinhos azuis).</div>
                    <div><span class="badge bg-danger me-1">failed</span> Falha no envio. Clique no ícone de detalhes para ler a mensagem de erro exata retornada pela Meta.</div>
                </div>
            </div>
        </section>

        <hr class="my-4">

        {{-- ========================================================================= --}}
        {{-- PILAR 5: PAINEL ANTI-BAN & CADÊNCIA CAMPO A CAMPO                         --}}
        {{-- ========================================================================= --}}
        <section class="meta-help-topic mb-4" id="topic-meta-anti-ban" data-keywords="anti-ban antiban politicas seguranca janela 24h opt in opt out descadastro safety delay cooldown">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="badge bg-warning text-dark px-2 py-1"><i class="bi bi-shield-lock-fill me-1"></i>Painel Anti-Ban</span>
                <h5 class="mb-0 fw-bold">Instruções Individuais dos Campos Anti-Ban</h5>
            </div>

            <p class="text-body-secondary small">
                Instruções detalhadas de cada campo e regra do painel de segurança operacional:
            </p>

            {{-- enabled --}}
            <div class="meta-help-subtopic border rounded p-3 mb-2 bg-body-tertiary" id="topic-safety-enabled">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <strong class="text-primary small"><i class="bi bi-shield-check me-1"></i>Proteção ativa (enabled)</strong>
                    <span class="badge bg-secondary-subtle text-body border small">Chave Geral</span>
                </div>
                <p class="small text-muted mb-0">Liga ou desliga todas as validações de segurança (janela comercial, delays e limites). Mantenha sempre <code>Sim</code> em produção.</p>
            </div>

            {{-- timezone --}}
            <div class="meta-help-subtopic border rounded p-3 mb-2 bg-body-tertiary" id="topic-safety-timezone">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <strong class="text-primary small"><i class="bi bi-clock me-1"></i>Timezone operacional (timezone)</strong>
                    <span class="badge bg-secondary-subtle text-body border small">Horário</span>
                </div>
                <p class="small text-muted mb-0">Fuso horário de referência para a janela comercial e reinício dos tetos diários. Padrão Brasil: <code>America/Sao_Paulo</code>.</p>
            </div>

            {{-- business hours --}}
            <div class="meta-help-subtopic border rounded p-3 mb-2 bg-body-tertiary" id="topic-safety-business_hours_enabled">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <strong class="text-primary small"><i class="bi bi-calendar-check me-1"></i>Janela Comercial (business_hours_enabled / start / end)</strong>
                    <span class="badge bg-secondary-subtle text-body border small">08:00 às 20:00</span>
                </div>
                <p class="small text-muted mb-0">Impede que cobranças automáticas sejam enviadas no meio da noite. Mensagens fora de horário são retidas na fila e disparadas no dia seguinte a partir das 08:00.</p>
            </div>

            {{-- stop keywords --}}
            <div class="meta-help-subtopic border rounded p-3 mb-2 bg-body-tertiary" id="topic-safety-stop_keywords">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <strong class="text-danger small"><i class="bi bi-slash-circle me-1"></i>Palavras de Parada / Opt-Out (stop_keywords)</strong>
                    <span class="badge bg-danger text-white border small">Regra Estrita</span>
                </div>
                <p class="small text-muted mb-0">Se o cliente responder <code>PARAR</code>, <code>SAIR</code>, <code>CANCELAR</code> ou <code>STOP</code>, o sistema desativa novos envios automáticos para respeitar as políticas da Meta e evitar denúncias de spam.</p>
            </div>

            {{-- human delay --}}
            <div class="meta-help-subtopic border rounded p-3 mb-2 bg-body-tertiary" id="topic-safety-human_delay_enabled">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <strong class="text-success small"><i class="bi bi-person-walking me-1"></i>Delay Humano Randômico (human_delay_min / max)</strong>
                    <span class="badge bg-success-subtle text-success border small">Anti-Bot</span>
                </div>
                <p class="small text-muted mb-0">Sorteia um intervalo variável (ex: de 12 a 32 segundos) entre mensagens para simular digitação humana e evitar detecção por periodicidade fixa.</p>
            </div>

            {{-- warmup --}}
            <div class="meta-help-subtopic border rounded p-3 mb-2 bg-body-tertiary" id="topic-safety-warmup_enabled">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <strong class="text-warning-emphasis small"><i class="bi bi-thermometer-half me-1"></i>Aquecimento Progressivo de Linhas Novas (Warm-up)</strong>
                    <span class="badge bg-warning text-dark border small">Números Novos</span>
                </div>
                <p class="small text-muted mb-0">Para linhas novas, escalona o volume diário (até 30 msgs/dia nos dias 1 a 3; até 80 msgs/dia nos dias 4 a 7; até 150 msgs/dia até o 15º dia) até atingir o limite pleno do Tier 1 oficial.</p>
            </div>

            {{-- cooldowns --}}
            <div class="meta-help-subtopic border rounded p-3 mb-2 bg-body-tertiary" id="topic-safety-recipient_cooldown_minutes">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <strong class="text-primary small"><i class="bi bi-hourglass-split me-1"></i>Cooldowns (Destinatário, Mensagem e Campanha)</strong>
                    <span class="badge bg-secondary-subtle text-body border small">Espaçamento</span>
                </div>
                <p class="small text-muted mb-0">Garante um intervalo mínimo (ex: 60 minutos) para que o mesmo cliente não receba avisos repetidos no mesmo dia.</p>
            </div>

            {{-- limits --}}
            <div class="meta-help-subtopic border rounded p-3 mb-2 bg-body-tertiary" id="topic-safety-limit_per_minute">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <strong class="text-primary small"><i class="bi bi-speedometer2 me-1"></i>Limites de Envio (Por minuto, hora e dia)</strong>
                    <span class="badge bg-secondary-subtle text-body border small">Tetos</span>
                </div>
                <p class="small text-muted mb-0">Controla a vazão máxima de mensagens por minuto (burst), por hora e por dia para manter a linha protegida dentro do Tier da Meta.</p>
            </div>

            {{-- risk score --}}
            <div class="meta-help-subtopic border rounded p-3 mb-2 bg-body-tertiary" id="topic-safety-risk_medium">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <strong class="text-danger small"><i class="bi bi-activity me-1"></i>Score de Risco & Bloqueio Preventivo (risk_block_above)</strong>
                    <span class="badge bg-danger-subtle text-danger border small">Autoproteção</span>
                </div>
                <p class="small text-muted mb-0">Se o score de risco ultrapassar o valor limite estipulado (ex: 85), o sistema pausa os disparos automaticamente para salvar a linha antes que ocorra restrição na Meta.</p>
            </div>

            {{-- qr code pix image --}}
            <div class="meta-help-subtopic border rounded p-3 mb-2 bg-body-tertiary" id="topic-safety-send_pix_qrcode_image">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <strong class="text-primary small"><i class="bi bi-qr-code me-1"></i>Enviar imagem do QR Code Pix (send_pix_qrcode_image)</strong>
                    <span class="badge bg-primary-subtle text-primary border small">Mídia Pix</span>
                </div>
                <p class="small text-muted mb-0">Gera e anexa a imagem do QR Code Pix além do código Copia e Cola em texto, facilitando o pagamento via câmera do celular.</p>
            </div>
        </section>

        <hr class="my-4">

        {{-- ========================================================================= --}}
        {{-- PILAR 6: OUTROS, RESOLUÇÃO DE DÚVIDAS & TENANTS                           --}}
        {{-- ========================================================================= --}}
        <section class="meta-help-topic mb-3" id="topic-meta-others" data-keywords="outros duvidas tenant instancia multi-tenant waba propria linha empresa erros resolucao">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="badge bg-secondary px-2 py-1"><i class="bi bi-question-diamond-fill me-1"></i>Outros & Dúvidas</span>
                <h5 class="mb-0 fw-bold">Multi-Tenant & Dúvidas Frequentes</h5>
            </div>

            {{-- Multi-Tenant --}}
            <div class="meta-help-subtopic border rounded p-3 mb-3 bg-body-tertiary" id="topic-tenant-instance-setup">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <strong class="text-primary small"><i class="bi bi-buildings me-1"></i>Configuração de Instância para Tenants</strong>
                    <span class="badge bg-secondary-subtle text-body border small">Empresas</span>
                </div>
                <p class="small text-muted mb-2">Cada empresa cadastrada no SaaS pode optar por:</p>
                <ul class="small ps-3 mb-0">
                    <li><strong>Linha Própria:</strong> Cadastra suas credenciais oficiais da Meta para ter seu próprio nome e logotipo verificado.</li>
                    <li><strong>Linha Compartilhada:</strong> Se deixar os campos em branco, o sistema usa a linha master do Superadmin automaticamente.</li>
                </ul>
            </div>

            {{-- Resolução de Erros Comuns --}}
            <div class="meta-help-subtopic border rounded p-3 mb-2 bg-body-tertiary" id="topic-others-troubleshooting">
                <strong class="text-primary small d-block mb-1"><i class="bi bi-wrench me-1"></i>Resolução de Erros Frequentes da Meta</strong>
                <ul class="small ps-3 mb-0">
                    <li><strong>Erro 190 (Token Expired):</strong> O token de acesso expirou. Gere um novo <em>Token de Usuário do Sistema</em> com expiração *"Nunca"*.</li>
                    <li><strong>Erro 131030 (Recipient not registered):</strong> O número do cliente não tem conta ativa no WhatsApp.</li>
                    <li><strong>Erro 132000 (Template not found):</strong> O nome do template informado no sistema não bate com o nome exato aprovado no WhatsApp Manager.</li>
                </ul>
            </div>
        </section>

    </div>

    {{-- Rodapé com links oficiais --}}
    <div class="offcanvas-footer border-top p-3 bg-body-tertiary d-flex align-items-center justify-content-between">
        <a href="https://developers.facebook.com/docs/whatsapp/cloud-api" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-book me-1"></i> Documentação Oficial Meta <i class="bi bi-box-arrow-up-right ms-1"></i>
        </a>
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="offcanvas">Fechar Guia</button>
    </div>
</div>

{{-- Estilos e Script Global do Menu Deslizante de Ajuda --}}
<style>
.meta-help-nav-pills .btn.active {
    background-color: var(--bs-primary) !important;
    color: #fff !important;
    border-color: var(--bs-primary) !important;
}
.meta-help-topic, .meta-help-subtopic {
    transition: all 0.35s ease;
    border-radius: 8px;
}
.meta-help-topic.highlighted, .meta-help-subtopic.highlighted {
    background-color: color-mix(in srgb, var(--bs-primary) 14%, transparent) !important;
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--bs-primary) 28%, transparent) !important;
    border-color: var(--bs-primary) !important;
    transform: scale(1.01);
}
.js-meta-help-trigger {
    cursor: pointer;
    transition: color 0.18s ease, transform 0.18s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.js-meta-help-trigger:hover {
    color: var(--bs-primary) !important;
    transform: scale(1.2);
}
</style>

<script>
(function() {
    window.openHelpDrawer = function(topicId) {
        const offcanvasEl = document.getElementById('metaHelpDrawer');
        if (!offcanvasEl) return;

        let offcanvas = null;
        if (typeof bootstrap !== 'undefined' && bootstrap.Offcanvas) {
            offcanvas = bootstrap.Offcanvas.getOrCreateInstance(offcanvasEl);
        }

        if (offcanvas) {
            offcanvas.show();
        } else {
            offcanvasEl.classList.add('show');
            offcanvasEl.style.visibility = 'visible';
            document.body.classList.add('modal-open');
        }

        if (topicId) {
            setTimeout(function() {
                window.scrollToHelpTopic(topicId);
            }, 320);
        }
    };

    window.scrollToHelpTopic = function(topicId) {
        if (!topicId) return;
        const cleanId = topicId.replace(/^#/, '').replace(/^topic-/, '');
        let target = document.getElementById('topic-' + cleanId) || document.getElementById(cleanId);
        
        if (!target && cleanId.startsWith('safety-')) {
            target = document.getElementById('topic-meta-anti-ban');
        }

        // Atualiza pílulas do topo
        document.querySelectorAll('.meta-help-nav-pills [data-nav-topic]').forEach(function(pill) {
            const navTopic = pill.getAttribute('data-nav-topic');
            if (navTopic === cleanId || 
                (cleanId.startsWith('safety-') && navTopic === 'meta-anti-ban') ||
                (cleanId.startsWith('customization-') && navTopic === 'meta-customization') ||
                (cleanId.startsWith('usage-') && navTopic === 'meta-usage')
            ) {
                pill.classList.add('active');
            } else {
                pill.classList.remove('active');
            }
        });

        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
            target.classList.add('highlighted');
            setTimeout(function() {
                target.classList.remove('highlighted');
            }, 3200);
        }
    };

    document.addEventListener('DOMContentLoaded', function() {
        document.addEventListener('click', function(e) {
            const trigger = e.target.closest('.js-meta-help-trigger, [data-help-topic]');
            if (!trigger) return;

            e.preventDefault();
            const topic = trigger.getAttribute('data-help-topic') || trigger.getAttribute('data-topic') || 'meta-overview';
            window.openHelpDrawer(topic);
        });

        document.querySelectorAll('.meta-help-nav-pills [data-nav-topic]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const topic = this.getAttribute('data-nav-topic');
                window.scrollToHelpTopic(topic);
            });
        });

        const searchInput = document.getElementById('metaHelpSearch');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const query = this.value.toLowerCase().trim();
                const topics = document.querySelectorAll('.meta-help-topic, .meta-help-subtopic');

                topics.forEach(function(section) {
                    if (!query) {
                        section.style.display = '';
                        return;
                    }
                    const text = section.textContent.toLowerCase();
                    const keywords = (section.getAttribute('data-keywords') || '').toLowerCase();
                    if (text.includes(query) || keywords.includes(query)) {
                        section.style.display = '';
                    } else {
                        section.style.display = 'none';
                    }
                });
            });
        }

        document.addEventListener('click', function(e) {
            const btn = e.target.closest('.btn-copy-meta-text');
            if (!btn) return;

            const textToCopy = btn.getAttribute('data-copy');
            if (!textToCopy) return;

            navigator.clipboard.writeText(textToCopy).then(function() {
                const origText = btn.textContent;
                btn.textContent = 'Copiado!';
                btn.classList.add('btn-success');
                btn.classList.remove('btn-outline-secondary');
                setTimeout(function() {
                    btn.textContent = origText;
                    btn.classList.remove('btn-success');
                    btn.classList.add('btn-outline-secondary');
                }, 1800);
            }).catch(function() {
                alert('Texto copiado: ' + textToCopy);
            });
        });
    });
})();
</script>
