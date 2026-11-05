# ==============================================================================
# Dockerfile Multi-Stage Otimizado (PHP 8.4-FPM + Composer)
# Desenvolvido por Marcelo Brad / KDKhost - WHATSAPP-EMPRESAS-OFICIAL
# ==============================================================================

FROM php:8.4-fpm-alpine AS base

# Dependências de sistema e extensões de produção
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    zip \
    unzip \
    mariadb-client \
    icu-dev \
    oniguruma-dev \
    linux-headers \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        gd \
        zip \
        bcmath \
        intl \
        opcache \
        mbstring

# Instalar Composer oficial
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copiar arquivos de dependências
COPY composer.json composer.lock* ./

# Instalar dependências PHP em produção
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader || true

# Copiar o restante da aplicação
COPY . .

# Permissões adequadas
RUN mkdir -p storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000

CMD ["sh", "-c", "php artisan migrate --force && php-fpm"]
