<?php

declare(strict_types=1);

namespace Artem\Callback\Tests\Support;

use Artem\Callback\Contract\RateStorageInterface;

/**
 * Хранилище счётчиков в памяти для тестов лимитера.
 */
final class ArrayRateStorage implements RateStorageInterface
{
    /** @var array<string, array{count: int, expires: int}> */
    private array $items = [];

    public function __construct(private int $now = 0)
    {
    }

    public function get(string $key): int
    {
        $item = $this->items[$key] ?? null;

        if ($item === null || $item['expires'] <= $this->now) {
            return 0;
        }

        return $item['count'];
    }

    public function increment(string $key, int $ttl): int
    {
        $item = $this->items[$key] ?? null;
        $alive = $item !== null && $item['expires'] > $this->now;

        // Срок ставится при первом попадании и дальше не сдвигается
        $count = $alive ? $item['count'] + 1 : 1;
        $expires = $alive ? $item['expires'] : $this->now + $ttl;

        $this->items[$key] = ['count' => $count, 'expires' => $expires];

        return $count;
    }

    public function travel(int $seconds): void
    {
        $this->now += $seconds;
    }
}
