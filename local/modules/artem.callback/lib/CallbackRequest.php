<?php

declare(strict_types=1);

namespace Artem\Callback;

/**
 * Заявка на обратный звонок в том виде, в каком она уходит в инфоблок и письмо.
 */
final class CallbackRequest
{
    public function __construct(
        public readonly string $name,
        public readonly string $phone,
        public readonly string $comment = '',
        public readonly string $slot = '',
        public readonly string $pageUrl = '',
        public readonly string $clientIp = '',
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'NAME' => $this->name,
            'PHONE' => $this->phone,
            'COMMENT' => $this->comment,
            'SLOT' => $this->slot,
            'PAGE_URL' => $this->pageUrl,
            'CLIENT_IP' => $this->clientIp,
        ];
    }
}
