# Продакшн-образ для Render: nginx + php-fpm в одном контейнере.
#
# Render в бесплатном плане даёт один веб-сервис на образ, поэтому
# разделять nginx и php-fpm по контейнерам здесь нельзя — они общаются
# через 127.0.0.1:9000 внутри одного контейнера.

# ---------------------------------------------------------------------
# Стадия 1: сборка фронтенда
# ---------------------------------------------------------------------
FROM node:22-alpine AS frontend-build

WORKDIR /build

# Сначала только манифесты: слой с npm ci кэшируется и не пересобирается,
# пока не изменится package-lock.json.
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci

COPY frontend/ ./
RUN npm run build

# ---------------------------------------------------------------------
# Стадия 2: зависимости бэкенда
# ---------------------------------------------------------------------
FROM composer:2 AS backend-deps

WORKDIR /build

COPY backend/composer.json backend/composer.lock backend/symfony.lock ./
# --no-scripts: скрипты Symfony Flex требуют исходников, которых здесь ещё нет.
# --no-dev: в прод не тянем phpunit и профайлер.
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader

# ---------------------------------------------------------------------
# Стадия 3: рантайм
# ---------------------------------------------------------------------
FROM php:8.4-fpm-alpine

# libpq-dev вместо метапакета postgresql-dev: последний в свежей Alpine
# тянет неудовлетворимые зависимости, а для pdo_pgsql достаточно
# заголовков libpq. Dev-пакеты удаляем сразу после сборки расширений,
# оставляя только рантайм-библиотеки — так образ заметно меньше.
RUN apk add --no-cache \
        nginx \
        gettext \
        libpq-dev \
        icu-dev \
    && docker-php-ext-install \
        pdo_pgsql \
        intl \
        opcache \
    && apk del libpq-dev icu-dev \
    && apk add --no-cache libpq icu-libs

# Продакшн-настройки PHP: OPcache без проверки времени модификации
# файлов — код внутри образа неизменен, перечитывать его незачем.
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && { \
        echo 'opcache.enable=1'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.max_accelerated_files=20000'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.preload_user=www-data'; \
    } > "$PHP_INI_DIR/conf.d/opcache.ini"

# php-fpm слушает TCP-порт вместо unix-сокета: так nginx в этом же
# контейнере обращается к нему по 127.0.0.1:9000.
RUN echo 'listen = 127.0.0.1:9000' > /usr/local/etc/php-fpm.d/zz-listen.conf

WORKDIR /var/www/backend

# Код бэкенда и установленные зависимости.
COPY backend/ ./
COPY --from=backend-deps /build/vendor ./vendor

# Собранная статика фронтенда.
COPY --from=frontend-build /build/dist /var/www/frontend/dist

# Конфиг nginx кладём как шаблон: entrypoint подставит в него $PORT.
COPY docker/nginx.prod.conf /etc/nginx/templates/default.conf.template
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# var/ должен быть доступен на запись — туда пишутся кэш и логи Symfony.
RUN mkdir -p var/cache var/log \
    && chown -R www-data:www-data var

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    PORT=8080

EXPOSE 8080

ENTRYPOINT ["/entrypoint.sh"]
