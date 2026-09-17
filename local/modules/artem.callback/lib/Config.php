<?php

declare(strict_types=1);

namespace Artem\Callback;

use Bitrix\Main\Config\Option;

/**
 * Настройки модуля из административной страницы, с разумными значениями
 * по умолчанию, чтобы компонент работал сразу после установки.
 */
final class Config
{
    public const MODULE_ID = 'artem.callback';

    public const DEFAULTS = [
        'email_to' => '',
        'rate_limit' => '3',
        'rate_period' => '60',
        'slots' => "09:00-12:00\n12:00-15:00\n15:00-18:00",
        'consent_required' => 'Y',
        'comment_required' => 'N',
        'max_comment' => '1000',
    ];

    public static function get(string $name): string
    {
        return (string) Option::get(self::MODULE_ID, $name, self::DEFAULTS[$name] ?? '');
    }

    /**
     * Границы числовых настроек. Ноль в лимите глушил бы форму на всём сайте,
     * поэтому значение за границей прижимается к ней и при чтении, и при записи.
     */
    public const LIMITS = [
        'rate_limit' => ['min' => 1, 'max' => 1000],
        'rate_period' => ['min' => 1, 'max' => 1440],
        'max_comment' => ['min' => 10, 'max' => 2000],
    ];

    /** Столько вмещает колонка SLOT. */
    public const MAX_SLOT_LENGTH = 50;

    public static function getInt(string $name): int
    {
        return self::clamp($name, (int) self::get($name));
    }

    public static function clamp(string $name, int $value): int
    {
        if (!isset(self::LIMITS[$name])) {
            return $value;
        }

        return max(self::LIMITS[$name]['min'], min(self::LIMITS[$name]['max'], $value));
    }

    public static function isOn(string $name): bool
    {
        return self::get($name) === 'Y';
    }

    /**
     * @return list<string>
     */
    public static function getSlots(): array
    {
        $lines = preg_split('/\R/', self::get('slots')) ?: [];

        return array_values(array_filter(
            array_map('trim', $lines),
            static fn (string $s): bool => $s !== '' && mb_strlen($s) <= self::MAX_SLOT_LENGTH
        ));
    }

    /**
     * @return list<string>
     */
    public static function getRecipients(): array
    {
        $emails = explode(',', self::get('email_to'));

        return array_values(array_filter(array_map('trim', $emails), static fn (string $s): bool => $s !== ''));
    }
}
