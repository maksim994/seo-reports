# UI-kit: TailAdmin

## Референс

- **Demo:** [TailAdmin Vue — free demo](https://free-vue-demo.tailadmin.com/)
- **Функциональный UX:** [seo-reports.ru](https://seo-reports.ru/)

Структура экранов — по seo-reports.ru, визуальный стиль и компоненты — TailAdmin.

## Стек

- Vue 3 + Composition API
- Tailwind CSS
- TailAdmin Vue template (sidebar layout, components)

## Маппинг экранов

| SEO Reports | TailAdmin pattern | Route |
|-------------|-------------------|-------|
| Вход | Sign In | `/login` |
| Регистрация | Sign Up | `/register` |
| Мои проекты | Basic Table + Modal | `/projects` |
| Источники данных | Cards grid + Buttons | `/integrations` |
| Шаблоны отчётов | Table + link to editor | `/templates` |
| Редактор шаблона | Dual list / custom layout | `/templates/:id/edit` |
| Генератор отчёта | Form + Date picker + Calendar | `/projects/:id/generate` |
| История отчётов | Data Table + Badges | `/reports` |
| Расписания | Table + Form modal | `/schedules` |

## Layout

```
┌─────────────────────────────────────────────┐
│ Header: logo, user dropdown, notifications  │
├──────────┬──────────────────────────────────┤
│ Sidebar  │  Page content (breadcrumb + card)│
│          │                                  │
│ - Проекты│                                  │
│ - Источн.│                                  │
│ - Шаблоны│                                  │
│ - Отчёты │                                  │
│ - Распис.│                                  │
└──────────┴──────────────────────────────────┘
```

## Компоненты TailAdmin для переиспользования

| Компонент | Где используется |
|-----------|------------------|
| Sidebar | AppLayout |
| Header | AppLayout |
| Breadcrumb | Все внутренние страницы |
| Basic Table / Data Table | Проекты, отчёты, интеграции |
| Modal | Добавить проект, настройки блока |
| Alert | Info при создании проекта без аналитики |
| Badge | Статус интеграции, статус job |
| Button (primary/secondary) | CTA: Добавить, Сгенерировать |
| Form inputs | Auth, формы проекта, генератор |
| Card | Empty states, stat widgets |
| Dropdown | User menu, фильтры |
| Progress bar | Генерация отчёта |

## Кастомизация

### Brand colors (tailwind.config)

```js
// Primary — можно оставить TailAdmin default или настроить
colors: {
  brand: {
    500: '#465fff', // primary actions
    // ...
  }
}
```

### Логотип

- Sidebar: «SEO Reports» + icon
- Auth pages: centered logo

## Правила

1. Не писать UI с нуля — брать паттерны из TailAdmin demo.
2. Новые страницы — в существующем AppLayout.
3. Таблицы — TailAdmin Data Table с pagination.
4. Формы — TailAdmin form components + vee-validate (если используется в template).
5. Dark mode — опционально, если включён в TailAdmin template.

## Auth pages

Отдельный layout без sidebar:

- `/login` — email + password, ссылка на register
- `/register` — name + email + password
- `/forgot-password` — email (фаза 0)
