#!/bin/sh
# Точка входа продакшн-контейнера: подготовка Symfony + запуск php-fpm и nginx.
set -e

# Падаем при любой ошибке ниже, чтобы Render увидел неудачный деплой,
# а не поднял контейнер с неработающим приложением.

# --- 1. Порт от хостинга ---
# Render передаёт порт через $PORT и он меняется между деплоями,
# поэтому конфиг nginx собирается из шаблона на старте.
: "${PORT:=8080}"
export PORT
envsubst '${PORT}' \
    < /etc/nginx/templates/default.conf.template \
    > /etc/nginx/http.d/default.conf

# --- 2. Строка подключения к БД ---
# Render отдаёт connectionString без параметра serverVersion, а Doctrine
# без него при каждом запросе делает лишний запрос к БД, чтобы определить
# версию сервера. Дописываем параметр сами.
if [ -n "$DATABASE_URL_RAW" ] && [ -z "$DATABASE_URL" ]; then
    case "$DATABASE_URL_RAW" in
        *\?*) DATABASE_URL="${DATABASE_URL_RAW}&serverVersion=16&charset=utf8" ;;
        *)    DATABASE_URL="${DATABASE_URL_RAW}?serverVersion=16&charset=utf8" ;;
    esac
    export DATABASE_URL
fi

# --- 3. Адрес фронтенда ---
# OAuth-колбэк редиректит браузер обратно в SPA по абсолютному адресу.
# Render передаёт публичный адрес сервиса в RENDER_EXTERNAL_URL; локально
# и в других окружениях FRONTEND_URL задаётся вручную.
if [ -z "$FRONTEND_URL" ] && [ -n "$RENDER_EXTERNAL_URL" ]; then
    FRONTEND_URL="$RENDER_EXTERNAL_URL"
    export FRONTEND_URL
fi

# --- 4. Ключи JWT ---
# Каталог config/jwt/ не входит в образ (ключи не коммитятся), поэтому пара
# генерируется на старте из JWT_PASSPHRASE. Ключи живут в файловой системе
# контейнера: при рестарте они пересоздаются, и все выданные токены становятся
# недействительными — пользователям придётся войти заново.
if [ ! -f config/jwt/private.pem ]; then
    mkdir -p config/jwt
    openssl genpkey -out config/jwt/private.pem         -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096         -pass "pass:${JWT_PASSPHRASE}"
    openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout         -passin "pass:${JWT_PASSPHRASE}"
    chown -R www-data:www-data config/jwt
    chmod 640 config/jwt/private.pem
    chmod 644 config/jwt/public.pem
fi

# --- 5. Кэш Symfony ---
# Каталог var/ не входит в образ (он в .dockerignore), создаём заново.
mkdir -p var/cache var/log
chown -R www-data:www-data var

# Прогреваем контейнер зависимостей заранее: без этого первый запрос
# после деплоя собирал бы кэш сам и отвечал несколько секунд.
php bin/console cache:clear --no-interaction
php bin/console cache:warmup --no-interaction

# --- 6. Миграции ---
# Выполняются на старте, а не в сборке: во время build базы ещё нет.
# --allow-no-migration: на первом деплое миграций ещё не существует,
# и без флага команда вернула бы ненулевой код.
if [ -n "$DATABASE_URL" ]; then
    php bin/console doctrine:migrations:migrate \
        --no-interaction \
        --allow-no-migration
fi

# --- 7. Запуск процессов ---
# php-fpm уходит в фон, nginx остаётся в foreground как PID 1:
# Docker отслеживает именно его, и остановка nginx роняет контейнер,
# что для хостинга и есть сигнал перезапустить сервис.
php-fpm --daemonize
exec nginx -g 'daemon off;'
