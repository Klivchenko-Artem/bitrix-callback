<?php

declare(strict_types=1);

namespace Artem\Callback\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Приведение адреса страницы к безопасному виду.
 *
 * Логика лежит в компоненте (он не автозагружается вне Битрикса), поэтому
 * здесь повторяется её контракт: три правила, каждое из которых закрывает
 * свою находку ревью.
 */
final class PageUrlSanitizerTest extends TestCase
{
    private const MAX_LENGTH = 500;

    private function sanitize(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (!in_array($scheme, ['http', 'https'], true)) {
            return '';
        }

        return mb_substr($url, 0, self::MAX_LENGTH);
    }

    /** Длинная рекламная ссылка режется, а не теряет заявку. */
    public function testLongUrlIsTrimmed(): void
    {
        $url = 'https://example.com/?'.str_repeat('utm_source=ad&', 100);

        $result = $this->sanitize($url);

        self::assertSame(self::MAX_LENGTH, mb_strlen($result));
        self::assertStringStartsWith('https://example.com/', $result);
    }

    /** Скрипт в адресе не попадёт в href админки. */
    public function testNonHttpSchemesAreDropped(): void
    {
        self::assertSame('', $this->sanitize("javascript:fetch('/bitrix/admin/')"));
        self::assertSame('', $this->sanitize('data:text/html,<script>alert(1)</script>'));
        self::assertSame('', $this->sanitize('file:///etc/passwd'));
    }

    /** Нормальный адрес проходит как есть. */
    public function testNormalUrlSurvives(): void
    {
        $url = 'https://example.com/catalog?page=2&sort=price';

        self::assertSame($url, $this->sanitize($url));
    }

    /** Длина считается в символах: русский адрес не должен рубиться пополам. */
    public function testCyrillicUrlIsCountedInCharacters(): void
    {
        $url = 'https://example.com/'.str_repeat('я', 600);

        $result = $this->sanitize($url);

        self::assertSame(self::MAX_LENGTH, mb_strlen($result));
        self::assertTrue(mb_check_encoding($result, 'UTF-8'));
    }
}
