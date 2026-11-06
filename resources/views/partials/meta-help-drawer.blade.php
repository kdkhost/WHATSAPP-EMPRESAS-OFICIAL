{{-- Central de Ajuda & Configuração Passo a Passo da Meta WhatsApp Cloud API --}}
{{-- Menu Deslizante da Direita para a Esquerda (Offcanvas) --}}

<div class="offcanvas offcanvas-end shadow-lg" tabindex="-1" id="metaHelpDrawer" aria-labelledby="metaHelpDrawerLabel" style="width: 620px; max-width: 95vw; z-index: 1085;">
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
                <small class="text-muted d-block mt-1">Manual interativo passo a passo e documentação técnica</small>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
    </div>

    {{-- Barra de Busca e Navegação Rápida --}}
    <div class="px-4 pt-3 pb-2 border-bottom bg-body-tertiary">
        <div class="position-relative mb-2">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" id="metaHelpSearch" class="form-control form-control-sm ps-5 rounded-pill" placeholder="Buscar no guia (ex: token permanente, webhook, templates, waba, phone id)...">
        </div>
        
        <div class="d-flex gap-1 overflow-x-auto pb-1 meta-help-nav-pills" style="white-space: nowrap; scrollbar-width: thin;">
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill active py-0 px-2" data-nav-topic="meta-overview">Geral</button>
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-0 px-2" data-nav-topic="meta-app-setup">Criar App</button>
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-0 px-2" data-nav-topic="meta-system-user-token">Token Permanente</button>
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-0 px-2" data-nav-topic="meta-phone-id">Phone ID</button>
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-0 px-2" data-nav-topic="meta-waba-id">WABA ID</button>
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-0 px-2" data-nav-topic="meta-webhook">Webhook</button>
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-0 px-2" data-nav-topic="meta-templates">Templates</button>
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-0 px-2" data-nav-topic="meta-quality-rating">Qualidade & Tiers</button>
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-0 px-2" data-nav-topic="meta-anti-ban">Anti-Ban</button>
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-0 px-2" data-nav-topic="tenant-instance-setup">Tenants</button>
        </div>
    </div>

    <div class="offcanvas-body p-4" id="metaHelpDrawerBody" style="scroll-behavior: smooth;">

        {{-- TOPIC 1: Visao Geral --}}
        <section class="meta-help-topic mb-4" id="topic-meta-overview" data-keywords="visao geral meta api oficial whatsapp graph v21 introducao beneficios sem qr code">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="badge bg-primary px-2 py-1"><i class="bi bi-stars me-1"></i>Visão Geral</span>
                <h5 class="mb-0 fw-bold">Por que WhatsApp Cloud API Oficial da Meta?</h5>
            </div>
            
            <p class="text-body-secondary">
                Nossa plataforma opera <strong>100% integrada à infraestrutura oficial da Meta</strong> através da <strong>Graph API v21.0</strong>. Este é o canal corporativo homologado globalmente pelo WhatsApp, substituindo emuladores não oficiais.
            </p>

            <div class="row g-2 mb-3">
                <div class="col-sm-6">
                    <div class="border rounded p-2 h-100 bg-body-tertiary">
                        <strong class="text-success d-flex align-items-center gap-1 small mb-1">
                            <i class="bi bi-shield-check"></i> Sem Risco de Banimento
                        </strong>
                        <span class="text-muted small">Os envios utilizam a rota oficial autenticada por Bearer Token da Meta. Seu número não é derrubado por Baileys/Chromium.</span>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="border rounded p-2 h-100 bg-body-tertiary">
                        <strong class="text-primary d-flex align-items-center gap-1 small mb-1">
                            <i class="bi bi-phone-fill"></i> Independente de Aparelho
                        </strong>
                        <span class="text-muted small">Não precisa de celular ligado à tomada, nem de conexão Wi-Fi ativa. O envio e o webhook operam na nuvem 24/7.</span>
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
                Para ter acesso à API Oficial do WhatsApp, você precisa de um Aplicativo no portal de desenvolvedores da Meta:
            </p>

            <ol class="list-group list-group-numbered small mb-3">
                <li class="list-group-item bg-transparent">
                    Acesse o portal oficial: <a href="https://developers.facebook.com" target="_blank" rel="noopener" class="fw-semibold">developers.facebook.com <i class="bi bi-box-arrow-up-right"></i></a> e faça login com sua conta corporativa.
                </li>
                <li class="list-group-item bg-transparent">
                    No canto superior direito, clique em <strong>Meus Aplicativos</strong> (My Apps) &gt; <strong>Criar Aplicativo</strong> (Create App).
                </li>
                <li class="list-group-item bg-transparent">
                    Na seleção de tipo de aplicativo, escolha <strong>Outro</strong> (Other) ou <strong>Empresa</strong> (Business).
                </li>
                <li class="list-group-item bg-transparent">
                    Dê um nome ao aplicativo (ex: <code>WhatsApp Notificações SaaS</code>), insira seu email de contato e selecione seu <strong>Gerenciador de Negócios (Meta Business Manager)</strong>.
                </li>
                <li class="list-group-item bg-transparent">
                    No painel inicial do App, localize o cartão do produto <strong>WhatsApp</strong> e clique em <strong>Configurar</strong> (Set Up).
                </li>
                <li class="list-group-item bg-transparent">
                    Pronto! O produto WhatsApp estará ativo no menu lateral esquerdo em <strong>WhatsApp &gt; Configuração da API</strong> (API Setup).
                </li>
            </ol>
        </section>

        <hr class="my-4">

        {{-- TOPIC 3: Token Permanente de Usuario de Sistema --}}
        <section class="meta-help-topic mb-4" id="topic-meta-system-user-token" data-keywords="token permanente system user usuario de sistema acesso eaag expiracao 24h nunca expira">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="badge bg-warning text-dark px-2 py-1"><i class="bi bi-key-fill me-1"></i>Essencial</span>
                <h5 class="mb-0 fw-bold">Gerar Token Permanente (System User Token)</h5>
            </div>

            <div class="alert alert-danger small py-2 px-3 mb-3 d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-octagon-fill fs-5"></i>
                <div>
                    <strong>Atenção:</strong> O token exibido na tela inicial do Developer Portal expira em apenas <strong>24 horas</strong>. Para produção contínua, você DEVE gerar um <strong>Token de Usuário do Sistema</strong> que nunca expira!
                </div>
            </div>

            <div class="card border bg-body-tertiary p-3 mb-3 small">
                <h6 class="fw-bold text-primary mb-2">Passo a passo no Meta Business Manager:</h6>
                <ol class="mb-0 ps-3">
                    <li class="mb-2">Acesse: <a href="https://business.facebook.com/settings/system-users" target="_blank" rel="noopener" class="fw-semibold">Configurações do Negócio &gt; Usuários do Sistema <i class="bi bi-box-arrow-up-right"></i></a>.</li>
                    <li class="mb-2">Clique em <strong>Adicionar</strong> (Add), defina o nome <code>WhatsApp Automacao</code> e selecione a função <strong>Administrador</strong> (Admin).</li>
                    <li class="mb-2">Selecione o usuário criado e clique em <strong>Atribuir Ativos</strong> (Assign Assets):
                        <ul class="mt-1 ps-3">
                            <li>Em <strong>Aplicativos</strong>, selecione seu App e marque <strong>Controle Total (Gerenciar Aplicativo)</strong>.</li>
                            <li>Em <strong>Contas do WhatsApp</strong>, selecione sua WABA e marque <strong>Controle Total</strong>.</li>
                        </ul>
                    </li>
                    <li class="mb-2">Clique em <strong>Gerar Novo Token</strong> (Generate New Token):
                        <ul class="mt-1 ps-3">
                            <li>Selecione o seu Aplicativo.</li>
                            <li>Em expiração do token, escolha: <strong>Nunca (Never)</strong>.</li>
                            <li>Marque as permissões obrigatórias:
                                <div class="mt-1">
                                    <span class="badge bg-primary me-1">whatsapp_business_management</span>
                                    <span class="badge bg-success">whatsapp_business_messaging</span>
                                </div>
                            </li>
                        </ul>
                    </li>
                    <li>Clique em <strong>Gerar Token</strong>. Copie imediatamente o código longo (iniciado por <code>EAAG...</code>) e cole no campo <strong>Token Permanente de Sistema</strong> do painel.</li>
                </ol>
            </div>
        </section>

        <hr class="my-4">

        {{-- TOPIC 4: Phone Number ID --}}
        <section class="meta-help-topic mb-4" id="topic-meta-phone-id" data-keywords="phone number id identificador numero telefone celular api setup">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="badge bg-primary px-2 py-1"><i class="bi bi-telephone-fill me-1"></i>Identificador</span>
                <h5 class="mb-0 fw-bold">Como obter o Phone Number ID</h5>
            </div>

            <p class="text-body-secondary small">
                O <strong>Phone Number ID</strong> é o identificador numérico único que a Meta atribui à linha telefônica. Não é o número do telefone, e sim um código de 15 a 17 dígitos.
            </p>

            <div class="border rounded p-3 bg-body-tertiary small mb-3">
                <strong class="d-block mb-1">Onde encontrar na Meta:</strong>
                <ol class="mb-0 ps-3">
                    <li class="mb-1">No <a href="https://developers.facebook.com/apps/" target="_blank" rel="noopener">Meta Developer Portal</a>, abra seu Aplicativo.</li>
                    <li class="mb-1">No menu lateral esquerdo, clique em <strong>WhatsApp</strong> &gt; <strong>Configuração da API</strong> (API Setup).</li>
                    <li class="mb-1">Em <strong>"Etapa 1: Selecionar números de telefone"</strong>, localize o campo:
                        <div class="p-2 my-1 rounded bg-body border font-monospace text-primary">
                            Identificador do número de telefone (Phone number ID)
                        </div>
                    </li>
                    <li>Copie esse número (ex: <code>109283746592817</code>) e cole no campo correspondente no painel.</li>
                </ol>
            </div>
        </section>

        <hr class="my-4">

        {{-- TOPIC 5: WABA ID --}}
        <section class="meta-help-topic mb-4" id="topic-meta-waba-id" data-keywords="waba id whatsapp business account conta comercial identificador gerenciador">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="badge bg-primary px-2 py-1"><i class="bi bi-briefcase-fill me-1"></i>Conta Comercial</span>
                <h5 class="mb-0 fw-bold">Como obter o WABA ID (WhatsApp Business Account)</h5>
            </div>

            <p class="text-body-secondary small">
                O <strong>WABA ID</strong> identifica sua conta corporativa do WhatsApp Business na Meta. É nela que os Message Templates e faturamento ficam vinculados.
            </p>

            <div class="border rounded p-3 bg-body-tertiary small mb-3">
                <strong class="d-block mb-1">Onde encontrar na Meta:</strong>
                <ol class="mb-0 ps-3">
                    <li class="mb-1">Na mesma tela de <strong>API Setup</strong> do Meta Developer, logo abaixo do Phone Number ID:
                        <div class="p-2 my-1 rounded bg-body border font-monospace text-success">
                            Identificador da conta do WhatsApp Business (WABA ID)
                        </div>
                    </li>
                    <li class="mb-1">Ou acesse diretamente o <a href="https://business.facebook.com/wa/manage/" target="_blank" rel="noopener">WhatsApp Manager <i class="bi bi-box-arrow-up-right"></i></a> e veja o ID da conta listado no topo.</li>
                    <li>Copie o valor numérico e cole no campo <strong>WhatsApp Business Account ID</strong>.</li>
                </ol>
            </div>
        </section>

        <hr class="my-4">

        {{-- TOPIC 6: App Secret --}}
        <section class="meta-help-topic mb-4" id="topic-meta-app-secret" data-keywords="app secret chave secreta aplicativo hmac sha256 assinatura seguranca">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="badge bg-dark px-2 py-1"><i class="bi bi-lock-fill me-1"></i>Segurança</span>
                <h5 class="mb-0 fw-bold">Meta App Secret (HMAC-SHA256)</h5>
            </div>

            <p class="text-body-secondary small">
                A <strong>Chave Secreta do Aplicativo</strong> protege seu servidor validando o cabeçalho criptográfico <code>X-Hub-Signature-256</code> em cada notificação de webhook, assegurando que o disparo veio genuinamente dos servidores da Meta.
            </p>

            <div class="border rounded p-3 bg-body-tertiary small mb-3">
                <strong class="d-block mb-1">Onde encontrar:</strong>
                <ol class="mb-0 ps-3">
                    <li class="mb-1">No Meta Developer Portal, clique no menu lateral esquerdo em <strong>Configurações do aplicativo</strong> &gt; <strong>Básico</strong> (App settings &gt; Basic).</li>
                    <li class="mb-1">Localize o campo <strong>Chave Secreta do Aplicativo</strong> (App Secret).</li>
                    <li class="mb-1">Clique em <strong>Mostrar</strong> (Show) e insira sua senha do Facebook se solicitado.</li>
                    <li>Copie a chave hexadecimal e cole no campo <strong>Meta App Secret</strong>.</li>
                </ol>
            </div>
        </section>

        <hr class="my-4">

        {{-- TOPIC 7: Webhook Oficial --}}
        <section class="meta-help-topic mb-4" id="topic-meta-webhook" data-keywords="webhook url callback verify token handshake retorno mensagens entregas">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="badge bg-success px-2 py-1"><i class="bi bi-arrow-repeat me-1"></i>Tempo Real</span>
                <h5 class="mb-0 fw-bold">Configurar o Webhook Oficial na Meta</h5>
            </div>

            <p class="text-body-secondary small">
                O Webhook é o canal pelo qual a Meta notifica nosso sistema sobre confirmações de entrega, leituras e respostas recebidas dos clientes.
            </p>

            <div class="card border mb-3 small">
                <div class="card-header bg-body-tertiary py-2">
                    <strong>Valores para preencher no painel Meta for Developers:</strong>
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <label class="form-label text-muted small fw-bold mb-1">URL de Retorno de Chamada (Callback URL):</label>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control font-monospace" value="{{ url('/api/v1/webhook') }}" readonly id="meta-help-webhook-url">
                            <button class="btn btn-outline-secondary btn-copy-meta-text" data-copy="{{ url('/api/v1/webhook') }}">Copiar</button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold mb-1">Verificar Token (Verify Token):</label>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control font-monospace" value="{{ config('whatsapp.meta_webhook_verify_token', 'kdkhost_meta_webhook_secret_2026') }}" readonly>
                            <button class="btn btn-outline-secondary btn-copy-meta-text" data-copy="{{ config('whatsapp.meta_webhook_verify_token', 'kdkhost_meta_webhook_secret_2026') }}">Copiar</button>
                        </div>
                    </div>

                    <h6 class="fw-bold mb-2">Campos para Assinar no Webhook (Manage Webhook Fields):</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-primary p-2"><i class="bi bi-check-circle me-1"></i>messages (obrigatório)</span>
                        <span class="badge bg-success p-2"><i class="bi bi-check-circle me-1"></i>message_template_status_update</span>
                    </div>
                </div>
            </div>
        </section>

        <hr class="my-4">

        {{-- TOPIC 8: Templates de Mensagens Meta --}}
        <section class="meta-help-topic mb-4" id="topic-meta-templates" data-keywords="templates message templates modelos aprovacao utility marketing botoes pix variaveis">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="badge bg-info text-dark px-2 py-1"><i class="bi bi-file-earmark-text-fill me-1"></i>Regras Meta</span>
                <h5 class="mb-0 fw-bold">Message Templates & Regras Oficiais</h5>
            </div>

            <p class="text-body-secondary small">
                Para iniciar conversas com clientes que não te enviaram mensagem nas últimas 24 horas, a Meta <strong>exige o uso de Message Templates pré-aprovados</strong>.
            </p>

            <div class="border rounded p-3 bg-body-tertiary small mb-3">
                <h6 class="fw-bold text-success mb-2"><i class="bi bi-check-all me-1"></i>Categorias Homologadas:</h6>
                <ul class="mb-3 ps-3">
                    <li><strong>UTILIDADE (Utility)</strong>: Cobranças, faturas, avisos de vencimento, comprovantes e confirmações. Tem aprovação prioritária pela IA da Meta (1 a 5 minutos) e o menor custo por conversa.</li>
                    <li><strong>MARKETING</strong>: Novidades, promoções, ofertas e reengajamento.</li>
                    <li><strong>AUTENTICAÇÃO</strong>: Códigos de verificação em duas etapas e recuperação de senha.</li>
                </ul>

                <h6 class="fw-bold text-primary mb-2"><i class="bi bi-braces me-1"></i>Variáveis Dinâmicas:</h6>
                <p class="mb-2">Utilize as chaves <code>&#123;&#123;1&#125;&#125;</code>, <code>&#123;&#123;2&#125;&#125;</code> para valores dinâmicos.</p>
                <div class="p-2 border rounded bg-body font-monospace mb-2 text-dark">
                    Olá &#123;&#123;1&#125;&#125;, sua fatura no valor de &#123;&#123;2&#125;&#125; vence em &#123;&#123;3&#125;&#125;. Segue seu código Pix para pagamento abaixo:
                </div>

                <h6 class="fw-bold text-dark mb-1"><i class="bi bi-hand-index-thumb me-1"></i>Botões de Ação Recomendados:</h6>
                <ul class="mb-0 ps-3">
                    <li><strong>Copiar Código (Copy Code)</strong>: Permite ao cliente copiar a chave Pix Copia e Cola com 1 toque no WhatsApp!</li>
                    <li><strong>URL Dinâmica</strong>: Botão para abrir o checkout seguro da fatura na Web.</li>
                </ul>
            </div>
        </section>

        <hr class="my-4">

        {{-- TOPIC 9: Qualidade do Numero & Tiers --}}
        <section class="meta-help-topic mb-4" id="topic-meta-quality-rating" data-keywords="qualidade quality rating tiers limites 1k 10k 100k escala warm up aquecimento">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="badge bg-success px-2 py-1"><i class="bi bi-speedometer2 me-1"></i>Escala</span>
                <h5 class="mb-0 fw-bold">Classificação de Qualidade & Limites de Envio (Tiers)</h5>
            </div>

            <p class="text-body-secondary small">
                A Meta monitora em tempo real a reputação do seu número de telefone e ajusta seu limite diário automaticamente:
            </p>

            <div class="table-responsive small mb-3">
                <table class="table table-bordered table-sm align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Nível (Tier)</th>
                            <th>Capacidade Diária</th>
                            <th>Como Atingir</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span class="badge bg-secondary">Tier 1</span></td>
                            <td><strong>1.000 clientes/24h</strong></td>
                            <td>Limite inicial de todo número verificado.</td>
                        </tr>
                        <tr>
                            <td><span class="badge bg-primary">Tier 2</span></td>
                            <td><strong>10.000 clientes/24h</strong></td>
                            <td>Dispare metade do Tier 1 em 7 dias mantendo Qualidade Alta.</td>
                        </tr>
                        <tr>
                            <td><span class="badge bg-success">Tier 3</span></td>
                            <td><strong>100.000 clientes/24h</strong></td>
                            <td>Atingido automaticamente conforme o volume cresce.</td>
                        </tr>
                        <tr>
                            <td><span class="badge bg-dark">Tier 4</span></td>
                            <td><strong>Ilimitado</strong></td>
                            <td>Contas de grande porte com verificação avançada.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="border rounded p-3 bg-body-tertiary small">
                <strong class="d-block mb-1">Cores da Qualidade (Quality Rating):</strong>
                <div class="d-flex flex-column gap-2 mt-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-success px-2 py-1">ALTA (GREEN)</span>
                        <span>Seu número tem excelente reputação. Poucos ou nenhum cliente marcaram como spam.</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-warning text-dark px-2 py-1">MÉDIA (YELLOW)</span>
                        <span>Alerta. Alguns clientes reportaram. Reduza o ritmo e use intervalos maiores.</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-danger px-2 py-1">BAIXA (RED)</span>
                        <span>Risco de restrição. Se permanecer em vermelho por 7 dias, o limite cai.</span>
                    </div>
                </div>
            </div>
        </section>

        <hr class="my-4">

        {{-- TOPIC 10: Anti-Ban & Boas Praticas --}}
        <section class="meta-help-topic mb-4" id="topic-meta-anti-ban" data-keywords="anti-ban antiban politicas seguranca janela 24h opt in opt out descadastro">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="badge bg-warning text-dark px-2 py-1"><i class="bi bi-shield-lock-fill me-1"></i>Proteção</span>
                <h5 class="mb-0 fw-bold">Políticas de Segurança e Anti-Ban</h5>
            </div>

            <ul class="list-group list-group-flush small mb-3">
                <li class="list-group-item bg-transparent px-0">
                    <strong>1. Janela de 24 Horas:</strong> Sempre que um cliente responde à sua mensagem, abre-se uma janela de 24 horas onde sua empresa pode enviar respostas livres em texto puro sem custo de nova conversa.
                </li>
                <li class="list-group-item bg-transparent px-0">
                    <strong>2. Descadastro Automático (Opt-Out):</strong> Se o cliente responder <code>PARAR</code>, <code>SAIR</code>, <code>CANCELAR</code> ou <code>STOP</code>, nosso sistema marca o opt-out imediatamente e bloqueia novos envios automáticos para respeitar as diretrizes da Meta.
                </li>
                <li class="list-group-item bg-transparent px-0">
                    <strong>3. Delay Humano:</strong> O disparador adiciona pequenos atrasos aleatórios entre as requisições, evitando picos de tráfego instantâneo.
                </li>
            </ul>
        </section>

        <hr class="my-4">

        {{-- TOPIC 11: Instancias de Tenants --}}
        <section class="meta-help-topic mb-3" id="topic-tenant-instance-setup" data-keywords="tenant instancia cliente multi-tenant waba propria linha empresa">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="badge bg-primary px-2 py-1"><i class="bi bi-buildings-fill me-1"></i>Multi-Tenant</span>
                <h5 class="mb-0 fw-bold">Configuração de Instância para Tenants</h5>
            </div>

            <p class="text-body-secondary small">
                Cada cliente ou empresa cadastrada no SaaS pode operar de duas formas:
            </p>

            <div class="row g-2 small mb-3">
                <div class="col-12">
                    <div class="border rounded p-3 bg-body-tertiary">
                        <strong class="text-primary d-block mb-1"><i class="bi bi-check2-circle me-1"></i>Modo Próprio (Recomendado para Empresas Médias/Grandes):</strong>
                        O tenant insere seu próprio <strong>Phone Number ID</strong>, <strong>WABA ID</strong> e <strong>Token Permanente</strong> gerados em seu próprio Meta Business Manager. Desta forma, a linha exibe o nome e marca oficial da empresa dele e os templates são geridos individualmente.
                    </div>
                </div>
                <div class="col-12">
                    <div class="border rounded p-3 bg-body-tertiary">
                        <strong class="text-success d-block mb-1"><i class="bi bi-cloud-check me-1"></i>Modo Compartilhado do Sistema:</strong>
                        Se o tenant deixar os campos de credenciais em branco, o sistema utiliza a instância principal da Meta configurada pelo Superadmin, facilitando a adesão imediata de novos clientes sem burocracia.
                    </div>
                </div>
            </div>
        </section>

    </div>

    {{-- Rodape com links de navegacao --}}
    <div class="offcanvas-footer border-top p-3 bg-body-tertiary d-flex align-items-center justify-content-between">
        <a href="https://developers.facebook.com/docs/whatsapp/cloud-api" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-book me-1"></i> Documentação Oficial Meta <i class="bi bi-box-arrow-up-right ms-1"></i>
        </a>
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="offcanvas">Fechar</button>
    </div>
