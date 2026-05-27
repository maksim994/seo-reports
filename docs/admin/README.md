# Админ-панель

> Спецификация: [TZ-faza-0.5-admin.md](../../TZ-faza-0.5-admin.md)

## Доступ

- URL: `/admin/*`
- Требуется `users.is_admin = true`
- Первый admin: `docker compose exec app php artisan make:admin email@example.com`

## Разделы

| Route | Описание |
|-------|----------|
| `/admin/users` | Список пользователей, роли, блокировка |
| `/admin/settings` | Глобальные настройки сервиса |
| `/admin/projects` | Все проекты (support view) |

## API

| Method | Endpoint |
|--------|----------|
| GET | `/api/admin/users` |
| PATCH | `/api/admin/users/{id}` |
| DELETE | `/api/admin/users/{id}` |
| GET/PUT | `/api/admin/settings` |
| GET | `/api/admin/projects` |

Статус: **запланировано** (Фаза 0.5).
