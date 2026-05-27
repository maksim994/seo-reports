# Changelog

Формат основан на [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Added

- **Фаза 1:** генератор отчётов — очередь, HTML/PDF, блок Метрики, история, UI генератора
- **Фаза 1:** конструктор шаблонов — CRUD, каталог 10 блоков, dual-list редактор
- **Фаза 1 (начало):** Google OAuth, listResources, UI привязки ресурсов к проектам
- **Фаза 1 (начало):** каркас OAuth-интеграций — миграции, API, провайдеры, UI «Источники данных»
- **Фаза 0.5:** Админ-панель — API пользователей/настроек/проектов, middleware `admin`, `make:admin`, UI `/admin/*`
- Публичные настройки `/api/settings/public` (регистрация, maintenance)
- Блокировка пользователей и отключение регистрации через настройки
- **Фаза 0:** Docker Compose dev-стек, Laravel 11 API (auth, projects CRUD), Vue 3 + TailAdmin-style UI
- Sanctum SPA authentication (register, login, logout)
- Health endpoint `/api/health`
- PHPUnit tests для auth и projects
- Dockerfile multi-stage (dev + production)
- docker-compose.prod.yml reference для Coolify

### Fixed

- Исправлена генерация `APP_KEY` в Docker entrypoint (не допускает битый ключ в `.env`)
- Документирована команда восстановления ключа в `docs/development/setup.md`

### Added (TZ)

- Модуль **4.10 Админ-панель** — пользователи, настройки сервиса
- [TZ-faza-0.5-admin.md](./TZ-faza-0.5-admin.md) — этап реализации админки

- Техническое задание (TZ.md) и этапы (TZ-faza-*.md)
- Структура документации (`docs/`)
- Решения: Docker Compose (local), Coolify (prod), TailAdmin (UI)
