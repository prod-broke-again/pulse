<?php

declare(strict_types=1);

namespace App\Domains\Department\ValueObject;

enum DepartmentCategory: string
{
    case Support = 'support';
    case Registration = 'registration';
    case Tech = 'tech';
    case Ethics = 'ethics';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Support => 'Поддержка',
            self::Registration => 'Регистрация',
            self::Tech => 'Техподдержка',
            self::Ethics => 'Этика',
            self::Other => 'Другое',
        };
    }
}
