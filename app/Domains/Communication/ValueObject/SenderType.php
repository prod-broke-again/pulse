<?php

declare(strict_types=1);

namespace App\Domains\Communication\ValueObject;

enum SenderType: string
{
    case Client = 'client';
    case Moderator = 'moderator';
    case System = 'system';
}
