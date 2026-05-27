# Фаза 1.5 — SEO-расширение

> [← TZ.md](./TZ.md) | Предыдущий: [Фаза 1](./TZ-faza-1-mvp-core.md) | Следующий: [Фаза 2](./TZ-faza-2-automation.md)

**Срок:** ~3–4 недели  
**Цель:** позиции, группировка ключей, проделанные работы, экономические KPI, экспорт DOCX/ODT.

---

## Scope

### В scope

- Topvisor / SE Ranking (API key)
- Блоки позиций: видимость, TOP-10/30/100, таблица ключей
- Группировка и сегментация ключевых запросов
- Модуль «Проделанные работы» (CRUD + блок отчёта)
- Экономические показатели (ручной ввод бюджета + расчёт CPA/ROI)
- Экспорт DOCX и ODT
- Абстракция провайдера позиций (`PositionProviderInterface`)

### Out of scope

- Авторассылка по расписанию (фаза 2)
- Рекламные кабинеты для KPI (фаза 2)
- Bitrix24 (фаза 2)

---

## Задачи

### 1. Позиции (Topvisor / SE Ranking)

- [ ] Провайдер `topvisor` и `se_ranking` в integrations (API key auth)
- [ ] `PositionProviderInterface`: fetchPositions, fetchVisibility, fetchTopDistribution
- [ ] UI: подключение по API key на странице «Источники данных»
- [ ] Привязка проекта позиций к проекту отчёта
- [ ] Блоки отчёта:
  - `positions_visibility` — график/таблица видимости
  - `positions_top_distribution` — TOP-10/30/100
  - `positions_table` — таблица ключ → позиция → Δ

### 2. Группировка ключевых запросов

- [ ] Миграции: `keyword_groups`, `keywords`
- [ ] API: CRUD `/api/projects/{id}/keyword-groups`
- [ ] UI: управление группами в настройках проекта
- [ ] Импорт ключей (CSV) и ручное добавление
- [ ] Привязка ключей из GSC / позиций к группам
- [ ] Блок отчёта `keyword_segmentation`: агрегация по группам (показы, клики, avg position, TOP-N)
- [ ] Фильтр групп в генераторе отчёта

### 3. Проделанные работы

- [ ] Миграция: `work_items` (project_id, date, category, description)
- [ ] API: CRUD `/api/projects/{id}/work-items`
- [ ] UI: вкладка/раздел в проекте — список работ с фильтром по периоду
- [ ] Категории: SEO, контент, техническое
- [ ] Блок отчёта `work_performed` — таблица работ за выбранный период

### 4. Экономические показатели

- [ ] Поля в настройках генерации: budget, leads_count, orders_count, avg_check (опц.)
- [ ] Альтернатива: подтягивание conversions из Метрики/GA4 goals
- [ ] Блок отчёта `economics_kpi`:
  - CPA (cost per acquisition)
  - Cost per lead
  - Cost per order
  - ROI (при указании avg_check)
- [ ] Формулы и отображение с сравнением периодов

### 5. Экспорт DOCX / ODT

- [ ] `DocxRenderer` на базе PhpWord
- [ ] Конвертация DOCX → ODT (LibreOffice headless в Docker)
- [ ] Выбор формата в шаблоне и при генерации
- [ ] Сохранение DOCX/ODT в S3 alongside PDF

---

## Deliverables

1. Topvisor или SE Ranking подключён по API key
2. Блоки позиций в отчёте
3. Группы ключей созданы и отображаются в отчёте
4. Проделанные работы добавлены и попали в отчёт
5. KPI рассчитаны из ручного бюджета
6. Отчёт скачивается в DOCX и ODT

---

## Критерии приёмки

- [ ] API key Topvisor/SE Ranking сохраняется и используется для fetch
- [ ] Блок видимости показывает данные за период + сравнение
- [ ] Группы ключей: CRUD, импорт CSV, сегментация в отчёте
- [ ] Работы: CRUD, фильтр по дате, блок в отчёте
- [ ] CPA/ROI рассчитываются корректно при вводе бюджета
- [ ] DOCX открывается в Word/LibreOffice без потери структуры
- [ ] ODT генерируется из DOCX

---

## Зависимости

- [Фаза 1 — MVP Core](./TZ-faza-1-mvp-core.md) завершена

## Следующий этап

[Фаза 2 — Автоматизация и реклама](./TZ-faza-2-automation.md)
