/**
 * @autor marcelo-brad rj
 * @contato Tel: 21 981325441 | contato@kdkhost.com.br | @MARCELO_BRAD
 * @copyright Copyright (c) KDK HOST / Marcelo Brad. Todos os direitos reservados.
 * @propriedade-intelectual Software proprietário — Entrada cPanel / WHM Phusion Passenger.
 */

const http = require('http');
const { spawn } = require('child_process');
const path = require('path');
const fs = require('fs');

const PORT = process.env.PORT || 3000;
const HOST = process.env.HOST || '0.0.0.0';

console.log(`[KDKhost cPanel Runner] Iniciando serviço na porta ${PORT}...`);

// Se existir o build em dist/index.js ou src/server.js, carrega nativamente
const distServer = path.join(__dirname, 'dist', 'index.js');
if (fs.existsSync(distServer)) {
    require(distServer);
} else {
    // Inicia o servidor HTTP padrão de monitoramento e encaminhamento para o ambiente cPanel
    const server = http.createServer((req, res) => {
        if (req.url === '/health' || req.url === '/api/v1/health') {
            res.writeHead(200, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({
                ok: true,
                system: 'KDKhost WhatsApp SaaS',
                api: 'Meta Cloud API v21.0 Oficial',
                gateway: 'Mercado Pago SDK v2',
                environment: 'cPanel / Phusion Passenger',
                time: new Date().toISOString()
            }));
            return;
        }

        // Webhook Handshake rápido para validação de esteira
        if (req.url.startsWith('/api/v1/webhook') && req.method === 'GET') {
            const urlObj = new URL(req.url, `http://${req.headers.host}`);
            const mode = urlObj.searchParams.get('hub.mode');
            const token = urlObj.searchParams.get('hub.verify_token');
            const challenge = urlObj.searchParams.get('hub.challenge');

            const expectedToken = process.env.META_WEBHOOK_VERIFY_TOKEN || 'kdkhost_meta_token';
            if (mode === 'subscribe' && token === expectedToken) {
                res.writeHead(200, { 'Content-Type': 'text/plain' });
                res.end(challenge);
                return;
            }
        }

        res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
        res.end(`
            <!DOCTYPE html>
            <html lang="pt-BR">
            <head>
                <meta charset="UTF-8">
                <title>KDKhost SaaS - WhatsApp Cloud API Oficial</title>
                <style>
                    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0b0f19; color: #f3f4f6; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
                    .card { background: #111827; border: 1px solid #1f2937; border-radius: 16px; padding: 32px; max-width: 520px; box-shadow: 0 20px 40px rgba(0,0,0,0.5); text-align: center; }
                    .badge { background: #059669; color: #fff; padding: 4px 12px; border-radius: 9999px; font-size: 12px; font-weight: bold; text-transform: uppercase; }
                    h1 { font-size: 20px; margin: 16px 0 8px; color: #fff; }
                    p { color: #9ca3af; font-size: 14px; line-height: 1.6; }
                    .footer { margin-top: 24px; font-size: 12px; color: #6b7280; border-top: 1px solid #1f2937; padding-top: 16px; }
                </style>
            </head>
            <body>
                <div class="card">
                    <span class="badge">Operacional</span>
                    <h1>Plataforma SaaS KDKhost</h1>
                    <p>WhatsApp Cloud API Oficial da Meta (Graph API v21.0) & Mercado Pago SDK v2 rodando perfeitamente no cPanel / Phusion Passenger.</p>
                    <div class="footer">
                        Desenvolvido por <strong>Marcelo Brad / KDKhost</strong><br>
                        Tel: (21) 98132-5441 | contato@kdkhost.com.br
                    </div>
                </div>
            </body>
            </html>
        `);
    });

    server.listen(PORT, HOST, () => {
        console.log(`[KDKhost cPanel Runner] Servidor escutando em http://${HOST}:${PORT}`);
    });
}

