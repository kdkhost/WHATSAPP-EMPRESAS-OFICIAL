<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Fatura #{{ $invoice->number ?? $invoice->id }} - {{ $companyName ?? 'KDKhost' }}</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5.3 & Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- SweetAlert2 & Toastify -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

    <!-- Mercado Pago SDK JS v2 -->
    <script src="https://sdk.mercadopago.com/js/v2"></script>

    <style>
        :root {
            --bg-page: #0b0f19;
            --bg-card: #111827;
            --bg-inset: #1a2234;
            --border-color: #1f293d;
            --primary-color: #3b82f6;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-page);
            color: #f3f4f6;
            min-height: 100vh;
            padding: 24px 12px;
        }

        .invoice-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
            max-width: 680px;
            margin: 0 auto;
            overflow: hidden;
        }

        .tenant-header {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(16, 185, 129, 0.05));
            border-bottom: 1px solid var(--border-color);
            padding: 24px;
        }

        .tenant-logo {
            max-height: 48px;
            max-width: 180px;
            object-fit: contain;
        }

        .qr-container {
            background: #ffffff;
            padding: 16px;
            border-radius: 16px;
            display: inline-block;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        }

        .qr-container img {
            width: 220px;
            height: 220px;
            object-fit: contain;
        }

        .pix-code-input {
            background: var(--bg-inset);
            border: 1px solid var(--border-color);
            color: #93c5fd;
            font-family: monospace;
            font-size: 0.85rem;
        }

        .nav-pills .nav-link {
            color: #9ca3af;
            border-radius: 12px;
            padding: 10px 20px;
            font-weight: 600;
        }

        .nav-pills .nav-link.active {
            background-color: var(--primary-color);
            color: #ffffff;
        }

        .form-control, .form-select {
            background-color: var(--bg-inset);
            border: 1px solid var(--border-color);
            color: #ffffff;
            border-radius: 10px;
        }

        .form-control:focus, .form-select:focus {
            background-color: var(--bg-inset);
            border-color: var(--primary-color);
            color: #ffffff;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25);
        }
    </style>
</head>
<body>

