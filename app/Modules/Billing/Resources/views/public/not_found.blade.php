<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fatura não encontrada</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background-color: #0b1329;
            color: #e2e8f0;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card-custom {
            background: #131f3d;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
            max-width: 480px;
            width: 100%;
            padding: 36px 28px;
            text-align: center;
        }
        .icon-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 38px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="card-custom">
        <div class="icon-circle">
            <i class="bi bi-exclamation-triangle"></i>
        </div>
        <h4 class="fw-bold mb-2">Fatura Não Encontrada</h4>
        <p class="text-secondary small mb-4">
            O link informado é inválido, expirou ou a cobrança foi cancelada pelo emissor.
        </p>
        <p class="text-muted small">
            Caso precise de ajuda, entre em contato diretamente com a empresa emissora da cobrança.
        </p>
    </div>
</body>
</html>
