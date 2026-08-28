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

# --- 3. Кэш Symfony ---
# Каталог var/ не входит в образ (он в .dockerignore), создаём заново.
mkdir -p var/cache var/log
chown -R www-data:www-data var

# Прогреваем контейнер зависимостей заранее: без этого первый запрос
# после деплоя собирал бы кэш сам и отвечал несколько секунд.
php bin/console cache:clear --no-interaction
php bin/console cache:warmup --no-interaction

# --- 4. Миграции ---
# Выполняются на старте, а не в сборке: во время build базы ещё нет.
# --allow-no-migration: на первом деплое миграций ещё не существует,
# и без флага команда вернула бы ненулевой код.
if [ -n "$DATABASE_URL" ]; then
    php bin/console doctrine:migrations:migrate \
        --no-interaction \
        --allow-no-migration
fi

# --- 5. Запуск процессов ---
# php-fpm уходит в фон, nginx остаётся в foreground как PID 1:
# Docker отслеживает именно его, и остановка nginx роняет контейнер,
# что для хостинга и есть сигнал перезапустить сервис.
php-fpm --daemonize
exec nginx -g 'daemon off;'
