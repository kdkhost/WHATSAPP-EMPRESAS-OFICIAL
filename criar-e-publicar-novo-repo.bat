@echo off
chcp 65001 > nul
echo ==============================================================================
echo [KDKhost] Publicando no repositório: WHATSAPP-EMPRESAS-OFICIAL
echo ==============================================================================
echo.

powershell -ExecutionPolicy Bypass -File .\criar-e-publicar-novo-repo.ps1

echo.
pause

