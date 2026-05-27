# Интеграции

> Документ будет заполняться по мере разработки (Фаза 1).

## MVP-провайдеры

| Provider | Auth | Документ |
|----------|------|----------|
| `yandex_metrika` | OAuth Yandex | _(TODO)_ |
| `google_analytics` | OAuth Google | _(TODO)_ |
| `yandex_webmaster` | OAuth Yandex | _(TODO)_ |
| `google_search_console` | OAuth Google | _(TODO)_ |
| `topvisor` | API key | _(TODO)_ |

## Фаза 2

| Provider | Auth |
|----------|------|
| `yandex_direct` | OAuth Yandex |
| `google_ads` | OAuth Google |
| `vk_ads` | OAuth VK |
| `facebook_ads` | OAuth Facebook |
| `bitrix24` | OAuth / webhook |

## Общий flow (OAuth)

1. `POST /api/integrations/{provider}/connect` → redirect URL
2. User authorizes → callback `GET /api/integrations/{provider}/callback`
3. Tokens saved (encrypted) → list resources
4. User selects resource → bind to project

## Статусы интеграции

| Status | Описание |
|--------|----------|
| `active` | Токен валиден |
| `token_expired` | Нужно переподключить |
| `error` | Ошибка API, см. logs |
