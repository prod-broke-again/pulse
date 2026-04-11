<?php

declare(strict_types=1);

namespace App\Domains\Integration\ValueObject;

enum SourceType: string
{
    case Web = 'web';
    case Vk = 'vk';
    case Tg = 'tg';
    case Max = 'max';

    public function label(): string
    {
        return match ($this) {
            self::Web => 'Web',
            self::Vk => 'VK',
            self::Tg => 'Telegram',
            self::Max => 'MAX',
        };
    }
}
