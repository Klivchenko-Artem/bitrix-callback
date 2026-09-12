<?php

declare(strict_types=1);

namespace Artem\Callback\Bitrix;

use Artem\Callback\Contract\RateStorageInterface;
use Artem\Callback\Model\RequestTable;
use Bitrix\Main\Type\DateTime;

/**
 * Счётчик заявок по таблице модуля.
 *
 * Хранилище на кеше ядра исчезает вместе с кешем: стоит выключить кеширование
 * в настройках главного модуля (обычное дело при отладке) — и защита от спама
 * перестаёт работать молча, потому что `initCache` всегда возвращает false.
 * Здесь считаются настоящие заявки, и выключить это нельзя ничем.
 *
 * Окно фиксированное, как и у кеш-хранилища: считаем заявки с этого адреса
 * за последние `ttl` секунд.
 */
final class DbRateStorage implements RateStorageInterface
{
    /** @var array<string, string> ключ счётчика → адрес посетителя */
    private array $addresses = [];

    private int $window = 3600;

    public function __construct(int $windowSeconds = 3600)
    {
        $this->window = max(1, $windowSeconds);
    }

    /**
     * Запоминает, какому адресу принадлежит ключ.
     *
     * Лимитер работает с хешированным ключом, а считать надо по адресу —
     * поэтому соответствие держим рядом.
     */
    public function remember(string $key, string $address): void
    {
        $this->addresses[$key] = $address;
    }

    public function get(string $key): int
    {
        $address = $this->addresses[$key] ?? null;

        if ($address === null || $address === '') {
            return 0;
        }

        $since = new DateTime();
        $since->add('-'.$this->window.' seconds');

        return (int) RequestTable::getCount([
            '=CLIENT_IP' => $address,
            '>=CREATED_AT' => $since,
        ]);
    }

    /**
     * Ничего не делает намеренно: счётчик растёт сам, когда заявка ложится
     * в таблицу. Отдельная запись означала бы двойной учёт.
     */
    public function increment(string $key, int $ttl): int
    {
        return $this->get($key) + 1;
    }
}
