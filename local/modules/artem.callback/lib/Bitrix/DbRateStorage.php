<?php

declare(strict_types=1);

namespace Artem\Callback\Bitrix;

use Artem\Callback\Contract\RateStorageInterface;
use Artem\Callback\Model\RequestTable;
use Bitrix\Main\Type\DateTime;

/**
 * Счётчик заявок по таблице модуля. Ключ здесь это адрес посетителя.
 *
 * Кеш ядра можно выключить в настройках главного модуля, и защита от спама
 * пропала бы вместе с ним. Таблицу заявок не выключишь.
 *
 * Окно скользящее: считаются заявки с адреса за последние N секунд.
 */
final class DbRateStorage implements RateStorageInterface
{
    private int $window;

    public function __construct(int $windowSeconds = 3600)
    {
        $this->window = max(1, $windowSeconds);
    }

    public function get(string $key): int
    {
        if ($key === '') {
            return 0;
        }

        $since = new DateTime();
        $since->add('-'.$this->window.' seconds');

        return (int) RequestTable::getCount([
            '=CLIENT_IP' => $key,
            '>=CREATED_AT' => $since,
        ]);
    }

    /**
     * Сам ничего не пишет: попыткой считается строка заявки, которую сохранят
     * следом под той же блокировкой. Возвращает счёт с учётом этой заявки.
     */
    public function increment(string $key, int $ttl): int
    {
        return $this->get($key) + 1;
    }
}
