<?php

declare(strict_types=1);

namespace Artem\Callback\Bitrix;

use Artem\Callback\CallbackRequest;
use Artem\Callback\Config;
use Artem\Callback\Service\PhoneNormalizer;
use Bitrix\Main\Mail\Event;

/**
 * Письмо менеджеру через почтовое событие: шаблон правится в админке,
 * а не в коде.
 */
final class Notifier
{
    public const EVENT_TYPE = 'ARTEM_CALLBACK_NEW';

    public function __construct(private readonly PhoneNormalizer $phones = new PhoneNormalizer())
    {
    }

    public function notify(CallbackRequest $request, string $siteId): bool
    {
        $recipients = Config::getRecipients();

        if ($recipients === []) {
            return false;
        }

        return Event::send([
            'EVENT_NAME' => self::EVENT_TYPE,
            'LID' => $siteId,
            'C_FIELDS' => [
                'NAME' => $request->name,
                'PHONE' => $this->phones->format($request->phone) ?? $request->phone,
                'COMMENT' => $request->comment !== '' ? $request->comment : '-',
                'SLOT' => $request->slot !== '' ? $request->slot : 'не важно',
                'PAGE_URL' => $request->pageUrl,
                'EMAIL_TO' => implode(', ', $recipients),
            ],
        ])->isSuccess();
    }
}
