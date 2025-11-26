FROM dunglas/frankenphp:1.9.1-php8.4.15-alpine AS base

WORKDIR /app

# Install system dependencies
RUN apk add --no-cache \
        git \
        unzip \
        curl \
        bash \
        libpng-dev \
        jpeg-dev \
        freetype-dev \
        oniguruma-dev \
        icu-dev \
        libzip-dev \
        postgresql-dev \
        sqlite-dev \
        whois \
        autoconf \
        build-base \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql pdo_sqlite zip intl gd \
    && rm -rf /var/cache/apk/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy source
COPY . .

# Prepare writable directories
RUN mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

# Optimize Laravel
RUN php artisan storage:link || true \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

# Caddy / FrankenPHP config
COPY Caddyfile /app/Caddyfile

EXPOSE 80

ENV APP_ENV=production \
    APP_DEBUG=false \
    SERVER_NAME=:80 \
    FRANKENPHP_CONFIG=/app/Caddyfile

CMD ["sh", "-c", "php artisan migrate --force && php artisan queue:work --tries=3 --timeout=120 & exec frankenphp run --config /app/Caddyfile"]
