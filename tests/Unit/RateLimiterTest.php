<?php

declare(strict_types=1);

namespace Artem\Callback\Tests\Unit;

use Artem\Callback\Service\RateLimiter;
use Artem\Callback\Tests\Support\ArrayRateStorage;
use PHPUnit\Framework\TestCase;

final class RateLimiterTest extends TestCase
{
    public function testAllowsUpToLimit(): void
    {
        $limiter = new RateLimiter(new ArrayRateStorage(), limit: 3, periodSeconds: 3600);

        self::assertTrue($limiter->hit('1.2.3.4'));
        self::assertTrue($limiter->hit('1.2.3.4'));
        self::assertTrue($limiter->hit('1.2.3.4'));
        self::assertFalse($limiter->hit('1.2.3.4'));
    }

    public function testCountsEachClientSeparately(): void
    {
        $limiter = new RateLimiter(new ArrayRateStorage(), limit: 1);

        self::assertTrue($limiter->hit('1.2.3.4'));
        self::assertTrue($limiter->hit('5.6.7.8'));
        self::assertFalse($limiter->hit('1.2.3.4'));
    }

    public function testForgetsAfterPeriod(): void
    {
        $storage = new ArrayRateStorage();
        $limiter = new RateLimiter($storage, limit: 1, periodSeconds: 60);

        self::assertTrue($limiter->hit('1.2.3.4'));
        self::assertFalse($limiter->hit('1.2.3.4'));

        $storage->travel(61);

        self::assertTrue($limiter->hit('1.2.3.4'));
    }

    public function testReportsAttemptsLeft(): void
    {
        $limiter = new RateLimiter(new ArrayRateStorage(), limit: 2);

        self::assertSame(2, $limiter->leftFor('1.2.3.4'));
        $limiter->hit('1.2.3.4');
        self::assertSame(1, $limiter->leftFor('1.2.3.4'));
    }
}