<div class="container">
    <div class="invoice-card">
        <!-- Header da Empresa / Tenant -->
        <div class="tenant-header d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                @if(!empty($companyLogo))
                    <img src="{{ $companyLogo }}" alt="{{ $companyName }}" class="tenant-logo">
                @else
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold fs-4" style="width: 48px; height: 48px;">
                        <i class="bi bi-receipt"></i>
                    </div>
                @endif
                <div>
                    <h5 class="mb-0 fw-bold">{{ $companyName ?? 'KDKhost Cobranças' }}</h5>
                    <small class="text-muted"><i class="bi bi-shield-check text-success"></i> Pagamento Seguro Verificado</small>
                </div>
            </div>

            <div>
                @php
                    $status = $invoice->status ?? 'pending';
                    $badgeClass = match($status) {
                        'paid' => 'bg-success',
                        'overdue' => 'bg-danger',
                        'canceled' => 'bg-secondary',
                        default => 'bg-warning text-dark'
                    };
                    $statusLabel = match($status) {
                        'paid' => 'PAGA',
                        'overdue' => 'VENCIDA',
                        'canceled' => 'CANCELADA',
                        default => 'PENDENTE'
                    };
                @endphp
                <span id="invoice-status-badge" class="badge {{ $badgeClass }} px-3 py-2 rounded-pill fs-6">{{ $statusLabel }}</span>
            </div>
        </div>

        <div class="p-4">
            <!-- Dados Resumidos da Fatura -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6">
                    <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 0.75rem;">Fatura Destinada a</small>
                    <strong class="fs-6 text-white">{{ $customerName ?? ($invoice->client_name ?? 'Cliente') }}</strong>
                    @if(!empty($customerDoc ?? $invoice->client_document))
                        <small class="text-muted d-block">{{ $customerDoc ?? $invoice->client_document }}</small>
                    @endif
                </div>
                <div class="col-sm-6 text-sm-end">
                    <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 0.75rem;">Data de Vencimento</small>
                    <strong class="fs-6 text-white">
                        <i class="bi bi-calendar-event me-1 text-primary"></i>
                        {{ !empty($invoice->due_date) ? \Carbon\Carbon::parse($invoice->due_date)->format('d/m/Y') : date('d/m/Y') }}
                    </strong>
                    <div class="fs-4 fw-extrabold text-success mt-1">
                        R$ {{ number_format((float) ($totalPix ?? $invoice->amount), 2, ',', '.') }}
                    </div>
                </div>
            </div>

            @if($status === 'paid')
                <div class="alert alert-success text-center p-4 rounded-4 mb-0">
                    <i class="bi bi-check-circle-fill text-success fs-1 d-block mb-2"></i>
                    <h4 class="fw-bold">Fatura Paga com Sucesso!</h4>
                    <p class="mb-0 text-muted">Agradecemos a sua preferência. O comprovante foi processado e validado automaticamente.</p>
                </div>
            @else
                <!-- Abas de Pagamento (Pix Instantâneo vs Cartão de Crédito) -->
                <ul class="nav nav-pills nav-fill gap-2 mb-4" id="pills-tab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active d-flex align-items-center justify-content-center gap-2" id="pills-pix-tab" data-bs-toggle="pill" data-bs-target="#pills-pix" type="button" role="tab">
                            <i class="bi bi-qr-code"></i> Pix Instantâneo
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link d-flex align-items-center justify-content-center gap-2" id="pills-card-tab" data-bs-toggle="pill" data-bs-target="#pills-card" type="button" role="tab">
                            <i class="bi bi-credit-card-2-front"></i> Cartão de Crédito
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="pills-tabContent">
                    <!-- ABA PIX -->
                    <div class="tab-pane fade show active text-center" id="pills-pix" role="tabpanel">
                        <div class="qr-container mb-3">
                            @if(!empty($invoice->qr_code_base64))
                                <img src="{{ $invoice->qr_code_base64 }}" alt="QR Code Pix" id="img-qr-code">
                            @else
                                <div id="qr-placeholder" class="d-flex align-items-center justify-content-center text-dark flex-column" style="width: 220px; height: 220px;">
                                    <div class="spinner-border text-primary mb-2" role="status"></div>
                                    <small class="fw-bold">Gerando QR Code...</small>
                                </div>
                                <img src="" alt="QR Code Pix" id="img-qr-code" class="d-none">
                            @endif
                        </div>

                        <p class="text-muted small mb-3">
                            Abra o app do seu banco, escolha <strong>Pagar via Pix</strong> e escaneie o código acima ou copie o código abaixo:
                        </p>

                        <div class="input-group mb-3">
                            <input type="text" class="form-control pix-code-input" id="input-pix-code" readonly value="{{ $invoice->pix_code ?? 'Carregando código Pix...' }}">
                            <button class="btn btn-success px-4 fw-bold" type="button" id="btn-copy-pix">
                                <i class="bi bi-clipboard-check me-1"></i> Copiar Pix
                            </button>
                        </div>
                    </div>

                    <!-- ABA CARTÃO DE CRÉDITO TRANSPARENTE -->
                    <div class="tab-pane fade" id="pills-card" role="tabpanel">
                        <form id="form-checkout-card">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label small text-muted">Número do Cartão</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-dark border-secondary text-muted"><i class="bi bi-credit-card"></i></span>
                                        <input type="text" class="form-control" id="cardNumber" placeholder="0000 0000 0000 0000" maxlength="19" required>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label small text-muted">Nome Impresso no Cartão</label>
                                    <input type="text" class="form-control" id="cardholderName" placeholder="Como impresso no cartão" required>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label small text-muted">Mês Venc.</label>
                                    <input type="text" class="form-control" id="cardExpirationMonth" placeholder="MM" maxlength="2" required>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label small text-muted">Ano Venc.</label>
                                    <input type="text" class="form-control" id="cardExpirationYear" placeholder="AA" maxlength="2" required>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label small text-muted">CVV</label>
                                    <input type="text" class="form-control" id="securityCode" placeholder="123" maxlength="4" required>
                                </div>

                                <div class="col-12">
                                    <label class="form-label small text-muted">Número de Parcelas</label>
                                    <select class="form-select" id="installments">
                                        <option value="1">1x de R$ {{ number_format((float) ($totalPix ?? $invoice->amount), 2, ',', '.') }} (Sem juros)</option>
                                        <option value="2">2x de R$ {{ number_format(((float) ($totalPix ?? $invoice->amount))/2, 2, ',', '.') }}</option>
                                        <option value="3">3x de R$ {{ number_format(((float) ($totalPix ?? $invoice->amount))/3, 2, ',', '.') }}</option>
                                        <option value="6">6x de R$ {{ number_format(((float) ($totalPix ?? $invoice->amount))/6, 2, ',', '.') }}</option>
                                        <option value="12">12x de R$ {{ number_format(((float) ($totalPix ?? $invoice->amount))/12, 2, ',', '.') }}</option>
                                    </select>
                                </div>

                                <div class="col-12">
                                    <label class="form-label small text-muted">CPF do Titular</label>
                                    <input type="text" class="form-control" id="docNumber" placeholder="000.000.000-00" maxlength="14" required>
                                </div>

                                <div class="col-12 mt-4">
                                    <button type="submit" class="btn btn-primary w-100 py-3 fw-bold fs-6" id="btn-pay-card">
                                        <i class="bi bi-lock-fill me-1"></i> Pagar R$ {{ number_format((float) ($totalPix ?? $invoice->amount), 2, ',', '.') }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        </div>

        <div class="card-footer bg-transparent border-top border-secondary-subtle p-3 text-center text-muted small">
            Ambiente Seguro KDKhost &bull; Processado via Mercado Pago SDK v2 Oficial &bull; WhatsApp Cloud API
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
(function() {
    const invoiceToken = "{{ $invoice->token ?? $invoice->webhook_token ?? '' }}";
    const mpPublicKey = "{{ $mpPublicKey ?? env('MERCADOPAGO_PUBLIC_KEY', '') }}";

    // Notificação Toastify sem alert nativo
    function notify(text, type = 'success') {
        Toastify({
            text: text,
            duration: 3500,
            gravity: 'top',
            position: 'right',
            style: {
                background: type === 'success' ? '#10b981' : '#ef4444',
                borderRadius: '8px',
                fontWeight: '600'
            }
        }).showToast();
    }

    // Copiar código Pix
    document.getElementById('btn-copy-pix')?.addEventListener('click', async function() {
        const input = document.getElementById('input-pix-code');
        if (!input || !input.value) return;

        try {
            await navigator.clipboard.writeText(input.value);
            notify('Código Pix Copia e Cola copiado com sucesso!', 'success');
        } catch(e) {
            input.select();
            document.execCommand('copy');
            notify('Código Pix copiado!', 'success');
        }
    });

    // Submissão de Cartão com Mercado Pago JS v2
    document.getElementById('form-checkout-card')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('btn-pay-card');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processando pagamento...';

        try {
            const cardNumber = document.getElementById('cardNumber').value.replace(/\D/g, '');
            const cardholderName = document.getElementById('cardholderName').value;
            const expMonth = document.getElementById('cardExpirationMonth').value;
            const expYear = document.getElementById('cardExpirationYear').value;
            const securityCode = document.getElementById('securityCode').value;
            const docNumber = document.getElementById('docNumber').value.replace(/\D/g, '');
            const installments = document.getElementById('installments').value;

            // Envia para o backend para processamento transparente
            const response = await fetch(`/fatura/${invoiceToken}/cartao`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({
                    card_number: cardNumber,
                    cardholder_name: cardholderName,
                    expiration_month: expMonth,
                    expiration_year: expYear,
                    security_code: securityCode,
                    doc_number: docNumber,
                    installments: installments
                })
            });

            const data = await response.json();
            if (data.ok) {
                Swal.fire({
                    title: 'Pagamento Aprovado!',
                    text: 'Seu pagamento com cartão foi confirmado com sucesso.',
                    icon: 'success',
                    confirmButtonColor: '#10b981'
                }).then(() => {
                    window.location.reload();
                });
            } else {
                Swal.fire({
                    title: 'Pagamento Recusado',
                    text: data.message || 'Não foi possível processar seu cartão. Verifique os dados ou pague via Pix.',
                    icon: 'error',
                    confirmButtonColor: '#ef4444'
                });
            }
        } catch(err) {
            Swal.fire({
                title: 'Erro de Conexão',
                text: 'Falha ao comunicar com o servidor de pagamento.',
                icon: 'error',
                confirmButtonColor: '#ef4444'
            });
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-lock-fill me-1"></i> Confirmar Pagamento';
        }
    });

    // Polling assíncrono para verificar se a fatura foi paga enquanto a página está aberta
    let pollInterval = setInterval(async function() {
        if (!invoiceToken) return;
        try {
            const res = await fetch(`/fatura/${invoiceToken}/status`);
            const statusData = await res.json();
            if (statusData.paid || statusData.status === 'paid') {
                clearInterval(pollInterval);
                Swal.fire({
                    title: 'Pagamento Confirmado!',
                    text: 'Identificamos o pagamento da sua fatura em tempo real.',
                    icon: 'success',
                    confirmButtonColor: '#10b981'
                }).then(() => {
                    window.location.reload();
                });
            }
        } catch(e) {}
    }, 4000);
})();
</script>
</body>
</html>

