# Локальная разработка — Docker Compose

## Принцип

Локальная среда **полностью в Docker**. Разработчик не устанавливает PHP, Composer, Node на хост.

## Сервисы

| Сервис | Образ / build | Порт | Назначение |
|--------|---------------|------|------------|
| `nginx` | nginx:alpine | 80 | Reverse proxy |
| `app` | docker/Dockerfile (target: dev) | — | PHP-FPM, Laravel |
| `worker` | same as app | — | `php artisan queue:work` |
| `scheduler` | same as app | — | `php artisan schedule:work` |
| `postgres` | postgres:16-alpine | 5432 | БД |
| `redis` | redis:7-alpine | 6379 | Queue, cache |
| `minio` | minio/minio | 9000, 9001 | S3-compatible storage |
| `mailpit` | axllent/mailpit | 8025 | Email testing |
| `frontend` | node:22 (dev) | 5173 | Vite HMR |

## Маршрутизация nginx

```
/           → frontend (Vite dev или static)
/api/*      → app (PHP-FPM)
/storage/*  → app public storage
```

## Volumes

- `postgres_data` — данные PostgreSQL
- `minio_data` — файлы отчётов (dev)
- `./backend` — mount для hot reload PHP
- `./frontend` — mount для Vite HMR

## Переменные окружения

См. `backend/.env.example`:

```env
DB_CONNECTION=pgsql
DB_HOST=postgres
REDIS_HOST=redis
QUEUE_CONNECTION=redis
FILESYSTEM_DISK=s3
AWS_ENDPOINT=http://minio:9000
MAIL_HOST=mailpit
```

## Troubleshooting

### Миграции не применяются

```bash
docker compose exec app php artisan migrate:fresh
```

### Permission denied на storage

```bash
docker compose exec app chown -R www-data:www-data storage bootstrap/cache
```

### No application encryption key

Причина: битый или пустой `APP_KEY` в `backend/.env` (часто после повторного `key:generate`).

```bash
./scripts/fix-app-key.sh
```

В `.env` должна быть **одна** строка: `APP_KEY="base64:..."`. Laravel читает `.env` с volume напрямую (не через Docker `env_file`).

### Frontend не видит API

Проверьте `VITE_API_URL` в `frontend/.env` (обычно `http://localhost/api`).

### Worker не обрабатывает jobs

```bash
docker compose restart worker
docker compose logs worker
```
