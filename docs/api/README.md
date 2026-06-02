# REST API

> Документ будет заполняться по мере разработки (Фаза 1).

## Base URL

| Среда | URL |
|-------|-----|
| Local (Docker) | `http://localhost/api` |
| Production | `https://app.example.com/api` |

## Auth

Sanctum SPA authentication (cookie-based).

| Method | Endpoint | Описание |
|--------|----------|----------|
| POST | `/register` | Регистрация |
| POST | `/login` | Вход |
| POST | `/logout` | Выход |
| GET | `/user` | Текущий пользователь |

## Product updates («Что нового»)

| Method | Endpoint | Описание |
|--------|----------|----------|
| GET | `/product-updates` | Список активных анонсов и `unread_count` |
| POST | `/product-updates/{id}/dismiss` | Отметить анонс прочитанным |
| POST | `/product-updates/dismiss-all` | Отметить все прочитанными |

Манифест: `backend/resources/product_updates.json`.

## Health

```
GET /api/health
```

Response:

```json
{
  "status": "ok",
  "db": "ok",
  "redis": "ok"
}
```
