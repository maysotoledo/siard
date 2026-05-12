FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader \
    --ignore-platform-req=ext-intl

FROM node:22-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json vite.config.js ./
COPY resources ./resources
COPY public ./public
COPY --from=vendor /app/vendor ./vendor

RUN npm ci && npm run build

FROM php:8.4-fpm-bookworm AS app

ENV APP_ENV=production \
    COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        git \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libzip-dev \
        nginx \
        poppler-utils \
        supervisor \
        unzip \
        zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_mysql \
        zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
COPY --from=vendor /app/vendor ./vendor

COPY . .
COPY --from=assets /app/public/build ./public/build

COPY docker/production/nginx.conf /etc/nginx/sites-available/default
COPY docker/production/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/production/php.ini /usr/local/etc/php/conf.d/99-siard.ini

RUN mkdir -p \
        storage/app/public \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && composer dump-autoload --no-dev --optimize \
    && php artisan package:discover --ansi \
    && php artisan filament:assets --ansi \
    && (php artisan storage:link --ansi || true) \
    && chown -R www-data:www-data storage bootstrap/cache \
    && rm -f /var/log/nginx/access.log /var/log/nginx/error.log \
    && ln -sf /dev/stdout /var/log/nginx/access.log \
    && ln -sf /dev/stderr /var/log/nginx/error.log

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
