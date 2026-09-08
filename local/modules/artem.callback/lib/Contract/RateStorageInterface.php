<?php

declare(strict_types=1);

namespace Artem\Callback\Contract;

/**
 * Хранилище счётчиков для ограничителя частоты.
 *
 * В бою за ним стоит кеш Битрикса, в тестах — массив в памяти.
 */
interface RateStorageInterface
{
    public function get(string $key): int;

    public function increment(string $key, int $ttl): int;
}
