FROM dunglas/frankenphp:1.9.1-php8.4.15-alpine AS php-build

WORKDIR /app

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
    && apk add --no-cache --virtual .build-deps \
        autoconf \
        build-base \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        pdo_pgsql \
        pdo_sqlite \
        zip \
        intl \
        gd \
        bcmath \
    && apk del --no-network .build-deps \
    && rm -rf /var/cache/apk/*

FROM php-build AS composer

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

RUN mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && composer install --no-dev --optimize-autoloader --no-interaction --no-progress \
    && rm -rf vendor/*/*/tests vendor/*/*/test vendor/*/*/doc vendor/*/*/docs vendor/*/*/.git

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

FROM dunglas/frankenphp:1.9.1-php8.4.15-alpine AS php-runtime

RUN apk add --no-cache \
        libpng \
        freetype \
        icu-libs \
        libzip \
        postgresql-libs \
        sqlite-libs \
        oniguruma

WORKDIR /app

COPY --from=php-build /usr/local/lib/php/extensions /usr/local/lib/php/extensions
COPY --from=php-build /usr/local/etc/php/conf.d /usr/local/etc/php/conf.d

FROM php-runtime AS runtime

WORKDIR /app

COPY composer.json composer.lock ./
COPY ./docker/Caddyfile Caddyfile
COPY app app
COPY bootstrap bootstrap
COPY config config
COPY database database
COPY routes routes
COPY resources/views resources/views
COPY public public
COPY artisan artisan

COPY --from=composer /app/vendor /app/vendor
COPY --from=frontend /app/public/build /app/public/build

RUN mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

RUN rm -f bootstrap/cache/*.php || true

COPY ./docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

ENV APP_ENV=production \
    APP_DEBUG=false \
    SERVER_NAME=:80 \
    FRANKENPHP_CONFIG=/app/Caddyfile \
    BOOST_ENABLED=false

CMD ["/entrypoint.sh"]
