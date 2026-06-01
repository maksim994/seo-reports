# Google OAuth — production и верификация

Руководство по настройке Google OAuth для production-окружения SEO Reports и прохождения модерации Google.

## Архитектура

- **Один Google Cloud проект** (`seo-report`) — достаточно для dev и prod.
- **Два OAuth client** — рекомендуется разделить local и production:

| Client | Назначение | Redirect URIs |
|--------|------------|---------------|
| `SEO Reports local` | Локальная разработка | `http://localhost/api/integrations/{provider}/callback` |
| `SEO Reports production` | Production | `https://seo-report.mv-deploy.ru/api/integrations/{provider}/callback` |

`{provider}` для Google: `google_analytics`, `google_search_console`.

---

## Шаг 1. Включить API

В [Google Cloud Console](https://console.cloud.google.com/) → **APIs & Services → Library**:

| API | Зачем |
|-----|-------|
| Google Analytics Admin API | Список GA4 properties после OAuth |
| Google Analytics Data API | Метрики для отчётов |
| Google Search Console API | Запросы, клики, страницы GSC |

---

## Шаг 2. Production OAuth client

**Google Auth Platform → Clients → + Create client**

| Поле | Значение |
|------|----------|
| Application type | Web application |
| Name | `SEO Reports production` |

**Authorized redirect URIs:**

```
https://seo-report.mv-deploy.ru/api/integrations/google_analytics/callback
https://seo-report.mv-deploy.ru/api/integrations/google_search_console/callback
```

Скопируйте **Client ID** и **Client secret** в Coolify env:

```env
APP_URL=https://seo-report.mv-deploy.ru
FRONTEND_URL=https://seo-report.mv-deploy.ru
GOOGLE_OAUTH_CLIENT_ID=<prod client id>
GOOGLE_OAUTH_CLIENT_SECRET=<prod client secret>
```

Перезапустите web и worker после деплоя.

---

## Шаг 3. Branding (OAuth consent screen)

**Google Auth Platform → Branding**

| Поле | Значение |
|------|----------|
| App name | `SEO Reports` |
| User support email | `maksim99437@gmail.com` |
| App logo | PNG/JPG 120×120 px |
| Application home page | `https://seo-report.mv-deploy.ru` |
| Privacy policy | `https://seo-report.mv-deploy.ru/privacy` |
| Terms of service | `https://seo-report.mv-deploy.ru/terms` |
| Authorized domains | `mv-deploy.ru` |

### Подтверждение домена

1. [Google Search Console](https://search.google.com/search-console) → добавить домен `mv-deploy.ru` (DNS TXT или HTML).
2. После верификации добавить `mv-deploy.ru` в **Authorized domains** в Branding.

Страницы `/privacy` и `/terms` уже реализованы во frontend.

---

## Шаг 4. Data Access (scopes)

**Google Auth Platform → Data Access → Add or remove scopes**

| Scope | Название в консоли |
|-------|-------------------|
| `https://www.googleapis.com/auth/analytics.readonly` | View Google Analytics data |
| `https://www.googleapis.com/auth/webmasters.readonly` | View Search Console data |

---

## Шаг 5. Verification Center

После заполнения Branding и публикации страниц — **Verification Center → Submit for verification**.

### Обоснование scopes (на английском)

**Analytics readonly:**

> SEO Reports is a B2B SaaS for digital agencies. Users connect their own Google Analytics 4 property via OAuth to generate automated SEO/marketing PDF reports. We only read aggregated analytics metrics (sessions, users, channels, conversions) for the date range selected by the user. Data is used solely to populate client reports. We do not modify Analytics settings. Access is revoked when the user disconnects the integration.

**Webmasters readonly:**

> Users connect their own Google Search Console property via OAuth to include search performance data (queries, clicks, impressions, CTR, pages) in automated reports. Read-only access. Data is fetched on demand for report generation and is not sold or used for advertising. Users can disconnect at any time.

### Demo video (2–5 мин, YouTube unlisted)

Показать:

1. Вход в SEO Reports на production.
2. **Интеграции → Подключить Google Analytics** → экран OAuth consent (видны scopes).
3. Выбор GA4 property → привязка к проекту.
4. **Подключить Google Search Console** → выбор сайта.
5. Генерация отчёта с блоками GA и GSC.
6. **Отключить** интеграцию.

---

## Шаг 6. Publish app

После одобрения verification:

**Audience → Publish app** → статус **In production**.

Любой Google-аккаунт сможет подключить интеграции без добавления в Test users.

---

## Быстрый путь без модерации (пилот)

Если verification ещё не пройдена:

- Оставить **Testing** в Audience.
- Добавлять каждый Google-аккаунт клиента в **Test users** (до ~100 пользователей).
- Использовать production OAuth client с prod redirect URIs.

---

## Чеклист

```
☐ Analytics Admin API, Analytics Data API, Search Console API включены
☐ Scopes analytics.readonly и webmasters.readonly в Data Access
☐ Production OAuth client с prod redirect URIs
☐ Coolify: APP_URL, FRONTEND_URL, GOOGLE_OAUTH_*
☐ /privacy и /terms доступны на prod-домене
☐ Authorized domain mv-deploy.ru подтверждён в Search Console
☐ Branding заполнен (logo, homepage, privacy, terms)
☐ Demo video записано и приложено к verification
☐ Submit for verification
☐ Publish app → In production
```

---

## Troubleshooting

| Ошибка | Решение |
|--------|---------|
| `401 invalid_client` | Client ID/secret не совпадают с Credentials или не те env на сервере |
| `403 access_denied` | Аккаунт не в Test users (режим Testing) |
| `500` на consent (GSC) | Не включён Search Console API или scope `webmasters.readonly` не добавлен |
| Redirect mismatch | `APP_URL` на prod ≠ URI в Google Console |

---

## Связанные файлы

| Файл | Описание |
|------|----------|
| `backend/config/integrations.php` | Scopes и redirect URI |
| `backend/.env.production.example` | Шаблон env для Coolify |
| `frontend/src/pages/legal/PrivacyPolicyPage.vue` | Политика конфиденциальности |
| `frontend/src/pages/legal/TermsOfServicePage.vue` | Условия использования |
| `docs/deployment/coolify.md` | Деплой на production |
