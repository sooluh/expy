# syntax=docker/dockerfile:1.7

###########
# PHP base with extensions
###########
FROM dunglas/frankenphp:1.9.1-php8.4.15-alpine AS php-base

WORKDIR /app

# Runtime deps + build deps for extensions
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
    && apk add --no-cache --virtual .build-deps \
        autoconf \
        build-base \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql pdo_sqlite zip intl gd \
    && apk del --no-network .build-deps \
    && rm -rf /var/cache/apk/*

###########
# Composer dependencies
###########
FROM php-base AS composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
COPY app app
COPY bootstrap bootstrap
COPY config config
COPY database database
COPY routes routes
COPY resources resources
COPY public public
COPY artisan artisan
COPY Caddyfile Caddyfile
COPY vite.config.* .
COPY postcss.config.* .
COPY tailwind.config.* .
COPY pnpm-lock.yaml pnpm-lock.yaml
COPY package.json package.json
RUN mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && composer install --no-dev --optimize-autoloader --no-interaction --no-progress

###########
# Frontend build
###########
FROM node:20-alpine AS frontend
WORKDIR /app
COPY package.json pnpm-lock.yaml ./
RUN corepack enable && pnpm install --frozen-lockfile
COPY --from=composer /app/vendor /app/vendor
COPY resources resources
COPY vite.config.* ./
COPY postcss.config.* ./
COPY tailwind.config.* ./
RUN pnpm run build

###########
# Runtime image
###########
FROM php-base AS runtime
WORKDIR /app

# Copy application source
COPY . .

# Clear any stale caches from the build context
RUN rm -f bootstrap/cache/*.php

# Bring in vendor and built assets
COPY --from=composer /app/vendor /app/vendor
COPY --from=frontend /app/public/build /app/public/build

# Prepare writable dirs and optimize Laravel
RUN mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && BOOST_ENABLED=false php artisan storage:link || true \
    && BOOST_ENABLED=false php artisan config:cache \
    && BOOST_ENABLED=false php artisan route:cache \
    && BOOST_ENABLED=false php artisan view:cache

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

ENV APP_ENV=production \
    APP_DEBUG=false \
    SERVER_NAME=:80 \
    FRANKENPHP_CONFIG=/app/Caddyfile \
    BOOST_ENABLED=false

CMD ["/entrypoint.sh"]
