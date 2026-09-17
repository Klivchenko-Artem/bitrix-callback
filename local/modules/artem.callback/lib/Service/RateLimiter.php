<?php

declare(strict_types=1);

namespace Artem\Callback\Service;

use Artem\Callback\Contract\RateStorageInterface;

/**
 * Не даёт слать заявки пачками с одного адреса.
 *
 * Форма открыта наружу без капчи, поэтому ограничение по IP это
 * единственное, что стоит между менеджером и ботом.
 */
final class RateLimiter
{
    public function __construct(
        private readonly RateStorageInterface $storage,
        private readonly int $limit = 3,
        private readonly int $periodSeconds = 3600,
    ) {
    }

    public function isAllowed(string $clientKey): bool
    {
        return $this->storage->get($clientKey) < $this->limit;
    }

    /**
     * Считает попытку и говорит, пропускать её или нет.
     */
    public function hit(string $clientKey): bool
    {
        if (!$this->isAllowed($clientKey)) {
            return false;
        }

        $this->storage->increment($clientKey, $this->periodSeconds);

        return true;
    }

    public function leftFor(string $clientKey): int
    {
        return max(0, $this->limit - $this->storage->get($clientKey));
    }
}
