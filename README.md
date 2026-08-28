# CV Management System

Курсовой проект: веб-приложение для управления резюме (позиции, атрибуты, автогенерация CV).

**Стек:** PHP 8.4 + Symfony 7 · PostgreSQL 16 · Doctrine ORM · Docker Compose · React 19 + Vite + TypeScript + Bootstrap 5

План реализации — в [IMPLEMENTATION_PLAN.md](IMPLEMENTATION_PLAN.md).

---

## Запуск

```bash
docker compose up -d
```

Приложение: <http://localhost:8080>
API: <http://localhost:8080/api/health>

Первый запуск занимает несколько минут (сборка образов). Если каталог
`frontend/node_modules` пуст внутри контейнера — установить зависимости в том:

```bash
docker compose run --rm --no-deps --entrypoint sh frontend -c "npm install"
```

Остановить: `docker compose down`
Остановить и удалить данные БД: `docker compose down -v`

---

## Архитектура

```
браузер :8080
     │
   nginx ──── /api/*  ──→ php-fpm (Symfony) ──→ PostgreSQL :5432
     │
     └─────── /*      ──→ Vite dev server :5173
```

**Почему единая точка входа.** Фронтенд и API живут на одном origin, поэтому
CORS не нужен ни в dev, ни в prod. Это же снимает будущие сложности с
httpOnly-cookie для JWT: cookie на `localhost:8080` уходит и на страницу, и в
API без настройки `SameSite`/`credentials`.

**Сервисы:**

| Сервис | Роль | Порт наружу |
|---|---|---|
| `nginx` | единая точка входа, маршрутизация | 8080 |
| `php` | Symfony API (php-fpm) | — |
| `frontend` | Vite dev server (HMR) | — |
| `db` | PostgreSQL 16 | 5432 |

Порт 5432 проброшен наружу для подключения из DataGrip/DBeaver.
Реквизиты dev-БД: `app` / `app` / база `cv_app`.

---

## Деплой на Render

Конфигурация лежит в [render.yaml](render.yaml) (Blueprint) — он описывает
веб-сервис и базу PostgreSQL.

**Порядок:**

1. Запушить репозиторий на GitHub.
2. Render Dashboard → **New** → **Blueprint** → выбрать репозиторий.
3. Render прочитает `render.yaml`, создаст базу `cv-app-db` и сервис `cv-app`,
   свяжет их через переменную `DATABASE_URL_RAW` и запустит сборку.

Дальше деплой автоматический на каждый push в основную ветку
(`autoDeploy: true`). Health-check настроен на `/api/health`.

### Чем прод отличается от dev

| | dev | prod |
|---|---|---|
| Фронтенд | Vite dev server + HMR | статика из `dist/`, собранная в образе |
| Контейнеры | 4 (nginx, php, frontend, db) | 1 (nginx + php-fpm) + внешняя БД |
| Dockerfile | `docker/php.Dockerfile` | `docker/prod.Dockerfile` |
| Конфиг nginx | `nginx.dev.conf` | `nginx.prod.conf` (шаблон с `${PORT}`) |
| Зависимости | с dev-пакетами | `--no-dev`, OPcache без проверки файлов |

Прод-образ — многоступенчатая сборка из трёх стадий: сборка фронтенда
(node), установка зависимостей бэкенда (composer) и рантайм (php-fpm-alpine,
в него копируются только результаты). Итоговый образ ≈ 200 МБ.

nginx и php-fpm живут в одном контейнере и общаются через `127.0.0.1:9000`,
потому что бесплатный план Render даёт один контейнер на сервис.

### Что делает entrypoint

[docker/entrypoint.sh](docker/entrypoint.sh) при каждом старте:

1. подставляет `$PORT` от Render в конфиг nginx (порт меняется между деплоями);
2. дописывает `?serverVersion=16` к строке подключения от Render — без этого
   Doctrine на каждом запросе делает лишний запрос за версией сервера;
3. прогревает кэш Symfony, чтобы первый запрос после деплоя не был медленным;
4. накатывает миграции (`--allow-no-migration`, т.к. на первом деплое их нет);
5. запускает php-fpm в фоне, а nginx — в foreground как PID 1.

### Локальная проверка прод-образа

Отправлять конфиг на Render вслепую не нужно — образ собирается и проверяется
локально:

```bash
docker build -f docker/prod.Dockerfile -t cv-app-prod:test .

docker run --rm --name cv-prod-test \
  --network course_project_default \
  -p 9090:9090 -e PORT=9090 \
  -e DATABASE_URL_RAW="postgresql://app:app@db:5432/cv_app" \
  cv-app-prod:test
```

Проверить: <http://localhost:9090/api/health> и <http://localhost:9090/>.

### Ограничения бесплатного плана

- База удаляется через 30 дней — перед защитой либо перейти на платный план,
  либо пересоздать базу и заново прогнать фикстуры;
- сервис засыпает после 15 минут простоя, первый запрос после сна идёт
  ~30 секунд.

---

## Структура

```
backend/     Symfony (API)
frontend/    React + Vite
docker/      Dockerfile'ы и конфиг nginx
docker-compose.yml
```

---

## Заметки по окружению

**Версия PHP зафиксирована.** В `backend/composer.json` задано
`config.platform.php = 8.4.0`, а образ собран на `php:8.4-fpm-alpine`. Обе
версии должны совпадать: если Composer соберёт зависимости под одну версию, а
контейнер будет другой, Symfony упадёт на `platform_check.php`.

**node_modules в именованном томе.** Каталог `frontend/node_modules` вынесен в
том `frontend_node_modules`, чтобы Windows-хост не перетирал зависимости,
собранные внутри Linux-контейнера (нативные бинарники несовместимы).

**Кэш Symfony.** `backend/var/` смонтирован с хоста. После смены версии PHP кэш
нужно чистить, иначе появится ошибка вида
`Failed to remove directory .../var/cache/dev/...`:

```bash
rm -rf backend/var/cache backend/var/log
```

**HMR за прокси.** В `frontend/vite.config.ts` задан `hmr.clientPort: 8080` —
браузер обращается к приложению через nginx, а не напрямую к Vite, и без этой
настройки WebSocket горячей перезагрузки стучится не на тот порт.
`watch.usePolling` включён, потому что на Windows + Docker inotify не видит
изменения файлов через смонтированный том.
