# Быстрый старт

## Требования

- Docker Desktop (или Docker Engine + Compose v2)
- Git

PHP, Node.js и Composer на хосте **не нужны** — всё работает в контейнерах.

## Запуск

```bash
git clone <repo-url> seo-reports
cd seo-reports

cp backend/.env.example backend/.env
cp frontend/.env.example frontend/.env

docker compose up -d --build
./scripts/fix-app-key.sh
docker compose exec app php artisan migrate
```

> **APP_KEY:** если ошибка «No application encryption key» — выполните `./scripts/fix-app-key.sh` (скрипт сбрасывает битый ключ и генерирует новый).

## URL

| Сервис | URL |
|--------|-----|
| Приложение | http://localhost |
| Mailpit (email) | http://localhost:8025 |
| MinIO Console | http://localhost:9001 |

## Полезные команды

```bash
# Логи
docker compose logs -f app worker

# Тесты backend
docker compose exec app php artisan test

# Frontend dev (hot reload)
docker compose logs -f frontend

# Остановка
docker compose down
```

Подробнее о сервисах: [docker.md](./docker.md)
