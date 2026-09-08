<?php

declare(strict_types=1);

namespace Artem\Callback\Tests\Unit;

use Artem\Callback\Service\PhoneNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PhoneNormalizerTest extends TestCase
{
    private PhoneNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new PhoneNormalizer();
    }

    #[DataProvider('validNumbers')]
    public function testNormalizesToSingleFormat(string $input, string $expected): void
    {
        self::assertSame($expected, $this->normalizer->normalize($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function validNumbers(): array
    {
        return [
            'с восьмёркой' => ['8 (900) 123-45-67', '+79001234567'],
            'с плюс семь' => ['+7 900 123 45 67', '+79001234567'],
            'без кода страны' => ['9001234567', '+79001234567'],
            'с мусором вокруг' => ['тел.: 8-900-123-45-67 (моб)', '+79001234567'],
            'городской' => ['84951234567', '+74951234567'],
        ];
    }

    #[DataProvider('invalidNumbers')]
    public function testRejectsNonPhones(string $input): void
    {
        self::assertNull($this->normalizer->normalize($input));
        self::assertFalse($this->normalizer->isValid($input));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidNumbers(): array
    {
        return [
            'пусто' => [''],
            'слишком коротко' => ['123456'],
            'слишком длинно' => ['890012345678'],
            'буквы' => ['позвоните мне'],
            'несуществующий код' => ['+7 100 123-45-67'],
        ];
    }

    public function testFormatsForHumans(): void
    {
        self::assertSame('+7 (900) 123-45-67', $this->normalizer->format('89001234567'));
        self::assertNull($this->normalizer->format('нет'));
    }
}
