# syntax=docker/dockerfile:1

ARG PHP_VERSION=8.4

FROM php:${PHP_VERSION}-fpm-bookworm AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev \
        libzip-dev \
        libicu-dev \
        unzip \
        git \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        bcmath \
        intl \
        zip \
        opcache \
        pcntl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY infra/docker/php/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini
COPY infra/docker/php/php.ini /usr/local/etc/php/conf.d/zz-tms.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

RUN groupadd -g 1000 tms && useradd -u 1000 -g tms -m tms

# ---------------------------------------------------------------------------
FROM base AS dependencies

COPY backend/tms-backend/composer.json backend/tms-backend/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY backend/tms-backend/ .
RUN composer dump-autoload --optimize --no-dev

# ---------------------------------------------------------------------------
# Local development target: full (dev+prod) Composer dependencies, source is
# bind-mounted over this by docker-compose.yml for live reload.
FROM base AS development

COPY backend/tms-backend/composer.json backend/tms-backend/composer.lock ./
RUN composer install --no-scripts --no-autoloader --prefer-dist

COPY backend/tms-backend/ .
RUN composer dump-autoload --optimize \
    && chown -R tms:tms /var/www/html

USER tms

CMD ["php-fpm"]

# ---------------------------------------------------------------------------
# Production target: no dev dependencies, code baked into the image, no bind mounts.
#
# Deliberately does NOT run artisan config:cache/route:cache/event:cache here:
# those would bake build-time environment values (this stage has no real
# APP_KEY/DB credentials/etc. available) into the image. Caching happens at
# container start instead, via entrypoint.sh, after real env vars are injected.
FROM base AS production

COPY infra/docker/php/opcache-production.ini /usr/local/etc/php/conf.d/zzz-opcache-production.ini
COPY infra/docker/php/entrypoint.sh /usr/local/bin/tms-entrypoint.sh
RUN chmod +x /usr/local/bin/tms-entrypoint.sh

COPY --from=dependencies /var/www/html /var/www/html
RUN chown -R tms:tms /var/www/html

USER tms

ENTRYPOINT ["/usr/local/bin/tms-entrypoint.sh"]
CMD ["php-fpm"]
