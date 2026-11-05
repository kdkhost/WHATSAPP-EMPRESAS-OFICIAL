# ==============================================================================
# Script de Commit Humanizado e Publicação Automática no Git
# Repositório: https://github.com/kdkhost/WHATSAPP-EMPRESAS-OFICIAL.git
# ==============================================================================

Write-Host "=====================================================================" -ForegroundColor Cyan
Write-Host " [KDKhost SaaS] Publicando em:" -ForegroundColor Cyan
Write-Host " https://github.com/kdkhost/WHATSAPP-EMPRESAS-OFICIAL.git" -ForegroundColor Green
Write-Host "=====================================================================" -ForegroundColor Cyan

# 1. Configurar remote origin explicitamente
git remote set-url origin https://github.com/kdkhost/WHATSAPP-EMPRESAS-OFICIAL.git 2>$null
if ($LASTEXITCODE -ne 0) {
    git remote add origin https://github.com/kdkhost/WHATSAPP-EMPRESAS-OFICIAL.git 2>$null
}

# 2. Verificar arquivos modificados
Write-Host "`n1. Verificando status dos arquivos..." -ForegroundColor Yellow
git status --short

# 3. Adicionar arquivos ao stage
Write-Host "`n2. Adicionando todos os arquivos ao Git..." -ForegroundColor Yellow
git add -A

# 4. Commit humanizado em português brasileiro
Write-Host "`n3. Executando commit humanizado em Português Brasileiro (v3.0.0)..." -ForegroundColor Yellow
$commitMsg = @"
feat(v3.0.0): refatoração simultânea completa - WhatsApp Meta v21.0, Mercado Pago Transparente e Deploy Dual

- Implementação completa de MetaCloudApiClient com injeção dinâmica de Pix e Link de Fatura nos templates.
- Criação do módulo MercadoPago com MercadoPagoPaymentService e webhook dinâmico para baixa de faturas e renovação de planos SaaS.
- Trava de segurança para banco de dados: SQLite homologado para dev local e bloqueado em produção (uso estrito de MySQL/MariaDB).
- Comando da régua de cobrança 'billing:process-schedule' executando D-3, D0, D+1, D+3, D+5 com cancelamento atômico ao pagar.
- Nova view responsiva 'resources/views/public/invoice.blade.php' com Checkout Transparente Mercado Pago JS v2, QR Code Pix e zero alerts.
- Configuração de deploy dual: app.js + cron-runner.js para cPanel/WHM e Dockerfile (PHP 8.4-FPM) + docker-compose.yml (App, Nginx, MariaDB, Redis).
- Atualização do CHANGELOG.md e README.md para a versão v3.0.0.
"@

git commit -m "$commitMsg"

# 5. Publicar automaticamente no Git remoto
Write-Host "`n4. Enviando alterações para o branch main de https://github.com/kdkhost/WHATSAPP-EMPRESAS-OFICIAL.git..." -ForegroundColor Green
git branch -M main
git push -u origin main

if ($LASTEXITCODE -eq 0) {
    Write-Host "`n=====================================================================" -ForegroundColor Cyan
    Write-Host " SUCESSO! Código publicado em https://github.com/kdkhost/WHATSAPP-EMPRESAS-OFICIAL.git" -ForegroundColor Cyan
    Write-Host "=====================================================================" -ForegroundColor Cyan
} else {
    Write-Host "`nHouve uma falha no git push. Verifique se você já está autenticado no Git/GitHub." -ForegroundColor Red
}
