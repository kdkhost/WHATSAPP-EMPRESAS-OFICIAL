<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $contract->title }} | Assinado</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<style>
body {
    margin: 0;
    font-family: Inter, "Segoe UI", system-ui, -apple-system, sans-serif;
    background:
        radial-gradient(circle at top left, rgba(22,101,52,.10), transparent 28%),
        radial-gradient(circle at top right, rgba(15,23,42,.10), transparent 24%),
        #eef2f7;
    color: #162033;
}
.signed-shell {
    max-width: 1180px;
    margin: 0 auto;
    padding: 30px 18px 54px;
}
.signed-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    margin-bottom: 22px;
    padding: 20px 22px;
    color: #fff;
    border-radius: 26px;
    background: linear-gradient(135deg, #166534, #0f766e);
    box-shadow: 0 26px 52px rgba(22, 101, 52, .24);
}
.signed-banner h1 {
    margin: 0;
    font-size: 24px;
    font-weight: 800;
}
.signed-banner p {
    margin: 6px 0 0;
    font-size: 14px;
    opacity: .9;
}
.signed-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.signed-actions .btn {
    border-radius: 999px;
    padding-inline: 18px;
    font-weight: 700;
}
.signed-document {
    padding: 22px;
    border-radius: 28px;
    background: rgba(255,255,255,.94);
    border: 1px solid rgba(15,23,42,.08);
    box-shadow: 0 30px 56px rgba(15,23,42,.12);
    overflow: visible;
}
@media (max-width: 768px) {
    .signed-shell {
        padding: 12px 10px 40px;
    }
    .signed-banner {
        padding: 20px 16px;
        flex-direction: column;
        align-items: stretch;
        gap: 14px;
        text-align: center;
        border-radius: 18px;
    }
    .signed-actions {
        justify-content: center;
    }
    .signed-document {
        padding: 12px 8px;
        border-radius: 18px;
        overflow: visible;
    }
}
@media print {
    .signed-banner,
    .signed-actions {
        display: none !important;
    }
    body {
        background: #fff;
    }
    .signed-shell {
        max-width: none;
        padding: 0;
    }
    .signed-document {
        border: 0;
        box-shadow: none;
        border-radius: 0;
        padding: 0;
    }
}
</style>
</head>
<body>
<div class="signed-shell">
    <div class="signed-banner">
        <div>
            <h1>Contrato assinado com sucesso</h1>
            <p>Registro concluído em {{ $contract->signed_at?->timezone('America/Sao_Paulo')->format('d/m/Y \à\s H:i') }}. Este documento já inclui os dados da assinatura eletrônica.</p>
        </div>
        <div class="signed-actions">
            <a href="{{ route('public.contracts.download', $contract->access_token) }}" class="btn btn-light">Baixar cópia</a>
            <button type="button" onclick="window.print()" class="btn btn-outline-light">Imprimir</button>
        </div>
    </div>

    <section class="signed-document">
        {!! app(\App\Modules\Contracts\Services\ContractService::class)->renderDocumentHtml($contract, true) !!}
    </section>
</div>
</body>
</html>
