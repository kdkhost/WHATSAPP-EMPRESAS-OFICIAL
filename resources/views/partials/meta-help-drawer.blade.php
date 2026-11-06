{{-- Central de Ajuda & Configuração Passo a Passo da Meta WhatsApp Cloud API --}}
{{-- Menu Deslizante da Direita para a Esquerda (Offcanvas) --}}

<div class="offcanvas offcanvas-end shadow-lg" tabindex="-1" id="metaHelpDrawer" aria-labelledby="metaHelpDrawerLabel" style="width: 660px; max-width: 95vw; z-index: 1085;">
    <div class="offcanvas-header border-bottom py-3 px-4" style="background: linear-gradient(135deg, rgba(13, 110, 253, 0.08), rgba(25, 135, 84, 0.08));">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle d-flex align-items-center justify-content-center text-white" style="width: 44px; height: 44px; background: linear-gradient(135deg, #0d6efd, #198754); box-shadow: 0 4px 12px rgba(13, 110, 253, 0.25);">
                <i class="bi bi-whatsapp fs-4"></i>
            </div>
            <div>
                <div class="d-flex align-items-center gap-2">
                    <h5 class="offcanvas-title mb-0 fw-bold" id="metaHelpDrawerLabel">Guia Oficial Meta WhatsApp</h5>
                    <span class="badge bg-success-subtle text-success border border-success-subtle small">Graph API v21.0</span>
                </div>
                <small class="text-muted d-block mt-1">Manual interativo com instruções individuais passo a passo de cada configuração</small>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
    </div>

    {{-- Barra de Busca e Navegação Rápida --}}
    <div class="px-4 pt-3 pb-2 border-bottom bg-body-tertiary">
        <div class="position-relative mb-2">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" id="metaHelpSearch" class="form-control form-control-sm ps-5 rounded-pill" placeholder="Buscar qualquer configuração (ex: token permanente, delay humano, webhook, waba, pix, cooldown)...">
        </div>
        
        <div class="d-flex gap-1 overflow-x-auto pb-1 meta-help-nav-pills" style="white-space: nowrap; scrollbar-width: thin;">
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill active py-0 px-2" data-nav-topic="meta-overview">Geral</button>
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-0 px-2" data-nav-topic="meta-app-setup">Criar App</button>
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-0 px-2" data-nav-topic="meta-system-user-token">Token Permanente</button>
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-0 px-2" data-nav-topic="meta-phone-id">Phone ID</button>
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-0 px-2" data-nav-topic="meta-waba-id">WABA ID</button>
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-0 px-2" data-nav-topic="meta-app-secret">App Secret</button>
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-0 px-2" data-nav-topic="meta-webhook">Webhook</button>
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-0 px-2" data-nav-topic="meta-templates">Templates</button>
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-0 px-2" data-nav-topic="meta-quality-rating">Qualidade & Tiers</button>
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-0 px-2" data-nav-topic="meta-anti-ban">Painel Anti-Ban</button>
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-0 px-2" data-nav-topic="tenant-instance-setup">Tenants</button>
        </div>
    </div>

    <div class="offcanvas-body p-4" id="metaHelpDrawerBody" style="scroll-behavior: smooth;">

        {{-- ========================================================================= --}}
        {{-- BLOCO 1: VISÃO GERAL & ONBOARDING OFICIAL META GRAPH API                  --}}
        {{-- ========================================================================= --}}

        {{-- TOPIC 1: Visao Geral --}}
        <section class="meta-help-topic mb-4" id="topic-meta-overview" data-keywords="visao geral meta api oficial whatsapp graph v21 introducao beneficios sem qr code">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="badge bg-primary px-2 py-1"><i class="bi bi-stars me-1"></i>Visão Geral</span>
                <h5 class="mb-0 fw-bold">Por que WhatsApp Cloud API Oficial da Meta?</h5>
            </div>
            
            <p class="text-body-secondary small">
                Nossa plataforma opera <strong>100% integrada à infraestrutura oficial da Meta</strong> através da <strong>Graph API v21.0</strong>. Este é o único canal corporativo homologado globalmente pelo WhatsApp, substituindo emuladores não oficiais (como Baileys, WPPConnect ou Chromium).
            </p>

            <div class="row g-2 mb-3">
                <div class="col-sm-6">
                    <div class="border rounded p-2 h-100 bg-body-tertiary">
                        <strong class="text-success d-flex align-items-center gap-1 small mb-1">
                            <i class="bi bi-shield-check"></i> Sem Risco de Banimento
                        </strong>
                        <span class="text-muted small">Os envios utilizam a rota oficial autenticada por Bearer Token da Meta. Seu número não é derrubado por desconexões forçadas de navegadores headless.</span>
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

        {{-- TOPIC 2: Passo a Passo Criar App no Meta Developer --}}
        <section class="meta-help-topic mb-4" id="topic-meta-app-setup" data-keywords="criar app aplicativo developers meta facebook registro onboarding painel">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="badge bg-info text-dark px-2 py-1"><i class="bi bi-1-circle me-1"></i>Passo 1</span>
                <h5 class="mb-0 fw-bold">Criar Aplicativo no Meta for Developers</h5>
            </div>

            <p class="text-body-secondary small">
                Para ter acesso à API Oficial do WhatsApp, você precisa de um Aplicativo registrado no portal de desenvolvedores da Meta:
            </p>

            <ol class="list-group list-group-numbered small mb-3">
                <li class="list-group-item bg-transparent">
                    Acesse o portal oficial: <a href="https://developers.facebook.com" target="_blank" rel="noopener" class="fw-semibold">developers.facebook.com <i class="bi bi-box-arrow-up-right"></i></a> e faça login com sua conta do Facebook associada à sua empresa.
                </li>
                <li class="list-group-item bg-transparent">
                    No canto superior direito, clique em <strong>Meus Aplicativos</strong> (My Apps) &gt; <strong>Criar Aplicativo</strong> (Create App).
                </li>
                <li class="list-group-item bg-transparent">
                    Na seleção de tipo de aplicativo, escolha <strong>Outro</strong> (Other) ou <strong>Empresa</strong> (Business).
                </li>
                <li class="list-group-item bg-transparent">
                    Dê um nome ao aplicativo (ex: <code>WhatsApp Cobranças SaaS</code>), insira seu e-mail de contato e selecione seu <strong>Gerenciador de Negócios (Meta Business Manager)</strong>.
                </li>
                <li class="list-group-item bg-transparent">
                    No painel inicial de produtos, localize o cartão do produto <strong>WhatsApp</strong> e clique em <strong>Configurar</strong> (Set Up).
                </li>
                <li class="list-group-item bg-transparent">
                    Pronto! O produto WhatsApp estará ativo no menu lateral esquerdo em <strong>WhatsApp &gt; Configuração da API</strong> (API Setup).
                </li>
            </ol>
        </section>

        <hr class="my-4">

        {{-- ========================================================================= --}}
        {{-- BLOCO 2: CREDENCIAIS DA META (PHONE ID, WABA ID, TOKEN, APP SECRET)       --}}
        {{-- ========================================================================= --}}

        {{-- TOPIC 3: Token Permanente de Usuario de Sistema --}}
        <section class="meta-help-topic mb-4" id="topic-meta-system-user-token" data-keywords="token permanente system user usuario de sistema acesso eaag expiracao 24h nunca expira meta_access_token">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="badge bg-warning text-dark px-2 py-1"><i class="bi bi-key-fill me-1"></i>Essencial</span>
                <h5 class="mb-0 fw-bold">Token Permanente de Sistema (System User Token)</h5>
            </div>

            <div class="alert alert-danger small py-2 px-3 mb-3 d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-octagon-fill fs-5"></i>
                <div>
                    <strong>Atenção Crítica:</strong> O token temporário exibido na tela inicial do Developer Portal expira em apenas <strong>24 horas</strong>. Para sua plataforma funcionar continuamente sem interrupções, você DEVE gerar um <strong>Token de Usuário do Sistema</strong> que nunca expira!
                </div>
            </div>

            <div class="card border bg-body-tertiary p-3 mb-3 small">
                <h6 class="fw-bold text-primary mb-2">Instruções Passo a Passo no Meta Business Suite:</h6>
                <ol class="mb-0 ps-3">
                    <li class="mb-2">Acesse: <a href="https://business.facebook.com/settings/system-users" target="_blank" rel="noopener" class="fw-semibold">Configurações do Negócio &gt; Usuários do Sistema <i class="bi bi-box-arrow-up-right"></i></a>.</li>
                    <li class="mb-2">Clique em <strong>Adicionar</strong> (Add), defina o nome <code>WhatsApp Integracao SaaS</code> e selecione a função <strong>Administrador</strong> (Admin).</li>
                    <li class="mb-2">Selecione o usuário criado e clique no botão <strong>Atribuir Ativos</strong> (Assign Assets):
                        <ul class="mt-1 ps-3">
                            <li>Em <strong>Aplicativos</strong>, selecione seu App e marque a permissão <strong>Controle Total (Gerenciar Aplicativo)</strong>.</li>
                            <li>Em <strong>Contas do WhatsApp</strong>, selecione sua WABA e marque a permissão <strong>Controle Total</strong>.</li>
                        </ul>
                    </li>
                    <li class="mb-2">Clique no botão <strong>Gerar Novo Token</strong> (Generate New Token):
                        <ul class="mt-1 ps-3">
                            <li>Selecione o seu Aplicativo criado anteriormente.</li>
                            <li>Em expiração do token, selecione obrigatoriamente: <strong>Nunca (Never)</strong>.</li>
                            <li>Marque as duas permissões obrigatórias:
                                <div class="mt-1">
                                    <span class="badge bg-primary me-1">whatsapp_business_management</span>
                                    <span class="badge bg-success">whatsapp_business_messaging</span>
                                </div>
                            </li>
                        </ul>
                    </li>
                    <li>Clique em <strong>Gerar Token</strong>. Copie imediatamente o código longo (iniciado por <code>EAAG...</code>) e cole no campo <strong>Token Permanente de Sistema</strong> do painel. O sistema salva com criptografia AES-256.</li>
                </ol>
            </div>
        </section>

        <hr class="my-4">

        {{-- TOPIC 4: Phone Number ID --}}
        <section class="meta-help-topic mb-4" id="topic-meta-phone-id" data-keywords="phone number id identificador numero telefone celular api setup meta_phone_number_id">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="badge bg-primary px-2 py-1"><i class="bi bi-telephone-fill me-1"></i>Identificador</span>
                <h5 class="mb-0 fw-bold">Phone Number ID da Meta (meta_phone_number_id)</h5>
            </div>

            <p class="text-body-secondary small">
                O <strong>Phone Number ID</strong> é o identificador numérico interno único de 15 a 17 dígitos que os servidores da Meta atribuem à linha de WhatsApp. <strong>Não confunda com o número do telefone</strong> (ex: 5511999999999). É esse ID que compõe as rotas de envio da Graph API.
            </p>

            <div class="border rounded p-3 bg-body-tertiary small mb-3">
                <strong class="d-block mb-1 text-primary"><i class="bi bi-search me-1"></i>Onde localizar no Meta Developer Portal:</strong>
                <ol class="mb-0 ps-3">
                    <li class="mb-1">Abra seu aplicativo no <a href="https://developers.facebook.com/apps/" target="_blank" rel="noopener">Meta Developer Portal</a>.</li>
                    <li class="mb-1">No menu lateral esquerdo, navegue até <strong>WhatsApp</strong> &gt; <strong>Configuração da API</strong> (API Setup).</li>
                    <li class="mb-1">Em <strong>"Etapa 1: Selecionar números de telefone"</strong>, localize a caixa:
                        <div class="p-2 my-1 rounded bg-body border font-monospace text-primary">
                            Identificador do número de telefone (Phone number ID)
                        </div>
                    </li>
                    <li>Copie o valor numérico (ex: <code>109283746592817</code>) e cole no campo correspondente no painel.</li>
                </ol>
            </div>

            <div class="alert alert-info py-2 px-3 small mb-0">
                <i class="bi bi-info-circle-fill me-1"></i> Se você for registrar um número próprio real, adicione o número em <em>Configuração da API &gt; Adicionar número de telefone</em>, confirme o PIN recebido por SMS/Ligação e use o Phone Number ID gerado para essa linha.
            </div>
        </section>

        <hr class="my-4">

        {{-- TOPIC 5: WABA ID --}}
        <section class="meta-help-topic mb-4" id="topic-meta-waba-id" data-keywords="waba id whatsapp business account conta comercial identificador gerenciador meta_waba_id">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="badge bg-primary px-2 py-1"><i class="bi bi-briefcase-fill me-1"></i>Conta Comercial</span>
                <h5 class="mb-0 fw-bold">WhatsApp Business Account ID - WABA ID (meta_waba_id)</h5>
            </div>

            <p class="text-body-secondary small">
                O <strong>WABA ID</strong> é o identificador da sua Conta Comercial do WhatsApp Business na Meta. É nela que residem os Message Templates aprovados, a forma de pagamento oficial da Meta e as políticas corporativas.
            </p>

            <div class="border rounded p-3 bg-body-tertiary small mb-3">
                <strong class="d-block mb-1 text-primary"><i class="bi bi-search me-1"></i>Onde localizar no painel da Meta:</strong>
                <ol class="mb-0 ps-3">
                    <li class="mb-1">Na mesma tela de <strong>API Setup</strong> do Meta Developer, diretamente abaixo do Phone Number ID:
                        <div class="p-2 my-1 rounded bg-body border font-monospace text-success">
                            Identificador da conta do WhatsApp Business (WABA ID)
                        </div>
                    </li>
                    <li class="mb-1">Ou acesse diretamente o <a href="https://business.facebook.com/wa/manage/" target="_blank" rel="noopener">WhatsApp Manager (Gerenciador do WhatsApp) <i class="bi bi-box-arrow-up-right"></i></a> e veja o número da conta no canto superior.</li>
                    <li>Copie o número e cole no campo <strong>WhatsApp Business Account ID</strong>.</li>
                </ol>
            </div>
        </section>

        <hr class="my-4">

        {{-- TOPIC 6: App Secret --}}
        <section class="meta-help-topic mb-4" id="topic-meta-app-secret" data-keywords="app secret chave secreta aplicativo hmac sha256 assinatura seguranca meta_app_secret">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="badge bg-dark px-2 py-1"><i class="bi bi-lock-fill me-1"></i>Segurança Criptográfica</span>
                <h5 class="mb-0 fw-bold">Meta App Secret - HMAC-SHA256 (meta_app_secret)</h5>
            </div>

            <p class="text-body-secondary small">
                O <strong>App Secret (Chave Secreta do Aplicativo)</strong> é usado pelo webhook para validar a assinatura criptográfica <code>X-Hub-Signature-256</code> em cada notificação de entrega ou resposta. Isso garante 100% de autenticidade, impedindo que terceiros enviem requisições falsas para seu servidor.
            </p>

            <div class="border rounded p-3 bg-body-tertiary small mb-3">
                <strong class="d-block mb-1 text-primary"><i class="bi bi-search me-1"></i>Onde obter:</strong>
                <ol class="mb-0 ps-3">
                    <li class="mb-1">No Meta Developer Portal, clique no menu lateral esquerdo em <strong>Configurações do aplicativo</strong> &gt; <strong>Básico</strong> (App settings &gt; Basic).</li>
                    <li class="mb-1">Localize o campo <strong>Chave Secreta do Aplicativo</strong> (App Secret).</li>
                    <li class="mb-1">Clique em <strong>Mostrar</strong> (Show) e confirme sua senha do Facebook se solicitado.</li>
                    <li>Copie a chave hexadecimal gerada e cole no campo <strong>Meta App Secret</strong>.</li>
                </ol>
            </div>
        </section>

        <hr class="my-4">

        {{-- TOPIC 7: Webhook Oficial --}}
        <section class="meta-help-topic mb-4" id="topic-meta-webhook" data-keywords="webhook url callback verify token handshake retorno mensagens entregas meta_webhook_verify_token">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="badge bg-success px-2 py-1"><i class="bi bi-arrow-repeat me-1"></i>Tempo Real</span>
                <h5 class="mb-0 fw-bold">Configurar o Webhook Oficial na Meta</h5>
            </div>

            <p class="text-body-secondary small">
                O Webhook é o canal bidirecional pelo qual a Meta notifica nosso sistema sobre o status das mensagens enviadas (se foi entregue, lida pelo cliente ou se houve erro) e entrega as respostas enviadas pelos clientes (como comprovantes e comandos de pagamento Pix).
            </p>

            <div class="card border mb-3 small">
                <div class="card-header bg-body-tertiary py-2">
                    <strong>Parâmetros para preencher na Meta:</strong>
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <label class="form-label text-muted small fw-bold mb-1">URL de Retorno de Chamada (Callback URL):</label>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control font-monospace" value="{{ url('/api/v1/webhook') }}" readonly id="meta-help-webhook-url">
                            <button class="btn btn-outline-secondary btn-copy-meta-text" data-copy="{{ url('/api/v1/webhook') }}">Copiar</button>
                        </div>
                        <small class="text-muted">No portal da Meta, cole no campo <strong>URL de retorno de chamada</strong>.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold mb-1">Verificar Token (Verify Token):</label>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control font-monospace" value="{{ config('whatsapp.meta_webhook_verify_token', env('META_WEBHOOK_VERIFY_TOKEN', 'kdkhost_meta_webhook_secret_2026')) }}" readonly>
                            <button class="btn btn-outline-secondary btn-copy-meta-text" data-copy="{{ config('whatsapp.meta_webhook_verify_token', env('META_WEBHOOK_VERIFY_TOKEN', 'kdkhost_meta_webhook_secret_2026')) }}">Copiar</button>
                        </div>
                        <small class="text-muted">No portal da Meta, cole no campo <strong>Verificar token</strong>.</small>
                    </div>

                    <h6 class="fw-bold mb-2">Campos para Assinar no Webhook (Manage Webhook Fields):</h6>
                    <p class="text-muted mb-2">Após verificar e salvar o webhook, clique em <strong>Gerenciar campos</strong> (Manage fields) e assine:</p>
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <span class="badge bg-primary p-2"><i class="bi bi-check-circle me-1"></i>messages (obrigatório: status e conversas)</span>
                        <span class="badge bg-success p-2"><i class="bi bi-check-circle me-1"></i>message_template_status_update (aprovação de modelos)</span>
                    </div>
                </div>
            </div>
        </section>

        <hr class="my-4">

        {{-- ========================================================================= --}}
        {{-- BLOCO 3: MESSAGE TEMPLATES, VARIÁVEIS, BOTÕES PIX E STATUS WAMID           --}}
        {{-- ========================================================================= --}}

        {{-- TOPIC 8: Templates de Mensagens Meta --}}
        <section class="meta-help-topic mb-4" id="topic-meta-templates" data-keywords="templates message templates modelos aprovacao utility marketing botoes pix variaveis">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="badge bg-info text-dark px-2 py-1"><i class="bi bi-file-earmark-text-fill me-1"></i>Regras Oficiais</span>
                <h5 class="mb-0 fw-bold">Message Templates & Regras de Disparo</h5>
            </div>

            <p class="text-body-secondary small">
                Para iniciar conversas com clientes fora da janela de 24 horas (como cobranças de faturas, avisos de vencimento ou confirmações de pedido), a Meta <strong>exige o uso de Message Templates pré-aprovados</strong>.
            </p>

            <div class="border rounded p-3 bg-body-tertiary small mb-3">
                <h6 class="fw-bold text-success mb-2"><i class="bi bi-check-all me-1"></i>Categorias Homologadas:</h6>
                <ul class="mb-3 ps-3">
                    <li><strong>UTILIDADE (Utility)</strong>: Faturas, avisos de vencimento, cobranças Pix, comprovantes e confirmações de serviço. Tem aprovação prioritária pela inteligência artificial da Meta (1 a 5 minutos) e o menor custo por conversa.</li>
                    <li><strong>MARKETING</strong>: Novidades, ofertas, promoções e reengajamento comercial.</li>
                    <li><strong>AUTENTICAÇÃO (Authentication)</strong>: Códigos de verificação (OTP) em duas etapas.</li>
                </ul>

                <h6 class="fw-bold text-primary mb-2" id="topic-meta-template-variables"><i class="bi bi-braces me-1"></i>Variáveis Dinâmicas ({nome}, {valor}, {vencimento}):</h6>
                <p class="mb-2">Na Meta, as variáveis dinâmicas utilizam o formato posicional <code>&#123;&#123;1&#125;&#125;</code>, <code>&#123;&#123;2&#125;&#125;</code>, <code>&#123;&#123;3&#125;&#125;</code>. Nosso sistema traduz automaticamente as tags amigáveis:</p>
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-bordered bg-body mb-0">
                        <thead>
                            <tr>
                                <th>Tag no Sistema</th>
                                <th>Posição Meta</th>
                                <th>Exemplo de Saída</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td><code>{nome}</code></td><td><code>&#123;&#123;1&#125;&#125;</code></td><td>João da Silva</td></tr>
                            <tr><td><code>{valor}</code></td><td><code>&#123;&#123;2&#125;&#125;</code></td><td>R$ 149,90</td></tr>
                            <tr><td><code>{vencimento}</code></td><td><code>&#123;&#123;3&#125;&#125;</code></td><td>15/11/2026</td></tr>
                            <tr><td><code>{pix_copia_cola}</code></td><td>Botão Copiar Código</td><td>00020126580014br.gov.bcb.pix...</td></tr>
                            <tr><td><code>{link_pagamento}</code></td><td>Botão de URL</td><td>https://fatura.link/f/abc123</td></tr>
                        </tbody>
                    </table>
                </div>

                <h6 class="fw-bold text-dark mb-2" id="topic-meta-pix-button"><i class="bi bi-qr-code me-1"></i>Botão Copiar Código Pix (Recomendação Meta):</h6>
                <p class="mb-0">
                    Ao criar seu template no WhatsApp Manager, adicione um botão do tipo <strong>Copiar código (Copy code)</strong> com o tipo <code>DYNAMIC</code>. Quando o cliente recebe a mensagem de cobrança, aparece um botão destacado <em>"Copiar Chave Pix"</em> que copia o código Pix Copia e Cola diretamente para a área de transferência do celular dele com 1 único toque!
                </p>
            </div>

            <div class="border rounded p-3 bg-body-tertiary small" id="topic-meta-wamid-tracking">
                <h6 class="fw-bold text-primary mb-2"><i class="bi bi-check2-all me-1"></i>Rastreamento WAMID e Ciclo de Vida da Mensagem:</h6>
                <p class="mb-2">Cada disparo realizado recebe um <strong>WAMID (WhatsApp Message ID)</strong> único da Meta (ex: <code>wamid.HBgL...</code>). Os status evoluem via Webhook:</p>
                <div class="d-flex flex-column gap-1">
                    <div><span class="badge bg-secondary me-1">sent</span> Mensagem processada e aceita pelos servidores da Meta.</div>
                    <div><span class="badge bg-info text-dark me-1">delivered</span> Mensagem entregue com sucesso no aparelho do destinatário (dois tracinhos cinzas).</div>
                    <div><span class="badge bg-success me-1">read</span> Mensagem visualizada pelo cliente (dois tracinhos azuis).</div>
                    <div><span class="badge bg-danger me-1">failed</span> Falha no envio (ex: número inexistente, opt-out ou restrição). O motivo exato é salvo no log.</div>
                </div>
            </div>
        </section>

        <hr class="my-4">

        {{-- TOPIC 9: Qualidade do Numero & Tiers --}}
        <section class="meta-help-topic mb-4" id="topic-meta-quality-rating" data-keywords="qualidade quality rating tiers limites 1k 10k 100k escala warm up aquecimento meta-quality-rating">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="badge bg-success px-2 py-1"><i class="bi bi-speedometer2 me-1"></i>Escala</span>
                <h5 class="mb-0 fw-bold">Classificação de Qualidade & Limites de Envio (Tiers)</h5>
            </div>

            <p class="text-body-secondary small">
                A Meta monitora em tempo real a reputação do seu número de telefone e ajusta seu limite diário automaticamente com base no feedback dos destinatários (bloqueios e denúncias de spam):
            </p>

            <div class="table-responsive small mb-3">
                <table class="table table-bordered table-sm align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Nível (Tier)</th>
                            <th>Capacidade Diária</th>
                            <th>Critério de Progressão</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span class="badge bg-secondary">Tier 1</span></td>
                            <td><strong>1.000 clientes/24h</strong></td>
                            <td>Limite padrão inicial de qualquer número verificado na Meta.</td>
                        </tr>
                        <tr>
                            <td><span class="badge bg-primary">Tier 2</span></td>
                            <td><strong>10.000 clientes/24h</strong></td>
                            <td>Dispare metade do Tier 1 em uma janela de 7 dias mantendo Qualidade Verde.</td>
                        </tr>
                        <tr>
                            <td><span class="badge bg-success">Tier 3</span></td>
                            <td><strong>100.000 clientes/24h</strong></td>
                            <td>Escalonado automaticamente conforme a base de clientes cresce.</td>
                        </tr>
                        <tr>
                            <td><span class="badge bg-dark">Tier 4</span></td>
                            <td><strong>Ilimitado</strong></td>
                            <td>Grandes operações corporativas com verificação avançada.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="border rounded p-3 bg-body-tertiary small">
                <strong class="d-block mb-1">Cores da Qualidade (Quality Rating):</strong>
                <div class="d-flex flex-column gap-2 mt-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-success px-2 py-1">ALTA (GREEN)</span>
                        <span>Reputação excelente. O número pode escalar para o próximo Tier.</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-warning text-dark px-2 py-1">MÉDIA (YELLOW)</span>
                        <span>Alerta. Alguns clientes reportaram a mensagem. O sistema recomenda aumentar os intervalos e manter o modo seguro.</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-danger px-2 py-1">BAIXA (RED)</span>
                        <span>Risco de restrição. Se permanecer em vermelho por 7 dias, a Meta reduz o Tier.</span>
                    </div>
                </div>
            </div>
        </section>

        <hr class="my-4">

        {{-- ========================================================================= --}}
        {{-- BLOCO 4: INSTRUÇÕES INDIVIDUAIS DE CADA CAMPO DO PAINEL ANTI-BAN           --}}
        {{-- ========================================================================= --}}

        <section class="meta-help-topic mb-4" id="topic-meta-anti-ban" data-keywords="anti-ban antiban politicas seguranca janela 24h opt in opt out descadastro safety">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="badge bg-warning text-dark px-2 py-1"><i class="bi bi-shield-lock-fill me-1"></i>Painel Anti-Ban</span>
                <h5 class="mb-0 fw-bold">Guia Individual de Cada Campo Anti-Ban</h5>
            </div>

            <p class="text-body-secondary small">
                Abaixo estão as instruções detalhadas de <strong>cada item e configuração</strong> do painel de segurança. Clique em qualquer um para conferir seu funcionamento e recomendações:
            </p>

            {{-- 1. enabled --}}
            <div class="meta-help-subtopic border rounded p-3 mb-3 bg-body-tertiary" id="topic-safety-enabled">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <strong class="text-primary small"><i class="bi bi-shield-check me-1"></i>Proteção ativa (enabled)</strong>
                    <span class="badge bg-secondary-subtle text-body border small">Política Geral</span>
                </div>
                <p class="small text-muted mb-2"><strong>O que é:</strong> Interruptor mestre que ativa ou desativa o motor de inteligência anti-ban em todo o sistema.</p>
                <div class="small text-body-secondary mb-2">
                    <strong>Como funciona:</strong> Quando ativo, todas as mensagens passam pelas verificações de horário comercial, limites de disparo, cooldowns entre contatos e detecção de opt-out. Desligue apenas temporariamente para testes de laboratório controlados.
                </div>
                <div class="alert alert-success py-1 px-2 small mb-0">
                    <i class="bi bi-check-circle me-1"></i><strong>Recomendação:</strong> Mantenha sempre <code>Sim</code> em produção.
                </div>
            </div>

            {{-- 2. timezone --}}
            <div class="meta-help-subtopic border rounded p-3 mb-3 bg-body-tertiary" id="topic-safety-timezone">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <strong class="text-primary small"><i class="bi bi-clock me-1"></i>Timezone (timezone)</strong>
                    <span class="badge bg-secondary-subtle text-body border small">Fuso Horário</span>
                </div>
                <p class="small text-muted mb-2"><strong>O que é:</strong> Fuso horário oficial utilizado para determinar o horário comercial, controle diário e rotação dos tetos de envio.</p>
                <div class="small text-body-secondary mb-2">
                    <strong>Como funciona:</strong> Garante que os limites diários reiniciem exatamente à meia-noite local da sua operação e que os disparos só ocorram no horário permitido no fuso horário dos seus clientes.
                </div>
                <div class="alert alert-info py-1 px-2 small mb-0">
                    <i class="bi bi-info-circle me-1"></i><strong>Padrão Brasil:</strong> <code>America/Sao_Paulo (Horário de Brasília)</code>.
                </div>
            </div>

            {{-- 3, 4, 5. business_hours --}}
            <div class="meta-help-subtopic border rounded p-3 mb-3 bg-body-tertiary" id="topic-safety-business_hours_enabled">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <strong class="text-primary small"><i class="bi bi-calendar-check me-1"></i>Janela comercial (business_hours_enabled / start / end)</strong>
                    <span class="badge bg-secondary-subtle text-body border small">Horário de Atendimento</span>
                </div>
                <p class="small text-muted mb-2"><strong>O que é:</strong> Bloqueia disparos automáticos de cobrança fora do horário de expediente comercial.</p>
                <div class="small text-body-secondary mb-2">
                    <strong>Como funciona na prática:</strong> Se configurado das <code>08:00</code> às <code>20:00</code>, uma fatura gerada às 22h não acordará o cliente de madrugada (o que é a causa número 1 de denúncias de spam). A mensagem fica salva na fila e é despachada automaticamente na manhã seguinte às 08:00.
                </div>
                <div class="d-flex gap-2">
                    <span class="badge bg-dark" id="topic-safety-business_hours_start">Início: 08:00</span>
                    <span class="badge bg-dark" id="topic-safety-business_hours_end">Fim: 20:00</span>
                </div>
            </div>

            {{-- 6. block_groups --}}
            <div class="meta-help-subtopic border rounded p-3 mb-3 bg-body-tertiary" id="topic-safety-block_groups">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <strong class="text-primary small"><i class="bi bi-people me-1"></i>Bloquear grupos (block_groups)</strong>
                    <span class="badge bg-secondary-subtle text-body border small">Destino</span>
                </div>
                <p class="small text-muted mb-2"><strong>O que é:</strong> Impede o envio de notificações em grupos do WhatsApp.</p>
                <div class="small text-body-secondary mb-2">
                    <strong>Motivo:</strong> Cobranças e mensagens financeiras contêm dados pessoais (LGPD). O envio oficial deve ser sempre 1:1 diretamente no privado do cliente.
                </div>
            </div>

            {{-- 7. minimum_message_chars --}}
            <div class="meta-help-subtopic border rounded p-3 mb-3 bg-body-tertiary" id="topic-safety-minimum_message_chars">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <strong class="text-primary small"><i class="bi bi-chat-text me-1"></i>Caracteres mínimos (minimum_message_chars)</strong>
                    <span class="badge bg-secondary-subtle text-body border small">Validação</span>
                </div>
                <p class="small text-muted mb-2"><strong>O que é:</strong> Tamanho mínimo exigido para mensagens de texto livre.</p>
                <div class="small text-body-secondary mb-2">
                    <strong>Motivo:</strong> Mensagens curtas demais (ex: "oi", "ola") disparam filtros heurísticos de robôs de spam na Meta. Mantenha em pelo menos <code>10</code> a <code>15</code> caracteres.
                </div>
            </div>

            {{-- 8. stop_keywords --}}
            <div class="meta-help-subtopic border rounded p-3 mb-3 bg-body-tertiary" id="topic-safety-stop_keywords">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <strong class="text-danger small"><i class="bi bi-slash-circle me-1"></i>Palavras de parada / Opt-Out (stop_keywords)</strong>
                    <span class="badge bg-danger text-white border small">Regra Estrita Meta</span>
                </div>
                <p class="small text-muted mb-2"><strong>O que é:</strong> Termos que ativam o descadastro automático imediato do cliente.</p>
                <div class="small text-body-secondary mb-2">
                    <strong>Como funciona:</strong> Se um cliente responder <code>PARAR</code>, <code>SAIR</code>, <code>CANCELAR</code>, <code>STOP</code> ou <code>NAO QUERO</code>, o sistema registra o opt-out no banco de dados e bloqueia preventivamente qualquer disparo automático futuro para esse número.
                </div>
                <div class="alert alert-warning py-1 px-2 small mb-0">
                    <i class="bi bi-exclamation-triangle me-1"></i> Esta é uma exigência expressa dos Termos de Serviço da Meta para manter a linha em Qualidade Verde.
                </div>
            </div>

            {{-- 9, 10, 11. limits per minute, hour, day --}}
            <div class="meta-help-subtopic border rounded p-3 mb-3 bg-body-tertiary" id="topic-safety-limit_per_minute">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <strong class="text-primary small"><i class="bi bi-speedometer2 me-1"></i>Limites de Envio (limit_per_minute / hour / day)</strong>
                    <span class="badge bg-secondary-subtle text-body border small">Controle de Fluxo</span>
                </div>
                <p class="small text-muted mb-2"><strong>O que é:</strong> Tetos máximos de disparos em diferentes janelas temporais para evitar rajadas.</p>
                <ul class="small ps-3 mb-2">
                    <li id="topic-safety-limit_per_minute"><strong>Limite por minuto (limit_per_minute):</strong> Evita picos instantâneos (rajadas). Recomendado: <code>5</code> a <code>15</code> msgs/min.</li>
                    <li id="topic-safety-limit_per_hour"><strong>Limite por hora (limit_per_hour):</strong> Distribui as campanhas suavemente. Recomendado: <code>100</code> a <code>250</code> msgs/hora.</li>
                    <li id="topic-safety-limit_per_day"><strong>Limite por dia (limit_per_day):</strong> Teto diário total da linha. Novos números devem respeitar o Tier da Meta (Tier 1: 1.000 msgs/dia).</li>
                </ul>
            </div>

            {{-- 12, 13, 14. cooldowns --}}
            <div class="meta-help-subtopic border rounded p-3 mb-3 bg-body-tertiary" id="topic-safety-recipient_cooldown_minutes">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <strong class="text-primary small"><i class="bi bi-hourglass-split me-1"></i>Cooldowns (recipient / message / campaign)</strong>
                    <span class="badge bg-secondary-subtle text-body border small">Espaçamento Inteligente</span>
                </div>
                <p class="small text-muted mb-2"><strong>O que é:</strong> Tempo de descanso obrigatório para evitar insistência ou disparos repetidos acidentais.</p>
                <ul class="small ps-3 mb-0">
                    <li id="topic-safety-recipient_cooldown_minutes"><strong>Cooldown do Destinatário (recipient_cooldown_minutes):</strong> Intervalo mínimo para o mesmo cliente receber outra notificação (ex: <code>30</code> a <code>120</code> minutos).</li>
                    <li id="topic-safety-message_cooldown_minutes"><strong>Cooldown da Mensagem (message_cooldown_minutes):</strong> Evita reenviar o mesmo texto exato em menos de X minutos.</li>
                    <li id="topic-safety-campaign_cooldown_minutes"><strong>Cooldown de Campanha (campaign_cooldown_minutes):</strong> Espaçamento entre lotes de disparos em massa.</li>
                </ul>
            </div>

            {{-- 16, 17, 18. human delay --}}
            <div class="meta-help-subtopic border rounded p-3 mb-3 bg-body-tertiary" id="topic-safety-human_delay_enabled">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <strong class="text-success small"><i class="bi bi-person-walking me-1"></i>Delay Humano Randômico (human_delay_min / max)</strong>
                    <span class="badge bg-success-subtle text-success border small">Anti-Detecção</span>
                </div>
                <p class="small text-muted mb-2"><strong>O que é:</strong> Adiciona pequenos atrasos variáveis entre cada mensagem disparada pela fila.</p>
                <div class="small text-body-secondary mb-2">
                    <strong>Como funciona:</strong> Em vez de disparar a cada 1 segundo exato (comportamento típico de bot), o disparador sorteia um atraso entre o mínimo (<code id="topic-safety-human_delay_min_seconds">12s</code>) e o máximo (<code id="topic-safety-human_delay_max_seconds">32s</code>), tornando o tráfego indistinguível de um operador humano real.
                </div>
            </div>

            {{-- 19, 20. send delay ms --}}
            <div class="meta-help-subtopic border rounded p-3 mb-3 bg-body-tertiary" id="topic-safety-send_delay_min_ms">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <strong class="text-primary small"><i class="bi bi-cpu me-1"></i>Jitter de Chamada da API (send_delay_min_ms / max_ms)</strong>
                    <span class="badge bg-secondary-subtle text-body border small">Microlatência</span>
                </div>
                <p class="small text-muted mb-2"><strong>O que é:</strong> Atraso em milissegundos entre requisições HTTP enviadas aos servidores da Meta (Graph API).</p>
                <div class="small text-body-secondary mb-0">
                    Evita concorrência excessiva de sockets e suaviza a carga sobre a API da Meta. Padrão: <code>250ms</code> a <code>850ms</code>.
                </div>
            </div>

            {{-- 21, 22, 23, 24. warmup --}}
            <div class="meta-help-subtopic border rounded p-3 mb-3 bg-body-tertiary" id="topic-safety-warmup_enabled">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <strong class="text-warning-emphasis small"><i class="bi bi-thermometer-half me-1"></i>Aquecimento Progressivo de Números Novos (Warm-up)</strong>
                    <span class="badge bg-warning text-dark border small">Números Recentes</span>
                </div>
                <p class="small text-muted mb-2"><strong>O que é:</strong> Escalonamento progressivo automático do volume diário para chips ou linhas recém-cadastradas na Meta.</p>
                <div class="small text-body-secondary mb-2">
                    Quando você ativa uma linha nova, disparar 1.000 mensagens no primeiro dia pode gerar choque de volume nos filtros da Meta. O warmup limita automaticamente:
                </div>
                <ul class="small ps-3 mb-0">
                    <li id="topic-safety-warmup_limit_days_1_3"><strong>Dias 1 a 3:</strong> até <code>30</code> mensagens/dia.</li>
                    <li id="topic-safety-warmup_limit_days_4_7"><strong>Dias 4 a 7:</strong> até <code>80</code> mensagens/dia.</li>
                    <li id="topic-safety-warmup_limit_days_8_15"><strong>Dias 8 a 15:</strong> até <code>150</code> mensagens/dia.</li>
                    <li><strong>Após 15 dias:</strong> volume liberado para o limite total do Tier 1 oficial (1.000 msgs/dia).</li>
                </ul>
            </div>

            {{-- 25, 26, 27, 28. risk score --}}
            <div class="meta-help-subtopic border rounded p-3 mb-3 bg-body-tertiary" id="topic-safety-risk_medium">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <strong class="text-danger small"><i class="bi bi-activity me-1"></i>Score de Risco & Bloqueio Preventivo (risk_block_above)</strong>
                    <span class="badge bg-danger-subtle text-danger border small">Proteção Ativa</span>
                </div>
                <p class="small text-muted mb-2"><strong>O que é:</strong> Algoritmo em tempo real que calcula a pontuação de risco da linha (0 a 100).</p>
                <div class="small text-body-secondary mb-2">
                    O cálculo considera: taxa de erros 4xx/5xx da Meta, clientes bloqueando, rejeições de mensagens e velocidade anormal. Se o score atingir a nota configurada em <code id="topic-safety-risk_block_above">risk_block_above (ex: 85)</code>, o sistema pausa os disparos automaticamente antes que a Meta penalize a conta!
                </div>
            </div>

            {{-- 36. send_pix_qrcode_image --}}
            <div class="meta-help-subtopic border rounded p-3 mb-3 bg-body-tertiary" id="topic-safety-send_pix_qrcode_image">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <strong class="text-primary small"><i class="bi bi-qr-code me-1"></i>Enviar imagem do QR Code Pix (send_pix_qrcode_image)</strong>
                    <span class="badge bg-primary-subtle text-primary border small">Mídia Financeira</span>
                </div>
                <p class="small text-muted mb-2"><strong>O que é:</strong> Define se o sistema deve gerar e anexar a imagem do QR Code Pix além do código Copia e Cola em texto.</p>
                <div class="small text-body-secondary mb-0">
                    Se marcado como <code>Sim</code>, o cliente que abre o WhatsApp no computador ou tablet pode simplesmente apontar a câmera do celular bancário para a imagem da tela e pagar imediatamente.
                </div>
            </div>

            {{-- 32, 33, 34, 35. tenant custom limits --}}
            <div class="meta-help-subtopic border rounded p-3 mb-3 bg-body-tertiary" id="topic-safety-tenant_allow_custom_limits">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <strong class="text-info small"><i class="bi bi-sliders2 me-1"></i>Permissões para Inquilinos (tenant_allow_custom_limits)</strong>
                    <span class="badge bg-info-subtle text-info border small">SaaS Multi-Tenant</span>
                </div>
                <p class="small text-muted mb-2"><strong>O que é:</strong> Permite que inquilinos/tenants ajustem o ritmo de suas próprias linhas em seus painéis (Modo Seguro, Moderado ou Acelerado).</p>
                <div class="small text-body-secondary mb-0">
                    O Superadmin pode estipular um teto máximo inultrapassável em <code id="topic-safety-tenant_max_limit_per_day">tenant_max_limit_per_day</code> (ex: 500 msgs/dia), garantindo que nenhum cliente comprometa os recursos do servidor.
                </div>
            </div>
        </section>

        <hr class="my-4">

        {{-- ========================================================================= --}}
        {{-- BLOCO 5: CONFIGURAÇÃO DE INSTÂNCIAS DE INQUILINOS (TENANTS)               --}}
        {{-- ========================================================================= --}}

        {{-- TOPIC 11: Instancias de Tenants --}}
        <section class="meta-help-topic mb-3" id="topic-tenant-instance-setup" data-keywords="tenant instancia cliente multi-tenant waba propria linha empresa">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="badge bg-primary px-2 py-1"><i class="bi bi-buildings-fill me-1"></i>Multi-Tenant</span>
                <h5 class="mb-0 fw-bold">Instâncias de Inquilinos (Tenants)</h5>
            </div>

            <p class="text-body-secondary small">
                Cada empresa ou cliente cadastrado na plataforma pode operar de duas formas conforme a sua preferência:
            </p>

            <div class="row g-2 small mb-3">
                <div class="col-12">
                    <div class="border rounded p-3 bg-body-tertiary">
                        <strong class="text-primary d-block mb-1"><i class="bi bi-check2-circle me-1"></i>1. Linha Própria Oficial do Tenant (Recomendado para Empresas):</strong>
                        O inquilino insere seu próprio <strong>Phone Number ID</strong>, <strong>WABA ID</strong> e <strong>Token Permanente</strong> gerados no Meta Business Suite dele.
                        <div class="text-muted mt-1">Benefícios: A linha exibe o nome, logotipo e perfil oficial da empresa dele no WhatsApp, e as conversas pertencem exclusivamente à conta comercial dele.</div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="border rounded p-3 bg-body-tertiary">
                        <strong class="text-success d-block mb-1"><i class="bi bi-cloud-check me-1"></i>2. Linha Compartilhada do Sistema (Onboarding Instantâneo):</strong>
                        Se o inquilino deixar os campos de credenciais em branco, o sistema utiliza automaticamente a linha master da Meta configurada pelo Superadmin no painel principal.
                        <div class="text-muted mt-1">Benefícios: O cliente começa a disparar cobranças no mesmo instante em que se cadastra, sem precisar criar conta de desenvolvedor na Meta.</div>
                    </div>
                </div>
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
</style>

<script>
(function() {
    // Função global para abrir o Drawer no tópico desejado
    window.openHelpDrawer = function(topicId) {
        const offcanvasEl = document.getElementById('metaHelpDrawer');
        if (!offcanvasEl) return;

        // Se bootstrap estiver disponível
        let offcanvas = null;
        if (typeof bootstrap !== 'undefined' && bootstrap.Offcanvas) {
            offcanvas = bootstrap.Offcanvas.getOrCreateInstance(offcanvasEl);
        }

        if (offcanvas) {
            offcanvas.show();
        } else {
            // Fallback nativo
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
        
        // Se nao encontrou exato e comeca com safety-, tenta achar container geral
        if (!target && cleanId.startsWith('safety-')) {
            target = document.getElementById('topic-meta-anti-ban');
        }

        // Atualiza os pills no topo
        document.querySelectorAll('.meta-help-nav-pills [data-nav-topic]').forEach(function(pill) {
            const navTopic = pill.getAttribute('data-nav-topic');
            if (navTopic === cleanId || (cleanId.startsWith('safety-') && navTopic === 'meta-anti-ban')) {
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
        // Event delegation para cliques em qualquer gatilho de ajuda
        document.addEventListener('click', function(e) {
            const trigger = e.target.closest('.js-meta-help-trigger, [data-help-topic]');
            if (!trigger) return;

            e.preventDefault();
            const topic = trigger.getAttribute('data-help-topic') || trigger.getAttribute('data-topic') || 'meta-overview';
            window.openHelpDrawer(topic);
        });

        // Clique nos pills do Drawer
        document.querySelectorAll('.meta-help-nav-pills [data-nav-topic]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const topic = this.getAttribute('data-nav-topic');
                window.scrollToHelpTopic(topic);
            });
        });

        // Busca em tempo real nos tópicos e sub-tópicos
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

        // Botões de copiar texto com 1 clique dentro do drawer
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
