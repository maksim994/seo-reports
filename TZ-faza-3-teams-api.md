# Фаза 3 — Команды и Public API (опционально)

> [← TZ.md](./TZ.md) | Предыдущий: [Фаза 2](./TZ-faza-2-automation.md)

**Срок:** ~4 недели (опционально)  
**Цель:** совместная работа в аккаунте, роли, внешний API для интеграций.

---

## Scope

### В scope

- Multi-user аккаунты (организация / workspace)
- Роли: Owner, Admin, Manager, Viewer
- Приглашения по email
- Public REST API с API keys
- Rate limiting per API key
- Webhooks (report.generated, integration.connected)
- Документация API (OpenAPI/Swagger)

### Out of scope

- Биллинг по количеству пользователей
- SSO / SAML (enterprise)

---

## Задачи

### 1. Организации и команды

- [ ] Миграции: `organizations`, `organization_user` (role enum)
- [ ] Роли:
  - **Owner** — полный доступ, billing settings, delete org
  - **Admin** — управление пользователями, интеграциями, шаблонами
  - **Manager** — проекты, генерация отчётов
  - **Viewer** — только просмотр отчётов и истории
- [ ] Привязка projects, integrations, templates к organization (не user)
- [ ] UI: «Пользователи» — список, invite, change role, remove
- [ ] Email-приглашение с accept link

### 2. Public API

- [ ] Миграция: `api_keys` (organization_id, key_hash, name, scopes, last_used_at)
- [ ] Auth middleware: Bearer token (API key)
- [ ] Scopes: projects:read, projects:write, reports:generate, reports:read, integrations:read
- [ ] Endpoints (subset of internal API):
  - `GET /api/v1/projects`
  - `POST /api/v1/projects/{id}/reports/generate`
  - `GET /api/v1/reports/{id}`
  - `GET /api/v1/reports/{id}/download`
- [ ] Rate limiting: 100 req/min per key (настраиваемо)
- [ ] UI: «API Keys» — create, revoke, scopes

### 3. Webhooks

- [ ] Миграция: `webhooks` (organization_id, url, events[], secret)
- [ ] Events: `report.generated`, `report.failed`, `integration.connected`, `integration.expired`
- [ ] HMAC signature в заголовке `X-Webhook-Signature`
- [ ] Retry delivery × 3
- [ ] UI: настройка webhooks

### 4. Документация

- [ ] `docs/API.md` — OpenAPI 3.0 spec
- [ ] Swagger UI at `/api/docs` (dev/staging)
- [ ] Примеры curl для основных операций

---

## Deliverables

1. Организация с несколькими пользователями и ролями
2. API key создаётся, генерирует отчёт programmatically
3. Webhook срабатывает при генерации отчёта
4. OpenAPI документация опубликована

---

## Критерии приёмки

- [ ] Owner приглашает Manager — тот видит проекты и генерирует отчёты
- [ ] Viewer не может редактировать шаблоны
- [ ] API key с scope `reports:generate` запускает генерацию
- [ ] Rate limit возвращает 429 при превышении
- [ ] Webhook доставляется с valid HMAC signature
- [ ] OpenAPI spec валиден и покрывает v1 endpoints

---

## Зависимости

- [Фаза 2 — Автоматизация и реклама](./TZ-faza-2-automation.md) завершена

---

## Общая roadmap (все этапы)

| # | Этап | Срок | Статус |
|---|------|------|--------|
| 0 | [Каркас](./TZ-faza-0-karkas.md) | ~2 нед. | — |
| 1 | [MVP Core](./TZ-faza-1-mvp-core.md) | ~6–8 нед. | — |
| 1.5 | [SEO-расширение](./TZ-faza-1.5-seo.md) | ~3–4 нед. | — |
| 2 | [Автоматизация и реклама](./TZ-faza-2-automation.md) | ~4–6 нед. | — |
| 3 | [Команды и API](./TZ-faza-3-teams-api.md) | ~4 нед. | опц. |

**Итого MVP (фазы 0–1.5):** ~11–14 недель  
**Полный продукт (фазы 0–2):** ~15–20 недель
