<?php

declare(strict_types=1);

namespace App\Domains\Integration\ValueObject;

enum SourceType: string
{
    case Web = 'web';
    case Vk = 'vk';
    case Tg = 'tg';
}
