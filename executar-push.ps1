# ==============================================================================
# Publicação no repositório WHATSAPP-EMPRESAS-OFICIAL
# ==============================================================================

Write-Host "Iniciando publicação..." -ForegroundColor Cyan

git add -A
git commit -m "feat(v3.0.0): refatoração simultânea completa - WhatsApp Meta v21.0, Mercado Pago Transparente e Deploy Dual"

git remote add origin https://github.com/kdkhost/WHATSAPP-EMPRESAS-OFICIAL.git 2>$null
git remote set-url origin https://github.com/kdkhost/WHATSAPP-EMPRESAS-OFICIAL.git

git branch -M main
git push -u origin main

Write-Host "`nProcesso concluído!" -ForegroundColor Green

