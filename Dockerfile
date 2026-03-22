# syntax=docker/dockerfile:1

FROM composer:2 AS vendor

WORKDIR /app

# Install PHP dependencies first for better layer caching.
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --optimize-autoloader \
    --no-scripts

# Copy application source.
COPY . .

# Refresh optimized autoload after full source copy.
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev


FROM php:8.2-cli-alpine AS runtime

WORKDIR /var/www/html

# Runtime extensions needed by Laravel.
RUN apk add --no-cache oniguruma sqlite-libs \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS oniguruma-dev sqlite-dev \
    && docker-php-ext-install mbstring pdo pdo_sqlite bcmath \
    && apk del .build-deps

COPY --from=vendor /app /var/www/html
COPY docker/start.sh /usr/local/bin/start-laravel

RUN chmod +x /usr/local/bin/start-laravel \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data /var/www/html

USER www-data

EXPOSE 10000

CMD ["start-laravel"]

