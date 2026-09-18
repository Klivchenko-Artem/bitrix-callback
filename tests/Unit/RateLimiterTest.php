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
        $storage = new ArrayRateStorage(windowSeconds: 60);
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

    /**
     * Нулевой лимит означал форму, которая не принимает ничего.
     *
     * Настройки модуля писались из POST без проверки, а админ мог поставить 0
     * в смысле «без ограничений» (или просто очистить поле: пустое number
     * приезжает нулём). Границы теперь прижимаются в options.php, но само
     * поведение лимитера тоже стоит зафиксировать: с нулём он не пропускает
     * ничего, и полагаться на «авось не поставят» нельзя.
     */
    public function testZeroLimitBlocksEverything(): void
    {
        $limiter = new RateLimiter(new ArrayRateStorage(), limit: 0, periodSeconds: 3600);

        self::assertFalse($limiter->hit('1.2.3.4'));
        self::assertSame(0, $limiter->leftFor('1.2.3.4'));
    }

    /**
     * Окно скользящее: попытка выпадает из счёта ровно через период после
     * неё самой, а не после первой попытки, как было бы в фиксированном окне.
     */
    public function testWindowSlides(): void
    {
        $storage = new ArrayRateStorage(windowSeconds: 60);
        $limiter = new RateLimiter($storage, limit: 2, periodSeconds: 60);

        self::assertTrue($limiter->hit('1.2.3.4'));   // t=0
        $storage->travel(40);
        self::assertTrue($limiter->hit('1.2.3.4'));   // t=40
        $storage->travel(10);
        self::assertFalse($limiter->hit('1.2.3.4'));  // t=50, в окне обе

        // Граница окна включается, как в запросе по таблице
        $storage->travel(10);
        self::assertFalse($limiter->hit('1.2.3.4'));  // t=60

        $storage->travel(1);
        self::assertTrue($limiter->hit('1.2.3.4'));   // t=61, первая выпала

        // Фиксированное окно с t=61 открыло бы новое на две попытки,
        // а скользящее ещё видит попытку с t=40
        self::assertFalse($limiter->hit('1.2.3.4'));
    }

    /** Нулевой период прижимается к секунде, а не выключает лимит. */
    public function testZeroPeriodStillLimits(): void
    {
        $limiter = new RateLimiter(new ArrayRateStorage(windowSeconds: 0), limit: 1, periodSeconds: 0);

        self::assertTrue($limiter->hit('1.2.3.4'));
        self::assertFalse($limiter->hit('1.2.3.4'));
    }
}
