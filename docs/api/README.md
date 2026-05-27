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

## Endpoints (planned)

См. [TZ.md — раздел 6.3](../../TZ.md#63-rest-api).

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
