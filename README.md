# SEO Reports

SaaS-сервис автоматической генерации SEO- и маркетинговых отчётов для digital-агентств и SEO-студий.

## Стек

| Слой | Технология |
|------|------------|
| Backend | Laravel 11, PHP 8.3 |
| Frontend | Vue 3, TailAdmin, Tailwind CSS |
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
| [TZ.md](./TZ.md) | Техническое задание |
| [docs/](./docs/) | Разработка, деплой, API, архитектура |
| [TZ-faza-0-karkas.md](./TZ-faza-0-karkas.md) | Текущий этап |
| [TZ-faza-0.5-admin.md](./TZ-faza-0.5-admin.md) | Админ-панель |

## Этапы

1. **Фаза 0** — каркас, Docker, TailAdmin, auth, проекты
2. **Фаза 1** — OAuth, шаблоны, генератор, PDF
3. **Фаза 1.5** — позиции, ключи, DOCX/ODT
4. **Фаза 2** — расписание, реклама, Coolify prod
5. **Фаза 3** — команды, public API (опц.)

## Референсы

- Функциональность: [seo-reports.ru](https://seo-reports.ru/)
- UI: [TailAdmin Vue Demo](https://free-vue-demo.tailadmin.com/)
