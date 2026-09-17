<?php

declare(strict_types=1);

namespace Artem\Callback\Bitrix;

use Artem\Callback\Config;

/**
 * Ошибки модуля в журнал событий Битрикса (Настройки, Журнал событий).
 *
 * AddMessage2Log пишет только при заданной LOG_FILENAME, а на обычном сайте
 * её нет, так что сбои туда уходили в никуда.
 */
final class Log
{
    public const AUDIT_TYPE = 'ARTEM_CALLBACK_ERROR';

    public static function error(string $message): void
    {
        \CEventLog::Add([
            'SEVERITY' => 'ERROR',
            'AUDIT_TYPE_ID' => self::AUDIT_TYPE,
            'MODULE_ID' => Config::MODULE_ID,
            'DESCRIPTION' => $message,
        ]);
    }
}
