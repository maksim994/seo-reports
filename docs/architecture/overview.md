# Архитектура

> Документ будет дополняться в Фазе 1.

## Общая схема

См. диаграмму в [TZ.md](../../TZ.md#2-технологический-стек).

## Слои

| Слой | Технология |
|------|------------|
| Frontend | Vue 3 + TailAdmin + Pinia |
| API | Laravel 11 + Sanctum |
| Queue | Redis + Laravel Queue |
| Storage | S3 (MinIO dev / external prod) |
| DB | PostgreSQL 16 |

## Report Generation Pipeline

1. **Resolve** — project, template, blocks, integrations
2. **Fetch** — parallel API calls (Bus::batch)
3. **Transform** — normalize to ReportDataset
4. **Calculate** — period comparison, KPI
5. **Render** — HTML → PDF/DOCX/ODT
6. **Store** — S3 + report_files record

Подробнее о блоках: [report-blocks.md](./report-blocks.md)
