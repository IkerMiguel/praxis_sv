# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Dependencias de Composer (etapa independiente para aprovechar la caché).
# ---------------------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
        --no-interaction \
        --no-progress \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --ignore-platform-reqs

# ---------------------------------------------------------------------------
# Imagen de la API: PHP 8.4 + PHP-FPM + Nginx (incluye pdo_pgsql y pcntl).
# ---------------------------------------------------------------------------
FROM serversideup/php:8.4-fpm-nginx AS app

ARG PHP_EXTRA_EXTENSIONS="pgsql intl bcmath"

USER root

RUN if [ -n "${PHP_EXTRA_EXTENSIONS}" ]; then install-php-extensions ${PHP_EXTRA_EXTENSIONS}; fi

COPY --chmod=755 docker/entrypoint.d/ /etc/entrypoint.d/

USER www-data

WORKDIR /var/www/html

COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=vendor /app/vendor ./vendor

RUN composer dump-autoload --optimize --no-interaction \
    && mkdir -p storage/app/private/evidencias storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

ENV PHP_OPCACHE_ENABLE=1 \
    SHOW_WELCOME_MESSAGE=false

EXPOSE 8080
