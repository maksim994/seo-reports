# Фаза 0.5 — Админ-панель

> [← TZ.md](./TZ.md) | Предыдущий: [Фаза 0](./TZ-faza-0-karkas.md) | Следующий: [Фаза 1](./TZ-faza-1-mvp-core.md)

**Срок:** ~1–2 недели  
**Цель:** админ-панель для управления пользователями и глобальными настройками сервиса.

---

## Scope

### В scope

- Роль Admin (`users.is_admin`)
- Middleware `EnsureUserIsAdmin`
- Artisan-команда `make:admin` для назначения первого администратора
- UI: раздел «Админ» в sidebar (только для admin)
- Страница `/admin/users` — список пользователей
- Страница `/admin/settings` — настройки сервиса
- API: admin endpoints с policy/guard
- Таблица `settings` (key-value)
- Seeder первого admin (опционально, через env `ADMIN_EMAIL`)

### Out of scope

- Impersonate (вход от имени пользователя) — опционально, фаза 2
- Audit log действий admin — фаза 2

---

## Задачи

### Backend

- [ ] Миграция: `is_admin` boolean на `users`, default false
- [ ] Миграция: `is_blocked` boolean на `users`, default false
- [ ] Миграция: `settings` (key string unique, value jsonb)
- [ ] Middleware `AdminMiddleware`
- [ ] `php artisan make:admin {email}` — назначить admin
- [ ] API `GET /api/admin/users` — список с pagination, search
- [ ] API `PATCH /api/admin/users/{id}` — role, blocked
- [ ] API `DELETE /api/admin/users/{id}` — удаление
- [ ] API `GET /api/admin/settings` — все настройки
- [ ] API `PUT /api/admin/settings` — batch update
- [ ] API `GET /api/admin/projects` — все проекты (опционально)
- [ ] Блокировка: заблокированный user не может login (422)

### Frontend

- [ ] Sidebar: пункт «Админ» (v-if user.is_admin)
- [ ] Sub-nav: Пользователи, Настройки, Все проекты
- [ ] `/admin/users` — TailAdmin Data Table, search, badges роли/статуса
- [ ] Modal: редактирование пользователя (admin toggle, block)
- [ ] `/admin/settings` — форма групп настроек (tabs или sections)
- [ ] Route guard: `/admin/*` → 403 redirect для non-admin

### Настройки сервиса (default keys)

```json
{
  "app_name": "SEO Reports",
  "support_email": "support@example.com",
  "registration_enabled": true,
  "email_verification_required": false,
  "report_retention_months": 12,
  "maintenance_mode": false,
  "maintenance_message": ""
}
```

---

## Deliverables

1. Admin назначается через CLI
2. Admin видит список всех пользователей
3. Admin может заблокировать пользователя и назначить admin
4. Admin редактирует глобальные настройки сервиса
5. Обычный пользователь не видит админ-раздел

---

## Критерии приёмки

- [ ] `php artisan make:admin user@example.com` работает
- [ ] GET `/api/admin/users` возвращает 403 для обычного user
- [ ] Таблица пользователей с поиском и pagination
- [ ] Toggle is_admin сохраняется
- [ ] Blocked user не может войти
- [ ] Настройки `registration_enabled=false` скрывает регистрацию на frontend
- [ ] UI в стиле TailAdmin

---

## Зависимости

- [Фаза 0 — Каркас](./TZ-faza-0-karkas.md) завершена

## Следующий этап

[Фаза 1 — MVP Core](./TZ-faza-1-mvp-core.md)
