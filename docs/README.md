# Документация SEO Reports

> Оглавление технической документации проекта.  
> ТЗ: [../TZ.md](../TZ.md)

## Разделы

### Разработка

| Документ | Описание |
|----------|----------|
| [development/setup.md](./development/setup.md) | Быстрый старт для разработчика |
| [development/docker.md](./development/docker.md) | Локальная среда через Docker Compose |

### Деплой

| Документ | Описание |
|----------|----------|
| [deployment/coolify.md](./deployment/coolify.md) | Production через Coolify |

### Дизайн

| Документ | Описание |
|----------|----------|
| [design/tailadmin.md](./design/tailadmin.md) | UI-kit TailAdmin, компоненты, кастомизация |

### Админ-панель

| Документ | Описание | Статус |
|----------|----------|--------|
| [admin/README.md](./admin/README.md) | Пользователи, настройки сервиса | Фаза 0.5 |

### Архитектура

| Документ | Описание | Статус |
|----------|----------|--------|
| [architecture/overview.md](./architecture/overview.md) | Общая архитектура, pipeline | Фаза 1 |
| [architecture/report-blocks.md](./architecture/report-blocks.md) | Каталог блоков отчёта | Фаза 1 |

### API и интеграции

| Документ | Описание | Статус |
|----------|----------|--------|
| [api/README.md](./api/README.md) | REST API endpoints | Фаза 1 |
| [integrations/README.md](./integrations/README.md) | OAuth-провайдеры | Фаза 1 |

## Правила ведения документации

1. Документ обновляется в том же PR, что и код (если затронут scope).
2. Env variables — в `.env.example` и в deployment docs.
3. Новая интеграция → файл в `integrations/`.
4. Новый endpoint → обновление `api/README.md`.
5. Релиз → запись в [CHANGELOG.md](../CHANGELOG.md).
