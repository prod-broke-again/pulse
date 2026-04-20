<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\DepartmentIcons;
use PHPUnit\Framework\TestCase;

final class DepartmentIconsTest extends TestCase
{
    public function test_normalize_valid_passes_through(): void
    {
        $this->assertSame('Headphones', DepartmentIcons::normalize('Headphones'));
    }

    public function test_normalize_invalid_returns_fallback(): void
    {
        $this->assertSame(DepartmentIcons::FALLBACK, DepartmentIcons::normalize('NotAnIcon'));
    }

    public function test_normalize_null_returns_fallback(): void
    {
        $this->assertSame(DepartmentIcons::FALLBACK, DepartmentIcons::normalize(null));
    }

    public function test_is_valid(): void
    {
        $this->assertTrue(DepartmentIcons::isValid('Wrench'));
        $this->assertFalse(DepartmentIcons::isValid(''));
        $this->assertFalse(DepartmentIcons::isValid('Fake'));
    }
}
