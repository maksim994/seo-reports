# Блоки отчёта

> Каталог блоков будет заполняться в Фазе 1.

## Интерфейс блока

```php
interface ReportBlockInterface {
    public function supports(Project $project, array $integrations): bool;
    public function fetch(Period $period, ?Period $compare): BlockData;
    public function render(BlockData $data): HtmlFragment;
}
```

## MVP blocks (planned)

| block_type | Категория | Required integration |
|------------|-----------|---------------------|
| `title_page` | Общие | — |
| `table_of_contents` | Общие | — |
| `text_block` | Общие | — |
| `metrika_overview` | Яндекс.Метрика | yandex_metrika |
| `metrika_traffic_sources` | Яндекс.Метрика | yandex_metrika |
| `metrika_goals` | Яндекс.Метрика | yandex_metrika |
| `ga_overview` | Google Analytics | google_analytics |
| `ga_channels` | Google Analytics | google_analytics |
| `gsc_top_queries` | Search Console | google_search_console |
| `webmaster_queries` | Яндекс.Вебмастер | yandex_webmaster |

## Фаза 1.5

| block_type | Категория |
|------------|-----------|
| `positions_visibility` | SEO / Позиции |
| `positions_top_distribution` | SEO / Позиции |
| `positions_table` | SEO / Позиции |
| `keyword_segmentation` | SEO |
| `work_performed` | Общие |
| `economics_kpi` | Экономика |
