<?php

declare(strict_types=1);

namespace Artem\Callback\Tests\Support;

use Artem\Callback\Contract\RateStorageInterface;

/**
 * Хранилище счётчиков в памяти — вместо кеша Битрикса в тестах.
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
        $count = $this->get($key) + 1;
        $this->items[$key] = ['count' => $count, 'expires' => $this->now + $ttl];

        return $count;
    }

    public function travel(int $seconds): void
    {
        $this->now += $seconds;
    }
}
