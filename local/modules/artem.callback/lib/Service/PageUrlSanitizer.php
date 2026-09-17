<?php

declare(strict_types=1);

namespace Artem\Callback\Service;

/**
 * Приводит адрес страницы к тому, что можно и записать, и показать ссылкой.
 */
final class PageUrlSanitizer
{
    /** Ровно столько вмещает колонка PAGE_URL. */
    public const MAX_LENGTH = 500;

    public static function isSafe(string $url): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        // javascript: и data: в href админки выполнились бы в сессии администратора
        return in_array($scheme, ['http', 'https'], true);
    }

    public static function sanitize(string $url): string
    {
        $url = trim($url);

        if ($url === '' || !self::isSafe($url)) {
            return '';
        }

        return mb_substr($url, 0, self::MAX_LENGTH);
    }
}
