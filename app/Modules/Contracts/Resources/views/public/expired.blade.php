<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Contrato expirado</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
:root {
    --expired-bg: #eef2f7;
    --expired-ink: #111827;
    --expired-muted: #667085;
    --expired-panel: rgba(255,255,255,.94);
    --expired-border: rgba(15,23,42,.10);
    --expired-danger: #dc3545;
}
* { box-sizing: border-box; }
body {
    min-height: 100vh;
    margin: 0;
    display: grid;
    place-items: center;
    padding: 24px;
    color: var(--expired-ink);
    font-family: Inter, "Segoe UI", system-ui, -apple-system, sans-serif;
    background:
        radial-gradient(circle at top left, rgba(220, 53, 69, .12), transparent 30%),
        radial-gradient(circle at bottom right, rgba(15, 23, 42, .10), transparent 28%),
        var(--expired-bg);
}
.expired-card {
    width: min(100%, 620px);
    border: 1px solid var(--expired-border);
    border-radius: 28px;
    background: var(--expired-panel);
    box-shadow: 0 30px 70px rgba(15,23,42,.14);
    overflow: hidden;
}
.expired-head {
    padding: 28px 30px 18px;
    border-bottom: 1px solid var(--expired-border);
    background: linear-gradient(135deg, rgba(220,53,69,.10), rgba(15,23,42,.03));
}
.expired-icon {
    width: 58px;
    height: 58px;
    border-radius: 18px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 18px;
    color: var(--expired-danger);
    background: rgba(220,53,69,.12);
    font-size: 1.8rem;
}
.expired-head h1 {
    margin: 0;
    font-size: clamp(1.65rem, 4vw, 2.25rem);
    line-height: 1.1;
    font-weight: 800;
    letter-spacing: 0;
}
.expired-head p {
    margin: 10px 0 0;
    color: var(--expired-muted);
    line-height: 1.65;
}
.expired-body { padding: 24px 30px 30px; }
.expired-meta {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 22px;
}
.expired-meta div {
    border: 1px solid var(--expired-border);
    border-radius: 16px;
    padding: 14px 16px;
    background: #fff;
}
.expired-meta span,
.expired-meta strong {
    display: block;
}
.expired-meta span {
    color: var(--expired-muted);
    font-size: .75rem;
    text-transform: uppercase;
    letter-spacing: .06em;
    font-weight: 800;
    margin-bottom: 5px;
}
.expired-meta strong {
    overflow-wrap: anywhere;
}
.expired-note {
    margin: 0;
    padding: 16px 18px;
    border-radius: 18px;
    background: rgba(15,23,42,.05);
    color: var(--expired-muted);
    line-height: 1.65;
}
@media (prefers-color-scheme: dark) {
    :root {
        --expired-bg: #151a20;
        --expired-ink: #f8fafc;
        --expired-muted: rgba(248,250,252,.68);
        --expired-panel: rgba(31,36,42,.96);
        --expired-border: rgba(255,255,255,.10);
    }
    .expired-meta div { background: rgba(255,255,255,.04); }
    .expired-note { background: rgba(255,255,255,.06); }
}
@media (max-width: 575.98px) {
    .expired-meta { grid-template-columns: 1fr; }
    .expired-head,
    .expired-body { padding-left: 20px; padding-right: 20px; }
}
</style>
</head>
<body>
<main class="expired-card" role="main">
    <section class="expired-head">
        <span class="expired-icon"><i class="bi bi-hourglass-split"></i></span>
        <h1>Este contrato expirou</h1>
        <p>O prazo de assinatura acabou. Solicite um novo envio para revisar e assinar o documento com um link válido.</p>
    </section>
    <section class="expired-body">
        <div class="expired-meta">
            <div>
                <span>Contrato</span>
                <strong>{{ $contract->number ?? '-' }}</strong>
            </div>
            <div>
                <span>Expirou em</span>
                <strong>{{ $contract->expires_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i') ?? '-' }}</strong>
            </div>
            <div>
                <span>Documento</span>
                <strong>{{ $contract->title }}</strong>
            </div>
            <div>
                <span>Cliente</span>
                <strong>{{ $contract->customer?->name ?? 'Cliente' }}</strong>
            </div>
        </div>
        <p class="expired-note">
            Por segurança, links expirados não permitem assinatura. O conteúdo do contrato não foi alterado por esta página.
        </p>
    </section>
</main>
</body>
</html>
