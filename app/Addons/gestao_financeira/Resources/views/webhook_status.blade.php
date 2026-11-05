<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Webhook Open Finance | Status Operacional</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-deep: #0b0f19;
            --card-bg: rgba(22, 30, 49, 0.75);
            --card-border: rgba(255, 255, 255, 0.08);
            --accent-green: #10b981;
            --accent-blue: #3b82f6;
        }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-deep);
            background-image: 
                radial-gradient(at 0% 0%, rgba(16, 185, 129, 0.12) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(59, 130, 246, 0.12) 0px, transparent 50%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #f1f5f9;
            padding: 1.5rem;
        }
        .status-card {
            background: var(--card-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--card-border);
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            max-width: 620px;
            width: 100%;
            overflow: hidden;
        }
        .pulse-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 0.85rem;
            border-radius: 999px;
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #34d399;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
        .pulse-dot {
            width: 8px;
            height: 8px;
            background-color: #10b981;
            border-radius: 50%;
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            animation: pulse-ring 1.8s infinite cubic-bezier(0.66, 0, 0, 1);
        }
        @keyframes pulse-ring {
            0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
            100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }
        .icon-circle {
            width: 68px;
            height: 68px;
            border-radius: 20px;
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(59, 130, 246, 0.2));
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: #6ee7b7;
            margin: 0 auto 1.25rem;
        }
        .info-pill {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 14px;
            padding: 0.85rem 1rem;
            transition: all 0.2s ease;
        }
        .info-pill:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.12);
        }
        .url-box {
            background: rgba(11, 15, 25, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 0.75rem 1rem;
            font-family: monospace;
            font-size: 0.85rem;
            color: #93c5fd;
            word-break: break-all;
        }
        .btn-copy {
            background: #2563eb;
            color: #ffffff;
            border: none;
            padding: 0.65rem 1.25rem;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-copy:hover {
            background: #1d4ed8;
            color: #ffffff;
            transform: translateY(-1px);
        }
        .btn-back {
            background: rgba(255, 255, 255, 0.08);
            color: #e2e8f0;
            border: 1px solid rgba(255, 255, 255, 0.12);
            padding: 0.65rem 1.25rem;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-back:hover {
            background: rgba(255, 255, 255, 0.14);
            color: #ffffff;
        }
    </style>
</head>
<body>
    <div class="status-card p-4 p-md-5 text-center">
        <div class="icon-circle">
            <i class="bi bi-shield-check"></i>
        </div>

        <div class="mb-3">
            <span class="pulse-badge">
                <span class="pulse-dot"></span> Webhook Ativo e Operacional
            </span>
        </div>

        <h3 class="fw-bold mb-2 text-white">Endpoint do Open Finance</h3>
        <p class="text-secondary small mb-4">
            Esta URL é uma <strong>ponte de comunicação entre servidores</strong> (API). Ela foi criada para receber eventos em segundo plano da <strong>Pluggy</strong> sempre que houver novas transações bancárias no seu SaaS.
        </p>

        {{-- Informações Técnicas --}}
        <div class="row g-2 mb-4 text-start">
            <div class="col-6">
                <div class="info-pill">
                    <div class="text-muted small text-uppercase" style="font-size:0.68rem; font-weight:700;">Status HTTP</div>
                    <div class="fw-bold text-success"><i class="bi bi-check-circle-fill me-1"></i> 200 OK</div>
                </div>
            </div>
            <div class="col-6">
                <div class="info-pill">
                    <div class="text-muted small text-uppercase" style="font-size:0.68rem; font-weight:700;">Provedor</div>
                    <div class="fw-bold text-info"><i class="bi bi-bank me-1"></i> Pluggy.ai</div>
                </div>
            </div>
            <div class="col-6">
                <div class="info-pill">
                    <div class="text-muted small text-uppercase" style="font-size:0.68rem; font-weight:700;">Métodos Suportados</div>
                    <div class="fw-bold text-light">GET, POST, HEAD</div>
                </div>
            </div>
            <div class="col-6">
                <div class="info-pill">
                    <div class="text-muted small text-uppercase" style="font-size:0.68rem; font-weight:700;">Segurança</div>
                    <div class="fw-bold text-warning"><i class="bi bi-lock-fill me-1"></i> HTTPS / TLS</div>
                </div>
            </div>
        </div>

        {{-- Caixa da URL para cópia --}}
        <div class="text-start mb-4">
            <label class="form-label small text-muted fw-semibold">URL oficial do Webhook (Cole no painel da Pluggy):</label>
            <div class="url-box d-flex align-items-center justify-content-between gap-2">
                <span id="webhook-url">{{ url('api/gestao-financeira/open-finance-webhook') }}</span>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 justify-content-center">
            <button class="btn btn-copy" type="button" onclick="copyWebhookUrl()">
                <i class="bi bi-clipboard me-1" id="copy-icon"></i> <span id="copy-text">Copiar URL do Webhook</span>
            </button>
            <a href="/painel/gestao-financeira/contas" class="btn btn-back">
                <i class="bi bi-arrow-left me-1"></i> Ir para o Painel Financeiro
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function copyWebhookUrl() {
            const url = document.getElementById('webhook-url').innerText;
            navigator.clipboard.writeText(url).then(() => {
                const btnText = document.getElementById('copy-text');
                const btnIcon = document.getElementById('copy-icon');
                btnText.innerText = 'Copiado!';
                btnIcon.className = 'bi bi-check-lg me-1';

                Swal.fire({
                    icon: 'success',
                    title: 'URL Copiada!',
                    text: 'Agora basta colar no campo Webhooks do Dashboard da Pluggy.',
                    timer: 2500,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });

                setTimeout(() => {
                    btnText.innerText = 'Copiar URL do Webhook';
                    btnIcon.className = 'bi bi-clipboard me-1';
                }, 3000);
            });
        }
    </script>
</body>
</html>
