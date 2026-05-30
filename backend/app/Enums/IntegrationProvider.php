<?php

namespace App\Enums;

enum IntegrationProvider: string
{
    case YandexMetrika = 'yandex_metrika';
    case GoogleAnalytics = 'google_analytics';
    case YandexWebmaster = 'yandex_webmaster';
    case YandexWordstat = 'yandex_wordstat';
    case GoogleSearchConsole = 'google_search_console';
    case Topvisor = 'topvisor';
    case KeysSo = 'keys_so';

    public function label(): string
    {
        return match ($this) {
            self::YandexMetrika => 'Яндекс.Метрика',
            self::GoogleAnalytics => 'Google Analytics 4',
            self::YandexWebmaster => 'Яндекс.Вебмастер',
            self::YandexWordstat => 'Яндекс Вордстат',
            self::GoogleSearchConsole => 'Google Search Console',
            self::Topvisor => 'Topvisor',
            self::KeysSo => 'Keys.so',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::YandexMetrika => 'Посещаемость, источники трафика, цели',
            self::GoogleAnalytics => 'Аналитика сайта и каналы привлечения',
            self::YandexWebmaster => 'Поисковые запросы и индексация в Яндексе',
            self::YandexWordstat => 'Динамика спроса и популярные запросы',
            self::GoogleSearchConsole => 'Запросы и позиции в Google',
            self::Topvisor => 'Позиции, видимость и TOP-N по ключевым фразам',
            self::KeysSo => 'Мониторинг позиций и видимость через Keys.so',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::YandexMetrika => '📊',
            self::GoogleAnalytics => '📈',
            self::YandexWebmaster => '🔍',
            self::YandexWordstat => '📉',
            self::GoogleSearchConsole => '🌐',
            self::Topvisor => '📍',
            self::KeysSo => '🔑',
        };
    }

    public function isAnalytics(): bool
    {
        return in_array($this, [self::YandexMetrika, self::GoogleAnalytics], true);
    }

    public static function tryFromString(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
