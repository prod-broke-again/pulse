<?php

declare(strict_types=1);

namespace App\Domains\Communication\ValueObject;

enum ChatStatus: string
{
    case New = 'new';
    case Active = 'active';
    case Closed = 'closed';
}
