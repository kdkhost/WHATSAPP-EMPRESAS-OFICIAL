@echo off
chcp 65001 > nul
echo ==============================================================================
echo [KDKhost SaaS] Publicando em:
echo https://github.com/kdkhost/WHATSAPP-EMPRESAS-OFICIAL.git
echo ==============================================================================
echo.
echo 1. Verificando status dos arquivos...
git status --short
echo.
echo 2. Adicionando todos os arquivos ao Git...
git add -A
echo.
echo 3. Executando commit humanizado em Português Brasileiro (v3.0.0)...
git commit -m "feat(v3.0.0): refatoração simultânea completa - WhatsApp Meta v21.0, Mercado Pago Transparente e Deploy Dual" -m "- MetaCloudApiClient com injeção dinâmica de Pix e Link de Fatura nos templates da Meta." -m "- Módulo MercadoPago com MercadoPagoPaymentService e webhook dinâmico para faturas e SaaS." -m "- Trava de segurança no config/database.php: SQLite exclusivo para dev local e proibido em produção." -m "- Comando da régua 'billing:process-schedule' com cancelamento atômico de disparos." -m "- Nova view 'resources/views/public/invoice.blade.php' com Checkout Transparente Mercado Pago JS v2." -m "- Deploy dual: app.js + cron-runner.js para cPanel e Dockerfile (PHP 8.4-FPM) + Nginx + MariaDB + Redis." -m "- CHANGELOG.md e README.md atualizados para a versão v3.0.0."
echo.
echo 4. Enviando código para o branch main do repositório remoto...
git push -u origin main
echo.
if %ERRORLEVEL% equ 0 (
    echo ==============================================================================
    echo SUCESSO: Publicado em https://github.com/kdkhost/WHATSAPP-EMPRESAS-OFICIAL.git
    echo ==============================================================================
) else (
    echo ==============================================================================
    echo Houve um aviso ou erro no envio. Verifique suas credenciais do GitHub.
    echo ==============================================================================
)
pause
