# Фаза 1 — MVP Core

> [← TZ.md](./TZ.md) | Предыдущий: [Фаза 0](./TZ-faza-0-karkas.md) | Следующий: [Фаза 1.5](./TZ-faza-1.5-seo.md)

**Срок:** ~6–8 недель  
**Цель:** подключение аналитики и вебмастеров, конструктор шаблонов, генерация отчётов в HTML/PDF, история.

---

## Scope

### В scope

- OAuth: Яндекс.Метрика, GA4, Яндекс.Вебмастер, Google Search Console
- Привязка источников к проектам
- Модуль «Источники данных» (полноценный UI)
- Конструктор шаблонов (10–12 базовых блоков)
- Генератор отчёта: период, сравнение, выбор шаблона
- Pipeline генерации: fetch → transform → render
- Экспорт HTML + PDF
- История отчётов
- Queue workers для async генерации
- MinIO/S3 для хранения файлов

### Out of scope

- Topvisor  (фаза 1.5)
- DOCX/ODT (фаза 1.5)
- Группировка ключей, работы, KPI (фаза 1.5)
- Авторассылка (фаза 2)
- Рекламные кабинеты (фаза 2)

---

## Задачи

### 1. Интеграции (OAuth)

- [ ] Миграции: `integrations`, `project_integrations`
- [ ] Enum провайдеров: yandex_metrika, google_analytics, yandex_webmaster, google_search_console
- [ ] `IntegrationProviderInterface` + реализации для каждого провайдера
- [ ] OAuth flow: connect → callback → save encrypted credentials
- [ ] Refresh token при истечении
- [ ] API: list integrations, connect, callback, disconnect, list resources
- [ ] UI: страница «Источники данных», empty state, карточки подключений
- [ ] UI: выбор ресурса после OAuth (counter, property, site)
- [ ] Привязка ресурса к проекту (авто по domain + ручная)
- [ ] Статусы: active, token_expired, error

### 2. Шаблоны отчётов

- [ ] Миграции: `report_templates`, `template_blocks`
- [ ] Каталог блоков (JSON/config): тип, категория, название, required integrations
- [ ] API: CRUD templates, GET blocks catalog, upload logo
- [ ] UI: список шаблонов, редактор с dual-list (доступные / выбранные)
- [ ] Сортировка блоков (↑↓ или drag-and-drop)
- [ ] Настройки блока (modal с JSON settings)
- [ ] Демо-шаблон при регистрации пользователя (seeder/event)

**Блоки MVP (минимум 10):**

| block_type | Категория | Источник |
|------------|-----------|----------|
| title_page | Общие | — |
| table_of_contents | Общие | — |
| text_block | Общие | — |
| metrika_overview | Яндекс.Метрика | yandex_metrika |
| metrika_traffic_sources | Яндекс.Метрика | yandex_metrika |
| metrika_goals | Яндекс.Метрика | yandex_metrika |
| ga_overview | Google Analytics | google_analytics |
| ga_channels | Google Analytics | google_analytics |
| gsc_top_queries | Search Console | google_search_console |
| webmaster_queries | Яндекс.Вебмастер | yandex_webmaster |

### 3. Генератор отчётов

- [ ] Миграции: `report_jobs`, `report_files`
- [ ] API: POST generate, GET status, GET history, GET download
- [ ] DateRangePicker: пресеты (30 дней, календарный месяц), custom range
- [ ] Сравнение периодов: checkbox + варианты
- [ ] UI: страница `/projects/:id/generate`
- [ ] Collapsible «Настройки отчёта»
- [ ] Кнопки: СГЕНЕРИРОВАТЬ, История генерации

### 4. Report Engine

- [ ] `ReportBlockInterface` + Factory по block_type
- [ ] `GenerateReportJob` (queue)
- [ ] Pipeline: Resolve → Fetch (Bus::batch) → Transform → Calculate → Render → Store
- [ ] `ReportDataset` — единая схема данных
- [ ] Сравнение периодов: current, previous, delta%
- [ ] Graceful degradation: блок с ошибкой → «данные недоступны»
- [ ] HTML renderer (Blade templates per block)
- [ ] PDF renderer (DomPDF или Browsershot)
- [ ] Прогресс статусов: queued → fetching → rendering → done / failed
- [ ] Кэш API-ответов в Redis (TTL настраиваемый)

### 5. История отчётов

- [ ] UI: `/reports` — таблица с фильтрами
- [ ] Просмотр HTML-версии в браузере
- [ ] Скачивание PDF
- [ ] Хранение в S3/MinIO

---

## Deliverables

1. Пользователь подключает Яндекс.Метрику и/или GA4
2. Пользователь подключает Search Console / Вебмастер
3. Создаёт шаблон из блоков, загружает логотип
4. Генерирует отчёт за период с сравнением
5. Скачивает PDF, смотрит HTML
6. Видит историю генераций

---

## Критерии приёмки

- [ ] OAuth Yandex Metrika: connect → select counter → bind to project
- [ ] OAuth GA4: connect → select property → bind to project
- [ ] OAuth Yandex Webmaster + Google Search Console работают аналогично
- [ ] Конструктор: добавление/удаление/сортировка блоков, сохранение
- [ ] Генерация отчёта ≤ 30 сек для 10–15 блоков
- [ ] PDF и HTML содержат данные из подключённых источников
- [ ] Сравнение периодов отображает Δ%
- [ ] История: список, статус, download
- [ ] Ошибка одного блока не ломает весь отчёт

---

## Зависимости

- [Фаза 0 — Каркас](./TZ-faza-0-karkas.md) завершена

## Следующий этап

[Фаза 1.5 — SEO-расширение](./TZ-faza-1.5-seo.md)
