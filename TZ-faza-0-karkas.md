# Фаза 0 — Каркас

> [← Вернуться к TZ.md](./TZ.md) | Следующий этап: [Фаза 1 — MVP Core](./TZ-faza-1-mvp-core.md)

**Срок:** ~2 недели  
**Цель:** рабочий каркас приложения с аутентификацией, базовым UI и CRUD проектов.

---

## Scope

### В scope

- Инициализация монорепозитория (`backend/`, `frontend/`, `docker/`)
- **Docker Compose** — единственный способ локальной разработки
- Laravel 11 + PostgreSQL + Redis
- Vue 3 SPA + Pinia + Vue Router + **[TailAdmin](https://free-vue-demo.tailadmin.com/)**
- Auth: регистрация, вход, выход, восстановление пароля (Sanctum)
- Layout на базе TailAdmin (sidebar, header, tables, modals)
- CRUD проектов (backend + frontend)
- Пустые экраны-заглушки всех модулей
- CI: lint + tests (GitHub Actions)
- **Документация:** README, docs/development, docs/deployment, docs/design
- **Подготовка к Coolify:** Dockerfile multi-stage, docker-compose.prod.yml
- Миграции базовых таблиц: `users`, `projects`

### Out of scope

- OAuth-интеграции
- Генерация отчётов
- Шаблоны и блоки
- Очереди и workers (кроме базовой настройки Redis)

---

## Задачи

### Backend

- [ ] Создать Laravel-проект в `backend/`
- [ ] Настроить PostgreSQL, Redis, Sanctum
- [ ] Миграция `projects`: name, domain, promotion_start_date, settings (jsonb)
- [ ] API: `POST /api/register`, `POST /api/login`, `POST /api/logout`, `GET /api/user`
- [ ] API: CRUD `/api/projects` с авторизацией (policy: только свои проекты)
- [ ] Валидация: name обязателен, domain опционален
- [ ] CORS и Sanctum SPA middleware

### Frontend

- [ ] Создать Vue 3 проект в `frontend/` на базе **TailAdmin Vue**
- [ ] Pinia store: auth, projects
- [ ] Страницы auth: `/login`, `/register` (TailAdmin sign-in layout)
- [ ] `AppLayout`: TailAdmin sidebar + header + user dropdown
- [ ] Sidebar: Мои проекты, Источники данных, Шаблоны, История отчётов, Расписания
- [ ] Страница проектов: TailAdmin Data Table, кнопка «+ Добавить», empty state (Card)
- [ ] Модалка «Добавить проект» (TailAdmin Modal + Alert info)
- [ ] Заглушки маршрутов: `/integrations`, `/templates`, `/reports`, `/schedules`
- [ ] Auth guard: редирект на login для защищённых страниц

### DevOps

- [ ] `docker-compose.yml`: app, nginx, postgres, redis, minio, mailpit, worker, scheduler, frontend
- [ ] `Dockerfile` multi-stage: Node (frontend build) → PHP 8.3-FPM
- [ ] `docker-compose.prod.yml` — reference для Coolify (web + worker + scheduler)
- [ ] `.env.example` для backend и frontend
- [ ] GitHub Actions: phpunit, eslint, build frontend
- [ ] `GET /api/health` — health check для Coolify

### Документация

- [ ] `README.md` — overview, quick start через Docker
- [ ] `docs/README.md` — оглавление
- [ ] `docs/development/setup.md` — быстрый старт
- [ ] `docs/development/docker.md` — сервисы, команды, troubleshooting
- [ ] `docs/deployment/coolify.md` — services, env vars, deploy flow
- [ ] `docs/design/tailadmin.md` — компоненты, кастомизация, маппинг экранов
- [ ] `CHANGELOG.md` — начальная версия 0.1.0

---

## UI-референс (фаза 0)

**Визуальный стиль:** [TailAdmin Vue Demo](https://free-vue-demo.tailadmin.com/)  
**Структура экранов:** [seo-reports.ru](https://seo-reports.ru/)

| Экран SEO Reports | Компонент TailAdmin |
|-------------------|---------------------|
| Dashboard layout | Sidebar + Header |
| Список проектов | Basic/Data Tables |
| Добавить проект | Modal + Form inputs + Alert |
| Auth | Sign In / Sign Up pages |
| Empty state | Card with icon |
| User menu | Header dropdown |

---

## Deliverables

1. `docker compose up` поднимает полный dev-стек (без нативного PHP/Node)
2. Пользователь регистрируется и логинится
3. CRUD проектов работает end-to-end
4. UI на базе TailAdmin: sidebar, tables, modals
5. Все основные страницы доступны (заглушки с layout)
6. CI проходит на push
7. Dockerfile и Coolify-ready конфигурация
8. Документация в `docs/` актуальна для фазы 0

---

## Критерии приёмки

- [ ] Регистрация нового пользователя по email + пароль
- [ ] Вход и выход из системы
- [ ] Создание проекта с name (обяз.) и domain (опц.)
- [ ] Список проектов отображается в таблице
- [ ] Empty state при отсутствии проектов
- [ ] Редактирование и удаление проекта
- [ ] Навигация между разделами без ошибок
- [ ] Неавторизованный пользователь не видит `/projects`
- [ ] UI соответствует TailAdmin (sidebar, tables, modals)
- [ ] `docs/development/docker.md` описывает запуск локально
- [ ] `docs/deployment/coolify.md` описывает prod-деплой

---

## Зависимости

- Нет (стартовый этап)

## Следующий этап

После завершения фазы 0 → [Фаза 0.5 — Админ-панель](./TZ-faza-0.5-admin.md)
