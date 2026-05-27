# Фаза 2 — Автоматизация и реклама

> [← TZ.md](./TZ.md) | Предыдущий: [Фаза 1.5](./TZ-faza-1.5-seo.md) | Следующий: [Фаза 3](./TZ-faza-3-teams-api.md)

**Срок:** ~4–6 недель  
**Цель:** автогенерация по расписанию, рекламные интеграции, Bitrix24, white-label домен.

---

## Scope

### В scope

- Автогенерация и email-рассылка отчётов по CRON
- Яндекс.Директ, Google Ads, VK Ads, Facebook Ads
- Bitrix24 — выгрузка проделанных работ
- White-label: custom domain (CNAME), брендирование
- Автоматический расчёт KPI из рекламных кабинетов
- Блоки отчёта для контекстной и таргетированной рекламы

### Out of scope

- Multi-user аккаунты (фаза 3)
- Public API (фаза 3)
- Биллинг и тарифы

---

## Задачи

### 1. Автогенерация и рассылка

- [ ] Миграция: `report_schedules` (project_id, template_id, cron, recipients, format, is_active)
- [ ] API: CRUD `/api/schedules`
- [ ] UI: `/schedules` — список расписаний, форма создания
- [ ] Пресеты: ежемесячно (1-е число), еженедельно, custom cron
- [ ] Автовыбор периода: «прошлый календарный месяц»
- [ ] Laravel Scheduler: `ProcessReportSchedules` command
- [ ] `SendReportEmailJob` — вложение PDF/DOCX или ссылка
- [ ] Лог отправок, retry × 3 при ошибке
- [ ] UI: кнопка «Автоматическая генерация» на странице генератора

### 2. Рекламные интеграции

| Провайдер | Протокол | Данные |
|-----------|----------|--------|
| Яндекс.Директ | OAuth Yandex | расход, клики, CPC, конверсии, кампании |
| Google Ads | OAuth Google | spend, clicks, conversions, campaigns |
| VK Ads | OAuth VK | spend, impressions, clicks |
| Facebook Ads | OAuth Facebook | spend, reach, conversions |

- [ ] OAuth flow для каждого провайдера
- [ ] UI: кнопки подключения на «Источники данных»
- [ ] Привязка рекламного аккаунта к проекту
- [ ] Блоки отчёта:
  - `direct_overview`, `direct_campaigns`
  - `google_ads_overview`, `google_ads_campaigns`
  - `vk_ads_overview`
  - `facebook_ads_overview`
- [ ] KPI из рекламы: CPA, cost per lead автоматически

### 3. Bitrix24

- [ ] OAuth / webhook подключение Bitrix24 portal
- [ ] Выгрузка задач/работ за период в `work_items`
- [ ] UI: кнопка «Импорт из Bitrix24» в разделе работ
- [ ] Mapping: Bitrix task → work_item (date, category, description)
- [ ] Настройка фильтра (воронка, ответственный, тип задачи)

### 4. White-label

- [ ] Миграция: `custom_domains` (user_id, domain, verified_at, ssl_status)
- [ ] CNAME verification flow (DNS TXT/CNAME record)
- [ ] Nginx/host routing по custom domain
- [ ] UI: настройки аккаунта → «Свой домен»
- [ ] Брендирование: logo, favicon, цвета nav (override defaults)
- [ ] Отчёты доступны по custom domain (опционально)

---

## Deliverables

1. Расписание создаётся, отчёт генерируется и отправляется автоматически
2. Яндекс.Директ и Google Ads подключены, данные в отчёте
3. Работы импортируются из Bitrix24
4. Custom domain привязан и верифицирован
5. KPI считаются из рекламных расходов

---

## Критерии приёмки

- [ ] Schedule: ежемесячная генерация за прошлый месяц + email
- [ ] Retry при ошибке отправки (3 попытки)
- [ ] Яндекс.Директ: расход и конверсии в блоке отчёта
- [ ] Google Ads: аналогично
- [ ] Bitrix24: импорт ≥ 1 работы в work_items
- [ ] Custom domain: CNAME verified, приложение открывается
- [ ] KPI CPA автоматически из рекламного расхода

---

## Зависимости

- [Фаза 1.5 — SEO-расширение](./TZ-faza-1.5-seo.md) завершена

## Следующий этап

[Фаза 3 — Команды и API](./TZ-faza-3-teams-api.md) (опционально)
