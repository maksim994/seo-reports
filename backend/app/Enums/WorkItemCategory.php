<?php

namespace App\Enums;

enum WorkItemCategory: string
{
    case Seo = 'seo';
    case Content = 'content';
    case Technical = 'technical';

    public function label(): string
    {
        return match ($this) {
            self::Seo => 'SEO',
            self::Content => 'Контент',
            self::Technical => 'Техническое',
        };
    }
}
