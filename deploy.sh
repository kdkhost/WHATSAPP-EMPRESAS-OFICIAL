#!/bin/bash
# =============================================================
# DEPLOY AUTOMATICO - Sistema de Cobranca WhatsApp
# Para cPanel compartilhado (CloudLinux/LiteSpeed/Imunify360)
#
# COMO USAR:
#   1. Acesse: cPanel > Terminal
#   2. Cole e execute:
#      cd ~/public_html && bash deploy.sh
# =============================================================

set -e

echo ""
echo "=========================================="
echo "  DEPLOY - Sistema de Cobranca WhatsApp"
echo "=========================================="

DIR=$(pwd)
echo "[OK] Diretorio: $DIR"

# Verificar PHP
echo "[1/6] Verificando PHP..."
PHP_VERSION=$(php -v 2>/dev/null | head -1 | grep -oP '\\d+\\.\\d+' | head -1)
echo "       PHP $PHP_VERSION"

# Composer
echo "[2/6] Instalando dependencias..."
if command -v composer >/dev/null 2>&1; then
    composer install --no-dev --optimize-autoloader --no-interaction 2>&1 | tail -3
elif [ -f "$HOME/composer.phar" ]; then
    php "$HOME/composer.phar" install --no-dev --optimize-autoloader --no-interaction 2>&1 | tail -3
else
    curl -sS https://getcomposer.org/installer | php -- --install-dir="$HOME" --filename=composer.phar 2>/dev/null
    php "$HOME/composer.phar" install --no-dev --optimize-autoloader --no-interaction 2>&1 | tail -3
fi

# .env
echo "[3/6] Configurando .env..."
[ ! -f ".env" ] && cp .env.example .env

# APP_KEY
echo "[4/6] Gerando APP_KEY..."
php artisan key:generate --ansi --no-interaction 2>/dev/null || true

# Permissoes
echo "[5/6] Permissoes..."
chmod -R 775 storage bootstrap/cache 2>/dev/null || true
mkdir -p storage/app/public storage/app/backups storage/framework/{cache/data,sessions,views} storage/logs 2>/dev/null || true

# Verificar
echo "[6/6] Verificando..."
php artisan --version 2>/dev/null && echo "       [OK] Laravel funcionando"

echo ""
echo "=========================================="
echo "  PRONTO! Acesse: https://seudominio.com/install"
echo "=========================================="
