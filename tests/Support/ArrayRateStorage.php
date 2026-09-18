<?php

declare(strict_types=1);

namespace Artem\Callback\Tests\Support;

use Artem\Callback\Contract\RateStorageInterface;

/**
 * Хранилище счётчиков в памяти для тестов лимитера.
 *
 * Повторяет DbRateStorage: окно скользящее, считаются попытки
 * за последние N секунд, включая границу.
 */
final class ArrayRateStorage implements RateStorageInterface
{
    /** @var array<string, list<int>> время каждой попытки по ключу */
    private array $hits = [];

    private int $window;

    public function __construct(int $windowSeconds = 3600, private int $now = 0)
    {
        $this->window = max(1, $windowSeconds);
    }

    public function get(string $key): int
    {
        $since = $this->now - $this->window;

        return count(array_filter($this->hits[$key] ?? [], static fn (int $at): bool => $at >= $since));
    }

    public function increment(string $key, int $ttl): int
    {
        $this->hits[$key][] = $this->now;

        return $this->get($key);
    }

    public function travel(int $seconds): void
    {
        $this->now += $seconds;
    }
}
