# SEO Reports

SaaS-сервис автоматической генерации SEO- и маркетинговых отчётов для digital-агентств и SEO-студий.

## Стек

| Слой | Технология |
|------|------------|
| Backend | Laravel 11, PHP 8.3 |
| Frontend | Vue 3, Tailwind CSS |
| DB | PostgreSQL 16 |
| Local | Docker Compose |
| Production | [Coolify](https://coolify.io/) |

## Быстрый старт

```bash
docker compose up -d
docker compose exec app php artisan migrate
```

→ http://localhost

Подробнее: [docs/development/setup.md](./docs/development/setup.md)

## Документация

| | |
|---|---|
| [docs/](./docs/) | Разработка, деплой, API, архитектура |
| [CHANGELOG.md](./CHANGELOG.md) | История изменений |
| [docs/architecture/report-blocks.md](./docs/architecture/report-blocks.md) | Каталог блоков отчёта |

## Возможности (кратко)

- Проекты, интеграции с аналитикой и SEO-сервисами, конструктор шаблонов и генерация PDF/HTML-отчётов
- Портфельный дашборд и **аналитика по проекту** (виджеты как в отчёте, своя раскладка)
- Публичные ссылки на отчёты, технические аудиты, админ-панель
- **«Что нового»** — анонсы фич для пользователей после деплоя

## Референсы

- Функциональность: [seo-reports.ru](https://seo-reports.ru/)
- UI: [TailAdmin Vue Demo](https://free-vue-demo.tailadmin.com/)
