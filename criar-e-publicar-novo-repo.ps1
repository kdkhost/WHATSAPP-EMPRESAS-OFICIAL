# ==============================================================================
# Script de Criação e Publicação no Repositório WHATSAPP-EMPRESAS-OFICIAL
# Desenvolvido para Marcelo Brad / KDKhost
# ==============================================================================

Write-Host "=====================================================================" -ForegroundColor Cyan
Write-Host " [KDKhost] Publicando no repositório: WHATSAPP-EMPRESAS-OFICIAL" -ForegroundColor Cyan
Write-Host "=====================================================================" -ForegroundColor Cyan

# 1. Verifica se o GitHub CLI (gh) está instalado para criar o repositório na nuvem automaticamente
$ghInstalled = Get-Command gh -ErrorAction SilentlyContinue

if ($ghInstalled) {
    Write-Host "`nDetectado GitHub CLI (gh)! Verificando autenticação..." -ForegroundColor Yellow
    gh auth status
    if ($LASTEXITCODE -eq 0) {
        Write-Host "Criando/vinculando repositório WHATSAPP-EMPRESAS-OFICIAL no GitHub via gh cli..." -ForegroundColor Green
        gh repo create WHATSAPP-EMPRESAS-OFICIAL --public --source=. --remote=origin --push
        if ($LASTEXITCODE -eq 0) {
            Write-Host "`nRepositório criado e código publicado com sucesso via GitHub CLI!" -ForegroundColor Cyan
            exit 0
        }
    }
}

# 2. Configuração manual do novo remote Git caso não use gh cli
Write-Host "`nConfigurando remote do novo repositório WHATSAPP-EMPRESAS-OFICIAL..." -ForegroundColor Yellow
$newRemoteUrl = "https://github.com/kdkhost/WHATSAPP-EMPRESAS-OFICIAL.git"

git remote set-url origin $newRemoteUrl 2>$null
if ($LASTEXITCODE -ne 0) {
    git remote add origin $newRemoteUrl 2>$null
}

# 3. Adicionar arquivos e verificar status
Write-Host "`nAdicionando arquivos modificados ao Git..." -ForegroundColor Yellow
git add -A
git status --short

# 4. Commit humanizado em português brasileiro
Write-Host "`nExecutando commit humanizado..." -ForegroundColor Yellow
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

# 5. Renomeia branch para main se necessário e faz o push
Write-Host "`nEnviando código para o novo repositório remoto (WHATSAPP-EMPRESAS-OFICIAL)..." -ForegroundColor Green
git branch -M main
git push -u origin main

if ($LASTEXITCODE -eq 0) {
    Write-Host "`n=====================================================================" -ForegroundColor Cyan
    Write-Host " SUCESSO: Código publicado em WHATSAPP-EMPRESAS-OFICIAL!" -ForegroundColor Cyan
    Write-Host " URL: $newRemoteUrl" -ForegroundColor Cyan
    Write-Host "=====================================================================" -ForegroundColor Cyan
} else {
    Write-Host "`n[Atenção] Se o repositório ainda não foi criado na sua conta do GitHub:" -ForegroundColor Yellow
    Write-Host "1. Acesse: https://github.com/new" -ForegroundColor Yellow
    Write-Host "2. Crie o repositório vazio com o nome: WHATSAPP-EMPRESAS-OFICIAL" -ForegroundColor Yellow
    Write-Host "3. Execute novamente este script para enviar o código: .\criar-e-publicar-novo-repo.ps1" -ForegroundColor Yellow
}