</div>

{{-- Estilos e Script Global do Menu Deslizante de Ajuda --}}
<style>
.meta-help-nav-pills .btn.active {
    background-color: var(--bs-primary) !important;
    color: #fff !important;
    border-color: var(--bs-primary) !important;
}
.meta-help-topic {
    transition: background-color 0.3s ease;
    border-radius: 8px;
    padding: 4px;
}
.meta-help-topic.highlighted {
    background-color: color-mix(in srgb, var(--bs-primary) 12%, transparent);
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--bs-primary) 12%, transparent);
}
</style>

<script>
(function() {
    // Funcao global para abrir o Drawer no topico desejado
    window.openHelpDrawer = function(topicId) {
        const offcanvasEl = document.getElementById('metaHelpDrawer');
        if (!offcanvasEl) return;

        // Se bootstrap estiver disponivel
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
            }, 300);
        }
    };

    window.scrollToHelpTopic = function(topicId) {
        const cleanId = topicId.replace(/^#/, '').replace(/^topic-/, '');
        const target = document.getElementById('topic-' + cleanId) || document.getElementById(cleanId);
        
        // Atualiza os pills
        document.querySelectorAll('.meta-help-nav-pills [data-nav-topic]').forEach(function(pill) {
            if (pill.getAttribute('data-nav-topic') === cleanId) {
                pill.classList.add('active');
            } else {
                pill.classList.remove('active');
            }
        });

        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            target.classList.add('highlighted');
            setTimeout(function() {
                target.classList.remove('highlighted');
            }, 2500);
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

        // Busca em tempo real nos topicos
        const searchInput = document.getElementById('metaHelpSearch');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const query = this.value.toLowerCase().trim();
                const topics = document.querySelectorAll('.meta-help-topic');

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

        // Botoes de copiar texto dentro do drawer
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
