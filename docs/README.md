# Документация SEO Reports

> Оглавление технической документации проекта.  
> История изменений: [../CHANGELOG.md](../CHANGELOG.md)

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
5. **Обязательно:** любое завершённое изменение → пункт в `[Unreleased]` [CHANGELOG.md](../CHANGELOG.md) (агенты: см. `.cursor/rules/changelog.mdc`).
6. Релиз → перенос `[Unreleased]` в версию с датой.
7. Новая пользовательская фича → запись в [backend/resources/product_updates.json](../backend/resources/product_updates.json) (см. ниже).

## Анонс новых фич («Что нового»)

При выкладке фичи, которую должны увидеть пользователи:

1. Добавьте объект в `backend/resources/product_updates.json` с уникальным `id` (не менять после публикации).
2. Укажите `cta_path` на экран, куда ведёт кнопка «Попробовать».
3. Если фича на отдельной странице — добавьте `context_paths` (например `/projects/:id/analytics`) для контекстного баннера.
4. Проверьте в UI: колокольчик в шапке, модалка, баннер на целевой странице, «Понятно» синхронизируется между устройствами.

`CHANGELOG.md` — для разработчиков; `product_updates.json` — понятный текст для пользователей.
