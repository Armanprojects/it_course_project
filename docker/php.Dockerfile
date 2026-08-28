# Базовый образ для Symfony API (dev).
FROM php:8.4-fpm-alpine

# pdo_pgsql — драйвер PostgreSQL для Doctrine.
# intl — нужен Symfony для локалей (i18n на этапе 8).
# zip/git — нужны Composer для установки пакетов.
RUN apk add --no-cache \
        libpq-dev \
        icu-dev \
        libzip-dev \
        git \
        unzip \
    && docker-php-ext-install \
        pdo_pgsql \
        intl \
        opcache \
    && apk del libpq-dev icu-dev libzip-dev \
    && apk add --no-cache libpq icu-libs libzip

# Composer ставим копированием из официального образа — так не тянем в образ PHP-установщик.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/backend

# Код монтируется томом в docker-compose (dev), поэтому COPY здесь не нужен.
# В прод-сборке (docker-compose.prod.yml) код копируется в образ.

EXPOSE 9000
CMD ["php-fpm"]
