<?php

declare(strict_types=1);

namespace App\Support;

final class DepartmentIcons
{
    public const ALLOWED = [
        'Building2', 'Users', 'ShoppingCart', 'Headphones', 'CreditCard',
        'Truck', 'Phone', 'Mail', 'FileText', 'BarChart3', 'MessageCircle',
        'Settings', 'Wrench', 'Scale', 'UserPlus', 'Shield', 'Package',
        'Briefcase', 'HelpCircle', 'AlertTriangle', 'Heart', 'Globe',
        'Tag', 'Zap', 'Clipboard',
    ];

    public const FALLBACK = 'Building2';

    public static function isValid(string $icon): bool
    {
        return in_array($icon, self::ALLOWED, true);
    }

    public static function normalize(?string $icon): string
    {
        return ($icon !== null && $icon !== '' && self::isValid($icon))
            ? $icon
            : self::FALLBACK;
    }
}
