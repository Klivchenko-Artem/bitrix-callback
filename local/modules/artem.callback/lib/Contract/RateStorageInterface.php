<?php

declare(strict_types=1);

namespace Artem\Callback\Contract;

/**
 * Хранилище счётчиков для ограничителя частоты.
 *
 * На сайте форма считает по таблице заявок (DbRateStorage), в тестах
 * используется массив в памяти. Ключ это адрес посетителя как есть.
 */
interface RateStorageInterface
{
    public function get(string $key): int;

    public function increment(string $key, int $ttl): int;
}
