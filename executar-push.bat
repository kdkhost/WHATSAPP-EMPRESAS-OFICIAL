@echo off
chcp 65001 > nul
echo ==============================================================================
echo [KDKhost] Publicando em https://github.com/kdkhost/WHATSAPP-EMPRESAS-OFICIAL.git
echo ==============================================================================
echo.

git add -A
git commit -m "feat(v3.0.0): refatoração simultânea completa - WhatsApp Meta v21.0, Mercado Pago Transparente e Deploy Dual"

git remote add origin https://github.com/kdkhost/WHATSAPP-EMPRESAS-OFICIAL.git 2>nul
git remote set-url origin https://github.com/kdkhost/WHATSAPP-EMPRESAS-OFICIAL.git

git branch -M main
git push -u origin main

echo.
echo ==============================================================================
echo Publicação finalizada!
echo ==============================================================================
pause

