<?php

declare(strict_types=1);

namespace Artem\Callback\Tests\Unit;

use Artem\Callback\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function testValueOutsideBoundsIsClampedToTheBound(): void
    {
        self::assertSame(1000, Config::clamp('rate_limit', 5000));
        self::assertSame(1, Config::clamp('rate_limit', 0));
        self::assertSame(1440, Config::clamp('rate_period', 1500));
    }

    public function testValueInsideBoundsIsKept(): void
    {
        self::assertSame(3, Config::clamp('rate_limit', 3));
    }

    public function testUnknownSettingIsNotTouched(): void
    {
        self::assertSame(-5, Config::clamp('something_else', -5));
    }
}
