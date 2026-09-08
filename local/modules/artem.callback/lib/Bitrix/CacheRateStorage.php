<?php

declare(strict_types=1);

namespace Artem\Callback\Bitrix;

use Artem\Callback\Contract\RateStorageInterface;
use Bitrix\Main\Data\Cache;

/**
 * Счётчики попыток в кеше Битрикса.
 *
 * Кеш просят на сутки, а реальный срок жизни лежит внутри значения:
 * иначе на чтении пришлось бы знать период, с которым счётчик писали.
 */
final class CacheRateStorage implements RateStorageInterface
{
    private const CACHE_DIR = '/artem.callback/rate';
    private const CACHE_TTL = 86400;

    public function get(string $key): int
    {
        $item = $this->read($key);

        return $item !== null && $item['expires'] > time() ? $item['count'] : 0;
    }

    public function increment(string $key, int $ttl): int
    {
        $item = $this->read($key);
        $alive = $item !== null && $item['expires'] > time();

        $count = $alive ? $item['count'] + 1 : 1;
        $expires = $alive ? $item['expires'] : time() + $ttl;

        $cache = Cache::createInstance();
        $cache->clean($key, self::CACHE_DIR);
        $cache->initCache(self::CACHE_TTL, $key, self::CACHE_DIR);
        $cache->startDataCache();
        $cache->endDataCache(['count' => $count, 'expires' => $expires]);

        return $count;
    }

    /**
     * @return array{count: int, expires: int}|null
     */
    private function read(string $key): ?array
    {
        $cache = Cache::createInstance();

        if (!$cache->initCache(self::CACHE_TTL, $key, self::CACHE_DIR)) {
            return null;
        }

        $vars = $cache->getVars();

        if (!is_array($vars) || !isset($vars['count'], $vars['expires'])) {
            return null;
        }

        return ['count' => (int) $vars['count'], 'expires' => (int) $vars['expires']];
    }
}
