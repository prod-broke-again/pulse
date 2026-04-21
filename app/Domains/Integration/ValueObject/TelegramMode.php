<?php

declare(strict_types=1);

namespace App\Domains\Integration\ValueObject;

enum TelegramMode: string
{
    case Direct = 'direct';
    case Business = 'business';

    /**
     * @param  array<string, mixed>  $settings
     */
    public static function fromSettings(array $settings): self
    {
        $raw = $settings['telegram_mode'] ?? self::Direct->value;

        return self::tryFrom(is_string($raw) ? $raw : self::Direct->value) ?? self::Direct;
    }
}
