<?php

declare(strict_types=1);

namespace Artem\Callback\Bitrix;

use Artem\Callback\CallbackRequest;
use Artem\Callback\Model\RequestTable;
use Bitrix\Main\Type\DateTime;

/**
 * Заявки в базе: запись, поиск свежего дубля, смена статуса.
 */
final class RequestRepository
{
    public function save(CallbackRequest $request, ?string $siteId = null): int
    {
        $result = RequestTable::add([
            'CREATED_AT' => new DateTime(),
            'NAME' => $request->name,
            'PHONE' => $request->phone,
            'COMMENT' => $request->comment,
            'SLOT' => $request->slot,
            'PAGE_URL' => $request->pageUrl,
            'CLIENT_IP' => $request->clientIp,
            'STATUS' => RequestTable::STATUS_NEW,
            'SITE_ID' => $siteId,
        ]);

        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }

        return (int) $result->getId();
    }

    /**
     * Тот же телефон за последние N минут почти всегда двойной клик,
     * а не второй вопрос. Менеджеру такое слать не надо.
     */
    public function hasRecent(string $phone, int $minutes = 5): bool
    {
        $since = new DateTime();
        $since->add('-' . $minutes . ' minutes');

        $row = RequestTable::query()
            ->setSelect(['ID'])
            ->where('PHONE', $phone)
            ->where('CREATED_AT', '>=', $since)
            ->setLimit(1)
            ->fetch();

        return $row !== false && $row !== null;
    }

    public function setStatus(int $id, string $status): void
    {
        RequestTable::update($id, ['STATUS' => $status]);
    }

    public function delete(int $id): void
    {
        RequestTable::delete($id);
    }
}
